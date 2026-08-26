# Deployment — Sistema de Facturación y Cobranzas

Consolida y formaliza la checklist de producción que ya existía en `README.md` (sección "Antes de desplegar a producción"), sin duplicarla — este documento la referencia y la amplía con el resto de lo pedido por el prompt de auditoría (entornos, rollback, smoke tests). **No se ejecuta ningún despliegue destructivo desde esta auditoría** — todo lo que sigue es documentación de un proceso, no una acción tomada.

## 1. Entornos

| Entorno | Estado | Detalle |
|---|---|---|
| Desarrollo | **Existe y es el único activo hoy** | XAMPP local (Apache + MySQL/MariaDB), puerto Apache dedicado (actualmente 8001, configurable — ver `README.md` paso 5), `APP_ENV=local`, `APP_DEBUG=true` |
| Testing / UAT | **No existe como entorno separado** | Los 87 tests automatizados corren contra SQLite en memoria (nunca contra la base de datos MySQL de desarrollo); el UAT descrito en `docs/UAT_PLAN.md` está pensado para ejecutarse sobre el mismo entorno de desarrollo con los datos de `docs/UAT_SEED_DATA.md`, no sobre un servidor de staging separado — no se definió uno |
| Producción | **No definido** | No hay proveedor de hosting, dominio, ni certificado HTTPS confirmado — todo lo de esta sección es una propuesta de checklist para cuando se defina |

`REQUIERE VALIDACIÓN DEL CLIENTE`: dónde se aloja producción (hosting compartido, VPS, proveedor cloud) determina varios de los puntos siguientes (si hay acceso a cron para backups, si hay SSH para desplegar, etc.).

## 2. Variables de entorno

Ver `README.md` sección "Variables de entorno (`.env`)" para la tabla completa. Las que **deben** cambiar entre desarrollo y producción:

| Variable | Desarrollo | Producción |
|---|---|---|
| `APP_ENV` | `local` | `production` |
| `APP_DEBUG` | `true` | **`false`** — evita filtrar trazas de error a usuarios finales (ver `docs/DATA_PROTECTION.md` sección 4) |
| `APP_URL` | `http://localhost:800X` | Dominio real, con `https://` |
| `SESSION_SECURE_COOKIE` | sin setear | **`true`** — no está seteado explícitamente en `.env.example` hoy, ver `docs/REQUIREMENTS_GAP_ANALYSIS.md` SEC-02 |
| `MAIL_MAILER` | `log` | Proveedor SMTP real — decisión de negocio pendiente desde Sprint 1, nunca definida |
| `DB_*` | XAMPP local (`root` sin contraseña) | Credenciales reales de producción, nunca reutilizar las de desarrollo |
| `APP_KEY` | generada localmente | **Generar una nueva para producción** (`php artisan key:generate`) — nunca reutilizar la de desarrollo, invalidaría cualquier dato cifrado/firmado con la clave equivocada si se mezclan |

## 3. Base de datos

- Migrar con `php artisan migrate --force` (el flag `--force` es obligatorio en `APP_ENV=production`, Laravel bloquea migraciones interactivas sin confirmación en ese entorno por diseño).
- **Respaldo obligatorio antes de cada migración sobre datos reales** — ver `docs/BACKUP_AND_RECOVERY.md`. Ya señalado como requisito en `README.md` desde Sprint 4, dado el antecedente de corrupción de MariaDB en este mismo entorno XAMPP (`docs/PROJECT_ANALYSIS.md` sección 2) — motivo adicional para no asumir que el servidor de destino esté libre del mismo riesgo sin verificarlo primero.
- Nunca correr `migrate:fresh` (que borra todas las tablas) contra producción bajo ninguna circunstancia.

## 4. Build

No hay paso de build de frontend en el sentido tradicional (sin Webpack/Vite en producción, ver `docs/TECHNICAL_SPECIFICATION.md` sección 3) — los assets estáticos (`public/assets/`) ya están en el repositorio, vendorizados. El único "build" real es de dependencias PHP:

```
composer install --no-dev --optimize-autoloader
```

`--no-dev` excluye PHPUnit y demás dependencias de desarrollo del entorno de producción — no están instaladas hoy con ese flag en el XAMPP local (correcto para desarrollo, donde sí se necesitan para correr tests).

## 5. Deploy

Checklist post-despliegue (ya en `README.md`, repetido aquí en el orden de ejecución):

1. `composer install --no-dev --optimize-autoloader`
2. Configurar `.env` de producción (ver sección 2).
3. `php artisan migrate --force` (con respaldo previo, ver sección 3).
4. `php artisan storage:link` (necesario para que las fotos de perfil se sirvan — no es idempotente-seguro correrlo dos veces si el link ya existe con otro destino, pero es seguro si nunca se corrió antes).
5. `php artisan config:cache && php artisan route:cache && php artisan view:cache` — cachear configuración/rutas/vistas compiladas, reduce el trabajo por request en producción.
6. Verificar permisos de escritura en `storage/` y `bootstrap/cache/` para el usuario del proceso PHP.
7. Smoke test (ver sección 7).

## 6. Rollback

No hay un mecanismo automatizado de rollback — es manual:

1. Si el problema es solo de código (no de esquema de base de datos): revertir al commit/release anterior y repetir el deploy desde el paso 1 de la sección 5.
2. Si el deploy incluyó una migración de base de datos: **restaurar desde el backup tomado antes de migrar** (sección 3) es más seguro que confiar en el método `down()` de la migración — Laravel lo soporta (`php artisan migrate:rollback`), pero un `down()` mal probado puede perder datos que se escribieron después de aplicar el `up()`. El backup previo es la red de seguridad real.
3. Tras cualquier rollback, correr `php artisan config:clear && php artisan route:clear && php artisan view:clear` antes de volver a cachear, para no servir configuración/rutas cacheadas de la versión que se acaba de revertir.

## 7. Health checks

Laravel 12 registra automáticamente `/up` (`bootstrap/app.php`, `health: '/up'`) — responde 200 si la aplicación arrancó correctamente (no verifica la conexión a base de datos por defecto, solo que el framework esté vivo). Usarlo como health check básico de cualquier balanceador de carga o supervisor de proceso que se configure en producción.

## 8. Smoke tests

Tras cada deploy, antes de considerarlo exitoso:

1. `GET /up` → 200.
2. Login con un usuario de prueba → llega al dashboard.
3. Dashboard carga sin error (confirma conexión a base de datos, no solo que el framework arrancó).
4. Abrir el listado de asociados → se ve al menos un registro (o el empty state correcto si la base está vacía).
5. `php artisan test` **no** se corre contra producción — es exclusivamente para SQLite en memoria en desarrollo/CI. El smoke test de producción es manual/HTTP, sobre datos reales.

## 9. Lo que falta para que esto sea ejecutable, no solo documentado

1. Definir dónde se aloja producción (sección 1).
2. Configurar el `.env` real de producción (sección 2) — incluye la decisión pendiente del proveedor SMTP.
3. Configurar backups reales (`docs/BACKUP_AND_RECOVERY.md`) — sin esto, el paso 3 de esta sección (respaldo antes de migrar) no tiene con qué cumplirse.
4. Ejecutar el primer deploy real y correr los smoke tests de la sección 8 contra el resultado.

Ninguno de estos cuatro puntos se ejecuta en esta sesión.
