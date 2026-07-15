<?php

namespace App\Models;

use App\Core\Database;
use App\Services\HistoricalWorkoutRecalculationService;
use PDO;
use Throwable;

final class Exercise
{
    public static function library(array $filters=[]): array
    {
        $db=Database::connection();$where=['e.is_active=1'];$params=[];
        if(($filters['search']??'')!==''){$where[]='(e.name LIKE ? OR e.canonical_name LIKE ? OR e.target_muscle LIKE ? OR mg.name LIKE ? OR eq.name LIKE ?)';$term='%'.$filters['search'].'%';array_push($params,$term,$term,$term,$term,$term);}
        if(($filters['scope']??'all')==='program')$where[]='EXISTS(SELECT 1 FROM workout_template_exercises px JOIN workout_templates pt ON pt.id=px.workout_template_id AND pt.is_active=1 WHERE px.exercise_id=e.id)';
        if(($filters['scope']??'all')==='recent')$where[]="EXISTS(SELECT 1 FROM workout_exercises rw JOIN workout_sessions rs ON rs.id=rw.workout_session_id WHERE rw.exercise_id=e.id AND rw.status='completed' AND rs.status='completed' AND rs.performed_at>=CURRENT_DATE-INTERVAL 30 DAY)";
        if(($filters['muscle']??'')!==''){$where[]='mg.name=?';$params[]=$filters['muscle'];}
        if(($filters['equipment']??'')!==''&&ctype_digit((string)$filters['equipment'])){$where[]='e.equipment_id=?';$params[]=(int)$filters['equipment'];}
        $sql="SELECT e.*,mg.name muscle_group,mg.sort_order muscle_sort,eq.name equipment,EXISTS(SELECT 1 FROM workout_template_exercises ip JOIN workout_templates it ON it.id=ip.workout_template_id AND it.is_active=1 WHERE ip.exercise_id=e.id) in_program,(SELECT MAX(t.set_count) FROM workout_template_exercises t JOIN workout_templates wt ON wt.id=t.workout_template_id AND wt.is_active=1 WHERE t.exercise_id=e.id) target_sets,(SELECT MIN(t.repetitions_min) FROM workout_template_exercises t JOIN workout_templates wt ON wt.id=t.workout_template_id AND wt.is_active=1 WHERE t.exercise_id=e.id) target_reps_min,(SELECT MAX(t.repetitions_max) FROM workout_template_exercises t JOIN workout_templates wt ON wt.id=t.workout_template_id AND wt.is_active=1 WHERE t.exercise_id=e.id) target_reps_max,(SELECT CASE WHEN eq.name='Dumbbells' AND e.load_semantics='per_dumbbell' AND COALESCE(we.load_semantics,e.load_semantics)='total' THEN es.weight_kg/2 WHEN eq.name='Dumbbells' AND e.load_semantics='total' AND COALESCE(we.load_semantics,e.load_semantics)='per_dumbbell' THEN es.weight_kg*2 ELSE es.weight_kg END FROM exercise_sets es JOIN workout_exercises we ON we.id=es.workout_exercise_id JOIN workout_sessions ws ON ws.id=we.workout_session_id WHERE we.exercise_id=e.id AND we.status='completed' AND ws.status='completed' AND es.completed=1 AND es.set_type='working' ORDER BY ws.performed_at DESC,es.weight_kg DESC,es.repetitions DESC LIMIT 1) last_weight,(SELECT es.repetitions FROM exercise_sets es JOIN workout_exercises we ON we.id=es.workout_exercise_id JOIN workout_sessions ws ON ws.id=we.workout_session_id WHERE we.exercise_id=e.id AND we.status='completed' AND ws.status='completed' AND es.completed=1 AND es.set_type='working' ORDER BY ws.performed_at DESC,es.weight_kg DESC,es.repetitions DESC LIMIT 1) last_reps FROM exercises e JOIN muscle_groups mg ON mg.id=e.muscle_group_id LEFT JOIN equipment eq ON eq.id=e.equipment_id WHERE ".implode(' AND ',$where).' ORDER BY mg.sort_order,e.name';
        $stmt=$db->prepare($sql);$stmt->execute($params);return ['exercises'=>$stmt->fetchAll(),'muscleGroups'=>$db->query('SELECT name FROM muscle_groups ORDER BY sort_order,name')->fetchAll(\PDO::FETCH_COLUMN),'equipmentOptions'=>$db->query('SELECT id,name FROM equipment ORDER BY name')->fetchAll()];
    }

    public static function formOptions(): array
    {
        $db = Database::connection();
        return [
            'muscleGroups' => $db->query('SELECT * FROM muscle_groups ORDER BY sort_order,name')->fetchAll(),
            'equipment' => $db->query('SELECT * FROM equipment ORDER BY name')->fetchAll(),
        ];
    }

    public static function all(): array
    {
        return Database::connection()->query("SELECT e.*, mg.name muscle_group, eq.name equipment FROM exercises e JOIN muscle_groups mg ON mg.id=e.muscle_group_id LEFT JOIN equipment eq ON eq.id=e.equipment_id WHERE e.is_active=1 ORDER BY mg.sort_order,e.name")->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare("SELECT e.*, mg.name muscle_group, eq.name equipment FROM exercises e JOIN muscle_groups mg ON mg.id=e.muscle_group_id LEFT JOIN equipment eq ON eq.id=e.equipment_id WHERE e.id=?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function history(int $id, ?string $from = null, ?string $to = null): array
    {
        $sql = "SELECT ws.id session_id,ws.performed_at,es.position,es.set_type,CASE WHEN eq.name='Dumbbells' AND e.load_semantics='per_dumbbell' AND COALESCE(we.load_semantics,e.load_semantics)='total' THEN es.weight_kg/2 WHEN eq.name='Dumbbells' AND e.load_semantics='total' AND COALESCE(we.load_semantics,e.load_semantics)='per_dumbbell' THEN es.weight_kg*2 ELSE es.weight_kg END weight_kg,es.weight_kg entered_weight_kg,COALESCE(we.load_semantics,e.load_semantics) load_semantics,es.repetitions,es.rest_seconds,es.notes,es.completed FROM exercise_sets es JOIN workout_exercises we ON we.id=es.workout_exercise_id JOIN exercises e ON e.id=we.exercise_id LEFT JOIN equipment eq ON eq.id=e.equipment_id JOIN workout_sessions ws ON ws.id=we.workout_session_id WHERE we.exercise_id=? AND we.status='completed' AND ws.status='completed'";
        $params = [$id];
        if ($from) { $sql .= ' AND DATE(ws.performed_at)>=?'; $params[] = $from; }
        if ($to) { $sql .= ' AND DATE(ws.performed_at)<=?'; $params[] = $to; }
        $sql .= ' ORDER BY ws.performed_at DESC, es.position';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $db=Database::connection();$equipmentId=($data['equipment_id']??'')!==''?(int)$data['equipment_id']:null;$semantics=self::semanticsForEquipment($db,$equipmentId,$data['load_semantics']??null);
        $stmt=$db->prepare('INSERT INTO exercises(muscle_group_id,equipment_id,name,variant,notes,recommended_rest_seconds,load_semantics) VALUES(?,?,?,?,?,?,?)');
        $stmt->execute([(int)$data['muscle_group_id'],$equipmentId,trim($data['name']),trim($data['variant']??'')?:null,trim($data['notes']??'')?:null,max(0,(int)$data['recommended_rest_seconds']),$semantics]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $db=Database::connection();$current=self::find($id);if(!$current)throw new \RuntimeException('Exercise not found.');$equipmentId=($data['equipment_id']??'')!==''?(int)$data['equipment_id']:null;$semantics=self::semanticsForEquipment($db,$equipmentId,$data['load_semantics']??null);$convert=$current['equipment']==='Dumbbells'&&self::equipmentName($db,$equipmentId)==='Dumbbells'&&$current['load_semantics']!==$semantics&&($data['history_weight_action']??'keep')==='convert';
        $db->beginTransaction();
        try{
            $stmt=$db->prepare('UPDATE exercises SET muscle_group_id=?,equipment_id=?,name=?,variant=?,notes=?,recommended_rest_seconds=?,load_semantics=? WHERE id=?');
            $stmt->execute([(int)$data['muscle_group_id'],$equipmentId,trim($data['name']),trim($data['variant']??'')?:null,trim($data['notes']??'')?:null,max(0,(int)$data['recommended_rest_seconds']),$semantics,$id]);
            if($convert)self::convertDumbbellHistory($db,$id,$semantics);
            $db->commit();
        }catch(Throwable $e){$db->rollBack();throw $e;}
        if($convert){$stmt=$db->prepare("SELECT ws.id FROM workout_sessions ws JOIN workout_exercises we ON we.workout_session_id=ws.id WHERE we.exercise_id=? AND ws.status='completed' ORDER BY ws.performed_at DESC,ws.id DESC LIMIT 1");$stmt->execute([$id]);$sessionId=(int)$stmt->fetchColumn();if($sessionId)(new HistoricalWorkoutRecalculationService())->recalculate($sessionId);}
    }

    private static function semanticsForEquipment(PDO $db,?int $equipmentId,mixed $requested): string
    {
        $name=self::equipmentName($db,$equipmentId);if($name==='Dumbbells')return in_array($requested,['per_dumbbell','total'],true)?$requested:'per_dumbbell';return match($name){'Cable','Machine'=>'machine_stack','Hammer Strength'=>'added_plates',default=>'total'};
    }

    private static function equipmentName(PDO $db,?int $equipmentId): ?string
    {
        if(!$equipmentId)return null;$stmt=$db->prepare('SELECT name FROM equipment WHERE id=?');$stmt->execute([$equipmentId]);return $stmt->fetchColumn()?:null;
    }

    private static function convertDumbbellHistory(PDO $db,int $exerciseId,string $target): void
    {
        $factor=$target==='total'?2:0.5;$from=$target==='total'?'per_dumbbell':'total';
        $stmt=$db->prepare("UPDATE exercise_sets es JOIN workout_exercises we ON we.id=es.workout_exercise_id JOIN workout_sessions ws ON ws.id=we.workout_session_id SET es.weight_kg=ROUND(es.weight_kg*?,2) WHERE we.exercise_id=? AND we.load_semantics=? AND ws.status IN ('completed','abandoned') AND es.weight_kg IS NOT NULL");$stmt->execute([$factor,$exerciseId,$from]);
        $stmt=$db->prepare("UPDATE exercise_set_segments ss JOIN exercise_sets es ON es.id=ss.exercise_set_id JOIN workout_exercises we ON we.id=es.workout_exercise_id JOIN workout_sessions ws ON ws.id=we.workout_session_id SET ss.weight_kg=ROUND(ss.weight_kg*?,2) WHERE we.exercise_id=? AND we.load_semantics=? AND ws.status IN ('completed','abandoned')");$stmt->execute([$factor,$exerciseId,$from]);
        $stmt=$db->prepare("UPDATE workout_exercises we JOIN workout_sessions ws ON ws.id=we.workout_session_id SET we.load_semantics=? WHERE we.exercise_id=? AND we.load_semantics=? AND ws.status IN ('completed','abandoned')");$stmt->execute([$target,$exerciseId,$from]);
    }

    public static function deactivate(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE exercises SET is_active=0 WHERE id=?');
        $stmt->execute([$id]);
    }
}
