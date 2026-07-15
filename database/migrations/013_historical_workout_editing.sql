USE muscu;

ALTER TABLE workout_sessions
  ADD COLUMN IF NOT EXISTS last_edited_at DATETIME NULL AFTER updated_at;

ALTER TABLE workout_exercises
  ADD COLUMN IF NOT EXISTS load_semantics ENUM('total','per_dumbbell','machine_stack','added_plates') NULL AFTER notes;

UPDATE workout_exercises we
JOIN exercises e ON e.id=we.exercise_id
SET we.load_semantics=e.load_semantics
WHERE we.load_semantics IS NULL;
