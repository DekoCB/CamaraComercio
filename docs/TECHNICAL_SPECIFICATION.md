# Especificación técnica — Sistema de Facturación y Cobranzas

Documento consolidado de arquitectura para producción. No introduce ningún cambio de stack o de diseño — **la arquitectura existente no se justifica de nuevo aquí más que lo necesario**; para el razonamiento completo de cada decisión (por qué Laravel puro sin capas Domain/Application, por qué RBAC propio en vez de un paquete, lecciones de portabilidad SQLite/MySQL) ver `docs/ARCHITECTURE.md`, que sigue siendo la referencia autoritativa. Este documento es el punto de entrada único que agrupa todo lo que un ingeniero o auditor de producción necesitaría revisar, con enlaces a donde ya está documentado en detalle.

## 1. Arquitectura general

Aplicación monolítica Laravel 12 (PHP 8.2), server-rendered con Blade — sin SPA, sin API separada, sin microservicios. Un único proceso PHP-FPM/Apache sirve tanto las vistas como la lógica de negocio; MySQL/MariaDB es el único almacén de datos persistente (sin caché externa tipo Redis, sin cola de mensajes externa — `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, ambos usan la misma base de datos relacional en vez de infraestructura adicional).

Justificación de por qué esto es apropiado para el tamaño de este dominio (un sistema interno de una sola Cámara de Comercio, no una plataforma multi-tenant de alto tráfico): ver `docs/ARCHITECTURE.md` sección "Por qué no hay una capa de Domain/Application separada".

Diagrama de ciclo de vida de una request: ver `docs/ARCHITECTURE.md` sección "Ciclo de vida de una request" — no se repite aquí.

## 2. Componentes

| Componente | Ubicación | Responsabilidad |
|---|---|---|
| Controllers | `app/Http/Controllers/**` | Orquestan Form Request → Service/Model → vista o redirect. Sin lógica de negocio propia más allá de ensamblar la respuesta |
| Form Requests | `app/Http/Requests/**` | Validación + autorización por endpoint (`authorize()` + `rules()`) |
| Services | `app/Services/*` | Lógica de negocio no trivial: `InvoiceGenerationService`, `PaymentService`, `PortfolioService`, `ReportService`, `ExportService`, `AssociateImportService`, `DashboardService` |
| Models | `app/Models/*` | Eloquent — relaciones, casts, scopes, invariantes de estado derivadas (`Invoice::effectiveStatus()`) |
| Vistas | `resources/views/**` | Blade — layouts (`app`, `guest`), componentes reutilizables (`resources/views/components/`), partials `_form.blade.php` para el patrón de modal-por-AJAX |
| Frontend estático | `public/assets/{css,js,icons,fonts,vendor}` | CSS/JS propio (design system + componentes JS: selects/date pickers/modal de formulario), Bootstrap 5 y Chart.js vendorizados, íconos Lucide como SVG individuales, fuente Inter autoalojada — **cero CDN en todo el proyecto** |
| Autorización | `app/Providers/AppServiceProvider.php` (`Gate::before`) | Ver sección 4 |
| Auditoría | `app/Models/AuditLog.php` | `AuditLog::record()`, llamado explícitamente desde cada controller de escritura |

## 3. Frontend

- **Sin build pipeline / sin bundler en producción:** todo el CSS/JS se sirve directamente desde `public/assets/`, ya minificado donde corresponde (Bootstrap, Chart.js vendorizados) o escrito a mano (`app.css`, `app.js` propios). No hay Webpack/Vite corriendo en producción — `package.json` existe solo por el scaffold de Laravel, no se usa para el flujo real de assets.
- **Diseño:** design system "Corporate Modern / Financial SaaS" completo — tokens CSS, tema oscuro/claro con persistencia, componentes propios (KPI cards, badges de estado, modales de formulario). Documentado exhaustivamente en `docs/DESIGN_SYSTEM.md` (15 secciones, incluida cada corrección de bug de UI encontrada durante la verificación con navegador real).
- **JS vanilla, sin framework:** ni React ni Vue ni Alpine — un único `app.js` con funciones puras por responsabilidad (sidebar, tema, popups de select/fecha porteados a `<body>`, modal de formulario genérico vía `fetch`). Justificado por el tamaño del proyecto; no hay estado de UI complejo que requiera un framework reactivo.
- **Accesibilidad:** contraste verificado ≥ 4.5:1, foco visible en todo elemento interactivo, navegación por teclado completa en los componentes personalizados (select/date picker), `aria-label` en botones de solo ícono — ver `docs/DESIGN_SYSTEM.md` sección 8.

## 4. Backend

- **Framework:** Laravel 12, PHP 8.2 (no 8.3+ porque el entorno XAMPP de desarrollo no lo tiene disponible — ver `docs/PROJECT_ANALYSIS.md` sección 2).
- **Patrón de capas:** Controller → Form Request (validación) → Service (si la lógica lo amerita) o Model directamente (si es CRUD simple) → vista/redirect. Ver sección 1.
- **Sin colas asíncronas activas:** aunque `QUEUE_CONNECTION=database` está configurado (infraestructura de Laravel lista), ninguna operación del sistema hoy se despacha a una cola — la generación masiva de facturas, la importación de Excel y el registro de pagos son todos síncronos dentro del ciclo de request/response. Es aceptable para los volúmenes actuales (decenas de asociados, no miles); si el volumen crece significativamente, la generación masiva sería la primera candidata a mover a un `Job` en cola.

## 5. Base de datos

Ver `docs/DATA_SCHEMA.md` para el esquema completo columna por columna, y `docs/DATA_MODEL.md` para el razonamiento de cada decisión de modelado (por qué `paid_total` está denormalizado, por qué `VENCIDA` nunca se persiste, por qué no hay borrado físico en ningún lado).

Resumen de motor: MySQL/MariaDB en desarrollo y (asumido, pendiente de confirmar) en producción; SQLite en memoria exclusivamente para la suite de tests (`RefreshDatabase`), nunca toca la base de datos de desarrollo real. Esto obligó a evitar funciones específicas de MySQL (`CURDATE()`) y ciertas combinaciones de `HAVING` no soportadas igual en ambos motores — ver `docs/ARCHITECTURE.md` para el detalle de esas dos lecciones concretas.

## 6. Autenticación

- Guard `web` nativo de Laravel, sesiones en base de datos (`SESSION_DRIVER=database`, `SESSION_LIFETIME=120` minutos).
- Contraseñas: bcrypt vía el cast `'password' => 'hashed'` de Eloquent, `BCRYPT_ROUNDS=12`.
- Recuperación de contraseña: broker nativo (`Password::sendResetLink`/`Password::reset`), token de un solo uso, expira en 60 minutos, respuesta idéntica exista o no el correo (anti-enumeración).
- Rate limiting: `throttle:5,1` (5 intentos por minuto) en login y en ambos endpoints de recuperación de contraseña — únicos endpoints con este control, ver `docs/REQUIREMENTS_GAP_ANALYSIS.md` SEC-10 para el razonamiento de por qué no se extendió a otros endpoints.
- "Recordarme" en login usa el mecanismo nativo de `remember_token`.
- Autoservicio de perfil (foto/nombre/correo/contraseña) para cualquier usuario autenticado, sin permiso de rol adicional — ver `docs/PROJECT_ANALYSIS.md` sección 10.19.

## 7. Autorización

RBAC propio (no un paquete de terceros) sobre 3 tablas: `roles`, `permissions`, `modules`, con pivotes `role_permissions`/`role_modules`. Cada request protegida se verifica **en el servidor**, nunca solo ocultando UI — ver `docs/ARCHITECTURE.md` sección "Autorización (RBAC)" para el mecanismo completo (`Gate::before()`, middleware `can:codigo`, directiva Blade `@module`).

## 8. Integraciones externas

**Ninguna existe hoy.** El sistema no se integra con ningún servicio de terceros: no hay pasarela de pago, no hay comprobante electrónico (SUNAT), no hay integración contable, no hay SSO, no hay servicio de correo transaccional configurado (`MAIL_MAILER=log` — los correos se escriben en `storage/logs/laravel.log`, no se envían realmente). Cualquiera de estas sería un cambio de alcance mayor, fuera de las 23 HU originales (ver `docs/PROJECT_ANALYSIS.md` sección 6, "fuera de alcance del MVP").

## 9. Importación / Exportación

- **Importación:** solo asociados, desde Excel — ver `docs/EXCEL_MIGRATION_SPECIFICATION.md` para el detalle completo del flujo, formato y validaciones.
- **Exportación:** reportes de cobranza y deuda pendiente, a Excel (`phpoffice/phpspreadsheet`) y PDF (`dompdf/dompdf`), ambos vendorizados vía Composer (sin CDN). Requiere el permiso `reports.export`, distinto de `reports.view`.

## 10. Seguridad

Ver `docs/REQUIREMENTS_GAP_ANALYSIS.md` sección 6 para el detalle punto por punto (hash de contraseñas, CSRF, XSS, SQLi, rate limiting, exposición de secretos) y `docs/DATA_PROTECTION.md` para qué datos personales se manejan y quién puede verlos.

## 11. Dashboard y "tiempo real"

La especificación funcional pide una "foto del momento" en el dashboard (HU-16) y reportes actualizados. Analizadas las tres interpretaciones posibles:

- **A. Actualización inmediata tras cada operación** — cada consulta al dashboard/reportes lee el estado actual de la base de datos en el momento de la request, sin caché intermedio.
- **B. Polling** — el navegador re-consulta el servidor cada N segundos.
- **C. WebSockets/SSE** — el servidor empuja actualizaciones a todos los clientes conectados cuando algo cambia.

**Decisión: Opción A**, ya implementada desde el diseño original de `DashboardService`/`ReportService`/`PortfolioService` — ninguno de los tres cachea resultados; cada visita a esas pantallas ejecuta las consultas agregadas en ese instante. No existe ningún requerimiento de multiusuario simultáneo viendo el mismo dashboard actualizarse en vivo sin recargar (eso es lo único que B o C resolverían que A no resuelve) — este es un sistema interno de una Cámara de Comercio con un puñado de usuarios administrativos, no un tablero de trading ni un chat.

No se implementa WebSockets/SSE ni polling solo porque la palabra "tiempo real" aparece en la especificación — sería sobre-ingeniería para un requerimiento que "foto del momento al momento de mirar la pantalla" ya satisface completamente. Si en el futuro la Cámara necesita que dos personas viendo el dashboard simultáneamente vean un cambio sin recargar la página, es un cambio de alcance a evaluar entonces, no algo que se anticipe hoy sin ese requerimiento explícito.

## 12. Deployment

Ver `docs/DEPLOYMENT.md` para el documento completo. Resumen: hoy el único entorno real es desarrollo local sobre XAMPP (Apache + MySQL, puerto dedicado). No existe todavía un entorno de producción definido, ni pipeline de CI/CD, ni contenedores — el health check nativo de Laravel (`/up`) ya está disponible y listo para usarse en cuanto exista un entorno donde monitorearlo.
