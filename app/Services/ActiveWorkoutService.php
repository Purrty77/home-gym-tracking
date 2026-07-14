<?php
namespace App\Services;

use App\Core\Database;
use App\Models\BodyWeightEntry;
use App\Models\WorkoutTemplate;
use PDO;
use RuntimeException;
use Throwable;

final class ActiveWorkoutService
{
    public function active(): ?array
    {
        $db=Database::connection();
        $session=$db->query("SELECT * FROM workout_sessions WHERE status='in_progress' ORDER BY id DESC LIMIT 1")->fetch();
        return $session?$this->state((int)$session['id']):null;
    }

    public function start(int $templateId): array
    {
        if($active=$this->active())return $active;
        $template=WorkoutTemplate::find($templateId);
        if(!$template)throw new RuntimeException('Workout plan not found.');
        $db=Database::connection();
        $policy=$this->warmupPolicy($db);
        $decision=['ask'=>'pending','add'=>'added','never'=>'skipped'][$policy];
        $db->beginTransaction();
        try{
            $entry=BodyWeightEntry::today()??BodyWeightEntry::latest();
            $stmt=$db->prepare("INSERT INTO workout_sessions(workout_template_id,performed_at,session_type,body_weight_kg,status,current_set_position,started_at) VALUES(?,NOW(),?,?, 'in_progress',1,NOW())");
            $stmt->execute([$templateId,$template['session_type'],$entry['weight_kg']??null]);
            $sessionId=(int)$db->lastInsertId();
            $insertExercise=$db->prepare("INSERT INTO workout_exercises(workout_session_id,exercise_id,position,notes,status,target_set_count,target_repetitions_min,target_repetitions_max,planned_rest_seconds,warmup_decision,started_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)");
            $insertSet=$db->prepare("INSERT INTO exercise_sets(workout_exercise_id,position,set_type,rest_seconds,completed,is_extra) VALUES(?,?,?,?,0,0)");
            $first=null;
            foreach($template['exercises'] as $index=>$exercise){
                $status=$index===0?'active':'pending';
                $insertExercise->execute([$sessionId,$exercise['exercise_id'],$exercise['position'],$exercise['notes'],$status,$exercise['set_count'],$exercise['repetitions_min'],$exercise['repetitions_max'],$exercise['rest_seconds'],$decision,$index===0?date('Y-m-d H:i:s'):null]);
                $workoutExerciseId=(int)$db->lastInsertId();
                $position=1;
                if($decision==='added')$insertSet->execute([$workoutExerciseId,$position++,'warmup',$exercise['rest_seconds']]);
                for($working=0;$working<(int)$exercise['set_count'];$working++)$insertSet->execute([$workoutExerciseId,$position++,'working',$exercise['rest_seconds']]);
                if($index===0)$first=$workoutExerciseId;
            }
            $db->prepare('UPDATE workout_sessions SET current_workout_exercise_id=? WHERE id=?')->execute([$first,$sessionId]);
            $db->commit();
            return $this->state($sessionId);
        }catch(Throwable $e){$db->rollBack();throw $e;}
    }

    public function state(int $sessionId): array
    {
        $db=Database::connection();
        $stmt=$db->prepare('SELECT * FROM workout_sessions WHERE id=?');$stmt->execute([$sessionId]);$session=$stmt->fetch();
        if(!$session)throw new RuntimeException('Workout not found.');
        $stmt=$db->prepare("SELECT we.*,e.name,e.muscle_group_id,mg.name muscle_group,e.weight_increment,e.quick_repetition_values,e.tracking_metric,e.instructions,e.instruction_steps,e.image_path,e.gif_path,e.media_attribution,e.target_muscle,e.secondary_muscles FROM workout_exercises we JOIN exercises e ON e.id=we.exercise_id JOIN muscle_groups mg ON mg.id=e.muscle_group_id WHERE we.workout_session_id=? ORDER BY we.position");
        $stmt->execute([$sessionId]);$exercises=$stmt->fetchAll();
        $current=null;foreach($exercises as $exercise)if((int)$exercise['id']===(int)$session['current_workout_exercise_id'])$current=$exercise;

        $previous=[];$completedSets=[];$completedWorking=[];$currentSet=null;$extraSets=[];$replacementCandidates=[];$defaultWeight=null;
        if($current){
            $stmt=$db->prepare('SELECT * FROM exercise_sets WHERE workout_exercise_id=? ORDER BY position');$stmt->execute([$current['id']]);$allSets=$stmt->fetchAll();
            $completedSets=array_values(array_filter($allSets,fn($set)=>(int)$set['completed']===1));
            $completedWorking=array_values(array_filter($completedSets,fn($set)=>$set['set_type']==='working'));
            $extraSets=array_values(array_filter($allSets,fn($set)=>(int)$set['is_extra']===1));
            foreach($allSets as $set)if((int)$set['position']===(int)$current['current_set_position'])$currentSet=$set;
            if(!$currentSet)foreach($allSets as $set)if((int)$set['completed']===0){$currentSet=$set;break;}
            if($currentSet&&(int)$currentSet['position']!==(int)$current['current_set_position']){$current['current_set_position']=$currentSet['position'];$session['current_set_position']=$currentSet['position'];$db->prepare('UPDATE workout_exercises SET current_set_position=? WHERE id=?')->execute([$currentSet['position'],$current['id']]);$db->prepare('UPDATE workout_sessions SET current_set_position=? WHERE id=?')->execute([$currentSet['position'],$sessionId]);}

            $stmt=$db->prepare("SELECT es.* FROM exercise_sets es JOIN workout_exercises we ON we.id=es.workout_exercise_id JOIN workout_sessions ws ON ws.id=we.workout_session_id WHERE we.exercise_id=? AND ws.status='completed' AND ws.id<>? AND es.set_type='working' AND es.completed=1 AND ws.id=(SELECT MAX(ws2.id) FROM workout_sessions ws2 JOIN workout_exercises we2 ON we2.workout_session_id=ws2.id WHERE we2.exercise_id=? AND ws2.status='completed') ORDER BY es.position");
            $stmt->execute([$current['exercise_id'],$sessionId,$current['exercise_id']]);$previous=$stmt->fetchAll();
            if($currentSet&&$currentSet['weight_kg']!==null)$defaultWeight=$currentSet['weight_kg'];
            elseif($currentSet&&$currentSet['set_type']==='working'&&$completedWorking)$defaultWeight=end($completedWorking)['weight_kg'];
            elseif($previous)$defaultWeight=$previous[0]['weight_kg'];

            if(!$completedSets){
                $stmt=$db->prepare("SELECT e.id,e.name,e.image_path,e.target_muscle,eq.name equipment,e.recommended_rest_seconds FROM exercises e LEFT JOIN equipment eq ON eq.id=e.equipment_id WHERE e.is_active=1 AND e.muscle_group_id=? AND e.id<>? AND NOT EXISTS(SELECT 1 FROM workout_exercises used WHERE used.workout_session_id=? AND used.exercise_id=e.id) ORDER BY e.name");
                $stmt->execute([$current['muscle_group_id'],$current['exercise_id'],$sessionId]);$replacementCandidates=$stmt->fetchAll();
            }
        }
        foreach($exercises as &$exercise)$exercise['rest_remaining']=$exercise['rest_ends_at']?max(0,strtotime($exercise['rest_ends_at'])-time()):(int)($exercise['rest_paused_seconds']??0);unset($exercise);
        if($current)foreach($exercises as $exercise)if((int)$exercise['id']===(int)$current['id']){$current=$exercise;break;}
        $remainingExercises=array_values(array_filter($exercises,fn($exercise)=>!in_array($exercise['status'],['completed','skipped'],true)));
        $activeExercises=array_values(array_filter($remainingExercises,fn($exercise)=>$exercise['status']==='active'));
        $remaining=$current&&$current['rest_ends_at']?max(0,strtotime($current['rest_ends_at'])-time()):0;
        $settings=$db->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('timer_sound_enabled','timer_vibration_enabled','warmup_default_behavior')")->fetchAll(PDO::FETCH_KEY_PAIR);
        $warmupPrompt=$current&&$current['warmup_decision']==='pending'&&!$completedSets;
        $workingSetNumber=$currentSet&&$currentSet['set_type']==='working'?count($completedWorking)+1:null;
        return compact('session','exercises','remainingExercises','activeExercises','current','currentSet','extraSets','replacementCandidates','previous','completedSets','completedWorking','defaultWeight','remaining','settings','warmupPrompt','workingSetNumber');
    }

    public function decideWarmup(bool $add): array
    {
        $state=$this->active();if(!$state||!$state['current'])throw new RuntimeException('No active exercise.');
        if($state['current']['warmup_decision']!=='pending')return ['status'=>'already_decided'];
        if($state['completedSets'])throw new RuntimeException('Warm-up choice is no longer available.');
        $db=Database::connection();$db->beginTransaction();
        try{
            if($add){
                $db->prepare('UPDATE exercise_sets SET position=position+1 WHERE workout_exercise_id=? ORDER BY position DESC')->execute([$state['current']['id']]);
                $db->prepare("INSERT INTO exercise_sets(workout_exercise_id,position,set_type,rest_seconds,completed,is_extra) VALUES(?,1,'warmup',?,0,0)")->execute([$state['current']['id'],$state['current']['planned_rest_seconds']]);
            }
            $db->prepare('UPDATE workout_exercises SET warmup_decision=? WHERE id=?')->execute([$add?'added':'skipped',$state['current']['id']]);
            $db->prepare('UPDATE workout_exercises SET current_set_position=1 WHERE id=?')->execute([$state['current']['id']]);
            $db->prepare('UPDATE workout_sessions SET current_set_position=1 WHERE id=?')->execute([$state['session']['id']]);
            $db->commit();return ['status'=>$add?'added':'skipped'];
        }catch(Throwable $e){$db->rollBack();throw $e;}
    }

    public function completeSet(array $data): array
    {
        $state=$this->active();if(!$state||!$state['current']||!$state['currentSet'])throw new RuntimeException('No active set.');
        if($state['warmupPrompt'])throw new RuntimeException('Choose whether to warm up first.');
        $segments=$this->parseSegments($data);
        $best=$segments[0];foreach($segments as $segment)if($this->comparePerformance($segment,$best)>0)$best=$segment;
        $db=Database::connection();$session=$state['session'];$current=$state['current'];$existing=$state['currentSet'];$type=$existing['set_type'];
        $recordFlags=$type==='working'?$this->personalRecordFlags((int)$current['exercise_id'],(int)$session['id'],$segments):array_fill(0,count($segments),false);$isRecord=in_array(true,$recordFlags,true);
        $db->beginTransaction();
        try{
            $stmt=$db->prepare('UPDATE exercise_sets SET weight_kg=?,repetitions=?,rest_seconds=?,notes=?,completed=1,completed_at=NOW(),is_personal_record=? WHERE id=?');
            $stmt->execute([$best['weight_kg'],$best['repetitions'],$current['planned_rest_seconds'],trim($data['notes']??'')?:null,$isRecord?1:0,$existing['id']]);
            $db->prepare('DELETE FROM exercise_set_segments WHERE exercise_set_id=?')->execute([$existing['id']]);
            $insert=$db->prepare('INSERT INTO exercise_set_segments(exercise_set_id,position,weight_kg,repetitions,is_personal_record) VALUES(?,?,?,?,?)');
            foreach($segments as $index=>$segment)$insert->execute([$existing['id'],$index+1,$segment['weight_kg'],$segment['repetitions'],$recordFlags[$index]?1:0]);
            $stmt=$db->prepare('SELECT position FROM exercise_sets WHERE workout_exercise_id=? AND completed=0 ORDER BY position LIMIT 1');$stmt->execute([$current['id']]);$nextPosition=$stmt->fetchColumn();
            if($nextPosition!==false){
                $rest=(int)$current['planned_rest_seconds'];
                $db->prepare('UPDATE workout_exercises SET current_set_position=?,rest_ends_at=DATE_ADD(NOW(),INTERVAL ? SECOND),rest_paused_seconds=NULL WHERE id=?')->execute([$nextPosition,$rest,$current['id']]);
                $db->prepare('UPDATE workout_sessions SET current_set_position=? WHERE id=?')->execute([$nextPosition,$session['id']]);
                $db->commit();return ['status'=>'set_completed','rest_seconds'=>$rest,'personal_record'=>$isRecord,'set_id'=>(int)$existing['id']];
            }
            $db->prepare("UPDATE workout_exercises SET status='completed',completed_at=NOW(),rest_ends_at=NULL,rest_paused_seconds=NULL WHERE id=?")->execute([$current['id']]);
            $stmt=$db->prepare("SELECT we.id,e.name,we.status,we.current_set_position FROM workout_exercises we JOIN exercises e ON e.id=we.exercise_id WHERE we.workout_session_id=? AND we.status IN ('active','pending') ORDER BY we.status='active' DESC,we.position LIMIT 1");$stmt->execute([$session['id']]);$next=$stmt->fetch();
            if($next){
                $db->prepare("UPDATE workout_exercises SET status='active',started_at=COALESCE(started_at,NOW()) WHERE id=?")->execute([$next['id']]);
                $db->prepare('UPDATE workout_sessions SET current_workout_exercise_id=?,current_set_position=?,rest_ends_at=NULL,rest_paused_seconds=NULL WHERE id=?')->execute([$next['id'],$next['current_set_position'],$session['id']]);
                $db->commit();return ['status'=>'exercise_completed','exercise'=>$current['name'],'best_weight'=>$best['weight_kg'],'best_reps'=>$best['repetitions'],'next_exercise'=>$next['name'],'personal_record'=>$isRecord];
            }
            $db->prepare('UPDATE workout_sessions SET current_workout_exercise_id=NULL,rest_ends_at=NULL,rest_paused_seconds=NULL WHERE id=?')->execute([$session['id']]);
            $db->commit();return ['status'=>'workout_completed','personal_record'=>$isRecord];
        }catch(Throwable $e){$db->rollBack();throw $e;}
    }

    public function addSet(): array
    {
        $state=$this->active();if(!$state||!$state['current'])throw new RuntimeException('No active exercise.');
        $db=Database::connection();$stmt=$db->prepare('SELECT COALESCE(MAX(position),0)+1 FROM exercise_sets WHERE workout_exercise_id=?');$stmt->execute([$state['current']['id']]);$position=(int)$stmt->fetchColumn();
        $weight=$state['completedWorking']?end($state['completedWorking'])['weight_kg']:$state['defaultWeight'];
        $stmt=$db->prepare("INSERT INTO exercise_sets(workout_exercise_id,position,set_type,weight_kg,rest_seconds,completed,is_extra) VALUES(?,?,'working',?,?,0,1)");$stmt->execute([$state['current']['id'],$position,$weight,$state['current']['planned_rest_seconds']]);$setId=(int)$db->lastInsertId();
        $db->prepare('UPDATE workout_exercises SET target_set_count=target_set_count+1 WHERE id=?')->execute([$state['current']['id']]);
        $db->prepare('UPDATE workout_sessions SET template_sets_changed=1 WHERE id=?')->execute([$state['session']['id']]);
        return ['status'=>'added','set_id'=>$setId,'position'=>$position,'weight_kg'=>$weight];
    }

    public function removeSet(int $setId): array
    {
        $state=$this->active();if(!$state||!$state['current'])throw new RuntimeException('No active exercise.');
        $db=Database::connection();$stmt=$db->prepare('SELECT * FROM exercise_sets WHERE id=? AND workout_exercise_id=? AND is_extra=1');$stmt->execute([$setId,$state['current']['id']]);$set=$stmt->fetch();
        if(!$set)throw new RuntimeException('This set cannot be removed.');
        if((int)$set['completed']===1)throw new RuntimeException('A completed set cannot be removed.');
        $db->beginTransaction();try{
            $db->prepare('DELETE FROM exercise_sets WHERE id=?')->execute([$setId]);
            $db->prepare('UPDATE exercise_sets SET position=position-1 WHERE workout_exercise_id=? AND position>? ORDER BY position')->execute([$state['current']['id'],$set['position']]);
            $db->prepare('UPDATE workout_exercises SET target_set_count=GREATEST(target_set_count-1,1) WHERE id=?')->execute([$state['current']['id']]);
            $db->prepare('UPDATE workout_sessions SET template_sets_changed=1 WHERE id=?')->execute([$state['session']['id']]);
            $db->commit();return ['status'=>'removed'];
        }catch(Throwable $e){$db->rollBack();throw $e;}
    }

    public function reorderExercises(array $orderedIds,?int $activateId=null): array
    {
        $state=$this->active();if(!$state)throw new RuntimeException('No active workout.');
        $remaining=array_map(fn($exercise)=>(int)$exercise['id'],$state['remainingExercises']);$orderedIds=array_values(array_unique(array_map('intval',$orderedIds)));
        $activateId??=$orderedIds[0]??null;
        $sorted=$remaining;$orderedForComparison=$orderedIds;sort($sorted);sort($orderedForComparison);
        if($sorted!==$orderedForComparison)throw new RuntimeException('The remaining exercise list is incomplete.');
        if($activateId!==null&&!in_array($activateId,$remaining,true))throw new RuntimeException('Choose a remaining exercise.');
        $db=Database::connection();$completedPositions=array_map(fn($exercise)=>(int)$exercise['position'],array_filter($state['exercises'],fn($exercise)=>in_array($exercise['status'],['completed','skipped'],true)));$position=$completedPositions?max($completedPositions)+1:1;
        $db->beginTransaction();try{
            $update=$db->prepare('UPDATE workout_exercises SET position=? WHERE id=?');foreach($orderedIds as $id)$update->execute([$position++,$id]);
            if($activateId!==null){
                $db->prepare("UPDATE workout_exercises SET status='active',started_at=COALESCE(started_at,NOW()) WHERE id=?")->execute([$activateId]);
                $stmt=$db->prepare('SELECT current_set_position FROM workout_exercises WHERE id=?');$stmt->execute([$activateId]);$setPosition=(int)$stmt->fetchColumn();
                $db->prepare('UPDATE workout_sessions SET current_workout_exercise_id=?,current_set_position=? WHERE id=?')->execute([$activateId,$setPosition,$state['session']['id']]);
            }
            $db->prepare('UPDATE workout_sessions SET template_order_changed=1 WHERE id=?')->execute([$state['session']['id']]);
            $db->commit();return ['status'=>'reordered'];
        }catch(Throwable $e){$db->rollBack();throw $e;}
    }

    public function switchExercise(int $workoutExerciseId): array
    {
        $state=$this->active();if(!$state)throw new RuntimeException('No active workout.');$target=null;foreach($state['remainingExercises'] as $exercise)if((int)$exercise['id']===$workoutExerciseId)$target=$exercise;if(!$target)throw new RuntimeException('Choose an unfinished exercise.');
        $db=Database::connection();$db->prepare("UPDATE workout_exercises SET status='active',started_at=COALESCE(started_at,NOW()) WHERE id=?")->execute([$workoutExerciseId]);$db->prepare('UPDATE workout_sessions SET current_workout_exercise_id=?,current_set_position=? WHERE id=?')->execute([$workoutExerciseId,(int)$target['current_set_position'],$state['session']['id']]);return ['status'=>'switched','exercise'=>$target['name'],'rest_remaining'=>(int)$target['rest_remaining']];
    }

    public function bodyWeight(mixed $weight): array
    {
        if(!is_numeric($weight))throw new RuntimeException('Enter a valid body weight.');$state=$this->active();if(!$state)throw new RuntimeException('No active workout.');$date=date('Y-m-d',strtotime($state['session']['performed_at']));$entry=BodyWeightEntry::record($date,(float)$weight,'workout');return ['status'=>'saved','weight_kg'=>(float)$entry['weight_kg']];
    }

    public function replaceExercise(int $exerciseId): array
    {
        $state=$this->active();if(!$state||!$state['current'])throw new RuntimeException('No active exercise.');if($state['completedSets'])throw new RuntimeException('The exercise cannot be changed after completing a set.');$candidate=null;foreach($state['replacementCandidates'] as $item)if((int)$item['id']===$exerciseId)$candidate=$item;if(!$candidate)throw new RuntimeException('Choose an available exercise from the same category.');$db=Database::connection();$policy=$this->warmupPolicy($db);$decision=['ask'=>'pending','add'=>'added','never'=>'skipped'][$policy];$db->beginTransaction();try{
            $db->prepare("DELETE FROM exercise_sets WHERE workout_exercise_id=? AND completed=0 AND set_type='warmup'")->execute([$state['current']['id']]);
            $stmt=$db->prepare('SELECT id FROM exercise_sets WHERE workout_exercise_id=? ORDER BY position');$stmt->execute([$state['current']['id']]);$sets=$stmt->fetchAll(PDO::FETCH_COLUMN);$db->prepare('UPDATE exercise_sets SET position=position+1000 WHERE workout_exercise_id=?')->execute([$state['current']['id']]);$position=1;$move=$db->prepare('UPDATE exercise_sets SET position=? WHERE id=?');foreach($sets as $setId)$move->execute([$position++,$setId]);
            if($decision==='added'){$db->prepare('UPDATE exercise_sets SET position=position+1 WHERE workout_exercise_id=? ORDER BY position DESC')->execute([$state['current']['id']]);$db->prepare("INSERT INTO exercise_sets(workout_exercise_id,position,set_type,rest_seconds,completed,is_extra) VALUES(?,1,'warmup',?,0,0)")->execute([$state['current']['id'],$candidate['recommended_rest_seconds']]);}
            $db->prepare('UPDATE workout_exercises SET exercise_id=?,planned_rest_seconds=?,warmup_decision=?,current_set_position=1,rest_ends_at=NULL,rest_paused_seconds=NULL WHERE id=?')->execute([$exerciseId,$candidate['recommended_rest_seconds'],$decision,$state['current']['id']]);$db->prepare('UPDATE exercise_sets SET weight_kg=NULL,repetitions=NULL,rest_seconds=? WHERE workout_exercise_id=? AND completed=0')->execute([$candidate['recommended_rest_seconds'],$state['current']['id']]);$db->prepare('UPDATE workout_sessions SET current_set_position=1 WHERE id=?')->execute([$state['session']['id']]);$db->commit();return ['status'=>'replaced','exercise'=>$candidate['name'],'warmup'=>$decision];
        }catch(Throwable $e){$db->rollBack();throw $e;}
    }

    public function timer(string $action): array
    {
        $state=$this->active();if(!$state||!$state['current'])return ['remaining'=>0];$id=$state['current']['id'];$db=Database::connection();if($action==='add')$db->prepare('UPDATE workout_exercises SET rest_ends_at=DATE_ADD(COALESCE(rest_ends_at,NOW()),INTERVAL 30 SECOND) WHERE id=?')->execute([$id]);elseif($action==='pause')$db->prepare('UPDATE workout_exercises SET rest_paused_seconds=GREATEST(TIMESTAMPDIFF(SECOND,NOW(),rest_ends_at),0),rest_ends_at=NULL WHERE id=?')->execute([$id]);elseif($action==='resume')$db->prepare('UPDATE workout_exercises SET rest_ends_at=DATE_ADD(NOW(),INTERVAL COALESCE(rest_paused_seconds,0) SECOND),rest_paused_seconds=NULL WHERE id=?')->execute([$id]);else $db->prepare('UPDATE workout_exercises SET rest_ends_at=NULL,rest_paused_seconds=NULL WHERE id=?')->execute([$id]);return ['remaining'=>$this->active()['remaining']];
    }

    public function finishEarly(): array
    {
        $state=$this->active();if(!$state)throw new RuntimeException('No active workout.');$db=Database::connection();$id=(int)$state['session']['id'];$db->beginTransaction();try{
            $db->prepare("UPDATE workout_exercises we SET status=CASE WHEN EXISTS(SELECT 1 FROM exercise_sets es WHERE es.workout_exercise_id=we.id AND es.completed=1 AND es.set_type='working') THEN 'completed' ELSE 'skipped' END,completed_at=NOW(),rest_ends_at=NULL,rest_paused_seconds=NULL WHERE we.workout_session_id=? AND we.status IN ('active','pending')")->execute([$id]);
            $skipped=(int)$db->query("SELECT COUNT(*) FROM workout_exercises WHERE workout_session_id={$id} AND status='skipped'")->fetchColumn();
            $db->prepare('UPDATE workout_sessions SET current_workout_exercise_id=NULL,rest_ends_at=NULL,rest_paused_seconds=NULL WHERE id=?')->execute([$id]);$db->commit();return ['status'=>'ready_to_finish','skipped'=>$skipped];
        }catch(Throwable $e){$db->rollBack();throw $e;}
    }

    public function finish(?string $notes=null,bool $updateOrder=false,bool $updateSets=false): int
    {
        $state=$this->active();if(!$state)throw new RuntimeException('No active workout.');$db=Database::connection();$id=(int)$state['session']['id'];$templateId=(int)($state['session']['workout_template_id']??0);
        if($templateId&&($updateOrder||$updateSets))$this->updateTemplate($state,$updateOrder,$updateSets);
        $db->prepare('UPDATE workout_exercises SET rest_ends_at=NULL,rest_paused_seconds=NULL WHERE workout_session_id=?')->execute([$id]);$db->prepare("UPDATE workout_sessions SET status='completed',notes=COALESCE(NULLIF(?,''),notes),completed_at=NOW(),current_workout_exercise_id=NULL,rest_ends_at=NULL WHERE id=?")->execute([trim((string)$notes),$id]);return $id;
    }

    public function abandon(): void {$state=$this->active();if($state)Database::connection()->prepare("UPDATE workout_sessions SET status='abandoned',completed_at=NOW(),current_workout_exercise_id=NULL,rest_ends_at=NULL WHERE id=?")->execute([$state['session']['id']]);}
    public function cancel(): void {$state=$this->active();if($state)Database::connection()->prepare("DELETE FROM workout_sessions WHERE id=? AND status='in_progress'")->execute([$state['session']['id']]);}

    public function summary(): array
    {
        $state=$this->active();if(!$state)throw new RuntimeException('No active workout.');$stmt=Database::connection()->prepare("SELECT e.name,we.status,MAX(es.weight_kg) best_weight,MAX(CASE WHEN es.weight_kg=(SELECT MAX(es2.weight_kg) FROM exercise_sets es2 WHERE es2.workout_exercise_id=we.id AND es2.completed=1 AND es2.set_type='working') THEN es.repetitions END) best_reps,COUNT(es.id) working_sets,MAX(es.is_personal_record) personal_record,SUM(COALESCE((SELECT SUM(ss.weight_kg*ss.repetitions) FROM exercise_set_segments ss WHERE ss.exercise_set_id=es.id),es.weight_kg*es.repetitions,0)) total_volume FROM workout_exercises we JOIN exercises e ON e.id=we.exercise_id LEFT JOIN exercise_sets es ON es.workout_exercise_id=we.id AND es.completed=1 AND es.set_type='working' WHERE we.workout_session_id=? GROUP BY we.id,e.name,we.status ORDER BY we.position");$stmt->execute([$state['session']['id']]);$state['summary']=$stmt->fetchAll();$state['skippedCount']=count(array_filter($state['summary'],fn($row)=>$row['status']==='skipped'));return $state;
    }

    private function parseSegments(array $data): array
    {
        $raw=$data['segments']??null;$segments=is_string($raw)?json_decode($raw,true):null;
        if(!is_array($segments)||!$segments)$segments=[['weight_kg'=>$data['weight_kg']??null,'repetitions'=>$data['repetitions']??null]];
        if(count($segments)>8)throw new RuntimeException('A drop set can contain up to 8 weight changes.');
        $valid=[];foreach($segments as $segment){$weight=$segment['weight_kg']??null;$reps=$segment['repetitions']??null;if(!is_numeric($weight)||!is_numeric($reps)||(float)$weight<0||(int)$reps<0)throw new RuntimeException('Enter a valid weight and repetition count for every segment.');$valid[]=['weight_kg'=>(float)$weight,'repetitions'=>(int)$reps];}return $valid;
    }

    private function personalRecordFlags(int $exerciseId,int $sessionId,array $segments): array
    {
        $stmt=Database::connection()->prepare("SELECT es.weight_kg,es.repetitions FROM exercise_sets es JOIN workout_exercises we ON we.id=es.workout_exercise_id JOIN workout_sessions ws ON ws.id=we.workout_session_id WHERE we.exercise_id=? AND es.set_type='working' AND es.completed=1 AND (ws.status='completed' OR ws.id=?) ORDER BY es.weight_kg DESC,es.repetitions DESC LIMIT 1");$stmt->execute([$exerciseId,$sessionId]);$best=$stmt->fetch();return array_map(fn($segment)=>!$best||$this->comparePerformance($segment,$best)>0,$segments);
    }
    private function comparePerformance(array $a,array $b): int {$weight=(float)$a['weight_kg']<=>(float)$b['weight_kg'];return $weight?:((int)$a['repetitions']<=>(int)$b['repetitions']);}
    private function warmupPolicy(PDO $db): string {$stmt=$db->query("SELECT setting_value FROM settings WHERE setting_key='warmup_default_behavior'");$value=$stmt->fetchColumn()?:'ask';return in_array($value,['ask','add','never'],true)?$value:'ask';}

    private function updateTemplate(array $state,bool $updateOrder,bool $updateSets): void
    {
        $db=Database::connection();$templateId=(int)$state['session']['workout_template_id'];
        if($updateOrder){$rows=$db->prepare('SELECT id,exercise_id FROM workout_template_exercises WHERE workout_template_id=? ORDER BY position');$rows->execute([$templateId]);$templateRows=$rows->fetchAll();$byExercise=[];foreach($templateRows as $row)$byExercise[(int)$row['exercise_id']]=$row;$db->prepare('UPDATE workout_template_exercises SET position=position+1000 WHERE workout_template_id=?')->execute([$templateId]);$position=1;$used=[];$update=$db->prepare('UPDATE workout_template_exercises SET position=? WHERE id=?');foreach($state['exercises'] as $exercise)if(isset($byExercise[(int)$exercise['exercise_id']])){$row=$byExercise[(int)$exercise['exercise_id']];$update->execute([$position++,$row['id']]);$used[(int)$row['id']]=true;}foreach($templateRows as $row)if(!isset($used[(int)$row['id']]))$update->execute([$position++,$row['id']]);}
        if($updateSets){$update=$db->prepare('UPDATE workout_template_exercises SET set_count=? WHERE workout_template_id=? AND exercise_id=?');foreach($state['exercises'] as $exercise)$update->execute([(int)$exercise['target_set_count'],$templateId,$exercise['exercise_id']]);}
    }
}
