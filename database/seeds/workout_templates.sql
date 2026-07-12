SET NAMES utf8mb4;
USE muscu;

INSERT INTO workout_templates (name,session_type,scheduled_days) VALUES
('Workout A — Chest / Triceps','Chest / Triceps','1,4'),
('Workout B — Back / Biceps / Forearms','Back / Biceps / Forearms','2,5'),
('Workout C — Shoulders / Neck','Shoulders / Neck','3,6'),
('Leg Workout','Legs','7');

SET @a=(SELECT id FROM workout_templates WHERE name='Workout A — Chest / Triceps');
INSERT INTO workout_template_exercises (workout_template_id,exercise_id,position,set_count,repetitions_min,repetitions_max,rest_seconds_min,rest_seconds) VALUES
(@a,(SELECT id FROM exercises WHERE name='Dumbbell Bench Press'),1,3,8,12,90,120),(@a,(SELECT id FROM exercises WHERE name='Incline Dumbbell Press'),2,3,8,12,90,120),(@a,(SELECT id FROM exercises WHERE name='Cable Fly'),3,3,10,15,60,90),(@a,(SELECT id FROM exercises WHERE name='Rope Triceps Pushdown'),4,3,8,12,60,90);

SET @b=(SELECT id FROM workout_templates WHERE name='Workout B — Back / Biceps / Forearms');
INSERT INTO workout_template_exercises (workout_template_id,exercise_id,position,set_count,repetitions_min,repetitions_max,rest_seconds_min,rest_seconds,notes) VALUES
(@b,(SELECT id FROM exercises WHERE name='Lat Pulldown'),1,3,8,12,90,120,NULL),(@b,(SELECT id FROM exercises WHERE name='Seated Cable Row'),2,3,8,12,90,120,NULL),(@b,(SELECT id FROM exercises WHERE name='Dumbbell Curl'),3,3,8,12,60,90,NULL),(@b,(SELECT id FROM exercises WHERE name='Hammer Curl'),4,3,8,12,60,90,NULL),(@b,(SELECT id FROM exercises WHERE name='Forearms'),5,3,NULL,NULL,60,60,'Wrist Curl or Farmer''s Carry');

SET @c=(SELECT id FROM workout_templates WHERE name='Workout C — Shoulders / Neck');
INSERT INTO workout_template_exercises (workout_template_id,exercise_id,position,set_count,repetitions_min,repetitions_max,rest_seconds_min,rest_seconds,notes) VALUES
(@c,(SELECT id FROM exercises WHERE name='Military Press'),1,3,8,12,90,120,'Dumbbells or barbell'),(@c,(SELECT id FROM exercises WHERE name='Lateral Raises'),2,3,10,15,60,90,NULL),(@c,(SELECT id FROM exercises WHERE name='Reverse Pec Deck'),3,3,10,15,60,90,NULL),(@c,(SELECT id FROM exercises WHERE name='Neck Exercises'),4,3,15,20,60,60,'Neck flexion, extension and lateral flexion');

SET @j=(SELECT id FROM workout_templates WHERE name='Leg Workout');
INSERT INTO workout_template_exercises (workout_template_id,exercise_id,position,set_count,repetitions_min,repetitions_max,rest_seconds_min,rest_seconds,notes) VALUES
(@j,(SELECT id FROM exercises WHERE name='Leg Press'),1,3,8,12,90,120,NULL),(@j,(SELECT id FROM exercises WHERE name='Prone Leg Curl'),2,3,8,12,60,90,NULL),(@j,(SELECT id FROM exercises WHERE name='Seated Calf Raise'),3,3,12,20,60,90,NULL),(@j,(SELECT id FROM exercises WHERE name='Back Extensions'),4,3,12,15,60,90,'If the gym has one');

