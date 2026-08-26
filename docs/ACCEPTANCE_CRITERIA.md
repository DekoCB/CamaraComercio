# Criterios de aceptación detallados — HU-01 a HU-23

Las 23 historias de usuario **no se modifican** — este documento agrega criterios de aceptación concretos (formato Given/When/Then) que las HU originales, redactadas a nivel de épica, no detallaban lo suficiente como para servir de contrato técnico. Cada criterio corresponde a comportamiento ya implementado y verificado (los archivos de test citados son la evidencia, no una promesa).

---

### HU-01 — Iniciar sesión

**Given** un usuario con credenciales válidas y cuenta activa
**When** envía el formulario de login
**Then** se autentica y es redirigido al dashboard

**Given** una contraseña incorrecta o un correo que no existe
**When** intenta iniciar sesión
**Then** permanece en `/login` con un mensaje de error genérico — idéntico en ambos casos, para no revelar si el correo existe

**Given** un usuario marcado como inactivo
**When** intenta iniciar sesión con su contraseña correcta
**Then** el acceso se rechaza igual que con una contraseña incorrecta

**Given** 5 intentos fallidos de login en el último minuto
**When** se intenta un sexto, incluso con la contraseña correcta
**Then** la respuesta es 429 (límite alcanzado), sin autenticar

*Evidencia: `tests/Feature/Auth/LoginTest.php`*

---

### HU-02 — Cerrar sesión

**Given** una sesión iniciada
**When** el usuario cierra sesión
**Then** la sesión se invalida y el token CSRF se regenera; cualquier ruta privada vuelve a exigir login

*Evidencia: `tests/Feature/Auth/LoginTest.php`*

---

### HU-03 — Recuperar contraseña

**Given** un correo registrado
**When** se solicita recuperar la contraseña
**Then** se genera un enlace de un solo uso, válido 60 minutos

**Given** un token ya usado
**When** se intenta reutilizarlo
**Then** se rechaza y redirige a solicitar uno nuevo

**Given** un correo que no existe en el sistema
**When** se solicita recuperación
**Then** la respuesta es idéntica a la de un correo válido (no revela si existe)

*Evidencia: `tests/Feature/Auth/PasswordResetTest.php`. Sin transporte de correo real configurado — el enlace se escribe en `storage/logs/laravel.log` (`MAIL_MAILER=log`), pendiente de decisión de negocio sobre el proveedor SMTP*

---

### HU-04 — Registrar un asociado

**Given** un usuario con permiso `associates.manage`
**When** completa el formulario con al menos el nombre
**Then** el asociado se crea, activo por defecto, visible de inmediato en el listado

**Given** un nombre en blanco
**When** se intenta guardar
**Then** se rechaza sin persistir nada, con el mensaje "El nombre del asociado es obligatorio."

**Given** un correo con formato inválido
**When** se intenta guardar
**Then** se rechaza sin persistir nada

**Given** un correo que ya pertenece a otro asociado
**When** se intenta guardar (alta manual o importación por Excel)
**Then** se rechaza en ambos flujos por igual — corregido en la auditoría de 2026-08-19, antes solo el importador lo validaba

*Evidencia: `tests/Feature/AssociateTest.php`*

---

### HU-05 — Actualizar datos de un asociado

**Given** un asociado existente con facturas y pagos históricos
**When** se edita su nombre/empresa/contacto/correo o se cambia su estado activo/inactivo
**Then** los datos se actualizan sin afectar ninguna factura o pago ya registrado

*Evidencia: `tests/Feature/AssociateTest.php`*

---

### HU-06 — Generar las facturas del mes

**Given** N asociados activos sin factura para el período elegido
**When** se confirma la generación masiva (período, monto, fecha límite)
**Then** se crean N facturas, una por asociado, todas en estado PENDIENTE

**Given** una segunda corrida para el mismo período, con las mismas facturas ya creadas
**When** se ejecuta de nuevo
**Then** no se duplica ninguna factura; se reportan como omitidas, sin modificar el monto original

**Given** un asociado inactivo
**When** se genera facturación masiva
**Then** ese asociado no recibe ninguna factura

**Given** un asociado nuevo, dado de alta después de la primera corrida del período
**When** se vuelve a correr la generación para ese mismo período
**Then** sí recibe factura en esa segunda corrida (no hay prorrateo por fecha de alta — ver `docs/OPEN_BUSINESS_DECISIONS.md`)

*Evidencia: `tests/Feature/InvoiceGenerationTest.php`*

---

### HU-07 — Consultar las facturas de un asociado

**Given** facturas de varios asociados, períodos y estados
**When** se filtra el listado por asociado, período o estado
**Then** solo se muestran las facturas que coinciden

**Given** una factura específica
**When** se abre su detalle
**Then** se muestra monto, pagado, saldo, y el historial completo de pagos asociados

*Evidencia: `tests/Feature/InvoiceGenerationTest.php`, `tests/Feature/PaymentTest.php`*

---

### HU-08 — Registrar un pago

**Given** una factura de S/ 500 sin pagos
**When** se registra un pago de S/ 500
**Then** la factura queda en PAGADA, saldo S/ 0.00

*Evidencia: `tests/Feature/PaymentTest.php`*

---

### HU-09 — Registrar un pago parcial

**Given** una factura de S/ 500 sin pagos registrados
**When** el encargado de cobranzas registra un pago de S/ 200
**Then:**
```
Monto:  S/ 500.00
Pagado: S/ 200.00
Saldo:  S/ 300.00
Estado: PARCIAL
```

**Given** esa misma factura ya en PARCIAL con saldo S/ 300
**When** se registra un segundo pago de S/ 300
**Then** la factura pasa a PAGADA, saldo S/ 0.00

**Given** un saldo pendiente de S/ 300
**When** se intenta registrar un pago de S/ 400
**Then** se rechaza, `paid_total` no cambia

**Given** un intento de pago de S/ 0 o negativo
**When** se envía
**Then** se rechaza

*Evidencia: `tests/Feature/PaymentTest.php::test_partial_payment_marks_the_invoice_as_partial_and_computes_balance` (el ejemplo de aceptación original de `docs/BACKLOG.md`, codificado literalmente como test)*

---

### HU-10 — Saber quién debe

**Given** asociados con distintos totales de facturación/pago
**When** se abre la vista de cartera general
**Then** cada asociado muestra su total facturado, pagado, pendiente, y conteo de facturas pendientes/vencidas, todos calculados con la misma fórmula (`amount - paid_total`) que el resto del sistema

*Evidencia: `tests/Feature/PortfolioTest.php`*

---

### HU-11 — Saber a quién falta cobrar

**Given** un asociado sin ninguna factura y otro con todas sus facturas en PAGADA
**When** se abre "a quién falta cobrar"
**Then** ninguno de los dos aparece en la lista

**Given** un asociado con varias facturas impagas de distintos períodos
**When** aparece en la lista
**Then** se muestra el período de su deuda más antigua, calculado correctamente

*Evidencia: `tests/Feature/PortfolioTest.php`*

---

### HU-12 — Ver el estado de un asociado

**Given** un asociado con historial de facturas y pagos
**When** se abre su estado de cuenta
**Then** se muestran sus datos de contacto, los tres totales (facturado/pagado/pendiente), y el historial completo de facturas con su estado

*Evidencia: `tests/Feature/PortfolioTest.php`*

---

### HU-13 — Ver lo cobrado en el mes

**Given** una factura de agosto pagada con un pago hecho en septiembre
**When** se genera el reporte de cobranza de agosto
**Then** ese pago **no** cuenta como "cobrado en agosto" — se basa en la fecha del pago (`paid_at`), no en el período de la factura

**Given** varios pagos del mismo asociado en el período
**When** se cuenta "asociados que pagaron"
**Then** se cuenta una vez por asociado distinto, no una vez por pago

*Evidencia: `tests/Feature/ReportTest.php`*

---

### HU-14 — Ver la deuda pendiente

**Given** facturas en distintos estados, algunas vencidas
**When** se abre el reporte de deuda pendiente
**Then** se muestra el total adeudado (hoy) y su distribución por estado (PENDIENTE/PARCIAL/VENCIDA), calculada con "hoy" como parámetro (no `CURDATE()`, por portabilidad SQLite/MySQL en tests)

*Evidencia: `tests/Feature/ReportTest.php`*

---

### HU-15 — Exportar la información (Excel/PDF)

**Given** un reporte generado en pantalla
**When** se exporta a Excel
**Then** el archivo contiene título, fecha de generación, período y los mismos totales vistos en pantalla — releído con PhpSpreadsheet para confirmarlo

**Given** el mismo reporte
**When** se exporta a PDF
**Then** el archivo tiene cabecera `%PDF-1.7` válida

**Given** un rol con `reports.view` pero sin `reports.export`
**When** intenta exportar
**Then** recibe 403, aunque sí pueda ver el reporte en pantalla

*Evidencia: `tests/Feature/ReportTest.php`*

---

### HU-16 — Pantalla principal clara

**Given** un usuario autenticado
**When** entra al dashboard
**Then** ve KPIs reales (asociados, facturado del período, cobrado del mes, deuda pendiente, facturas vencidas) y accesos rápidos según sus permisos — nunca datos de otro rol al que no tiene acceso

*Evidencia: `app/Services/DashboardService.php`, verificado visualmente con Playwright durante el rediseño UI/UX*

---

### HU-17 — Información ordenada en pantalla

**Given** cualquiera de las 9 áreas del sistema (asociados, facturación, pagos, cartera, deudores, estado de cuenta, reportes, usuarios, roles/módulos)
**When** se navega a esa pantalla
**Then** la información se presenta en tablas y tarjetas KPI con el mismo sistema de diseño, badges de estado consistentes en todo el sistema

*Evidencia: `docs/DESIGN_SYSTEM.md`*

---

### HU-18 — Buscar y filtrar desde la pantalla

**Given** un listado de asociados, facturas, pagos, cartera o "a quién falta cobrar"
**When** se ingresa un término de búsqueda o se aplica un filtro
**Then** solo se muestran los resultados que coinciden, sin recargar toda la aplicación

*Evidencia: `tests/Feature/AssociateTest.php::test_search_filters_by_name_company_phone_or_email`, `tests/Feature/PortfolioTest.php`*

---

### HU-19 — Uso desde distintos dispositivos (responsivo)

**Given** un viewport de 375px de ancho (móvil)
**When** se navega por cualquier pantalla del sistema
**Then** el `<body>` nunca excede el ancho del viewport; una tabla ancha se desplaza dentro de su propio contenedor, nunca arrastrando toda la página

**Given** el sidebar colapsado
**When** se navega entre módulos
**Then** permanece colapsado desde el primer frame, sin parpadeo (corregido en la sesión de 2026-08-19)

*Evidencia: barridos con Playwright en 1440px y 375px, documentados en `docs/DESIGN_SYSTEM.md` y `docs/PROJECT_ANALYSIS.md`*

---

### HU-20 — Mensajes de confirmación

**Given** una acción de escritura exitosa o fallida
**When** se completa
**Then** aparece un toast (nunca un `alert()` nativo del navegador) con el resultado

**Given** una acción irreversible o cuyo efecto alcanza a otros usuarios (generar facturación masiva, confirmar una importación, desactivar un módulo)
**When** se intenta ejecutar
**Then** se exige una confirmación explícita en un modal propio (nunca `window.confirm()`) antes de proceder

*Evidencia: `docs/DESIGN_SYSTEM.md` secciones 10.2, 14*

---

### HU-21 — Crear los módulos del sistema

**Given** un código de módulo ya existente
**When** se intenta crear otro módulo con el mismo código
**Then** se rechaza

*Evidencia: `tests/Feature/Admin/AdminManagementTest.php`*

---

### HU-22 — Activar o desactivar módulos

**Given** un módulo activo, asignado a un rol
**When** se desactiva
**Then** desaparece del menú lateral para **todos** los roles que lo tengan asignado, incluso los que no participaron en la desactivación

*Evidencia: `tests/Feature/Admin/AdminManagementTest.php`*

---

### HU-23 — Gestionar usuarios y permisos

**Given** un rol sin el permiso `admin.users`
**When** ese usuario pide `/admin/users` directamente por URL (no por el menú)
**Then** recibe 403 real — la autorización se aplica en el servidor, no solo ocultando el enlace

**Given** un usuario desactivado
**When** intenta usar cualquier permiso que su rol le otorgaría
**Then** no puede — `is_active = false` bloquea el acceso independientemente de los permisos del rol

*Evidencia: `tests/Feature/Admin/RbacTest.php`*

---

## Historias adicionales (fuera de la numeración original, ya implementadas)

Estas no tienen HU-ID porque se agregaron después de la especificación funcional original, pero tienen el mismo nivel de criterios de aceptación por consistencia:

**Importación desde Excel** — ver `docs/EXCEL_MIGRATION_SPECIFICATION.md` para los criterios completos.

**Configuración de perfil propio** — dado un usuario autenticado, cuando edita su nombre/correo sin tocar los campos de contraseña, entonces se guarda sin exigir la contraseña actual; cuando sí completa "nueva contraseña", entonces exige la contraseña actual correcta y el "nombre del asociado obligatorio" ya no aplica (aplica a usuarios, no asociados) — ver `tests/Feature/ProfileTest.php`.
