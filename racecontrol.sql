SET NAMES utf8mb4;
-- RaceControl · esquema MySQL (InnoDB, utf8mb4)
CREATE DATABASE IF NOT EXISTS racecontrol CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE racecontrol;

-- Importar en phpMyAdmin (pestaña Importar). Los usuarios se agregan desde /registro con rol 'lector'
-- y contraseña cifrada (password_hash Argon2id/bcrypt) en usuarios.password_hash.
-- ===== Seguridad y usuarios =====
CREATE TABLE IF NOT EXISTS roles (
  id TINYINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  nombre VARCHAR(20) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  nombre VARCHAR(120) NOT NULL,
  usuario VARCHAR(40) NOT NULL UNIQUE,
  email VARCHAR(160) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  rol_id TINYINT UNSIGNED NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  bloqueado_hasta DATETIME NULL,
  sesion_version INT UNSIGNED NOT NULL DEFAULT 1, -- subir este número invalida todas las sesiones (logout global)
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (rol_id) REFERENCES roles(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS intentos_login (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  usuario VARCHAR(40) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  exitoso TINYINT(1) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_intentos (usuario, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tokens_recuperacion (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  usuario_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expira_at DATETIME NOT NULL,
  usado_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ===== Catálogos =====
CREATE TABLE IF NOT EXISTS equipos (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  nombre VARCHAR(100) NOT NULL UNIQUE,
  pais VARCHAR(60) NOT NULL,
  anio_fundacion SMALLINT UNSIGNED NULL,
  deleted_at DATETIME NULL, -- borrado lógico
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pilotos (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  nombre VARCHAR(80) NOT NULL,
  apellido VARCHAR(80) NOT NULL,
  documento VARCHAR(30) NOT NULL UNIQUE,
  fecha_nacimiento DATE NOT NULL,
  nacionalidad VARCHAR(60) NOT NULL,
  equipo_id INT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (equipo_id) REFERENCES equipos(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vehiculos (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  tipo_vehiculo ENUM('auto','moto') NOT NULL,
  marca VARCHAR(60) NOT NULL,
  modelo VARCHAR(60) NOT NULL,
  placa VARCHAR(20) NOT NULL UNIQUE,
  anio SMALLINT UNSIGNED NOT NULL,
  equipo_id INT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (equipo_id) REFERENCES equipos(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS circuitos (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  nombre VARCHAR(100) NOT NULL UNIQUE,
  pais VARCHAR(60) NOT NULL,
  ciudad VARCHAR(80) NOT NULL,
  longitud_km DECIMAL(6,3) NOT NULL,
  mapa VARCHAR(120) NULL, -- archivo del mapa de la pista (storage/mapas)
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ===== Competencias =====
CREATE TABLE IF NOT EXISTS campeonatos (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  nombre VARCHAR(120) NOT NULL,
  temporada SMALLINT UNSIGNED NOT NULL,
  tipo_vehiculo ENUM('auto','moto') NOT NULL,
  puntos VARCHAR(100) NOT NULL DEFAULT '25,18,15,12,10,8,6,4,2,1', -- puntos por posición, separados por coma
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_campeonato (nombre, temporada)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS carreras (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  campeonato_id INT UNSIGNED NOT NULL,
  circuito_id INT UNSIGNED NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  fecha DATE NOT NULL,
  tipo_vehiculo ENUM('auto','moto') NOT NULL, -- se hereda del campeonato
  cupo_maximo SMALLINT UNSIGNED NOT NULL DEFAULT 20,
  vueltas SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  estado ENUM('programada','finalizada') NOT NULL DEFAULT 'programada',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (campeonato_id) REFERENCES campeonatos(id),
  FOREIGN KEY (circuito_id) REFERENCES circuitos(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inscripciones (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  carrera_id INT UNSIGNED NOT NULL,
  piloto_id INT UNSIGNED NOT NULL,
  vehiculo_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_piloto_carrera (carrera_id, piloto_id),     -- un piloto no se inscribe dos veces
  UNIQUE KEY uq_vehiculo_carrera (carrera_id, vehiculo_id), -- un vehículo no corre dos veces
  FOREIGN KEY (carrera_id) REFERENCES carreras(id) ON DELETE CASCADE,
  FOREIGN KEY (piloto_id) REFERENCES pilotos(id),
  FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS resultados (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  inscripcion_id INT UNSIGNED NOT NULL UNIQUE,
  estado ENUM('clasificado','dnf','dsq') NOT NULL DEFAULT 'clasificado',
  posicion SMALLINT UNSIGNED NULL,
  tiempo_ms INT UNSIGNED NULL,
  mejor_vuelta_ms INT UNSIGNED NULL,
  puntos DECIMAL(6,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (inscripcion_id) REFERENCES inscripciones(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ===== Tablas agregadas y actividad =====
CREATE TABLE IF NOT EXISTS posiciones_campeonato (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  campeonato_id INT UNSIGNED NOT NULL,
  piloto_id INT UNSIGNED NOT NULL,
  puntos DECIMAL(8,2) NOT NULL DEFAULT 0,
  victorias SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  podios SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  carreras SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pos (campeonato_id, piloto_id),
  FOREIGN KEY (campeonato_id) REFERENCES campeonatos(id) ON DELETE CASCADE,
  FOREIGN KEY (piloto_id) REFERENCES pilotos(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS metricas_diarias (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  fecha DATE NOT NULL UNIQUE,
  usos INT UNSIGNED NOT NULL DEFAULT 0,
  operaciones INT UNSIGNED NOT NULL DEFAULT 0,
  errores INT UNSIGNED NOT NULL DEFAULT 0,
  latidos INT UNSIGNED NOT NULL DEFAULT 0,
  pilotos_activos INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS registro_actividad (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  usuario_id INT UNSIGNED NULL,
  tipo ENUM('modulo','operacion','error','latido') NOT NULL,
  modulo VARCHAR(40) NULL,
  detalle VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_actividad (tipo, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS configuracion (
  clave VARCHAR(60) PRIMARY KEY,
  valor VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Datos iniciales (sin contraseñas). El administrador se crea con: php bin/crear_admin.php
INSERT IGNORE INTO roles (id, nombre) VALUES (1,'admin'),(2,'editor'),(3,'lector');
INSERT IGNORE INTO configuracion (clave, valor) VALUES ('puntos_por_defecto','25,18,15,12,10,8,6,4,2,1');

-- Datos de ejemplo para probar la interfaz
INSERT IGNORE INTO equipos (id,nombre,pais,anio_fundacion) VALUES
 (1,'Escudería Nocturna','Italia',1998),(2,'Lobos de Hierro','Alemania',2005),(3,'Cuervo Racing','Colombia',2012);
INSERT IGNORE INTO pilotos (id,nombre,apellido,documento,fecha_nacimiento,nacionalidad,equipo_id) VALUES
 (1,'Lucía','Navarro','1001','1995-03-14','Colombiana',3),(2,'Marco','Valenti','1002','1992-07-02','Italiana',1),
 (3,'Klaus','Reiner','1003','1990-11-21','Alemana',2),(4,'Sofía','Duarte','1004','1998-01-30','Colombiana',3);
INSERT IGNORE INTO vehiculos (id,tipo_vehiculo,marca,modelo,placa,anio,equipo_id) VALUES
 (1,'auto','Ferrari','296 GT3','RC-001',2024,1),(2,'auto','Porsche','911 GT3 R','RC-002',2024,2),
 (3,'auto','Chevrolet','Camaro GT4','RC-003',2023,3),(4,'auto','BMW','M4 GT3','RC-004',2024,3);
INSERT IGNORE INTO circuitos (id,nombre,pais,ciudad,longitud_km) VALUES
 (1,'Autódromo de Tocancipá','Colombia','Tocancipá',3.100),(2,'Monza','Italia','Monza',5.793);
INSERT IGNORE INTO campeonatos (id,nombre,temporada,tipo_vehiculo) VALUES (1,'Copa Catedral',2026,'auto');
INSERT IGNORE INTO carreras (id,campeonato_id,circuito_id,nombre,fecha,tipo_vehiculo,cupo_maximo,vueltas) VALUES
 (1,1,1,'Gran Premio Medianoche','2026-12-05','auto',20,12);

-- ===== Administrador inicial =====
-- Usuario: admin   Contraseña inicial: Catedral#Noche2026
-- CÁMBIALA al entrar por primera vez (menú de usuario → Cambiar contraseña).
INSERT IGNORE INTO usuarios (id,nombre,usuario,email,password_hash,rol_id) VALUES
 (1,'Administrador General','admin','admin@racecontrol.local','$2y$10$p5NdMVZbiULEqRgyubMlu.mCrphGdr8OMoDDFarnpr8DrbiUQCe12',1);

-- Si ya habías importado la base antes, ejecuta solo esta línea:
-- ALTER TABLE circuitos ADD COLUMN mapa VARCHAR(120) NULL AFTER longitud_km;
