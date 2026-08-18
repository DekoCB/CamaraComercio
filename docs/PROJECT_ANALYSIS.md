# PROJECT_ANALYSIS.md — Sistema de Facturación y Cobranzas (Cámara de Comercio)

Fecha de análisis: 2026-08-17
Autor: Equipo de desarrollo (asistido por IA)

## 1. Estado actual del repositorio

- Repositorio git inicializado en `c:\xampp\htdocs\CamaraComercio`, rama `main`, **sin commits** y sin archivos de aplicación. Proyecto greenfield.
- No existe frontend, backend, base de datos, ni configuración previa que deba conservarse.
- No hay `composer.json`, `package.json`, `.env`, ni estructura de carpetas previa.

## 2. Stack detectado en el entorno (no en el repo)

El repositorio corre bajo una instalación XAMPP local:

| Herramienta | Versión detectada | Notas |
|---|---|---|
| PHP | 8.2.12 (ZTS, Apache module + CLI) | `c:\xampp\php` |
| Composer | 2.8.3 | Disponible en PATH |
| Node.js | v22.18.0 | Disponible, solo se usará si hace falta tooling de assets (no imprescindible para el MVP) |
| npm | 10.8.3 | Disponible |
| MariaDB | 10.4.32 (mysqld.exe activo, PID en ejecución) | Servidor de BD activo bajo `c:\xampp\mysql` |
| Apache | XAMPP incluido | Servirá `public/` como docroot del vhost |

**Riesgo detectado:** existe una carpeta `c:\xampp\mysql_corrupt_backup_20260704_095255` y varios logs (`mysqld_restart*.log`, `mysql_debug_out.log`) que sugieren un incidente previo de corrupción/reinicio de MySQL en esta instalación XAMPP (ajeno a este proyecto, no se toca). Antes de correr migraciones en Sprint 1 se validará que el servicio esté sano y se recomienda al usuario mantener respaldos regulares de la base de datos del proyecto una vez creada.

## 3. Arquitectura propuesta

Dado que el entorno de despliegue es XAMPP (Apache + PHP + MariaDB) y el equipo probablemente da mantenimiento en ese mismo stack, se descarta introducir un framework pesado o un pipeline de build de frontend (SPA) que añadiría complejidad operativa innecesaria para un sistema administrativo interno de una Cámara de Comercio. Se opta por:

- **Backend:** PHP 8.2 puro, organizado en capas (arquitectura limpia/modular), con autoload PSR-4 vía Composer. Sin framework MVC pesado; un front controller propio (`public/index.php`) + router ligero propio (pocas líneas, sin dependencia externa) resuelve las rutas HTML y las rutas `/api/*` (JSON) con el mismo motor.
- **Persistencia:** PDO + MySQL/MariaDB, con capa de Repositorios (Repository pattern) que aísla el SQL del dominio. Sin ORM pesado (Eloquent/Doctrine) — el modelo de datos es acotado y un ORM completo sería sobre-ingeniería para el alcance definido.
- **Frontend:** Renderizado en servidor (plantillas PHP con vistas parciales reutilizables) + Bootstrap 5 (servido localmente, sin CDN, para evitar dependencia de red) + JavaScript vanilla con `fetch()` para interacciones AJAX (modales de pago, filtros, exportaciones, importación Excel). No se introduce SPA/React/Vue: el caso de uso (formularios administrativos, tablas, filtros) no lo justifica y evita duplicar lógica de validación en dos stacks.
- **Autenticación:** Sesiones nativas de PHP (`session_start`) con cookies `HttpOnly`, `SameSite=Lax` y flag `Secure` cuando se sirva por HTTPS; contraseñas con `password_hash`/`password_verify` (bcrypt). CSRF token por formulario.
- **Autorización:** Middleware de autenticación + middleware de permisos por módulo/acción, evaluado en servidor en cada ruta (nunca solo ocultando botones en el frontend), según exige la sección 17 del prompt maestro.
- **Migraciones/Seeders:** Sistema propio y mínimo (tabla `migrations`, archivos SQL versionados numerados, ejecutados por un script CLI `bin/migrate.php`). Se evita añadir Phinx/Doctrine Migrations como dependencia adicional cuando un runner de <150 líneas cubre la necesidad real.
- **Exportación:** `phpoffice/phpspreadsheet` (Excel) y `dompdf/dompdf` (PDF) vía Composer — ambas son librerías PHP puras, ampliamente usadas, sin binarios externos, justificadas directamente por HU-15 y por la importación de Excel (sección 15). No se sustituyen por soluciones caseras: generar XLSX/PDF a mano es reinventar una rueda compleja y propensa a errores.
- **Testing:** PHPUnit (dependencia de desarrollo) para pruebas unitarias (cálculo de saldos, máquina de estados de factura) y de integración (repositorios contra una BD de pruebas).

### Capas (Clean Architecture simplificada)

```
src/
  Domain/            Entidades y reglas de negocio puras (Invoice, Payment, Associate...)
                     Sin dependencias de framework ni de PDO.
  Application/       Casos de uso / servicios de aplicación (GenerateMonthlyInvoices,
                     RegisterPayment, GetDebtors...). Orquestan Domain + Repositorios.
  Infrastructure/    Implementación de repositorios sobre PDO/MySQL, adaptadores de
                     Excel/PDF, conexión a BD, migraciones.
  Http/              Router, Controllers, Middleware (auth, permisos, CSRF), Request/Response.
public/              Front controller (index.php) + assets estáticos (css/js/vendor).
resources/views/     Plantillas PHP (layout, parciales, vistas por módulo).
database/            migrations/, seeders/.
config/              Carga de variables de entorno y configuración de la app.
tests/               PHPUnit: Unit/ e Integration/.
docs/                Documentación del proyecto (este archivo, arquitectura, API, backlog).
```

Esto separa negocio (Domain/Application) de infraestructura (BD, HTTP), cumpliendo la sección 22 del prompt maestro y dejando la puerta abierta a futuras integraciones (pasarela de pagos, comprobantes electrónicos) sin reescribir el dominio — solo se añadirían nuevos adaptadores en `Infrastructure/`.

## 4. Modelo de datos propuesto (resumen — detalle en `docs/DATA_MODEL.md`)

Entidades mínimas: `users`, `roles`, `permissions`, `role_permissions`, `modules`, `role_modules`, `associates`, `invoices`, `payments`, `password_resets`, `audit_logs`, `migrations`.

Relación conceptual principal (según sección 23 del prompt):

```
associates (1) ──< invoices (1) ──< payments
```

El saldo de una factura **nunca se almacena directamente**: se deriva de `invoice.amount - SUM(payments.amount)` (con una columna `paid_total` desnormalizada y recalculada transaccionalmente en cada registro de pago, para que las lecturas de listados/reportes no recalculen agregados en cada consulta — ver `docs/DATA_MODEL.md` para la justificación).

Restricción de integridad clave: `UNIQUE(associate_id, period)` en `invoices` para prevenir duplicados de facturación por asociado/período (HU-06).

## 5. Módulos del sistema (sección 16 del prompt)

`dashboard`, `associates`, `billing`, `payments`, `portfolio`, `reports`, `administration` — modelados como filas en la tabla `modules`, activables/desactivables por el administrador, y usados tanto para la navegación (sidebar) como para el chequeo de autorización en el backend.

## 6. Problemas encontrados

Ninguno de código (no hay código previo). Riesgos identificados:

1. Historial de corrupción de MariaDB en esta instalación XAMPP (ver sección 2) — mitigar con respaldos (`mysqldump`) antes de operaciones destructivas y verificación de integridad post-migración.
2. El prompt maestro es extremadamente amplio (48 secciones, 4 sprints, ~23 HUs con auditoría, import/export, tests en 3 niveles). No es realista completarlo en una sola sesión de trabajo; se ejecuta de forma incremental por sprint, tal como el propio documento exige (sección 39: "NO intentes construir todo el sistema en una sola operación").

## 7. Plan de implementación

Se sigue el plan de sprints ya definido en la documentación funcional (sección 10 del PDF), sin alterar HUs, épicas ni prioridades:

- **Sprint 1 (17–23 ago 2026):** Scaffold del proyecto, migraciones y seeders base, EP-01 (login/logout/recuperar contraseña), EP-02 (módulos, usuarios, roles/permisos), EP-03 (registrar/actualizar asociado). Layout base responsivo (sidebar + topbar) para que los siguientes sprints construyan sobre una plataforma estable.
- **Sprint 2 (24–30 ago 2026):** EP-04 (facturación masiva + consulta), EP-05 (pagos totales/parciales), máquina de estados de factura.
- **Sprint 3 (31 ago–6 sep 2026):** EP-06 (cartera, morosidad, estado de cuenta), EP-07 (reportes + exportación Excel/PDF).
- **Sprint 4 (7–13 sep 2026):** EP-08 (UX: pantalla principal, filtros, responsive, confirmaciones), importación desde Excel, QA/pruebas integrales, endurecimiento de seguridad, preparación para producción.

Cada sprint se cierra con: ejecución de tests, actualización de `docs/BACKLOG.md`, y un resumen de lo implementado antes de continuar al siguiente.

## 8. Decisiones técnicas registradas

| # | Decisión | Justificación |
|---|---|---|
| D1 | PHP puro en capas, sin framework MVC completo | Evita complejidad/dependencias innecesarias para el alcance definido; equipo de mantenimiento probable ya opera en XAMPP/PHP plano |
| D2 | Sin SPA (React/Vue); vistas server-rendered + fetch para AJAX puntual | El caso de uso es CRUD administrativo con tablas/filtros; una SPA duplicaría validación y añadiría build pipeline sin beneficio claro |
| D3 | PDO + Repository pattern, sin ORM | Modelo de datos acotado (7 entidades núcleo); un ORM completo es sobre-ingeniería |
| D4 | Runner de migraciones propio (sin Phinx/Doctrine) | Necesidad cubierta con <150 líneas; se evita una dependencia más |
| D5 | `phpspreadsheet` + `dompdf` para Excel/PDF | Requisito funcional explícito (HU-15, sección 15); reinventar generación de XLSX/PDF es alto riesgo/bajo valor |
| D6 | Saldo de factura desnormalizado (`paid_total`) recalculado transaccionalmente | Evita recalcular agregados en cada lectura de listados/dashboard/reportes, manteniendo consistencia vía transacción en el registro de pago |
| D7 | Autorización verificada en backend (middleware), no solo oculta en UI | Requisito explícito de seguridad (sección 17) |

## 9. Próximo paso (histórico — ver sección 10 para el estado vigente)

Publicar resumen al usuario y comenzar Sprint 1: scaffold de Composer, migraciones, seeders, EP-01/EP-02/EP-03.

---

## 10. Adenda — 2026-08-17: pivote a Laravel + MySQL

Tras completar Sprint 1 sobre el stack descrito en las secciones 1–9 (PHP plano en capas, verificado end-to-end), el usuario solicitó explícitamente reconstruir el sistema **sobre Laravel, con MySQL como base de datos**. Esta sección documenta la migración; las secciones 1–9 se conservan como registro histórico de la decisión original y su justificación en ese momento.

### 10.1 Qué cambió y qué no

- **Cambia el framework:** de un micro-framework propio (router/DB/vistas hechos a mano) a **Laravel 12** (la versión estable más reciente compatible con PHP 8.2; Laravel 13 requiere PHP ^8.3, no disponible en este XAMPP).
- **Cambia el motor de vistas:** de plantillas PHP planas a **Blade** (`@extends`, `@section`, componentes `@can`/`@module`).
- **Cambia el acceso a datos:** de PDO + Repository pattern manual a **Eloquent** (ORM de Laravel). Se abandona la decisión D3 original ("sin ORM, sobre-ingeniería") porque, una vez dentro de Laravel, usar Eloquent es lo idiomático — pelear contra el ORM del framework elegido sí sería sobre-ingeniería.
- **Cambia el sistema de migraciones:** de un runner SQL propio a las **migraciones nativas de Laravel** (`database/migrations/*.php`, `php artisan migrate`). Se abandona la decisión D4 original por el mismo motivo.
- **Cambia la autenticación/autorización:** de sesiones PHP + middleware manual a los mecanismos nativos de Laravel (`Auth::attempt`, guard `web`, broker de `Password::sendResetLink`/`Password::reset`, middleware `auth`/`can:` ya incluidos en el framework). La autorización sigue siendo un requisito no negociable verificado en backend (sección 17 del prompt maestro) — ver 10.3.
- **No cambia:** el modelo de datos conceptual (sección 4), la relación `associates → invoices → payments`, el catálogo de módulos, ni las decisiones D5 (phpspreadsheet/dompdf), D6 (`paid_total` desnormalizado) y D7 (autorización en backend), que siguen vigentes tal cual bajo Laravel.
- **No cambia:** el frontend sigue siendo renderizado en servidor (ahora Blade) + Bootstrap 5 local + JS vanilla — la decisión D2 (sin SPA) sigue aplicando.

### 10.2 Cómo se ejecutó la migración

1. Se generó un proyecto Laravel 12 limpio (`composer create-project laravel/laravel`) en un directorio temporal y se fusionó dentro de `CamaraComercio/`, reemplazando por completo `src/`, el `public/` anterior, `resources/views/*.php`, `bin/`, `config/app.php` propio y el runner de migraciones — se conservaron `.git/` y `docs/`.
2. Se recreó el esquema como migraciones Laravel (`database/migrations/2026_08_17_1000xx_*.php`), preservando exactamente las mismas tablas, columnas, índices y claves foráneas descritas en la sección 4 y en `docs/DATA_MODEL.md`.
3. Se recrearon los assets de Bootstrap 5/Bootstrap Icons (servidos localmente, sin CDN — sigue vigente esa decisión) dentro del nuevo `public/assets/vendor/`.
4. Se reimplementaron las historias de Sprint 1 (EP-01, EP-02, EP-03) como controladores + Form Requests + modelos Eloquent + vistas Blade + rutas de Laravel.
5. Se migró y sembró la base de datos MySQL (`camara_comercio`) desde cero con `php artisan migrate:fresh --seed`.
6. Se reescribió la suite de pruebas como **Feature tests** de Laravel (`tests/Feature/`, `RefreshDatabase`, SQLite en memoria — ver 10.4) cubriendo exactamente los mismos casos verificados manualmente en la versión anterior (login válido/inválido, usuario inactivo, RBAC con 403 real, CRUD de asociados con validación, reset de contraseña de un solo uso).
7. Se verificó manualmente el flujo completo por HTTP contra Apache (login, dashboard, asociados, administración, RBAC, reset de contraseña) antes y después de escribir los tests automatizados.

### 10.3 RBAC bajo Laravel

En vez de middleware de permisos escrito a mano, se usa el mecanismo nativo `Gate::before()` (registrado en `AppServiceProvider::boot()`): cualquier código de permiso (`can('associates.manage')`, middleware `can:admin.users`, `@can` en Blade) se resuelve contra `role_permissions` sin necesidad de declarar `Gate::define()` uno por uno — así un administrador puede seguir creando permisos nuevos desde la UI (HU-23) sin que eso requiera tocar código. La visibilidad de módulos en el sidebar (independiente de los permisos de acción) usa una directiva Blade propia `@module('code')`, respaldada por `role_modules`. La autorización se sigue verificando en el backend en cada ruta protegida — nunca solo ocultando el enlace en el menú —, confirmado con un test (`RbacTest::test_user_without_admin_users_permission_gets_403_on_direct_url`) que pide la URL de administración directamente con un usuario que no tiene ese permiso.

### 10.4 Nota sobre las pruebas y `APP_URL`

El `.env` de desarrollo usa `APP_URL=http://localhost:8000/CamaraComercio/public` (subcarpeta de XAMPP). El cliente de pruebas HTTP de Laravel construye las URLs de cada request a través de `config('app.url')`, así que un `APP_URL` con subcarpeta rompe el enrutamiento **dentro de los tests** (no en producción/desarrollo real, donde Apache resuelve la subcarpeta a nivel de servidor). Por eso `phpunit.xml` sobreescribe `APP_URL=http://localhost` solo para el entorno de pruebas — ver el comentario en ese archivo. Las pruebas corren contra SQLite en memoria (`DB_DATABASE=:memory:`), nunca contra la base de datos MySQL de desarrollo; las migraciones que usan `CHECK` constraints (`invoices`, `payments`) están condicionadas a `DB::connection()->getDriverName() === 'mysql'` porque SQLite no soporta `ALTER TABLE ADD CONSTRAINT`.

### 10.5 Decisiones técnicas actualizadas

| # | Decisión | Estado | Justificación |
|---|---|---|---|
| D1 | Framework | **Reemplazada** | Laravel 12 en vez de PHP plano en capas — instrucción explícita del usuario |
| D3 | Sin ORM | **Reemplazada** | Eloquent es lo idiomático una vez dentro de Laravel |
| D4 | Runner de migraciones propio | **Reemplazada** | Se usan las migraciones nativas de Laravel |
| D8 | RBAC vía `Gate::before()` + tabla `role_permissions`, sin `spatie/laravel-permission` | Nueva | El modelo ya incluye el concepto adicional de "módulos" (visibilidad de menú, distinto de permisos de acción) que el paquete no cubre; el volumen de código propio necesario es pequeño (un `Gate::before` + una directiva Blade) |
| D9 | `Password::sendResetLink`/`Password::reset` nativos de Laravel en vez de un flujo de tokens hecho a mano | Nueva | HU-03 (expiración, uso único, no revelar si el correo existe) ya está cubierto por el broker nativo; reimplementarlo sería puro riesgo sin beneficio |
| D10 | `APP_URL` sin sufijo de subcarpeta en `phpunit.xml` (solo entorno de pruebas) | Nueva | Ver 10.4 |
| D5, D6, D7, D2 | Sin cambios | — | Siguen aplicando tal como se documentaron originalmente |

### 10.6 Sprint 2 — completado el mismo día (17 ago 2026)

EP-04 (facturación mensual masiva + consulta) y EP-05 (pagos totales/parciales) se implementaron inmediatamente después del pivote a Laravel, sobre los modelos Eloquent `Invoice`/`Payment` (tablas ya migradas desde Sprint 1):

- `App\Services\InvoiceGenerationService`: genera una factura por asociado activo para un período, omite a quienes ya la tienen (protegido además por el `UNIQUE(associate_id, period)` de la base de datos), y devuelve un resumen de creadas/omitidas/con error para auditoría.
- `App\Services\PaymentService`: registra un pago (total o parcial) dentro de una transacción con `lockForUpdate()` sobre la factura, para que dos pagos concurrentes no puedan sobrepasar el saldo conjuntamente; rechaza cualquier pago mayor al saldo pendiente.
- La máquina de estados vive en `App\Models\Invoice`: la columna `status` solo refleja `PENDIENTE`/`PARCIAL`/`PAGADA` (mantenida por `PaymentService`); `VENCIDA` se calcula al leer (`effectiveStatus()`) porque depende de la fecha actual, no de un evento de escritura — ver el razonamiento completo en `docs/DATA_MODEL.md`. Esta fue la primera vez que se evaluó la nota de `docs/ARCHITECTURE.md` sobre extraer lógica a `app/Services/` cuando la lógica de negocio deja de ser trivial para un modelo Eloquent solo; se decidió que sí ameritaba un servicio dedicado en ambos casos (generación masiva con manejo de errores por ítem, y registro de pago con bloqueo transaccional), no una reintroducción de capas Domain/Application completas.
- 17 pruebas Feature nuevas (`tests/Feature/InvoiceGenerationTest.php`, `tests/Feature/PaymentTest.php`), suite completa en 46/46 verde.
- Verificado en vivo contra Apache+MySQL: generación masiva para 5 asociados de desarrollo, pago parcial con recálculo de saldo, rechazo de sobrepago, y una factura vencida mostrando el badge `VENCIDA` correctamente aunque su columna `status` almacenada sea `PARCIAL`.

### 10.7 Sprint 3 — completado el mismo día (17 ago 2026)

EP-06 (cartera/morosidad/estado de cuenta) y EP-07 (reportes + exportación) se implementaron a continuación de Sprint 2, con confirmación previa del usuario para seguir en modo autónomo:

- `App\Services\PortfolioService`: `debtSummary()` (HU-10, todos los asociados con sus totales), `debtors()` (HU-11, solo quienes deben) y `statement()` (HU-12, hoja de vida de un asociado). El filtro de `debtors()` originalmente usaba `havingRaw()` sobre los alias de `withSum()` — funcionaba en MySQL pero rompía en el SQLite de los tests ("HAVING clause on a non-aggregate query", porque SQLite exige que un HAVING sin GROUP BY solo aparezca junto a una función agregada real en el nivel superior de la consulta, cosa que MySQL no exige). Se resolvió reemplazándolo por `whereHas('invoices', fn ($q) => $q->where('status', '!=', 'PAGADA'))`, que además es más correcto: por el propio invariante de la máquina de estados (`PAGADA` solo cuando `paid_total >= amount`), "tiene una factura que no está `PAGADA`" es exactamente equivalente a "debe algo", sin necesitar comparar sumas en absoluto.
- `App\Services\ReportService`: `collections()` (HU-13) y `pendingDebt()` (HU-14). La distribución de deuda por estado (`PENDIENTE`/`PARCIAL`/`VENCIDA`) se calcula con SQL agregado (`GROUP BY`) reimplementando la misma regla que `Invoice::effectiveStatus()` — "hoy" se pasa como parámetro ligado (`?`) en vez de `CURDATE()` (función SQL específica de MySQL) para que la consulta funcione igual contra SQLite en los tests.
- `App\Services\ExportService` (HU-15): un único servicio para exportar a Excel (`phpoffice/phpspreadsheet`, con bloque de título/fecha de generación/período y fila de totales) y a PDF (`dompdf/dompdf`, renderizando una vista Blade dedicada por reporte). Se implementó para los dos reportes de esta épica; extenderlo a cartera/morosidad queda pendiente de que se pida explícitamente. La exportación exige el permiso `reports.export`, separado de `reports.view` — un rol puede ver un reporte en pantalla sin poder extraer el archivo.
- 13 pruebas Feature nuevas (`tests/Feature/PortfolioTest.php`, `tests/Feature/ReportTest.php`), suite completa en 59/59 verde.
- Verificado en vivo contra Apache+MySQL: cartera y "a quién falta cobrar" con datos reales, estado de cuenta de un asociado, reporte de cobranza distinguiendo devengo vs. caja para el mes, reporte de deuda con distribución `VENCIDA`, y los cuatro archivos exportados (Excel/PDF × cobranza/deuda) — el `.xlsx` se releyó con PhpSpreadsheet para confirmar su contenido y el `.pdf` se verificó con la cabecera `%PDF-1.7`.

### 10.8 Próximo paso

Sprint 4 (7–13 sep 2026): EP-08 (pantalla principal, información ordenada, búsqueda/filtros y mensajes de confirmación — ya tienen una base sólida desde Sprint 1, falta pulido final), importación de información histórica desde Excel, pruebas integrales (QA), endurecimiento de seguridad y preparación para producción.
