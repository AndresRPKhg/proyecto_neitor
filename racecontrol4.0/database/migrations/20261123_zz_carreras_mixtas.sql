-- Permite que una carrera incluya vehículos de ambos tipos.
ALTER TABLE carreras
  MODIFY tipo_vehiculo ENUM('auto','moto','mixto') NOT NULL;
