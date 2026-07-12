SET NAMES utf8mb4;
USE muscu;

INSERT INTO muscle_groups (name,sort_order) VALUES
('Neck',10),('Shoulders',20),('Chest',30),('Back',40),('Biceps',50),('Triceps',51),('Forearms',60),('Lower Back',70),('Legs',80),('Calves',90);

INSERT INTO equipment (name) VALUES
('Dumbbells'),('Cable'),('Machine'),('Barbell'),('Bodyweight'),('Hammer Strength');

INSERT INTO exercises (muscle_group_id,equipment_id,name,variant,recommended_rest_seconds) VALUES
((SELECT id FROM muscle_groups WHERE name='Chest'),(SELECT id FROM equipment WHERE name='Dumbbells'),'Dumbbell Bench Press',NULL,120),
((SELECT id FROM muscle_groups WHERE name='Chest'),(SELECT id FROM equipment WHERE name='Dumbbells'),'Incline Dumbbell Press',NULL,120),
((SELECT id FROM muscle_groups WHERE name='Chest'),(SELECT id FROM equipment WHERE name='Dumbbells'),'Dumbbell Fly',NULL,75),
((SELECT id FROM muscle_groups WHERE name='Triceps'),(SELECT id FROM equipment WHERE name='Cable'),'Rope Triceps Pushdown',NULL,75),
((SELECT id FROM muscle_groups WHERE name='Back'),(SELECT id FROM equipment WHERE name='Cable'),'Lat Pulldown',NULL,120),
((SELECT id FROM muscle_groups WHERE name='Back'),(SELECT id FROM equipment WHERE name='Cable'),'Seated Cable Row',NULL,120),
((SELECT id FROM muscle_groups WHERE name='Biceps'),(SELECT id FROM equipment WHERE name='Dumbbells'),'Dumbbell Curl',NULL,75),
((SELECT id FROM muscle_groups WHERE name='Biceps'),(SELECT id FROM equipment WHERE name='Dumbbells'),'Hammer Curl',NULL,75),
((SELECT id FROM muscle_groups WHERE name='Forearms'),NULL,'Forearms',NULL,60),
((SELECT id FROM muscle_groups WHERE name='Shoulders'),(SELECT id FROM equipment WHERE name='Dumbbells'),'Military Press',NULL,120),
((SELECT id FROM muscle_groups WHERE name='Shoulders'),(SELECT id FROM equipment WHERE name='Dumbbells'),'Lateral Raises',NULL,75),
((SELECT id FROM muscle_groups WHERE name='Shoulders'),(SELECT id FROM equipment WHERE name='Machine'),'Reverse Pec Deck',NULL,75),
((SELECT id FROM muscle_groups WHERE name='Neck'),NULL,'Neck Exercises',NULL,60),
((SELECT id FROM muscle_groups WHERE name='Legs'),(SELECT id FROM equipment WHERE name='Machine'),'Leg Press',NULL,120),
((SELECT id FROM muscle_groups WHERE name='Legs'),(SELECT id FROM equipment WHERE name='Machine'),'Prone Leg Curl',NULL,90),
((SELECT id FROM muscle_groups WHERE name='Calves'),(SELECT id FROM equipment WHERE name='Hammer Strength'),'Seated Calf Raise',NULL,75),
((SELECT id FROM muscle_groups WHERE name='Lower Back'),(SELECT id FROM equipment WHERE name='Machine'),'Back Extensions','If available',90);

UPDATE exercises SET weight_increment=2.00,load_semantics='per_dumbbell' WHERE equipment_id=(SELECT id FROM equipment WHERE name='Dumbbells');
UPDATE exercises SET weight_increment=5.00,load_semantics='machine_stack' WHERE equipment_id IN (SELECT id FROM equipment WHERE name IN ('Machine','Cable'));
UPDATE exercises SET weight_increment=1.00 WHERE name IN ('Lateral Raises','Neck Exercises');
UPDATE exercises SET weight_increment=5.00,load_semantics='added_plates' WHERE name='Seated Calf Raise';
UPDATE exercises SET quick_repetition_values='10,12,15,20' WHERE name IN ('Lateral Raises','Dumbbell Fly','Reverse Pec Deck','Seated Calf Raise','Neck Exercises');
