# Plataforma TESOEM: resumen de trabajo del backend

Fecha: 10 de octubre de 2026

Este documento resume lo que se hizo en esta sesión sobre el backend Laravel de la plataforma de capacitación TESOEM: qué se construyó, dónde va cada archivo, cómo se configura y qué falta.

## 1. Panorama

De la lista de pendientes de seguridad del backend se completaron cuatro puntos:

- Paginación de usuarios en el panel de administración.
- Expiración de sesión con un driver propio sobre la tabla `sesiones`.
- Limpieza de datos de desarrollo.
- Carga segura de archivos, empezando por los materiales de los profesores.

Quedan pendientes: más pruebas automatizadas, la carga de archivos de proyectos de alumnos y la interfaz de materiales.

## 2. Paginación de usuarios

**Qué se hizo**

- `GET /api/v1/usuarios` acepta `pagina` y `por_pagina` (20 por defecto, máximo 100).
- La respuesta conserva la clave `usuarios` y agrega `paginacion` con `pagina`, `por_pagina`, `total` y `ultima_pagina`.
- Si se pide una página fuera de rango, el backend la corrige a la última.
- En el frontend, `admin.js` muestra "1-20 de N usuarios", "Página X de Y" y botones Anterior y Siguiente. Los controles solo aparecen si hay más de una página.
- Al filtrar o buscar se vuelve a la página 1. Al validar, desactivar o reactivar una cuenta se queda en la página actual.

**Archivos**

- `app\Http\Controllers\Api\V1\UsuarioController.php`
- `public\app\js\controllers\admin.js`

**Limitación conocida:** el rol visible se calcula en PHP (roles más solicitudes pendientes), así que el filtro por rol y la búsqueda se aplican en memoria antes de cortar la página. Funciona bien para cientos de usuarios; con miles habría que mover ese cálculo a SQL.

## 3. Expiración de sesión

**Hallazgo.** El restablecimiento de contraseña hacía `DELETE` sobre la tabla `sesiones`, pero el `.env` usaba `SESSION_DRIVER=file`, así que esa línea no cerraba ninguna sesión. Además, la tabla `sesiones` del esquema tiene columnas propias (`usuario_id`, `carga_util`, `ultima_actividad`, `expira_en`, `revocado_en`) que el driver `database` de Laravel no puede usar.

**Decisión:** crear un driver de sesión propio llamado `tesoem` que trabaje sobre esa tabla.

**Cómo funciona**

- Expiración por inactividad, según `SESSION_LIFETIME`.
- Expiración absoluta desde el login, según `SESSION_ABSOLUTE_LIFETIME`. El límite se fija una sola vez al crear la sesión.
- Revocación: `revocado_en` permite cerrar todas las sesiones de un usuario.
- Al suspender o desactivar una cuenta desde el panel, sus sesiones abiertas se revocan.
- El frontend (`api.js`) ya redirigía al login al recibir un 401.

**Archivos**

- `app\Support\Session\SesionTesoemHandler.php` (nuevo)
- `app\Providers\AppServiceProvider.php` (registra el driver)
- `config\session.php` (agrega la clave `absoluto`)
- `app\Http\Controllers\Api\V1\UsuarioController.php` (revoca sesiones al suspender)

**Configuración en `.env`**

```
SESSION_DRIVER=tesoem
SESSION_LIFETIME=30
SESSION_ABSOLUTE_LIFETIME=480
SESSION_EXPIRE_ON_CLOSE=true
```

El usuario de base de datos `tesoem_app` necesita `SELECT`, `INSERT`, `UPDATE` y `DELETE` sobre `sesiones`. Después de cambiar el `.env` hay que correr `php artisan config:clear`.

**Estado de verificación:** el código pasa la revisión de sintaxis, pero el driver aún no tiene pruebas automatizadas ni se probó contra la base de datos. Conviene iniciar sesión y confirmar que aparece una fila en `sesiones` con el `usuario_id`.

## 4. Limpieza de datos de desarrollo

**Qué se hizo**

- Comando nuevo `php artisan tesoem:limpiar-datos-prueba`. Borra cuentas de prueba y todo lo que cuelga de ellas (cursos, lecciones, inscripciones, progreso, certificados, proyectos, auditoría, sesiones, roles y perfiles) dentro de una transacción. Si algo falla, revierte todo. Nunca toca `sistema@tesoem.local`.
- Opciones: `--simulacro`, `--dominio=`, `--correo=` (repetible), `--conservar-auditoria`, `--force`.
- `DatabaseSeeder` corregido: antes usaba una factory que no existe en este esquema. Ahora ejecuta `SistemaSeeder` siempre, y los seeders de prueba solo en `local` y `testing`.
- `DatosPruebaSeeder` y `LeccionesPruebaSeeder` lanzan error si se ejecutan fuera de `local` o `testing`.
- Frontend: se quitó de las 10 páginas HTML la carga de `mockServer.js`. La carpeta `public\app\js\mock` debe borrarse a mano.

**Ejecución real:** se corrió el comando sobre la base `tesoem`. Se eliminaron las dos cuentas de prueba (`osornoalex3@gmail.com` y `osornoalex3.1@gmail.com`) con sus 5 eventos de auditoría, 2 roles y 2 perfiles.

**Estado actual de la base `tesoem`**

- Queda solo la cuenta técnica `sistema@tesoem.local`, bloqueada.
- No hay ningún administrador. Antes de producción hay que crear uno real. Se ofreció un comando `tesoem:crear-admin` que pida correo y contraseña por consola; está pendiente de decidir.
- Para seguir probando en local, `php artisan db:seed` crea cuentas de prueba (`admin@tesoem.test` y `profesor@tesoem.test`).

**Archivos:** `app\Console\Commands\LimpiarDatosPrueba.php`, `database\seeders\DatabaseSeeder.php`, `DatosPruebaSeeder.php`, `LeccionesPruebaSeeder.php` y las 10 páginas HTML sin el script del mock.

## 5. Carga segura de materiales de profesores

**Endpoints**

- `GET /api/v1/cursos/{curso}/materiales`: listar.
- `POST /api/v1/cursos/{curso}/materiales`: subir (solo el profesor dueño).
- `GET /api/v1/materiales/{material}/descargar`: descargar.
- `DELETE /api/v1/materiales/{material}`: eliminar (dueño o administrador).

**Quién puede qué**

- Subir: el profesor dueño del curso, con el permiso `materiales.administrar_propios`, en un curso no archivado.
- Ver y descargar: el dueño, administradores y revisores, y los alumnos inscritos (activos o completados) en un curso publicado. A los alumnos solo se les ofrecen materiales ya analizados y de lecciones publicadas.
- Cualquier otro caso recibe 404, para no revelar que el material existe.

**Medidas de seguridad**

- Formatos permitidos: PDF, PNG, JPG, DOCX, PPTX y XLSX, hasta 20 MB por archivo y 50 materiales por curso.
- El tipo se valida por contenido, no por la extensión ni por el tipo que declara el navegador. Un ejecutable renombrado a `.pdf` se rechaza.
- Los documentos de Office se revisan por dentro: deben ser un zip válido con las partes propias del formato, y se rechazan los que traen macros (`vbaProject.bin`) o descomprimen a más de 200 MB.
- Las imágenes se validan y se limita su tamaño en píxeles.
- Los archivos se guardan en disco privado (`storage\app\private\materiales\{curso}\`) con nombre aleatorio. El nombre del cliente nunca se usa como ruta; se limpia y solo se guarda para mostrarlo.
- Se guarda el checksum SHA-256 y todo queda auditado.
- Descarga siempre como adjunto, con `X-Content-Type-Options: nosniff` y sin caché.
- Límites de peticiones: 20 subidas por minuto, 60 descargas y listados, 30 eliminaciones.

**Antivirus.** Existe un escáner opcional con ClamAV (`MATERIALES_ESCANER=clamav`). En desarrollo, `ninguno` solo valida el tipo. En producción, sin escáner configurado el sistema rechaza todas las subidas, por diseño. La conexión real con ClamAV no se pudo probar; solo se probó la lógica alrededor. Si el servidor de TESOEM no permite instalar ClamAV, hay que buscar otra opción.

**Configuración opcional en `.env`**

```
MATERIALES_ESCANER=ninguno
MATERIALES_MAX_KB=20480
MATERIALES_MAX_POR_CURSO=50
```

En `php.ini` hay que confirmar que `upload_max_filesize` y `post_max_size` sean al menos 20M y que `extension=fileinfo` esté activa. Esto ya se verificó en el entorno del usuario.

**Archivos**

- `config\materiales.php`
- `app\Models\Material.php`
- `app\Policies\MaterialPolicy.php`
- `app\Support\Files\ValidadorArchivo.php`
- `app\Support\Files\EscanerArchivos.php`
- `app\Actions\Materials\SubirMaterial.php`
- `app\Http\Controllers\Api\V1\MaterialController.php`
- `routes\tesoem-api.php` (agrega las 4 rutas)
- `tests\Feature\MaterialesTest.php`

**Verificación.** Las 9 pruebas de `MaterialesTest.php` pasan contra MariaDB 10.11 con el esquema `tesoem.sql`. Cubren subida válida, extensiones prohibidas, ejecutable disfrazado, macros, límite de tamaño, permisos del profesor ajeno y del alumno, acceso de alumnos inscritos y de lecciones sin publicar, eliminación y el bloqueo en producción sin escáner.

## 6. Hallazgos y advertencias

- **Sesiones:** el `DELETE` sobre `sesiones` no hacía nada con el driver `file` (ya resuelto con el driver propio).
- **Collation:** en `tesoem.sql`, la tabla `lecciones` se crea sin collation explícita y hereda la de la base. En la base `tesoem` del usuario todas las tablas tienen `utf8mb4_unicode_ci`, así que no hay problema. El código de materiales tampoco depende de ello. Conviene avisar al compañero que mantiene el esquema.
- **Pruebas del starter kit:** al correr toda la suite fallan 25 pruebas (carpetas `Settings`, `Auth` y `dashboard`, con nombres en inglés). Usan `User::factory()` y rutas Livewire desactivadas. Las pruebas propias del proyecto pasan.
- **Rutas `settings/*` de Livewire:** siguen activas para usuarios con sesión. Falta revisar si permiten cambiar la contraseña sin pasar por las reglas de contraseña del proyecto.
- **`.env` en el zip:** el zip del proyecto incluía el `.env` con la clave de la base y el `APP_KEY`. No debe compartirse así; si salió del equipo, conviene rotar esas credenciales.
- **`database\database.sqlite`:** es un residuo del starter kit y se puede borrar.

## 7. Pendientes

- Más pruebas automatizadas: eliminar las 25 pruebas del starter kit y cubrir sesión, paginación, limpieza y el driver de sesión.
- Interfaz de materiales: botón de subida y lista de archivos en `curso-editor`, y descarga para alumnos inscritos.
- Carga segura de archivos de proyectos de alumnos (tabla `archivos_proyectos`).
- Crear un administrador real antes de producción (por ejemplo con un comando `tesoem:crear-admin`).
- Instalar y configurar ClamAV en el servidor de producción.
- MFA para administradores, como punto aparte (requiere elegir librería TOTP).
- Borrar la carpeta `public\app\js\mock` y revisar las rutas `settings/*` de Livewire.
