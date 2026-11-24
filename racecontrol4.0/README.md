# RaceControl

Sistema web para organizar y administrar competencias automovilísticas y motociclísticas: pilotos, escuderías, vehículos, circuitos, inscripciones, resultados, posiciones, dashboards y reportes, con interfaz gráfica de estilo gótico. La especificación completa está en `SKILLS2.md` (fuente de verdad del proyecto).

**Stack:** PHP 8.2+ (API REST + JSON, PDO), MySQL (InnoDB, utf8mb4), HTML5/CSS3/JS ES6+, Bootstrap 5 personalizado (`--rc-*`), Chart.js, Vite (solo assets), PHPUnit.

## Instalación en XAMPP (Windows)

1. Copia la carpeta en `C:\xampp\htdocs\racecontrol` y enciende Apache y MySQL.
2. Crea la base de datos: phpMyAdmin → Importar → `database/racecontrol.sql` (un solo archivo con tablas, roles, datos de ejemplo y el administrador inicial). También existen `schema.sql` y `seeds.sql` por separado.
3. Copia `.env.example` como `.env` y ajusta `DB_*` y `APP_URL` (por defecto `http://localhost/racecontrol/public`). **`.env` nunca se sube al repositorio.**
4. Entra con el administrador inicial: usuario `admin`, contraseña `Catedral#Noche2026`. **Cámbiala de inmediato** (menú de usuario → Cambiar contraseña). Otros usuarios se crean en «Usuarios y roles» → «Nuevo usuario» (solo Admin). `php bin/crear_admin.php` sigue disponible para crear o recuperar un administrador.
5. Abre `http://localhost/racecontrol/` (redirige solo a `/public`) e inicia sesión.

Si actualizas una base existente, ejecuta una sola vez y en este orden `database/migrations/20261123_vehiculo_piloto_escuderia.sql`, `database/migrations/20261123_z_campeonatos_mixtos_inscripcion_automatica.sql` y `database/migrations/20261123_zz_carreras_mixtas.sql` en phpMyAdmin. Las instalaciones nuevas que importen `racecontrol.sql` ya incluyen estos cambios.
En cualquier campeonato se puede crear una carrera mixta, además del tipo predeterminado por el campeonato. En una carrera mixta pueden competir autos y motos. Cada piloto se inscribe una sola vez; si tiene ambos tipos de vehículo asignados, se elige cuál usará en esa carrera.

Los assets ya vienen compilados en `public/assets`. Solo si cambias `resources/`: `npm install` y `npm run build`.
Para las pruebas: `composer install` y `vendor/bin/phpunit` (también sirve un `phpunit` global).
Prueba de extremo a extremo (servidor en marcha): `python3 tests/humo.py http://localhost/racecontrol/public`.

Tareas programadas (Programador de tareas de Windows o cron): `php bin/latido.php` cada 5 min (tiempo activo) y `php bin/consolidar.php` una vez al día (tabla `metricas_diarias`).
Con `MAIL_DRIVER=log` el correo de recuperación se escribe en `storage/logs/correos.log` (útil en XAMPP).

## Estructura

```
public/        index.php (controlador frontal), .htaccess, assets compilados
src/Domain/            reglas puras: Rol, PoliticaContrasena, Tiempo, SistemaPuntos, Estadistica, ReglasInscripcion
src/Application/       casos de uso: Auth, Catalogo (CRUD genérico), Inscripcion, Resultado, Metricas, Reporte, Configuracion
src/Infrastructure/    Env, Database (PDO), Sesion, Csrf, Correo, Actividad
src/Presentation/      Router, ApiController (REST), Pagina (vistas)
resources/             js/, css/theme.css (tema gótico), views/
database/              schema.sql, seeds.sql, migrations/
tests/                 PHPUnit + humo.py
```

## Módulos (plan de construcción)

| # | Módulo | Estado |
|---|--------|--------|
| 1 | Base: estructura, `.env.example`, `schema.sql`, tema gótico, layout, sidebar, header | Hecho |
| 2 | Seguridad: login/logout global, bloqueo 5 intentos/15 min, recuperación por correo, roles | Hecho |
| 3 | Catálogos: pilotos, escuderías, vehículos, circuitos (borrado lógico en los tres primeros) | Hecho |
| 4 | Competencias: campeonatos mixtos de autos y motos, carreras por tipo | Hecho |
| 5 | Inscripciones: vehículo asignado al piloto y tipo de carrera, sin duplicados, cupos | Hecho |
| 6 | Resultados: tiempos `mm:ss.mmm`, posiciones, puntos, recálculo, importación CSV | Hecho |
| 7 | Dashboards y reportes: KPI, gráficas, estadística, outliers (IQR), calidad de datos, CSV | Hecho |
| 8 | Configuración: usuarios, sistema de puntos, preferencias | Hecho |
| 9 | Pruebas y despliegue: PHPUnit, humo, build Vite, CI en `.github/workflows/ci.yml` | Hecho (la revisión de accesibilidad con lector de pantalla queda manual) |

## Roles

Admin, Editor y Lector, según la matriz del Paso 13 de la especificación; los permisos se validan siempre en el backend (`src/Domain/Rol.php`).

## Contraste (WCAG AAA, mínimo 7:1)

Calculado con la fórmula WCAG: texto hueso sobre fondo 15,9:1; texto secundario sobre superficie 9,0:1; dorado sobre superficie 7,7:1; hueso sobre burdeos 7,4:1; error 8,2:1; éxito 10,9:1; blanco sobre botón rojo de peligro 8,1:1.

## Decisiones propias (la especificación no las definía)

- **Borradores:** los formularios nuevos guardan un borrador local en `localStorage` y ofrecen restaurarlo o descartarlo. La sincronización automática de borradores con el backend (Paso 14) **no se construyó**: no había un modelo de datos definido para ello y MySQL sigue siendo la única fuente de verdad.
- **Borrado:** solo Admin elimina (catálogos y competencias); Editor crea y edita. Campeonatos con carreras y circuitos con carreras no se pueden eliminar.
- **Carreras** de un campeonato mixto definen si son de autos o motos; al inscribir, se usa automáticamente el vehículo del piloto de ese tipo. Al guardar resultados la carrera pasa a «finalizada». Cambiar los puntos de un campeonato recalcula sus resultados.
- **Empates** en la tabla: puntos, luego victorias, luego podios. Un DNF o DSQ no suma puntos ni posición.
- **Tiempo activo:** cada latido cuenta 5 minutos (`bin/latido.php` o la actividad de usuarios conectados).
- **Fuentes y librerías** (Cinzel, UnifrakturCook, EB Garamond, Bootstrap, Chart.js) se empaquetan con Vite: no hay dependencias externas en tiempo de ejecución.
- **Seeds de ejemplo** (pilotos, escuderías, una carrera) sirven solo para probar; bórralos antes de producción.

## Producción

HTTPS obligatorio (`APP_ENV=production` marca la cookie como `Secure`), `display_errors` apagado (ya lo hace `public/index.php`), variables en `.env`, copias de seguridad diarias de MySQL y despliegue manual desde `main`.
