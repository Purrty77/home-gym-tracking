<?php

declare(strict_types=1);

use Dotenv\Dotenv;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
Dotenv::createImmutable($root)->load();
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/Paris');

$directory = $root . '/storage/backups';
if (!is_dir($directory)) mkdir($directory, 0770, true);
$plain = $directory . '/muscu-' . date('Ymd-His') . '.sql';
$archive = $plain . '.gz';

putenv('MYSQL_PWD=' . $_ENV['DB_PASSWORD']);
$command = sprintf(
    'mariadb-dump --single-transaction --host=%s --port=%s --user=%s %s > %s',
    escapeshellarg($_ENV['DB_HOST']), escapeshellarg($_ENV['DB_PORT']),
    escapeshellarg($_ENV['DB_USERNAME']), escapeshellarg($_ENV['DB_DATABASE']), escapeshellarg($plain)
);
exec($command, $output, $status);
putenv('MYSQL_PWD');
if ($status !== 0) { @unlink($plain); fwrite(STDERR, "Échec de la sauvegarde.\n"); exit(1); }

file_put_contents($archive, gzencode((string) file_get_contents($plain), 9));
unlink($plain);
foreach (glob($directory . '/muscu-*.sql.gz') ?: [] as $file) {
    if (filemtime($file) < strtotime('-60 days')) unlink($file);
}
echo "Sauvegarde créée : {$archive}\n";
