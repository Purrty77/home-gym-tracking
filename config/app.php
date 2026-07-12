<?php

return [
    'name' => 'Home Gym',
    'url' => $_ENV['APP_URL'] ?? 'http://muscu.local',
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Europe/Paris',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL),
];
