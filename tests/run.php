<?php
declare(strict_types=1);
use App\Core\Validator;
use App\Services\StatisticsService;
require dirname(__DIR__).'/vendor/autoload.php';

$tests=[];
function test(string $name,callable $callback): void { global $tests;try{$callback();$tests[]=['ok',$name];}catch(Throwable $e){$tests[]=['fail',$name,$e->getMessage()];} }
function expect(bool $condition,string $message='Assertion échouée'): void { if(!$condition)throw new RuntimeException($message); }

test('a valid workout is accepted',function(){expect(Validator::workout(['performed_at'=>'2026-07-12T10:00','session_type'=>'Legs','body_weight_kg'=>'80.5','exercises'=>[['exercise_id'=>'1','sets'=>[['weight_kg'=>'50','repetitions'=>'12','rest_seconds'=>'90']]]]])===[]);});
test('an empty workout is rejected',function(){expect(count(Validator::workout([]))>=3);});
test('invalid training values are rejected',function(){expect(count(Validator::workout(['performed_at'=>'2026-07-12','session_type'=>'Test','body_weight_kg'=>'999','exercises'=>[['exercise_id'=>1,'sets'=>[['weight_kg'=>'-1','repetitions'=>'abc','rest_seconds'=>'9999']]]]]))===4);});
test('a valid exercise is accepted',function(){expect(Validator::exercise(['name'=>'Squat','muscle_group_id'=>1,'recommended_rest_seconds'=>120])===[]);});
test('a dated check-up may skip every optional measurement',function(){expect(Validator::measurement(['measured_on'=>'2026-07-12'])===[]);});
test('HTML is escaped',function(){expect(e('<script>')==='&lt;script&gt;');});
test('displayed numbers use at most two decimals',function(){expect(format_number('60.000000')==='60');expect(format_number('47.200000')==='47.2');expect(format_number('17.555')==='17.56');});
test('local assets are cache-busted',function(){expect(str_starts_with(asset('assets/js/app.js'),'/assets/js/app.js?v='));});
test('workout streaks count consecutive days across week boundaries',function(){$today=new DateTimeImmutable('today');$dates=[$today->modify('-1 day')->format('Y-m-d'),$today->format('Y-m-d')];$streak=(new StatisticsService())->dailyStreaks($dates);expect($streak['current']===2&&$streak['longest']===2,'Sunday and Monday should be a two-day streak, not two weeks.');});

$failures=0;foreach($tests as $result){[$status,$name]=$result;echo ($status==='ok'?'✓':'✗')." {$name}".($status==='fail'?': '.$result[2]:'').PHP_EOL;if($status==='fail')$failures++;}echo PHP_EOL.count($tests).' tests, '.$failures.' failure(s).'.PHP_EOL;exit($failures?1:0);
