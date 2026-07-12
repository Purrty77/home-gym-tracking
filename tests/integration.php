<?php
declare(strict_types=1);
use App\Models\Exercise;
use App\Models\Measurement;
use App\Models\WorkoutSession;
use App\Models\WorkoutTemplate;
use App\Services\StatisticsService;
use Dotenv\Dotenv;
require dirname(__DIR__).'/vendor/autoload.php';
Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$sundayTemplate=WorkoutTemplate::forDay(7);if(!$sundayTemplate||$sundayTemplate['session_type']!=='Legs'||count(WorkoutTemplate::asFormData($sundayTemplate))!==4)throw new RuntimeException('Sunday workout plan is incorrect');

$exercise=Exercise::all()[0]??throw new RuntimeException('Catalogue vide');
$sessionId=WorkoutSession::create(['performed_at'=>'2026-07-12 12:00:00','session_type'=>'Test intégration','body_weight_kg'=>'80.5','notes'=>'Automatique','exercises'=>[['exercise_id'=>$exercise['id'],'sets'=>[['set_type'=>'working','weight_kg'=>'42.5','repetitions'=>'10','rest_seconds'=>'90','notes'=>'Test']]]]]);
$session=WorkoutSession::find($sessionId);
if(!$session||count($session['rows'])!==1||(float)$session['rows'][0]['weight_kg']!==42.5)throw new RuntimeException('Workout creation is incorrect');
WorkoutSession::update($sessionId,['performed_at'=>'2026-07-12 12:30:00','session_type'=>'Test updated','body_weight_kg'=>'81','notes'=>'Updated','exercises'=>[['exercise_id'=>$exercise['id'],'sets'=>[['set_type'=>'working','weight_kg'=>'45','repetitions'=>'8','rest_seconds'=>'100','notes'=>'Update']]]]]);
$updated=WorkoutSession::find($sessionId);
if($updated['session_type']!=='Test updated'||(float)$updated['rows'][0]['weight_kg']!==45.0)throw new RuntimeException('Workout update is incorrect');
$secondSessionId=WorkoutSession::create(['performed_at'=>'2026-07-13 12:00:00','session_type'=>'Progress test','body_weight_kg'=>'81','notes'=>null,'exercises'=>[['exercise_id'=>$exercise['id'],'sets'=>[['set_type'=>'working','weight_kg'=>'50','repetitions'=>'8','rest_seconds'=>'90','notes'=>null]]]]]);
$history=Exercise::history((int)$exercise['id'],'2026-07-01','2026-07-31');if(!$history)throw new RuntimeException('Exercise history is empty');
Measurement::create(['measured_on'=>'2099-01-01','weight_kg'=>'80.5','waist_cm'=>'90']);
Measurement::create(['measured_on'=>'2099-01-20','weight_kg'=>'81','waist_cm'=>'89']);
$measurement=Measurement::forMonth('2099-01')??throw new RuntimeException('Measurement is missing');if($measurement['measured_on']!=='2099-01-20'||(float)$measurement['weight_kg']!==81.0)throw new RuntimeException('Monthly measurement was not updated');
$stats=(new StatisticsService())->dashboard();if(!isset($stats['week'],$stats['records'],$stats['weight'],$stats['progress'],$stats['todayWorkout'],$stats['totalWorkouts'],$stats['longestStreak']))throw new RuntimeException('Statistics are incomplete');$trend=array_values(array_filter($stats['progress'],fn($row)=>(int)$row['id']===(int)$exercise['id']))[0]??throw new RuntimeException('Progress trend is missing');if((float)$trend['latest_weight']!==50.0||(float)$trend['previous_weight']!==45.0||(float)$trend['all_time_max']!==50.0)throw new RuntimeException('Progress comparison is incorrect');
Measurement::delete((int)$measurement['id']);WorkoutSession::delete($secondSessionId);WorkoutSession::delete($sessionId);
if(WorkoutSession::find($sessionId)!==null)throw new RuntimeException('Suppression de séance incorrecte');
echo "✓ MariaDB integration: schema, CRUD, history and statistics\n";
