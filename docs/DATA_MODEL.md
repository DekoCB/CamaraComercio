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
Cuentas de acceso al sistema (tabla base de Laravel + columnas propias añadidas en una segunda migración: `role_id`, `is_active`). La contraseña se guarda con el cast `'password' => 'hashed'` de Eloquent (bcrypt vía `Hash::make`) — nunca se almacena ni se expone en texto plano (`$hidden = ['password', 'remember_token']`). `email` es único.

### password_reset_tokens
Tabla **nativa de Laravel** (no una tabla de diseño propio): clave primaria `email`, `token` (hash, generado por el `PasswordBroker` de Laravel) y `created_at`. El broker nativo (`Password::sendResetLink` / `Password::reset`) gestiona expiración (`config('auth.passwords.users.expire')`, 60 minutos por defecto) y uso único (el registro se borra al completar el reset) — ver HU-03 y la nota D9 en `PROJECT_ANALYSIS.md`.

### associates
Asociados de la Cámara de Comercio (HU-04/HU-05): `name`, `company`, `contact_phone`, `email`, `is_active`.

### invoices
Una factura por asociado y período (`UNIQUE(associate_id, period)`, previene duplicados — HU-06, ver `App\Services\InvoiceGenerationService`). `paid_total` es una columna **desnormalizada**: se recalcula transaccionalmente cada vez que se registra un pago (`paid_total = SUM(payments.amount)` para esa factura, mantenido por `App\Services\PaymentService::register()` dentro de una transacción con `lockForUpdate()`), en lugar de calcularse en cada lectura. Esto evita que cada listado/dashboard/reporte tenga que hacer un `JOIN + SUM` sobre `payments`, a costa de que **todo código que modifique `payments` debe pasar por `PaymentService`** para mantener `paid_total` sincronizado.

`status` (columna almacenada) solo refleja el estado derivado de los pagos: `PENDIENTE`, `PARCIAL` o `PAGADA` — nunca `VENCIDA`. "Vencida" depende de la fecha de hoy, no de un evento de escritura, así que se calcula al leer (`Invoice::effectiveStatus()` / `Invoice::isOverdue()` / scope `Invoice::overdue()`) en vez de persistirse vía un job programado que podría quedar desactualizado entre corridas. La UI (badges de estado en `invoices/_status_badge.blade.php`) siempre usa `effectiveStatus()`, nunca la columna `status` directamente.

`created_by` referencia al usuario que generó la factura (nullable — se conserva la factura aunque el usuario se elimine).

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
