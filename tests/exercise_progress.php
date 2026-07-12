<?php
declare(strict_types=1);

use App\Core\Database;
use App\Services\ExerciseProgressService;
use Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';
Dotenv::createImmutable(dirname(__DIR__))->load();
$db=Database::connection();$exerciseId=(int)$db->query("SELECT we.exercise_id FROM workout_exercises we JOIN workout_sessions ws ON ws.id=we.workout_session_id JOIN exercise_sets es ON es.workout_exercise_id=we.id WHERE ws.status='completed' AND es.set_type='working' LIMIT 1")->fetchColumn();
if(!$exerciseId)throw new RuntimeException('No completed exercise exists for progression testing.');
$result=(new ExerciseProgressService())->build($exerciseId);
if(!$result['record'])throw new RuntimeException('Personal record was not calculated.');
if($result['stats']['working_sets']<1)throw new RuntimeException('Working sets were not aggregated.');
if(!$result['recommendation']['title'])throw new RuntimeException('Recommendation is missing.');
if(!$result['history'])throw new RuntimeException('Grouped workout history is missing.');
if(count($result['insights'])>3)throw new RuntimeException('Too many insights were returned.');
echo "✓ Exercise progression: records, statistics, recommendation and grouped history\n";
