# Protección de datos — Sistema de Facturación y Cobranzas

No declara cumplimiento legal de ninguna normativa — documenta qué datos personales maneja el sistema, quién puede acceder a ellos, y qué protecciones técnicas ya existen. Cualquier requisito legal concreto (Ley N.° 29733, Ley de Protección de Datos Personales del Perú, u otra) se marca `REQUIERE VALIDACIÓN LEGAL` y no se implementa por inferencia.

## 1. Qué datos personales maneja el sistema

| Dato | Entidad | Naturaleza | Obligatorio |
|---|---|---|---|
| Nombre | `associates.name`, `users.name` | Identificación | Sí (ambos) |
| Correo electrónico | `associates.email`, `users.email` | Contacto / credencial de acceso (solo `users`) | No en `associates`, sí en `users` |
| Teléfono de contacto | `associates.contact_phone` | Contacto | No |
| Empresa | `associates.company` | Dato comercial, no estrictamente personal | No |
| Contraseña (hash) | `users.password` | Credencial — nunca en texto plano | Sí |
| Foto de perfil | `users.avatar_path` | Imagen personal, autoservicio | No |
| Historial de pagos | `payments.*` | Dato financiero — quién pagó, cuánto, cuándo, quién lo registró | — |
| Historial de facturación | `invoices.*` | Dato financiero | — |

No se maneja: DNI/RUC (no existe el campo hoy — ver `docs/OPEN_BUSINESS_DECISIONS.md` pregunta 12), dirección física, datos bancarios, datos de salud, ni ninguna otra categoría de dato sensible según la legislación peruana.

## 2. Quién puede acceder

El acceso se rige íntegramente por el sistema de permisos ya implementado (`docs/ARCHITECTURE.md`, sección Autorización). No existe ningún acceso que se salte esa capa — no hay endpoints públicos que expongan datos de asociados, facturas o pagos.

| Rol de desarrollo | Ve datos de asociados | Ve facturación | Ve pagos | Exporta reportes |
|---|---|---|---|---|
| Administrador | Sí | Sí | Sí | Sí |
| Encargado de Cobranzas | Sí | Sí | Sí | Sí |

Ambos roles de desarrollo hoy tienen acceso a todo — es una configuración de datos (asignación de permisos a un rol), no una limitación de la aplicación. Si la Cámara necesita un rol con visibilidad restringida (ej. alguien que vea cartera pero no el detalle de pagos individuales), el sistema de permisos granulares ya lo soporta sin cambios de código — solo requiere crear un rol nuevo con el subconjunto de permisos correspondiente.

**Cada usuario, además, puede ver y editar su propio perfil** (nombre, correo, foto, contraseña) sin necesidad de ningún permiso adicional — es autoservicio, ver `docs/PROJECT_ANALYSIS.md` sección 10.19.

## 3. Cómo se protegen los datos

- **En tránsito:** depende de que el entorno de producción tenga HTTPS configurado — no está garantizado en el entorno de desarrollo local (XAMPP sin certificado). Ver `docs/DEPLOYMENT.md`.
- **En reposo — contraseñas:** bcrypt (`BCRYPT_ROUNDS=12`), nunca texto plano, nunca expuestas en ninguna respuesta (`$hidden` en el modelo `User`).
- **En reposo — resto de los datos:** sin cifrado a nivel de columna (nombre, correo, teléfono se almacenan en texto plano en la base de datos) — es el estándar para este tipo de dato en la mayoría de sistemas administrativos; cifrar cada columna sería una decisión de mayor costo operativo que no se implementa sin que se pida explícitamente.
- **Sesión:** cookie `HttpOnly` (`config/session.php`, `http_only: true` — no accesible desde JavaScript, mitiga robo de sesión vía XSS), `SameSite=lax`. `Secure` (solo por HTTPS) no está forzado explícitamente en `.env.example` — pendiente de setear en producción, ver `docs/DEPLOYMENT.md`.
- **Contra acceso no autorizado:** RBAC server-side verificado en cada request (ver sección 2).
- **Contra inyección/XSS/CSRF:** ver `docs/REQUIREMENTS_GAP_ANALYSIS.md` sección 6 — las tres protecciones están implementadas y verificadas.

## 4. Qué información aparece en logs

`audit_logs` (la única tabla de logging de negocio) registra usuario, acción, tipo y ID de entidad, resultado, y un `metadata` JSON acotado — verificado por inspección directa de cada llamado a `AuditLog::record()` en el código: **nunca** contiene contraseñas, tokens de sesión, ni el contenido completo de un registro (solo IDs y, para pagos, el monto). Ejemplo real: `payment.register` guarda `{invoice_id, amount}`, nunca el nombre del asociado ni datos de contacto.

`storage/logs/laravel.log` (el log técnico de Laravel) puede contener trazas de excepciones no controladas — en `APP_DEBUG=true` (desarrollo) estas trazas también se muestran en pantalla al usuario; **debe** ser `false` en producción (ver `docs/REQUIREMENTS_GAP_ANALYSIS.md` DEP-01 y `docs/DEPLOYMENT.md`) para no filtrar detalles internos ni, potencialmente, fragmentos de datos de request a un usuario final.

## 5. Qué información aparece en exports

Los reportes exportables (Excel/PDF, HU-15) incluyen exclusivamente datos agregados o por asociado que el rol ya tiene permiso de ver en pantalla (`reports.view`) — exportar requiere además `reports.export`. No se exportan contraseñas ni ningún dato de otros usuarios del sistema (los exports son sobre asociados/facturas/pagos, nunca sobre la tabla `users`).

## 6. Política de backups

Ver `docs/BACKUP_AND_RECOVERY.md` — no existen backups configurados hoy; ese documento es una propuesta de arquitectura, no una descripción de infraestructura real.

## 7. Retención de información

**No definida.** No existe ningún proceso de purga ni límite de conservación — todo se conserva indefinidamente, coherente con la política de "sin borrado físico" del resto del sistema (ver `docs/DATA_MODEL.md`).

`REQUIERE VALIDACIÓN LEGAL`: si aplica alguna normativa peruana de conservación de comprobantes contables/tributarios (que típicamente exige un **mínimo** de años de conservación, no un máximo — lo cual coincide naturalmente con la política actual de "nunca borrar"), o si la Ley de Protección de Datos Personales exige ofrecer a un asociado el derecho de solicitar la eliminación de sus datos personales (lo cual, si se aprobara, chocaría con la política de "nunca borrado físico" y requeriría una decisión explícita de cómo reconciliar ambos: ¿anonimización en vez de borrado?).

## 8. Declaración de cumplimiento

Este sistema implementa buenas prácticas técnicas de seguridad (hash de contraseñas, autorización server-side, protección CSRF/XSS/SQLi, auditoría de operaciones críticas). **Eso no equivale a una declaración de cumplimiento con la Ley N.° 29733 ni con ninguna otra normativa de protección de datos peruana** — el cumplimiento legal requiere, como mínimo, confirmar los puntos marcados `REQUIERE VALIDACIÓN LEGAL` en este documento con asesoría legal calificada antes de tratarlo como resuelto.
