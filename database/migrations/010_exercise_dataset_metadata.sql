INSERT IGNORE INTO muscle_groups(name,sort_order) VALUES('Abs',75);

ALTER TABLE exercises
  ADD COLUMN external_dataset_id VARCHAR(20) NULL AFTER is_active,
  ADD COLUMN canonical_name VARCHAR(180) NULL AFTER external_dataset_id,
  ADD COLUMN dataset_category VARCHAR(100) NULL AFTER canonical_name,
  ADD COLUMN body_part VARCHAR(100) NULL AFTER dataset_category,
  ADD COLUMN dataset_equipment VARCHAR(100) NULL AFTER body_part,
  ADD COLUMN target_muscle VARCHAR(150) NULL AFTER dataset_equipment,
  ADD COLUMN dataset_muscle_group VARCHAR(150) NULL AFTER target_muscle,
  ADD COLUMN secondary_muscles JSON NULL AFTER dataset_muscle_group,
  ADD COLUMN instructions TEXT NULL AFTER secondary_muscles,
  ADD COLUMN instruction_steps JSON NULL AFTER instructions,
  ADD COLUMN image_path VARCHAR(255) NULL AFTER instruction_steps,
  ADD COLUMN gif_path VARCHAR(255) NULL AFTER image_path,
  ADD COLUMN media_attribution VARCHAR(255) NULL AFTER gif_path,
  ADD COLUMN source VARCHAR(100) NULL AFTER media_attribution,
  ADD COLUMN imported_at DATETIME NULL AFTER source,
  ADD UNIQUE KEY uq_exercises_dataset_source_id(source,external_dataset_id);
