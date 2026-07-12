# Modèle de données

## MCD

- Un **groupe musculaire** classe zéro à plusieurs exercices.
- Un **matériel** équipe zéro à plusieurs exercices ; un exercice peut ne pas en avoir.
- Une **séance** contient zéro à plusieurs exercices réalisés.
- Un **exercice** peut être réalisé dans zéro à plusieurs séances.
- Un **exercice réalisé** appartient à une séance et référence un exercice du catalogue.
- Un **exercice réalisé** contient zéro à plusieurs séries ordonnées.
- Une **mensuration** représente les mesures prises à une date unique.
- Un **paramètre** conserve une préférence configurable.

La table associative `workout_exercises` est une entité à part entière : elle conserve l’ordre et les notes propres à l’exercice pendant une séance.

## MLD

```text
MUSCLE_GROUP(id, name)
EQUIPMENT(id, name)
EXERCISE(id, #muscle_group_id, #equipment_id?, name, variant?, notes?, recommended_rest_seconds, is_active)
WORKOUT_SESSION(id, performed_at, session_type, body_weight_kg?, notes?)
WORKOUT_EXERCISE(id, #workout_session_id, #exercise_id, position, notes?)
EXERCISE_SET(id, #workout_exercise_id, position, set_type, weight_kg?, repetitions?, rest_seconds?, notes?, completed)
MEASUREMENT(id, measured_on, weight_kg?, waist_cm?, chest_cm?, arm_cm?, thigh_cm?, calf_cm?, neck_cm?, notes?)
SETTING(setting_key, setting_value)
```

Le schéma physique complet et les contraintes se trouvent dans `database/schema.sql`.

