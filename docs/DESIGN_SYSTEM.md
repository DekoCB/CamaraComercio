# Design System — Sistema de Facturación y Cobranzas

Dirección visual: **Corporate Modern / Financial SaaS**. Institucional, sobrio, orientado a que un encargado de cobranzas responda rápido "¿cuánto falta cobrar?" sin ruido visual. Ver el brief de diseño completo (prompt maestro de UI/UX) para el razonamiento de negocio; este documento es la referencia técnica de implementación.

## 0. Inspección previa (2026-08-18)

- **Stack:** Laravel 12, Blade (server-rendered, sin SPA), Bootstrap 5 vendorizado localmente (sin CDN) como base de utilidades de layout (grid/flex), sobrescrito casi por completo a nivel visual por `public/assets/css/app.css`. JavaScript vanilla, sin framework ni bundler activo (el `package.json`/Vite del scaffold de Laravel existe pero no se usa — los assets se vendorizan igual que Bootstrap, ver decisión D2 en `PROJECT_ANALYSIS.md`).
- **Iconografía previa:** Bootstrap Icons (fuente + CSS). Se reemplaza por **Lucide** (SVG inline, vendorizados individualmente en `public/assets/icons/`, sin CDN ni icon-font) para tener control total del color vía `currentColor` y evitar FOUC de fuentes de íconos.
- **Gráficos:** no existían. Se agrega **Chart.js** vendorizado (`public/assets/vendor/chartjs/chart.umd.min.js`) solo para el dashboard (cobranza mensual, distribución de cartera) — únicos dos gráficos que aportan información real, por decisión explícita del brief de no llenar el dashboard de charts.
- **Logo:** no existe un logo oficial en el repositorio. Se usa un tratamiento tipográfico + una marca geométrica simple (ver sección 3) como placeholder. **Debe reemplazarse por el branding oficial de la Cámara de Comercio cuando esté disponible.**
- **Layout existente:** sidebar fijo + topbar + contenido, ya presente desde Sprint 1 (`resources/views/layouts/app.blade.php`). Se conserva la estructura, se rediseña visualmente y se le agrega colapso en desktop.
- **No se reemplaza el stack.** Bootstrap sigue resolviendo grid/flex/utilidades; el sistema de diseño vive en tokens CSS + componentes Blade nuevos, no en un framework de UI nuevo.

## 1. Design tokens

Todos los tokens viven como custom properties CSS en `:root` (`public/assets/css/tokens.css`), cargado antes que `app.css`. Nunca se escriben valores de color/espaciado arbitrarios directamente en un componente — siempre `var(--token)`.

### Color — modo claro (por defecto)

| Token | Valor | Uso |
|---|---|---|
| `--color-navy` | `#0F2747` | Sidebar, headers importantes, botón primario, branding |
| `--color-navy-hover` | `#16375F` | Hover sobre elementos navy |
| `--color-blue` | `#2563EB` | Links, acciones secundarias, focus, estados activos |
| `--color-blue-subtle` | `#EFF6FF` | Fondo de estados activos/hover suaves |
| `--color-teal` | `#14B8A6` | Acento — tendencias positivas, métricas destacadas. Uso moderado, nunca dominante |
| `--color-success` | `#16A34A` | Estado PAGADA, confirmaciones |
| `--color-success-subtle` | `#F0FDF4` | Fondo de badge/alerta success |
| `--color-warning` | `#F59E0B` | Estado PARCIAL, advertencias |
| `--color-warning-subtle` | `#FFFBEB` | Fondo de badge/alerta warning |
| `--color-danger` | `#DC2626` | Estado VENCIDA, errores |
| `--color-danger-subtle` | `#FEF2F2` | Fondo de badge/alerta danger |
| `--color-info` | `#0284C7` | Estado PENDIENTE, mensajes informativos |
| `--color-info-subtle` | `#F0F9FF` | Fondo de badge/alerta info |
| `--color-bg` | `#F8FAFC` | Fondo de la aplicación |
| `--color-surface` | `#FFFFFF` | Cards, tablas, modales |
| `--color-border` | `#E2E8F0` | Bordes de superficies |
| `--color-text` | `#0F172A` | Texto principal |
| `--color-text-secondary` | `#64748B` | Texto secundario, labels |
| `--color-text-tertiary` | `#94A3B8` | Texto terciario, placeholders |

**Regla de color:** el 90% de la interfaz es blanco / gris muy claro / navy / azul. Verde, ámbar y rojo se reservan para comunicar *estado* (badges de factura, alertas), nunca como decoración. Ningún color se usa "porque sí".

### Color — modo oscuro (preparado, no activado)

Definido bajo `@media (prefers-color-scheme: dark)` más una clase `.theme-dark` opcional para activación manual futura. **No es una inversión automática** — la jerarquía se redefine a propósito (superficies elevadas más claras que el fondo, no al revés):

| Token | Valor |
|---|---|
| `--color-bg` | `#0B1120` |
| `--color-surface` | `#111827` |
| `--color-surface-elevated` | `#1E293B` |
| `--color-border` | `#334155` |
| `--color-text` | `#F8FAFC` |
| `--color-text-secondary` | `#94A3B8` |

La primera versión del sistema usa **Light Mode por defecto**; el dark mode queda preparado en tokens pero sin selector visible en la UI todavía (se activa fácilmente en el futuro agregando el toggle, sin tocar componentes).

### Tipografía

- **Familia:** Inter, self-hosted en `public/assets/fonts/inter/` (pesos 400/500/600/700, woff2 + woff — no CDN de Google Fonts). Fallback: `system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif`.
- **Escala:** H1 32px/700, H2 24px/700, H3 18px/600, Body 14–16px/400, Labels 13px/500, Datos/montos 600–700 (los montos siempre en semibold o bold, nunca en peso regular, para que destaquen en tablas).

### Espaciado

Escala fija: `4 8 12 16 20 24 32 40 48 64` px → tokens `--space-1` … `--space-16` (múltiplos de 4). No se usan valores sueltos fuera de esta escala salvo con justificación puntual documentada en el propio CSS.

### Radios

`--radius-sm: 8px` (inputs, botones) · `--radius-md: 12px` (cards) · `--radius-lg: 16px` (modales) · `--radius-full: 999px` (badges, avatares).

### Sombras

Solo dos niveles, ambos sutiles (nada de neumorphism ni sombras dramáticas):

```
--shadow-sm: 0 1px 2px rgba(15, 23, 42, 0.06), 0 1px 1px rgba(15, 23, 42, 0.04);   /* cards, tablas */
--shadow-md: 0 12px 24px -8px rgba(15, 23, 42, 0.18), 0 4px 8px rgba(15, 23, 42, 0.08); /* modales, dropdowns */
```

### Transiciones

`--transition-fast: 150ms ease-out` (hover, focus) · `--transition-base: 200ms ease-out` (sidebar, dropdowns, toasts) · `--transition-modal: 220ms ease-out` (modales). Nunca 500ms+ en elementos administrativos. Se anima `transform`/`opacity` casi exclusivamente (performance); nunca `width`/`height`/`top`/`left` cuando `transform` alcanza. Se respeta `prefers-reduced-motion: reduce` desactivando duraciones no funcionales.

## 2. Iconografía

Una sola familia: **Lucide**, SVG inline vía el helper `icon('nombre', 'clase', tamaño)` (`app/helpers.php`). Trazo lineal 2px, `stroke="currentColor"` (hereda el color del texto circundante — no hace falta un color por ícono). Prohibido mezclar con Bootstrap Icons, Font Awesome o emojis en la interfaz de producto (los emojis de este mismo documento son solo para el lector humano).

## 3. Marca

Sin logo oficial disponible. Tratamiento temporal: una marca geométrica simple — cuadrado redondeado (`--radius-sm`) en navy con el ícono `building-2` en blanco — junto al wordmark "Cámara de Comercio" (Inter Bold) + subtítulo "Gestión de Facturación" (Inter Regular, texto secundario). Vive en `resources/views/components/brand-mark.blade.php`, reutilizada en el sidebar y en el login. **Reemplazar por el logo oficial en cuanto esté disponible** — un solo componente, un solo lugar que tocar.

## 4. Layout

`Sidebar (260px, colapsable a 72px) + Topbar (64px) + Main content`. Colapso solo en desktop (≥992px); en mobile el sidebar es un drawer superpuesto (ya existía desde Sprint 1, se conserva ese comportamiento). Transición de colapso: `width` + `transform` combinados a 220ms — es de los pocos casos donde animar `width` es inevitable (cambia el layout, no solo la apariencia), mitigado con `will-change` solo durante la transición.

## 5. Componentes (Blade, en `resources/views/components/`)

| Componente | Archivo | Notas |
|---|---|---|
| Botón | `button.blade.php` | Variantes primary/secondary/ghost/danger × estados default/hover/active/focus/disabled/loading |
| Badge de estado | `status-badge.blade.php` | Reemplaza el badge ad-hoc anterior; una sola fuente de verdad para PENDIENTE/PARCIAL/PAGADA/VENCIDA |
| KPI Card | `kpi-card.blade.php` | Ícono + valor + etiqueta + tendencia opcional |
| Card genérica | ya existía como clase `.card-surface` | |
| Modal de confirmación | `confirm-modal.blade.php` + `confirm.js` | Reemplaza `window.confirm()` nativo — el brief lo prohíbe explícitamente (sección 32/40) |
| Toast | `toast-container.blade.php` + `toast.js` | Reemplaza los `<div class="alert">` estáticos anteriores por notificaciones auto-descartables |
| Ícono | función `icon()` | No es un componente Blade por rendimiento (se usa docenas de veces por página) |
| Select personalizado | `app.js`: `enhanceSelects()` / `app.css`: `.select-field` | Progresivamente mejora cada `<select class="form-select">` (2026-08-18, ver sección 10) |
| Selector de fecha/mes | `app.js`: `enhanceDateInputs()` / `app.css`: `.datepicker-field` | Progresivamente mejora `input[type="date"]` y `input[type="month"]` (2026-08-18, ver sección 10) |
| Modal de formulario | `app.js`: `openFormModal()` / `app.css`: `.modal-panel-form` | Overlay para crear/editar registros sin navegar a otra pantalla (2026-08-18, ver sección 10) |

No se duplican componentes: por ejemplo, la tarjeta de "asociado" (sección 24 del brief) reutiliza la vista de estado de cuenta ya construida en Sprint 3 (HU-12) en vez de crear una página nueva paralela.

## 6. Estados de UI

Cada listado con datos server-side implementa: **empty state** (ícono + mensaje + CTA), **estados de formulario** (label + input + helper + error), y feedback vía **toast** tras cada acción de escritura. Dado que la aplicación es server-rendered (sin fetch de datos por AJAX salvo la exportación), no hay "loading skeletons" de datos en el sentido SPA — el equivalente funcional implementado es: estado de carga en el propio botón de submit (spinner + texto "Guardando…") para que ninguna acción se sienta congelada, y una barra de progreso indeterminada durante la generación masiva de facturas.

## 7. Responsive

Mobile-first en los componentes base, pero **diseñado primero para desktop** a nivel de layout (es un sistema administrativo). Breakpoints de Bootstrap conservados (`sm 576 · md 768 · lg 992 · xl 1200`). En mobile: KPIs a 1 columna, tablas con scroll horizontal contenido en su propio contenedor (nunca la página completa — verificado con Playwright, ver `docs/BACKLOG.md` Sprint 4), filtros que se apilan, sidebar como drawer.

## 8. Accesibilidad

Contraste verificado ≥ 4.5:1 en texto sobre superficies (navy #0F2747 sobre blanco = 14.9:1; texto secundario #64748B sobre blanco = 5.4:1). Anillo de foco visible (`--color-blue` con offset) en todo elemento interactivo — nunca `outline: none` sin reemplazo. Botones de solo-ícono llevan `aria-label`. Los estados (badges) nunca dependen solo del color: siempre llevan texto. Modales atrapan el foco y cierran con `Esc`. Navegación completa por teclado.

## 9. Qué no cambia

Las reglas de negocio, permisos (`Gate::before`), rutas, controladores y Form Requests **no se tocan** en este trabajo — es una capa visual sobre la funcionalidad existente. Cualquier ajuste de copy (textos de botones, mensajes) se hace *dentro* de las vistas Blade ya existentes, no reescribiendo lógica de servidor.

## 10. Adenda — 2026-08-18: calendarios/listas personalizados y formularios en modal

A pedido explícito del usuario: (1) unificar los calendarios y listas desplegables nativos del navegador con la identidad visual del sistema, y (2) hacer que los formularios pequeños de crear/editar se sobrepongan como modal en vez de navegar a una pantalla dedicada.

### 10.1 Por qué progressive enhancement en vez de un plugin de terceros

El popup nativo de `<input type="date">` y de `<select>` lo dibuja el sistema operativo/navegador — ningún CSS puede tocarlo. La única forma de que coincidan con la paleta navy/azul/teal es reemplazarlos por un widget propio. Se optó por JS vanilla (sin vendorizar una librería de fecha/select) siguiendo el mismo criterio ya aplicado a iconos y gráficos: cero CDN, dependencias mínimas. `enhanceSelects()`/`enhanceDateInputs()` (`public/assets/js/app.js`) envuelven cada `<select>`/`input[type="date"|"month"]` en un trigger + popup estilizados; **el elemento nativo original permanece en el DOM** (oculto, `tabindex="-1"`) y es el que de verdad viaja en el `<form>` — el backend nunca se entera de que la UI cambió: cero cambios en validación, cero cambios en cómo cada página ya leía `request()->input(...)`.

- Selector de fecha: grid de días con semana en Lu-Do, hoy resaltado con anillo azul, seleccionado en navy. `input[type="month"]` usa un grid de 12 meses con navegación por año en su lugar (la generación de facturación y los reportes).
- Selector genérico: listbox con navegación por teclado completa (flechas, Home/End, Escape, *typeahead* por letra) y checkmark en la opción activa — cumple operabilidad por teclado (WCAG 2.1.1) igual que el `<select>` nativo que reemplaza.
- Ambos popups se alinean dinámicamente (`opens-up`/`opens-left`) para no salirse del viewport — el mismo problema, en otra forma, que el bug de `pointer-events` del modal de confirmación (sección "QA visual" de `PROJECT_ANALYSIS.md` §10.10): un elemento `position: absolute` sigue contando para el ancho/alto scrolleable de la página aunque esté visualmente oculto (`opacity: 0`), así que el cálculo de alineación corre tanto al construir el widget como al abrirlo, no solo al abrirlo.

### 10.2 Formularios en modal

Los CRUD pequeños (asociado, usuario, rol — solo nombre/descripción, módulo) se abren ahora con `openFormModal()` sobre la lista que los originó, en vez de navegar a `/asociados/create` como pantalla propia. La pantalla de permisos y módulos de un rol (`admin/roles/{role}/access`) **se dejó fuera a propósito**: es una matriz potencialmente larga de checkboxes, no encaja en "formularios que no sean tan grandes" (instrucción explícita del usuario).

Mecanismo (sin duplicar lógica de validación en JS):
1. Cada `create()`/`edit()` de controlador devuelve la vista completa (`*.form`) para navegación directa/sin JS, o solo el fragmento `<form>` (`*._form`) cuando la petición es AJAX (`$request->ajax()`) — un único partial Blade alimenta ambos caminos.
2. El submit se intercepta y se reenvía por `fetch()` con `Accept: application/json`. Eso hace que Laravel, ante un `ValidationException`, responda **422 + JSON** en vez de redirigir con errores en sesión — comportamiento nativo del framework, no requirió tocar ningún Form Request.
3. Un éxito real (redirect 302 que el propio controlador ya emitía) se sigue automáticamente; el JS navega el navegador a `response.url`, que es lo que el controlador decida — por eso crear un rol sigue aterrizando en la pantalla de permisos ("Ahora asigne sus permisos y módulos") sin ningún caso especial en el JS.
4. Bug encontrado y corregido durante la verificación: el flash de sesión de Laravel solo sobrevive **una** petición siguiente; como `fetch()` ya sigue el redirect internamente, ese flash se consumía antes de que el navegador navegara de verdad, y el toast de éxito nunca aparecía. Se relevó vía `sessionStorage` (`cc_pending_flash`): el JS extrae el flash de la respuesta ya obtenida y lo vuelve a mostrar en la carga de página siguiente.

### 10.3 Verificación

Suite completa (71/71) sin cambios; verificado con Playwright en 1440px y 375px en las 15 pantallas principales — cero errores de consola, cero overflow horizontal tras corregir el alineamiento de los popups cerca del borde derecho en filtros angostos (cartera de pagos en mobile).
