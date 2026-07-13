<?php
declare(strict_types=1);
use App\Core\Database;
use App\Services\AchievementCatalog;
use App\Services\AchievementService;
use Dotenv\Dotenv;
require dirname(__DIR__).'/vendor/autoload.php';Dotenv::createImmutable(dirname(__DIR__))->load();
$count=(new AchievementCatalog())->sync();if($count!==102)throw new RuntimeException("Expected 102 achievements, found {$count}.");$service=new AchievementService();$sessionId=(int)Database::connection()->query("SELECT id FROM workout_sessions WHERE status='completed' ORDER BY id LIMIT 1")->fetchColumn();if($sessionId){$definitionId=(int)Database::connection()->query("SELECT id FROM achievement_definitions WHERE code='workouts_1'")->fetchColumn();Database::connection()->prepare('DELETE FROM achievement_unlocks WHERE achievement_id=?')->execute([$definitionId]);}$rows=$service->allWithProgress();if(count($rows)!==102)throw new RuntimeException('Achievement progress list is incomplete.');if($sessionId){$first=array_values(array_filter($rows,fn($row)=>$row['code']==='workouts_1'))[0]??null;if(!$first||!$first['unlocked_at'])throw new RuntimeException('A completed achievement was not reconciled.');}echo "✓ Achievements: 102 definitions, reconciliation, unlocking and progress\n";
