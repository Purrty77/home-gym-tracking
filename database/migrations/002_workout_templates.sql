USE muscu;

CREATE TABLE workout_templates (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    session_type VARCHAR(120) NOT NULL,
    scheduled_days VARCHAR(20) NOT NULL COMMENT 'Comma-separated ISO weekdays, Monday=1',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    UNIQUE KEY uq_template_name (name)
) ENGINE=InnoDB;

CREATE TABLE workout_template_exercises (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workout_template_id SMALLINT UNSIGNED NOT NULL,
    exercise_id INT UNSIGNED NOT NULL,
    position SMALLINT UNSIGNED NOT NULL,
    set_count TINYINT UNSIGNED NOT NULL DEFAULT 3,
    repetitions_min SMALLINT UNSIGNED NULL,
    repetitions_max SMALLINT UNSIGNED NULL,
    rest_seconds_min SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    rest_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 90,
    notes VARCHAR(500) NULL,
    CONSTRAINT fk_wte_template FOREIGN KEY (workout_template_id) REFERENCES workout_templates(id) ON DELETE CASCADE,
    CONSTRAINT fk_wte_exercise FOREIGN KEY (exercise_id) REFERENCES exercises(id),
    UNIQUE KEY uq_template_position (workout_template_id, position)
) ENGINE=InnoDB;
