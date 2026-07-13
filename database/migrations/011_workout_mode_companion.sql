USE muscu;

ALTER TABLE workout_sessions
  ADD COLUMN IF NOT EXISTS template_order_changed BOOLEAN NOT NULL DEFAULT FALSE AFTER rest_paused_seconds,
  ADD COLUMN IF NOT EXISTS template_sets_changed BOOLEAN NOT NULL DEFAULT FALSE AFTER template_order_changed;

ALTER TABLE workout_exercises
  ADD COLUMN IF NOT EXISTS warmup_decision ENUM('pending','added','skipped') NOT NULL DEFAULT 'skipped' AFTER planned_rest_seconds;

UPDATE workout_exercises we
SET we.target_set_count=(SELECT COUNT(*) FROM exercise_sets es WHERE es.workout_exercise_id=we.id AND es.set_type='working')
WHERE we.target_set_count IS NOT NULL;

UPDATE workout_exercises we
SET we.warmup_decision='added'
WHERE EXISTS(SELECT 1 FROM exercise_sets es WHERE es.workout_exercise_id=we.id AND es.set_type='warmup');

CREATE TABLE IF NOT EXISTS exercise_set_segments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  exercise_set_id BIGINT UNSIGNED NOT NULL,
  position TINYINT UNSIGNED NOT NULL,
  weight_kg DECIMAL(7,2) NOT NULL,
  repetitions SMALLINT UNSIGNED NOT NULL,
  is_personal_record BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_segment_set FOREIGN KEY (exercise_set_id) REFERENCES exercise_sets(id) ON DELETE CASCADE,
  UNIQUE KEY uq_segment_position (exercise_set_id,position),
  INDEX idx_segments_set (exercise_set_id,position)
) ENGINE=InnoDB;

ALTER TABLE exercise_set_segments
  ADD COLUMN IF NOT EXISTS is_personal_record BOOLEAN NOT NULL DEFAULT FALSE AFTER repetitions;

INSERT INTO exercise_set_segments(exercise_set_id,position,weight_kg,repetitions,is_personal_record)
SELECT id,1,weight_kg,repetitions,is_personal_record FROM exercise_sets
WHERE completed=1 AND weight_kg IS NOT NULL AND repetitions IS NOT NULL
ON DUPLICATE KEY UPDATE weight_kg=VALUES(weight_kg),repetitions=VALUES(repetitions),is_personal_record=VALUES(is_personal_record);

INSERT INTO settings(setting_key,setting_value) VALUES ('warmup_default_behavior','ask')
ON DUPLICATE KEY UPDATE setting_value=setting_value;
