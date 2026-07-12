<?php

namespace App\Models;

use App\Core\Database;

final class Measurement
{
    public static function all(): array
    {
        return Database::connection()->query('SELECT * FROM measurements ORDER BY measured_on DESC')->fetchAll();
    }

    public static function forMonth(string $month): ?array
    {
        $stmt=Database::connection()->prepare('SELECT * FROM measurements WHERE measurement_month=?');$stmt->execute([$month]);return $stmt->fetch()?:null;
    }

    public static function previousBefore(string $month): ?array
    {
        $stmt=Database::connection()->prepare('SELECT * FROM measurements WHERE measurement_month<? ORDER BY measurement_month DESC LIMIT 1');$stmt->execute([$month]);return $stmt->fetch()?:null;
    }

    public static function pageData(): array
    {
        $measurements=self::all();$month=date('Y-m');$current=self::forMonth($month);$previous=self::previousBefore($month);$last=$measurements[0]??null;
        $weightEntry=BodyWeightEntry::today()??BodyWeightEntry::latest();$defaultWeight=$weightEntry?(float)$weightEntry['weight_kg']:null;
        $fields=['weight_kg'=>'Weight','waist_cm'=>'Waist','chest_cm'=>'Chest','arm_cm'=>'Arm','thigh_cm'=>'Thigh','calf_cm'=>'Calf','neck_cm'=>'Neck'];$series=[];
        foreach($fields as $field=>$label){$series[$field]=['label'=>$label,'unit'=>$field==='weight_kg'?'kg':'cm','rows'=>[]];if($field==='weight_kg'){$series[$field]['rows']=array_map(fn($entry)=>['label'=>date('M j, Y',strtotime($entry['label'])),'value'=>(float)$entry['value']],BodyWeightEntry::all());continue;}foreach(array_reverse($measurements) as $measurement)if($measurement[$field]!==null)$series[$field]['rows'][]=['label'=>date('M Y',strtotime($measurement['measured_on'])),'value'=>(float)$measurement[$field]];}
        return compact('measurements','current','previous','last','series','defaultWeight');
    }

    public static function create(array $data): void
    {
        $fields = ['measured_on','measurement_month','weight_kg','waist_cm','chest_cm','arm_cm','thigh_cm','calf_cm','neck_cm','notes'];
        $values = [];
        $data['measurement_month']=substr((string)$data['measured_on'],0,7);
        foreach ($fields as $field) $values[] = trim((string)($data[$field] ?? '')) ?: null;
        $stmt = Database::connection()->prepare('INSERT INTO measurements('.implode(',',$fields).') VALUES('.implode(',',array_fill(0,count($fields),'?')).') ON DUPLICATE KEY UPDATE measured_on=VALUES(measured_on),weight_kg=VALUES(weight_kg),waist_cm=VALUES(waist_cm),chest_cm=VALUES(chest_cm),arm_cm=VALUES(arm_cm),thigh_cm=VALUES(thigh_cm),calf_cm=VALUES(calf_cm),neck_cm=VALUES(neck_cm),notes=VALUES(notes)');
        $stmt->execute($values);
        if(is_numeric($data['weight_kg']??null))BodyWeightEntry::record((string)$data['measured_on'],(float)$data['weight_kg'],'measurement');
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM measurements WHERE id=?');
        $stmt->execute([$id]);
    }
}
