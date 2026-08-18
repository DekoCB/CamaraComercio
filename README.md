# Sistema de Facturación y Cobranzas — Cámara de Comercio

Sistema web para automatizar la facturación y cobranza mensual de los asociados de la Cámara de Comercio, reemplazando el proceso manual en Excel. Documentación funcional completa en [`docs/PROJECT_ANALYSIS.md`](docs/PROJECT_ANALYSIS.md), backlog en [`docs/BACKLOG.md`](docs/BACKLOG.md).

**Estado actual:** Sprints 1, 2 y 3 completados sobre **Laravel 12 + MySQL** — acceso y seguridad, administración de módulos/usuarios/roles, gestión de asociados, facturación mensual masiva, pagos totales/parciales, cartera/morosidad/estado de cuenta, y reportes exportables a Excel/PDF. (El proyecto arrancó sobre un micro-framework PHP propio y fue migrado a Laravel a pedido explícito; ver `docs/PROJECT_ANALYSIS.md` sección 10 para el detalle del pivote.)

## Stack

- **Laravel 12** (PHP 8.2) — Eloquent ORM, Blade, guard de autenticación y broker de recuperación de contraseña nativos
- **MySQL / MariaDB**
- Bootstrap 5 + Bootstrap Icons (servidos localmente, sin CDN) + JavaScript vanilla puntual — sin SPA
- `phpoffice/phpspreadsheet` (Excel) y `dompdf/dompdf` (PDF) — exportación de reportes (Sprint 3); importación desde Excel queda para Sprint 4
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
5. Iniciar Apache y MySQL desde el panel de control de XAMPP.
6. Migrar y sembrar la base de datos (crea `camara_comercio` si no existe, carga roles/permisos/módulos/usuarios y asociados de ejemplo):
   ```
   php artisan migrate --seed
   ```
   El seeder imprime las credenciales generadas. **Cámbielas antes de usar el sistema fuera de un entorno de desarrollo.**
7. Abrir en el navegador: `http://localhost:8000/CamaraComercio/public/` (ajustar host/puerto según la configuración de Apache y `APP_URL` en `.env`).

## Variables de entorno (`.env`)

| Variable | Descripción |
|---|---|
| `APP_ENV`, `APP_DEBUG` | Entorno y si se muestran errores detallados |
| `APP_URL` | URL base de la aplicación (incluye la subcarpeta `/CamaraComercio/public` en este entorno XAMPP) |
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

- `tests/Feature/` — pruebas de extremo a extremo por HTTP (login, RBAC con 403 real, CRUD de asociados con validación, recuperación de contraseña de un solo uso, administración de usuarios/roles/módulos, generación de facturas, pagos, cartera y reportes con exportación), usando `RefreshDatabase` sobre **SQLite en memoria** (nunca toca la base de datos MySQL de desarrollo). 59 tests en total.
- `tests/Unit/` — reservado para lógica de dominio pura que no dependa del framework; hasta ahora toda la lógica de negocio ha cabido en Form Requests, accessors/scopes de Eloquent o clases de `app/Services/`, cubiertas por Feature tests (ver `docs/ARCHITECTURE.md`).

Nota: `phpunit.xml` sobreescribe `APP_URL` a un valor sin subcarpeta solo para el entorno de pruebas — ver el comentario en ese archivo y `docs/PROJECT_ANALYSIS.md` sección 10.4 si hace falta tocarlo.

## Estructura del proyecto

```
app/
  Http/Controllers/    Controladores (incluye Admin/ y Auth/)
  Http/Requests/        Form Requests (validación + autorización por endpoint)
  Models/                Modelos Eloquent (User, Role, Permission, Module, Associate, Invoice, Payment, AuditLog...)
  Services/              Casos de uso con lógica no trivial: InvoiceGenerationService, PaymentService,
                          PortfolioService, ReportService, ExportService — ver docs/ARCHITECTURE.md
  Providers/             AppServiceProvider: Gate::before (RBAC) y directiva Blade @module
routes/web.php          Todas las rutas de la aplicación
resources/views/        Plantillas Blade (layouts, auth, dashboard, associates, invoices, payments,
                          portfolio, reports, admin)
database/migrations/    Esquema versionado
database/seeders/       Roles/permisos/módulos/usuarios/asociados de desarrollo
database/factories/     Factories para tests
tests/Feature/          Pruebas de extremo a extremo
public/assets/          CSS/JS propios + Bootstrap/Bootstrap Icons (vendorizados, sin CDN)
docs/                    Documentación del proyecto
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
- **Sprint 4 (7–13 sep 2026):** UX/responsive, importación desde Excel, QA integral, cierre.
