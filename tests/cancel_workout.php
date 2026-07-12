<?php
declare(strict_types=1);

use App\Core\Database;
use App\Models\WorkoutTemplate;
use App\Services\ActiveWorkoutService;
use Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';
Dotenv::createImmutable(dirname(__DIR__))->load();

$service=new ActiveWorkoutService();
$template=WorkoutTemplate::forDay(7)??throw new RuntimeException('Workout plan is missing.');
$db=Database::connection();
$db->beginTransaction();
try{
    $stmt=$db->prepare("INSERT INTO workout_sessions(workout_template_id,performed_at,session_type,status,started_at) VALUES(?,NOW(),'Cancellation Test','in_progress',NOW())");
    $stmt->execute([$template['id']]);
    $sessionId=(int)$db->lastInsertId();
    $service->cancel();
    $stmt=$db->prepare('SELECT COUNT(*) FROM workout_sessions WHERE id=?');
    $stmt->execute([$sessionId]);
    if((int)$stmt->fetchColumn()!==0)throw new RuntimeException('Cancelled workout was not deleted.');
    echo "✓ Workout cancellation: the active session is deleted\n";
}finally{
    $db->rollBack();
}
