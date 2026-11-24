-- Ejecutar una sola vez en bases creadas antes de incluir el piloto asignado al vehículo.
ALTER TABLE pilotos
  ADD UNIQUE KEY uq_piloto_id_escuderia (id, equipo_id);

ALTER TABLE vehiculos
  ADD COLUMN piloto_id INT UNSIGNED NULL AFTER equipo_id,
  ADD INDEX idx_vehiculo_piloto_equipo (piloto_id, equipo_id),
  ADD CONSTRAINT fk_vehiculo_piloto_escuderia
    FOREIGN KEY (piloto_id, equipo_id) REFERENCES pilotos(id, equipo_id),
  ADD CONSTRAINT chk_vehiculo_piloto_escuderia
    CHECK (piloto_id IS NULL OR equipo_id IS NOT NULL);
