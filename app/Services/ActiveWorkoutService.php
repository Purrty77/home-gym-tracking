<?php
namespace App\Services;

use App\Core\Database;
use App\Models\WorkoutTemplate;
use App\Models\BodyWeightEntry;
use PDO;
use RuntimeException;
use Throwable;

final class ActiveWorkoutService
{
    public function active(): ?array
    {
        $db=Database::connection();$session=$db->query("SELECT * FROM workout_sessions WHERE status='in_progress' ORDER BY id DESC LIMIT 1")->fetch();if(!$session)return null;
        return $this->state((int)$session['id']);
    }

    public function start(int $templateId): array
    {
        if($active=$this->active())return $active;
        $template=WorkoutTemplate::find($templateId);if(!$template)throw new RuntimeException('Workout plan not found.');$db=Database::connection();$db->beginTransaction();
        try{$entry=BodyWeightEntry::today()??BodyWeightEntry::latest();$weight=$entry['weight_kg']??null;$stmt=$db->prepare("INSERT INTO workout_sessions(workout_template_id,performed_at,session_type,body_weight_kg,status,current_set_position,started_at) VALUES(?,NOW(),?,?, 'in_progress',1,NOW())");$stmt->execute([$templateId,$template['session_type'],$weight]);$sessionId=(int)$db->lastInsertId();$insert=$db->prepare("INSERT INTO workout_exercises(workout_session_id,exercise_id,position,notes,status,target_set_count,target_repetitions_min,target_repetitions_max,planned_rest_seconds,started_at) VALUES(?,?,?,?,?,?,?,?,?,?)");$insertSet=$db->prepare("INSERT INTO exercise_sets(workout_exercise_id,position,set_type,rest_seconds,completed,is_extra) VALUES(?,?,?,?,0,0)");$first=null;foreach($template['exercises'] as $index=>$exercise){$status=$index===0?'active':'pending';$totalSets=(int)$exercise['set_count']+1;$insert->execute([$sessionId,$exercise['exercise_id'],$exercise['position'],$exercise['notes'],$status,$totalSets,$exercise['repetitions_min'],$exercise['repetitions_max'],$exercise['rest_seconds'],$index===0?date('Y-m-d H:i:s'):null]);$workoutExerciseId=(int)$db->lastInsertId();for($position=1;$position<=$totalSets;$position++)$insertSet->execute([$workoutExerciseId,$position,$position===1?'warmup':'working',$exercise['rest_seconds']]);if($index===0)$first=$workoutExerciseId;}$db->prepare('UPDATE workout_sessions SET current_workout_exercise_id=? WHERE id=?')->execute([$first,$sessionId]);$db->commit();return $this->state($sessionId);}catch(Throwable $e){$db->rollBack();throw $e;}
    }

    public function state(int $sessionId): array
    {
        $db=Database::connection();$stmt=$db->prepare('SELECT * FROM workout_sessions WHERE id=?');$stmt->execute([$sessionId]);$session=$stmt->fetch();if(!$session)throw new RuntimeException('Workout not found.');
        $stmt=$db->prepare("SELECT we.*,e.name,e.weight_increment,e.quick_repetition_values,e.tracking_metric,e.instructions,e.instruction_steps,e.image_path,e.gif_path,e.media_attribution,e.target_muscle,e.secondary_muscles FROM workout_exercises we JOIN exercises e ON e.id=we.exercise_id WHERE we.workout_session_id=? ORDER BY we.position");$stmt->execute([$sessionId]);$exercises=$stmt->fetchAll();$current=null;foreach($exercises as $exercise)if((int)$exercise['id']===(int)$session['current_workout_exercise_id'])$current=$exercise;
        $previous=[];$completedSets=[];$currentSet=null;$extraSets=[];$defaultWeight=null;if($current){$stmt=$db->prepare('SELECT * FROM exercise_sets WHERE workout_exercise_id=? ORDER BY position');$stmt->execute([$current['id']]);$allSets=$stmt->fetchAll();$completedSets=array_values(array_filter($allSets,fn($set)=>(int)$set['completed']===1));$extraSets=array_values(array_filter($allSets,fn($set)=>(int)($set['is_extra']??0)===1));foreach($allSets as $set)if((int)$set['position']===(int)$session['current_set_position'])$currentSet=$set;$stmt=$db->prepare("SELECT es.* FROM exercise_sets es JOIN workout_exercises we ON we.id=es.workout_exercise_id JOIN workout_sessions ws ON ws.id=we.workout_session_id WHERE we.exercise_id=? AND ws.status='completed' AND ws.id<>? AND es.set_type='working' AND es.completed=1 AND ws.id=(SELECT MAX(ws2.id) FROM workout_sessions ws2 JOIN workout_exercises we2 ON we2.workout_session_id=ws2.id WHERE we2.exercise_id=? AND ws2.status='completed') ORDER BY es.position");$stmt->execute([$current['exercise_id'],$sessionId,$current['exercise_id']]);$previous=$stmt->fetchAll();$setIndex=max(0,(int)$session['current_set_position']-2);$defaultWeight=$currentSet['weight_kg']??($previous[$setIndex]['weight_kg']??($previous?end($previous)['weight_kg']:null));}
        $remaining=$session['rest_ends_at']?max(0,strtotime($session['rest_ends_at'])-time()):0;$settings=$db->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('timer_sound_enabled','timer_vibration_enabled')")->fetchAll(PDO::FETCH_KEY_PAIR);
        return compact('session','exercises','current','currentSet','extraSets','previous','completedSets','defaultWeight','remaining','settings');
    }

    public function completeSet(array $data): array
    {
        $state=$this->active();if(!$state||!$state['current'])throw new RuntimeException('No active set.');$weight=$data['weight_kg']??null;$reps=$data['repetitions']??null;if(!is_numeric($weight)||!is_numeric($reps)||(float)$weight<0||(int)$reps<0)throw new RuntimeException('Enter a valid weight and repetition count.');$db=Database::connection();$session=$state['session'];$current=$state['current'];$position=(int)$session['current_set_position'];$requestedType=$data['set_type']??($state['currentSet']['set_type']??'working');$type=in_array($requestedType,['working','warmup','ramp'],true)?$requestedType:'working';
        $stmt=$db->prepare("SELECT es.weight_kg,es.repetitions FROM exercise_sets es JOIN workout_exercises we ON we.id=es.workout_exercise_id JOIN workout_sessions ws ON ws.id=we.workout_session_id WHERE we.exercise_id=? AND ws.id<>? AND ws.status='completed' AND es.set_type='working' AND es.completed=1 ORDER BY es.weight_kg DESC,es.repetitions DESC LIMIT 1");$stmt->execute([$current['exercise_id'],$session['id']]);$best=$stmt->fetch();$isRecord=$type==='working'&&(!$best||(float)$weight>(float)$best['weight_kg']||((float)$weight===(float)$best['weight_kg']&&(int)$reps>(int)$best['repetitions']));
        $existing=$state['currentSet'];if($existing){$stmt=$db->prepare('UPDATE exercise_sets SET set_type=?,weight_kg=?,repetitions=?,rest_seconds=?,notes=?,completed=1,completed_at=NOW(),is_personal_record=? WHERE id=?');$stmt->execute([$type,$weight,$reps,$current['planned_rest_seconds'],trim($data['notes']??'')?:null,$isRecord?1:0,$existing['id']]);$setId=(int)$existing['id'];}else{$stmt=$db->prepare('INSERT INTO exercise_sets(workout_exercise_id,position,set_type,weight_kg,repetitions,rest_seconds,notes,completed,completed_at,is_personal_record,is_extra) VALUES(?,?,?,?,?,?,?,?,NOW(),?,0)');$stmt->execute([$current['id'],$position,$type,$weight,$reps,$current['planned_rest_seconds'],trim($data['notes']??'')?:null,1,$isRecord?1:0]);$setId=(int)$db->lastInsertId();}$target=(int)$current['target_set_count'];
        if($position<$target){$rest=(int)$current['planned_rest_seconds'];$db->prepare('UPDATE workout_sessions SET current_set_position=current_set_position+1,rest_ends_at=DATE_ADD(NOW(),INTERVAL ? SECOND),rest_paused_seconds=NULL WHERE id=?')->execute([$rest,$session['id']]);return ['status'=>'set_completed','rest_seconds'=>$rest,'personal_record'=>$isRecord,'set_id'=>$setId];}
        $db->prepare("UPDATE workout_exercises SET status='completed',completed_at=NOW() WHERE id=?")->execute([$current['id']]);$stmt=$db->prepare("SELECT we.id,e.name FROM workout_exercises we JOIN exercises e ON e.id=we.exercise_id WHERE we.workout_session_id=? AND we.status='pending' ORDER BY we.position LIMIT 1");$stmt->execute([$session['id']]);$next=$stmt->fetch();if($next){$db->prepare("UPDATE workout_exercises SET status='active',started_at=NOW() WHERE id=?")->execute([$next['id']]);$db->prepare('UPDATE workout_sessions SET current_workout_exercise_id=?,current_set_position=1,rest_ends_at=NULL WHERE id=?')->execute([$next['id'],$session['id']]);return ['status'=>'exercise_completed','exercise'=>$current['name'],'best_weight'=>$weight,'best_reps'=>$reps,'next_exercise'=>$next['name'],'personal_record'=>$isRecord];}
        $db->prepare('UPDATE workout_sessions SET current_workout_exercise_id=NULL,rest_ends_at=NULL WHERE id=?')->execute([$session['id']]);return ['status'=>'workout_completed','personal_record'=>$isRecord];
    }

    public function addSet(): array
    {
        $state=$this->active();if(!$state||!$state['current'])throw new RuntimeException('No active exercise.');$db=Database::connection();$current=$state['current'];$position=(int)$current['target_set_count']+1;$weight=null;for($index=count($state['completedSets'])-1;$index>=0;$index--){if($state['completedSets'][$index]['weight_kg']!==null){$weight=$state['completedSets'][$index]['weight_kg'];break;}}$weight??=$state['defaultWeight'];
        $stmt=$db->prepare("INSERT INTO exercise_sets(workout_exercise_id,position,set_type,weight_kg,rest_seconds,completed,is_extra) VALUES(?,?,'working',?,?,0,1)");$stmt->execute([$current['id'],$position,$weight,$current['planned_rest_seconds']]);$setId=(int)$db->lastInsertId();$db->prepare('UPDATE workout_exercises SET target_set_count=target_set_count+1 WHERE id=?')->execute([$current['id']]);return ['status'=>'added','set_id'=>$setId,'position'=>$position,'weight_kg'=>$weight];
    }

    public function removeSet(int $setId,bool $confirmed=false): array
    {
        $state=$this->active();if(!$state||!$state['current'])throw new RuntimeException('No active exercise.');$db=Database::connection();$stmt=$db->prepare('SELECT * FROM exercise_sets WHERE id=? AND workout_exercise_id=? AND is_extra=1');$stmt->execute([$setId,$state['current']['id']]);$set=$stmt->fetch();if(!$set)throw new RuntimeException('This set cannot be removed.');if((int)$set['completed']===1&&!$confirmed)throw new RuntimeException('Confirmation is required to remove a completed set.');$db->beginTransaction();try{$db->prepare('DELETE FROM exercise_sets WHERE id=?')->execute([$setId]);$db->prepare('UPDATE exercise_sets SET position=position-1 WHERE workout_exercise_id=? AND position>? ORDER BY position')->execute([$state['current']['id'],$set['position']]);$db->prepare('UPDATE workout_exercises SET target_set_count=GREATEST(target_set_count-1,1) WHERE id=?')->execute([$state['current']['id']]);if((int)$set['position']<(int)$state['session']['current_set_position'])$db->prepare('UPDATE workout_sessions SET current_set_position=GREATEST(current_set_position-1,1) WHERE id=?')->execute([$state['session']['id']]);$db->commit();return ['status'=>'removed'];}catch(Throwable $e){$db->rollBack();throw $e;}
    }

    public function bodyWeight(mixed $weight): array
    {
        if(!is_numeric($weight))throw new RuntimeException('Enter a valid body weight.');$state=$this->active();if(!$state)throw new RuntimeException('No active workout.');$date=date('Y-m-d',strtotime($state['session']['performed_at']));$entry=BodyWeightEntry::record($date,(float)$weight,'workout');return ['status'=>'saved','weight_kg'=>(float)$entry['weight_kg']];
    }

    public function timer(string $action): array
    {
        $state=$this->active();if(!$state)return ['remaining'=>0];$id=$state['session']['id'];$db=Database::connection();if($action==='add')$db->prepare('UPDATE workout_sessions SET rest_ends_at=DATE_ADD(COALESCE(rest_ends_at,NOW()),INTERVAL 30 SECOND) WHERE id=?')->execute([$id]);elseif($action==='pause')$db->prepare('UPDATE workout_sessions SET rest_paused_seconds=GREATEST(TIMESTAMPDIFF(SECOND,NOW(),rest_ends_at),0),rest_ends_at=NULL WHERE id=?')->execute([$id]);elseif($action==='resume')$db->prepare('UPDATE workout_sessions SET rest_ends_at=DATE_ADD(NOW(),INTERVAL COALESCE(rest_paused_seconds,0) SECOND),rest_paused_seconds=NULL WHERE id=?')->execute([$id]);else $db->prepare('UPDATE workout_sessions SET rest_ends_at=NULL,rest_paused_seconds=NULL WHERE id=?')->execute([$id]);return ['remaining'=>$this->active()['remaining']];
    }

    public function finish(?string $notes=null): int
    {
        $state=$this->active();if(!$state)throw new RuntimeException('No active workout.');$id=(int)$state['session']['id'];Database::connection()->prepare("UPDATE workout_sessions SET status='completed',notes=COALESCE(NULLIF(?,''),notes),completed_at=NOW(),current_workout_exercise_id=NULL,rest_ends_at=NULL WHERE id=?")->execute([trim((string)$notes),$id]);return $id;
    }

    public function abandon(): void
    {
        $state=$this->active();if($state)Database::connection()->prepare("UPDATE workout_sessions SET status='abandoned',completed_at=NOW(),current_workout_exercise_id=NULL,rest_ends_at=NULL WHERE id=?")->execute([$state['session']['id']]);
    }

    public function cancel(): void
    {
        $state=$this->active();
        if(!$state)return;
        $stmt=Database::connection()->prepare("DELETE FROM workout_sessions WHERE id=? AND status='in_progress'");
        $stmt->execute([$state['session']['id']]);
    }

    public function summary(): array
    {
        $state=$this->active();if(!$state)throw new RuntimeException('No active workout.');$stmt=Database::connection()->prepare("SELECT e.name,MAX(es.weight_kg) best_weight,MAX(CASE WHEN es.weight_kg=(SELECT MAX(es2.weight_kg) FROM exercise_sets es2 WHERE es2.workout_exercise_id=we.id AND es2.completed=1) THEN es.repetitions END) best_reps,SUM(es.set_type='working') working_sets,MAX(es.is_personal_record) personal_record FROM workout_exercises we JOIN exercises e ON e.id=we.exercise_id LEFT JOIN exercise_sets es ON es.workout_exercise_id=we.id AND es.completed=1 WHERE we.workout_session_id=? GROUP BY we.id,e.name ORDER BY we.position");$stmt->execute([$state['session']['id']]);$state['summary']=$stmt->fetchAll();return $state;
    }
}
