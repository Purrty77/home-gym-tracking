USE muscu;

UPDATE exercises e
JOIN equipment eq ON eq.id=e.equipment_id
SET e.load_semantics='per_dumbbell'
WHERE eq.name='Dumbbells'
  AND e.load_semantics NOT IN ('per_dumbbell','total');

UPDATE workout_exercises we
JOIN exercises e ON e.id=we.exercise_id
SET we.load_semantics=e.load_semantics
WHERE we.load_semantics IS NULL;
