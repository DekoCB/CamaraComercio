# Arquitectura

Ver la justificación de cada decisión en [`PROJECT_ANALYSIS.md`](PROJECT_ANALYSIS.md) (secciones 1–9 para el diseño original, sección 10 para el pivote a Laravel). Este documento describe cómo está organizada la aplicación **tal como quedó tras la migración a Laravel 12**.

## Stack

- **Framework:** Laravel 12 (PHP 8.2)
- **Base de datos:** MySQL/MariaDB vía Eloquent
- **Vistas:** Blade, server-rendered (sin SPA)
- **Frontend:** Bootstrap 5 + Bootstrap Icons servidos localmente (`public/assets/vendor/`, sin CDN) + JS vanilla puntual
- **Autenticación:** guard `web` nativo de Laravel (`Auth::attempt`), broker de recuperación de contraseña nativo (`Password::sendResetLink`/`Password::reset`)
- **Autorización:** `Gate::before()` + tablas `role_permissions`/`role_modules` (ver más abajo)
- **Testing:** PHPUnit vía `php artisan test`, Feature tests con `RefreshDatabase` sobre SQLite en memoria

## Ciclo de vida de una request

```
Navegador
  │
  ▼
public/index.php  (front controller de Laravel)
  │
  ▼
routes/web.php
  │  - agrupa rutas bajo middleware 'guest' (login, recuperar contraseña)
  │  - agrupa rutas bajo middleware 'auth' (todo lo demás)
  │  - sub-grupos bajo 'can:codigo.permiso' para administración/asociados
  ▼
Middleware (nativos de Laravel: auth, guest, can:*, VerifyCsrfToken, ShareErrorsFromSession...)
  ▼
Controller (app/Http/Controllers/**)
  │  - recibe un Form Request tipado (valida antes de ejecutar el método)
  │  - llama a modelos Eloquent directamente (no hay capa de repositorios
  │    separada: para el tamaño de este dominio, Eloquent como acceso a
  │    datos + validación en Form Requests es la separación de
  │    responsabilidades idiomática en Laravel)
  │  - registra auditoría vía App\Models\AuditLog::record(...)
  ▼
Eloquent Models (app/Models/**)
  │  - relaciones (belongsTo, belongsToMany, hasMany)
  │  - casts (is_active → boolean, metadata → array, etc.)
  ▼
MySQL/MariaDB
```

La respuesta se construye devolviendo una vista Blade (`return view('associates.index', [...])`) o una redirección (`redirect()->route(...)`), ambos mecanismos nativos de Laravel.

## Autorización (RBAC)

No se usa un paquete de terceros (p. ej. `spatie/laravel-permission`) porque el modelo de datos ya definido en Sprint 1 incluye un concepto que esos paquetes no cubren de fábrica: **módulos** (qué aparece en el menú lateral) como algo distinto de **permisos** (qué acción puede ejecutar un rol). En su lugar:

- `app/Providers/AppServiceProvider.php` registra `Gate::before(fn (User $user, string $ability) => ...)`, que resuelve **cualquier** nombre de habilidad contra `role_permissions` sin necesidad de declarar cada permiso con `Gate::define()`. Esto es intencional: un administrador puede crear permisos nuevos desde la UI (HU-23) sin que eso requiera un despliegue de código.
- Las rutas protegidas usan el middleware nativo `can:codigo.permiso` (`Illuminate\Auth\Middleware\Authorize`, ya viene registrado como alias `can` en Laravel — no se escribió middleware propio).
- Los Form Requests (`AssociateRequest`, `UserStoreRequest`, etc.) repiten la verificación en su método `authorize()` como defensa en profundidad.
- La visibilidad de módulos en el sidebar usa una directiva Blade propia, `@module('code') ... @endmodule`, respaldada por `role_modules` — ver `resources/views/layouts/app.blade.php`.
- `User::permissionCodes()` y `User::moduleCodes()` leen la colección de relación **ya cargada** (`$this->role->permissions`, no `$this->role->permissions()`) para que `Gate::before()` no dispare una consulta nueva en cada chequeo dentro de la misma request.

**Ningún permiso se verifica solo ocultando un botón o un enlace del menú.** `tests/Feature/Admin/RbacTest.php` verifica explícitamente que pedir una URL de administración por HTTP directo, con un usuario sin el permiso correspondiente, devuelve **403** real.

## Por qué no hay una capa de Domain/Application separada

La versión pre-Laravel de este proyecto (ver sección 1–9 de `PROJECT_ANALYSIS.md`) sí tenía capas `Domain/Application/Infrastructure` explícitas, apropiadas para un framework hecho a mano. Bajo Laravel, introducir esas mismas capas por encima de Eloquent sería duplicar responsabilidades que el framework ya resuelve bien (validación vía Form Requests, reglas de negocio simples vía scopes/accessors de Eloquent).

Esto se puso a prueba en Sprint 2: la generación masiva de facturas (HU-06) y el registro de pagos (HU-08/HU-09) sí tenían lógica demasiado compleja para vivir en un controlador o en el modelo — manejo de errores por ítem en un lote, bloqueo transaccional para evitar condiciones de carrera. En vez de reintroducir capas Domain/Application completas, esa lógica se extrajo a clases de servicio puntuales en `app/Services/` (`InvoiceGenerationService`, `PaymentService`), inyectadas en el controlador correspondiente. La máquina de estados de la factura (`PENDIENTE`/`PARCIAL`/`PAGADA`/`VENCIDA`) sí quedó como métodos del modelo `Invoice` (`effectiveStatus()`, `isOverdue()`, scope `overdue()`) porque es lectura derivada de sus propias columnas, no un caso de uso con pasos ni efectos secundarios — ver `docs/DATA_MODEL.md` para el razonamiento de por qué `VENCIDA` nunca se persiste. Este es el patrón a seguir en los sprints siguientes: Eloquent primero, un servicio dedicado solo cuando la operación tiene pasos/transacciones/manejo de errores que un modelo o un controlador no deberían cargar.
