# Datos de prueba para UAT — Sistema de Facturación y Cobranzas

Sección 19 del prompt de auditoría de 2026-08-19: nunca información real. Todo lo listado aquí es ficticio, generado por seeders versionados en `database/seeders/`.

## Cómo generarlos

```
php artisan migrate --seed
```

o, si la base de datos ya existe y solo falta este set de datos:

```
php artisan db:seed --class=UatDemoDataSeeder
```

Es **idempotente** (`updateOrCreate` con claves únicas) — correrlo más de una vez no duplica nada.

## Qué crea cada seeder

| Seeder | Contenido |
|---|---|
| `RolesPermissionsModulesSeeder` | 2 roles (Administrador, Encargado de Cobranzas), sus permisos y módulos, y los 2 usuarios de desarrollo (`admin@camaracomercio.test`, `cobranzas@camaracomercio.test`) — credenciales impresas al correr el seeder |
| `AssociateSeeder` | 5 asociados activos con nombre/empresa/contacto/correo ficticios |
| `UatDemoDataSeeder` | Agregado en esta auditoría — cubre los estados que `AssociateSeeder` por sí solo no generaba (ver "Antes de esta auditoría" abajo) |

## Qué agrega `UatDemoDataSeeder`

| Elemento | Detalle |
|---|---|
| 1 asociado **inactivo** | "Zapatería El Paso (inactivo)" — prueba en vivo de que `InvoiceGenerationService` nunca lo incluye en una corrida de facturación masiva |
| 1 factura **PENDIENTE** | Período actual, vencimiento a futuro, sin pagos |
| 1 factura **PARCIAL** | Período anterior, con un pago de S/ 100 sobre un monto de S/ 250 (saldo S/ 150) |
| 1 factura **PAGADA** | Dos períodos atrás, pago completo registrado |
| 1 factura **VENCIDA** (calculada) | Dos períodos atrás, sin pagos, `due_date` ya pasado — el campo `status` en base de datos sigue diciendo `PENDIENTE` (nunca se persiste `VENCIDA`, ver `docs/DATA_MODEL.md`), pero se lee como vencida en cualquier pantalla que use `Invoice::effectiveStatus()` |
| 2 pagos | Uno parcial (S/ 100), uno completo (S/ 250), ambos registrados vía `PaymentService::register()` — no se insertan directo en la tabla `payments`, así que `paid_total`/`status` de la factura quedan exactamente como si se hubieran registrado desde la UI |

## Antes de esta auditoría

`AssociateSeeder` por sí solo dejaba el sistema con asociados pero **cero facturas y cero pagos** — cualquier pantalla de facturación, pagos, cartera o reportes se veía completamente vacía tras un `migrate --seed` limpio, lo cual no era representativo para ejecutar `docs/UAT_PLAN.md` ni para verificar manualmente el dashboard. `UatDemoDataSeeder` cierra esa brecha sin tocar `AssociateSeeder` (que sigue siendo válido tal como estaba).

## Credenciales de desarrollo

Ver `README.md` — no se repiten aquí para no tener dos fuentes de verdad sobre las mismas contraseñas.
