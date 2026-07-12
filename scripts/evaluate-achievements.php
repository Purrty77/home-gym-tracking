<?php
declare(strict_types=1);

use App\Core\Database;
use App\Services\AchievementService;
use Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';
Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$service=new AchievementService();
$ids=Database::connection()->query("SELECT id FROM workout_sessions WHERE status='completed' ORDER BY performed_at,id")->fetchAll(PDO::FETCH_COLUMN);
$unlocked=0;
foreach($ids as $id)$unlocked+=count($service->evaluateWorkout((int)$id));
echo "Achievement evaluation complete: {$unlocked} newly unlocked.\n";
