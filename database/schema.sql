SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS muscu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE muscu;

CREATE TABLE muscle_groups (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 100
) ENGINE=InnoDB;

CREATE TABLE equipment (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE exercises (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    muscle_group_id SMALLINT UNSIGNED NOT NULL,
    equipment_id SMALLINT UNSIGNED NULL,
    name VARCHAR(150) NOT NULL,
    variant VARCHAR(120) NULL,
    notes TEXT NULL,
    recommended_rest_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 90,
    weight_increment DECIMAL(6,2) NOT NULL DEFAULT 2.50,
    tracking_metric ENUM('repetitions','duration','both') NOT NULL DEFAULT 'repetitions',
    load_semantics ENUM('total','per_dumbbell','machine_stack','added_plates') NOT NULL DEFAULT 'total',
    quick_repetition_values VARCHAR(50) NOT NULL DEFAULT '8,10,12,15',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_exercise_muscle_group FOREIGN KEY (muscle_group_id) REFERENCES muscle_groups(id),
    CONSTRAINT fk_exercise_equipment FOREIGN KEY (equipment_id) REFERENCES equipment(id),
    UNIQUE KEY uq_exercise_name_variant (name, variant),
    INDEX idx_exercises_group (muscle_group_id)
) ENGINE=InnoDB;

CREATE TABLE workout_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workout_template_id SMALLINT UNSIGNED NULL,
    performed_at DATETIME NOT NULL,
    session_type VARCHAR(120) NOT NULL,
    body_weight_kg DECIMAL(5,2) NULL,
    notes TEXT NULL,
    status ENUM('in_progress','completed','abandoned') NOT NULL DEFAULT 'completed',
    current_workout_exercise_id BIGINT UNSIGNED NULL,
    current_set_position SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    rest_ends_at DATETIME NULL,
    rest_paused_seconds SMALLINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sessions_date (performed_at)
) ENGINE=InnoDB;

CREATE TABLE workout_exercises (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workout_session_id BIGINT UNSIGNED NOT NULL,
    exercise_id INT UNSIGNED NOT NULL,
    position SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    notes TEXT NULL,
    status ENUM('pending','active','completed','skipped') NOT NULL DEFAULT 'completed',
    target_set_count TINYINT UNSIGNED NULL,
    target_repetitions_min SMALLINT UNSIGNED NULL,
    target_repetitions_max SMALLINT UNSIGNED NULL,
    planned_rest_seconds SMALLINT UNSIGNED NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    CONSTRAINT fk_we_session FOREIGN KEY (workout_session_id) REFERENCES workout_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_we_exercise FOREIGN KEY (exercise_id) REFERENCES exercises(id),
    INDEX idx_we_session_position (workout_session_id, position),
    INDEX idx_we_exercise (exercise_id)
) ENGINE=InnoDB;

CREATE TABLE exercise_sets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workout_exercise_id BIGINT UNSIGNED NOT NULL,
    position SMALLINT UNSIGNED NOT NULL,
    set_type ENUM('warmup', 'ramp', 'working') NOT NULL DEFAULT 'working',
    weight_kg DECIMAL(7,2) NULL,
    repetitions SMALLINT UNSIGNED NULL,
    rest_seconds SMALLINT UNSIGNED NULL,
    notes VARCHAR(500) NULL,
    completed BOOLEAN NOT NULL DEFAULT TRUE,
    completed_at DATETIME NULL,
    duration_seconds SMALLINT UNSIGNED NULL,
    is_personal_record BOOLEAN NOT NULL DEFAULT FALSE,
    CONSTRAINT fk_set_workout_exercise FOREIGN KEY (workout_exercise_id) REFERENCES workout_exercises(id) ON DELETE CASCADE,
    INDEX idx_sets_workout_exercise (workout_exercise_id, position)
) ENGINE=InnoDB;

CREATE TABLE measurements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    measured_on DATE NOT NULL,
    measurement_month CHAR(7) NOT NULL,
    weight_kg DECIMAL(5,2) NULL,
    waist_cm DECIMAL(5,2) NULL,
    chest_cm DECIMAL(5,2) NULL,
    arm_cm DECIMAL(5,2) NULL,
    thigh_cm DECIMAL(5,2) NULL,
    calf_cm DECIMAL(5,2) NULL,
    neck_cm DECIMAL(5,2) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_measurement_month (measurement_month),
    INDEX idx_measurements_date (measured_on)
) ENGINE=InnoDB;

CREATE TABLE settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value VARCHAR(500) NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

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

INSERT INTO settings (setting_key, setting_value) VALUES
('measurement_reminder_enabled', '1'),
('measurement_reminder_day', '1'),
('appearance_theme', 'dark'),
('motivational_message_index','1'),
('motivational_message_date',''),
('timer_sound_enabled','1'),
('timer_vibration_enabled','1'),
('body_weight_goal_direction','unset');

CREATE TABLE motivational_messages (id TINYINT UNSIGNED PRIMARY KEY,message VARCHAR(160) NOT NULL) ENGINE=InnoDB;
CREATE TABLE achievement_definitions (id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,code VARCHAR(100) NOT NULL UNIQUE,name VARCHAR(150) NOT NULL,description VARCHAR(300) NOT NULL,category VARCHAR(50) NOT NULL,icon VARCHAR(20) NOT NULL DEFAULT '🏆',is_hidden BOOLEAN NOT NULL DEFAULT FALSE,evaluation_type VARCHAR(50) NOT NULL,requirement_value DECIMAL(10,2) NULL,workout_template_id SMALLINT UNSIGNED NULL,exercise_id INT UNSIGNED NULL,sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 100,is_active BOOLEAN NOT NULL DEFAULT TRUE,CONSTRAINT fk_achievement_template FOREIGN KEY(workout_template_id) REFERENCES workout_templates(id) ON DELETE CASCADE,CONSTRAINT fk_achievement_exercise FOREIGN KEY(exercise_id) REFERENCES exercises(id) ON DELETE CASCADE) ENGINE=InnoDB;
CREATE TABLE achievement_unlocks (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,achievement_id SMALLINT UNSIGNED NOT NULL,workout_session_id BIGINT UNSIGNED NULL,unlocked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,context_json JSON NULL,notification_seen_at DATETIME NULL,UNIQUE KEY uq_achievement_unlock(achievement_id),CONSTRAINT fk_unlock_definition FOREIGN KEY(achievement_id) REFERENCES achievement_definitions(id) ON DELETE CASCADE,CONSTRAINT fk_unlock_session FOREIGN KEY(workout_session_id) REFERENCES workout_sessions(id) ON DELETE SET NULL) ENGINE=InnoDB;
CREATE TABLE exercise_milestones (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,exercise_id INT UNSIGNED NOT NULL,achievement_id SMALLINT UNSIGNED NOT NULL,metric VARCHAR(40) NOT NULL,target_weight DECIMAL(7,2) NULL,target_repetitions SMALLINT UNSIGNED NULL,target_set_count TINYINT UNSIGNED NULL,consecutive_sessions TINYINT UNSIGNED NULL,CONSTRAINT fk_milestone_exercise FOREIGN KEY(exercise_id) REFERENCES exercises(id) ON DELETE CASCADE,CONSTRAINT fk_milestone_achievement FOREIGN KEY(achievement_id) REFERENCES achievement_definitions(id) ON DELETE CASCADE) ENGINE=InnoDB;
