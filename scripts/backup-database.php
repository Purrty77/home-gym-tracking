<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use App\Services\BackupRetentionService;

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

$contents = file_get_contents($plain);
$compressed = $contents !== false && $contents !== '' ? gzencode($contents, 9) : false;
if ($compressed === false || file_put_contents($archive, $compressed) === false) {
    @unlink($plain);
    @unlink($archive);
    fwrite(STDERR, "Échec de la compression de la sauvegarde.\n");
    exit(1);
}
unlink($plain);
$deleted = (new BackupRetentionService())->prune($directory, 5);
echo "Sauvegarde créée : {$archive}\n";
if ($deleted) echo count($deleted) . " ancienne(s) sauvegarde(s) supprimée(s).\n";
