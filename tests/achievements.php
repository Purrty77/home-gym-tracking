<?php
declare(strict_types=1);
use App\Core\Database;
use App\Services\AchievementCatalog;
use App\Services\AchievementService;
use Dotenv\Dotenv;
require dirname(__DIR__).'/vendor/autoload.php';Dotenv::createImmutable(dirname(__DIR__))->load();
$count=(new AchievementCatalog())->sync();if($count!==102)throw new RuntimeException("Expected 102 achievements, found {$count}.");$sessionId=(int)Database::connection()->query("SELECT id FROM workout_sessions WHERE status='completed' ORDER BY id LIMIT 1")->fetchColumn();if($sessionId)(new AchievementService())->evaluateWorkout($sessionId);$rows=(new AchievementService())->allWithProgress();if(count($rows)!==102)throw new RuntimeException('Achievement progress list is incomplete.');echo "✓ Achievements: 102 definitions, evaluation, unlocking and progress\n";

