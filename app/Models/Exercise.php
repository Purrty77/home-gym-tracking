<?php

namespace App\Models;

use App\Core\Database;

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
        $sql="SELECT e.*,mg.name muscle_group,mg.sort_order muscle_sort,eq.name equipment,EXISTS(SELECT 1 FROM workout_template_exercises ip JOIN workout_templates it ON it.id=ip.workout_template_id AND it.is_active=1 WHERE ip.exercise_id=e.id) in_program,(SELECT MAX(t.set_count) FROM workout_template_exercises t JOIN workout_templates wt ON wt.id=t.workout_template_id AND wt.is_active=1 WHERE t.exercise_id=e.id) target_sets,(SELECT MIN(t.repetitions_min) FROM workout_template_exercises t JOIN workout_templates wt ON wt.id=t.workout_template_id AND wt.is_active=1 WHERE t.exercise_id=e.id) target_reps_min,(SELECT MAX(t.repetitions_max) FROM workout_template_exercises t JOIN workout_templates wt ON wt.id=t.workout_template_id AND wt.is_active=1 WHERE t.exercise_id=e.id) target_reps_max,(SELECT CASE WHEN e.load_semantics='per_dumbbell' AND COALESCE(we.load_semantics,e.load_semantics)='total' THEN es.weight_kg/2 ELSE es.weight_kg END FROM exercise_sets es JOIN workout_exercises we ON we.id=es.workout_exercise_id JOIN workout_sessions ws ON ws.id=we.workout_session_id WHERE we.exercise_id=e.id AND we.status='completed' AND ws.status='completed' AND es.completed=1 AND es.set_type='working' ORDER BY ws.performed_at DESC,es.weight_kg DESC,es.repetitions DESC LIMIT 1) last_weight,(SELECT es.repetitions FROM exercise_sets es JOIN workout_exercises we ON we.id=es.workout_exercise_id JOIN workout_sessions ws ON ws.id=we.workout_session_id WHERE we.exercise_id=e.id AND we.status='completed' AND ws.status='completed' AND es.completed=1 AND es.set_type='working' ORDER BY ws.performed_at DESC,es.weight_kg DESC,es.repetitions DESC LIMIT 1) last_reps FROM exercises e JOIN muscle_groups mg ON mg.id=e.muscle_group_id LEFT JOIN equipment eq ON eq.id=e.equipment_id WHERE ".implode(' AND ',$where).' ORDER BY mg.sort_order,e.name';
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
        $sql = "SELECT ws.id session_id,ws.performed_at,es.position,es.set_type,CASE WHEN e.load_semantics='per_dumbbell' AND COALESCE(we.load_semantics,e.load_semantics)='total' THEN es.weight_kg/2 ELSE es.weight_kg END weight_kg,es.repetitions,es.rest_seconds,es.notes,es.completed FROM exercise_sets es JOIN workout_exercises we ON we.id=es.workout_exercise_id JOIN exercises e ON e.id=we.exercise_id JOIN workout_sessions ws ON ws.id=we.workout_session_id WHERE we.exercise_id=? AND we.status='completed' AND ws.status='completed'";
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
        $stmt = Database::connection()->prepare('INSERT INTO exercises(muscle_group_id,equipment_id,name,variant,notes,recommended_rest_seconds) VALUES(?,?,?,?,?,?)');
        $stmt->execute([(int)$data['muscle_group_id'], $data['equipment_id'] !== '' ? (int)$data['equipment_id'] : null, trim($data['name']), trim($data['variant'] ?? '') ?: null, trim($data['notes'] ?? '') ?: null, max(0,(int)$data['recommended_rest_seconds'])]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare('UPDATE exercises SET muscle_group_id=?,equipment_id=?,name=?,variant=?,notes=?,recommended_rest_seconds=? WHERE id=?');
        $stmt->execute([(int)$data['muscle_group_id'], $data['equipment_id'] !== '' ? (int)$data['equipment_id'] : null, trim($data['name']), trim($data['variant'] ?? '') ?: null, trim($data['notes'] ?? '') ?: null, max(0,(int)$data['recommended_rest_seconds']), $id]);
    }

    public static function deactivate(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE exercises SET is_active=0 WHERE id=?');
        $stmt->execute([$id]);
    }
}
