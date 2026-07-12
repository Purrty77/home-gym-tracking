ALTER TABLE exercise_sets
  ADD COLUMN is_extra BOOLEAN NOT NULL DEFAULT FALSE AFTER is_personal_record,
  ADD UNIQUE KEY uq_sets_workout_exercise_position (workout_exercise_id,position);

CREATE TABLE body_weight_entries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recorded_on DATE NOT NULL,
  weight_kg DECIMAL(5,2) NOT NULL,
  source ENUM('dashboard','workout','measurement','migration') NOT NULL DEFAULT 'dashboard',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_body_weight_date (recorded_on),
  INDEX idx_body_weight_date (recorded_on)
) ENGINE=InnoDB;

INSERT INTO body_weight_entries(recorded_on,weight_kg,source)
SELECT measured_on,weight_kg,'migration' FROM measurements WHERE weight_kg IS NOT NULL
ON DUPLICATE KEY UPDATE weight_kg=VALUES(weight_kg);

INSERT INTO body_weight_entries(recorded_on,weight_kg,source)
SELECT DATE(performed_at),MAX(body_weight_kg),'migration' FROM workout_sessions
WHERE body_weight_kg IS NOT NULL GROUP BY DATE(performed_at)
ON DUPLICATE KEY UPDATE weight_kg=VALUES(weight_kg);
