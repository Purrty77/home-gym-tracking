USE muscu;
UPDATE exercises SET weight_increment=2.00,load_semantics='per_dumbbell' WHERE equipment_id=(SELECT id FROM equipment WHERE name='Dumbbells');
UPDATE exercises SET weight_increment=5.00,load_semantics='machine_stack' WHERE equipment_id IN (SELECT id FROM equipment WHERE name IN ('Machine','Cable'));
UPDATE exercises SET weight_increment=1.00 WHERE name IN ('Lateral Raises','Neck Exercises');
UPDATE exercises SET weight_increment=5.00,load_semantics='added_plates' WHERE name='Seated Calf Raise';
UPDATE exercises SET quick_repetition_values='10,12,15,20' WHERE name IN ('Lateral Raises','Cable Fly','Reverse Pec Deck','Seated Calf Raise','Neck Exercises');

