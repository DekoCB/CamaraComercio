# Sistema de Facturación y Cobranzas — Cámara de Comercio

Sistema web para automatizar la facturación y cobranza mensual de los asociados de la Cámara de Comercio, reemplazando el proceso manual en Excel. Documentación funcional completa en [`docs/PROJECT_ANALYSIS.md`](docs/PROJECT_ANALYSIS.md), backlog en [`docs/BACKLOG.md`](docs/BACKLOG.md).

**Estado actual:** Sprints 1 a 4 completados sobre **Laravel 12 + MySQL** — MVP funcional completo: acceso y seguridad, administración de módulos/usuarios/roles, gestión de asociados (incluida importación desde Excel), facturación mensual masiva, pagos totales/parciales, cartera/morosidad/estado de cuenta, reportes exportables a Excel/PDF, y endurecimiento de seguridad para producción. (El proyecto arrancó sobre un micro-framework PHP propio y fue migrado a Laravel a pedido explícito; ver `docs/PROJECT_ANALYSIS.md` sección 10 para el detalle del pivote.) Sobre ese MVP se aplicó además un rediseño completo de identidad visual ("Corporate Modern / Financial SaaS", ver `docs/DESIGN_SYSTEM.md`), sin tocar lógica de negocio.

## Stack

- **Laravel 12** (PHP 8.2) — Eloquent ORM, Blade, guard de autenticación y broker de recuperación de contraseña nativos
- **MySQL / MariaDB**
- Bootstrap 5 (solo utilidades de grid/layout) + sistema de diseño propio por tokens CSS (`public/assets/css/tokens.css`) + Lucide (iconos, vendorizados como SVG) + Chart.js (vendorizado) + Inter (autoalojada) — sin CDN, sin SPA
- `phpoffice/phpspreadsheet` (Excel) y `dompdf/dompdf` (PDF) — exportación de reportes e importación de asociados desde Excel
- PHPUnit (`php artisan test`) — Feature tests sobre SQLite en memoria

Justificación de cada decisión en [`docs/PROJECT_ANALYSIS.md`](docs/PROJECT_ANALYSIS.md) y [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

## Instalación (entorno XAMPP)

1. Clonar/copiar el proyecto dentro de `htdocs`, por ejemplo `c:\xampp\htdocs\CamaraComercio`.
2. Instalar dependencias PHP:
   ```
   composer install
   ```
3. Copiar el archivo de entorno y generar la `APP_KEY`:
   ```
   cp .env.example .env
   php artisan key:generate
   ```
4. Ajustar `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` en `.env` si difieren de los valores por defecto (`camara_comercio` / `root` / vacío).
5. Este proyecto tiene un **puerto Apache dedicado (8002)**, distinto del resto de proyectos en `htdocs` (que siguen en el 8000) — evita que este sistema comparta origen/URL con otras aplicaciones ajenas alojadas en el mismo `htdocs`. Configuración en `c:\xampp\apache\conf\httpd.conf` (`Listen 8002`, agregado junto al `Listen 8000` ya existente) y `c:\xampp\apache\conf\extra\httpd-vhosts.conf` (`<VirtualHost *:8002>` con `DocumentRoot` apuntando directo a `CamaraComercio/public`, sin subcarpeta). Iniciar Apache y MySQL desde el panel de control de XAMPP (o `httpd.exe`/`mysqld.exe` directamente).
6. Migrar y sembrar la base de datos (crea `camara_comercio` si no existe, carga roles/permisos/módulos/usuarios y asociados de ejemplo):
   ```
   php artisan migrate --seed
   ```
   El seeder imprime las credenciales generadas. **Cámbielas antes de usar el sistema fuera de un entorno de desarrollo.**
7. Crear el enlace simbólico de almacenamiento público (necesario para que las fotos de perfil se sirvan por URL):
   ```
   php artisan storage:link
   ```
8. Abrir en el navegador: `http://localhost:8002/` (sin subcarpeta, gracias al VirtualHost dedicado — ajustar si se cambia el puerto en `httpd-vhosts.conf` y `APP_URL`).

## Variables de entorno (`.env`)

| Variable | Descripción |
|---|---|
| `APP_ENV`, `APP_DEBUG` | Entorno y si se muestran errores detallados |
| `APP_URL` | URL base de la aplicación — `http://localhost:8002` (puerto dedicado vía VirtualHost, ver paso 5 de instalación; sin subcarpeta porque el `DocumentRoot` del vhost ya apunta a `public/`) |
| `APP_KEY` | Generada por `php artisan key:generate`, no compartir |
| `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Conexión a MySQL/MariaDB |
| `SESSION_DRIVER`, `SESSION_LIFETIME` | Configuración de sesión (por defecto `database`) |
| `MAIL_MAILER` | `log` por defecto — los correos (p. ej. recuperación de contraseña) se escriben en `storage/logs/laravel.log` en vez de enviarse; el proveedor SMTP real queda pendiente de definición (ver `docs/BACKLOG.md`) |

## Base de datos y migraciones

Migraciones nativas de Laravel en `database/migrations/`. Comandos habituales:

```
php artisan migrate              # aplicar migraciones pendientes
php artisan migrate:fresh --seed # recrear todo desde cero con datos de desarrollo
php artisan db:seed              # solo sembrar (requiere el esquema ya migrado)
```

Ver el modelo de datos completo en [`docs/DATA_MODEL.md`](docs/DATA_MODEL.md).

## Ejecución de pruebas

```
php artisan test
```

- `tests/Feature/` — pruebas de extremo a extremo por HTTP (login con límite de intentos, RBAC con 403 real, CRUD de asociados con validación, recuperación de contraseña de un solo uso, administración de usuarios/roles/módulos, generación de facturas, pagos, cartera y reportes con exportación, importación de asociados desde Excel, configuración de perfil propio incluida foto/contraseña), usando `RefreshDatabase` sobre **SQLite en memoria** (nunca toca la base de datos MySQL de desarrollo). 84 tests en total.
- `tests/Unit/` — reservado para lógica de dominio pura que no dependa del framework; hasta ahora toda la lógica de negocio ha cabido en Form Requests, accessors/scopes de Eloquent o clases de `app/Services/`, cubiertas por Feature tests (ver `docs/ARCHITECTURE.md`).

Nota: `phpunit.xml` sobreescribe `APP_URL` a un valor sin subcarpeta solo para el entorno de pruebas — ver el comentario en ese archivo y `docs/PROJECT_ANALYSIS.md` sección 10.4 si hace falta tocarlo.

## Estructura del proyecto

```
app/
  Http/Controllers/    Controladores (incluye Admin/ y Auth/)
  Http/Requests/        Form Requests (validación + autorización por endpoint)
  Models/                Modelos Eloquent (User, Role, Permission, Module, Associate, Invoice, Payment, AuditLog...)
  Services/              Casos de uso con lógica no trivial: InvoiceGenerationService, PaymentService,
                          PortfolioService, ReportService, ExportService, AssociateImportService,
                          DashboardService — ver docs/ARCHITECTURE.md
  Providers/             AppServiceProvider: Gate::before (RBAC) y directiva Blade @module
  helpers.php            format_money(), format_date(), icon() (inlinea SVGs de Lucide)
routes/web.php          Todas las rutas de la aplicación (login rate-limited a 5/min)
resources/views/        Plantillas Blade (layouts, auth, dashboard, associates —incl. import—, invoices,
                          payments, portfolio, reports, admin, components/ con el sistema de diseño)
database/migrations/    Esquema versionado
database/seeders/       Roles/permisos/módulos/usuarios/asociados de desarrollo
database/factories/     Factories para tests
tests/Feature/          Pruebas de extremo a extremo
public/assets/          CSS (tokens.css + app.css) / JS propios, iconos Lucide (SVG), Chart.js y
                          fuente Inter vendorizados, Bootstrap (solo grid/layout) — sin CDN
docs/                    Documentación del proyecto (incluye DESIGN_SYSTEM.md)
```

## Usuarios de desarrollo (creados por el seeder)

| Rol | Correo | Contraseña |
|---|---|---|
| Administrador | admin@camaracomercio.test | Admin#2026Local |
| Encargado de Cobranzas | cobranzas@camaracomercio.test | Cobranzas#2026Local |

## Roadmap

Ver el plan de sprints completo en [`docs/PROJECT_ANALYSIS.md`](docs/PROJECT_ANALYSIS.md) y el estado historia por historia en [`docs/BACKLOG.md`](docs/BACKLOG.md).

- **Sprint 1 (17–23 ago 2026):** ✅ Completado (reconstruido sobre Laravel el 17 ago) — acceso/seguridad, administración (módulos/usuarios/roles), asociados.
- **Sprint 2 (24–30 ago 2026):** ✅ Completado (17 ago, adelantado) — facturación mensual masiva, consulta de facturas, pagos totales/parciales, máquina de estados.
- **Sprint 3 (31 ago–6 sep 2026):** ✅ Completado (17 ago, adelantado) — cartera, a quién falta cobrar, estado de cuenta, reportes de cobranza/deuda y exportación a Excel/PDF.
- **Sprint 4 (7–13 sep 2026):** ✅ Completado (17 ago, adelantado) — importación de asociados desde Excel, límite de intentos de login, filtros de búsqueda en cartera, verificación responsiva en navegador real, y este checklist de producción.
- **Rediseño UI/UX (18 ago 2026):** ✅ Completado — identidad visual "Corporate Modern / Financial SaaS" propia de una Cámara de Comercio: tokens de diseño, iconografía Lucide, componentes Blade reutilizables, modal de confirmación y toasts propios (sin `window.confirm()` ni alertas nativas), dashboard con datos y gráficos reales, wizard de generación de facturación. Detalle en `docs/DESIGN_SYSTEM.md` y `docs/PROJECT_ANALYSIS.md` sección 10.10.
- **Calendarios/listas personalizados, formularios en modal y tema oscuro (18–19 ago 2026):** ✅ Completado — selectores de fecha/mes y listas desplegables con la identidad visual del sistema (reemplazan los popups nativos del navegador), crear/editar registros pequeños como overlay en vez de pantalla propia, toggle de tema oscuro/claro persistente, y colapso de sidebar sin saltos de posición. Detalle en `docs/DESIGN_SYSTEM.md` secciones 10–11 y `docs/PROJECT_ANALYSIS.md` secciones 10.11–10.12.
- **Configuración de perfil de usuario (19 ago 2026):** ✅ Completado — cualquier usuario autenticado edita su propia foto, nombre, correo y contraseña desde un modal accesible desde el topbar; foto servida desde el disco público de Laravel. Detalle en `docs/PROJECT_ANALYSIS.md` sección 10.19.
- **Auditoría de aptitud para producción (19 ago 2026):** ✅ Completada — 16 documentos nuevos (ver "Documentación" abajo) cubriendo integridad de datos, seguridad, protección de datos, backups, QA/UAT, deployment y manual de usuario; 20 decisiones de negocio pendientes formalizadas sin implementarlas por inferencia. Detalle en `docs/PROJECT_ANALYSIS.md` sección 10.20.
- **Anulación de pagos e identificador RUC del asociado (28 ago 2026):** ✅ Completado — un pago se anula (con motivo obligatorio), nunca se edita ni se elimina; el RUC se adoptó como identificador legal opcional del asociado. Resuelve las preguntas 21 y 12 de `docs/OPEN_BUSINESS_DECISIONS.md`. Detalle en `docs/PROJECT_ANALYSIS.md` sección 10.22.
- **Primer despliegue a producción, Hostinger hosting compartido (14 sep 2026):** ✅ Completado — dominio real, base de datos y usuarios de producción configurados. Detalle en `docs/PROJECT_ANALYSIS.md` sección 10.23.
- **Importación de facturas y pagos desde Excel (14 sep 2026):** ✅ Completado — mismo patrón cargar/previsualizar/confirmar que la importación de asociados; ninguno de los dos crea el registro padre (asociado o factura) si no existe de antemano. Cierra el hallazgo `XLS-04` de `docs/REQUIREMENTS_GAP_ANALYSIS.md`. Detalle en `docs/PROJECT_ANALYSIS.md` sección 10.24.
- **Campana de notificaciones (14 sep 2026):** ✅ Completado — feed compartido en el topbar con contador de no leídas, pestañas por categoría y "marcar todo como leído"; se genera al facturar en masa, anular un pago o completar una importación de Excel. Detalle en `docs/PROJECT_ANALYSIS.md` sección 10.25.

## Documentación

| Documento | Contenido |
|---|---|
| [`docs/PROJECT_ANALYSIS.md`](docs/PROJECT_ANALYSIS.md) | Historia completa del proyecto — decisiones técnicas, cada sprint, cada pivote |
| [`docs/BACKLOG.md`](docs/BACKLOG.md) | Estado historia por historia (23 HU + adicionales) |
| [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) | Por qué la arquitectura es como es (capas, RBAC, lecciones SQLite/MySQL) |
| [`docs/DATA_MODEL.md`](docs/DATA_MODEL.md) | Modelo de datos con el razonamiento de cada decisión |
| [`docs/DATA_SCHEMA.md`](docs/DATA_SCHEMA.md) | Esquema exhaustivo columna por columna, con cardinalidades |
| [`docs/BUSINESS_RULES.md`](docs/BUSINESS_RULES.md) | Catálogo de reglas de negocio, categorizadas Definida/Inferible/No definida |
| [`docs/OPEN_BUSINESS_DECISIONS.md`](docs/OPEN_BUSINESS_DECISIONS.md) | Las 20 decisiones de negocio pendientes de respuesta del cliente |
| [`docs/REQUIREMENTS_GAP_ANALYSIS.md`](docs/REQUIREMENTS_GAP_ANALYSIS.md) | Qué está implementado, parcial o faltante frente a los requisitos de producción |
| [`docs/TECHNICAL_SPECIFICATION.md`](docs/TECHNICAL_SPECIFICATION.md) | Especificación técnica consolidada (frontend, backend, seguridad, integraciones) |
| [`docs/EXCEL_MIGRATION_SPECIFICATION.md`](docs/EXCEL_MIGRATION_SPECIFICATION.md) | Formato, validaciones y flujo de la importación de asociados |
| [`docs/DATA_PROTECTION.md`](docs/DATA_PROTECTION.md) | Qué datos personales se manejan, quién accede, cómo se protegen |
| [`docs/BACKUP_AND_RECOVERY.md`](docs/BACKUP_AND_RECOVERY.md) | Propuesta de backups (no configurados todavía) |
| [`docs/QA_PLAN.md`](docs/QA_PLAN.md) | Plan de pruebas, casos cubiertos y pendientes |
| [`docs/UAT_PLAN.md`](docs/UAT_PLAN.md) / [`docs/UAT_SEED_DATA.md`](docs/UAT_SEED_DATA.md) | 9 escenarios de aceptación de usuario y los datos ficticios para ejecutarlos |
| [`docs/ACCEPTANCE_CRITERIA.md`](docs/ACCEPTANCE_CRITERIA.md) | Criterios Given/When/Then detallados para cada HU |
| [`docs/DEFINITION_OF_DONE.md`](docs/DEFINITION_OF_DONE.md) | Cuándo una historia o el proyecto completo está realmente terminado |
| [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) | Entornos, variables, migración, rollback, health checks |
| [`docs/USER_MANUAL.md`](docs/USER_MANUAL.md) | Manual para el personal de la Cámara, sin lenguaje técnico |
| [`docs/CHANGE_MANAGEMENT.md`](docs/CHANGE_MANAGEMENT.md) | Cómo se clasifica y aprueba cualquier cambio futuro |
| [`docs/DESIGN_SYSTEM.md`](docs/DESIGN_SYSTEM.md) | Sistema de diseño visual completo |

## Antes de desplegar a producción

Este MVP fue desarrollado y verificado en un entorno local XAMPP. Antes de exponerlo:

1. **Entorno:** `APP_ENV=production`, `APP_DEBUG=false` (evita filtrar trazas de error a usuarios finales), `APP_URL` con el dominio real y HTTPS.
2. **Correo:** definir un proveedor SMTP real en `MAIL_MAILER`/`MAIL_HOST`/etc. — hoy los correos (recuperación de contraseña) solo se escriben en `storage/logs/laravel.log` (ver `docs/BACKLOG.md`, HU-03).
3. **Rendimiento:** `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache` tras cada despliegue.
4. **Base de datos:** respaldos periódicos (`mysqldump`) antes de cada migración en producción; esta instalación de XAMPP en particular tiene antecedentes de corrupción de MariaDB (ver `docs/PROJECT_ANALYSIS.md` sección 2) — verificar la integridad del servidor de destino antes de migrar datos reales.
5. **Sesiones:** confirmar que las cookies de sesión tengan `Secure` activado (automático detrás de HTTPS) y revisar `SESSION_LIFETIME` según la política de la Cámara.
6. **Usuarios:** cambiar las contraseñas de los usuarios de desarrollo (`admin@camaracomercio.test`, `cobranzas@camaracomercio.test`) o eliminarlos y crear cuentas reales antes de dar acceso a usuarios finales.
7. **Permisos de archivos:** `storage/` y `bootstrap/cache/` deben ser escribibles por el usuario del servidor web; nada bajo `storage/app/imports` debe quedar accesible públicamente (ya está fuera de `public/`). `php artisan storage:link` debe ejecutarse en el servidor de destino (no es algo que se versione en git) para que las fotos de perfil (`storage/app/public/avatars`) se sirvan correctamente; incluir esa carpeta en los respaldos periódicos junto con la base de datos.
