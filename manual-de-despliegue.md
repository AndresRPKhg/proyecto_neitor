# RaceControl · Manual de despliegue

*Versión 3.0 · Noviembre de 2026 · Para quien instala el sistema en un equipo o en un servidor*

Este manual cubre dos caminos: **A) XAMPP en Windows** (pruebas, clase o uso local) y **B) servidor Linux con Apache** (producción). Después vienen las tareas programadas, las copias de seguridad, las actualizaciones y una lista de revisión.

## 1. Requisitos

| Componente      | Versión                    | Nota                                                                                                        |
|-----------------|----------------------------|-------------------------------------------------------------------------------------------------------------|
| PHP             | 8.2 o superior             | Extensiones pdo_mysql y mbstring (obligatorias). sodium es opcional: con él se usa Argon2id; sin él, bcrypt |
| MySQL o MariaDB | MySQL 5.7+ / MariaDB 10.4+ | Motor InnoDB, utf8mb4                                                                                       |
| Apache          | 2.4                        | Módulos mod_rewrite y mod_headers; AllowOverride All para que lea los .htaccess                             |
| Node.js         | 20                         | **Solo** si vas a cambiar archivos de resources/ y recompilar. El proyecto ya trae los assets compilados    |
| Composer        | 2                          | **Solo** para correr PHPUnit. No hace falta en el servidor                                                  |

No se necesita instalar nada más en producción: no hay dependencias de PHP ni de Node en tiempo de ejecución.

## 2. Qué recibes

Una carpeta racecontrol/ con el código, database/racecontrol.sql (esquema, roles, datos de ejemplo y el administrador inicial) y .env.example. Cuanto menos tengas que tocar, mejor: solo se configura el archivo .env.

## 3. Camino A · XAMPP en Windows

1.  **Instala XAMPP** con una versión de PHP 8.2 o superior y abre el panel de control.

2.  **Copia la carpeta** a C:\xampp\htdocs\racecontrol.

3.  **Enciende Apache y MySQL** en el panel de XAMPP.

4.  **Importa la base de datos**:

    - Abre http://localhost/phpmyadmin.

    - Ve a **Importar**, elige database/racecontrol.sql y pulsa **Importar**. El archivo crea la base racecontrol por sí solo.

    - O desde consola: C:\xampp\mysql\bin\mysql.exe -u root \< C:\xampp\htdocs\racecontrol\database\racecontrol.sql

5.  **Crea el archivo .env**: copia .env.example y renómbralo a .env. Con XAMPP normalmente no hay que cambiar nada (usuario root, sin contraseña). Revisa que APP_URL sea http://localhost/racecontrol/public.

6.  **Abre** http://localhost/racecontrol/. Te redirige a /public y aparece el inicio de sesión.

7.  **Entra con el administrador inicial** (usuario admin, ver la sección 7) y **cambia la contraseña de inmediato**.

Si Apache no lee las reglas de reescritura (ves «Not Found» al entrar a cualquier ruta que no sea la raíz), abre C:\xampp\apache\conf\httpd.conf, busca el bloque \<Directory "C:/xampp/htdocs"\> y cambia AllowOverride None por AllowOverride All. Reinicia Apache.

**Correo en XAMPP.** Con MAIL_DRIVER=log (el valor por defecto) los correos de recuperación **no se envían**: se escriben en storage/logs/correos.log. Para probar la recuperación, pide el enlace en pantalla y cópialo desde ese archivo.

## 4. Camino B · Servidor Linux con Apache

Los comandos son para Ubuntu o Debian. Ajusta rutas y nombres a tu caso.

### 4.1 Paquetes

sudo apt update  
sudo apt install apache2 php php-mysql php-mbstring mariadb-server unzip  
sudo a2enmod rewrite headers

Verifica la versión con php -v (debe ser 8.2 o más) y los módulos con php -m \| grep -E "pdo_mysql\|mbstring".

### 4.2 Código

sudo mkdir -p /var/www/racecontrol  
sudo unzip racecontrol.zip -d /var/www/  
sudo chown -R www-data:www-data /var/www/racecontrol/storage  
sudo chmod -R 750 /var/www/racecontrol/storage

Solo storage/ necesita permiso de escritura para el usuario del servidor web (guarda registros y el log de correos). El resto del código puede ser de solo lectura.

### 4.3 Base de datos con usuario propio

**No uses root en producción.** Crea un usuario que solo pueda leer y escribir datos de esta base:

sudo mysql \< /var/www/racecontrol/database/racecontrol.sql  
sudo mysql -e "CREATE USER 'racecontrol'@'localhost' IDENTIFIED BY 'UNA_CLAVE_LARGA_Y_UNICA';  
GRANT SELECT,INSERT,UPDATE,DELETE ON racecontrol.\* TO 'racecontrol'@'localhost';  
FLUSH PRIVILEGES;"

La aplicación no ejecuta cambios de estructura mientras funciona, por eso ese usuario no necesita CREATE ni ALTER.

### 4.4 Configuración .env

cd /var/www/racecontrol  
sudo cp .env.example .env  
sudo nano .env  
sudo chown root:www-data .env && sudo chmod 640 .env

Valores típicos de producción:

APP_ENV=production  
APP_URL=https://campeonatos.tudominio.com  
APP_TIMEZONE=America/Bogota  
  
DB_HOST=127.0.0.1  
DB_PORT=3306  
DB_NAME=racecontrol  
DB_USER=racecontrol  
DB_PASS=UNA_CLAVE_LARGA_Y_UNICA  
  
MAIL_DRIVER=mail  
MAIL_FROM=no-reply@tudominio.com  
REGISTRO_ABIERTO=true

Con APP_ENV=production la cookie de sesión se marca Secure (solo viaja por HTTPS) y los errores internos ya no muestran detalles técnicos. Por eso **producción debe usar HTTPS**. APP_URL debe ser exactamente la dirección pública, porque con ella se arma el enlace del correo de recuperación.

### 4.5 Sitio de Apache

La forma recomendada es que el servidor apunte **directo a public/**, de modo que el código ni siquiera quede dentro de la raíz web:

\<VirtualHost \*:80\>  
ServerName campeonatos.tudominio.com  
DocumentRoot /var/www/racecontrol/public  
  
\<Directory /var/www/racecontrol/public\>  
AllowOverride All  
Require all granted  
\</Directory\>  
  
ErrorLog \${APACHE_LOG_DIR}/racecontrol_error.log  
CustomLog \${APACHE_LOG_DIR}/racecontrol_access.log combined  
\</VirtualHost\>

sudo nano /etc/apache2/sites-available/racecontrol.conf \# pega el bloque de arriba  
sudo a2ensite racecontrol  
sudo systemctl reload apache2

Con este esquema la aplicación queda en la raíz del dominio y la ruta base es vacía; no se usa el index.php de la raíz del proyecto.

### 4.6 HTTPS

La manera más simple es Let's Encrypt:

sudo apt install certbot python3-certbot-apache  
sudo certbot --apache -d campeonatos.tudominio.com

Certbot agrega el sitio en el puerto 443 y renueva el certificado solo. Comprueba que http:// redirija a https://.

### 4.7 Correo

MAIL_DRIVER=mail usa la función mail() de PHP, que depende de que el servidor tenga un programa de correo configurado (por ejemplo Postfix). Si los correos no llegan o caen en spam, es un tema del servidor de correo, no de RaceControl. Mientras tanto, con MAIL_DRIVER=log puedes leer los enlaces en storage/logs/correos.log y dárselos a la persona.

## 5. Tareas programadas

El sistema usa dos scripts de consola para sus indicadores. No son obligatorios para que funcione, pero sin ellos el **tiempo activo** y las métricas diarias quedan incompletos.

| Script             | Frecuencia     | Para qué                       |
|--------------------|----------------|--------------------------------|
| bin/latido.php     | Cada 5 minutos | Suma tiempo activo del sistema |
| bin/consolidar.php | Una vez al día | Resume las métricas de ayer    |

**Linux (cron).** Ejecuta sudo crontab -u www-data -e y agrega:

\*/5 \* \* \* \* /usr/bin/php /var/www/racecontrol/bin/latido.php  
10 0 \* \* \* /usr/bin/php /var/www/racecontrol/bin/consolidar.php

**Windows (Programador de tareas).** En una consola de administrador:

schtasks /create /sc minute /mo 5 /tn "RaceControl latido" /tr "C:\xampp\php\php.exe C:\xampp\htdocs\racecontrol\bin\latido.php"  
schtasks /create /sc daily /st 00:10 /tn "RaceControl consolidar" /tr "C:\xampp\php\php.exe C:\xampp\htdocs\racecontrol\bin\consolidar.php"

Para consolidar un día atrasado: php bin/consolidar.php 2026-11-20.

## 6. Registro público de usuarios

La pantalla de inicio de sesión permite **Crear cuenta**, y toda cuenta nueva nace con rol **Lector**. Para decidir si lo dejas abierto:

| Situación                    | Recomendación                                                                                                                                    |
|------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------|
| Red interna, clase o pruebas | Déjalo abierto (REGISTRO_ABIERTO=true)                                                                                                           |
| Internet público             | Ciérralo (REGISTRO_ABIERTO=false) o añade verificación de correo y CAPTCHA antes                                                                 |
| Detrás de un proxy inverso   | El límite de 5 registros por hora se calcula por la IP que ve PHP; con proxy todos compartirían IP. Configúrala en el proxy o cierra el registro |

Para cerrarlo: cambia REGISTRO_ABIERTO=false en .env (no hace falta reiniciar nada). El enlace desaparece del login, /registro responde 404 y la API rechaza el intento. Quien se haya registrado antes conserva su cuenta.

Para subir a alguien de Lector a Editor o Admin, un administrador lo hace en **Usuarios y roles**.

## 7. Primer acceso y cuenta de administrador

El archivo SQL trae un administrador inicial: usuario **admin**, correo admin@racecontrol.local. La contraseña inicial está escrita en el archivo README.md y en las pruebas, que **no deben llegar a un servidor expuesto sin cambiarla**.

Checklist del primer día:

1.  Entra con admin y cambia la contraseña: menú de usuario → **Cambiar contraseña**.

2.  Cambia el correo del administrador por uno real (módulo **Usuarios y roles**), para poder recuperar la cuenta.

3.  Quita la contraseña inicial del README.md de la copia que dejes en el servidor.

Si pierdes el acceso de administrador, en el servidor ejecuta:

cd /var/www/racecontrol  
php bin/crear_admin.php usuario correo@dominio.com "Nombre Completo"

El script pide la contraseña por consola (también acepta la variable RC_ADMIN_PASS en entornos automatizados).

## 8. Datos de ejemplo

racecontrol.sql incluye datos de demostración: 3 equipos, 4 pilotos, 4 vehículos, 2 circuitos y el campeonato «Copa Catedral» 2026 con una carrera. En producción real, elimínalos desde el sistema (con el rol Admin), empezando por la carrera y el campeonato, o importa solo el esquema y los roles. Ten presente que tests/humo.py **espera** esos datos: solo se corre en una base de prueba, nunca en la de producción.

## 9. Verificar que todo funciona

**Verificación rápida (a mano):**

1.  Abre la dirección del sistema: ves el inicio de sesión con candado en el navegador si usas HTTPS.

2.  Inicia sesión con el administrador.

3.  Abre **Panel principal**: cargan los indicadores y las gráficas.

4.  Crea una cuenta nueva desde **Crear cuenta** en una ventana privada: debe entrar como Lector y no ver el menú de usuarios.

5.  Abre https://tu-dominio/.env y https://tu-dominio/src/: ambos deben dar **403 o 404**, nunca mostrar contenido.

**Prueba completa (solo en una base de prueba):**

mysql -u root -e "DROP DATABASE IF EXISTS racecontrol"  
mysql -u root \< database/racecontrol.sql  
python3 tests/humo.py http://localhost/racecontrol/public

Debe terminar con RESULTADO: TODO BIEN. Si se corre dos veces sin reimportar la base, fallará porque los usuarios de prueba ya existen (y el límite de registros por hora también estará consumido).

## 10. Copias de seguridad

Lo único que cambia con el uso es la base de datos (y storage/logs). El código se recupera del paquete original.

**Copia diaria (Linux):**

mysqldump -u racecontrol -p --single-transaction racecontrol \| gzip \> /backups/racecontrol\_\$(date +%F).sql.gz

Progámala con cron (por ejemplo, 30 2 \* \* \*) y borra las copias de más de 30 días. Guarda copias fuera del servidor.

**Restaurar:**

mysql -u root -e "DROP DATABASE IF EXISTS racecontrol; CREATE DATABASE racecontrol CHARACTER SET utf8mb4;"  
gunzip -c /backups/racecontrol_2026-11-20.sql.gz \| mysql -u root racecontrol

**En XAMPP:** desde phpMyAdmin, pestaña **Exportar** con el método rápido, y **Importar** para restaurar. Guarda también tu archivo .env.

Prueba una restauración al menos una vez: una copia que nunca se ha restaurado no es una copia confiable.

## 11. Actualizar a una versión nueva

1.  **Haz una copia** de la base de datos y del archivo .env.

2.  Descarga el paquete nuevo y revisa si trae archivos .sql de cambios (migraciones). Esta versión 3.0 **no cambió la estructura de la base de datos** respecto a la anterior, así que no hay que migrar nada.

3.  Reemplaza el código (src, resources, public, bin, database, tests, archivos de la raíz) **sin pisar** .env ni storage/logs.

4.  Compara tu .env con el nuevo .env.example y agrega las variables que falten. Para la 3.0, la nueva es REGISTRO_ABIERTO; si no la pones, el registro queda **abierto** por defecto.

5.  Recarga Apache si cambiaste su configuración, y abre el sistema con un refresco forzado (Ctrl+F5) para que el navegador baje los assets nuevos.

6.  Haz la verificación rápida de la sección 9.

**Volver atrás:** devuelve el código anterior; si hubo cambios de base de datos, restaura la copia del paso 1.

## 12. Endurecimiento para producción

- [ ] HTTPS activo y APP_ENV=production.

- [ ] Contraseña del administrador inicial cambiada y quitada de README.md.

- [ ] Usuario de base de datos propio, sin root y sin permisos de estructura.

- [ ] .env con permisos 640 y fuera del control de versiones.

- [ ] Datos de ejemplo eliminados.

- [ ] Decidido y configurado REGISTRO_ABIERTO.

- [ ] DocumentRoot apuntando a public/.

- [ ] Copias de seguridad programadas y probadas.

- [ ] Tareas latido y consolidar programadas.

- [ ] PHP y el sistema operativo con actualizaciones al día; display_errors = Off en php.ini.

## 13. Solución de problemas

| Síntoma                                    | Causa probable                                     | Qué hacer                                                                                       |
|--------------------------------------------|----------------------------------------------------|-------------------------------------------------------------------------------------------------|
| Página en blanco o error 500               | Error de PHP o de base de datos                    | Mira el registro de errores de Apache/PHP. En pruebas, pon APP_ENV=local para ver el mensaje    |
| Access denied for user                     | DB_USER o DB_PASS mal puestos                      | Revisa .env. En MariaDB, root por socket no entra por TCP: usa un usuario propio con contraseña |
| Unknown database 'racecontrol'             | No se importó el SQL                               | Importa database/racecontrol.sql                                                                |
| Todas las rutas dan 404 menos la raíz      | Apache no lee .htaccess                            | Activa mod_rewrite y AllowOverride All                                                          |
| «Token de seguridad inválido» (419)        | Sesión o cookie perdida; cache vieja               | Recarga la página. Con APP_ENV=production sin HTTPS la cookie Secure no se guarda: usa HTTPS    |
| Entra y al instante lo saca al login       | Cookie Secure sobre HTTP, o reloj del servidor mal | Usa HTTPS o APP_ENV=local en pruebas; revisa la hora del servidor                               |
| El enlace de recuperación no funciona      | APP_URL incorrecto o correo no enviado             | Corrige APP_URL; con MAIL_DRIVER=log lee storage/logs/correos.log                               |
| No se puede escribir en storage/logs       | Permisos                                           | chown -R www-data storage (Linux) o revisa permisos de carpeta (Windows)                        |
| Estilos rotos o sin botones                | Assets viejos en caché, o public/assets incompleto | Ctrl+F5; verifica que existan app.js y app.css en public/assets                                 |
| «Demasiados registros desde esta conexión» | Límite de 5 por hora por IP                        | Espera una hora, o crea la cuenta desde **Usuarios y roles**                                    |
| Tiempo activo en 0                         | No están programadas las tareas                    | Sección 5                                                                                       |

Si nada de esto lo resuelve, reúne el mensaje exacto, la hora y las últimas líneas del registro de errores y pásalos a quien mantiene el código (ver el manual técnico).
