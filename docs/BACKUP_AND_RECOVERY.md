# Backups y recuperación — Sistema de Facturación y Cobranzas

**Estado actual: no existen backups configurados.** No hay ningún script, cron job, ni servicio de backup en el repositorio ni en el entorno de desarrollo XAMPP. Este documento es una **propuesta de arquitectura**, no una descripción de infraestructura real — no se afirma que exista algo que no existe, siguiendo la instrucción explícita del prompt de auditoría.

## Qué se necesita respaldar

| Elemento | Contenido | Criticidad |
|---|---|---|
| Base de datos MySQL (`camara_comercio`) | Todas las tablas de negocio (asociados, facturas, pagos, usuarios, roles, permisos, módulos, auditoría) | **Crítica** — es la única fuente de verdad, no hay copia en ningún otro lado |
| `storage/app/public/avatars` | Fotos de perfil de usuarios | Baja — se puede volver a subir manualmente si se pierde, no afecta datos financieros |
| `.env` (fuera del repositorio, nunca en git) | `APP_KEY`, credenciales de base de datos | **Crítica pero de otra naturaleza** — no es un "backup de datos", es configuración; perderlo sin un respaldo separado significa no poder desencriptar sesiones/cookies existentes y tener que reconfigurar credenciales desde cero |
| Código fuente | Todo `app/`, `resources/`, `routes/`, etc. | Ya respaldado por git (GitHub) — no requiere una estrategia de backup adicional |

`storage/app/imports` (archivos temporales de importación) **no** requiere backup — son efímeros por diseño, se borran al confirmar o cancelar cada importación.

## Propuesta — frecuencia

- **Base de datos:** `mysqldump` diario (fuera de horario de uso, ej. 2:00 AM hora Perú) más un dump manual **antes de cada migración en producción** (`php artisan migrate` sobre datos reales) — esto último ya estaba señalado como requisito en `README.md` antes de esta auditoría, dado el antecedente de corrupción de MariaDB documentado en `docs/PROJECT_ANALYSIS.md` sección 2.
- **Archivos (`storage/app/public`):** semanal es suficiente dado su bajo volumen de cambio (solo fotos de perfil).

## Propuesta — tipo

- **Base de datos:** dump lógico completo (`mysqldump --single-transaction`) en vez de snapshot físico — más simple de restaurar de forma selectiva si hiciera falta, y no depende de que el motor de almacenamiento del servidor de destino sea idéntico.
- **Archivos:** copia directa del directorio (`rsync` o equivalente).

## Propuesta — retención

- Diarios: conservar 14 días.
- Uno semanal de cada semana: conservar 3 meses.
- Backup previo a cada migración de producción: conservar indefinidamente hasta confirmar que la migración fue exitosa y estable (mínimo 30 días).

Estos números son una propuesta razonable por defecto, no un requisito confirmado por el cliente — `REQUIERE VALIDACIÓN DEL CLIENTE` si la Cámara tiene una política de retención distinta (por ejemplo, motivada por el punto de retención legal abierto en `docs/DATA_PROTECTION.md` sección 7).

## Propuesta — ubicación

Los backups **no deben** vivir en el mismo servidor que la base de datos original (un backup que se pierde junto con el servidor que falló no es un backup). Opciones típicas: almacenamiento en la nube (S3 u equivalente) o un segundo servidor/NAS. La elección concreta depende de dónde termine desplegándose producción — no definido todavía (ver `docs/DEPLOYMENT.md`).

## Propuesta — restauración

1. Detener la aplicación (modo mantenimiento: `php artisan down`).
2. Restaurar el dump más reciente válido (`mysql camara_comercio < backup.sql`).
3. Ejecutar `php artisan migrate --force` si el backup es anterior a alguna migración ya aplicada en el código actual (para que el esquema quede alineado).
4. Verificar con un smoke test manual: login, ver dashboard, ver un asociado, ver una factura — confirma que los datos restaurados son legibles y consistentes antes de reabrir el sistema.
5. Reabrir la aplicación (`php artisan up`).

## Propuesta — verificación

Un backup que nunca se probó restaurar no es un backup confiable. Se recomienda una restauración de prueba trimestral, en un entorno separado (no producción), para confirmar que el proceso de restauración descrito arriba efectivamente funciona con los dumps que se están generando.

## Qué falta para que esto deje de ser una propuesta

1. Definir el entorno de producción real (proveedor de hosting, si hay acceso a cron jobs o si requiere un servicio externo de backup gestionado).
2. Aprobar la frecuencia/retención propuesta o ajustarla según la política de la Cámara.
3. Configurar el script/cron real y probarlo con una restauración real, no solo documentarlo.

Ninguno de estos tres pasos se ejecuta en esta sesión — están fuera del alcance de lo que se puede decidir sin el cliente, y coherente con la instrucción explícita de "no afirmar que existen backups reales si todavía no están configurados".
