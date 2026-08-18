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

### 10.8 Sprint 4 — completado el mismo día (17 ago 2026)

EP-08 (experiencia de usuario), importación desde Excel, QA y endurecimiento de seguridad, con confirmación previa del usuario para seguir en modo autónomo:

- **Importación de asociados (sección 15):** `App\Services\AssociateImportService` + `AssociateImportController`, con el flujo de tres pasos exigido explícitamente por el prompt maestro — cargar → previsualizar con errores por fila → confirmar (o cancelar). El archivo se guarda en `storage/app/imports/` con un nombre aleatorio (UUID) al subirlo, y el paso de confirmación **vuelve a parsear ese archivo desde disco** en vez de confiar en cualquier dato que el navegador reenvíe junto con el POST de confirmación — evita que alguien manipule el formulario para colar filas que nunca pasaron por validación. El archivo temporal se borra al confirmar o al cancelar. Encabezados reconocidos en español, en cualquier orden de columnas (Nombre/Empresa/Contacto/Correo); solo "Nombre" es obligatorio.
- **HU-18, hueco cerrado:** `/portfolio` y `/portfolio/debtors` no tenían buscador (a diferencia de asociados/facturas/pagos, que sí desde sprints anteriores). Se agregó un filtro por nombre/empresa a `PortfolioService::baseQuery()`, reutilizado por ambos métodos.
- **HU-19, verificado con navegador real, no solo revisión de CSS:** se usó Playwright (Chromium headless, viewport 375×812) contra la app corriendo en Apache. Primer hallazgo interesante: el `.click()` normal de Playwright no disparaba el listener del botón de menú en este entorno headless concreto, mientras que `dispatch_event('click')` sí — se confirmó que el código de la aplicación es correcto (la clase `open` se aplica, la transición CSS `translateX` funciona, `matchMedia` coincide) y que la discrepancia era un artefacto de interacción sintética de Playwright, no un bug real. Con eso descartado: el `<body>` nunca excede el ancho del viewport (dashboard y una tabla de 7 columnas por igual), y una tabla ancha se desplaza dentro de su propio `.table-responsive` (710px de contenido en 292px visibles) sin arrastrar el resto de la página — exactamente el comportamiento que pedía la sección 21 del prompt maestro ("no simplemente reduzcas el tamaño de los elementos").
- **HU-20, hueco cerrado:** se agregó `data-confirm` a la desactivación de un módulo (afecta la navegación de todos los roles que lo tengan asignado), sumándose a la generación masiva de facturas y a la confirmación de importación que ya lo tenían. No hay borrados físicos en el sistema, así que no quedan más acciones "destructivas" sin confirmar.
- **Seguridad para producción:** los controladores de autenticación se escribieron a mano (no vienen de Breeze/Fortify), así que no traían límite de intentos de fábrica. Se agregó `throttle:5,1` a `POST /login`, `POST /forgot-password` y `POST /reset-password` — cierra el vector de fuerza bruta más obvio antes de exponer el sistema. Revisión adicional: sin salidas Blade sin escapar (`{!! !!}`) en ningún view, sin sentencias de depuración (`dd()`, `var_dump()`) olvidadas en `app/`, `.env.example` sin secretos reales.
- 12 pruebas Feature nuevas (9 de importación, 1 de rate limiting, 2 de filtros de cartera), suite completa en 71/71 verde.
- Se agregó un checklist de "antes de desplegar a producción" al `README.md` (entorno, correo, cachés de Laravel, respaldos de BD, cookies de sesión, credenciales de desarrollo, permisos de archivos) — es documentación, no una tarea de infraestructura ejecutada en esta sesión.

### 10.9 Sprints funcionales — cierre

El plan de sprints original (sección 7) queda completo: los cuatro sprints y las 23 historias de usuario de la documentación funcional están implementados, probados y verificados en vivo. Lo que sigue es explícitamente fuera de alcance del MVP (sección 6): integración contable, comprobantes electrónicos, pasarelas de pago, portal de asociados — cualquiera de esos requeriría una decisión de negocio explícita antes de comenzar, tal como se documentó desde la sección 41 del prompt maestro. La decisión pendiente más inmediata sigue siendo el proveedor de correo real (hoy `MAIL_MAILER=log`), que el usuario indicó que definiría más adelante.

### 10.10 Rediseño UI/UX — 2026-08-18 ("Corporate Modern / Financial SaaS")

A pedido explícito del usuario (segundo prompt maestro, 65 secciones), se rediseñó por completo la capa visual del sistema sin tocar lógica de negocio, rutas, controladores ni el esquema de base de datos — ningún test de `tests/Feature/` se modificó y los 71 siguen en verde. Decisiones y alcance documentados en detalle en [`docs/DESIGN_SYSTEM.md`](DESIGN_SYSTEM.md); resumen:

- **Tokens de diseño** (`public/assets/css/tokens.css`): paleta navy/azul/teal específica de una Cámara de Comercio (no una plantilla de dashboard genérica), tipografía Inter autoalojada (4 pesos, sin Google Fonts), escala de espaciado/radios/sombras/transiciones, modo oscuro preparado (no activado por UI) bajo `prefers-color-scheme`.
- **Iconografía:** se reemplazó Bootstrap Icons por Lucide, vendorizado como SVGs individuales (`public/assets/icons/`, ~57 iconos) e inlineados server-side vía el helper `icon()` (`app/helpers.php`) — sin CDN ni icon-font.
- **Componentes Blade nuevos** (`resources/views/components/`): `brand-mark`, `kpi-card`, `status-badge`, `page-header`, `empty-state`, `pagination-meta` — reutilizados en las 9 áreas del sistema, reemplazando el marcado Bootstrap-por-defecto anterior.
- **Modal de confirmación y toasts propios:** reemplazan `window.confirm()` y las alertas Bootstrap estáticas, por requerimiento explícito de la sección 65. Bug real encontrado y corregido durante la verificación con Playwright: el backdrop del modal (`opacity: 0` cuando cerrado) seguía capturando clics en toda la página porque `opacity: 0` no saca un elemento del hit-testing — se corrigió agregando `pointer-events: none` por defecto y `auto` solo con `.is-open`.
- **Dashboard con datos reales:** `App\Services\DashboardService` (nuevo) agrega KPIs, tendencia mes a mes (omitida si no hay mes anterior con qué comparar) y los datos para dos gráficos Chart.js vendorizado (cobranza mensual, distribución de cartera por estado) — sin invertir la regla de "no persistir `VENCIDA`" ya establecida (sección 10.5).
- **Wizard de generación de facturación:** `invoices/generate.blade.php` pasó de un formulario de una pantalla a un stepper de 4 pasos (Período/Monto/Fecha límite/Confirmar) con resumen antes de confirmar.
- **Verificación:** barrido con Playwright en dos viewports — 1440px (14 páginas, cero errores de consola) y 375px (todas las páginas del sistema, cero errores de consola). Un solo hallazgo de overflow horizontal real: el stepper del wizard no cabía en 375px porque mostraba las cuatro etiquetas de paso completas; se corrigió ocultando la etiqueta de los pasos no activos por debajo de 575.98px (solo el paso activo muestra texto, el resto queda como punto numerado + conector) — deja el patrón ya usado en Sprint 4 (nunca reducir tipografía para "que quepa", sino replantear qué se muestra).
- **Qué no cambió:** ninguna ruta, ningún Form Request, ningún Service de dominio, ningún permiso ni regla de RBAC. `AuthenticatedSessionController` solo ganó el checkbox "Recordarme" (`Auth::attempt($credentials, $request->boolean('remember'))`), que antes estaba cableado a `false`.
- **Logo:** no existe un logo oficial de la Cámara en el repositorio; `x-brand-mark` es un tratamiento tipográfico temporal, documentado como tal en `DESIGN_SYSTEM.md` para que se reemplace en cuanto haya un logo real (sección 52 del prompt).

### 10.11 Calendarios/listas personalizados y formularios en modal — 2026-08-18

A pedido del usuario, dos mejoras de UX sobre el rediseño de la sección 10.10, sin tocar reglas de negocio, rutas ni Form Requests (detalle completo en `docs/DESIGN_SYSTEM.md` sección 10):

- **Calendarios y listas desplegables:** los popups nativos de `<input type="date">`/`<input type="month">` y `<select>` los dibuja el navegador y no admiten CSS. Se reemplazaron por widgets propios (`app.js`: `enhanceSelects()`/`enhanceDateInputs()`) que envuelven el elemento nativo original (oculto, sigue siendo el que viaja en el `<form>`) en un trigger + popup con la paleta del sistema, navegación por teclado completa y alineación dinámica para no salirse del viewport.
- **Formularios en modal:** crear/editar asociado, usuario, rol (datos básicos) y módulo ahora se abren como overlay sobre la lista, no como pantalla propia. La matriz de permisos de un rol se dejó como pantalla completa a propósito — no es un "formulario pequeño". El mecanismo reutiliza el mismo partial Blade para navegación directa y para el fragmento AJAX; el envío intercepta el submit con `Accept: application/json`, lo que hace que Laravel responda 422+JSON ante errores de validación sin tocar ningún Form Request. Bug encontrado y corregido en la verificación: el flash de sesión de Laravel solo sobrevive una petición, y como `fetch()` ya sigue el redirect de éxito internamente, el toast se perdía — se relevó vía `sessionStorage`.
- **Verificación:** 71/71 tests sin cambios; Playwright en 1440px y 375px en las 15 pantallas — cero errores de consola. Un hallazgo real de overflow horizontal (popup de fecha saliéndose del viewport en el filtro de pagos en mobile, igual en naturaleza al bug de `pointer-events` del modal de confirmación de la sección 10.10: geometría `position: absolute` cuenta para el scroll de la página aunque esté oculta con `opacity: 0`) corregido con alineación dinámica izquierda/derecha.

### 10.12 Toggle de tema oscuro/claro y colapso de sidebar sin saltos — 2026-08-19

A pedido del usuario. Detalle completo en `docs/DESIGN_SYSTEM.md` sección 11:

- **Tema oscuro:** botón en el topbar, persistido en `localStorage`, aplicado antes de la primera pintura (script inline en `<head>` de ambos layouts, incluido el de invitado) para evitar parpadeo. Sin preferencia explícita, respeta `prefers-color-scheme` del sistema. Dos bugs reales encontrados y corregidos, ambos invisibles en modo claro: `.form-control:focus` heredaba un `background-color` blanco de mayor especificidad de Bootstrap (nuestra regla de foco no lo redeclaraba), y `.dropdown-menu` no tenía fondo propio y dependía del blanco por defecto de Bootstrap.
- **Sidebar sin saltos:** la causa de que los íconos "saltaran" al colapsar/expandir era que `justify-content` no es interpolable por CSS — el cambio `flex-start`↔`center` ocurría en un solo frame. Se quitó ese flip (padding/justify-content del nav-link ahora constantes); verificado con Playwright que la posición del ícono no se mueve ni un píxel durante la animación. El botón de colapsar desaparecía por un selector CSS que, por error de copiar-pegar, también ocultaba su propio ícono al colapsar — corregido.
- **Verificación:** 71/71 tests sin cambios (nada de esto toca PHP); Playwright en 8+ pantallas en modo oscuro sin fugas de fondo blanco, persistencia confirmada tras recargar y tras cerrar sesión.

### 10.14 Sidebar sin parpadeo, wizard de facturación en modal, gráfico sin líneas blancas — 2026-08-19

A pedido del usuario. Detalle completo en `docs/DESIGN_SYSTEM.md` sección 12:

- **Sidebar sin parpadeo al navegar:** con el sidebar colapsado, cada navegación (MPA server-rendered, recarga completa) lo mostraba expandido una fracción de segundo antes de colapsarse de golpe — mismo tipo de causa que el parpadeo de tema (sección 10.12): el estado se aplicaba en `app.js`, al final de `<body>`, después de que el sidebar ya había pintado expandido. Corregido aplicándolo pre-pintura en `<html>` (igual que el tema), con los selectores CSS actualizados de `.app-shell.is-collapsed` a `html.sidebar-collapsed .app-shell`.
- **Wizard de facturación como modal:** se suma al resto de formularios en modal (sección 10.11), con la particularidad de tener JS propio no trivial (4 pasos) que antes vivía en un `@push('scripts')` inservible para el fragmento AJAX — se trasladó a `initInvoiceWizard()` en `app.js`, escopado a `root` para funcionar igual en la página completa y en el modal. Dos bugs reales encontrados en la verificación: el sistema de confirmación (`data-confirm`, HU-20) y el envío por AJAX del modal nunca habían coexistido en el mismo formulario y competían entre sí (el envío arrancaba antes de mostrar la confirmación) — resuelto exponiendo `form.__submitViaAjax` para que el diálogo de confirmación lo invoque en vez de un submit nativo; y el diálogo de confirmación quedaba visualmente tapado detrás del modal de formulario por compartir el mismo z-index — resuelto con un z-index específico más alto para el modal de confirmación.
- **Gráfico sin líneas blancas:** la cuadrícula del eje Y del gráfico de cobranza mensual usaba un gris casi blanco fijo, pensado solo para modo claro. Se quitó la cuadrícula por completo (igual que ya tenía el eje X) en vez de detectar el tema en JS — más simple y funciona en ambos modos.
- **Verificación:** 71/71 tests sin cambios; Playwright confirmó cero parpadeo del sidebar (muestreo de ancho por frame), el flujo completo del wizard dentro del modal (los 4 pasos + confirmación + envío + toast), el diálogo de confirmación correctamente por encima del modal tras el fix de z-index, y que los formularios de confirmación nativos preexistentes (desactivar módulo) no sufrieron regresión.

### 10.16 Barra de scroll también sigue el tema — 2026-08-19

A pedido del usuario. Detalle completo en `docs/DESIGN_SYSTEM.md` sección 13. La barra de scroll del navegador se quedaba con los colores por defecto del sistema, sin importar el tema activo en la app. Corregido con `color-scheme: light|dark` en los mismos tres bloques de `tokens.css` que ya definen los tokens de color (señal estándar para que el navegador tematice su propia UI nativa), más `scrollbar-color`/`::-webkit-scrollbar-*` en `app.css` usando `var(--color-*)` — se re-teman solos, sin bloque de modo oscuro aparte. Aplicado con el selector universal para cubrir tanto el scroll de la página como el de cualquier contenedor con overflow propio en una sola regla; el sidebar (navy sin importar el tema) tiene su propio override translúcido. 71/71 tests sin cambios.

### 10.18 Calendarios recortados en el modal, botón de reporte atascado tras volver atrás, sidebar centrado — 2026-08-19

A pedido del usuario. Detalle completo en `docs/DESIGN_SYSTEM.md` sección 14:

- **Calendarios/listas recortados en el modal:** el popup de fecha se veía cortado dentro del wizard de facturación al abrirse en modal. Causa en dos capas: `.modal-form-body` tiene `overflow-y: auto` (necesario para formularios largos) y recorta cualquier descendiente que pinte fuera de su área visible; y el arreglo obvio (`position: fixed` calculado por JS) no alcanzaba por sí solo porque `.modal-panel` anima con `transform`, y un ancestro con `transform` se vuelve el *containing block* de sus descendientes `fixed` también, no solo de los `absolute` — un detalle poco conocido de la especificación. Se resolvió "porteando" los popups a hijos directos de `<body>` (el campo real del formulario no se mueve, solo el popup visual), con limpieza de huérfanos en cada apertura del modal y cierre del popup al hacer scroll de cualquier ancestro.
- **"Ver reporte" atascado tras volver con el botón Atrás:** el formulario GET de "Lo cobrado en el mes" marca su botón `disabled` en cada envío; el navegador puede capturar esa foto exacta en su back-forward cache al navegar al reporte, y volver atrás la restaura tal cual en vez de recargar. Corregido con el patrón estándar: un listener de `pageshow`/`event.persisted` que limpia cualquier botón atascado — genérico, cubre este caso y cualquier otro formulario del sistema. El enlace de "Deuda pendiente" es un `<a>` simple, nunca pasaba por este mecanismo; se verificó por separado que no queda en ningún estado distinto.
- **Sidebar centrado:** los íconos del menú quedaban apilados arriba con un vacío grande debajo, más notorio colapsado. Un `justify-content: center` en `.sidebar-nav` (que ya ocupaba todo el alto disponible vía `flex: 1`) los centra verticalmente.
- **Verificación:** 71/71 tests sin cambios; Playwright confirmó las 42 celdas del calendario dentro del viewport, el popup de select porteado y sincronizado con el `<select>` nativo, sin acumulación de popups huérfanos tras varios ciclos de abrir/cerrar el modal, y la limpieza correcta del estado `disabled` simulando una restauración de bfcache.

### 10.19 Próximo paso

Con los cuatro sprints funcionales, el rediseño UI/UX y las mejoras de UX de formularios/calendarios/tema/sidebar completos, lo pendiente es: (1) definir el proveedor SMTP real para producción (`MAIL_MAILER=log` hoy), (2) reemplazar `x-brand-mark` por el logo oficial cuando exista, y (3) cualquier extensión fuera del alcance original del MVP (integración contable, comprobantes electrónicos, pasarelas de pago, portal de asociados), que requeriría una decisión de negocio explícita antes de comenzar.
