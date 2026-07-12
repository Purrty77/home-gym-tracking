<?php
declare(strict_types=1);
use App\Core\Database;
use App\Models\WorkoutTemplate;
use App\Services\ActiveWorkoutService;
use Dotenv\Dotenv;
require dirname(__DIR__).'/vendor/autoload.php';Dotenv::createImmutable(dirname(__DIR__))->load();
$service=new ActiveWorkoutService();if($service->active())throw new RuntimeException('An active workout already exists; workout-mode test was not run.');$template=WorkoutTemplate::forDay(7)??throw new RuntimeException('Leg workout plan is missing.');$state=$service->start((int)$template['id']);$sessionId=(int)$state['session']['id'];
try{if($state['current']['name']!=='Leg Press'||(int)$state['current']['target_set_count']!==3)throw new RuntimeException('Workout plan was not initialized correctly.');$sets=0;$exerciseCompletions=0;while(($state=$service->active())&&$state['current']){$result=$service->completeSet(['weight_kg'=>$state['defaultWeight']?:50,'repetitions'=>12,'set_type'=>'working']);$sets++;if($result['status']==='exercise_completed')$exerciseCompletions++;if($sets>20)throw new RuntimeException('Workout did not advance.');}$summary=$service->summary();if($sets!==12||count($summary['summary'])!==4||$exerciseCompletions!==3)throw new RuntimeException('Workout completion state is incorrect.');$finished=$service->finish('Automated workout-mode test');if($finished!==$sessionId)throw new RuntimeException('Workout was not finished.');echo "✓ Workout Mode: start, set persistence, exercise advancement, recovery and completion\n";}finally{Database::connection()->prepare('DELETE FROM workout_sessions WHERE id=?')->execute([$sessionId]);}

