SET NAMES utf8mb4;
USE muscu;

ALTER TABLE workout_sessions
  ADD COLUMN workout_template_id SMALLINT UNSIGNED NULL AFTER id,
  ADD COLUMN status ENUM('in_progress','completed','abandoned') NOT NULL DEFAULT 'completed' AFTER notes,
  ADD COLUMN current_workout_exercise_id BIGINT UNSIGNED NULL AFTER status,
  ADD COLUMN current_set_position SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER current_workout_exercise_id,
  ADD COLUMN started_at DATETIME NULL AFTER current_set_position,
  ADD COLUMN completed_at DATETIME NULL AFTER started_at,
  ADD COLUMN rest_ends_at DATETIME NULL AFTER completed_at,
  ADD COLUMN rest_paused_seconds SMALLINT UNSIGNED NULL AFTER rest_ends_at,
  ADD CONSTRAINT fk_session_template FOREIGN KEY (workout_template_id) REFERENCES workout_templates(id) ON DELETE SET NULL;

UPDATE workout_sessions SET status='completed',started_at=performed_at,completed_at=performed_at WHERE started_at IS NULL;

ALTER TABLE workout_exercises
  ADD COLUMN status ENUM('pending','active','completed','skipped') NOT NULL DEFAULT 'completed' AFTER notes,
  ADD COLUMN target_set_count TINYINT UNSIGNED NULL AFTER status,
  ADD COLUMN target_repetitions_min SMALLINT UNSIGNED NULL AFTER target_set_count,
  ADD COLUMN target_repetitions_max SMALLINT UNSIGNED NULL AFTER target_repetitions_min,
  ADD COLUMN planned_rest_seconds SMALLINT UNSIGNED NULL AFTER target_repetitions_max,
  ADD COLUMN started_at DATETIME NULL AFTER planned_rest_seconds,
  ADD COLUMN completed_at DATETIME NULL AFTER started_at;

ALTER TABLE exercise_sets
  ADD COLUMN completed_at DATETIME NULL AFTER completed,
  ADD COLUMN duration_seconds SMALLINT UNSIGNED NULL AFTER completed_at,
  ADD COLUMN is_personal_record BOOLEAN NOT NULL DEFAULT FALSE AFTER duration_seconds;

UPDATE exercise_sets SET completed_at=CURRENT_TIMESTAMP WHERE completed=1 AND completed_at IS NULL;

ALTER TABLE exercises
  ADD COLUMN weight_increment DECIMAL(6,2) NOT NULL DEFAULT 2.50 AFTER recommended_rest_seconds,
  ADD COLUMN tracking_metric ENUM('repetitions','duration','both') NOT NULL DEFAULT 'repetitions' AFTER weight_increment,
  ADD COLUMN load_semantics ENUM('total','per_dumbbell','machine_stack','added_plates') NOT NULL DEFAULT 'total' AFTER tracking_metric,
  ADD COLUMN quick_repetition_values VARCHAR(50) NOT NULL DEFAULT '8,10,12,15' AFTER load_semantics;

CREATE TABLE motivational_messages (
  id TINYINT UNSIGNED PRIMARY KEY,
  message VARCHAR(160) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE achievement_definitions (
  id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(100) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  description VARCHAR(300) NOT NULL,
  category VARCHAR(50) NOT NULL,
  icon VARCHAR(20) NOT NULL DEFAULT '🏆',
  is_hidden BOOLEAN NOT NULL DEFAULT FALSE,
  evaluation_type VARCHAR(50) NOT NULL,
  requirement_value DECIMAL(10,2) NULL,
  workout_template_id SMALLINT UNSIGNED NULL,
  exercise_id INT UNSIGNED NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  CONSTRAINT fk_achievement_template FOREIGN KEY (workout_template_id) REFERENCES workout_templates(id) ON DELETE CASCADE,
  CONSTRAINT fk_achievement_exercise FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE achievement_unlocks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  achievement_id SMALLINT UNSIGNED NOT NULL,
  workout_session_id BIGINT UNSIGNED NULL,
  unlocked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  context_json JSON NULL,
  notification_seen_at DATETIME NULL,
  UNIQUE KEY uq_achievement_unlock (achievement_id),
  CONSTRAINT fk_unlock_definition FOREIGN KEY (achievement_id) REFERENCES achievement_definitions(id) ON DELETE CASCADE,
  CONSTRAINT fk_unlock_session FOREIGN KEY (workout_session_id) REFERENCES workout_sessions(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE exercise_milestones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  exercise_id INT UNSIGNED NOT NULL,
  achievement_id SMALLINT UNSIGNED NOT NULL,
  metric VARCHAR(40) NOT NULL,
  target_weight DECIMAL(7,2) NULL,
  target_repetitions SMALLINT UNSIGNED NULL,
  target_set_count TINYINT UNSIGNED NULL,
  consecutive_sessions TINYINT UNSIGNED NULL,
  CONSTRAINT fk_milestone_exercise FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE,
  CONSTRAINT fk_milestone_achievement FOREIGN KEY (achievement_id) REFERENCES achievement_definitions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO settings (setting_key,setting_value) VALUES
('motivational_message_index','1'),('motivational_message_date',''),('timer_sound_enabled','1'),('timer_vibration_enabled','1'),('body_weight_goal_direction','unset')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

