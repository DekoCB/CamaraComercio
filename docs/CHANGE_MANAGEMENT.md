# Gestión de cambios — Sistema de Facturación y Cobranzas

Toda nueva funcionalidad o modificación solicitada, a partir de esta auditoría, se clasifica en una de tres categorías antes de tocar código. La clasificación determina si se implementa directamente o si requiere aprobación explícita primero.

## Categorías

### Corrección

Un bug respecto a un requerimiento **ya aprobado** — el sistema no hace lo que la especificación funcional (23 HU) o una decisión de negocio ya validada dice que debería hacer.

**Se implementa directamente**, sin pedir aprobación adicional — es restaurar el comportamiento acordado, no cambiarlo.

*Ejemplo real de esta sesión:* la validación de correo duplicado de asociados existía en la importación por Excel pero no en el alta manual — ambos caminos deberían aplicar la misma regla (HU-04 no distingue "por Excel" de "manual"). Se corrigió sin pedir aprobación porque no cambia ninguna regla, solo la hace consistente.

### Ajuste

Un cambio menor que **no** altera arquitectura, modelo de datos, ni alcance — mejora de redacción, orden de campos, un mensaje de error más claro, un ajuste de estilo visual.

**Se implementa directamente**, documentando el cambio en el archivo correspondiente de `docs/` si afecta una regla ya escrita.

### Cambio de alcance

Nueva funcionalidad, o una modificación que altera:

- Arquitectura (ej. introducir colas asíncronas, WebSockets, un servicio externo).
- Modelo de datos (ej. una columna nueva con significado de negocio, como el RUC del asociado).
- Reglas de negocio (ej. permitir anular una factura, permitir corregir un pago).
- Esfuerzo o cronograma de forma significativa.

**No se implementa automáticamente.** Se documenta en `docs/OPEN_BUSINESS_DECISIONS.md` (si es una pregunta abierta) o se marca `REQUIERE APROBACIÓN` en el documento correspondiente, y se espera confirmación explícita antes de escribir código.

## Cómo se marca un cambio de alcance en la documentación

Cuando este proyecto identifica algo que cae en esta categoría, se anota así en el documento relevante:

```
`REQUIERE APROBACIÓN` — [descripción del cambio, impacto estimado, y por qué no es una Corrección ni un Ajuste]
```

Ejemplos ya identificados en esta auditoría que caerían aquí si se aprueban:

| Cambio propuesto | Por qué es cambio de alcance | Dónde está documentado |
|---|---|---|
| Agregar RUC como identificador de asociado | Columna nueva con significado de negocio, posible validación de unicidad nueva | `docs/OPEN_BUSINESS_DECISIONS.md` pregunta 12 |
| Permitir anular una factura | Nuevo estado, toca reportes/cartera/dashboard | `docs/OPEN_BUSINESS_DECISIONS.md` pregunta 6 |
| Permitir corregir/anular un pago | Nueva lógica de reversa, afecta `paid_total`/`status` de la factura | `docs/OPEN_BUSINESS_DECISIONS.md` pregunta 8 |
| Importar facturas/pagos históricos desde Excel (no solo asociados) | Nuevo flujo completo, fuera del alcance original de la sección 15 | `docs/REQUIREMENTS_GAP_ANALYSIS.md` XLS-04 |
| Pantalla para consultar el log de auditoría desde la UI | Nueva pantalla, nuevo permiso — la tabla ya existe pero no es visible hoy | `docs/REQUIREMENTS_GAP_ANALYSIS.md` AUD-08 |

## Alcance dinámico durante Discovery — qué sí se puede refinar sin aprobación

Consistente con el propio prompt de auditoría de 2026-08-19: la documentación funcional puede refinarse sin que cada ajuste sea un "cambio de alcance". **Se puede refinar sin aprobación adicional:**

- Criterios de aceptación (agregar detalle a una HU ya aprobada — `docs/ACCEPTANCE_CRITERIA.md`).
- Campos de un formulario (agregar un `placeholder`, un `field-help`, reordenar).
- Validaciones (endurecer una regla ya implícita, como se hizo con el correo duplicado).
- Reglas de negocio que estaban pendientes y se confirman (una respuesta a `docs/OPEN_BUSINESS_DECISIONS.md` no es un cambio de alcance en sí misma — es completar información faltante; el cambio de alcance sería la *implementación* que esa respuesta habilite, si es significativa).
- Detalles técnicos (nombre de una variable, estructura interna de un Service) que no cambian el comportamiento observable.

**No se puede cambiar sin aprobación, bajo ninguna circunstancia:**

- Los IDs de HU (HU-01 a HU-23).
- Las épicas (EP-01 a EP-08).
- Los objetivos principales de cada HU.

## Quién aprueba

Este documento no asigna un aprobador específico (no hay un rol de "Product Owner" definido en la especificación funcional original) — en la práctica, cualquier `REQUIERE APROBACIÓN` o `REQUIERE VALIDACIÓN DEL CLIENTE` de este proyecto se resuelve con quien tenga autoridad de negocio sobre el sistema en la Cámara de Comercio, la misma persona/rol que aprobaría cualquier otra decisión de `docs/OPEN_BUSINESS_DECISIONS.md`.
