<?php
namespace App\Services;
use App\Core\Database;

final class AchievementService
{
    public function evaluateWorkout(int $sessionId): array
    {
        (new AchievementCatalog())->sync();$db=Database::connection();$definitions=$db->query('SELECT * FROM achievement_definitions WHERE is_active=1')->fetchAll();$unlocked=[];$insert=$db->prepare('INSERT IGNORE INTO achievement_unlocks(achievement_id,workout_session_id,context_json) VALUES(?,?,?)');foreach($definitions as $definition){$value=$this->value($definition,$sessionId);$requirement=(float)($definition['requirement_value']??1);if($value>=$requirement){$insert->execute([$definition['id'],$sessionId,json_encode(['value'=>$value,'requirement'=>$requirement])]);if($insert->rowCount())$unlocked[]=$definition;}}return $unlocked;
    }

    public function evaluateTracking(): array
    {
        (new AchievementCatalog())->sync();$db=Database::connection();$definitions=$db->query("SELECT * FROM achievement_definitions WHERE category='Tracking' AND is_active=1")->fetchAll();$unlocked=[];$insert=$db->prepare('INSERT IGNORE INTO achievement_unlocks(achievement_id,context_json) VALUES(?,?)');foreach($definitions as $definition){$value=$this->value($definition,null);$requirement=(float)($definition['requirement_value']??1);if($value>=$requirement){$insert->execute([$definition['id'],json_encode(['value'=>$value])]);if($insert->rowCount())$unlocked[]=$definition;}}return $unlocked;
    }

    public function allWithProgress(): array
    {
        $this->reconcile();$rows=Database::connection()->query('SELECT ad.*,au.unlocked_at FROM achievement_definitions ad LEFT JOIN achievement_unlocks au ON au.achievement_id=ad.id WHERE ad.is_active=1 ORDER BY ad.sort_order')->fetchAll();foreach($rows as &$row){$row['current_value']=$this->value($row,null);$row['progress_percent']=min(100,(int)round($row['current_value']/max(1,(float)($row['requirement_value']??1))*100));}return $rows;
    }

    public function reconcile(): array
    {
        (new AchievementCatalog())->sync();$db=Database::connection();$sessionId=(int)$db->query("SELECT id FROM workout_sessions WHERE status='completed' ORDER BY performed_at DESC,id DESC LIMIT 1")->fetchColumn();$unlocked=$sessionId?$this->evaluateWorkout($sessionId):[];return array_merge($unlocked,$this->evaluateTracking());
    }

    public function unlockedForWorkout(int $sessionId): array
    {
        $stmt=Database::connection()->prepare('SELECT ad.*,au.unlocked_at FROM achievement_unlocks au JOIN achievement_definitions ad ON ad.id=au.achievement_id WHERE au.workout_session_id=? ORDER BY au.id');$stmt->execute([$sessionId]);return $stmt->fetchAll();
    }

    private function value(array $definition,?int $sessionId): float
    {
        $db=Database::connection();$type=$definition['evaluation_type'];$exercise=(int)($definition['exercise_id']??0);$template=(int)($definition['workout_template_id']??0);
        if($type==='workout_count')return (float)$db->query("SELECT COUNT(*) FROM workout_sessions WHERE status='completed'")->fetchColumn();
        if($type==='pr_count')return (float)$db->query('SELECT COUNT(*) FROM exercise_sets WHERE is_personal_record=1')->fetchColumn();
        if($type==='body_weight_count')return (float)$db->query('SELECT (SELECT COUNT(*) FROM measurements WHERE weight_kg IS NOT NULL)+(SELECT COUNT(*) FROM workout_sessions WHERE body_weight_kg IS NOT NULL)')->fetchColumn();
        if(in_array($type,['measurement_count','measurement_streak'],true))return (float)$db->query('SELECT COUNT(*) FROM measurements')->fetchColumn();
        if($type==='program_count'){$stmt=$db->prepare("SELECT COUNT(*) FROM workout_sessions WHERE status='completed' AND workout_template_id=?");$stmt->execute([$template]);return (float)$stmt->fetchColumn();}
        if($type==='exercise_count'){$stmt=$db->prepare("SELECT COUNT(*) FROM workout_exercises we JOIN workout_sessions ws ON ws.id=we.workout_session_id WHERE we.exercise_id=? AND we.status='completed' AND ws.status='completed'");$stmt->execute([$exercise]);return (float)$stmt->fetchColumn();}
        if(in_array($type,['exercise_weight','weight_reps'],true)){$stmt=$db->prepare('SELECT COALESCE(MAX(es.weight_kg),0) FROM exercise_sets es JOIN workout_exercises we ON we.id=es.workout_exercise_id WHERE we.exercise_id=? AND es.completed=1');$stmt->execute([$exercise]);return (float)$stmt->fetchColumn();}
        if($type==='exercise_reps'){$stmt=$db->prepare('SELECT COALESCE(MAX(es.repetitions),0) FROM exercise_sets es JOIN workout_exercises we ON we.id=es.workout_exercise_id WHERE we.exercise_id=? AND es.completed=1');$stmt->execute([$exercise]);return (float)$stmt->fetchColumn();}
        if($type==='exercise_complete')return (float)$db->query("SELECT COUNT(*) FROM workout_exercises WHERE status='completed'")->fetchColumn();
        if(in_array($type,['full_workout','perfect_plan'],true))return (float)$db->query("SELECT COUNT(*) FROM workout_sessions WHERE status='completed' AND workout_template_id IS NOT NULL")->fetchColumn();
        if($type==='program_complete')return $this->value(array_merge($definition,['evaluation_type'=>'program_count']),$sessionId);
        if($type==='no_abandoned')return (float)$db->query("SELECT CASE WHEN COUNT(*)>=25 AND SUM(status='abandoned')=0 THEN COUNT(*) ELSE 0 END FROM workout_sessions")->fetchColumn();
        if($type==='pr_in_workout'&&$sessionId){$stmt=$db->prepare('SELECT COUNT(*) FROM exercise_sets es JOIN workout_exercises we ON we.id=es.workout_exercise_id WHERE we.workout_session_id=? AND es.is_personal_record=1');$stmt->execute([$sessionId]);return (float)$stmt->fetchColumn();}
        if(in_array($type,['sets_top_range','sets_min_reps'],true)){$stmt=$db->prepare("SELECT COALESCE(MAX(set_total),0) FROM (SELECT COUNT(*) set_total FROM exercise_sets es JOIN workout_exercises we ON we.id=es.workout_exercise_id WHERE we.exercise_id=? AND es.set_type='working' AND es.completed=1 GROUP BY we.id) totals");$stmt->execute([$exercise]);return (float)$stmt->fetchColumn();}
        return 0;
    }
}
