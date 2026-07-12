<?php
namespace App\Models;
use App\Core\Database;

final class WorkoutTemplate
{
    public static function all(): array
    {
        return Database::connection()->query('SELECT * FROM workout_templates WHERE is_active=1 ORDER BY id')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $db=Database::connection();$stmt=$db->prepare('SELECT * FROM workout_templates WHERE id=? AND is_active=1');$stmt->execute([$id]);$template=$stmt->fetch();if(!$template)return null;
        $stmt=$db->prepare('SELECT wte.*,e.name exercise_name FROM workout_template_exercises wte JOIN exercises e ON e.id=wte.exercise_id WHERE wte.workout_template_id=? ORDER BY wte.position');$stmt->execute([$id]);$template['exercises']=$stmt->fetchAll();return $template;
    }

    public static function forDay(int $isoDay): ?array
    {
        foreach(self::all() as $template){if(in_array((string)$isoDay,explode(',',$template['scheduled_days']),true))return self::find((int)$template['id']);}return null;
    }

    public static function asFormData(array $template): array
    {
        return array_map(function(array $exercise): array {
            $sets=[];for($i=0;$i<(int)$exercise['set_count'];$i++)$sets[]=['set_type'=>'working','weight_kg'=>'','repetitions'=>'','rest_seconds'=>$exercise['rest_seconds'],'notes'=>''];
            $target=$exercise['repetitions_min']&&$exercise['repetitions_max']?$exercise['set_count'].' × '.$exercise['repetitions_min'].'–'.$exercise['repetitions_max'].' reps':$exercise['set_count'].' sets';
            $target.=' · rest '.($exercise['rest_seconds_min']===$exercise['rest_seconds']?$exercise['rest_seconds']:$exercise['rest_seconds_min'].'–'.$exercise['rest_seconds']).' sec';
            return ['exercise_id'=>$exercise['exercise_id'],'notes'=>$exercise['notes'],'target'=>$target,'sets'=>$sets];
        },$template['exercises']);
    }
}
