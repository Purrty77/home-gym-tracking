<?php
namespace App\Core;
final class Validator
{
    public static function workout(array $data): array
    {
        $errors=[];if(empty($data['performed_at'])||!strtotime($data['performed_at']))$errors[]='The workout date is invalid.';if(trim((string)($data['session_type']??''))==='')$errors[]='The workout type is required.';if(($data['body_weight_kg']??'')!==''&&!self::number($data['body_weight_kg'],20,400))$errors[]='Body weight must be between 20 and 400 kg.';$validExercises=0;$validSets=0;
        foreach(($data['exercises']??[]) as $exercise){if(empty($exercise['exercise_id']))continue;$validExercises++;foreach(($exercise['sets']??[]) as $set){if(($set['weight_kg']??'')===''&&($set['repetitions']??'')==='')continue;$validSets++;if(($set['weight_kg']??'')!==''&&!self::number($set['weight_kg'],0,2000))$errors[]='A weight value is invalid.';if(($set['repetitions']??'')!==''&&!self::integer($set['repetitions'],0,1000))$errors[]='A repetition value is invalid.';if(($set['rest_seconds']??'')!==''&&!self::integer($set['rest_seconds'],0,3600))$errors[]='A rest time is invalid.';}}
        if($validExercises===0)$errors[]='Add at least one exercise.';if($validSets===0)$errors[]='Add at least one completed set.';return array_values(array_unique($errors));
    }
    public static function exercise(array $data): array { $errors=[];if(trim((string)($data['name']??''))==='')$errors[]='The name is required.';if(empty($data['muscle_group_id']))$errors[]='The muscle group is required.';if(!self::integer($data['recommended_rest_seconds']??'',0,3600))$errors[]='The recommended rest time is invalid.';return $errors; }
    public static function measurement(array $data): array { if(empty($data['measured_on'])||!strtotime($data['measured_on']))return ['The date is invalid.'];$errors=[];foreach(['weight_kg','waist_cm','chest_cm','arm_cm','thigh_cm','calf_cm','neck_cm'] as $field){if(($data[$field]??'')==='')continue;if(!self::number($data[$field],0,500))$errors[]='A measurement value is invalid.';}return array_values(array_unique($errors)); }
    private static function number(mixed $value,float $min,float $max): bool { return is_numeric($value)&&(float)$value>=$min&&(float)$value<=$max; }
    private static function integer(mixed $value,int $min,int $max): bool { return filter_var($value,FILTER_VALIDATE_INT)!==false&&(int)$value>=$min&&(int)$value<=$max; }
}
