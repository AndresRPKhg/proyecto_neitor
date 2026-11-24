# Especificación técnica: RaceControl

> Sistema web para organizar y administrar competencias automovilísticas y motociclísticas: pilotos, equipos, vehículos, circuitos, inscripciones y resultados, con una interfaz gráfica de estilo gótico.

## Cómo usar este archivo (prompt principal)

Este archivo es la **fuente de verdad** del proyecto. Quien lo lea (persona o IA) debe seguir estas reglas:

1. Todo lo que se construya debe respetar las decisiones de este documento. Si algo no está definido, se propone una opción simple y se deja anotada.
2. No se agregan tecnologías, librerías ni funciones que no estén aquí sin avisar antes.
3. Se trabaja por módulos, uno a la vez, en el orden de la sección **Plan de construcción**.
4. Cada módulo se entrega funcionando: base de datos, backend, interfaz gótica, validaciones y al menos una prueba.
5. El código va comentado en español, con nombres claros y sin duplicar lógica (DRY).
6. Las claves, contraseñas y datos de conexión **nunca** van en el código: solo en `.env` (que no se sube al repositorio).

## Alcance

El sistema permitirá:

- Registrar y administrar pilotos y equipos.
- Gestionar vehículos (autos y motos) y circuitos.
- Crear campeonatos y competencias (carreras).
- Controlar inscripciones de pilotos a competencias.
- Registrar resultados y tiempos.
- Generar tablas de posiciones y estadísticas.
- Mostrar dashboards y reportes.
- Administrar usuarios y roles.

**Fuera de alcance (por ahora):** inteligencia artificial o chatbot, pagos, transmisión en vivo, app móvil nativa, PWA y 2FA.

## Contexto general

La organización de competencias deportivas requiere manejar información de pilotos, equipos, vehículos y resultados. RaceControl surge como una solución tecnológica para centralizar estos datos, facilitar la administración de las carreras y mejorar el control de los campeonatos.

---

# Definición de producto

## Fase A — Producto y experiencia

### Paso 1 — Interfaz de usuario y categorías

**Tipo de interfaz:** Desktop primero (pensada para pantallas de computador), con diseño responsive para que se pueda ver bien en tablet y celular.

**Categorías del producto:** Gestión de usuarios, Reportes, Catálogos, Dashboards, Configuración.

> Se quitó "IA / chatbot" porque el proyecto no usa IA (ver Paso 9).

### Paso 2 — Experiencia de usuario y comportamiento

- **Flujo:** Lineal (cada proceso se hace paso a paso, por ejemplo: elegir campeonato → elegir carrera → inscribir pilotos → registrar resultados).
- **Densidad:** Cómoda (espaciado generoso, nada apretado).
- **Feedback:** Modales para confirmaciones y detalles; toasts para avisos rápidos.
- **Navegación:** Tabs dentro de cada módulo (por ejemplo, en un campeonato: Información / Carreras / Inscritos / Posiciones).
- **Accesibilidad:** WCAG AAA. Contraste mínimo 7:1 en texto normal, navegación completa con teclado, foco visible, etiquetas en todos los campos, textos alternativos y respeto a `prefers-reduced-motion`.

### Paso 3 — Componentes estándar de UI

- **Panel de control:** Modal superpuesto (preferencias del usuario y ajustes rápidos).
- **Dropdown de opciones:** En el header (perfil, cambiar contraseña, cerrar sesión).
- **Sidebar, disposición:** Colapsable.
- **Sidebar, comportamiento:** Hover-expand (se expande al pasar el mouse; con teclado se expande al enfocar).
- **Menú hamburguesa:** Híbrido (en pantallas pequeñas reemplaza al sidebar; en escritorio solo fija o suelta el sidebar).

### Paso 4 — Elementos de UI y comportamiento UX

- **Formularios:** Validación inline (el mensaje aparece junto al campo, también en el servidor).
- **Tablas:** Paginación (10, 25 o 50 filas), con búsqueda, filtros y orden por columna.
- **Notificaciones:** Toast (éxito, error, aviso), con `aria-live`.
- **Estados vacíos:** Con guía (mensaje temático y botón de acción, por ejemplo "Aún no hay pilotos. Registra el primero").
- **Loaders:** Spinner con la temática gótica.
- **Confirmaciones destructivas:** Modal de confirmación. Al eliminar, se explica qué se borra y el botón es rojo.

### Paso 4.1 — Identidad visual gótica (nuevo)

**Concepto:** una pista de carreras nocturna dentro de una catedral gótica: oscuro, elegante y dramático, pero siempre legible.

**Paleta (verificar contraste con una herramienta antes de fijarla):**

| Uso | Color | Nota |
| --- | --- | --- |
| Fondo principal | `#0B0A0C` | Negro carbón |
| Superficie (tarjetas, tablas) | `#151218` | Gris humo |
| Borde | `#2A2430` | Líneas sutiles |
| Texto principal | `#EDE6DA` | Hueso, contraste alto sobre el fondo |
| Texto secundario | `#BDB3A6` | Mantener 7:1 mínimo |
| Acento principal | `#8B1A2B` | Burdeos (fondos de botones, con texto hueso encima) |
| Acento dorado | `#C9A227` | Títulos, bordes ornamentales, foco |
| Error | `#FF8A8A` | Sobre fondo oscuro |
| Éxito | `#7FD99A` | Sobre fondo oscuro |

**Tipografías:** `Cinzel` para títulos, `UnifrakturCook` solo para el logo "RaceControl", `EB Garamond` o `Lora` para el texto. En tablas y formularios se prioriza legibilidad (tamaño mínimo 16 px).

**Detalles de estilo:**

- Bordes dorados finos con esquinas ornamentales en tarjetas y modales (hechos con CSS o SVG, sin imágenes pesadas).
- Modales con marco tipo vitral.
- Separadores con filigrana, iconos de calaveras, banderas de cuadros, cálices y engranajes (SVG).
- Botón primario burdeos con borde dorado; secundario con solo borde.
- El anillo de foco debe ser dorado, grueso y visible.
- Animaciones suaves (niebla, brillo) que se desactivan con `prefers-reduced-motion`.
- Se implementa con Bootstrap 5 personalizado mediante variables CSS (`--rc-*`), no con CSS suelto repartido por todos lados.
- Gráficos con paleta gótica y patrones o etiquetas, nunca solo color para diferenciar datos.

## Fase B — Stack técnico y datos

### Paso 5 — Tecnologías, librerías y usos

- **Frontend:** HTML5, CSS3, JavaScript (ES6+).
- **Backend:** PHP 8.2 o superior, con Composer.
- **Librerías de UI:** Bootstrap 5 (personalizado con el tema gótico).
- **Gráficos:** Chart.js (barras, líneas y pastel).
- **Gestión de estado en el cliente:** JavaScript (DOM y localStorage), solo para preferencias de interfaz y borradores (ver Paso 14).
- **Testing:** PHPUnit para el backend.
- **Build:** Vite, solo para compilar y minimizar los assets (CSS y JS). No reemplaza a Apache ni a PHP.
- **Uso declarado:** estas herramientas permiten desarrollar RaceControl como una aplicación web dinámica, organizada y adaptable a diferentes dispositivos, facilitando la administración de campeonatos y competencias deportivas.

### Paso 6 — Estilo de backend y principios

- **Estilo:** API REST con respuestas JSON.
  > Se cambió desde GraphQL: en PHP con XAMPP, REST es más simple de construir, probar y explicar, y cubre todo lo que necesita RaceControl.
- **Principios:** SOLID, DRY, Clean Architecture, DDD, arquitectura hexagonal y 12-factor, aplicados de forma práctica (sin complicar de más):
  - **Capas:** `Dominio` (reglas y entidades) → `Aplicación` (casos de uso) → `Infraestructura` (MySQL, correo, sesiones) → `Presentación` (controladores REST y vistas).
  - **Puertos y adaptadores:** el dominio no conoce MySQL; usa interfaces de repositorio.
  - **12-factor:** configuración en `.env`, logs a salida estándar o archivo, dependencias declaradas en `composer.json` y `package.json`.
- **Estructura sugerida:**

```
racecontrol/
├── public/            # index.php, assets compilados
├── src/
│   ├── Domain/        # Piloto, Equipo, Vehiculo, Circuito, Campeonato, Carrera, Inscripcion, Resultado
│   ├── Application/   # casos de uso
│   ├── Infrastructure/# repositorios MySQL, correo, sesión
│   └── Presentation/  # controladores y rutas REST
├── resources/         # JS, SCSS/CSS, vistas
├── database/          # schema.sql, seeds.sql, migraciones
├── tests/
├── .env.example
└── composer.json / package.json / vite.config.js
```

### Paso 7 — Tipos de base de datos

- **Tipo:** Relacional (SQL), con MySQL (InnoDB, `utf8mb4`).
- **Justificación:** RaceControl necesita almacenar y relacionar información de pilotos, equipos, vehículos, circuitos y competencias. MySQL permite organizar los datos, evitar duplicaciones y hacer consultas eficientes.
- **Tablas principales (mínimo):**
  - `usuarios`, `roles`, `intentos_login`, `tokens_recuperacion`
  - `pilotos`, `equipos`, `vehiculos`, `circuitos`
  - `campeonatos`, `carreras`, `inscripciones`, `resultados`
  - `posiciones_campeonato` (agregada), `metricas_diarias` (agregada), `registro_actividad`
- **Reglas:** llaves foráneas, `UNIQUE` donde corresponda (por ejemplo, un piloto no se inscribe dos veces a la misma carrera), `created_at` y `updated_at` en todas las tablas, borrado lógico en pilotos, equipos y vehículos, y consultas siempre con sentencias preparadas (PDO).
- **Tipos de competencia:** `tipo_vehiculo` con valores `auto` y `moto`.

### Paso 8 — Procesamiento y analítica de datos

- **Ingesta:** Manual (formularios) y opcionalmente importación de resultados desde CSV.
- **Procesamiento:** pipelines ligeros dentro de la app (servicios PHP).
- **Transformación:** limpieza (quitar espacios, validar tiempos), normalización (formatos de fecha y tiempo `mm:ss.mmm`, un solo formato de nombres) y agregación (puntos por piloto, equipo y campeonato).
- **Almacenamiento analítico:** tablas agregadas (`posiciones_campeonato`, `metricas_diarias`), recalculadas cada vez que se guardan resultados y consolidadas una vez al día.
- **Sistema de puntos:** configurable por campeonato (por defecto 25-18-15-12-10-8-6-4-2-1).

## Fase C — Analítica (sin inteligencia artificial)

### Paso 9 — Agentes de IA

**Modo:** Sin IA. No se usan agentes, runtime local, proveedor online, modelo ni API key de IA.

### Paso 10 — Asistencia por IA y chatbot

**Estado:** No aplica, porque el proyecto no usa IA.

### Paso 11 — Métricas, KPI, EDA y visualización

**Métricas y KPI:**

1. **Uso por módulo:** cantidad de veces que se utiliza cada módulo. Unidad: número de usos. Se cuenta cada vez que un usuario abre un módulo (guardado en `registro_actividad`).
2. **Errores:** (cantidad de errores / total de operaciones) × 100. Unidad: porcentaje (%).
3. **Tiempo activo:** tiempo total de funcionamiento del sistema sin caídas. Unidad: horas. (Se calcula con un registro de latido o "heartbeat" y el log del servidor.)
4. **Pilotos registrados:** total de pilotos inscritos (activos) en el sistema. Unidad: número de pilotos.

**Estadísticas descriptivas:** media, mediana y moda. Se aplican a tiempos por vuelta, puntos por piloto y posiciones de llegada.

**EDA:**

- **Outliers:** tiempos fuera de rango (por ejemplo, con el método IQR) se marcan para revisión.
- **Valores nulos:** campos obligatorios vacíos o resultados sin tiempo se listan en un reporte de calidad de datos.
- **Tendencias:** evolución de puntos y tiempos de un piloto a lo largo de las carreras.

**Gráficos:** barras (uso por módulo, puntos por equipo), líneas (tendencia de tiempos y puntos) y pastel (distribución de pilotos por equipo o por tipo de vehículo).

**Frecuencia de actualización:** diaria (consolidación automática de las tablas agregadas) y en tiempo real para posiciones tras cada resultado guardado.

## Fase D — Operación, seguridad y distribución

### Paso 12 — Seguridad de login/logout

- **Método de login:** usuario y contraseña.
- **Duración de sesión:** máximo 8 horas.
- **Renovación:** deslizante (cada acción renueva la sesión, sin pasar nunca del máximo de 8 horas).
- **Cierre por inactividad:** 15 minutos sin actividad, con aviso un minuto antes.
- **Logout global:** sí, cerrar sesión invalida todas las sesiones del usuario.
- **2FA:** sin 2FA.
- **Bloqueo por intentos:** 5 intentos fallidos bloquean la cuenta 15 minutos (se registra en `intentos_login`).
- **Política de contraseñas:** fuerte, mínimo 12 caracteres, con mayúscula, minúscula, número y símbolo. No se permiten contraseñas comunes.
- **Recuperación:** por correo electrónico, con enlace de un solo uso que vence en 30 minutos.
- **Medidas obligatorias:** contraseñas con `password_hash` (Argon2id o bcrypt), `session_regenerate_id` al iniciar sesión, cookies `HttpOnly`, `Secure` y `SameSite`, token CSRF en formularios y peticiones que modifican datos, sentencias preparadas contra inyección SQL y escape de salida contra XSS.
- **Importante:** el token de sesión nunca se guarda en localStorage.

### Paso 13 — Mono usuario vs. multiusuario

- **Modelo:** multiusuario con roles.
- **Jerarquía:** un nivel (Admin, Editor, Lector, sin roles heredados).
- **Aislamiento de datos:** sin aislamiento (todos ven los mismos datos, según su permiso).

| Acción | Admin | Editor | Lector |
| --- | --- | --- | --- |
| Gestionar usuarios y roles | Sí | No | No |
| Configuración del sistema | Sí | No | No |
| Crear, editar y borrar catálogos | Sí | Crear y editar | No |
| Crear campeonatos y carreras | Sí | Sí | No |
| Gestionar inscripciones | Sí | Sí | No |
| Registrar y corregir resultados | Sí | Sí | No |
| Ver tablas, dashboards y reportes | Sí | Sí | Sí |
| Exportar reportes | Sí | Sí | Sí |

Los permisos se validan siempre en el backend; ocultar un botón en la interfaz no cuenta como seguridad.

### Paso 14 — Persistencia local

- **Modo:** localStorage.
- **PWA:** No (sin service worker, sin instalación, sin modo offline).
  > Se quitó la capacidad "Offline" porque contradecía "PWA: No".
- **Qué se guarda en localStorage:** preferencias de interfaz (estado del sidebar, tamaño de tabla, filtros recientes) y borradores de formularios largos.
- **Qué NO se guarda:** contraseñas, tokens, datos personales sensibles ni la base de datos.
- **Sincronización con backend:** automática para los borradores (al reconectar, se ofrece restaurar o descartar).
- **Estrategia de conflicto:** last write wins, usando `updated_at` del servidor. La fuente de verdad siempre es MySQL.

### Paso 15 — Compilación y plataforma destino

- **Compilación:** los assets del frontend se compilan con Vite a archivos estáticos en `public/assets`. El backend PHP se ejecuta directamente, sin compilar.
- **Plataformas:** Windows (desarrollo y pruebas con XAMPP), Linux (producción con Apache o Nginx + PHP-FPM) y Web (acceso desde cualquier navegador moderno).
- **Empaquetadores:** ninguno adicional (Composer para PHP y npm para JS).
- **Notas de despliegue:** RaceControl se desplegará como una aplicación web con HTML, CSS, JavaScript y PHP, con MySQL como base de datos. Se usa XAMPP y Apache para las pruebas locales. Después podrá alojarse en un servidor con soporte para PHP y MySQL, accesible desde diferentes dispositivos mediante un navegador. En producción: HTTPS obligatorio, `display_errors` apagado, variables en `.env` y copias de seguridad diarias de la base de datos.

### Paso 16 — Repositorio y acciones asociadas

- **¿Maneja repositorio?:** sí.
- **Proveedor:** GitHub.
- **Visibilidad:** privado durante el desarrollo. Se puede pasar a público al final, siempre que no haya secretos en el historial.
  > El documento original decía "Público" en la configuración y "privado" en las notas; se unificó así para proteger el código mientras se construye.
- **Acciones:** Init, Branch, Commit, Push, Pull, Merge, PR, CI/CD.
- **Estrategia de ramas:** feature branches. `main` siempre estable, `develop` para integrar y `feature/nombre-corto` para cada funcionalidad. Los cambios entran por Pull Request.
- **Commits:** mensajes cortos en español con prefijo (`feat:`, `fix:`, `docs:`, `test:`).
- **CI/CD (GitHub Actions):** en cada PR se ejecuta PHPUnit, revisión de estilo de código y el build de Vite. El despliegue es manual desde `main`.
- **Notas de repositorio:** se trabaja con ramas independientes por funcionalidad, commits para registrar cambios y merge para integrar, manteniendo la organización y facilitando el trabajo en equipo. Incluir `.gitignore` (con `.env`, `vendor/` y `node_modules/`) y `README.md` con instrucciones de instalación.

---

# Plan de construcción

1. **Base:** estructura de carpetas, `.env.example`, `schema.sql`, tema gótico (variables, layout, sidebar, header).
2. **Seguridad:** login, logout, bloqueo por intentos, recuperación por correo, roles y permisos.
3. **Catálogos:** pilotos, equipos, vehículos, circuitos.
4. **Competencias:** campeonatos y carreras.
5. **Inscripciones:** reglas (sin duplicados, vehículo compatible con el tipo de carrera, cupos).
6. **Resultados:** tiempos, posiciones, puntos y recálculo de tablas.
7. **Dashboards y reportes:** KPI, gráficos, estadísticas, exportación.
8. **Configuración:** usuarios, sistema de puntos, preferencias.
9. **Pruebas y despliegue:** PHPUnit, revisión de accesibilidad, build y publicación.

# Criterios de aceptación

- Todos los formularios validan en el cliente y en el servidor.
- Ninguna acción restringida funciona sin el rol correcto.
- Las tablas de posiciones coinciden con los resultados cargados.
- La interfaz cumple contraste 7:1 y se usa completa con teclado.
- No hay claves ni datos sensibles en el repositorio.
- Las pruebas de PHPUnit pasan antes de cada merge a `main`.

---

## Resumen de decisiones

| Fase | Paso | Estado | Resumen |
| --- | --- | --- | --- |
| A | 1. Interfaz y categorías | Completo | Desktop primero y responsive; Gestión de usuarios, Reportes, Catálogos, Dashboards, Configuración |
| A | 2. Experiencia de usuario | Completo | Lineal; cómoda; modales; tabs; WCAG AAA |
| A | 3. Componentes de UI | Completo | Modal superpuesto; dropdown en header; sidebar colapsable con hover-expand; hamburguesa híbrida |
| A | 4. Elementos de UI | Completo | Validación inline; paginación; toast; estados vacíos con guía; spinner; modal de confirmación |
| A | 4.1 Identidad visual | Completo | Gótica: negro, burdeos, dorado y hueso; Cinzel y EB Garamond |
| B | 5. Tecnologías | Completo | HTML5, CSS3, JS; PHP 8.2+; Bootstrap 5; Chart.js; PHPUnit; Vite solo para assets |
| B | 6. Backend | Completo | REST (JSON); SOLID, DRY, Clean Architecture, DDD, hexagonal, 12-factor |
| B | 7. Base de datos | Completo | MySQL relacional con tablas principales y agregadas |
| B | 8. Analítica de datos | Completo | Ingesta manual y CSV; pipelines en la app; limpieza, normalización y agregación; tablas agregadas |
| C | 9. Agentes de IA | Completo | Sin IA |
| C | 10. Chatbot | No aplica | El proyecto no usa IA |
| C | 11. Métricas | Completo | Uso por módulo, errores, tiempo activo, pilotos registrados; media, mediana, moda; outliers, nulos, tendencias; barras, líneas, pastel; actualización diaria |
| D | 12. Login/logout | Completo | Usuario/contraseña; 8 h deslizante; 15 min de inactividad; 5 intentos; contraseña fuerte; recuperación por email; sin 2FA |
| D | 13. Usuarios | Completo | Multiusuario con roles Admin, Editor, Lector, con matriz de permisos |
| D | 14. Persistencia local | Completo | localStorage para preferencias y borradores; sin PWA; MySQL como fuente de verdad |
| D | 15. Compilación y destino | Completo | Assets con Vite; PHP sin compilar; Windows (XAMPP), Linux y Web |
| D | 16. Repositorio | Completo | GitHub privado en desarrollo; feature branches; CI/CD con GitHub Actions |

---

## Registro de cambios de esta revisión

- Se agregó la sección **Cómo usar este archivo** para que funcione como prompt principal.
- Se agregó el **Paso 4.1** con la identidad visual gótica y una paleta pensada para WCAG AAA.
- Se quitó la categoría "IA / chatbot" del Paso 1 (contradecía "Sin IA").
- Se cambió GraphQL por REST (más simple para PHP y XAMPP).
- Se aclaró el uso de Vite (solo assets) y el despliegue (PHP no se compila).
- Se quitó "Offline" de la persistencia (contradecía "PWA: No") y se definió qué va en localStorage y qué no.
- Se unificó la visibilidad del repositorio (privado en desarrollo).
- Se aclararon las reglas de sesión (8 h máximo + 15 min de inactividad) y se agregaron medidas de seguridad concretas.
- Se agregó la matriz de permisos por rol, las tablas principales, el plan de construcción y los criterios de aceptación.
- Se eliminaron los avisos del generador ("0 ítems", "0 categorías").
