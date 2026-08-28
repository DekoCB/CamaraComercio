# Requirements Gap Analysis — Sistema de Facturación y Cobranzas

**Fecha:** 2026-08-19
**Alcance:** auditoría de todo el repositorio (frontend, backend, base de datos, migraciones, autenticación, autorización, servicios, modelos, tests, configuración, variables de entorno, deployment) contra la especificación funcional original (23 HU, 8 épicas, 4 sprints) **más** los requerimientos de aptitud-para-producción listados en el prompt de auditoría de 2026-08-19. Las HU-01 a HU-23 no se modifican — este documento solo registra su estado real de implementación y lo que falta *alrededor* de ellas para ir a producción.

Metodología: lectura directa del código fuente (no de la documentación previa) — migraciones, modelos, servicios, controllers, Form Requests, rutas, configuración, tests. Cada fila cita el archivo/línea que sustenta la evidencia.

## Leyenda de estados

- **IMPLEMENTADO** — existe en código, verificado con evidencia directa.
- **PARCIAL** — existe pero con una laguna concreta (inconsistencia, alcance incompleto, protegido en un flujo y no en otro).
- **FALTANTE** — no existe ningún artefacto en el repositorio.
- **REQUIERE VALIDACIÓN** — la implementación depende de una regla de negocio que la documentación funcional no define (ver `docs/OPEN_BUSINESS_DECISIONS.md` para el detalle completo de cada una).

---

## 1. Integridad de datos

| ID | Requerimiento | Estado | Evidencia | Acción |
|---|---|---|---|---|
| DAT-01 | Un pago no puede existir sin factura | IMPLEMENTADO | `payments.invoice_id` es `foreignId()->constrained()->restrictOnDelete()` (`database/migrations/..._create_payments_table.php:13`); no existe ruta ni código que cree un `Payment` sin `invoice_id` | Ninguna |
| DAT-02 | Una factura no puede existir sin asociado | IMPLEMENTADO | `invoices.associate_id` es `foreignId()->constrained()->restrictOnDelete()` (`..._create_invoices_table.php:13`) | Ninguna |
| DAT-03 | Un pago no puede ser negativo o cero | IMPLEMENTADO (dos capas) | `PaymentService::register()` lanza excepción si `$amount <= 0` (`app/Services/PaymentService.php:22-24`); además `CHECK (amount > 0)` a nivel MySQL (`..._create_payments_table.php:25`, guardado para no romper SQLite en tests) | Ninguna |
| DAT-04 | Una factura no puede tener monto inválido | IMPLEMENTADO (dos capas) | `InvoiceGenerateRequest` valida `amount` numérico > 0 (ver Form Request); `CHECK (amount > 0)` y `CHECK (paid_total >= 0)` a nivel MySQL (`..._create_invoices_table.php:34-35`) | Ninguna |
| DAT-05 | Un asociado no puede repetirse según su identificador único | IMPLEMENTADO (2026-08-28) | `AssociateRequest` valida correos duplicados en alta/edición manual (antes solo lo hacía la importación por Excel — corregido con `Rule::unique('associates', 'email')->ignore(...)`). Además, se agregó `associates.ruc` (nullable, `UNIQUE`, 11 dígitos) como identificador legal — resolución de la pregunta 12 (ver `OPEN_BUSINESS_DECISIONS.md`) | El RUC es opcional, no obligatorio — un asociado sin RUC registrado todavía es válido; el nombre sigue sin unicidad forzada (puede repetirse legítimamente entre empresas distintas) |
| DAT-06 | No se pueden duplicar facturas para el mismo asociado+período | IMPLEMENTADO | `$table->unique(['associate_id', 'period'])` (`..._create_invoices_table.php:24`); `InvoiceGenerationService::generateForPeriod()` además pre-filtra asociados ya facturados antes de intentar el `INSERT`, así que el `UNIQUE` es una defensa secundaria, no la única | Ninguna — coincide exactamente con la sección 5 del prompt de auditoría |
| DAT-07 | Cálculo de saldo centralizado | PARCIAL | La fórmula `monto - pagado` está implementada en 3 lugares con la misma semántica pero código separado: `Invoice::balance()` (PHP, para un registro cargado — `app/Models/Invoice.php:59-62`), y la expresión SQL cruda `amount - paid_total` repetida en `ReportService.php:63,75` y `DashboardService.php:35` (necesaria ahí porque son sumas agregadas a nivel de base de datos, no se puede invocar un método PHP dentro de un `SUM()`) | Extraer la expresión SQL a una constante compartida (`Invoice::BALANCE_SQL`) para que exista un único lugar donde la fórmula esté escrita, aunque se use en dos contextos (PHP y SQL) — cambio de bajo riesgo, sin impacto de alcance. Implementado en esta misma sesión (ver commit) |

## 2. Ciclo de vida de la factura

| ID | Requerimiento | Estado | Evidencia | Acción |
|---|---|---|---|---|
| INV-01 | Estados PENDIENTE/PARCIAL/PAGADA/VENCIDA | IMPLEMENTADO | `Invoice::STATUS_*` constantes; columna `status` enum con los 3 primeros persistidos, `VENCIDA` calculado en `effectiveStatus()`/`isOverdue()`/`scopeOverdue()` (`app/Models/Invoice.php:74-92`) — decisión arquitectónica documentada explícitamente en el propio modelo: VENCIDA depende de la fecha de hoy, no de un evento de escritura, así que nunca se persiste | Ninguna |
| INV-02 | Transición automática de estado al pagar | IMPLEMENTADO | `PaymentService::statusFor()` decide PAGADA/PARCIAL/PENDIENTE según `paid_total` vs `amount`, dentro de la misma transacción que crea el pago (`app/Services/PaymentService.php:44-48,54-61`) | Ninguna |
| INV-03 | ¿Una factura vencida puede recibir pagos? | **REQUIERE VALIDACIÓN** (comportamiento actual documentado) | El código actual **sí lo permite**: `PaymentController@store` y `PaymentService::register()` no comprueban `isOverdue()` ni `effectiveStatus()` en ningún punto — solo verifican que el monto no exceda el saldo. No hay ninguna regla que lo bloquee | Ver `docs/OPEN_BUSINESS_DECISIONS.md` pregunta 11. Comportamiento actual = "sí, sin restricción" — marcado explícitamente como inferido por ausencia de regla, no como decisión de negocio confirmada |
| INV-04 | ¿Puede modificarse una factura ya generada? | FALTANTE (por diseño, sin confirmar si es intencional) | `InvoiceController` no tiene método `edit()` ni `update()`; no existe ninguna ruta `PUT/PATCH /invoices/{invoice}`. Una vez creada, solo su `paid_total`/`status` cambian, y únicamente vía `PaymentService` | Ver `docs/OPEN_BUSINESS_DECISIONS.md` pregunta 5 |
| INV-05 | ¿Puede anularse una factura? | FALTANTE | No existe columna de estado "ANULADA", ni ruta, ni servicio para esto | Ver pregunta 6 |
| INV-06 | ¿Puede eliminarse una factura? | FALTANTE (por diseño) | No existe ruta `destroy()`; `invoices.associate_id` es `restrictOnDelete()`, consistente con la política general de "no hay borrado físico" ya documentada en `docs/DATA_MODEL.md` | Confirmar que esto es intencional — parece serlo dado el patrón consistente en todo el sistema, pero no está escrito como regla explícita en ningún lado hasta este documento |
| INV-07 | ¿Puede una factura tener múltiples pagos? | IMPLEMENTADO | `Invoice::payments(): HasMany`; probado explícitamente en `tests/Feature/PaymentTest.php` (pagos parciales acumulativos) | Ninguna |
| INV-08 | ¿Se puede corregir/anular un pago ya registrado? | **RESUELTO (2026-08-28)** | `PaymentController::void()` + `PaymentService::void()` — un pago se anula (nunca se edita ni se elimina), con motivo obligatorio, excluido de `paid_total`/reportes/dashboard | Ver pregunta 21 de `OPEN_BUSINESS_DECISIONS.md` (corregida — antes citaba erróneamente la pregunta 7, que trata sobre eliminar facturas, no pagos) |
| INV-09 | ¿Pago superior al saldo? | IMPLEMENTADO | `PaymentService::register()` rechaza explícitamente si `$amount > $locked->balance()`, dentro de una transacción con `lockForUpdate()` para evitar condición de carrera entre dos pagos concurrentes sobre la misma factura (`app/Services/PaymentService.php:26-34`) | Ninguna — cubre correctamente la pregunta 9 |

## 3. Estado del asociado

| ID | Requerimiento | Estado | Evidencia | Acción |
|---|---|---|---|---|
| ASO-01 | Modelo soporta ACTIVO/INACTIVO | IMPLEMENTADO | `associates.is_active` boolean, default `true` (`..._create_associates_table.php:17`) | Ninguna |
| ASO-02 | Regla de negocio para pasar de ACTIVO a INACTIVO | **REQUIERE VALIDACIÓN** | El único mecanismo existente es editar manualmente el checkbox "Asociado activo" en el formulario de edición (`AssociateRequest`, `is_active` es `sometimes\|boolean`) — **no hay automatización de ningún tipo**, ni por inactividad, ni por mora, ni por ninguna otra condición | Ver pregunta 1-3. Correcto no automatizar nada sin definición — así queda hoy |
| ASO-03 | ¿Los asociados inactivos reciben facturación? | IMPLEMENTADO (regla ya definida y aplicada) | `InvoiceGenerationService::generateForPeriod()` filtra explícitamente `Associate::where('is_active', true)` (línea 30) — un asociado inactivo **nunca** es incluido en la generación masiva | Ninguna — esta es la única de las 3 preguntas de esta sección que el código ya responde de forma inequívoca |

## 4. Facturación mensual (HU-06)

| ID | Requerimiento | Estado | Evidencia | Acción |
|---|---|---|---|---|
| FAC-01 | Selección de período/monto/vencimiento | IMPLEMENTADO | Wizard de 4 pasos, `InvoiceGenerateRequest` valida los 3 campos | Ninguna |
| FAC-02 | Determinar asociados elegibles | IMPLEMENTADO | Solo activos (ver ASO-03) | Ninguna |
| FAC-03 | Validar duplicados | IMPLEMENTADO | Pre-check en memoria + `UNIQUE` en BD (ver DAT-06) | Ninguna |
| FAC-04 | Proteger contra doble ejecución accidental | PARCIAL | Existe un `data-confirm` (modal de confirmación explícita, HU-20) antes de ejecutar, y la unicidad de BD hace que una segunda ejecución accidental para el mismo período **no duplique nada** (se reporta como "omitidas"). No existe, en cambio, un lock/idempotency-key a nivel de request — dos clics muy rápidos podrían, en teoría, disparar dos requests casi simultáneos; el `UNIQUE(associate_id, period)` seguiría evitando la duplicación de datos, pero uno de los dos requests recibiría errores por fila en el resumen en vez de una experiencia limpia | Riesgo bajo (mitigado por el constraint de BD); no se considera bloqueante para producción. Documentado en `docs/BUSINESS_RULES.md` |
| FAC-05 | Qué ocurre si ya existen facturas para el período | IMPLEMENTADO (comportamiento definido) | Se omiten, se cuentan como "skipped", no se sobrescribe el monto original — verificado explícitamente en `tests/Feature/InvoiceGenerationTest.php` | Ninguna |
| FAC-06 | Qué ocurre con asociados registrados después del inicio del mes | **REQUIERE VALIDACIÓN** | El sistema los factura igual la próxima vez que se ejecute la generación para ese período (no hay prorrateo ni exclusión por fecha de alta) — es el comportamiento *por defecto* al no existir ninguna regla de prorrateo, no una decisión confirmada | Ver pregunta en `docs/OPEN_BUSINESS_DECISIONS.md` — probablemente aceptable (factura completa independientemente del día de alta), pero debe confirmarse |

## 5. Migración de Excel (sección 15 / HU adicional)

| ID | Requerimiento | Estado | Evidencia | Acción |
|---|---|---|---|---|
| XLS-01 | Flujo upload → validación → previsualización → confirmación → resumen | IMPLEMENTADO | `AssociateImportController` con 4 acciones (`create`, `preview`, `confirm`, `cancel`), `AssociateImportService::parse()`/`import()` | Ninguna |
| XLS-02 | Nunca insertar sin validación previa | IMPLEMENTADO | El archivo se re-parsea desde disco en `confirm()`, nunca se confía en lo que el navegador reenvía tras la previsualización (documentado en `docs/PROJECT_ANALYSIS.md` sección 10.8) | Ninguna |
| XLS-03 | Reporte de fila/columna/valor/motivo por error | PARCIAL | Cada fila con error reporta **fila + motivo** (`AssociateImportService::parse()`, array `errors`), pero no aísla la **columna específica** ni el **valor** que causó el error dentro del mensaje — el mensaje es a nivel de fila completa (ej. "El correo no es válido" ya identifica implícitamente la columna, pero no de forma estructurada) | Mejora de bajo riesgo, no bloqueante — documentado en `docs/EXCEL_MIGRATION_SPECIFICATION.md` como recomendación futura |
| XLS-04 | Importación de **facturas** y **pagos** desde Excel | FALTANTE | El importador solo cubre **asociados**. No existe ningún flujo de importación para facturas o pagos históricos | Ver `docs/OPEN_BUSINESS_DECISIONS.md` — la especificación funcional original (sección 15) solo pedía explícitamente importación de asociados; si la Cámara necesita migrar historial de facturación/pagos, es un cambio de alcance que requiere aprobación explícita (sección 25 de este prompt) |
| XLS-05 | Importación transaccional (todo o nada) | **REQUIERE VALIDACIÓN** (comportamiento actual documentado) | La importación actual es **parcial por diseño**: cada fila se inserta en su propio `try/catch` (`AssociateImportService::import()`, líneas 108-121) — si una fila falla (p. ej. una condición de carrera de correo duplicado entre el momento de la previsualización y la confirmación), las demás filas válidas sí se insertan y la fila fallida se reporta en el resumen. Esto es intencional y coherente con "informar registros creados/omitidos/errores" (sección 11 del prompt), pero es lo opuesto de "todo o nada" | Documentado como la interpretación **inferible** correcta de la sección 11: la especificación pide informar errores por fila individual, lo cual es incompatible con una transacción atómica de todo el archivo. Se documenta explícitamente para que quede claro que es una decisión de diseño, no un descuido |

## 6. Seguridad

| ID | Requerimiento | Estado | Evidencia | Acción |
|---|---|---|---|---|
| SEC-01 | Hash seguro de contraseñas | IMPLEMENTADO | Eloquent cast `'password' => 'hashed'` (bcrypt vía `Hash::make`), `BCRYPT_ROUNDS=12` en `.env.example` | Ninguna |
| SEC-02 | Sesiones seguras | PARCIAL | `SESSION_DRIVER=database`, `http_only=true`, `same_site=lax` (`config/session.php`) — correcto. Pero `SESSION_SECURE_COOKIE` **no está seteado explícitamente** en `.env.example` (queda `null`, Laravel lo infiere de si la request llegó por HTTPS) | Para producción, setear `SESSION_SECURE_COOKIE=true` explícitamente una vez haya HTTPS real — documentado en `docs/DEPLOYMENT.md` |
| SEC-03 | Expiración de sesión | IMPLEMENTADO | `SESSION_LIFETIME=120` minutos | Ninguna |
| SEC-04 | Recuperación de contraseña segura | IMPLEMENTADO | Broker nativo de Laravel (`Password::sendResetLink`/`Password::reset`), token de un solo uso, expira en 60 min, mensaje idéntico exista o no el correo (anti-enumeración) — verificado en `tests/Feature/Auth/PasswordResetTest.php` | Ninguna |
| SEC-05 | Autorización server-side, no solo frontend | IMPLEMENTADO | `Gate::before()` en `AppServiceProvider` + middleware `can:codigo.permiso` en cada ruta protegida; verificado explícitamente con test que pide un 403 real por URL directa, no solo un menú oculto (`tests/Feature/Admin/RbacTest.php`) | Ninguna |
| SEC-06 | Validación de input (strings, emails, fechas, montos, IDs, archivos) | IMPLEMENTADO | Form Requests dedicados para cada entidad; route-model binding valida IDs implícitamente (404 si no existe); `avatar` valida `image`+`max:2048`; import valida extensión de archivo vía `IOFactory::load()` (falla si no es un formato de spreadsheet reconocible) | Ninguna |
| SEC-07 | Protección SQL Injection | IMPLEMENTADO | 100% Eloquent/Query Builder parametrizado; único uso de SQL crudo es `DB::statement()` para `CHECK` constraints (sin input de usuario) y `selectRaw()` con literales fijos, no interpolación de variables de usuario | Ninguna |
| SEC-08 | Protección XSS | IMPLEMENTADO | Blade escapa por defecto (`{{ }}`); revisión de código confirmó (sesión de Sprint 4) cero usos de `{!! !!}` con datos de usuario | Ninguna |
| SEC-09 | CSRF | IMPLEMENTADO | Laravel CSRF middleware activo por defecto (`@csrf` en todos los formularios, incluidos los enviados por AJAX vía `fetch` con el token del formulario) | Ninguna |
| SEC-10 | Rate limiting / fuerza bruta en login | IMPLEMENTADO (parcial en alcance) | `throttle:5,1` en `POST /login`, `POST /forgot-password`, `POST /reset-password` (`routes/web.php`) — verificado en `tests/Feature/Auth/LoginTest.php`. **No existe** rate limiting en ningún otro endpoint de escritura (alta de asociado, generación de facturas, registro de pago, etc.) | Riesgo bajo — esos endpoints ya están detrás de autenticación + autorización por rol, el vector de "fuerza bruta" no aplica del mismo modo que en login. No se considera bloqueante, pero se documenta como mejora posible en `docs/DEPLOYMENT.md` |
| SEC-11 | No exponer secretos en logs | IMPLEMENTADO | Revisado: `AuditLog::record()` solo guarda IDs y montos, nunca contraseñas ni tokens (ver metadata de `payment.register`, `associate.import`, etc. — ninguna contiene datos sensibles) | Ninguna |
| SEC-12 | No contraseñas en texto plano | IMPLEMENTADO | Ver SEC-01; `$hidden = ['password', 'remember_token']` en `User` | Ninguna |

## 7. Auditoría

| ID | Requerimiento | Estado | Evidencia | Acción |
|---|---|---|---|---|
| AUD-01 | Trazabilidad de pagos | IMPLEMENTADO | `payment.register` con `invoice_id`+`amount` (`app/Http/Controllers/PaymentController.php:53-56`) | Ninguna |
| AUD-02 | Trazabilidad de facturas | IMPLEMENTADO | `invoice.generate_batch` con el resumen completo (creadas/omitidas/errores) | Ninguna |
| AUD-03 | Trazabilidad de usuarios | IMPLEMENTADO | `user.create`, `user.update`, `user.update_profile` | Ninguna |
| AUD-04 | Trazabilidad de permisos/roles | IMPLEMENTADO | `role.create`, `role.update`, `role.update_access` | Ninguna |
| AUD-05 | Trazabilidad de asociados | IMPLEMENTADO | `associate.create`, `associate.update`, `associate.import` | Ninguna |
| AUD-06 | Trazabilidad de módulos | IMPLEMENTADO | `module.create`, `module.activate`/`module.deactivate` | Ninguna |
| AUD-07 | Trazabilidad de login (éxito/fallo) | IMPLEMENTADO (va más allá de lo pedido) | `auth.login` con `result: success\|failure` — no estaba explícitamente pedido en la spec original pero ya existe | Ninguna |
| AUD-08 | El log de auditoría es visible/consultable desde la UI | FALTANTE | La tabla `audit_logs` existe y se escribe correctamente, pero no hay ninguna pantalla en la aplicación para **leerla** — solo es accesible por consulta directa a la base de datos | Cambio de alcance (nueva pantalla, nuevo permiso) — no estaba en las 23 HU originales. Marcar `REQUIERE APROBACIÓN` si se quiere agregar |

## 8. Protección de datos y privacidad

| ID | Requerimiento | Estado | Evidencia | Acción |
|---|---|---|---|---|
| PRI-01 | Documento formal de qué datos personales se manejan | FALTANTE | No existía `docs/DATA_PROTECTION.md` antes de esta auditoría | Creado en esta sesión |
| PRI-02 | Cumplimiento de la Ley de Protección de Datos Personales del Perú (Ley 29733) | **REQUIERE VALIDACIÓN LEGAL** | El sistema no implementa ningún mecanismo específico de esa ley (registro ante la ANPD, consentimiento explícito, derecho de rectificación/cancelación formalizado, etc.) — nunca se pidió en la especificación funcional original | No se declara cumplimiento legal. Ver `docs/DATA_PROTECTION.md` |

## 9. Backups y recuperación

| ID | Requerimiento | Estado | Evidencia | Acción |
|---|---|---|---|---|
| BAK-01 | Backups configurados y en ejecución | FALTANTE | No existe ningún script, cron job, ni configuración de backup en el repositorio ni en el entorno XAMPP local | Documentado como **propuesta**, no como estado actual, en `docs/BACKUP_AND_RECOVERY.md` — coherente con la instrucción explícita de no afirmar que existen backups reales |

## 10. QA / Testing

| ID | Requerimiento | Estado | Evidencia | Acción |
|---|---|---|---|---|
| QA-01 | Tests automatizados de extremo a extremo | IMPLEMENTADO | 84 tests en `tests/Feature/` (`php artisan test`), cubren las 23 HU + import + perfil | Ninguna |
| QA-02 | Tests unitarios puros (sin framework) | FALTANTE (por elección arquitectónica, no descuido) | `tests/Unit/` no existe con contenido — toda la lógica de negocio no trivial vive en `app/Services/*` y se prueba a través de Feature tests HTTP completos, no aislada. Documentado ya en `docs/ARCHITECTURE.md` de sprints anteriores como decisión consciente | Ninguna — es una decisión arquitectónica válida, no un gap |
| QA-03 | Tests de seguridad (permisos, 403, sesión expirada) | IMPLEMENTADO | `tests/Feature/Admin/RbacTest.php` cubre 403 real; `LoginTest.php` cubre rate limiting y credenciales inválidas | Ninguna |
| QA-04 | Tests responsivos/E2E de navegador real | PARCIAL | Se hicieron barridos manuales con Playwright durante el desarrollo (documentados en `docs/PROJECT_ANALYSIS.md`), pero **no están automatizados como suite repetible** — fueron scripts ad-hoc de verificación, no forman parte de `php artisan test` ni de un pipeline | Ver `docs/QA_PLAN.md` — se documenta como recomendación para CI futuro, no bloqueante para el estado actual |
| QA-05 | Plan de pruebas formal (QA_PLAN.md) | FALTANTE | No existía | Creado en esta sesión |
| QA-06 | Plan UAT formal | FALTANTE | No existía | Creado en esta sesión |

## 11. Deployment y operación

| ID | Requerimiento | Estado | Evidencia | Acción |
|---|---|---|---|---|
| DEP-01 | Documentación de entornos (dev/testing/producción) | PARCIAL | `README.md` documenta instalación local XAMPP en detalle; no existe documentación de un entorno de producción real (no se ha definido dónde se desplegará) | `docs/DEPLOYMENT.md` documenta la arquitectura propuesta, marca configuración concreta de producción como pendiente |
| DEP-02 | Dockerización / IaC | FALTANTE | No existe `Dockerfile`, `docker-compose.yml`, ni ningún script de infraestructura | No es un requisito de la especificación funcional original — no se implementa sin que se apruebe como cambio de alcance |
| DEP-03 | Health checks | IMPLEMENTADO (nativo de Laravel, no configurado a medida) | Laravel 12 registra `/up` automáticamente vía `bootstrap/app.php:11` (`health: '/up'`) | Ninguna — documentar su existencia en `docs/DEPLOYMENT.md` |
| DEP-04 | Manual de usuario | FALTANTE | No existía | Creado en esta sesión |
| DEP-05 | Capacitación realizada | FALTANTE (no aplica a este documento) | Es un evento, no un artefacto de código — no puede "implementarse" | Se deja fuera del alcance de este análisis técnico; el manual de usuario es el insumo para que ocurra |

---

## Resumen ejecutivo

**Ya existe y funciona correctamente** (verificado con evidencia de código, no solo documentación): las 23 HU, RBAC real a nivel de servidor, hash de contraseñas, rate limiting en login, protección CSRF/XSS/SQLi, cálculo de saldo consistente, constraint de unicidad factura+período, prevención de sobrepago, auditoría de las operaciones críticas (pagos, facturas, usuarios, permisos), y 87 tests automatizados en verde.

**Se implementó en esta misma sesión** (categorías A/B, sin depender de ninguna decisión pendiente del cliente): (1) se centralizó la expresión SQL del cálculo de saldo en `Invoice::BALANCE_SQL`, usada ahora por `ReportService` y `DashboardService` en vez de repetir el fragmento `amount - paid_total` suelto en cada archivo (DAT-07); (2) se cerró la inconsistencia real entre el alta manual de asociados (no validaba correos duplicados) y la importación por Excel (sí lo hacía) — ambos caminos aplican ahora la misma regla (DAT-05), con 3 tests nuevos. Ninguno de los dos cambios altera alcance, arquitectura, ni responde ninguna de las 20 preguntas abiertas — son consistencia interna, no decisiones de negocio nuevas.

**Resuelto e implementado en una sesión posterior (2026-08-28), con autorización explícita del cliente:** las dos decisiones de mayor impacto identificadas en la auditoría — identificador único del asociado (pregunta 12, RUC opcional) y corrección/anulación de pagos (pregunta 21, por anulación con motivo, no edición). Ver `docs/OPEN_BUSINESS_DECISIONS.md` para el detalle de ambas resoluciones.

**Sigue requiriendo decisión del cliente** (ver `docs/OPEN_BUSINESS_DECISIONS.md`): regla de inactivación de asociados (ASO-02), si una factura vencida puede recibir pagos (INV-03, aunque el comportamiento actual ya es razonable) y si las facturas pueden corregirse o anularse (INV-04/05 — distinto de los pagos, que ya se resolvieron).

**Riesgos:**
1. **Resuelto (2026-08-28):** un pago mal ingresado ya puede anularse desde la UI, con motivo obligatorio y trazabilidad completa (INV-08 — ver `OPEN_BUSINESS_DECISIONS.md` pregunta 21).
2. **Resuelto (2026-08-28):** la inconsistencia de duplicados entre alta manual e importación (DAT-05) ya no existe, y el identificador de negocio del asociado (RUC, pregunta 12) ya está implementado.
3. **Riesgo bajo:** no hay backups configurados (BAK-01) — aceptable mientras el sistema siga en desarrollo/UAT local, bloqueante antes de producción real.

**Impacto técnico de resolver las decisiones pendientes:** ninguna de las 20 preguntas abiertas requiere un cambio de arquitectura — todas se resuelven con ajustes acotados sobre el modelo de datos y los Services ya existentes (agregar una columna de estado, un método de anulación, una validación de unicidad). No hay razón para migrar tecnología ni rediseñar el sistema para atender ninguna de ellas.
