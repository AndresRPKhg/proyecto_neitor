# RaceControl · Manual técnico

*Versión 3.0 · Noviembre de 2026 · Para quien va a mantener, depurar o ampliar el código*

## 1. Resumen

RaceControl es una aplicación web monolítica en **PHP 8.2+** con **MySQL/MariaDB** (InnoDB, utf8mb4). El servidor entrega páginas HTML y una **API REST en JSON**; el navegador pinta los datos con **JavaScript ES6+** (módulos), **Bootstrap 5** con un tema propio (--rc-\*) y **Chart.js**. **Vite** solo compila y empaqueta los assets; no corre en producción. No hay framework PHP: el enrutador, la autenticación y el CRUD genérico son código propio y pequeño.

| Capa          | Tecnología                                                              |
|---------------|-------------------------------------------------------------------------|
| Backend       | PHP 8.2+, PDO (sentencias preparadas), extensiones pdo_mysql y mbstring |
| Base de datos | MySQL / MariaDB, InnoDB, utf8mb4                                        |
| Frontend      | HTML5, CSS3, JavaScript ES6+, Bootstrap 5, Chart.js                     |
| Build         | Vite 5 (solo assets) → public/assets                                    |
| Pruebas       | PHPUnit 11, prueba de humo en Python (tests/humo.py)                    |
| CI            | GitHub Actions (.github/workflows/ci.yml)                               |

La especificación funcional completa está en SKILLS2.md, que es la fuente de verdad del proyecto.

## 2. Arquitectura por capas

El código de src/ sigue una separación simple, de adentro hacia afuera. Cada capa solo conoce a las de adentro.

| Capa            | Carpeta            | Responsabilidad                                             | Archivos                                                                                                                                                                                                                                  |
|-----------------|--------------------|-------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Dominio         | src/Domain         | Reglas puras, sin base de datos ni HTTP. Fáciles de probar. | Rol, PoliticaContrasena, Tiempo, SistemaPuntos, Estadistica, ReglasInscripcion                                                                                                                                                            |
| Aplicación      | src/Application    | Casos de uso que combinan dominio y base de datos.          | AuthService, CatalogoService, InscripcionService, ResultadoService, MetricasService, ReporteService, ConfiguracionService, Recursos, Hooks y los errores (ErrorApp, ValidacionError, PermisoError, NoAutenticadoError, NoEncontradoError) |
| Infraestructura | src/Infrastructure | Todo lo que toca el exterior.                               | Env, Database, Sesion, Csrf, Correo, Actividad                                                                                                                                                                                            |
| Presentación    | src/Presentation   | HTTP: rutas, controladores y vistas.                        | Router, Peticion, Respuesta, ApiController, Pagina                                                                                                                                                                                        |

La carga de clases la hace src/autoload.php (PSR-4, espacio de nombres RaceControl\\, así que **en producción no hace falta Composer**; composer.json solo sirve para PHPUnit.

## 3. Estructura de carpetas

racecontrol/  
├── public/ Raíz web: index.php (controlador frontal), .htaccess, assets/ compilados  
├── src/ Código PHP por capas (sección 2)  
├── resources/  
│ ├── views/ Plantillas PHP (layout, auth, login, registro, modulo, dashboard, ...)  
│ ├── js/ Módulos JS fuente (app, core, shell, auth, tabla, campeonato, dashboard, ...)  
│ └── css/theme.css Tema gótico  
├── database/ racecontrol.sql (todo en uno), schema.sql, seeds.sql  
├── bin/ Scripts de consola: crear_admin, latido, consolidar  
├── tests/ DominioTest.php (PHPUnit), humo.py (extremo a extremo)  
├── storage/logs/ Registros y correos de prueba (no se versiona su contenido)  
├── .env.example Plantilla de configuración (el .env real nunca se sube)  
├── .htaccess Bloquea el acceso web a código y archivos sensibles  
└── index.php Redirige la raíz a /public

El .htaccess de la raíz responde 403 a src, database, storage, bin, tests, resources, vendor, node_modules, .github, a los .env\* y a los archivos de configuración. Aun así, la forma recomendada en producción es apuntar el servidor directamente a public/ (ver el manual de despliegue).

## 4. Ciclo de una petición

1.  Apache envía todo lo que no sea un archivo real a public/index.php (reglas de public/.htaccess, que también pasa la cabecera X-CSRF-Token a PHP).

2.  El controlador frontal carga .env (Env::cargar), fija la zona horaria (APP_TIMEZONE), construye la Peticion y registra rutas con Pagina::registrar y ApiController::registrar.

3.  Router::despachar busca la ruta por método y patrón. Para cada ruta aplica, en este orden:

    1.  **Autenticación**: si la ruta no tiene auth =\> false, exige Sesion::usuario(); si no hay, lanza NoAutenticadoError (401).

    2.  **CSRF**: toda petición que no sea GET/HEAD debe traer X-CSRF-Token (o \_csrf en el cuerpo) igual al de la sesión, o responde 419.

    3.  **Permiso**: si la ruta declara permiso, lo valida con Rol::puede; si no, 403.

    4.  **Métrica**: cuenta la operación en registro_actividad (solo peticiones que modifican datos).

4.  Se ejecuta el manejador. Los errores de la aplicación (ErrorApp y sus hijas) se convierten en JSON {error, errores} con el código HTTP del error. Cualquier otra excepción se registra con error_log y responde 500 con un mensaje genérico (el detalle solo se muestra si APP_ENV=local).

5.  Las páginas HTML se arman con Pagina::render, que envía las cabeceras de seguridad y entrega al JavaScript un bloque JSON inerte (#rc-datos) con base, csrf, usuario y pagina. No hay scripts en línea, lo que permite una CSP estricta.

## 5. Base de datos

Esquema en database/racecontrol.sql (crea la base racecontrol). Todas las tablas tienen created_at y updated_at; las de catálogo con borrado lógico tienen deleted_at.

| Tabla                       | Propósito                        | Detalles clave                                                                                                       |
|-----------------------------|----------------------------------|----------------------------------------------------------------------------------------------------------------------|
| roles                       | admin, editor, lector            | Ids fijos 1, 2 y 3                                                                                                   |
| usuarios                    | Cuentas                          | usuario y email únicos; password_hash; activo; bloqueado_hasta; sesion_version (subirlo invalida todas las sesiones) |
| intentos_login              | Intentos de acceso               | Indexada por (usuario, created_at). También cuenta los registros públicos (marca @registro)                          |
| tokens_recuperacion         | Enlaces de recuperación          | Se guarda solo el hash SHA-256 del token; expira_at, usado_at                                                        |
| equipos, pilotos, vehiculos | Catálogos                        | Borrado lógico; documento y placa únicos                                                                             |
| circuitos                   | Catálogo                         | Sin borrado lógico; no se elimina si tiene carreras                                                                  |
| campeonatos                 | Torneos                          | Único (nombre, temporada); puntos es texto con la lista separada por comas                                           |
| carreras                    | Fechas del campeonato            | estado programada/finalizada; hereda tipo_vehiculo                                                                   |
| inscripciones               | Piloto + vehículo en una carrera | Únicos (carrera, piloto) y (carrera, vehículo); ON DELETE CASCADE desde la carrera                                   |
| resultados                  | Tiempo y puntos                  | Uno por inscripción; estado clasificado/dnf/dsq; tiempos en milisegundos                                             |
| posiciones_campeonato       | Tabla agregada                   | Puntos, victorias, podios y carreras por piloto; se recalcula                                                        |
| metricas_diarias            | KPI consolidados                 | Una fila por día                                                                                                     |
| registro_actividad          | Eventos                          | tipo: modulo, operacion, error, latido                                                                               |
| configuracion               | Ajustes clave-valor              | Hoy solo puntos_por_defecto                                                                                          |

Los datos de ejemplo (3 equipos, 4 pilotos, 4 vehículos, 2 circuitos, el campeonato «Copa Catedral» 2026 y una carrera) y el administrador inicial viven al final del mismo archivo. **Hay que borrarlos o reemplazarlos antes de producción.**

## 6. Seguridad

| Tema              | Implementación                                                                                                                                                                                                 |
|-------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Contraseñas       | password_hash con **Argon2id** si PHP lo trae, y bcrypt si no. PoliticaContrasena: mínimo 12 caracteres, mayúscula, minúscula, número y símbolo; rechaza claves comunes y las que contienen el usuario         |
| Sesión            | Cookie rc_sesion: HttpOnly, SameSite=Lax, Secure con HTTPS o APP_ENV=production; modo estricto; session_regenerate_id al entrar. Máximo 8 h y cierre a los 15 min de inactividad (renovación deslizante)       |
| Logout global     | usuarios.sesion_version: cada sesión guarda la versión con la que nació. Subirla invalida todas. Se sube al cerrar sesión, al cambiar contraseña y al cambiar rol o desactivar                                 |
| Bloqueo de cuenta | 5 intentos fallidos desde el último acceso correcto → bloqueo de 15 min (bloqueado_hasta, HTTP 423). password_verify se ejecuta siempre para igualar tiempos y no revelar si el usuario existe                 |
| Recuperación      | Token de 32 bytes aleatorios, de un solo uso, 30 min; en base de datos solo su SHA-256. La respuesta es igual exista o no la cuenta                                                                            |
| CSRF              | Token por sesión con hash_equals en toda petición que modifica datos                                                                                                                                           |
| Inyección SQL     | Solo sentencias preparadas (ATTR_EMULATE_PREPARES =\> false). Los nombres de tabla y columna salen de Recursos (código), nunca de la petición                                                                  |
| XSS               | e() en vistas PHP y esc() en JS al armar HTML. CSP: default-src 'self'; script-src 'self'; frame-ancestors 'none'; además X-Frame-Options: DENY, X-Content-Type-Options: nosniff, Referrer-Policy: same-origin |
| Permisos          | Rol::puede valida siempre en el backend. El frontend solo oculta botones con la lista permisos que recibe                                                                                                      |
| Secretos          | Solo en .env (ignorado por git y bloqueado por .htaccess)                                                                                                                                                      |

### Matriz de permisos (src/Domain/Rol.php)

| Permiso                                                                                                               | Admin | Editor | Lector |
|-----------------------------------------------------------------------------------------------------------------------|-------|--------|--------|
| usuarios.gestionar, config.gestionar                                                                                  | Sí    | No     | No     |
| catalogo.crear, catalogo.editar, competencia.crear, competencia.editar, inscripciones.gestionar, resultados.gestionar | Sí    | Sí     | No     |
| catalogo.borrar, competencia.borrar                                                                                   | Sí    | No     | No     |
| reportes.ver, reportes.exportar                                                                                       | Sí    | Sí     | Sí     |

Para agregar un permiso: se declara en Rol::PERMISOS, se usa en la definición del recurso o en la ruta (\['permiso' =\> '...'\]) y se agrega una prueba.

## 7. Registro público de usuarios (nuevo en 3.0)

El login tiene el enlace **Crear cuenta** hacia /registro. Diseño:

- **Rutas**: GET /registro (página, sin sesión) y POST /api/auth/registro (sin sesión, pero con CSRF como todo POST).

- **Servicio**: AuthService::registrar(array \$d, string \$ip).

- **Rol fijo**: el servicio busca el id del rol lector en la tabla roles y lo usa siempre. El controlador además filtra el cuerpo con array_intersect_key para pasar solo nombre, usuario, email, clave y confirmar; un rol_id, rol o activo enviado a mano se ignora. Esto está cubierto por una prueba.

- **Validación**: reutiliza las reglas del módulo Usuarios en Recursos (patrón del usuario ^\[A-Za-z0-9\_.-\]{3,40}\$, largos máximos), el filtro de correo de PHP y PoliticaContrasena. Normaliza el nombre igual que el módulo de usuarios.

- **Unicidad**: se comprueba antes de insertar y, además, si dos peticiones compiten, el error 23000 de MySQL se convierte en un 422 limpio.

- **Límite por IP**: 5 registros por hora. No se creó una tabla nueva: cada registro se anota en intentos_login con usuario = '@registro' y la IP; el patrón de usuario no admite @, así que nunca choca con una cuenta real. Se supera → HTTP 429.

- **Apagado**: REGISTRO_ABIERTO=false en .env oculta el enlace, responde 404 a /registro y 403 a la API.

- **Sesión**: tras crear la cuenta se abre sesión (Sesion::abrir) y se devuelve un token CSRF nuevo, igual que en el login.

**Limitaciones conocidas**

1.  La IP sale de REMOTE_ADDR. Detrás de un proxy inverso todos los visitantes compartirían IP y el límite de 5 por hora sería global; habría que leer X-Forwarded-For solo desde proxies de confianza.

2.  El formulario indica si un usuario o correo ya existe (necesario para que sea usable). Eso permite comprobar si una persona tiene cuenta. El login y la recuperación sí evitan esa filtración.

3.  No hay verificación del correo ni CAPTCHA. Si el sistema se expone a internet, conviene añadir uno de los dos o cerrar el registro.

## 8. API REST

Base: {APP_URL}/api. Todas las respuestas son JSON (excepto el CSV). Los errores tienen la forma {"error": "mensaje", "errores": {"campo": "detalle"}}.

| Código    | Significado                                                  |
|-----------|--------------------------------------------------------------|
| 200 / 201 | Correcto / creado                                            |
| 401       | Sin sesión                                                   |
| 403       | Sin permiso (o registro cerrado)                             |
| 404       | No existe                                                    |
| 409       | Conflicto de negocio (por ejemplo, eliminar al último admin) |
| 419       | Falta el token CSRF o es inválido                            |
| 422       | Validación por campo                                         |
| 423       | Cuenta bloqueada                                             |
| 429       | Demasiados registros                                         |

### Autenticación

| Método y ruta            | Sesión | Descripción                                                                |
|--------------------------|--------|----------------------------------------------------------------------------|
| POST /auth/login         | No     | usuario, clave                                                             |
| POST /auth/registro      | No     | nombre, usuario, email, clave, confirmar. Crea cuenta Lector y abre sesión |
| POST /auth/recuperar     | No     | usuario (usuario o correo)                                                 |
| POST /auth/restablecer   | No     | token, clave, confirmar                                                    |
| POST /auth/logout        | Sí     | Logout global                                                              |
| GET /auth/yo             | Sí     | Perfil, permisos y segundos restantes de sesión                            |
| POST /auth/ping          | Sí     | Latido de actividad                                                        |
| POST /auth/cambiar-clave | Sí     | actual, nueva, confirmar                                                   |

### Competencias, dashboard y configuración

| Método y ruta                           | Permiso                 | Descripción                                                                                                   |
|-----------------------------------------|-------------------------|---------------------------------------------------------------------------------------------------------------|
| GET /campeonatos/{id}/posiciones        | sesión                  | Tabla de posiciones                                                                                           |
| GET /carreras/{id}/inscripciones        | sesión                  | Inscritos                                                                                                     |
| POST /carreras/{id}/inscripciones       | inscripciones.gestionar | piloto_id, vehiculo_id                                                                                        |
| DELETE /inscripciones/{id}              | inscripciones.gestionar | Quita una inscripción                                                                                         |
| GET /carreras/{id}/resultados           | sesión                  | Resultados                                                                                                    |
| POST /carreras/{id}/resultados          | resultados.gestionar    | resultados: \[{inscripcion_id, estado, tiempo, mejor_vuelta}\]                                                |
| POST /carreras/{id}/resultados/importar | resultados.gestionar    | csv (hasta 200 000 caracteres)                                                                                |
| GET /dashboard                          | reportes.ver            | KPI, gráficas, estadística, outliers y calidad. Filtros campeonato_id, piloto_id                              |
| GET /reportes y GET /reportes/{tipo}    | reportes.ver            | Tipos: posiciones, resultados, pilotos, estadisticas, outliers, calidad. ?formato=csv exige reportes.exportar |
| GET /configuracion                      | sesión                  | Configuración actual                                                                                          |
| PUT /configuracion                      | config.gestionar        | puntos_por_defecto                                                                                            |

### CRUD genérico

Recursos: pilotos, equipos, vehiculos, circuitos, campeonatos, carreras, usuarios (y roles, solo para llenar listas).

| Método y ruta           | Descripción                                                                   |
|-------------------------|-------------------------------------------------------------------------------|
| GET /{recurso}/meta     | Campos, columnas y permisos del recurso (para armar la tabla y el formulario) |
| GET /{recurso}/opciones | Lista corta para desplegables                                                 |
| GET /{recurso}          | Lista con q, orden, dir, pagina, por y los filtros del recurso                |
| GET /{recurso}/{id}     | Detalle                                                                       |
| POST /{recurso}         | Crear (201)                                                                   |
| PUT /{recurso}/{id}     | Editar                                                                        |
| DELETE /{recurso}/{id}  | Eliminar (lógico o físico, según el recurso)                                  |

### CRUD declarativo

Cada recurso se define **una sola vez** en Recursos::todos(): tabla, etiquetas, permisos (ver, crear, editar, borrar), columnas visibles, campos con tipo (texto, email, entero, decimal, fecha, enum, ref, puntos, password, booleano), requerido, max, patron, unicos y borrado_logico. CatalogoService valida y ejecuta a partir de esa definición, y el frontend recibe la versión sin SQL (Recursos::meta) para dibujar tablas y formularios. **Para agregar un catálogo nuevo**: crear la tabla en el SQL, agregar su definición en Recursos, añadirlo a la lista de la ruta de páginas en Pagina::registrar y, si hace falta, al menú en Pagina::menu.

Las reglas que no caben en una definición están en Hooks: la carrera hereda el tipo del campeonato, no se cambia el tipo de un campeonato con carreras, siempre queda un admin activo, nadie borra su propia cuenta, los cambios sensibles de un usuario suben sesion_version, y cambiar los puntos recalcula los resultados.

## 9. Lógica de competencias

- **Inscripción** (ReglasInscripcion): carrera no finalizada, cupo disponible, piloto no repetido, vehículo del mismo tipo que la carrera y no repetido. Los únicos de la base de datos son la última defensa ante carreras de concurrencia.

- **Tiempos** (Tiempo): formato mm:ss.mmm, guardado en milisegundos; segundos 00–59.

- **Resultados** (ResultadoService::guardar): valida todas las filas antes de escribir; si hay un error no se guarda nada. Dentro de una transacción inserta o actualiza, recalcula posiciones y puntos de la carrera, marca la carrera finalizada y recalcula posiciones_campeonato. Un DNF o DSQ no tiene posición ni puntos.

- **Puntos** (SistemaPuntos): lista de enteros (0–1000) por posición; fuera de la lista, 0 puntos.

- **Desempates** de la tabla: puntos, luego victorias, luego podios.

- **Importación CSV**: columnas documento,tiempo,mejor_vuelta,estado. Todo o nada: una línea mala cancela la importación.

- **Estadística** (Estadistica): media, mediana, moda y detección de valores atípicos con IQR sobre los tiempos.

## 10. Métricas y tareas programadas

- Actividad::registrar guarda eventos en registro_actividad: modulo (al abrir un módulo), operacion (petición que modifica datos), error (operación con HTTP 400 o más) y latido.

- **Tiempo activo**: cada latido vale 5 minutos. Lo generan los usuarios conectados (/auth/ping) y bin/latido.php.

- **KPI** (últimos 30 días): pilotos registrados, campeonatos, carreras programadas, porcentaje de errores, horas activas y uso por módulo.

- bin/consolidar.php \[AAAA-MM-DD\] resume un día en metricas_diarias (por defecto, ayer). El dashboard también consolida el día de hoy al abrirse.

- bin/crear_admin.php usuario correo "Nombre" crea o recupera un administrador. Pide la contraseña por consola o lee RC_ADMIN_PASS.

## 11. Frontend

- **Fuentes**: resources/js/app.js es la entrada. core.js trae utilidades compartidas (cliente api(), esc(), toasts, confirmaciones, preferencias); shell.js el menú, el panel de control y el cierre por inactividad; auth.js login, registro, recuperación y restablecimiento; tabla.js la tabla genérica (búsqueda, orden, paginación, formulario, borradores); campeonato.js, dashboard.js, reportes.js, configuracion.js y grafico.js se cargan solo cuando la página los necesita (chunk-\*.js).

- **Cliente de la API**: api(ruta, {metodo, cuerpo, params}) agrega X-CSRF-Token, convierte errores en ErrorApi (con errores por campo) y avisa si no hay conexión.

- **Formularios**: auth.js usa enviarFormulario(form, ruta, prefijo, alExito, errorGeneral); los errores por campo se pintan en \#\<prefijo\>\<campo\>-error. El formulario de registro usa el prefijo reg-.

- **Accesibilidad**: contraste WCAG AAA (7:1 mínimo), etiquetas asociadas, role="alert" en errores, enlace «Saltar al contenido», tablas de datos alternativas a cada gráfica y animaciones desactivables.

- **Almacenamiento local**: solo preferencias de interfaz y borradores de formularios (nunca contraseñas ni tokens).

- **Compilación**: npm install y npm run build generan public/assets (app.js, app.css, chunk-\*.js y las fuentes). El resultado ya viene incluido en el repositorio, así que solo hace falta recompilar si se cambia algo en resources/. npm run dev recompila al guardar.

## 12. Configuración (.env)

| Variable                                    | Valor por defecto                         | Para qué sirve                                                                              |
|---------------------------------------------|-------------------------------------------|---------------------------------------------------------------------------------------------|
| APP_ENV                                     | local                                     | local o production. En producción la cookie es Secure y los errores 500 no muestran detalle |
| APP_URL                                     | http://localhost/racecontrol/public       | URL pública; se usa para el enlace del correo de recuperación                               |
| APP_TIMEZONE                                | America/Bogota                            | Zona horaria de PHP                                                                         |
| DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS | 127.0.0.1, 3306, racecontrol, root, vacío | Conexión a MySQL                                                                            |
| MAIL_DRIVER                                 | log                                       | log escribe en storage/logs/correos.log; mail usa la función mail() de PHP                  |
| MAIL_FROM                                   | no-reply@racecontrol.local                | Remitente                                                                                   |
| REGISTRO_ABIERTO                            | true                                      | false cierra el registro público                                                            |

Env acepta comentarios al final de la línea (# ...) y también lee variables del entorno del sistema.

## 13. Pruebas

- **PHPUnit** (vendor/bin/phpunit): tests/DominioTest.php cubre las reglas puras (contraseñas, tiempos, puntos, estadística, inscripción y permisos).

- **Prueba de humo** (python3 tests/humo.py http://localhost/racecontrol/public): recorre el sistema de punta a punta contra un servidor real: autenticación, CRUD, inscripciones, resultados, dashboard, reportes, roles, **registro público**, bloqueo de cuenta y logout global. Debe correrse sobre una base recién importada (database/racecontrol.sql), porque espera los datos de ejemplo y crea usuarios de prueba. Termina con TODO BIEN o con la lista de fallos.

- **CI**: revisión de sintaxis de todos los .php, PHPUnit y build de Vite en cada pull request y en las ramas main y develop.

Las pruebas del registro verifican: página y enlace, CSRF obligatorio, validación por campo, contraseña débil, duplicados, rol forzado ignorado, permisos de un Lector recién creado, aparición en el módulo Usuarios, inicio de sesión posterior y límite de 5 por hora.

## 14. Mantenimiento y extensión

| Quiero...                                | Hago esto                                                                                          |
|------------------------------------------|----------------------------------------------------------------------------------------------------|
| Cambiar la política de contraseñas       | Edito PoliticaContrasena; aplica a registro, admin y recuperación a la vez                         |
| Cambiar el límite de registros           | AuthService::REGISTROS_POR_HORA                                                                    |
| Cambiar el rol de las cuentas nuevas     | La constante Rol::LECTOR en AuthService::registrar (no lo hagas sin revisar la matriz de permisos) |
| Agregar un catálogo                      | Sección 8, «CRUD declarativo»                                                                      |
| Agregar un reporte                       | Entrada en ReporteService::TIPOS y su rama en generar                                              |
| Cambiar el tiempo de sesión              | Constantes Sesion::MAXIMO e Sesion::INACTIVIDAD (el JS las recibe por \#rc-datos)                  |
| Subir un usuario a admin sin la interfaz | php bin/crear_admin.php usuario correo "Nombre"                                                    |
| Ver por qué falló algo                   | Registro de errores de PHP/Apache (error_log); con APP_ENV=local el 500 muestra el mensaje         |

**Reglas de estilo del proyecto**: código comentado en español, nombres claros, sin duplicar lógica (DRY), claves solo en .env, y todo cambio de comportamiento con al menos una prueba.

**Pendientes conocidos**: sincronizar borradores con el servidor (no se construyó), revisión de accesibilidad con lector de pantalla (manual), verificación de correo y CAPTCHA en el registro público, y soporte de proxy inverso para la IP del cliente.
