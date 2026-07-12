SET @chest_id=(SELECT id FROM muscle_groups WHERE name='Chest' LIMIT 1);
SET @dumbbells_id=(SELECT id FROM equipment WHERE name='Dumbbells' LIMIT 1);

INSERT INTO exercises(muscle_group_id,equipment_id,name,variant,recommended_rest_seconds,weight_increment,load_semantics,quick_repetition_values)
SELECT @chest_id,@dumbbells_id,'Dumbbell Fly',NULL,75,2.00,'per_dumbbell','10,12,15,20'
WHERE NOT EXISTS(SELECT 1 FROM exercises WHERE name='Dumbbell Fly');

SET @dumbbell_fly_id=(SELECT id FROM exercises WHERE name='Dumbbell Fly' LIMIT 1);
SET @cable_fly_id=(SELECT id FROM exercises WHERE name='Cable Fly' LIMIT 1);

UPDATE workout_template_exercises wte
JOIN workout_templates wt ON wt.id=wte.workout_template_id
SET wte.exercise_id=@dumbbell_fly_id
WHERE wt.session_type='Chest / Triceps' AND wte.exercise_id=@cable_fly_id;

UPDATE achievement_definitions SET code='dumbbell_fly_first',name='First Dumbbell Fly',exercise_id=@dumbbell_fly_id WHERE code='cable_fly_first';
UPDATE achievement_definitions SET code='dumbbell_fly_control',exercise_id=@dumbbell_fly_id WHERE code='cable_fly_control';
UPDATE exercises SET is_active=FALSE WHERE id=@cable_fly_id;
