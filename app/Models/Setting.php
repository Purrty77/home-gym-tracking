<?php
namespace App\Models;
use App\Core\Database;
final class Setting
{
    public static function all(): array { return Database::connection()->query('SELECT setting_key,setting_value FROM settings')->fetchAll(\PDO::FETCH_KEY_PAIR); }
    public static function save(array $values): void { $stmt=Database::connection()->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'); foreach($values as $key=>$value)$stmt->execute([$key,(string)$value]); }
}

