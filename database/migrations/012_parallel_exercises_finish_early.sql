USE muscu;

ALTER TABLE workout_exercises
  ADD COLUMN IF NOT EXISTS current_set_position SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER warmup_decision,
  ADD COLUMN IF NOT EXISTS rest_ends_at DATETIME NULL AFTER current_set_position,
  ADD COLUMN IF NOT EXISTS rest_paused_seconds SMALLINT UNSIGNED NULL AFTER rest_ends_at;

UPDATE workout_exercises we
JOIN workout_sessions ws ON ws.current_workout_exercise_id=we.id
SET we.current_set_position=COALESCE(ws.current_set_position,1),
    we.rest_ends_at=ws.rest_ends_at,
    we.rest_paused_seconds=ws.rest_paused_seconds
WHERE ws.status='in_progress';
