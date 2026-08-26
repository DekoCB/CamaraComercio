# Plan de UAT (User Acceptance Testing) — Sistema de Facturación y Cobranzas

Escenarios que representan el trabajo real de la Cámara de Comercio, para que un usuario de negocio (no técnico) confirme que el sistema hace lo que necesita, usando datos ficticios de prueba (ver `docs/UAT_SEED_DATA.md`) — nunca información real de asociados.

**Estado:** este plan está redactado y listo para ejecutarse; los campos "Resultado real" y "Estado" de cada caso quedan en blanco hasta que un usuario de la Cámara lo ejecute — no se completan aquí de forma simulada.

---

### UAT-01 — Registrar asociado

**Precondición:** sesión iniciada con un usuario que tiene el permiso de gestionar asociados.

**Pasos:**
1. Ir a Asociados → "Nuevo asociado".
2. Completar Nombre (obligatorio) y, opcionalmente, Empresa/Contacto/Correo.
3. Guardar.

**Resultado esperado:** el asociado aparece en el listado, activo por defecto, sin necesidad de recargar la página completa (se guarda desde una ventana superpuesta). Si el correo ya existe en otro asociado, el sistema lo rechaza con un mensaje claro antes de guardar.

**Resultado real:** _pendiente de ejecución_
**Estado:** _pendiente_
**Observaciones:** _—_

---

### UAT-02 — Generar facturación mensual

**Precondición:** existen asociados activos; usuario con permiso de generar facturación.

**Pasos:**
1. Ir a Facturación → "Generar facturación del mes".
2. Elegir el período (mes/año), el monto por factura, y la fecha límite de pago.
3. Revisar el resumen (cuántos asociados se van a facturar) y confirmar explícitamente.

**Resultado esperado:** se crea una factura por cada asociado activo que todavía no tenga una para ese período. El sistema informa cuántas se crearon y cuántas se omitieron (si ya existían). Volver a correr el mismo período no duplica nada.

**Resultado real:** _pendiente de ejecución_
**Estado:** _pendiente_
**Observaciones:** _—_

---

### UAT-03 — Registrar un pago

**Precondición:** existe al menos una factura pendiente.

**Pasos:**
1. Abrir el detalle de una factura pendiente.
2. Registrar un pago por el monto total de la factura.

**Resultado esperado:** la factura pasa a estado PAGADA, el saldo queda en S/ 0.00, aparece en el historial de pagos de esa factura con la fecha y el usuario que lo registró.

**Resultado real:** _pendiente de ejecución_
**Estado:** _pendiente_
**Observaciones:** _—_

---

### UAT-04 — Registrar un pago parcial

**Precondición:** existe una factura pendiente con un monto conocido (ej. S/ 500).

**Pasos:**
1. Abrir el detalle de esa factura.
2. Registrar un pago menor al monto total (ej. S/ 200).

**Resultado esperado:** la factura pasa a PARCIAL, el saldo se recalcula correctamente (S/ 300), y un segundo pago posterior puede completar el saldo restante. Intentar pagar más del saldo pendiente se rechaza con un mensaje claro.

**Resultado real:** _pendiente de ejecución_
**Estado:** _pendiente_
**Observaciones:** _—_

---

### UAT-05 — Consultar deuda (cartera)

**Precondición:** existen asociados con facturas en distintos estados (pendiente, parcial, pagada, vencida).

**Pasos:**
1. Ir a Cartera.
2. Revisar el listado general y la vista "A quién falta cobrar".

**Resultado esperado:** cada asociado muestra su total facturado, pagado y pendiente correctamente sumado. "A quién falta cobrar" solo lista asociados con saldo pendiente mayor a cero, con el período de su deuda más antigua.

**Resultado real:** _pendiente de ejecución_
**Estado:** _pendiente_
**Observaciones:** _—_

---

### UAT-06 — Consultar estado de cuenta de un asociado

**Precondición:** un asociado con historial de varias facturas y pagos.

**Pasos:**
1. Desde el listado de asociados, abrir el estado de cuenta de uno con historial.

**Resultado esperado:** se ve el resumen financiero (facturado/pagado/pendiente) y el historial completo de facturas con su estado, más el historial de pagos.

**Resultado real:** _pendiente de ejecución_
**Estado:** _pendiente_
**Observaciones:** _—_

---

### UAT-07 — Generar un reporte

**Precondición:** existen pagos registrados en el mes actual y facturas pendientes.

**Pasos:**
1. Ir a Reportes → "Lo cobrado en el mes", elegir un período, ver el reporte.
2. Ir a Reportes → "Deuda pendiente", ver el reporte.

**Resultado esperado:** el reporte de cobranza muestra el total facturado vs. cobrado del período elegido, cantidad de pagos y de asociados distintos que pagaron. El reporte de deuda pendiente muestra el total adeudado y su distribución por estado, al día de hoy.

**Resultado real:** _pendiente de ejecución_
**Estado:** _pendiente_
**Observaciones:** _—_

---

### UAT-08 — Exportar un reporte

**Precondición:** un reporte ya generado en pantalla (UAT-07); usuario con permiso de exportar.

**Pasos:**
1. Desde cualquiera de los dos reportes, exportar a Excel.
2. Exportar a PDF.

**Resultado esperado:** ambos archivos se descargan correctamente, con los mismos totales que se veían en pantalla, título, fecha de generación y período. Un usuario sin el permiso de exportación no ve la opción o recibe un error claro si la intenta por URL directa.

**Resultado real:** _pendiente de ejecución_
**Estado:** _pendiente_
**Observaciones:** _—_

---

### UAT-09 — Importar asociados desde Excel

**Precondición:** un archivo Excel con al menos una fila válida y una fila con un error intencional (ej. sin nombre, o con un correo ya existente).

**Pasos:**
1. Ir a Asociados → "Importar desde Excel".
2. Subir el archivo.
3. Revisar la previsualización — confirmar que se ve claramente cuáles filas se importarán y cuáles no, y por qué.
4. Confirmar la importación.

**Resultado esperado:** solo las filas válidas se crean como asociados nuevos; las filas con error se muestran claramente marcadas con el motivo, y no se crean. Cancelar en vez de confirmar no crea absolutamente nada.

**Resultado real:** _pendiente de ejecución_
**Estado:** _pendiente_
**Observaciones:** _—_

---

## Cómo registrar el resultado

Al ejecutar cada caso, completar "Resultado real" con lo que efectivamente ocurrió, "Estado" con `PASA` / `FALLA` / `PASA CON OBSERVACIONES`, y "Observaciones" con cualquier detalle relevante (ej. un mensaje de error específico, una pantalla que no se veía como se esperaba). Un caso `FALLA` se convierte en un hallazgo a resolver antes de considerar el sprint correspondiente `DONE` — ver `docs/DEFINITION_OF_DONE.md`.
