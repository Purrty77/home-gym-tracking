USE muscu;

ALTER TABLE measurements ADD COLUMN measurement_month CHAR(7) NULL AFTER measured_on;
UPDATE measurements SET measurement_month=DATE_FORMAT(measured_on, '%Y-%m');

DELETE newer FROM measurements newer
JOIN measurements older
  ON newer.measurement_month=older.measurement_month
 AND newer.id>older.id;

ALTER TABLE measurements DROP INDEX uq_measurement_date;
ALTER TABLE measurements MODIFY measurement_month CHAR(7) NOT NULL;
ALTER TABLE measurements ADD UNIQUE KEY uq_measurement_month (measurement_month);

INSERT INTO settings (setting_key,setting_value) VALUES ('measurement_reminder_day','1')
ON DUPLICATE KEY UPDATE setting_value='1';
