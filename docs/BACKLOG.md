# Backlog — Sistema de Facturación y Cobranzas

Estados: `TODO` · `IN_PROGRESS` · `REVIEW` · `DONE` · `BLOCKED`.
Los IDs de HU y las épicas (EP-01 a EP-08) no se modifican respecto a la documentación funcional original — ver `PROJECT_ANALYSIS.md`.

Nota (2026-08-17): Sprint 1 fue reconstruido sobre Laravel 12 (ver `PROJECT_ANALYSIS.md` sección 10). Las observaciones de abajo reflejan la implementación actual; los criterios de aceptación ahora están cubiertos por pruebas automatizadas (`php artisan test`, 29 tests) además de la verificación manual original.

| ID | Épica | Historia | Prioridad | Sprint | Estado | Observaciones |
|---|---|---|---|---|---|---|
| HU-01 | EP-01 | Iniciar sesión (login) | Alta | 1 | **DONE** | `Auth\AuthenticatedSessionController` + `Auth::attempt` (guard nativo de Laravel). Cubierto por `tests/Feature/Auth/LoginTest.php` (credenciales válidas/inválidas, correo inexistente, usuario inactivo). |
| HU-02 | EP-01 | Cerrar sesión | Alta | 1 | **DONE** | `AuthenticatedSessionController::destroy` invalida la sesión y regenera el token CSRF; rutas privadas vuelven a exigir login. Cubierto por test. |
| HU-03 | EP-01 | Recuperar contraseña | Media | 1 | **DONE** | Usa el broker nativo de Laravel (`Password::sendResetLink`/`Password::reset`, tabla `password_reset_tokens`): un solo uso, expira en 60 min, mensaje idéntico exista o no el correo. Cubierto por `tests/Feature/Auth/PasswordResetTest.php` (incluye reuso de token). Sin transporte de correo configurado (`MAIL_MAILER=log`, el enlace queda en `storage/logs/laravel.log`) — **pendiente de decisión de negocio**: definir proveedor SMTP para producción. |
| HU-04 | EP-03 | Registrar un asociado | Alta | 1 | **DONE** | `AssociateRequest` valida nombre obligatorio y formato de correo. Cubierto por `tests/Feature/AssociateTest.php`. |
| HU-05 | EP-03 | Actualizar datos de un asociado | Media | 1 | **DONE** | Incluye activar/desactivar sin afectar facturas/pagos relacionados (no hay borrado físico). |
| HU-21 | EP-02 | Crear los módulos del sistema | Alta | 1 | **DONE** | `ModuleRequest` valida código único con regex `[a-z0-9_-]+`. Cubierto por `tests/Feature/Admin/AdminManagementTest.php`. |
| HU-22 | EP-02 | Activar o desactivar módulos | Media | 1 | **DONE** | La navegación (directiva Blade `@module`) respeta `is_active` y el acceso del rol (`role_modules`). Cubierto por test (un módulo desactivado desaparece del sidebar aunque el rol lo tenga asignado). |
| HU-23 | EP-02 | Gestionar usuarios y permisos | Alta | 1 | **DONE** | CRUD de usuarios + asignación de permisos/módulos por rol vía `Gate::before()` (ver `docs/ARCHITECTURE.md`). Cubierto por `tests/Feature/Admin/RbacTest.php`: rol sin `admin.users` recibe **403** al pedir `/admin/users` directamente por URL (no solo oculto en UI). |
| HU-06 | EP-04 | Generar las facturas del mes | Alta | 2 | **DONE** | `InvoiceGenerationService::generateForPeriod()` + `InvoiceController@create`/`@store`. Un asociado activo por factura/período; los que ya tienen factura para ese período se omiten (no se duplican, protegido además por `UNIQUE(associate_id, period)`); requiere checkbox de confirmación explícita (`confirm` = accepted) antes de ejecutar; registra auditoría con el resumen creadas/omitidas/con error. Cubierto por `tests/Feature/InvoiceGenerationTest.php`. |
| HU-07 | EP-04 | Consultar las facturas de un asociado | Alta | 2 | **DONE** | `invoices.index` filtra por asociado/período/estado (incluye `VENCIDA` calculado); `invoices.show` muestra el detalle completo de una factura. Enlace "Facturas" en el listado de asociados. |
| HU-08 | EP-05 | Registrar un pago | Alta | 2 | **DONE** | `PaymentService::register()`, transaccional con `lockForUpdate()` para evitar condiciones de carrera entre pagos concurrentes sobre la misma factura. Cubierto por `tests/Feature/PaymentTest.php`. |
| HU-09 | EP-05 | Registrar un pago parcial | Alta | 2 | **DONE** | Mismo servicio que HU-08; rechaza pagos mayores al saldo pendiente (`amount > balance()`) tanto en el primer pago como en pagos posteriores sobre un saldo ya parcialmente cubierto. El caso de ejemplo de la sección "Criterio de aceptación" de abajo está codificado literalmente como test (`test_partial_payment_marks_the_invoice_as_partial_and_computes_balance`). |
| HU-10 | EP-06 | Saber quién debe | Alta | 3 | TODO | |
| HU-11 | EP-06 | Saber a quién falta cobrar | Alta | 3 | TODO | |
| HU-12 | EP-06 | Ver el estado de un asociado | Media | 3 | TODO | |
| HU-13 | EP-07 | Ver lo cobrado en el mes | Alta | 3 | TODO | |
| HU-14 | EP-07 | Ver la deuda pendiente | Alta | 3 | TODO | |
| HU-15 | EP-07 | Exportar la información (Excel/PDF) | Media | 3 | TODO | Vía `phpoffice/phpspreadsheet` y `dompdf/dompdf` (ya instalados). |
| HU-16 | EP-08 | Pantalla principal clara | Alta | 4 | **PARCIAL (base en Sprint 1)** | El layout con sidebar/topbar y el dashboard base ya existen (`resources/views/layouts/app.blade.php`, `dashboard/index.blade.php`), con KPIs reales de asociados; los KPIs de facturación/cartera se completan en Sprint 4 una vez existan. |
| HU-17 | EP-08 | Información ordenada en pantalla | Alta | 4 | **PARCIAL (base en Sprint 1)** | Tablas/badges de estado ya usados en asociados/usuarios/roles/módulos. |
| HU-18 | EP-08 | Buscar y filtrar desde la pantalla | Alta | 4 | **PARCIAL (base en Sprint 1)** | Búsqueda + paginación ya implementada en Asociados; se extiende a facturas/pagos/cartera en sprints 2-4. |
| HU-19 | EP-08 | Uso desde distintos dispositivos (responsivo) | Media | 4 | **PARCIAL (base en Sprint 1)** | Sidebar colapsable en móvil (`public/assets/css/app.css`, breakpoint 991.98px) ya implementado; falta validar tablas de facturación/cartera cuando existan. |
| HU-20 | EP-08 | Mensajes de confirmación | Media | 4 | **PARCIAL (base en Sprint 1)** | Flash messages (éxito/error) ya implementados para todas las operaciones de Sprint 1; confirmación de acciones destructivas vía `data-confirm` en `app.js`. |
| — | EP-08 | Carga de información desde Excel | Alta | 4 | TODO | |
| — | EP-08 | Pruebas integrales (QA), corrección de errores | Alta | 4 | TODO | |

## Ejemplo de criterio de aceptación (HU-09)

```
Dado una factura de S/ 500 sin pagos registrados
Cuando el encargado de cobranzas registra un pago de S/ 200
Entonces el sistema debe mostrar:
  Monto:  S/ 500.00
  Pagado: S/ 200.00
  Saldo:  S/ 300.00
  Estado: PARCIAL
```

Codificado literalmente en `tests/Feature/PaymentTest.php::test_partial_payment_marks_the_invoice_as_partial_and_computes_balance`.

## Criterios de aceptación — Sprint 2

Verificados manualmente por HTTP contra la app corriendo en XAMPP **y** cubiertos por pruebas automatizadas (`tests/Feature/InvoiceGenerationTest.php`, `tests/Feature/PaymentTest.php`; 46/46 en verde en el total de la suite):

- **HU-06:** generar la facturación de agosto 2026 crea una factura por cada uno de los asociados activos (verificado con 5 asociados de desarrollo); ejecutar la generación una segunda vez para el mismo período no duplica las facturas existentes ni sobreescribe su monto original, y sí factura a un asociado nuevo dado de alta después de la primera corrida.
- **HU-07:** el listado de facturas filtra por asociado/período/estado; el detalle de una factura muestra monto, pagado, saldo e historial de pagos completo.
- **HU-08/HU-09:** un pago igual al saldo deja la factura en `PAGADA`; un pago menor la deja en `PARCIAL` con el saldo recalculado correctamente; varios pagos parciales se acumulan hasta completar el monto; un pago mayor al saldo pendiente (incluyendo sobre un saldo ya parcialmente cubierto) es rechazado sin modificar `paid_total`. Verificado en vivo: una factura vencida y con pago parcial muestra el badge `VENCIDA` (el estado mostrado, no el almacenado, refleja fecha de vencimiento — ver `docs/DATA_MODEL.md`).
- **Concurrencia:** `PaymentService::register()` usa `lockForUpdate()` dentro de una transacción para que dos registros de pago simultáneos sobre la misma factura no puedan sobrepasar el saldo conjuntamente.

## Criterios de aceptación — Sprint 1

Verificados manualmente por HTTP contra la app corriendo en XAMPP (Apache + MySQL) **y** cubiertos por pruebas automatizadas (`php artisan test`, 29/29 en verde):

- **HU-01:** login con `admin@camaracomercio.test` / contraseña correcta → 302 a `/dashboard`; contraseña incorrecta o correo inexistente → permanece en `/login` con mensaje de error genérico; usuario inactivo no puede autenticarse.
- **HU-02:** `POST /logout` invalida la sesión; `GET /dashboard` posterior → 302 a `/login`.
- **HU-03:** `POST /forgot-password` genera un enlace de un solo uso (visible en `storage/logs/laravel.log` mientras no haya SMTP configurado); reutilizar el mismo token tras usarlo devuelve error y redirige a solicitar uno nuevo; la respuesta es idéntica exista o no el correo.
- **HU-04/05:** crear/editar asociado con correo inválido o nombre en blanco no persiste el cambio y muestra el mensaje de validación; con datos válidos persiste y aparece en el listado.
- **HU-21/22:** crear módulo con código duplicado es rechazado; desactivar un módulo lo retira de la navegación para todos los roles, incluso uno que lo tenga asignado.
- **HU-23:** un usuario con rol "Encargado de Cobranzas" (sin `admin.users`) recibe **403** al pedir `/admin/users` directamente por URL — confirma que la autorización se aplica en backend, no solo ocultando el enlace del menú. Un usuario desactivado pierde todos sus permisos aunque su rol los conserve.

Suite de pruebas: `tests/Feature/Auth/LoginTest.php`, `tests/Feature/Auth/PasswordResetTest.php`, `tests/Feature/AssociateTest.php`, `tests/Feature/Admin/RbacTest.php`, `tests/Feature/Admin/AdminManagementTest.php`.
