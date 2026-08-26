# Definition of Done — Sistema de Facturación y Cobranzas

## Por historia de usuario

Una HU está `DONE` solamente cuando **todo** lo siguiente es verdadero:

- [ ] Código implementado (controller, Form Request, Service si aplica, modelo).
- [ ] Validaciones implementadas — cada regla de `docs/BUSINESS_RULES.md` categoría A o B que le corresponda está codificada, no solo documentada.
- [ ] Backend protegido — la ruta exige el permiso correcto vía middleware `can:`, verificado con un test que confirme 403 real ante un usuario sin ese permiso (no alcanza con que el frontend oculte el botón).
- [ ] Frontend integrado — la pantalla existe, sigue el sistema de diseño (`docs/DESIGN_SYSTEM.md`), y no rompe ninguna otra pantalla existente.
- [ ] Base de datos integrada — migración aplicada, columnas/constraints correctos, sin romper `php artisan migrate:fresh --seed` desde cero.
- [ ] Tests ejecutados y en verde — `php artisan test` completo, no solo el archivo nuevo.
- [ ] Sin errores críticos — cero excepciones no controladas al ejercer el flujo feliz y los casos de error esperables en un navegador real (no solo en los tests automatizados).
- [ ] Documentación actualizada — el archivo de `docs/` correspondiente (`BUSINESS_RULES.md`, `DATA_SCHEMA.md`, `ACCEPTANCE_CRITERIA.md`, según corresponda) refleja el comportamiento final, no una versión anterior.
- [ ] Criterios de aceptación de `docs/ACCEPTANCE_CRITERIA.md` cumplidos — cada Given/When/Then de esa HU pasa, verificado explícitamente (no asumido porque "el código se parece a lo que pide la HU").

Una HU que cumple 8 de 9 puntos no está `DONE` — está `IN_PROGRESS` o `REVIEW`, según los estados definidos en `docs/BACKLOG.md`.

## Del proyecto completo

El proyecto completo está `DONE` solamente cuando, además de que las 23 HU (+ las historias adicionales de `docs/BACKLOG.md`) cumplan lo anterior:

- [ ] **QA aprobado** — `docs/QA_PLAN.md` ejecutado, sin hallazgos abiertos de severidad alta o crítica.
- [ ] **UAT aprobado** — los 9 escenarios de `docs/UAT_PLAN.md` ejecutados por un usuario real de la Cámara (no simulados por el equipo de desarrollo), con estado `PASA` o `PASA CON OBSERVACIONES` aceptadas explícitamente por el cliente.
- [ ] **Migración validada** — si hay datos reales que migrar desde el proceso manual actual (Excel), la importación se probó con un archivo real de la Cámara, no solo con datos ficticios (ver `docs/EXCEL_MIGRATION_SPECIFICATION.md` pregunta 15 pendiente).
- [ ] **Seguridad revisada** — `docs/REQUIREMENTS_GAP_ANALYSIS.md` sección 6 sin hallazgos `FALTANTE` de severidad alta; `SESSION_SECURE_COOKIE` seteado explícitamente para el entorno de producción real.
- [ ] **Backups configurados** — no solo documentados como propuesta (`docs/BACKUP_AND_RECOVERY.md`), sino efectivamente corriendo en el entorno de destino, con al menos una restauración de prueba exitosa.
- [ ] **Deployment realizado** — el sistema corre en el entorno de producción real definido (`docs/DEPLOYMENT.md`), no solo en el XAMPP de desarrollo.
- [ ] **Manual entregado** — `docs/USER_MANUAL.md` compartido con los usuarios finales de la Cámara.
- [ ] **Capacitación realizada** — evento humano, no un artefacto de código; su insumo es el manual de usuario.
- [ ] **Aceptación formal** — alguien con autoridad de la Cámara de Comercio confirma explícitamente, por escrito, que el sistema cumple lo acordado. Ningún checklist de este documento reemplaza esa confirmación.

## Decisiones de negocio pendientes no bloquean "DONE" de las 23 HU originales

Las 20 preguntas de `docs/OPEN_BUSINESS_DECISIONS.md` no impiden declarar `DONE` las historias ya implementadas y probadas — impiden implementar **funcionalidad nueva** que dependa de esas respuestas (ej. anulación de facturas, corrección de pagos). El proyecto puede estar `DONE` en sus 23 HU originales mientras esas preguntas siguen abiertas como trabajo futuro explícitamente fuera del alcance del MVP — exactamente como ya lo documenta `docs/PROJECT_ANALYSIS.md` sección 6.
