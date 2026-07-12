SET NAMES utf8mb4;
USE muscu;

INSERT INTO workout_sessions (performed_at, session_type, body_weight_kg, notes)
VALUES (CURRENT_DATE, 'Legs', 80.50, 'Demo workout');
SET @session_id = LAST_INSERT_ID();

INSERT INTO workout_exercises (workout_session_id, exercise_id, position)
VALUES (@session_id, (SELECT id FROM exercises WHERE name='Leg Press'), 1);
SET @we = LAST_INSERT_ID();
INSERT INTO exercise_sets (workout_exercise_id, position, set_type, weight_kg, repetitions) VALUES
(@we, 1, 'warmup', 30, 16), (@we, 2, 'ramp', 40, 16),
(@we, 3, 'working', 50, 13), (@we, 4, 'working', 50, 13), (@we, 5, 'working', 50, 14);

INSERT INTO workout_exercises (workout_session_id, exercise_id, position)
VALUES (@session_id, (SELECT id FROM exercises WHERE name='Prone Leg Curl'), 2);
SET @we = LAST_INSERT_ID();
INSERT INTO exercise_sets (workout_exercise_id, position, set_type, weight_kg, repetitions) VALUES
(@we, 1, 'working', 30, 9), (@we, 2, 'working', 30, 7), (@we, 3, 'working', 30, 7);

INSERT INTO workout_exercises (workout_session_id, exercise_id, position, notes)
VALUES (@session_id, (SELECT id FROM exercises WHERE name='Seated Calf Raise'), 3, 'Starting resistance: 27.2 kg + 10 kg per side');
SET @we = LAST_INSERT_ID();
INSERT INTO exercise_sets (workout_exercise_id, position, set_type, weight_kg, repetitions, completed, notes) VALUES
(@we, 1, 'working', 47.20, 16, 1, NULL), (@we, 2, 'working', 47.20, 16, 1, NULL),
(@we, 3, 'working', 47.20, NULL, 0, 'Set not recorded');
