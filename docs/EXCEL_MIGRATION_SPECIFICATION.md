# Especificación de migración desde Excel — Asociados

Formaliza el comportamiento de `app/Services/AssociateImportService.php` y `app/Http/Controllers/AssociateImportController.php` tal como existen hoy (verificado por lectura directa del código, no por documentación previa). Cubre únicamente **asociados** — no facturas ni pagos, ver `docs/OPEN_BUSINESS_DECISIONS.md` (XLS-04 en `docs/REQUIREMENTS_GAP_ANALYSIS.md`) si se necesita ampliar el alcance.

## Formato esperado

- Cualquier formato de spreadsheet que `PhpOffice\PhpSpreadsheet\IOFactory::load()` reconozca (`.xlsx`, `.xls`, `.csv`, `.ods` entre otros) — la librería detecta el formato automáticamente por el contenido del archivo, no por la extensión.
- Primera fila = encabezados. Todo lo demás = datos, una fila por asociado.
- Filas completamente vacías se ignoran silenciosamente (no cuentan como error) — es común que un Excel real tenga filas en blanco al final.

## Columnas

Reconocimiento de encabezado **insensible a mayúsculas y a tildes**, en **cualquier orden**:

| Campo interno | Encabezados aceptados | Obligatorio |
|---|---|---|
| `name` | `Nombre`, `Name`, `Asociado` | **Sí** — un archivo sin esta columna se rechaza antes de mostrar cualquier previsualización |
| `company` | `Empresa`, `Company`, `Compania`, `Compañia` | No |
| `contact_phone` | `Contacto`, `Telefono`, `Teléfono`, `Phone`, `Contact_phone` | No |
| `email` | `Correo`, `Email`, `Correo electronico`, `Correo electrónico` | No |

Cualquier columna adicional que el archivo tenga (que no coincida con ninguno de estos alias) se ignora — no genera error, simplemente no se importa.

`REQUIERE VALIDACIÓN DEL CLIENTE`: estos encabezados son una inferencia razonable a partir de los campos que pide HU-04, no una confirmación de que coinciden con el Excel real que usa la Cámara hoy — ver pregunta 15 de `docs/OPEN_BUSINESS_DECISIONS.md`.

## Mapeo Excel → base de datos

| Columna Excel | Columna `associates` | Transformación |
|---|---|---|
| Nombre | `name` | `trim()`; vacío se reporta como error de fila |
| Empresa | `company` | `trim()`; vacío se guarda como `NULL`, no como cadena vacía |
| Contacto | `contact_phone` | `trim()`; igual que Empresa |
| Correo | `email` | `trim()`; validado con `filter_var(FILTER_VALIDATE_EMAIL)`; vacío se guarda como `NULL` |

Todo asociado importado se crea con `is_active = true` — no existe forma de importar un asociado ya inactivo.

## Validaciones

Aplicadas fila por fila durante la **previsualización** (antes de cualquier escritura en base de datos):

1. `name` vacío → error "El nombre es obligatorio."
2. `email` no vacío pero con formato inválido → error "El correo no es válido."
3. `email` no vacío y ya existente en `associates` (o repetido dentro del mismo archivo, contando los que ya se marcaron válidos en filas anteriores) → error "Ya existe un asociado con ese correo."

Una fila con **cualquier** error queda marcada como no-importable en la previsualización — se muestra igual en pantalla (para que el usuario vea qué pasó), pero no se envía al paso de confirmación.

## Duplicados

Ver `docs/OPEN_BUSINESS_DECISIONS.md` pregunta 16: la política actual es **omitir y reportar**, nunca sobrescribir un registro existente. Un correo duplicado nunca actualiza al asociado existente, aunque los demás datos de la fila sean distintos.

## Fechas y montos

No aplica — el importador de asociados no maneja ningún campo de fecha ni monto (esos solo existen en facturas/pagos, fuera del alcance de este importador). Si en el futuro se agrega importación de facturas/pagos históricos, este documento debe extenderse con las reglas de parseo de fecha (¿qué formato? ¿Excel serial date o texto?) y de monto (¿separador decimal? ¿símbolo de moneda incluido?) — ninguna de esas reglas existe hoy porque no hace falta.

## Flujo

```
UPLOAD (GET /associates/import, formulario con <input type="file">)
  ↓
POST /associates/import/preview
  → AssociateImportService::parse() lee el archivo, lo guarda en storage/app/imports/
    con un nombre aleatorio (UUID), valida cada fila
  ↓
PREVISUALIZACIÓN (vista con tabla: fila, nombre, empresa, contacto, correo, estado ✓/✗)
  → el usuario ve exactamente qué se va a crear y qué se va a omitir, antes de
    que exista ningún registro en la base de datos
  ↓
  ├── POST /associates/import/cancel → borra el archivo temporal, no crea nada
  │
  └── POST /associates/import/confirm
        → AssociateImportController::confirm() vuelve a leer el archivo DESDE DISCO
          (nunca confía en lo que el navegador reenvía desde la previsualización —
          ver docs/ARCHITECTURE.md, es el mismo principio de seguridad que
          PaymentService aplica con lockForUpdate())
        → AssociateImportService::import() crea cada fila válida en su propio
          try/catch — una fila que falle no aborta las demás (ver "Importación
          transaccional" abajo)
        → el archivo temporal se borra al confirmar
  ↓
RESUMEN (toast + AuditLog::record('associate.import', ...) con creados/omitidos/errores)
```

**Nunca se inserta el archivo completo sin validación previa** — la ruta `preview` es de solo lectura (parsea y valida, no escribe nada), y es la única forma de llegar a `confirm`.

## Importación transaccional

**No es "todo o nada" — es intencionalmente parcial por fila.** Cada fila válida se inserta en su propio `try/catch` dentro de `AssociateImportService::import()`. Si una fila falla en el momento de la escritura (ej. una condición de carrera de correo duplicado entre el instante de la previsualización y el de la confirmación, si otro usuario creó ese mismo correo mientras tanto), las demás filas válidas **sí se insertan**, y la fallida se reporta en el resumen final junto a su motivo.

Esto es la lectura correcta de la sección 11 del prompt de auditoría ("informar registros procesados/creados/omitidos/errores, cada error con fila/columna/valor/motivo") — una transacción atómica de todo el archivo sería incompatible con reportar qué fila específica falló mientras las demás sí se procesaron. Documentado explícitamente en `docs/BUSINESS_RULES.md` categoría B para que quede claro que es una decisión de diseño consciente, no un descuido.

**Brecha real identificada:** el reporte de error actual da **fila + motivo**, pero no aísla explícitamente **columna + valor** de forma estructurada (el motivo suele mencionarlos implícitamente, ej. "El correo no es válido" ya apunta a la columna correo, pero no como un campo separado). Ver `docs/REQUIREMENTS_GAP_ANALYSIS.md` XLS-03 — mejora de bajo riesgo, no bloqueante, no implementada en esta sesión por no ser una brecha crítica.

## Resumen entregado al usuario

Al confirmar: `{created: int, errors: array<{row: int, message: string}>}`. Al previsualizar: cuenta de filas válidas vs. con error, mostradas en pantalla con un badge por fila. No se reportan "registros actualizados" porque el importador nunca actualiza, solo crea (ver "Duplicados" arriba).
