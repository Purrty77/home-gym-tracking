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
        $sql="SELECT ws.*,COUNT(DISTINCT we.id) exercise_count,COUNT(es.id) set_count,SUM(es.set_type='working' AND es.completed=1) working_set_count,SUM(es.is_personal_record=1) personal_record_count,GROUP_CONCAT(DISTINCT e.name ORDER BY we.position SEPARATOR ' · ') exercise_names FROM workout_sessions ws LEFT JOIN workout_exercises we ON we.workout_session_id=ws.id LEFT JOIN exercises e ON e.id=we.exercise_id LEFT JOIN exercise_sets es ON es.workout_exercise_id=we.id WHERE ".implode(' AND ',$where).' GROUP BY ws.id ORDER BY ws.performed_at DESC';
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
        $stmt = $db->prepare("SELECT we.id workout_exercise_id,we.exercise_id,we.notes exercise_notes,e.name,e.recommended_rest_seconds,es.* FROM workout_exercises we JOIN exercises e ON e.id=we.exercise_id LEFT JOIN exercise_sets es ON es.workout_exercise_id=we.id WHERE we.workout_session_id=? ORDER BY we.position,es.position");
        $stmt->execute([$id]);
        $session['rows'] = $stmt->fetchAll();
        return $session;
    }

    public static function create(array $data): int
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('INSERT INTO workout_sessions(performed_at,session_type,body_weight_kg,notes) VALUES(?,?,?,?)');
            $stmt->execute([$data['performed_at'], trim($data['session_type']), $data['body_weight_kg'] ?: null, trim($data['notes'] ?? '') ?: null]);
            $sessionId = (int) $db->lastInsertId();
            self::saveExercises($db, $sessionId, $data['exercises'] ?? []);
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
            $stmt=$db->prepare('UPDATE workout_sessions SET performed_at=?,session_type=?,body_weight_kg=?,notes=? WHERE id=?');
            $stmt->execute([$data['performed_at'],trim($data['session_type']),$data['body_weight_kg']?:null,trim($data['notes']??'')?:null,$id]);
            $stmt=$db->prepare('DELETE FROM workout_exercises WHERE workout_session_id=?');$stmt->execute([$id]);
            self::saveExercises($db,$id,$data['exercises']??[]);$db->commit();
        } catch(Throwable $e){$db->rollBack();throw $e;}
    }

    public static function forEdit(int $id): ?array
    {
        $session=self::find($id);if(!$session)return null;$exercises=[];
        foreach($session['rows'] as $row){$key=$row['workout_exercise_id'];if(!isset($exercises[$key]))$exercises[$key]=['exercise_id'=>$row['exercise_id'],'notes'=>$row['exercise_notes'],'sets'=>[]];if($row['id'])$exercises[$key]['sets'][]=['set_type'=>$row['set_type'],'weight_kg'=>$row['weight_kg'],'repetitions'=>$row['repetitions'],'rest_seconds'=>$row['rest_seconds'],'notes'=>$row['notes']];}
        $session['exercises']=array_values($exercises);return $session;
    }

    private static function saveExercises(PDO $db,int $sessionId,array $exercises): void
    {
        $weStmt=$db->prepare('INSERT INTO workout_exercises(workout_session_id,exercise_id,position,notes) VALUES(?,?,?,?)');
        $setStmt=$db->prepare('INSERT INTO exercise_sets(workout_exercise_id,position,set_type,weight_kg,repetitions,rest_seconds,notes,completed) VALUES(?,?,?,?,?,?,?,?)');
        foreach($exercises as $exercisePosition=>$exercise){if(empty($exercise['exercise_id']))continue;$weStmt->execute([$sessionId,(int)$exercise['exercise_id'],$exercisePosition+1,trim($exercise['notes']??'')?:null]);$weId=(int)$db->lastInsertId();foreach(($exercise['sets']??[]) as $setPosition=>$set){if(($set['weight_kg']??'')===''&&($set['repetitions']??'')==='')continue;$type=in_array($set['set_type']??'',['warmup','ramp','working'],true)?$set['set_type']:'working';$setStmt->execute([$weId,$setPosition+1,$type,$set['weight_kg']!==''?$set['weight_kg']:null,$set['repetitions']!==''?$set['repetitions']:null,$set['rest_seconds']!==''?$set['rest_seconds']:null,trim($set['notes']??'')?:null,1]);}}
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM workout_sessions WHERE id=?');
        $stmt->execute([$id]);
    }
}
