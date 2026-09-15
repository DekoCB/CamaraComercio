# Modelo de datos

Ver decisiones y justificación en [`PROJECT_ANALYSIS.md`](PROJECT_ANALYSIS.md) (sección 4 para el diseño original, sección 10 para el pivote a Laravel — el esquema en sí no cambió con la migración de framework, solo su forma de definición: migraciones Laravel en vez de SQL a mano).

## Diagrama conceptual

```
roles ──< users
roles ──< role_permissions >── permissions
roles ──< role_modules >── modules

associates ──< invoices ──< payments
users ──< password_reset_tokens (nativa de Laravel, keyed por email)
users ──< audit_logs (nullable, SET NULL si se borra el usuario)
users ──< invoices.created_by (nullable, SET NULL si se borra el usuario)
users ──< payments.registered_by (NOT NULL, RESTRICT — no se puede borrar
                                    un usuario que registró algún pago)
```

## Tablas

### roles
Catálogo de roles (Administrador, Encargado de Cobranzas, y los que el administrador cree). `name` es único.

### permissions
Catálogo de códigos de permiso (`associates.manage`, `billing.generate`, `admin.users`, etc.). Un permiso representa una acción concreta que el backend verifica en cada request vía `Gate::before()` + middleware `can:codigo` — nunca se confía solo en ocultar botones en el frontend. Ver `docs/ARCHITECTURE.md`.

### role_permissions
Tabla puente `role_id` + `permission_id` (clave primaria compuesta). Define qué puede *hacer* cada rol. Modelada como relación `belongsToMany` entre `Role` y `Permission`.

### modules
Catálogo de módulos de navegación (`dashboard`, `associates`, `billing`, `payments`, `portfolio`, `reports`, `administration`). `is_active` controla si el módulo aparece en el menú para cualquier rol que tenga acceso a él (HU-21/HU-22).

### role_modules
Tabla puente `role_id` + `module_id`. Define qué puede *ver* cada rol en la navegación (independiente de qué puede *hacer* — ver `role_permissions`).

### users
Cuentas de acceso al sistema (tabla base de Laravel + columnas propias añadidas en migraciones posteriores: `role_id`, `is_active`, `avatar_path`). La contraseña se guarda con el cast `'password' => 'hashed'` de Eloquent (bcrypt vía `Hash::make`) — nunca se almacena ni se expone en texto plano (`$hidden = ['password', 'remember_token']`). `email` es único. `avatar_path` (nullable) guarda la ruta relativa de la foto de perfil dentro del disco `public` (`storage/app/public/avatars`, servida vía `php artisan storage:link`); cada usuario edita su propia foto/nombre/correo/contraseña desde `/profile` (autoservicio, sin permiso de rol adicional).

### password_reset_tokens
Tabla **nativa de Laravel** (no una tabla de diseño propio): clave primaria `email`, `token` (hash, generado por el `PasswordBroker` de Laravel) y `created_at`. El broker nativo (`Password::sendResetLink` / `Password::reset`) gestiona expiración (`config('auth.passwords.users.expire')`, 60 minutos por defecto) y uso único (el registro se borra al completar el reset) — ver HU-03 y la nota D9 en `PROJECT_ANALYSIS.md`.

### associates
Asociados de la Cámara de Comercio (HU-04/HU-05). Desde el 15/09/2026 la tabla replica columna a columna el padrón Excel de la Cámara ("DATA DE ASOCIADOS", columnas C–AJ + OBSERVACIONES); las columnas de aportes mensuales (AK en adelante) **no** se guardan aquí — son facturas y pagos.

| Columna Excel | Campo | Notas |
|---|---|---|
| ESTADO | `status` | `ACTIVO` / `SUSPENDIDO` / `DESAFILIADO`. `is_active` se **deriva** de `status` en el modelo (`saving`): solo `ACTIVO` factura. Escribir `is_active` directamente sigue funcionando (el modelo ajusta `status`). |
| ULT. MES PAGO | — | No se almacena: `Associate::lastPaidPeriod()` lo calcula desde las facturas `PAGADA`. |
| SECTORISTA / CAT. / MONTO A PAGAR | `sectorista`, `category`, `monthly_fee` | `monthly_fee` (nullable) tiene prioridad sobre el monto del lote al generar facturas (`InvoiceGenerationService`). |
| FECHA DE INGRESO / TIPO DE PERSONA / FECHA DE ANIVERSARIO | `joined_at`, `person_type`, `anniversary_date` | `person_type`: `PERSONA JURÍDICA` / `PERSONA NATURAL`. |
| RUC / RAZON SOCIAL / NOMBRE COMERCIAL | `ruc`, `name`, `company` | `name` = razón social; `company` = nombre comercial. RUC único (11 dígitos) cuando está presente. |
| DIRECCIÓN DE FACTURACIÓN / DISTRITO / DIRECCION DE CORRESPONDENCIA / DISTRITO DE CORRESPONDENCIA | `billing_address`, `billing_district`, `mailing_address`, `mailing_district` | |
| SEGÚN SU TAMAÑO / SEGÚN SU ACTIVIDAD / COMITÉ SECTORIAL / CIIU / SUB SECTOR | `company_size`, `activity_type`, `sector_committee`, `ciiu`, `sub_sector` | Tamaño y actividad son texto libre con sugerencias (`Associate::COMPANY_SIZES`, `ACTIVITY_TYPES`). |
| CORREO DE LA EMPRESA | `email` | Único cuando está presente. `contact_phone` (teléfono de la empresa) se conserva aunque no exista en el Excel. |
| REPRESENTANTE LEGAL + DNI / GENERO / CUMPLEAÑOS / CELULAR / CORREO | `legal_rep_name`, `legal_rep_dni`, `legal_rep_gender`, `legal_rep_birthday`, `legal_rep_phone`, `legal_rep_email` | |
| RESPRESENTANTE ANTE LA CCH + DNI / GENERO / CUMPLEAÑOS / CELULAR / CORREO | `cch_rep_name`, `cch_rep_dni`, `cch_rep_gender`, `cch_rep_birthday`, `cch_rep_phone`, `cch_rep_email` | |
| IMAGENES | `image_path` | Logo/foto subido al disco `public` (`storage/app/public/associates`). |
| OBSERVACIONES | `notes` | |

El importador (`App\Services\AssociateImportService`) reconoce estos encabezados tal cual aparecen en el padrón (fila 1), ignora la fila de meses y las filas de continuación de cada asociado, y distingue los encabezados repetidos (`DNI N°`/`DNI N°4`, dos `GENERO`, `CORREO`/`CORREO 6`) por orden de aparición.

### invoices
Una factura por asociado y período (`UNIQUE(associate_id, period)`, previene duplicados — HU-06, ver `App\Services\InvoiceGenerationService`). `paid_total` es una columna **desnormalizada**: se recalcula transaccionalmente cada vez que se registra un pago (`paid_total = SUM(payments.amount)` para esa factura, mantenido por `App\Services\PaymentService::register()` dentro de una transacción con `lockForUpdate()`), en lugar de calcularse en cada lectura. Esto evita que cada listado/dashboard/reporte tenga que hacer un `JOIN + SUM` sobre `payments`, a costa de que **todo código que modifique `payments` debe pasar por `PaymentService`** para mantener `paid_total` sincronizado.

`status` (columna almacenada) solo refleja el estado derivado de los pagos: `PENDIENTE`, `PARCIAL` o `PAGADA` — nunca `VENCIDA`. "Vencida" depende de la fecha de hoy, no de un evento de escritura, así que se calcula al leer (`Invoice::effectiveStatus()` / `Invoice::isOverdue()` / scope `Invoice::overdue()`) en vez de persistirse vía un job programado que podría quedar desactualizado entre corridas. La UI (badges de estado en `invoices/_status_badge.blade.php`) siempre usa `effectiveStatus()`, nunca la columna `status` directamente.

`created_by` referencia al usuario que generó la factura (nullable — se conserva la factura aunque el usuario se elimine).

`receipt_number` (nullable, indexado) es el N° de comprobante (`F020-00001289`, `FE01-000322`, …) que el padrón Excel anota bajo cada mes de la grilla "AÑO 2024-APORTES / AÑO 2025 / AÑO 2026". Un período = un comprobante, por eso vive en la factura y no en el pago. El módulo de Pagos lo muestra y lo busca.

**Grilla de aportes del padrón → facturas + pagos.** `App\Services\AssociateImportService` lee la fila 2 del Excel (etiquetas de mes, fechas reales formateadas "ene-24") y, por cada asociado, la fila de datos maestros (comprobante bajo cada mes pagado) más la fila inmediatamente inferior (monto). Cada mes con comprobante/monto se convierte en una factura `PAGADA` (`issue_date` = primer día del período, `due_date` = último) y un pago por el monto, registrado vía `PaymentService::register()` con `paid_at` = primer día del período (el Excel no guarda el día exacto; los meses futuros —pagos adelantados— se fechan hoy). Celdas `NOTA DE CREDITO` / `ANULADO` / `EXCEPCION` se omiten; "50 Y 75" suma ambos montos. Reimportar el padrón es idempotente: el asociado se actualiza (clave: RUC, o razón social si ninguno tiene RUC) y solo se agregan los períodos que aún no tienen factura.

### payments
Pagos (totales o parciales) asociados a una factura, registrados vía `App\Services\PaymentService::register()`. `amount > 0` (constraint en MySQL — ver nota de SQLite abajo, reforzado además por la validación `min:0.01` en `PaymentRequest` y por la propia regla de negocio: no se acepta un pago mayor al saldo pendiente de la factura — HU-09), fecha de pago, quién lo registró (`registered_by`, obligatorio) y notas opcionales.

### audit_logs
Registro de auditoría de operaciones críticas (sección 26 del prompt maestro): quién (`user_id`, nullable), qué acción (`action`, p. ej. `associate.create`, `auth.login`), sobre qué entidad (`entity_type` + `entity_id`), resultado y metadata JSON opcional. No es un módulo visible en el menú; existe para trazabilidad. Se escribe con `App\Models\AuditLog::record(...)`, que toma el usuario autenticado automáticamente.

## Integridad referencial sobre `users`

- `users.role_id` → `roles.id`: **RESTRICT**. No se puede borrar un rol que todavía tiene usuarios asignados.
- `invoices.created_by` → `users.id`: **SET NULL**. La factura sobrevive aunque se borre el usuario que la generó.
- `audit_logs.user_id` → `users.id`: **SET NULL**. El log de auditoría sobrevive aunque se borre el usuario.
- `payments.registered_by` → `users.id`: **RESTRICT** (columna obligatoria). **No se puede borrar un usuario que registró al menos un pago** — este es el límite práctico real para el borrado físico de usuarios. Por eso la gestión de usuarios (HU-23) ofrece **desactivar** (`is_active = 0`), nunca eliminar.

## Nota sobre `CHECK` constraints y el entorno de pruebas

Las migraciones de `invoices` y `payments` añaden `CHECK (amount > 0)` (y `CHECK (paid_total >= 0)` en `invoices`) como defensa adicional más allá de la validación en los Form Requests. Estas restricciones se aplican solo cuando el driver activo es `mysql` (`DB::connection()->getDriverName() === 'mysql'`), porque SQLite —usado por la suite de tests, ver `docs/PROJECT_ANALYSIS.md` sección 10.4— no soporta `ALTER TABLE ADD CONSTRAINT`. En la base de datos de desarrollo/producción (MySQL) el constraint sí existe siempre.
