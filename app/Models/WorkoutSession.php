<?php

namespace App\Models;

use App\Core\Database;
use PDO;
use Throwable;

final class WorkoutSession
{
    public static function history(array $filters=[]): array
    {
        $db=Database::connection();$where=["ws.status IN ('completed','abandoned')"];$params=[];
        if(($filters['search']??'')!==''){$where[]='(ws.session_type LIKE ? OR EXISTS(SELECT 1 FROM workout_exercises sw JOIN exercises se ON se.id=sw.exercise_id WHERE sw.workout_session_id=ws.id AND se.name LIKE ?))';$term='%'.$filters['search'].'%';$params[]=$term;$params[]=$term;}
        if(($filters['plan']??'all')!=='all'){$where[]='ws.session_type=?';$params[]=$filters['plan'];}
        $period=$filters['period']??'all';if($period==='month')$where[]='ws.performed_at>=DATE_FORMAT(CURRENT_DATE,\'%Y-%m-01\')';elseif($period==='3months')$where[]='ws.performed_at>=CURRENT_DATE-INTERVAL 3 MONTH';elseif($period==='year')$where[]='YEAR(ws.performed_at)=YEAR(CURRENT_DATE)';
        $sql="SELECT ws.*,COUNT(DISTINCT we.id) exercise_count,COUNT(es.id) set_count,SUM(es.set_type='working' AND es.completed=1) working_set_count,SUM(es.is_personal_record=1) personal_record_count,COUNT(DISTINCT CASE WHEN we.status='skipped' THEN we.id END) skipped_exercise_count,GROUP_CONCAT(DISTINCT e.name ORDER BY we.position SEPARATOR ' · ') exercise_names FROM workout_sessions ws LEFT JOIN workout_exercises we ON we.workout_session_id=ws.id LEFT JOIN exercises e ON e.id=we.exercise_id LEFT JOIN exercise_sets es ON es.workout_exercise_id=we.id WHERE ".implode(' AND ',$where).' GROUP BY ws.id ORDER BY ws.performed_at DESC';
        $stmt=$db->prepare($sql);$stmt->execute($params);return $stmt->fetchAll();
    }

    public static function historySummary(): array
    {
        $db=Database::connection();$summary=$db->query("SELECT COUNT(DISTINCT ws.id) workouts_month,COUNT(CASE WHEN es.set_type='working' AND es.completed=1 THEN 1 END) working_sets_month,MAX(ws.performed_at) latest_workout FROM workout_sessions ws LEFT JOIN workout_exercises we ON we.workout_session_id=ws.id LEFT JOIN exercise_sets es ON es.workout_exercise_id=we.id WHERE ws.status='completed' AND ws.performed_at>=DATE_FORMAT(CURRENT_DATE,'%Y-%m-01')")->fetch();
        $summary['latest_workout']=$db->query("SELECT MAX(performed_at) FROM workout_sessions WHERE status='completed'")->fetchColumn()?:null;
        $summary['most_trained']=$db->query("SELECT session_type FROM workout_sessions WHERE status='completed' GROUP BY session_type ORDER BY COUNT(*) DESC,MAX(performed_at) DESC LIMIT 1")->fetchColumn()?:null;return $summary;
    }

    public static function planNames(): array
    {
        return Database::connection()->query("SELECT DISTINCT session_type FROM workout_sessions WHERE status IN ('completed','abandoned') UNION SELECT session_type FROM workout_templates WHERE is_active=1 ORDER BY session_type")->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function recent(int $limit = 20): array
    {
        $stmt = Database::connection()->prepare("SELECT ws.*,COUNT(DISTINCT we.id) exercise_count,COUNT(es.id) set_count,COUNT(CASE WHEN es.set_type='working' THEN 1 END) working_set_count FROM workout_sessions ws LEFT JOIN workout_exercises we ON we.workout_session_id=ws.id LEFT JOIN exercise_sets es ON es.workout_exercise_id=we.id WHERE ws.status='completed' GROUP BY ws.id ORDER BY ws.performed_at DESC LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM workout_sessions WHERE id=?'); $stmt->execute([$id]);
        $session = $stmt->fetch();
        if (!$session) return null;
        $stmt = $db->prepare("SELECT we.id workout_exercise_id,we.exercise_id,we.notes exercise_notes,we.status exercise_status,COALESCE(we.load_semantics,e.load_semantics) load_semantics,e.name,e.recommended_rest_seconds,es.* FROM workout_exercises we JOIN exercises e ON e.id=we.exercise_id LEFT JOIN exercise_sets es ON es.workout_exercise_id=we.id WHERE we.workout_session_id=? ORDER BY we.position,es.position");
        $stmt->execute([$id]);
        $session['rows'] = $stmt->fetchAll();
        $segments=$db->prepare('SELECT ss.* FROM exercise_set_segments ss JOIN exercise_sets es ON es.id=ss.exercise_set_id JOIN workout_exercises we ON we.id=es.workout_exercise_id WHERE we.workout_session_id=? ORDER BY ss.exercise_set_id,ss.position');$segments->execute([$id]);$bySet=[];foreach($segments->fetchAll() as $segment)$bySet[(int)$segment['exercise_set_id']][]=$segment;foreach($session['rows'] as &$row)$row['segments']=$row['id']?($bySet[(int)$row['id']]??[]):[];unset($row);
        return $session;
    }

    public static function create(array $data): int
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $templateId=ctype_digit((string)($data['workout_template_id']??''))?(int)$data['workout_template_id']:null;
            $stmt = $db->prepare('INSERT INTO workout_sessions(workout_template_id,performed_at,session_type,body_weight_kg,notes) VALUES(?,?,?,?,?)');
            $stmt->execute([$templateId,$data['performed_at'],trim($data['session_type']),($data['body_weight_kg']??'')!==''?$data['body_weight_kg']:null,trim($data['notes']??'')?:null]);
            $sessionId = (int) $db->lastInsertId();
            self::saveExercises($db,$sessionId,$data['exercises']??[],$templateId);
            $db->commit();
            return $sessionId;
        } catch (Throwable $e) {
            $db->rollBack(); throw $e;
        }
    }

    public static function update(int $id, array $data): void
    {
        $db=Database::connection();$db->beginTransaction();
        try {
            $templateId=ctype_digit((string)($data['workout_template_id']??''))?(int)$data['workout_template_id']:null;
            $stmt=$db->prepare('UPDATE workout_sessions SET workout_template_id=?,performed_at=?,session_type=?,body_weight_kg=?,notes=?,last_edited_at=NOW() WHERE id=?');
            $stmt->execute([$templateId,$data['performed_at'],trim($data['session_type']),($data['body_weight_kg']??'')!==''?$data['body_weight_kg']:null,trim($data['notes']??'')?:null,$id]);
            $stmt=$db->prepare('DELETE FROM workout_exercises WHERE workout_session_id=?');$stmt->execute([$id]);
            self::saveExercises($db,$id,$data['exercises']??[],$templateId);$db->commit();
        } catch(Throwable $e){$db->rollBack();throw $e;}
    }

    public static function forEdit(int $id): ?array
    {
        $session=self::find($id);if(!$session)return null;$exercises=[];
        foreach($session['rows'] as $row){$key=$row['workout_exercise_id'];if(!isset($exercises[$key]))$exercises[$key]=['exercise_id'=>$row['exercise_id'],'notes'=>$row['exercise_notes'],'status'=>$row['exercise_status'],'load_semantics'=>$row['load_semantics'],'original'=>true,'sets'=>[]];if($row['id'])$exercises[$key]['sets'][]=['set_type'=>$row['set_type'],'weight_kg'=>$row['weight_kg'],'repetitions'=>$row['repetitions'],'rest_seconds'=>$row['rest_seconds'],'notes'=>$row['notes'],'segments'=>$row['segments']??[]];}
        $session['exercises']=array_values($exercises);return $session;
    }

    private static function saveExercises(PDO $db,int $sessionId,array $exercises,?int $templateId=null): void
    {
        $weStmt=$db->prepare("INSERT INTO workout_exercises(workout_session_id,exercise_id,position,notes,load_semantics,status,completed_at) VALUES(?,?,?,?,?,?,CASE WHEN ?='completed' THEN NOW() ELSE NULL END)");
        $setStmt=$db->prepare('INSERT INTO exercise_sets(workout_exercise_id,position,set_type,weight_kg,repetitions,rest_seconds,notes,completed) VALUES(?,?,?,?,?,?,?,?)');
        $segmentStmt=$db->prepare('INSERT INTO exercise_set_segments(exercise_set_id,position,weight_kg,repetitions,is_personal_record) VALUES(?,?,?,?,0)');
        foreach($exercises as $exercisePosition=>$exercise){
            if(empty($exercise['exercise_id']))continue;
            $exerciseId=(int)$exercise['exercise_id'];
            $status=($exercise['status']??'completed')==='skipped'?'skipped':'completed';
            $semantics=in_array($exercise['load_semantics']??'',['total','per_dumbbell','machine_stack','added_plates'],true)?$exercise['load_semantics']:null;
            $weStmt->execute([$sessionId,$exerciseId,$exercisePosition+1,trim($exercise['notes']??'')?:null,$semantics,$status,$status]);
            $weId=(int)$db->lastInsertId();
            if($status!=='skipped'){
                foreach(($exercise['sets']??[]) as $setPosition=>$set){
                    $segments=[];
                    foreach(($set['segments']??[]) as $segment){
                        if(($segment['weight_kg']??'')!==''||($segment['repetitions']??'')!=='')$segments[]=['weight_kg'=>$segment['weight_kg'],'repetitions'=>$segment['repetitions']];
                    }
                    if(!$segments&&(($set['weight_kg']??'')!==''||($set['repetitions']??'')!==''))$segments[]=['weight_kg'=>$set['weight_kg'],'repetitions'=>$set['repetitions']];
                    if(!$segments)continue;
                    $type=in_array($set['set_type']??'',['warmup','ramp','working'],true)?$set['set_type']:'working';
                    $best=$segments[0];
                    foreach($segments as $segment){
                        if((float)$segment['weight_kg']>(float)$best['weight_kg']||((float)$segment['weight_kg']===(float)$best['weight_kg']&&(int)$segment['repetitions']>(int)$best['repetitions']))$best=$segment;
                    }
                    $rest=($set['rest_seconds']??'')!==''?$set['rest_seconds']:null;
                    $setStmt->execute([$weId,$setPosition+1,$type,$best['weight_kg'],$best['repetitions'],$rest,trim($set['notes']??'')?:null,1]);
                    $setId=(int)$db->lastInsertId();
                    foreach($segments as $segmentPosition=>$segment)$segmentStmt->execute([$setId,$segmentPosition+1,$segment['weight_kg'],$segment['repetitions']]);
                }
            }
            if($templateId&&isset($exercise['add_to_template']))self::addExerciseToTemplate($db,$templateId,$exerciseId,$exercise);
        }
    }

    private static function addExerciseToTemplate(PDO $db,int $templateId,int $exerciseId,array $exercise): void
    {
        $exists=$db->prepare('SELECT 1 FROM workout_template_exercises WHERE workout_template_id=? AND exercise_id=?');$exists->execute([$templateId,$exerciseId]);if($exists->fetchColumn())return;$position=(int)$db->query('SELECT COALESCE(MAX(position),0)+1 FROM workout_template_exercises WHERE workout_template_id='.(int)$templateId)->fetchColumn();$working=array_values(array_filter($exercise['sets']??[],fn($set)=>($set['set_type']??'working')==='working'));$reps=[];foreach($working as $set)foreach(($set['segments']??[])?:[$set] as $segment)if(($segment['repetitions']??'')!=='')$reps[]=(int)$segment['repetitions'];$rest=(int)($working[0]['rest_seconds']??90);$stmt=$db->prepare('INSERT INTO workout_template_exercises(workout_template_id,exercise_id,position,set_count,repetitions_min,repetitions_max,rest_seconds_min,rest_seconds) VALUES(?,?,?,?,?,?,?,?)');$stmt->execute([$templateId,$exerciseId,$position,max(1,count($working)),$reps?min($reps):null,$reps?max($reps):null,$rest,$rest]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM workout_sessions WHERE id=?');
        $stmt->execute([$id]);
    }
}
