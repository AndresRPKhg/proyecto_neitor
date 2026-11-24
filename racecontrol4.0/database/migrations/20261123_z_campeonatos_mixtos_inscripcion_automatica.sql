-- Un campeonato mixto contiene carreras individuales de autos o de motos.
ALTER TABLE campeonatos
  MODIFY tipo_vehiculo ENUM('auto','moto','mixto') NOT NULL;

-- Asignar vehículos de demostración si aún no tienen piloto y el piloto coincide con su escudería.
UPDATE vehiculos v
JOIN equipos e ON e.nombre='Escudería Nocturna'
JOIN pilotos p ON p.documento='1002' AND p.equipo_id=e.id AND p.deleted_at IS NULL
LEFT JOIN vehiculos ocupado ON ocupado.piloto_id=p.id AND ocupado.tipo_vehiculo='auto'
  AND ocupado.deleted_at IS NULL AND ocupado.id<>v.id
SET v.piloto_id=p.id
WHERE v.placa='RC-001' AND v.marca='Ferrari' AND v.modelo='296 GT3' AND v.tipo_vehiculo='auto'
  AND v.equipo_id=e.id AND v.piloto_id IS NULL AND v.deleted_at IS NULL AND ocupado.id IS NULL;

UPDATE vehiculos v
JOIN equipos e ON e.nombre='Lobos de Hierro'
JOIN pilotos p ON p.documento='1003' AND p.equipo_id=e.id AND p.deleted_at IS NULL
LEFT JOIN vehiculos ocupado ON ocupado.piloto_id=p.id AND ocupado.tipo_vehiculo='auto'
  AND ocupado.deleted_at IS NULL AND ocupado.id<>v.id
SET v.piloto_id=p.id
WHERE v.placa='RC-002' AND v.marca='Porsche' AND v.modelo='911 GT3 R' AND v.tipo_vehiculo='auto'
  AND v.equipo_id=e.id AND v.piloto_id IS NULL AND v.deleted_at IS NULL AND ocupado.id IS NULL;

UPDATE vehiculos v
JOIN equipos e ON e.nombre='Cuervo Racing'
JOIN pilotos p ON p.documento='1001' AND p.equipo_id=e.id AND p.deleted_at IS NULL
LEFT JOIN vehiculos ocupado ON ocupado.piloto_id=p.id AND ocupado.tipo_vehiculo='auto'
  AND ocupado.deleted_at IS NULL AND ocupado.id<>v.id
SET v.piloto_id=p.id
WHERE v.placa='RC-003' AND v.marca='Chevrolet' AND v.modelo='Camaro GT4' AND v.tipo_vehiculo='auto'
  AND v.equipo_id=e.id AND v.piloto_id IS NULL AND v.deleted_at IS NULL AND ocupado.id IS NULL;

UPDATE vehiculos v
JOIN equipos e ON e.nombre='Cuervo Racing'
JOIN pilotos p ON p.documento='1004' AND p.equipo_id=e.id AND p.deleted_at IS NULL
LEFT JOIN vehiculos ocupado ON ocupado.piloto_id=p.id AND ocupado.tipo_vehiculo='auto'
  AND ocupado.deleted_at IS NULL AND ocupado.id<>v.id
SET v.piloto_id=p.id
WHERE v.placa='RC-004' AND v.marca='BMW' AND v.modelo='M4 GT3' AND v.tipo_vehiculo='auto'
  AND v.equipo_id=e.id AND v.piloto_id IS NULL AND v.deleted_at IS NULL AND ocupado.id IS NULL;

ALTER TABLE vehiculos
  ADD COLUMN piloto_tipo_activo VARCHAR(20) GENERATED ALWAYS AS (
    CASE WHEN piloto_id IS NOT NULL AND deleted_at IS NULL THEN CONCAT(piloto_id, ':', tipo_vehiculo) ELSE NULL END
  ) VIRTUAL,
  ADD UNIQUE KEY uq_vehiculo_piloto_tipo_activo (piloto_tipo_activo);
