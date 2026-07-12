INSERT INTO settings(setting_key,setting_value) VALUES ('appearance_theme','dark')
ON DUPLICATE KEY UPDATE setting_value=setting_value;
