# Manual de usuario — Sistema de Facturación y Cobranzas

Guía para el personal de la Cámara de Comercio que usará el sistema día a día. No requiere conocimientos técnicos — cualquier paso que mencione algo técnico está marcado para que se pueda omitir si no aplica a tu rol.

---

## 1. Ingresar al sistema

1. Abre el sistema en tu navegador, con la dirección que te indique el administrador.
2. Ingresa tu correo y contraseña.
3. Si quieres que el navegador recuerde tu sesión, marca "Recordarme".
4. Presiona "Iniciar sesión".

**¿Olvidaste tu contraseña?** Presiona el enlace "¿Olvidaste tu contraseña?" en la pantalla de inicio de sesión, ingresa tu correo, y sigue las instrucciones que recibirás. El enlace para restablecerla es válido por 60 minutos y solo se puede usar una vez.

**¿Tu cuenta no funciona?** Si tu cuenta fue desactivada por un administrador, no podrás ingresar aunque tu contraseña sea correcta — contacta al administrador del sistema.

---

## 2. El menú principal (sidebar)

A la izquierda de la pantalla verás el menú con las secciones a las que tienes acceso — no todos los usuarios ven las mismas opciones, depende de tu rol. Puedes colapsar el menú a solo íconos con el botón de flecha en la parte inferior, para tener más espacio en pantalla.

En la esquina superior derecha:
- El **círculo con tus iniciales** (o tu foto, si subiste una) abre un menú con "Mi perfil" y "Cerrar sesión".
- El ícono de **sol/luna** cambia entre tema claro y oscuro — tu elección se recuerda la próxima vez que entres.

---

## 3. Dashboard (pantalla principal)

Al entrar, ves un resumen general:

- **Asociados** — cuántos hay registrados en total.
- **Facturado** — cuánto se facturó en el período actual.
- **Cobrado** — cuánto se ha cobrado este mes, con una comparación contra el mes anterior si hay datos suficientes.
- **Pendiente** — cuánto falta cobrar en total, y cuántas facturas están vencidas.

Debajo, dos gráficos: cobranza mensual (facturado vs. cobrado en los últimos 6 meses) y distribución de la cartera por estado. Más abajo, accesos directos a las tareas más comunes (registrar un asociado, generar facturación, etc.), según lo que tu rol te permita hacer.

---

## 4. Mi perfil

Desde el menú del usuario (esquina superior derecha) → "Mi perfil", puedes:

- **Cambiar tu foto:** presiona "Cambiar foto", elige una imagen (JPG, PNG o WEBP, máximo 2 MB). Si ya tienes una foto y quieres volver a las iniciales, marca "Quitar foto actual".
- **Cambiar tu nombre o correo:** edítalos y guarda.
- **Cambiar tu contraseña:** completa "Contraseña actual", "Nueva contraseña" y "Confirmar nueva contraseña". Si no quieres cambiarla, deja esos tres campos en blanco — tu nombre y correo se pueden actualizar sin tocar la contraseña.

---

## 5. Asociados

### Ver la lista

Menú → Asociados. Puedes buscar por nombre, empresa, teléfono o correo con la barra de búsqueda.

### Registrar un asociado nuevo

1. Presiona "+ Nuevo asociado" (arriba a la derecha, o en el estado vacío si todavía no hay ninguno).
2. Completa el nombre (el único campo obligatorio) y, si los tienes, empresa, teléfono de contacto y correo.
3. Guarda. El formulario se abre sobre la pantalla actual — no necesitas navegar a otra página.

Si el correo que ingresas ya pertenece a otro asociado, el sistema te avisará y no dejará guardar hasta que lo corrijas.

### Editar un asociado

Presiona "Editar" en la fila del asociado. Puedes cambiar cualquier dato, incluido activarlo o desactivarlo con el checkbox "Asociado activo". **Un asociado inactivo no recibe facturación mensual** — es la única forma de "pausar" a un asociado sin borrar su historial.

### Importar varios asociados desde Excel

1. Menú → Asociados → "Importar desde Excel".
2. Sube tu archivo (debe tener al menos una columna "Nombre").
3. El sistema te muestra una **previsualización**: qué filas se van a importar y cuáles tienen algún error (correo inválido, correo duplicado, nombre vacío) — nada se guarda todavía en este paso.
4. Revisa la previsualización. Si algo no se ve bien, presiona "Cancelar" y nada se habrá creado.
5. Si todo está correcto, presiona "Confirmar importación". Solo las filas sin errores se crean como asociados nuevos.

---

## 6. Facturación

### Generar la facturación del mes

1. Menú → Facturación → "Generar facturación del mes".
2. Sigue los 4 pasos: elige el período (mes y año), el monto que se facturará a cada asociado, la fecha límite de pago, y finalmente confirma.
3. Antes de confirmar, verás un resumen: cuántas facturas se van a crear. Esta acción no se puede deshacer, así que revisa el resumen con cuidado.
4. El sistema crea una factura por cada asociado **activo** que todavía no tenga una para ese período — si ya la generaste antes para el mismo mes, no se duplica nada.

### Consultar facturas

Menú → Facturación. Puedes filtrar por asociado, período o estado (Pendiente, Parcial, Pagada, Vencida). Presiona "Ver" en cualquier fila para ver el detalle completo de esa factura, incluido su historial de pagos.

**Sobre las facturas:** una vez generada, una factura no se puede editar ni eliminar desde el sistema — si necesitas corregir un error, contacta al administrador (ver `docs/OPEN_BUSINESS_DECISIONS.md` para el estado de esta limitación).

---

## 7. Pagos

### Registrar un pago

Desde el detalle de una factura (Facturación → abrir una factura), en la sección "Registrar pago":

1. Ingresa el monto a pagar — el sistema te muestra en tiempo real cuál sería el nuevo saldo antes de guardar.
2. Elige la fecha del pago.
3. Agrega una observación si quieres (opcional).
4. Guarda.

Si el monto que ingresas es mayor al saldo pendiente, el sistema no te dejará guardar. Puedes registrar varios pagos parciales sobre la misma factura hasta completarla — cuando el saldo llega a S/ 0.00, la factura pasa automáticamente a "Pagada".

**Importante:** un pago, una vez registrado, no se puede corregir ni eliminar desde el sistema — revisa el monto y la factura correcta antes de guardar.

### Ver el historial de pagos

Menú → Pagos. Puedes filtrar por asociado o por rango de fechas.

---

## 8. Cartera (a quién le falta pagar)

Menú → Cartera. Dos vistas:

- **Cartera general** — cada asociado con su total facturado, pagado y pendiente.
- **A quién falta cobrar** — solo los asociados que tienen algo pendiente, con el período de su deuda más antigua.

Desde cualquiera de las dos, puedes abrir el **estado de cuenta** de un asociado específico para ver su historial financiero completo.

---

## 9. Reportes

Menú → Reportes. Dos reportes disponibles:

- **Lo cobrado en el mes** — elige un período y ve cuánto se facturó vs. cuánto se cobró realmente en ese mes calendario, y cuántos asociados pagaron.
- **Deuda pendiente** — el total que se debe al día de hoy, con su distribución por estado.

### Exportar un reporte

En cualquiera de los dos reportes, presiona "Exportar" para descargarlo en Excel o PDF. Si no ves la opción de exportar, es porque tu rol no tiene ese permiso — puedes ver el reporte en pantalla igual, solo no puedes descargarlo.

---

## 10. Administración (solo para administradores)

Menú → Administración. Tres secciones:

### Usuarios

Crea o edita cuentas de acceso al sistema — nombre, correo, rol, y si la cuenta está activa. Un usuario inactivo no puede iniciar sesión aunque su contraseña sea correcta.

### Roles

Cada rol agrupa un conjunto de permisos y módulos visibles. Desde "Permisos y módulos" de un rol, decides qué puede hacer (permisos) y qué ve en el menú (módulos) cualquier usuario con ese rol.

### Módulos

Los módulos son las opciones del menú lateral (Asociados, Facturación, etc.). Puedes desactivar un módulo para que desaparezca del menú de **todos** los roles que lo tengan asignado — útil si una sección todavía no está lista para usarse, sin tener que quitar el permiso de cada rol uno por uno.

---

## 11. Preguntas frecuentes

**¿Por qué no puedo ver cierta opción del menú?** Tu rol no tiene ese módulo asignado, o el módulo está desactivado. Contacta a un administrador.

**¿Por qué me rechaza una acción que antes sí podía hacer?** Puede que un administrador haya cambiado los permisos de tu rol — cierra sesión y vuelve a entrar para que el cambio se aplique.

**¿Puedo deshacer un pago o una factura que registré por error?** No, actualmente no. Contacta al administrador del sistema — ver `docs/OPEN_BUSINESS_DECISIONS.md` para el detalle de esta limitación conocida.

**¿Qué pasa si genero la facturación del mes dos veces sin querer?** Nada malo — el sistema no crea facturas duplicadas para el mismo asociado y período, solo te avisa cuántas se omitieron por ya existir.
