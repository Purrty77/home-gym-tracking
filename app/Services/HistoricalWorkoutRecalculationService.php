<?php
namespace App\Services;

use App\Core\Database;
use Throwable;

final class HistoricalWorkoutRecalculationService
{
    public function recalculate(int $editedSessionId): void
    {
        $db=Database::connection();$db->beginTransaction();
        try{
            $db->exec("INSERT INTO exercise_set_segments(exercise_set_id,position,weight_kg,repetitions,is_personal_record) SELECT es.id,1,es.weight_kg,es.repetitions,0 FROM exercise_sets es WHERE es.completed=1 AND es.weight_kg IS NOT NULL AND es.repetitions IS NOT NULL AND NOT EXISTS(SELECT 1 FROM exercise_set_segments ss WHERE ss.exercise_set_id=es.id)");
            $db->exec('UPDATE exercise_sets SET is_personal_record=0');$db->exec('UPDATE exercise_set_segments SET is_personal_record=0');
            $rows=$db->query("SELECT ss.id segment_id,es.id set_id,we.exercise_id,CASE WHEN eq.name='Dumbbells' AND COALESCE(we.load_semantics,e.load_semantics)='total' THEN ss.weight_kg/2 ELSE ss.weight_kg END weight_kg,ss.repetitions FROM exercise_set_segments ss JOIN exercise_sets es ON es.id=ss.exercise_set_id JOIN workout_exercises we ON we.id=es.workout_exercise_id JOIN exercises e ON e.id=we.exercise_id LEFT JOIN equipment eq ON eq.id=e.equipment_id JOIN workout_sessions ws ON ws.id=we.workout_session_id WHERE ws.status='completed' AND we.status='completed' AND es.completed=1 AND es.set_type='working' ORDER BY ws.performed_at,ws.id,we.position,es.position,ss.position")->fetchAll();
            $best=[];$recordSets=[];$mark=$db->prepare('UPDATE exercise_set_segments SET is_personal_record=1 WHERE id=?');
            foreach($rows as $row){$exercise=(int)$row['exercise_id'];$candidate=['weight'=>(float)$row['weight_kg'],'reps'=>(int)$row['repetitions']];if(!isset($best[$exercise])||$this->compare($candidate,$best[$exercise])>0){$best[$exercise]=$candidate;$mark->execute([$row['segment_id']]);$recordSets[(int)$row['set_id']]=true;}}
            if($recordSets){$ids=implode(',',array_map('intval',array_keys($recordSets)));$db->exec("UPDATE exercise_sets SET is_personal_record=1 WHERE id IN ({$ids})");}
            $db->commit();
        }catch(Throwable $e){$db->rollBack();throw $e;}
        (new AchievementService())->evaluateWorkout($editedSessionId);
    }

    private function compare(array $a,array $b): int
    {
        $weight=$a['weight']<=>$b['weight'];return $weight?:($a['reps']<=>$b['reps']);
    }
}
