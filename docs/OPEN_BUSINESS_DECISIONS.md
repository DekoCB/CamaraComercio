# Decisiones de negocio abiertas — Sistema de Facturación y Cobranzas

Cada pregunta de esta lista bloquea una implementación concreta. Ninguna se responde por inferencia cuando la inferencia sería una regla de negocio *crítica* (irreversible, afecta dinero, o afecta a quién puede ver qué). Donde el código ya tiene un comportamiento de facto (porque algo tenía que pasar), se documenta como "comportamiento actual" — eso **no** es lo mismo que una decisión de negocio confirmada, y se marca así explícitamente.

Formato: pregunta → comportamiento actual (si existe) → opciones → recomendación (no vinculante) → `REQUIERE VALIDACIÓN DEL CLIENTE`.

---

### 1. ¿Cuándo un asociado pasa de ACTIVO a INACTIVO?

**Comportamiento actual:** solo manualmente, editando el checkbox "Asociado activo" en el formulario de edición. No hay ninguna automatización.

**Opciones:**
- **A.** Inactivo manualmente por un administrador (comportamiento actual, sin cambios).
- **B.** Inactivo automáticamente tras un período sin actividad (¿cuánto tiempo? ¿"actividad" significa sin facturas nuevas, sin pagos, o ambas?).
- **C.** Inactivo según una condición administrativa definida por la Cámara (ej. renuncia formal, no renovación de membresía anual).

**Recomendación:** mantener la Opción A. Una automatización silenciosa que deje de facturar a un asociado sin que nadie lo decidiera explícitamente es exactamente el tipo de regla crítica que esta auditoría no debe inventar.

`REQUIERE VALIDACIÓN DEL CLIENTE`

---

### 2. ¿Quién puede cambiar el estado de un asociado?

**Comportamiento actual:** cualquier usuario con el permiso `associates.manage` (el mismo permiso que controla crear/editar asociados — no hay un permiso separado para "activar/desactivar" como sí existe para módulos).

**Opciones:**
- **A.** Mantener bajo `associates.manage` (comportamiento actual).
- **B.** Crear un permiso separado `associates.deactivate`, análogo a como los módulos ya distinguen creación de activación.

**Recomendación:** Opción A es suficiente mientras solo dos roles existan (Administrador, Encargado de Cobranzas) y ambos ya tengan o no tengan `associates.manage` como bloque. Si en el futuro se necesita que alguien edite datos de contacto pero no pueda desactivar asociados, se vuelve relevante la Opción B.

`REQUIERE VALIDACIÓN DEL CLIENTE`

---

### 3. ¿La inactivación es automática?

Ver pregunta 1 — misma respuesta: no, hoy es 100% manual, y no se recomienda automatizar sin una regla exacta.

`REQUIERE VALIDACIÓN DEL CLIENTE`

---

### 4. ¿Los asociados inactivos reciben facturas?

**Esta es la única de las preguntas de este documento que el código ya responde de forma inequívoca:** **no**. `InvoiceGenerationService::generateForPeriod()` filtra explícitamente `Associate::where('is_active', true)` antes de generar cualquier factura — un asociado inactivo queda completamente fuera de cualquier corrida de facturación masiva, sin excepción.

Esto **no** requiere validación — es DEFINIDO por el propio diseño del sistema desde Sprint 2, y coincide con la lectura más razonable de "inactivo" en un sistema de cobranza.

---

### 5. ¿Puede una factura modificarse una vez generada?

**Comportamiento actual:** no. No existe ruta `edit`/`update` para facturas. Una vez creada, solo `paid_total` y `status` cambian, y únicamente como efecto secundario de registrar un pago.

**Opciones:**
- **A.** Nunca editable — si hay un error, se corrige por otra vía (nota de crédito, ajuste manual en BD, factura de corrección).
- **B.** Editable solo si `paid_total = 0` (todavía no recibió ningún pago) — evita reescribir el historial financiero de algo que ya se cobró parcialmente.
- **C.** Editable siempre, con registro en auditoría de qué cambió.

**Recomendación:** Opción B es el punto medio más común en sistemas de facturación — permite corregir errores de tipeo (monto, fecha) mientras la factura sigue "en borrador" desde la perspectiva contable, y protege la integridad una vez que hay dinero real de por medio.

`REQUIERE VALIDACIÓN DEL CLIENTE`

---

### 6. ¿Puede anularse una factura?

**Comportamiento actual:** no existe el concepto de "anulada" en el modelo de datos (`status` solo admite PENDIENTE/PARCIAL/PAGADA, y VENCIDA se calcula aparte).

**Opciones:**
- **A.** No implementar anulación — una factura generada por error se corrige ajustando manualmente o queda como pendiente sin cobrar nunca (mal indicador en reportes, pero sin cambio de alcance).
- **B.** Agregar un quinto estado `ANULADA`, con motivo obligatorio, excluida de reportes de cobranza pendiente pero visible en el historial.

**Recomendación:** si la Cámara genera facturación masiva mensual (HU-06) y se equivoca de monto o de período, necesitará una forma de anular sin que quede como "deuda pendiente" falsa en la cartera. Se recomienda la Opción B, pero es un cambio de alcance real: nueva columna, nueva regla en `Invoice::effectiveStatus()`, nuevo filtro en `PortfolioService`/`ReportService`. No se implementa sin aprobación.

`REQUIERE VALIDACIÓN DEL CLIENTE` / si se aprueba, además `REQUIERE APROBACIÓN` como cambio de alcance (sección 25 del prompt de auditoría)

---

### 7. ¿Puede eliminarse una factura?

**Comportamiento actual:** no — coherente con la política de "sin borrado físico" que rige todo el sistema (`docs/DATA_MODEL.md`), reforzada por `invoices.associate_id` con `restrictOnDelete()`.

**Recomendación:** no eliminar físicamente nunca — es la práctica correcta para cualquier registro con implicancia contable/financiera. Si se necesita "deshacer" una factura, la respuesta correcta es la anulación (pregunta 6), no el borrado.

Esto **no requiere validación adicional** más allá de confirmar que la Cámara está de acuerdo con "nunca se borra, como máximo se anula" — que ya es la política implícita del resto del sistema.

---

### 8. ¿Puede una factura tener múltiples pagos?

**Ya definido e implementado:** sí. `Invoice::payments(): HasMany`, probado explícitamente con pagos parciales acumulativos (`tests/Feature/PaymentTest.php`). No requiere validación.

---

### 9. ¿Qué ocurre con un pago superior al saldo?

**Ya definido e implementado:** se rechaza. `PaymentService::register()` lanza una excepción de validación si `$amount > $invoice->balance()`, verificado con `lockForUpdate()` transaccional para que dos pagos concurrentes no puedan sobrepagar juntos una misma factura. No requiere validación.

---

### 10. ¿Cuál es la regla exacta de vencimiento?

**Ya definido e implementado:** una factura vence en su `due_date` (fecha fijada al momento de generarla, elegida en el paso 3 del wizard de facturación). `isOverdue()` compara esa fecha contra "hoy". No hay días de gracia, ni distinción de fin de semana/feriado. Si la Cámara necesita días de gracia antes de considerar una factura vencida, es un cambio de regla explícito.

`REQUIERE VALIDACIÓN DEL CLIENTE` únicamente si se desea introducir un período de gracia — el comportamiento actual (vencimiento exacto en `due_date`) es razonable como default y no bloquea nada mientras tanto.

---

### 11. ¿Qué ocurre con una factura vencida que recibe un pago?

**Comportamiento actual:** se acepta sin restricción — `PaymentService::register()` no verifica `isOverdue()`/`effectiveStatus()` en ningún punto, solo el saldo disponible. Un pago sobre una factura vencida la mueve a PARCIAL o PAGADA exactamente igual que uno sobre una factura pendiente a tiempo.

**Opciones:**
- **A.** Aceptar el pago sin restricción (comportamiento actual) — es lo esperable en cobranza: cobrar una deuda vencida es el objetivo, no una excepción.
- **B.** Aceptar el pago pero exigir una nota/observación obligatoria cuando la factura estaba vencida (ya existe el campo `notes`, opcional hoy).
- **C.** Aplicar algún recargo por mora automático (no mencionado en ninguna parte de la especificación funcional original — sería un cambio de alcance significativo).

**Recomendación:** Opción A. Es el comportamiento natural para un sistema de cobranza de morosidad (HU-10/HU-11 existen justamente para identificar deuda vencida y cobrarla). No se recomienda la Opción C sin que la Cámara lo pida explícitamente, ya que implica una fórmula de mora que hoy no existe en ningún lado de la documentación.

`REQUIERE VALIDACIÓN DEL CLIENTE` solo para confirmar que A es aceptable — el sistema ya se comporta así.

---

### 12. ¿Cuál es el identificador único del asociado?

**Comportamiento actual:** ninguno, más allá del `id` autoincremental interno. `associates.email` es nullable y **sin índice `UNIQUE`**; `associates.name` tampoco. El alta manual (`AssociateRequest`) no valida duplicados de ningún campo. La importación por Excel sí rechaza correos duplicados (`AssociateImportService::parse()`), pero solo dentro de ese flujo — es una inconsistencia real entre los dos caminos de alta.

**Opciones:**
- **A.** El correo electrónico es el identificador de negocio — agregar `UNIQUE` a `associates.email` (con la complicación de que hoy es nullable: dos asociados sin correo no deberían chocar entre sí, así que sería un unique condicional/parcial, o se pasaría a requerir correo siempre).
- **B.** Un número de RUC/documento de identidad tributario — **no existe ningún campo así en el modelo de datos actual**; sería una columna nueva.
- **C.** Sin identificador de negocio único — el nombre puede repetirse legítimamente (empresas con nombres comerciales similares, personas con el mismo nombre), y la responsabilidad de no duplicar queda en el criterio de quien da de alta.

**Recomendación:** para una Cámara de Comercio, lo más natural es el **RUC** (Opción B) — es el identificador legal real de una empresa afiliada en Perú, más confiable que el correo (que puede cambiar) o el nombre (que puede repetirse). Pero esto es un cambio de modelo de datos (columna nueva, posiblemente obligatoria) que la especificación funcional original nunca mencionó — es un cambio de alcance, no una corrección.

`REQUIERE VALIDACIÓN DEL CLIENTE` — es la pregunta con mayor impacto de modelo de datos de todo este documento.

---

### 13. ¿Cuál es el identificador único de una factura?

**Ya definido e implementado:** el `id` autoincremental interno, más la combinación **`(associate_id, período)`** que sí tiene un `UNIQUE` real en base de datos — un asociado no puede tener dos facturas para el mismo período (`YYYY-MM`). No requiere validación; es exactamente la regla que la sección 5 del prompt de auditoría pedía confirmar, y ya está implementada como constraint de base de datos, no solo como validación de aplicación.

---

### 14. ¿Puede existir más de una factura por período para el mismo asociado?

Respondida por la pregunta 13: no, está bloqueado a nivel de base de datos. No requiere validación adicional — a menos que la Cámara efectivamente necesite facturar dos conceptos distintos al mismo asociado en el mismo mes (ej. cuota ordinaria + cuota extraordinaria), en cuyo caso el modelo actual (una fila = un asociado + un período) no lo soporta y sería un cambio de alcance.

`REQUIERE VALIDACIÓN DEL CLIENTE` solo si existe ese escenario de "más de un concepto facturable por mes" — no estaba contemplado en la especificación original.

---

### 15. ¿Qué columnas tiene el Excel actual (de origen, el que la Cámara ya usa)?

**No definido.** El importador (`AssociateImportService`) fue diseñado reconociendo encabezados en español de forma flexible (Nombre/Empresa/Contacto/Correo, en cualquier orden, con variantes de tildes), pero **nunca se recibió un archivo Excel real de la Cámara** para confirmar que esas son efectivamente las columnas que usan hoy. Es una inferencia razonable basada en los campos que pide HU-04 (registrar asociado), no una confirmación.

`REQUIERE VALIDACIÓN DEL CLIENTE` — idealmente, un archivo Excel real (o una plantilla) de la Cámara para verificar que el importador realmente reconoce sus columnas.

---

### 16. ¿Cómo deben tratarse los duplicados en la importación?

**Comportamiento actual:** un correo que ya existe en la base de datos (o que se repite dentro del mismo archivo) se marca con error y se omite de la importación — nunca se sobrescribe ni se fusiona con el registro existente.

**Opciones:**
- **A.** Omitir silenciosamente y reportarlo como error de fila (comportamiento actual).
- **B.** Actualizar el registro existente con los datos nuevos del Excel (upsert).
- **C.** Preguntar al usuario fila por fila qué hacer (no viable dado el flujo de previsualización masiva ya implementado).

**Recomendación:** mantener la Opción A — es la más segura (nunca sobrescribe datos existentes sin que alguien lo decida explícitamente), y ya está implementada.

`REQUIERE VALIDACIÓN DEL CLIENTE` solo si la Cámara efectivamente necesita actualizar asociados existentes vía Excel (Opción B), que sería una funcionalidad nueva.

---

### 17. ¿Qué información es obligatoria (en alta de asociado y en el Excel)?

**Ya definido e implementado, con una asimetría real:** en el alta manual, solo `name` es obligatorio (`AssociateRequest`); `company`, `contact_phone`, `email` son opcionales. En el Excel, la misma regla aplica (solo "Nombre" obligatorio) — son consistentes entre sí. No requiere validación adicional, salvo que la pregunta 12 (identificador único) determine que algún campo deba pasar a ser obligatorio (ej. si se decide que el correo es el identificador de negocio, tendría que dejar de ser opcional).

---

### 18. ¿Cuánto tiempo deben conservarse los datos?

**No definido.** No existe ninguna política de retención ni proceso de purga en el sistema — todo se conserva indefinidamente (coherente con "no hay borrado físico").

`REQUIERE VALIDACIÓN LEGAL` si aplica alguna normativa peruana de protección de datos o de conservación de comprobantes contables/tributarios (que típicamente exige un mínimo de años, no un máximo) — ver `docs/DATA_PROTECTION.md`.

---

### 19. ¿Quién puede exportar información?

**Ya definido e implementado:** el permiso `reports.export`, distinto de `reports.view` — un rol puede ver un reporte en pantalla sin poder extraer el archivo (verificado en `tests/Feature/ReportTest.php`). No requiere validación.

---

### 20. ¿Quién puede ver información financiera?

**Ya definido e implementado:** vía los permisos existentes (`billing.view`, `payments.register`, `portfolio.view`, `reports.view`) — cada uno controla una vista financiera distinta, y hoy ambos roles de desarrollo (Administrador, Encargado de Cobranzas) tienen acceso a todas. Si la Cámara necesita un rol con acceso limitado (ej. alguien que solo vea cartera pero no pagos individuales), el sistema de permisos granulares ya lo soporta — solo falta que se defina y se le asigne ese subconjunto de permisos al crear el rol. No requiere cambio de código, solo configuración de datos.

---

## Resumen de impacto por pregunta

| # | Pregunta | Impacto si se aprueba un cambio | Bloquea código hoy? |
|---|---|---|---|
| 1-3 | Regla de inactivación de asociado | Bajo (una automatización futura, opcional) | No — el modelo ya soporta ambos estados |
| 4 | Inactivos no facturan | — ya resuelto | No |
| 5 | Editar factura generada | Medio (nuevo endpoint + regla de negocio) | No — nadie ha pedido esto todavía como bug |
| 6 | Anular factura | Alto (nuevo estado, toca reportes/cartera) | No, pero es la mejora de mayor valor percibido |
| 7 | Eliminar factura | — ya resuelto (nunca) | No |
| 8-10 | Pagos múltiples / sobrepago / vencimiento | — ya resuelto | No |
| 11 | Pago sobre factura vencida | — ya resuelto (se acepta) | No |
| 12 | Identificador único del asociado | **Alto** — posible columna nueva (RUC), validación de unicidad real | **Sí** — cualquier regla de unicidad que se implemente sin esto se estaría inventando |
| 13-14 | Identificador de factura | — ya resuelto | No |
| 15-17 | Formato del Excel real | Medio — el importador podría no reconocer el archivo real de la Cámara | No bloquea el código, sí la confianza en que funcione con datos reales |
| 18 | Retención de datos | Bajo técnicamente, puede ser alto legalmente | No |
| 19-20 | Quién exporta/ve qué | — ya resuelto | No |

**La única pregunta que genuinamente bloquea escribir código nuevo con confianza es la 12** (identificador único del asociado) — es la base de cualquier regla de "no duplicar asociados" que se quiera implementar. Todas las demás son mejoras opcionales sobre un sistema que ya tiene un comportamiento coherente, documentado y probado.
