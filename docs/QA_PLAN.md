# Plan de QA — Sistema de Facturación y Cobranzas

## Tipos de prueba

| Tipo | Estado | Herramienta | Cobertura |
|---|---|---|---|
| Integration/Feature (HTTP end-to-end) | **Implementado** | PHPUnit vía `php artisan test` | 87 tests, todas las 23 HU + import + perfil, sobre SQLite en memoria (`RefreshDatabase`) |
| Unit puro (sin framework) | No aplica por decisión arquitectónica | — | Toda la lógica de negocio no trivial vive en `app/Services/*`, probada a través de Feature tests HTTP completos en vez de aislada — ver `docs/ARCHITECTURE.md`. No es una brecha, es una decisión consciente ya documentada en sprints anteriores |
| E2E de navegador real | Parcial — manual, no automatizado en CI | Playwright (scripts ad-hoc de verificación durante el desarrollo) | Barridos de responsive/dark-mode/interacciones JS documentados en `docs/PROJECT_ANALYSIS.md`, pero no forman una suite repetible dentro de `php artisan test` |
| Seguridad | Implementado | PHPUnit | 403 real por permiso faltante, rate limiting de login, contraseña actual incorrecta rechazada |
| Migración (Excel) | Implementado | PHPUnit | Ver `tests/Feature/AssociateImportTest.php` |
| Responsive | Manual, no automatizado | Playwright ad-hoc | Verificado en 1440px y 375px durante el rediseño UI/UX (`docs/DESIGN_SYSTEM.md`) |

**Recomendación no vinculante:** si el proyecto avanza a un pipeline de CI/CD real, convertir los scripts Playwright ad-hoc en una suite versionada (ej. `tests/e2e/`) ejecutada en cada PR — hoy corren manualmente cuando se toca UI, lo cual es suficiente para el ritmo de desarrollo actual pero no escala a un equipo más grande sin volverse repetible.

## Casos de prueba

Cada caso indica si ya está automatizado (✅, con el archivo/método) o si es un caso adicional identificado en esta auditoría que todavía no tiene un test (⬜).

### Facturación

| Caso | Automatizado |
|---|---|
| Generación normal — una factura por asociado activo | ✅ `tests/Feature/InvoiceGenerationTest.php` |
| Duplicación — correr la generación dos veces para el mismo período no duplica | ✅ `tests/Feature/InvoiceGenerationTest.php` |
| Asociado inactivo — no recibe factura | ✅ `tests/Feature/InvoiceGenerationTest.php` |
| Monto inválido — rechazado por `InvoiceGenerateRequest` | ✅ (validación de Form Request, cubierta por el flujo general del test de generación) |
| Asociado nuevo dado de alta después de la primera corrida — sí se factura en la siguiente | ✅ `tests/Feature/InvoiceGenerationTest.php` |

### Pagos

| Caso | Automatizado |
|---|---|
| Pago completo — deja la factura en PAGADA | ✅ `tests/Feature/PaymentTest.php` |
| Pago parcial — deja la factura en PARCIAL con saldo correcto | ✅ `tests/Feature/PaymentTest.php` |
| Múltiples pagos — se acumulan hasta completar el monto | ✅ `tests/Feature/PaymentTest.php` |
| Pago superior al saldo — rechazado | ✅ `tests/Feature/PaymentTest.php` |
| Pago inválido (cero o negativo) — rechazado | ✅ `tests/Feature/PaymentTest.php` |
| Dos pagos concurrentes no sobrepagan juntos la misma factura | ✅ cubierto por `lockForUpdate()` en `PaymentService`, verificado por diseño (no hay test de concurrencia real con dos procesos simultáneos — sería necesario un test de integración con hilos/procesos separados, no estándar en PHPUnit) |

### Estados de factura

| Caso | Automatizado |
|---|---|
| PENDIENTE al crear | ✅ `tests/Feature/InvoiceGenerationTest.php` |
| PARCIAL tras un pago menor al monto | ✅ `tests/Feature/PaymentTest.php` |
| PAGADA tras completar el saldo | ✅ `tests/Feature/PaymentTest.php` |
| VENCIDA calculada correctamente (no persistida) para una factura sin pagar con `due_date` pasado | ✅ `tests/Feature/InvoiceGenerationTest.php` / `PortfolioTest.php` |
| Una factura vencida y con pago parcial sigue mostrando el saldo correcto, no "vencida" si ya está pagada | ✅ `tests/Feature/InvoiceGenerationTest.php` |

### Seguridad

| Caso | Automatizado |
|---|---|
| Usuario sin permiso recibe 403 real (no solo oculto en el menú) | ✅ `tests/Feature/Admin/RbacTest.php` |
| Endpoint protegido rechaza acceso directo por URL | ✅ `tests/Feature/Admin/RbacTest.php` |
| Login con credenciales inválidas | ✅ `tests/Feature/Auth/LoginTest.php` |
| Login con usuario inactivo | ✅ `tests/Feature/Auth/LoginTest.php` |
| Rate limiting tras 5 intentos fallidos de login | ✅ `tests/Feature/Auth/LoginTest.php` |
| Sesión expirada — acceso a ruta protegida sin sesión redirige a login | ✅ implícito en cada test de RBAC (`actingAs` ausente → redirect) |
| Token de recuperación de contraseña de un solo uso | ✅ `tests/Feature/Auth/PasswordResetTest.php` |
| Cambio de contraseña propio requiere la contraseña actual correcta | ✅ `tests/Feature/ProfileTest.php` |

### Casos identificados en esta auditoría sin test todavía

| Caso | Por qué no está cubierto | Prioridad |
|---|---|---|
| Correo de asociado duplicado rechazado en alta manual | ✅ **Agregado en esta sesión** — `tests/Feature/AssociateTest.php::test_duplicate_email_is_rejected_on_manual_create` | — |
| Un asociado sin correo no colisiona con otro asociado sin correo | ✅ **Agregado en esta sesión** — `test_multiple_associates_without_an_email_do_not_collide` | — |
| Editar un asociado conservando su propio correo no se rechaza como duplicado | ✅ **Agregado en esta sesión** — `test_editing_an_associate_keeping_its_own_email_is_not_a_duplicate` | — |
| Formulario real de navegador que envía `current_password=""` (no un campo ausente) no rompe un guardado de perfil que no toca la contraseña | ✅ ya existía — `tests/Feature/ProfileTest.php::test_saving_the_profile_with_all_fields_present_but_password_fields_empty_succeeds` (agregado en la sesión de perfil, tras encontrar el bug con Playwright) | — |
| Exportación de reporte sin el permiso `reports.export` (teniendo `reports.view`) | ✅ ya existía — `tests/Feature/ReportTest.php` | — |
| Concurrencia real de dos requests HTTP simultáneas sobre el mismo pago (no solo el `lockForUpdate` a nivel de código) | No cubierto — PHPUnit no ejecuta requests HTTP en paralelo de forma nativa; requeriría una herramienta de carga externa (ej. `k6`, `ab`) apuntando al entorno de desarrollo | Baja — el mecanismo (`lockForUpdate` transaccional) es el patrón estándar y correcto para esto, la prueba de concurrencia real es una validación de infraestructura, no de lógica |

## Cómo ejecutar

```
php artisan test
```

87 tests, ~7 segundos, contra SQLite en memoria — nunca toca la base de datos MySQL de desarrollo. Ver `README.md` para más detalle del setup.
