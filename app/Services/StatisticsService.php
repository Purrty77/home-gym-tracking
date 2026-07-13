<?php
namespace App\Services;

use App\Core\Database;
use App\Models\WorkoutTemplate;
use DateTimeImmutable;
use PDO;

final class StatisticsService
{
    public function dashboard(): array
    {
        $db=Database::connection();
        $week=$db->query("SELECT COUNT(DISTINCT ws.id) sessions,COUNT(CASE WHEN es.set_type='working' THEN 1 END) sets FROM workout_sessions ws LEFT JOIN workout_exercises we ON we.workout_session_id=ws.id LEFT JOIN exercise_sets es ON es.workout_exercise_id=we.id WHERE ws.status='completed' AND YEARWEEK(ws.performed_at,1)=YEARWEEK(CURRENT_DATE,1)")->fetch();
        $records=$db->query("SELECT e.id,e.name,MAX(es.weight_kg) max_weight FROM exercises e JOIN workout_exercises we ON we.exercise_id=e.id JOIN exercise_sets es ON es.workout_exercise_id=we.id WHERE es.completed=1 AND es.set_type='working' AND es.weight_kg IS NOT NULL GROUP BY e.id,e.name ORDER BY max_weight DESC LIMIT 5")->fetchAll();
        $recordCount=(int)$db->query("SELECT COUNT(DISTINCT we.exercise_id) FROM workout_exercises we JOIN exercise_sets es ON es.workout_exercise_id=we.id WHERE es.completed=1 AND es.set_type='working' AND es.weight_kg IS NOT NULL")->fetchColumn();
        $weight=$db->query("SELECT recorded_on label,weight_kg value FROM body_weight_entries ORDER BY recorded_on")->fetchAll();
        $currentWeight=$weight?(float)end($weight)['value']:null;$todayWeight=null;foreach($weight as $entry)if($entry['label']===date('Y-m-d'))$todayWeight=(float)$entry['value'];$previousWeight=count($weight)>1?(float)$weight[count($weight)-2]['value']:null;$entryWeightChange=$currentWeight!==null&&$previousWeight!==null?$currentWeight-$previousWeight:null;$weightChange=null;$weeklyWeightTrend=null;$monthlyWeightTrend=null;
        if($weight){$latestMonth=substr((string)end($weight)['label'],0,7);for($index=count($weight)-2;$index>=0;$index--){if(substr((string)$weight[$index]['label'],0,7)<$latestMonth){$weightChange=$currentWeight-(float)$weight[$index]['value'];break;}}}
        $recentWeek=array_filter($weight,fn($row)=>strtotime($row['label'])>=strtotime('-7 days'));$priorWeek=array_filter($weight,fn($row)=>strtotime($row['label'])>=strtotime('-14 days')&&strtotime($row['label'])<strtotime('-7 days'));if($recentWeek&&$priorWeek)$weeklyWeightTrend=array_sum(array_column($recentWeek,'value'))/count($recentWeek)-array_sum(array_column($priorWeek,'value'))/count($priorWeek);$thisMonth=array_filter($weight,fn($row)=>substr($row['label'],0,7)===date('Y-m'));$lastMonth=array_filter($weight,fn($row)=>substr($row['label'],0,7)===date('Y-m',strtotime('first day of last month')));if($thisMonth&&$lastMonth)$monthlyWeightTrend=array_sum(array_column($thisMonth,'value'))/count($thisMonth)-array_sum(array_column($lastMonth,'value'))/count($lastMonth);

        $setting=$db->query("SELECT setting_key,setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
        $weightGoal=$setting['body_weight_goal_direction']??'unset';
        $lastMeasurement=$db->query('SELECT MAX(measured_on) FROM measurements')->fetchColumn()?:null;
        $measuredThisMonth=$lastMeasurement&&date('Y-m',strtotime($lastMeasurement))===date('Y-m');
        $due=($setting['measurement_reminder_enabled']??'1')==='1'&&!$measuredThisMonth;
        $nextReminder=$due?date('Y-m-01'):date('Y-m-d',strtotime('first day of next month'));

        $allProgress=$db->query("WITH set_ranked AS (SELECT e.id,e.name,ws.id session_id,ws.performed_at,es.weight_kg,es.repetitions,ROW_NUMBER() OVER(PARTITION BY e.id,ws.id ORDER BY es.weight_kg DESC,es.repetitions DESC,es.id DESC) set_rank FROM exercises e JOIN workout_exercises we ON we.exercise_id=e.id JOIN workout_sessions ws ON ws.id=we.workout_session_id JOIN exercise_sets es ON es.workout_exercise_id=we.id WHERE es.completed=1 AND es.set_type='working' AND es.weight_kg IS NOT NULL), performances AS (SELECT * FROM set_ranked WHERE set_rank=1), ranked AS (SELECT performances.*,ROW_NUMBER() OVER(PARTITION BY id ORDER BY performed_at DESC,session_id DESC) performance_rank,MAX(weight_kg) OVER(PARTITION BY id) all_time_max FROM performances) SELECT id,name,MAX(CASE WHEN performance_rank=1 THEN weight_kg END) latest_weight,MAX(CASE WHEN performance_rank=1 THEN repetitions END) latest_reps,MAX(CASE WHEN performance_rank=2 THEN weight_kg END) previous_weight,MAX(CASE WHEN performance_rank=2 THEN repetitions END) previous_reps,MAX(CASE WHEN performance_rank=1 THEN performed_at END) performed_at,MAX(all_time_max) all_time_max FROM ranked WHERE performance_rank<=2 GROUP BY id,name ORDER BY performed_at DESC")->fetchAll();
        $progressedThisMonth=count(array_filter($allProgress,fn(array $row):bool=>date('Y-m',strtotime($row['performed_at']))===date('Y-m')&&$row['previous_weight']!==null&&((float)$row['latest_weight']>(float)$row['previous_weight']||((float)$row['latest_weight']===(float)$row['previous_weight']&&(int)$row['latest_reps']>(int)$row['previous_reps']))));
        $progress=array_slice($allProgress,0,8);

        $heatmap=$db->query("SELECT DATE(performed_at) workout_date,COUNT(*) workout_count FROM workout_sessions WHERE status='completed' AND performed_at>=CURRENT_DATE-INTERVAL 364 DAY GROUP BY DATE(performed_at) ORDER BY workout_date")->fetchAll();
        $totalWorkouts=(int)$db->query("SELECT COUNT(*) FROM workout_sessions WHERE status='completed'")->fetchColumn();
        $monthWorkouts=(int)$db->query("SELECT COUNT(*) FROM workout_sessions WHERE status='completed' AND YEAR(performed_at)=YEAR(CURRENT_DATE) AND MONTH(performed_at)=MONTH(CURRENT_DATE)")->fetchColumn();
        $workoutDates=array_column($heatmap,'workout_date');
        ['current'=>$streak,'longest'=>$longestStreak]=$this->dailyStreaks($workoutDates);
        $todayWorkout=WorkoutTemplate::forDay((int)(new DateTimeImmutable('today'))->format('N'));
        $nextWorkout=WorkoutTemplate::forDay((int)(new DateTimeImmutable('tomorrow'))->format('N'));
        $todayCompleted=$db->query("SELECT id,session_type,performed_at,workout_template_id FROM workout_sessions WHERE status='completed' AND DATE(performed_at)=CURRENT_DATE ORDER BY completed_at DESC,id DESC LIMIT 1")->fetch()?:null;

        return compact('week','records','recordCount','weight','currentWeight','todayWeight','previousWeight','entryWeightChange','weightChange','weeklyWeightTrend','monthlyWeightTrend','weightGoal','due','lastMeasurement','nextReminder','progress','progressedThisMonth','heatmap','streak','longestStreak','totalWorkouts','monthWorkouts','todayWorkout','nextWorkout','todayCompleted');
    }

    public function dailyStreaks(array $dates): array
    {
        $active=[];foreach($dates as $date)$active[(new DateTimeImmutable($date))->format('Y-m-d')]=true;
        $day=new DateTimeImmutable('today');
        if(!isset($active[$day->format('Y-m-d')]))$day=$day->modify('-1 day');
        $streak=0;while(isset($active[$day->format('Y-m-d')])){$streak++;$day=$day->modify('-1 day');}
        $days=array_keys($active);sort($days);$longest=0;$run=0;$previous=null;foreach($days as $value){$current=new DateTimeImmutable($value);if($previous&&$previous->modify('+1 day')->format('Y-m-d')===$value)$run++;else $run=1;$longest=max($longest,$run);$previous=$current;}return ['current'=>$streak,'longest'=>$longest];
    }
}
