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
| HU-10 | EP-06 | Saber quién debe | Alta | 3 | **DONE** | `PortfolioService::debtSummary()` + `portfolio.index`: cada asociado con total facturado/pagado/pendiente y conteo de facturas pendientes/vencidas. Cubierto por `tests/Feature/PortfolioTest.php`. |
| HU-11 | EP-06 | Saber a quién falta cobrar | Alta | 3 | **DONE** | `PortfolioService::debtors()` + `portfolio.debtors`: solo asociados con saldo pendiente, con contacto/correo y el período de su factura impaga más antigua. Filtrado con `whereHas(status != PAGADA)`, no con `HAVING` sobre los alias de `withSum` (ver nota de portabilidad SQLite/MySQL en el propio servicio). |
| HU-12 | EP-06 | Ver el estado de un asociado | Media | 3 | **DONE** | `PortfolioService::statement()` + `associates.statement`: datos de contacto, resumen financiero (facturado/pagado/pendiente) e historial completo de facturas con su badge de estado. |
| HU-13 | EP-07 | Ver lo cobrado en el mes | Alta | 3 | **DONE** | `ReportService::collections()`: selecciona un período, muestra facturado (base devengo) vs. cobrado (pagos con `paid_at` en ese mes calendario — deliberadamente distinto del devengo, ver comentario en el servicio), cantidad de pagos y de asociados distintos que pagaron. |
| HU-14 | EP-07 | Ver la deuda pendiente | Alta | 3 | **DONE** | `ReportService::pendingDebt()`: total pendiente, asociados con deuda, facturas pendientes/vencidas y distribución por estado (`PENDIENTE`/`PARCIAL`/`VENCIDA`) vía SQL agregado — misma regla que `Invoice::effectiveStatus()` pero expresada para un `GROUP BY`, con la fecha de hoy pasada como parámetro ligado (no `CURDATE()`) para que funcione igual en MySQL y en el SQLite de los tests. |
| HU-15 | EP-07 | Exportar la información (Excel/PDF) | Media | 3 | **DONE** | `App\Services\ExportService` (compartido): Excel vía `phpoffice/phpspreadsheet` con título/fecha de generación/período/totales; PDF vía `dompdf/dompdf` renderizando una vista Blade dedicada (`resources/views/reports/pdf/*`). Implementado para los dos reportes de esta épica (cobranza y deuda pendiente); exportar cartera/morosidad queda para una fase posterior si se pide. Requiere el permiso `reports.export` además de `reports.view` (un rol puede ver un reporte en pantalla sin poder extraer el archivo). Verificado en vivo: el `.xlsx` generado se releyó con PhpSpreadsheet y el `.pdf` tiene cabecera `%PDF-1.7` válida. |
| HU-16 | EP-08 | Pantalla principal clara | Alta | 4 | **DONE** | Layout con sidebar/topbar y dashboard (`resources/views/layouts/app.blade.php`, `dashboard/index.blade.php`) con KPIs reales de las cuatro épicas operativas (asociados, facturado del período, cobrado del mes, deuda pendiente y facturas vencidas) y accesos rápidos a cada módulo según los permisos del usuario. |
| HU-17 | EP-08 | Información ordenada en pantalla | Alta | 4 | **DONE** | Tablas, badges de estado y tarjetas KPI consistentes en las 9 áreas del sistema (asociados, facturación, pagos, cartera, deudores, estado de cuenta, reportes, usuarios, roles/módulos). |
| HU-18 | EP-08 | Buscar y filtrar desde la pantalla | Alta | 4 | **DONE** | Búsqueda y/o filtros en asociados, facturas (asociado/período/estado), pagos (asociado/fechas) y, desde Sprint 4, también en cartera y "a quién falta cobrar" (por nombre/empresa) — cierre del último hueco de esta historia. |
| HU-19 | EP-08 | Uso desde distintos dispositivos (responsivo) | Media | 4 | **DONE** | Sidebar colapsable en móvil (`public/assets/css/app.css`, breakpoint 991.98px) y tablas anchas contenidas en su propio scroll horizontal (`.table-responsive`, nunca la página completa) — **verificado con Playwright en un viewport de 375px real** (no solo revisión de CSS): el body nunca excede el ancho del viewport, el toggle del menú abre/cierra el sidebar correctamente, y una tabla de 7 columnas se desplaza dentro de su contenedor sin romper el layout. |
| HU-20 | EP-08 | Mensajes de confirmación | Media | 4 | **DONE** | Flash messages (éxito/error) en todas las operaciones de escritura del sistema; confirmación explícita (`data-confirm`) antes de generar facturación masiva, confirmar una importación y desactivar un módulo — las tres acciones cuyo efecto alcanza a otros usuarios o no se puede deshacer. No existen borrados físicos en el sistema (ver `docs/DATA_MODEL.md`), así que no hay más acciones "destructivas" que confirmar. |
| — | EP-08 | Carga de información desde Excel | Alta | 4 | **DONE** | `AssociateImportService` + `AssociateImportController`, flujo de tres pasos (cargar → previsualizar con errores por fila → confirmar) siguiendo literalmente la sección 15 del prompt maestro: nada se inserta sin que el usuario vea antes qué se va a importar y qué filas se omitirán. Reconoce encabezados en español (Nombre/Empresa/Contacto/Correo) en cualquier orden de columnas; valida nombre obligatorio, formato de correo y correos duplicados contra la base de datos. El archivo subido se re-parsea desde disco al confirmar (nunca se confía en lo que el navegador reenvía) y se borra apenas termina la importación o si se cancela. |
| — | EP-08 | Pruebas integrales (QA), corrección de errores | Alta | 4 | **DONE** | 71 tests automatizados en verde (12 nuevos en Sprint 4: 9 de importación, 1 de rate limiting, 2 de filtros de cartera). Verificación en vivo contra Apache+MySQL para cada HU nueva. Endurecimiento de seguridad: límite de 5 intentos/minuto en login y en los formularios de recuperación de contraseña (ninguno de los dos lo tenía, al no venir de un scaffold de autenticación de Laravel); revisión de código sin salidas Blade sin escapar (`{!! !!}`) ni sentencias de depuración olvidadas. |

## Criterios de aceptación — Rediseño UI/UX (2026-08-18)

Trabajo puramente visual sobre EP-08 (HU-16 a HU-20), sin crear ni modificar historias de usuario nuevas — ver `docs/PROJECT_ANALYSIS.md` sección 10.10 y `docs/DESIGN_SYSTEM.md` para el detalle completo. Verificado con Playwright en dos viewports (1440px y 375px) contra la app corriendo en Apache, y con la suite completa (71/71 tests) sin cambios:

- Cero errores de consola/página en las 14+ pantallas principales, en ambos viewports.
- Cero overflow horizontal en 375px tras corregir el stepper del wizard de facturación (mostraba las 4 etiquetas de paso completas; se ocultan las de los pasos inactivos bajo 575.98px).
- El modal de confirmación y los toasts (reemplazos de `window.confirm()` y las alertas Bootstrap) funcionan en las tres acciones que ya requerían confirmación (HU-20): generación masiva de facturación, confirmación de importación, desactivación de módulo.
- El dashboard (HU-16) muestra KPIs reales con tendencia mes a mes y dos gráficos (cobranza mensual, distribución de cartera) alimentados por `App\Services\DashboardService`, sin alterar la regla de que `VENCIDA` nunca se persiste (`Invoice::effectiveStatus()`).

## Criterios de aceptación — Calendarios/listas personalizados y formularios en modal (2026-08-18)

Mejora de UX sobre EP-08, sin historias de usuario nuevas — ver `docs/PROJECT_ANALYSIS.md` sección 10.11 y `docs/DESIGN_SYSTEM.md` sección 10:

- Todo `<input type="date">`/`type="month"` y `<select>` del sistema usa ahora un widget propio con la paleta navy/azul/teal en vez del popup nativo del navegador; el valor que llega al backend es idéntico (el elemento nativo sigue en el DOM, solo oculto visualmente), así que ningún test de validación cambió.
- Crear/editar asociado, usuario, rol (datos básicos) y módulo se abren como modal sobre la lista en vez de navegar a una pantalla propia; la matriz de permisos de un rol se dejó como pantalla completa a propósito (no es un "formulario pequeño"). Los errores de validación se muestran inline sin recargar; un envío exitoso muestra el mismo toast de siempre.
- 71/71 tests sin cambios; verificado con Playwright en 1440px y 375px en las 15 pantallas del sistema.

## Criterios de aceptación — Toggle de tema y colapso de sidebar (2026-08-19)

Mejora de UX sobre EP-08, sin historias de usuario nuevas — ver `docs/PROJECT_ANALYSIS.md` sección 10.12 y `docs/DESIGN_SYSTEM.md` sección 11:

- El botón de tema en el topbar alterna claro/oscuro, persiste entre sesiones y se aplica sin parpadeo (incluida la pantalla de login).
- Los íconos del sidebar ya no saltan de posición al colapsar/expandir (verificado con Playwright: el ícono no se mueve ni un píxel durante la animación), y el botón de colapsar/expandir ya no desaparece al colapsar.
- 71/71 tests sin cambios; verificado visualmente con Playwright en modo oscuro en 8+ pantallas, incluidos el modal de formulario, el selector personalizado y el selector de fecha.

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

## Criterios de aceptación — Sprint 4

Verificados manualmente por HTTP contra la app corriendo en XAMPP, con un navegador real vía Playwright (sección responsiva) **y** cubiertos por pruebas automatizadas (`tests/Feature/AssociateImportTest.php`, `tests/Feature/Auth/LoginTest.php::test_login_attempts_are_rate_limited`, `tests/Feature/PortfolioTest.php`; 71/71 en verde en el total de la suite):

- **Importación (sección 15):** un archivo con 3 filas (una válida, una sin nombre, una con correo inválido) muestra la vista previa con 1 lista para importar y 2 marcadas en rojo con el motivo del error; confirmar solo crea el asociado válido; cancelar no crea nada y descarta el archivo temporal; confirmar sin haber subido nada antes redirige al formulario en vez de fallar; un archivo sin columna "Nombre" se rechaza antes de mostrar cualquier vista previa.
- **HU-18 (cartera):** buscar "Buscable" en `/portfolio` y en `/portfolio/debtors` muestra solo los asociados cuyo nombre o empresa coincide.
- **HU-19 (responsivo):** verificado con Chromium headless a 375px de ancho — el `<body>` nunca excede el ancho del viewport (ni en el dashboard ni en una tabla de 7 columnas), el botón de menú abre y cierra el sidebar (`x: -240` → `x: 0` → `x: -240` al tocar fuera), y la tabla ancha se desplaza dentro de su propio contenedor (`scrollWidth` 710px en un contenedor de 292px visibles) sin arrastrar el resto de la página.
- **Seguridad:** 5 intentos de login fallidos seguidos de uno con la contraseña correcta responde **429** (límite alcanzado) en vez de autenticar — el sexto intento en la misma ventana de un minuto queda bloqueado sin importar si las credenciales son correctas.

## Criterios de aceptación — Sprint 3

Verificados manualmente por HTTP contra la app corriendo en XAMPP (login como Encargado de Cobranzas, generación de facturas de julio 2026 ya vencidas, un pago parcial) **y** cubiertos por pruebas automatizadas (`tests/Feature/PortfolioTest.php`, `tests/Feature/ReportTest.php`; 59/59 en verde en el total de la suite):

- **HU-10:** la cartera general muestra, por asociado, el total facturado/pagado/pendiente y el conteo de facturas pendientes y vencidas — verificado con un asociado en estado `PARCIAL` (facturado S/500, pagado S/200, pendiente S/300 visibles en pantalla).
- **HU-11:** "a quién falta cobrar" solo lista asociados con saldo pendiente > 0 — un asociado sin facturas y otro con todas sus facturas `PAGADA` quedan fuera de la lista; el período de la deuda más antigua se calcula correctamente cuando un asociado tiene varias facturas impagas.
- **HU-12:** el estado de cuenta de un asociado muestra sus datos de contacto, los tres totales (facturado/pagado/pendiente) y el historial completo de facturas con su estado.
- **HU-13:** el reporte de cobranza de agosto 2026 separa correctamente lo facturado (base devengo, por período de factura) de lo cobrado (pagos cuya fecha cae en el mes calendario) — un pago hecho en septiembre para saldar una factura de agosto no cuenta como "cobrado en agosto"; cuenta asociados distintos que pagaron, no pagos individuales.
- **HU-14:** el reporte de deuda pendiente al día de hoy muestra el total adeudado y su distribución por estado (`PENDIENTE`/`PARCIAL`/`VENCIDA`) — verificado en vivo: 5 facturas de julio (ya vencidas para la fecha de la prueba) con S/920 pendientes tras un pago parcial de S/80, todas clasificadas como `VENCIDA` en la distribución.
- **HU-15:** tanto el reporte de cobranza como el de deuda pendiente se pueden exportar a Excel (releído con PhpSpreadsheet para confirmar título, fecha de generación, período, filas y totales) y a PDF (cabecera `%PDF-1.7` válida); pedir la exportación sin el permiso `reports.export` (aunque se tenga `reports.view`) devuelve **403**.

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

Suite de pruebas completa (59 tests): `tests/Feature/Auth/LoginTest.php`, `tests/Feature/Auth/PasswordResetTest.php`, `tests/Feature/AssociateTest.php`, `tests/Feature/Admin/RbacTest.php`, `tests/Feature/Admin/AdminManagementTest.php`, `tests/Feature/InvoiceGenerationTest.php`, `tests/Feature/PaymentTest.php`, `tests/Feature/PortfolioTest.php`, `tests/Feature/ReportTest.php`.
