# Reglas de negocio — Sistema de Facturación y Cobranzas

Catálogo formal de las reglas de negocio del sistema, categorizadas según el criterio del prompt de auditoría de 2026-08-19:

- **A. Definida** — está explícitamente indicada por la documentación funcional original (23 HU) o por un requerimiento aprobado posterior.
- **B. Inferible** — no está escrita en ningún documento, pero se deduce razonablemente de cómo el resto del sistema ya funciona, sin riesgo de estar inventando algo crítico.
- **C. No definida** — `REQUIERE VALIDACIÓN DEL CLIENTE`. Remite a `docs/OPEN_BUSINESS_DECISIONS.md` para el detalle completo (opciones, recomendación, impacto).

Este documento es el punto único de referencia para "¿cuál es la regla?" — `docs/DATA_SCHEMA.md` documenta la estructura, `docs/REQUIREMENTS_GAP_ANALYSIS.md` documenta qué está implementado, este documento documenta *la regla en sí*.

---

## 1. Asociados

| Regla | Categoría | Detalle |
|---|---|---|
| Un asociado tiene nombre obligatorio; empresa, contacto y correo son opcionales | **A** | HU-04 |
| Un asociado puede activarse/desactivarse sin afectar sus facturas o pagos históricos | **A** | HU-05, explícito: "incluye activar/desactivar sin afectar facturas/pagos relacionados" |
| No hay borrado físico de asociados | **B** | Inferible del patrón consistente en todo el sistema (`restrictOnDelete` en toda referencia a `associates`/`users`) — nunca se declaró explícitamente pero es la única lectura coherente |
| Los asociados inactivos no reciben facturación masiva | **A** (comportamiento implementado y verificado) | `InvoiceGenerationService` filtra `is_active = true`; ver `docs/OPEN_BUSINESS_DECISIONS.md` pregunta 4 |
| Cuándo/quién inactiva a un asociado, y si es automático | **C** | Preguntas 1-3 |
| Un correo de asociado no puede repetirse (aplicado consistentemente en alta manual e importación por Excel) | **B** | Cerraba una inconsistencia real: el importador ya rechazaba correos duplicados, el alta manual no. Corregido en esta sesión (`AssociateRequest`) — no requiere validación porque es la misma regla ya existente en un flujo, aplicada al otro |
| El RUC es el identificador legal del asociado, opcional (no obligatorio) y único cuando está presente | **A** (resuelto e implementado 2026-08-28) | Pregunta 12 — `associates.ruc`, `UNIQUE`, nullable |

## 2. Facturación

| Regla | Categoría | Detalle |
|---|---|---|
| La facturación mensual es un proceso masivo: un período, un monto, una fecha de vencimiento, aplicado a todos los asociados activos elegibles | **A** | HU-06 |
| No se duplican facturas para el mismo asociado + período en corridas repetidas | **A** | HU-06, verificado con `UNIQUE(associate_id, period)` + pre-check en el servicio |
| Un asociado dado de alta después del inicio del período igual recibe factura completa la próxima vez que se corra la generación para ese período (sin prorrateo) | **B** | Comportamiento por defecto al no existir regla de prorrateo — ver pregunta 6 del gap analysis (FAC-06) |
| Una factura, una vez creada, no puede editarse, anularse ni eliminarse desde la aplicación | **B** (comportamiento actual) / **C** (si se desea cambiar) | Ver preguntas 5, 6, 7 |
| `VENCIDA` es un estado calculado en tiempo de lectura (`due_date < hoy`), nunca persistido | **A** | Decisión arquitectónica documentada desde Sprint 2 en `docs/DATA_MODEL.md`, motivada por evitar un job diario y valores obsoletos entre corridas |
| El vencimiento es exacto en `due_date`, sin días de gracia | **B** | Ausencia de regla de gracia; ver pregunta 10 si se desea cambiar |
| Una factura vencida puede recibir pagos sin restricción | **B** (comportamiento actual) | Ver pregunta 11 — es la lectura natural para un sistema cuyo propósito es cobrar morosidad |

## 3. Pagos

| Regla | Categoría | Detalle |
|---|---|---|
| Saldo = monto de la factura − suma de pagos válidos | **A** | HU-08/HU-09, fórmula explícita en la especificación funcional |
| Un pago no puede ser mayor al saldo pendiente | **A** | HU-09, ejemplo de aceptación literal en `docs/BACKLOG.md` |
| Un pago no puede ser cero o negativo | **A** | Validado en `PaymentService` + `CHECK` de BD |
| Una factura puede recibir múltiples pagos parciales hasta completarse | **A** | HU-08/HU-09 |
| El estado de la factura se recalcula automáticamente tras cada pago (PENDIENTE → PARCIAL → PAGADA) | **A** | HU-09 |
| Dos registros de pago concurrentes sobre la misma factura no pueden sobrepasar el saldo conjuntamente | **A** | Requisito implícito de integridad financiera — implementado con `lockForUpdate()` transaccional |
| Un pago, una vez registrado, no puede editarse ni eliminarse — solo anularse, con motivo obligatorio y sin borrar el registro original | **A** (resuelto e implementado 2026-08-28) | Pregunta 21 — `PaymentService::void()`, permiso `payments.void` |
| Un pago anulado no cuenta para `paid_total`, ni para reportes de cobranza ni el dashboard | **A** (resuelto e implementado 2026-08-28) | `Payment::scopeActive()`, aplicado en `ReportService`/`DashboardService` |

## 4. Excel / Importación

| Regla | Categoría | Detalle |
|---|---|---|
| Nada se inserta en la base de datos sin que el usuario vea antes una previsualización | **A** | Sección 15 de la especificación funcional original, literal |
| Solo el campo "Nombre" es obligatorio en el archivo | **A** | HU-04 aplicado al flujo de importación (mismo campo obligatorio que el alta manual) |
| Encabezados reconocidos en español, insensibles a mayúsculas/tildes, en cualquier orden de columnas | **A** | Sección 15 |
| Un correo duplicado (contra la BD o dentro del mismo archivo) se omite y se reporta como error de fila, nunca se sobrescribe | **B** | Es la interpretación más segura de "informar errores" sin una regla explícita de upsert — ver pregunta 16 |
| La importación es parcial por fila, no transaccional para todo el archivo (una fila puede fallar sin abortar las demás) | **B** | Es la única interpretación compatible con "informar registros creados/omitidos/errores por fila" (sección 11 del prompt de auditoría) — una transacción atómica de todo el archivo sería incompatible con ese requisito |
| El archivo se re-parsea desde disco al confirmar, nunca se confía en lo que el navegador reenvía tras la previsualización | **A** | Requisito explícito de seguridad de la sección 15 |
| El formato exacto de columnas que usa la Cámara hoy | **C** | Pregunta 15 — nunca se recibió un archivo real |
| Solo se importan asociados, no facturas ni pagos históricos | **B** (alcance actual) | La especificación original (sección 15) solo mencionaba asociados; importar facturas/pagos sería cambio de alcance — ver pregunta en gap analysis (XLS-04) |

## 5. Permisos y visibilidad

| Regla | Categoría | Detalle |
|---|---|---|
| La autorización se verifica en el servidor en cada request, nunca solo ocultando UI | **A** | Sección 25 de la especificación funcional original ("protección de endpoints"), verificado con test de 403 real |
| Ver un reporte y exportarlo son permisos distintos (`reports.view` vs `reports.export`) | **A** | HU-15 |
| Un módulo inactivo desaparece del menú para todos los roles que lo tengan asignado | **A** | HU-22 |
| Un usuario inactivo no puede autenticarse aunque su rol conserve todos sus permisos | **A** | HU-23 |

## 6. Auditoría

| Regla | Categoría | Detalle |
|---|---|---|
| Se registra usuario, acción, entidad, registro afectado, fecha/hora y resultado de cada operación crítica | **A** | Sección 14 del prompt de auditoría, ya implementado desde sprints anteriores para pagos/facturas/usuarios/permisos |
| Nunca se registra información sensible (contraseñas, tokens) en el log de auditoría | **A** | Verificado por inspección directa de cada llamado a `AuditLog::record()` |
| Intentos de login fallidos también se auditan (`result: failure`) | **B** (va más allá de lo pedido) | No estaba explícitamente pedido, pero ya está implementado y es coherente con buenas prácticas de seguridad |

## 7. Cálculo — regla central

```
Saldo de una factura = amount − paid_total
```

Esta es la **única** fórmula de saldo en todo el sistema. Se expresa en tres lugares por razones puramente técnicas, no porque existan tres reglas distintas:

1. `Invoice::balance()` (PHP) — para un registro ya cargado en memoria, usado por la vista de detalle de factura y por `PortfolioService`.
2. `amount - paid_total` (SQL crudo, dentro de `SUM()`) en `ReportService` y `DashboardService` — necesario porque un método PHP no puede invocarse dentro de una agregación SQL.

Los tres puntos están sincronizados porque las dos columnas de origen (`amount`, `paid_total`) son las mismas en los tres casos — no hay una tercera fuente de verdad. Se centralizó la expresión SQL en una constante compartida (`Invoice::BALANCE_SQL`) en esta misma sesión para que quede escrita en un solo lugar del código fuente, aunque se consuma desde dos contextos distintos.

**Regla de consistencia:** cualquier vista que muestre un saldo (factura, estado de cuenta, cartera, dashboard, reportes) debe derivar ese número de `amount − paid_total`, nunca de una fuente independiente. Verificado por inspección de código: ninguna vista actual viola esta regla.
