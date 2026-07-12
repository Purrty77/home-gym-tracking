<?php
namespace App\Services;
use App\Core\Database;
use PDO;

final class MotivationalMessageService
{
    public function current(): string
    {
        $db=Database::connection();$settings=$db->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('motivational_message_index','motivational_message_date')")->fetchAll(PDO::FETCH_KEY_PAIR);$next=max(1,min(50,(int)($settings['motivational_message_index']??1)));$date=$settings['motivational_message_date']??'';
        if($date!==date('Y-m-d')){$shown=$next;$following=$next===50?1:$next+1;$stmt=$db->prepare("INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");$stmt->execute(['motivational_message_index',(string)$following]);$stmt->execute(['motivational_message_date',date('Y-m-d')]);}else{$shown=$next===1?50:$next-1;}
        $stmt=$db->prepare('SELECT message FROM motivational_messages WHERE id=?');$stmt->execute([$shown]);return $stmt->fetchColumn()?:'Train, record, improve.';
    }
}
