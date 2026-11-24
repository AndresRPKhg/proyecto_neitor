SET NAMES utf8mb4;
-- Datos iniciales (sin contraseñas). El administrador se crea con: php bin/crear_admin.php
USE racecontrol;
INSERT IGNORE INTO roles (id, nombre) VALUES (1,'admin'),(2,'editor'),(3,'lector');
INSERT IGNORE INTO configuracion (clave, valor) VALUES ('puntos_por_defecto','25,18,15,12,10,8,6,4,2,1');

-- Datos de ejemplo para probar la interfaz
INSERT IGNORE INTO equipos (id,nombre,pais,anio_fundacion) VALUES
 (1,'Escudería Nocturna','Italia',1998),(2,'Lobos de Hierro','Alemania',2005),(3,'Cuervo Racing','Colombia',2012);
INSERT IGNORE INTO pilotos (id,nombre,apellido,documento,fecha_nacimiento,nacionalidad,equipo_id) VALUES
 (1,'Lucía','Navarro','1001','1995-03-14','Colombiana',3),(2,'Marco','Valenti','1002','1992-07-02','Italiana',1),
 (3,'Klaus','Reiner','1003','1990-11-21','Alemana',2),(4,'Sofía','Duarte','1004','1998-01-30','Colombiana',3);
INSERT IGNORE INTO vehiculos (id,tipo_vehiculo,marca,modelo,placa,anio,equipo_id,piloto_id) VALUES
 (1,'auto','Ferrari','296 GT3','RC-001',2024,1,2),(2,'auto','Porsche','911 GT3 R','RC-002',2024,2,3),
 (3,'auto','Chevrolet','Camaro GT4','RC-003',2023,3,1),(4,'auto','BMW','M4 GT3','RC-004',2024,3,4);
INSERT IGNORE INTO circuitos (id,nombre,pais,ciudad,longitud_km) VALUES
 (1,'Autódromo de Tocancipá','Colombia','Tocancipá',3.100),(2,'Monza','Italia','Monza',5.793);
INSERT IGNORE INTO campeonatos (id,nombre,temporada,tipo_vehiculo) VALUES (1,'Copa Catedral',2026,'auto');
INSERT IGNORE INTO carreras (id,campeonato_id,circuito_id,nombre,fecha,tipo_vehiculo,cupo_maximo,vueltas) VALUES
 (1,1,1,'Gran Premio Medianoche','2026-12-05','auto',20,12);
