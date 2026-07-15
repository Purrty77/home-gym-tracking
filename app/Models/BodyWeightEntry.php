<?php
namespace App\Models;

use App\Core\Database;

final class BodyWeightEntry
{
    public static function today(): ?array { return self::forDate(date('Y-m-d')); }
    public static function forDate(string $date): ?array { $stmt=Database::connection()->prepare('SELECT * FROM body_weight_entries WHERE recorded_on=?');$stmt->execute([$date]);return $stmt->fetch()?:null; }
    public static function latest(): ?array { return Database::connection()->query('SELECT * FROM body_weight_entries ORDER BY recorded_on DESC,id DESC LIMIT 1')->fetch()?:null; }
    public static function all(): array { return Database::connection()->query('SELECT recorded_on label,weight_kg value,source FROM body_weight_entries ORDER BY recorded_on')->fetchAll(); }
    public static function record(string $date,float $weight,string $source='dashboard'): array
    {
        if(!strtotime($date)||$weight<20||$weight>400)throw new \InvalidArgumentException('Enter a valid date and body weight.');$source=in_array($source,['dashboard','workout','measurement','migration'],true)?$source:'dashboard';$db=Database::connection();$stmt=$db->prepare('INSERT INTO body_weight_entries(recorded_on,weight_kg,source) VALUES(?,?,?) ON DUPLICATE KEY UPDATE weight_kg=VALUES(weight_kg),source=VALUES(source)');$stmt->execute([$date,$weight,$source]);$db->prepare('UPDATE workout_sessions SET body_weight_kg=? WHERE DATE(performed_at)=?')->execute([$weight,$date]);$db->prepare('UPDATE measurements SET weight_kg=? WHERE measured_on=?')->execute([$weight,$date]);return self::forDate($date);
    }

    public static function syncWorkoutEdit(string $oldDate,string $newDate,mixed $weight): void
    {
        if(is_numeric($weight))self::record($newDate,(float)$weight,'workout');else self::removeOrphanedWorkoutEntry($newDate);
        if($oldDate!==$newDate)self::removeOrphanedWorkoutEntry($oldDate);
    }

    private static function removeOrphanedWorkoutEntry(string $date): void
    {
        $db=Database::connection();$stmt=$db->prepare("SELECT COUNT(*) FROM workout_sessions WHERE DATE(performed_at)=? AND status='completed' AND body_weight_kg IS NOT NULL");$stmt->execute([$date]);if((int)$stmt->fetchColumn()>0)return;$stmt=$db->prepare("DELETE FROM body_weight_entries WHERE recorded_on=? AND source='workout'");$stmt->execute([$date]);
    }
}
