# RaceControl · Manual de usuario

*Versión 3.0 · Noviembre de 2026 · Para quien usa el sistema día a día (Admin, Editor y Lector)*

## 1. ¿Qué es RaceControl?

RaceControl es un sistema web para organizar campeonatos de autos y motos. Aquí se registran los pilotos, los equipos, los vehículos y los circuitos; se crean campeonatos y carreras; se inscribe a los pilotos; se cargan los tiempos y el sistema calcula solo las posiciones y los puntos. También tiene un panel con gráficas y reportes que se pueden exportar a Excel (CSV).

La interfaz tiene estilo gótico: fondo oscuro, letras doradas y mucho contraste para que se lea bien.

## 2. Entrar al sistema

### 2.1 Iniciar sesión

1.  Abre la dirección que te dio el administrador (por ejemplo http://localhost/racecontrol/).

2.  Escribe tu **usuario o correo** y tu **contraseña**.

3.  Pulsa **Entrar**.

Si te equivocas 5 veces seguidas, la cuenta se **bloquea 15 minutos** para protegerla. Pasado ese tiempo puedes intentar de nuevo.

### 2.2 Crear una cuenta nueva

Si todavía no tienes cuenta:

1.  En la pantalla de inicio de sesión pulsa **Crear cuenta**.

2.  Llena el formulario:

    - **Nombre completo**.

    - **Usuario**: de 3 a 40 caracteres; solo letras, números, punto, guion y guion bajo (sin espacios).

    - **Correo electrónico**: debe ser válido y no estar usado por otra cuenta.

    - **Contraseña** y **Repite la contraseña** (ver reglas abajo).

3.  Pulsa **Crear cuenta**. Entras directo al sistema.

**Tu cuenta nueva siempre es de rol Lector**: puedes consultar y exportar reportes, pero no crear ni modificar datos. Si necesitas más permisos, pídele a un administrador que te cambie el rol.

**Reglas de la contraseña** (valen para todas las cuentas):

- Mínimo 12 caracteres.

- Al menos una mayúscula, una minúscula, un número y un símbolo.

- No puede ser una contraseña muy común ni contener tu nombre de usuario.

Por seguridad, desde una misma conexión solo se pueden crear 5 cuentas por hora. Además, el administrador puede cerrar el registro; en ese caso no verás el enlace «Crear cuenta».

### 2.3 Olvidé mi contraseña

1.  En el inicio de sesión pulsa **¿Olvidaste tu contraseña?**

2.  Escribe tu usuario o correo y pulsa **Enviar enlace**.

3.  Te llega un correo con un enlace. Sirve **una sola vez** y vence a los **30 minutos**.

4.  Ábrelo, escribe la contraseña nueva dos veces y pulsa **Guardar contraseña**.

Por seguridad, el mensaje es el mismo exista o no la cuenta. Si no te llega el correo, avisa al administrador.

### 2.4 Cuidar tu sesión

- Si pasas **15 minutos sin usar** el sistema, la sesión se cierra. Un minuto antes aparece el aviso «Tu sesión se cerrará en un minuto por inactividad» con el botón **Seguir conectado**.

- Una sesión dura como máximo **8 horas**.

- **Cerrar sesión** (menú de usuario) cierra tu sesión en **todos** los dispositivos donde estés conectado.

## 3. Los roles: qué puede hacer cada uno

| Acción                                                                   | Admin | Editor | Lector |
|--------------------------------------------------------------------------|-------|--------|--------|
| Ver catálogos, campeonatos, carreras, inscritos, resultados y posiciones | Sí    | Sí     | Sí     |
| Ver el panel principal y los reportes                                    | Sí    | Sí     | Sí     |
| Exportar reportes a CSV                                                  | Sí    | Sí     | Sí     |
| Crear y editar pilotos, equipos, vehículos y circuitos                   | Sí    | Sí     | No     |
| Crear y editar campeonatos y carreras                                    | Sí    | Sí     | No     |
| Inscribir pilotos y registrar resultados                                 | Sí    | Sí     | No     |
| Eliminar registros                                                       | Sí    | No     | No     |
| Administrar usuarios y roles                                             | Sí    | No     | No     |
| Cambiar la configuración del sistema                                     | Sí    | No     | No     |

Los botones que no puedes usar no aparecen. Aunque alguien intente entrar por otro camino, el sistema revisa los permisos y lo rechaza.

## 4. Moverte por la pantalla

**Menú lateral** (izquierda), agrupado así:

- **Dashboards**: Panel principal.

- **Catálogos**: Pilotos, Equipos, Vehículos, Circuitos.

- **Competencias**: Campeonatos.

- **Reportes**.

- **Gestión de usuarios**: Usuarios y roles (solo Admin).

- **Configuración**: Sistema (solo Admin).

**Panel de control** (icono del engranaje, arriba a la derecha). Aquí ajustas a tu gusto:

- Filas por página en las tablas.

- Mantener el menú lateral fijo abierto.

- Animaciones de niebla y brillo (apágalas si te mareas o tu equipo va lento).

- **Restablecer** para volver a los valores de fábrica.

**Menú de usuario**: **Mi perfil** (tu nombre, usuario y correo), **Cambiar contraseña** y **Cerrar sesión**.

El enlace **Saltar al contenido** (primer elemento al usar el teclado) te lleva directo a lo importante. Todo el sistema se puede usar solo con teclado.

## 5. Panel principal

Es la pantalla de inicio. Muestra:

- **Indicadores clave** (últimos 30 días): pilotos registrados, porcentaje de errores, tiempo activo en horas y uso de cada módulo.

- **Gráficas**: uso por módulo, puntos por equipo, tendencia de puntos de un piloto carrera a carrera, pilotos por equipo y vehículos por tipo. Con los filtros **Campeonato** y **Piloto** cambias lo que se dibuja.

- Debajo de cada gráfica, **Ver los datos en tabla** muestra los mismos números en texto.

- **Estadística descriptiva** (media, mediana y moda), **tiempos fuera de rango** para revisar y un chequeo de **calidad de datos** (campos vacíos o faltantes).

## 6. Catálogos: pilotos, equipos, vehículos y circuitos

Todos funcionan igual.

**Ver y buscar.** La tabla tiene un cuadro **Buscar**, orden al pulsar el título de una columna y paginación abajo.

**Crear** (Admin y Editor): pulsa el botón de nuevo registro, llena el formulario y guarda. Los errores aparecen justo debajo del campo. Si cierras el formulario sin guardar, el sistema guarda un **borrador** en tu navegador y te ofrece **Restaurar** o descartarlo (las contraseñas nunca se guardan).

**Editar**: botón de editar en la fila. **Eliminar** (solo Admin): pide confirmación. En pilotos, equipos y vehículos el borrado es «lógico»: el registro deja de verse pero el historial de carreras se conserva.

Campos y reglas de cada catálogo:

| Catálogo  | Campos (\* obligatorios)                                                         | Reglas                                                                            |
|-----------|----------------------------------------------------------------------------------|-----------------------------------------------------------------------------------|
| Pilotos   | Nombre\*, Apellido\*, Documento\*, Fecha de nacimiento\*, Nacionalidad\*, Equipo | El documento no se repite. La fecha no puede ser futura.                          |
| Equipos   | Nombre\*, País\*, Año de fundación                                               | El nombre no se repite. Año entre 1900 y el año actual.                           |
| Vehículos | Tipo\* (auto o moto), Marca\*, Modelo\*, Placa\*, Año\*, Equipo                  | La placa no se repite y se guarda en mayúsculas. Año entre 1950 y el próximo año. |
| Circuitos | Nombre\*, País\*, Ciudad\*, Longitud (km)\*                                      | El nombre no se repite. Longitud entre 0,1 y 50 km.                               |

Los nombres escritos todo en minúsculas o todo en mayúsculas se corrigen solos (por ejemplo, «ana ruiz» queda «Ana Ruiz»).

## 7. Campeonatos, carreras y resultados

El flujo es lineal, de izquierda a derecha por pestañas:

**Información → Carreras → Inscritos → Resultados → Posiciones**

### 7.1 Crear un campeonato

En **Campeonatos** crea uno nuevo con: **Nombre**, **Temporada** (año), **Tipo de vehículo** (auto o moto) y **Puntos por posición** (números separados por coma). Por defecto: 25,18,15,12,10,8,6,4,2,1; es decir, 25 puntos al 1.º, 18 al 2.º, y así hasta el 10.º. No puede haber dos campeonatos con el mismo nombre y temporada.

Abre el campeonato para ver sus pestañas.

### 7.2 Crear carreras

En la pestaña **Carreras** agrega cada carrera con: **Circuito**, **Nombre**, **Fecha**, **Cupo máximo de pilotos** (de 2 a 100, por defecto 20) y **Número de vueltas** (por defecto 10). La carrera hereda el tipo de vehículo del campeonato y nace en estado **programada**.

### 7.3 Inscribir pilotos

En **Inscritos** elige la carrera, un **piloto** y un **vehículo**, y pulsa para inscribir. El sistema rechaza la inscripción si:

- el piloto ya está inscrito en esa carrera;

- el vehículo ya está inscrito en esa carrera;

- el vehículo no es del mismo tipo que la carrera (una moto en una carrera de autos);

- ya no hay cupos;

- la carrera ya finalizó.

Puedes quitar una inscripción mientras la carrera no esté finalizada.

### 7.4 Registrar resultados

En **Resultados** elige la carrera. Verás una fila por piloto inscrito:

- **Estado**: *clasificado*, *DNF* (no terminó) o *DSQ* (descalificado).

- **Tiempo total** en formato **mm:ss.mmm**, por ejemplo 45:12.345. Es obligatorio para los clasificados.

- **Mejor vuelta** (opcional) con el mismo formato.

Pulsa **Guardar resultados**. El sistema ordena por tiempo, asigna la posición, calcula los puntos y marca la carrera como **finalizada**. Un DNF o DSQ no suma posición ni puntos.

**Importar desde CSV**: pulsa **Importar CSV** y elige un archivo con las columnas documento,tiempo,mejor_vuelta,estado (la columna estado es opcional). Ejemplo:

documento,tiempo,mejor_vuelta,estado  
1002,45:12.345,01:51.200,clasificado  
1001,45:30.100,01:52.000,clasificado  
1003,,,dnf

Si alguna línea tiene un problema (documento no inscrito, tiempo mal escrito), **no se importa nada** y el sistema te dice qué línea corregir.

### 7.5 Tabla de posiciones

La pestaña **Posiciones** muestra la clasificación del campeonato con puntos, victorias, podios y carreras. Si dos pilotos empatan en puntos, gana el que tenga más victorias, y luego más podios. Con **Exportar** la bajas a CSV.

### 7.6 Cosas que conviene saber

- Si cambias el sistema de puntos de un campeonato, **sus resultados se recalculan solos**.

- No puedes cambiar el tipo de vehículo de un campeonato que ya tiene carreras.

- No puedes mover a otro campeonato una carrera con inscritos o resultados.

- Un campeonato con carreras no se puede eliminar: primero se eliminan sus carreras (solo Admin).

## 8. Reportes

En **Reportes** elige el tipo, completa el filtro que pida (campeonato o carrera) y pulsa **Ver**. Con **Exportar CSV** descargas el archivo, que abre bien en Excel.

| Reporte                 | Qué muestra                             |
|-------------------------|-----------------------------------------|
| Tabla de posiciones     | Clasificación de un campeonato          |
| Resultados              | Resultados de una carrera               |
| Listado de pilotos      | Todos los pilotos activos               |
| Estadística descriptiva | Media, mediana y moda                   |
| Tiempos fuera de rango  | Tiempos raros (método IQR) para revisar |
| Calidad de datos        | Campos nulos o faltantes                |

## 9. Guía para administradores

### 9.1 Usuarios y roles

En **Usuarios y roles** puedes crear, editar y eliminar cuentas. Cada cuenta tiene **Nombre**, **Usuario**, **Correo**, **Rol**, **Contraseña** y **Cuenta activa**.

- Las personas que se registran solas llegan como **Lector**. Para darles más permisos, edita su cuenta y cambia el **Rol** a Editor o Admin.

- Al editar, deja la contraseña vacía si no quieres cambiarla.

- Para suspender a alguien sin borrarlo, desmarca **Cuenta activa**.

- Si cambias el rol, desactivas una cuenta o cambias su contraseña, **se cierran todas sus sesiones** y debe entrar de nuevo.

- Siempre debe quedar **al menos un administrador activo**, y nadie puede eliminar su propia cuenta.

### 9.2 Configuración del sistema

En **Configuración → Sistema** defines los **puntos por posición por defecto** que se proponen al crear campeonatos nuevos. Escribe números enteros separados por coma, por ejemplo 25,18,15.

### 9.3 Primer día

1.  Entra con la cuenta de administrador inicial que te entregó quien instaló el sistema.

2.  Cambia la contraseña de inmediato: **menú de usuario → Cambiar contraseña**.

3.  Crea las cuentas de tu equipo o pídeles que se registren y luego ajusta sus roles.

## 10. Problemas frecuentes

| Me pasa esto                               | Qué hacer                                                                                          |
|--------------------------------------------|----------------------------------------------------------------------------------------------------|
| «Usuario o contraseña incorrectos»         | Revisa mayúsculas y minúsculas. Si sigues fallando, usa «¿Olvidaste tu contraseña?».               |
| «Cuenta bloqueada»                         | Espera los minutos que indica el mensaje o pide el enlace de recuperación.                         |
| La contraseña no es válida al registrarme  | Usa 12 o más caracteres con mayúscula, minúscula, número y símbolo, sin tu usuario dentro.         |
| «Ese usuario o correo ya está registrado»  | Elige otro usuario, o usa «¿Olvidaste tu contraseña?» si ya tenías cuenta.                         |
| «Demasiados registros desde esta conexión» | Espera una hora o pídele una cuenta al administrador.                                              |
| No veo el enlace «Crear cuenta»            | El registro está cerrado: pide una cuenta al administrador.                                        |
| No veo botones de crear o editar           | Tu rol es Lector. Pide al administrador que lo cambie.                                             |
| «Tu sesión terminó»                        | Pasaron más de 15 minutos sin actividad. Vuelve a entrar.                                          |
| «Usa el formato mm:ss.mmm»                 | Escribe el tiempo así: 45:12.345 (minutos, segundos y milésimas).                                  |
| El CSV no se importó                       | Revisa que cada documento esté inscrito en esa carrera y que la cabecera tenga documento y tiempo. |
| «La carrera no tiene cupos disponibles»    | Sube el cupo máximo de la carrera (solo Admin o Editor) o inscribe en otra carrera.                |

Si nada de esto resuelve tu caso, avisa al administrador con el texto exacto del mensaje que ves en pantalla.
