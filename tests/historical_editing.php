<?php
declare(strict_types=1);

use App\Core\Database;
use App\Models\WorkoutSession;
use App\Services\HistoricalWorkoutRecalculationService;
use Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';
Dotenv::createImmutable(dirname(__DIR__))->load();

$db=Database::connection();
$exercise=$db->query("SELECT id,load_semantics FROM exercises WHERE is_active=1 AND load_semantics='per_dumbbell' ORDER BY id LIMIT 1")->fetch();
if(!$exercise)throw new RuntimeException('A dumbbell exercise is required for the historical editor test.');
$otherExerciseId=(int)$db->query('SELECT id FROM exercises WHERE is_active=1 AND id<>'.(int)$exercise['id'].' ORDER BY id LIMIT 1')->fetchColumn();
$unlockFloor=(int)$db->query('SELECT COALESCE(MAX(id),0) FROM achievement_unlocks')->fetchColumn();
$sessionId=0;

try{
    $sessionId=WorkoutSession::create([
        'performed_at'=>'2098-01-15 18:00:00','session_type'=>'Historical editor test','body_weight_kg'=>'80.5','notes'=>'Before edit',
        'exercises'=>[['exercise_id'=>$exercise['id'],'load_semantics'=>'per_dumbbell','status'=>'completed','sets'=>[['set_type'=>'working','weight_kg'=>'20','repetitions'=>'10','rest_seconds'=>'90']]]],
    ]);
    WorkoutSession::update($sessionId,[
        'workout_template_id'=>'','performed_at'=>'2098-01-15 18:30:00','session_type'=>'Historical editor updated','body_weight_kg'=>'80.2','notes'=>'After edit',
        'exercises'=>[
            ['exercise_id'=>$exercise['id'],'load_semantics'=>'total','status'=>'completed','notes'=>'Exercise note','sets'=>[
                ['set_type'=>'warmup','weight_kg'=>'20','repetitions'=>'12','rest_seconds'=>'60','notes'=>'Warm-up'],
                ['set_type'=>'working','rest_seconds'=>'90','notes'=>'Drop set','segments'=>[['weight_kg'=>'40','repetitions'=>'8'],['weight_kg'=>'30','repetitions'=>'4']]],
            ]],
            ['exercise_id'=>$otherExerciseId,'status'=>'skipped','notes'=>'Machine occupied','sets'=>[]],
        ],
    ]);
    (new HistoricalWorkoutRecalculationService())->recalculate($sessionId);
    $updated=WorkoutSession::find($sessionId)??throw new RuntimeException('Edited workout no longer exists.');
    if($updated['session_type']!=='Historical editor updated'||(float)$updated['body_weight_kg']!==80.2||!$updated['last_edited_at'])throw new RuntimeException('Workout metadata was not updated.');
    $completed=array_values(array_filter($updated['rows'],fn($row)=>$row['exercise_status']==='completed'));
    $skipped=array_values(array_filter($updated['rows'],fn($row)=>$row['exercise_status']==='skipped'));
    if(count($completed)!==2||count($skipped)!==1)throw new RuntimeException('Exercise completion or set persistence is incorrect.');
    $drop=array_values(array_filter($completed,fn($row)=>$row['set_type']==='working'))[0]??throw new RuntimeException('Working drop set is missing.');
    if($drop['load_semantics']!=='total'||count($drop['segments'])!==2||(float)$drop['segments'][1]['weight_kg']!==30.0)throw new RuntimeException('Drop-set segments or dumbbell mode were not preserved.');
    $workingCount=(int)$db->query("SELECT COUNT(*) FROM exercise_sets es JOIN workout_exercises we ON we.id=es.workout_exercise_id WHERE we.workout_session_id={$sessionId} AND we.status='completed' AND es.set_type='working'")->fetchColumn();
    if($workingCount!==1)throw new RuntimeException('Warm-up or skipped work leaked into working-set totals.');
    echo "✓ Historical workout editing: metadata, skipped exercise, warm-up, drop set and dumbbell mode\n";
}finally{
    if($sessionId)WorkoutSession::delete($sessionId);
    $db->exec('DELETE FROM achievement_unlocks WHERE id>'.(int)$unlockFloor);
}
