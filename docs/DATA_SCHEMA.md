# Data Schema — Sistema de Facturación y Cobranzas

Documenta todas las entidades del sistema tal como existen hoy en `database/migrations/` y `app/Models/` — es un complemento operativo de `docs/DATA_MODEL.md` (que explica el *por qué* de cada decisión) enfocado en listar cada columna, tipo, restricción y cardinalidad de forma exhaustiva, como pide el punto 4 del prompt de auditoría. Ningún campo de esta lista es especulativo — todos están tomados directamente de las migraciones ejecutadas.

## Diagrama de relaciones

```
roles ──< users
roles >──< permissions   (pivote: role_permissions)
roles >──< modules       (pivote: role_modules)

associates ──< invoices
invoices ──< payments
users ──< invoices.created_by      (nullable)
users ──< payments.registered_by   (obligatorio)
users ──< audit_logs.user_id       (nullable)
```

`──<` = uno a muchos. `>──<` = muchos a muchos.

---

## Asociado (`associates`)

| Columna | Tipo | Restricciones | Notas |
|---|---|---|---|
| `id` | bigint | PK, autoincrement | Identificador técnico interno |
| `name` | string(150) | `NOT NULL`, índice simple | Único campo obligatorio del alta |
| `company` | string(150) | nullable, índice simple | |
| `contact_phone` | string(40) | nullable | Sin formato validado (texto libre) |
| `email` | string(190) | nullable | **Sin `UNIQUE`** — ver `docs/OPEN_BUSINESS_DECISIONS.md` pregunta 12 |
| `is_active` | boolean | `NOT NULL`, default `true` | Controla si recibe facturación masiva (HU-06) |
| `created_at` / `updated_at` | timestamp | Laravel estándar | "Fecha de alta" y "fecha de actualización" pedidas por el punto 4 del prompt |

**Fecha de baja:** no existe una columna separada — la "baja" de un asociado es `is_active = false`, no un timestamp de baja ni un borrado físico. Si se necesita saber *cuándo* se dio de baja (no solo que está inactivo), sería una columna nueva (`deactivated_at`), pendiente de aprobación.

**Cardinalidad:** un asociado tiene muchas facturas (`Associate::invoices(): HasMany`). Cero o muchas — un asociado recién creado no tiene facturas hasta la primera corrida de facturación masiva que lo incluya.

**Identificador único de negocio:** ninguno definido — ver pregunta 12.

---

## Factura (`invoices`)

| Columna | Tipo | Restricciones | Notas |
|---|---|---|---|
| `id` | bigint | PK, autoincrement | |
| `associate_id` | bigint FK → `associates.id` | `NOT NULL`, `ON DELETE RESTRICT` | No se puede borrar un asociado con facturas |
| `period` | char(7) | `NOT NULL` | Formato `YYYY-MM`, comentario de columna lo documenta en la propia BD |
| `amount` | decimal(12,2) | `NOT NULL`, `CHECK (amount > 0)` (solo MySQL) | Monto original de la factura, nunca cambia tras crearse |
| `paid_total` | decimal(12,2) | `NOT NULL`, default `0`, `CHECK (paid_total >= 0)` (solo MySQL) | **Denormalizado**: `SUM(payments.amount)` para esa factura, mantenido transaccionalmente por `PaymentService` — nunca se recalcula on-the-fly desde `payments` en cada lectura |
| `issue_date` | date | `NOT NULL` | |
| `due_date` | date | `NOT NULL` | Fecha exacta de vencimiento — sin días de gracia (ver pregunta 10) |
| `status` | enum | `NOT NULL`, default `PENDIENTE` | Valores: `PENDIENTE`, `PARCIAL`, `PAGADA`, `VENCIDA` — **`VENCIDA` nunca se escribe aquí**, se calcula en tiempo de lectura (ver `Invoice::effectiveStatus()`) |
| `created_by` | bigint FK → `users.id` | nullable, `ON DELETE SET NULL` | Quién ejecutó la generación masiva que creó esta factura |
| `created_at` / `updated_at` | timestamp | Laravel estándar | Fechas de auditoría pedidas por el punto 4 |

**Restricciones compuestas:**
- `UNIQUE(associate_id, period)` — un asociado no puede tener dos facturas para el mismo período.
- Índices simples en `period`, `status`, `due_date` (soportan los filtros de HU-07 y los reportes de HU-13/HU-14).

**Cardinalidad:**
- Una factura pertenece a exactamente un asociado (`Invoice::associate(): BelongsTo`).
- Una factura tiene cero o muchos pagos (`Invoice::payments(): HasMany`) — cero mientras está PENDIENTE, uno o más a medida que se cobra.
- Una factura pertenece opcionalmente a un usuario creador (`Invoice::creator(): BelongsTo`, nullable).

**No editable, no anulable, no eliminable** una vez creada — ver preguntas 5, 6, 7 de `docs/OPEN_BUSINESS_DECISIONS.md`.

---

## Pago (`payments`)

| Columna | Tipo | Restricciones | Notas |
|---|---|---|---|
| `id` | bigint | PK, autoincrement | |
| `invoice_id` | bigint FK → `invoices.id` | `NOT NULL`, `ON DELETE RESTRICT` | No se puede borrar una factura con pagos (tampoco se puede borrar ninguna factura, ver arriba) |
| `amount` | decimal(12,2) | `NOT NULL`, `CHECK (amount > 0)` (solo MySQL) | Nunca puede exceder el saldo pendiente al momento de registrarse (verificado en `PaymentService`, no solo en BD) |
| `paid_at` | datetime | `NOT NULL` | Fecha del pago tal como la ingresa quien lo registra — puede diferir de `created_at` |
| `registered_by` | bigint FK → `users.id` | `NOT NULL`, `ON DELETE RESTRICT` | **No se puede borrar un usuario que registró al menos un pago** — es el límite práctico real para cualquier borrado físico de usuarios en todo el sistema |
| `notes` | string(255) | nullable | Observación libre |
| `created_at` / `updated_at` | timestamp | Laravel estándar | |

**Cardinalidad:** un pago pertenece a exactamente una factura (`Payment::invoice(): BelongsTo`) y a exactamente un usuario que lo registró (`Payment::registeredBy(): BelongsTo`). No existe la cardinalidad inversa "una factura tiene un pago" — es 1 a N desde la factura.

**No editable ni eliminable una vez registrado** — ver pregunta 8 (`docs/OPEN_BUSINESS_DECISIONS.md`), es el hallazgo de mayor impacto operativo de la auditoría.

---

## Usuario (`users`)

| Columna | Tipo | Restricciones | Notas |
|---|---|---|---|
| `id` | bigint | PK, autoincrement | |
| `name` | string | `NOT NULL` | |
| `email` | string | `NOT NULL`, `UNIQUE` | A diferencia de `associates.email`, este **sí** es único — es la credencial de acceso |
| `email_verified_at` | timestamp | nullable | Campo nativo de Laravel, no usado activamente (no hay flujo de verificación de correo en las 23 HU) |
| `password` | string | `NOT NULL` | Cast `'hashed'` — bcrypt automático vía `Hash::make`, nunca se expone (`$hidden`) |
| `role_id` | bigint FK → `roles.id` | `NOT NULL`, `ON DELETE RESTRICT` | No se puede borrar un rol que todavía tiene usuarios |
| `is_active` | boolean | `NOT NULL`, default `true` | Un usuario inactivo no puede autenticarse (verificado en `tests/Feature/Auth/LoginTest.php`) |
| `avatar_path` | string | nullable | Ruta relativa dentro del disco `public` (`storage/app/public/avatars`) |
| `remember_token` | string | nullable | Nativo de Laravel ("recordarme" en login) |
| `created_at` / `updated_at` | timestamp | Laravel estándar | |

**Cardinalidad:** un usuario pertenece a exactamente un rol (`User::role(): BelongsTo`) — no hay roles múltiples por usuario en este sistema.

**Credenciales:** correo + contraseña (hash bcrypt). No hay autenticación de dos factores, ni SSO, ni OAuth — no estaba en las 23 HU.

---

## Rol (`roles`)

| Columna | Tipo | Restricciones |
|---|---|---|
| `id` | bigint | PK |
| `name` | string | `NOT NULL` |
| `description` | text | nullable |

**Cardinalidad:** un rol tiene muchos usuarios (`Role::users(): HasMany`); un rol tiene muchos permisos y muchos módulos, ambos muchos-a-muchos vía tablas pivote.

---

## Permiso (`permissions`)

| Columna | Tipo | Restricciones |
|---|---|---|
| `id` | bigint | PK |
| `code` | string | `NOT NULL` — identificador de negocio real, ej. `associates.manage`, `billing.generate`, `admin.users` |
| `description` | string | nullable |

**Cardinalidad:** muchos a muchos con `roles` vía `role_permissions` (pivote sin columnas propias más allá de las dos llaves foráneas).

Verificado en cada request vía `Gate::before()` + middleware `can:codigo` — nunca solo ocultando botones en el frontend (ver `docs/ARCHITECTURE.md`).

---

## Módulo (`modules`)

| Columna | Tipo | Restricciones | Notas |
|---|---|---|---|
| `id` | bigint | PK | |
| `code` | string | `NOT NULL`, `UNIQUE`, patrón `[a-z0-9_-]+` validado en `ModuleRequest` | |
| `name` | string | `NOT NULL` | Texto mostrado en el menú lateral |
| `icon` | string | nullable | Nombre de ícono Lucide |
| `route` | string | nullable | |
| `sort_order` | integer | default `0` | Orden en el sidebar |
| `is_active` | boolean | `NOT NULL`, default `true` | Un módulo inactivo desaparece del menú para **todos** los roles que lo tengan asignado (verificado en `tests/Feature/Admin/AdminManagementTest.php`) |

**Cardinalidad:** muchos a muchos con `roles` vía `role_modules`. Controla *visibilidad de menú*, distinto de los permisos (que controlan *acciones*) — un rol puede ver la opción "Facturación" en el menú (módulo) pero no poder generar facturación masiva si le falta el permiso `billing.generate`.

---

## Auditoría (`audit_logs`)

| Columna | Tipo | Restricciones | Notas |
|---|---|---|---|
| `id` | bigint | PK | |
| `user_id` | bigint FK → `users.id` | nullable, `ON DELETE SET NULL` | El log sobrevive aunque se borre el usuario (aunque en la práctica nunca se borran usuarios, ver arriba) |
| `action` | string(80) | `NOT NULL` | Ej. `payment.register`, `invoice.generate_batch` — catálogo completo en `docs/BUSINESS_RULES.md` |
| `entity_type` | string(60) | `NOT NULL` | Ej. `associate`, `invoice`, `payment`, `user`, `role`, `module` |
| `entity_id` | string(60) | nullable | String, no FK real — permite loggear acciones sin una entidad puntual (ej. `associate.import` no referencia una fila específica) |
| `result` | string(20) | default `success` | `success` o `failure` — usado hoy por `auth.login` para loguear intentos fallidos |
| `metadata` | json | nullable | Contexto adicional sin datos sensibles (verificado: nunca contraseñas ni tokens) |
| `created_at` | timestamp | `useCurrent()`, **sin `updated_at`** (`$timestamps = false` + columna manual) | Un log de auditoría nunca se edita, así que no necesita `updated_at` |

**Cardinalidad:** un log de auditoría pertenece opcionalmente a un usuario (nullable — cubre acciones del sistema sin un actor humano, aunque hoy todas las acciones registradas las dispara un usuario autenticado).

---

## Tablas nativas de Laravel (no de diseño propio)

- `password_reset_tokens` — PK `email`, `token` (hash), `created_at`. Gestionada por el `PasswordBroker` nativo, expira en 60 min (`config('auth.passwords.users.expire')`), un solo uso.
- `sessions` — backing store de `SESSION_DRIVER=database`.
- `cache`, `jobs` — infraestructura de Laravel, sin datos de negocio.

---

## Resumen de cardinalidades

| Relación | Tipo | Notas |
|---|---|---|
| Rol → Usuario | 1:N | Un rol, muchos usuarios |
| Rol ↔ Permiso | N:M | vía `role_permissions` |
| Rol ↔ Módulo | N:M | vía `role_modules` |
| Asociado → Factura | 1:N | Un asociado, muchas facturas (una por período como máximo) |
| Factura → Pago | 1:N | Una factura, muchos pagos (0 o más) |
| Usuario → Factura (`created_by`) | 1:N, opcional | |
| Usuario → Pago (`registered_by`) | 1:N, obligatorio | |
| Usuario → AuditLog | 1:N, opcional | |

Ninguna relación N:M existe entre las entidades de negocio (asociado/factura/pago) — todas las relaciones muchos-a-muchos del sistema pertenecen exclusivamente al subsistema de RBAC (rol↔permiso, rol↔módulo).
