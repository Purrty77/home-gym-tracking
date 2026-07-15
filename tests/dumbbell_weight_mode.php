<?php
declare(strict_types=1);

use App\Core\Database;
use App\Models\Exercise;
use App\Models\WorkoutSession;
use App\Services\ExerciseProgressService;
use Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';
Dotenv::createImmutable(dirname(__DIR__))->load();

$db=Database::connection();$dumbbellId=(int)$db->query("SELECT id FROM equipment WHERE name='Dumbbells'")->fetchColumn();$groupId=(int)$db->query('SELECT id FROM muscle_groups ORDER BY id LIMIT 1')->fetchColumn();$unlockFloor=(int)$db->query('SELECT COALESCE(MAX(id),0) FROM achievement_unlocks')->fetchColumn();$exerciseId=0;$sessionIds=[];
try{
    $base=['muscle_group_id'=>$groupId,'equipment_id'=>$dumbbellId,'name'=>'Dumbbell mode test','variant'=>'','notes'=>'Disposable','recommended_rest_seconds'=>90];
    $exerciseId=Exercise::create($base+['load_semantics'=>'per_dumbbell']);$exercise=Exercise::find($exerciseId);if($exercise['load_semantics']!=='per_dumbbell')throw new RuntimeException('Dumbbell exercises must default to per-dumbbell weight.');
    $sessionIds[]=WorkoutSession::create(['performed_at'=>'2098-03-01 10:00:00','session_type'=>'Dumbbell mode test','body_weight_kg'=>'','notes'=>'','exercises'=>[['exercise_id'=>$exerciseId,'sets'=>[['set_type'=>'working','weight_kg'=>10,'repetitions'=>10,'rest_seconds'=>90]]]]]);
    $before=WorkoutSession::find($sessionIds[0]);if($before['rows'][0]['load_semantics']!=='per_dumbbell')throw new RuntimeException('The workout did not snapshot the exercise weight mode.');

    Exercise::update($exerciseId,$base+['load_semantics'=>'total','history_weight_action'=>'convert']);$converted=WorkoutSession::find($sessionIds[0]);if($converted['rows'][0]['load_semantics']!=='total'||(float)$converted['rows'][0]['weight_kg']!==20.0||(float)$converted['rows'][0]['segments'][0]['weight_kg']!==20.0)throw new RuntimeException('Explicit history conversion did not update the stored value and mode.');

    Exercise::update($exerciseId,$base+['load_semantics'=>'per_dumbbell','history_weight_action'=>'keep']);$kept=WorkoutSession::find($sessionIds[0]);if($kept['rows'][0]['load_semantics']!=='total'||(float)$kept['rows'][0]['weight_kg']!==20.0)throw new RuntimeException('Keep history changed a recorded value.');
    $progress=(new ExerciseProgressService())->build($exerciseId);$set=$progress['history'][0]['working'][0];if((float)$set['weight_kg']!==10.0||(float)$set['entered_weight_kg']!==20.0||(float)$set['calculated_total_load_kg']!==20.0)throw new RuntimeException('Display, entered, and calculated total weights are inconsistent.');
    echo "✓ Dumbbell weight mode: defaults, snapshots, faithful history, display normalization and explicit conversion\n";
}finally{
    foreach($sessionIds as $sessionId)WorkoutSession::delete($sessionId);
    $db->exec('DELETE FROM achievement_unlocks WHERE id>'.(int)$unlockFloor);
    if($exerciseId)$db->exec('DELETE FROM exercises WHERE id='.(int)$exerciseId);
}
