SET NAMES utf8mb4;
USE muscu;

ALTER TABLE muscle_groups ADD COLUMN sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 100;

UPDATE muscle_groups SET name='Chest',sort_order=30 WHERE name='Pectoraux';
UPDATE muscle_groups SET name='Triceps',sort_order=51 WHERE name='Triceps';
UPDATE muscle_groups SET name='Back',sort_order=40 WHERE name='Dos';
UPDATE muscle_groups SET name='Biceps',sort_order=50 WHERE name='Biceps';
UPDATE muscle_groups SET name='Forearms',sort_order=60 WHERE name='Avant-bras';
UPDATE muscle_groups SET name='Shoulders',sort_order=20 WHERE name='Épaules';
UPDATE muscle_groups SET name='Neck',sort_order=10 WHERE name='Cou';
UPDATE muscle_groups SET name='Legs',sort_order=80 WHERE name='Jambes';
UPDATE muscle_groups SET name='Calves',sort_order=90 WHERE name='Mollets';
UPDATE muscle_groups SET name='Lower Back',sort_order=70 WHERE name='Lombaires';

UPDATE equipment SET name='Dumbbells' WHERE name='Haltères';
UPDATE equipment SET name='Cable' WHERE name='Poulie';
UPDATE equipment SET name='Barbell' WHERE name='Barre';
UPDATE equipment SET name='Bodyweight' WHERE name='Poids du corps';

UPDATE exercises SET name='Dumbbell Bench Press' WHERE name='Développé couché haltères';
UPDATE exercises SET name='Incline Dumbbell Press' WHERE name='Développé incliné';
UPDATE exercises SET name='Cable Fly' WHERE name='Écarté poulie';
UPDATE exercises SET name='Rope Triceps Pushdown' WHERE name='Extension triceps corde';
UPDATE exercises SET name='Forearms' WHERE name='Avant-bras';
UPDATE exercises SET name='Military Press' WHERE name='Développé militaire';
UPDATE exercises SET name='Lateral Raises' WHERE name='Élévations latérales';
UPDATE exercises SET name='Neck Exercises' WHERE name='Cou';
UPDATE exercises SET name='Seated Calf Raise' WHERE name='Mollets assis';
UPDATE exercises SET name='Back Extensions',variant='If available' WHERE name='Extensions lombaires';

UPDATE workout_templates SET name='Workout A — Chest / Triceps',session_type='Chest / Triceps' WHERE name='Séance A — Pectoraux / Triceps';
UPDATE workout_templates SET name='Workout B — Back / Biceps / Forearms',session_type='Back / Biceps / Forearms' WHERE name='Séance B — Dos / Biceps / Avant-bras';
UPDATE workout_templates SET name='Workout C — Shoulders / Neck',session_type='Shoulders / Neck' WHERE name='Séance C — Épaules / Cou';
UPDATE workout_templates SET name='Leg Workout',session_type='Legs' WHERE name='Séance Jambes';
UPDATE workout_template_exercises SET notes='Dumbbells or barbell' WHERE notes='Haltères ou barre';
UPDATE workout_template_exercises SET notes='Neck flexion, extension and lateral flexion' WHERE notes='Flexion, extension et inclinaisons';
UPDATE workout_template_exercises SET notes='If the gym has one' WHERE notes='Si la salle en possède';

UPDATE workout_sessions SET session_type='Legs' WHERE session_type='Jambes';

