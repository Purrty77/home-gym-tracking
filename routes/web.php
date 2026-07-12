<?php

use App\Controllers\AchievementController;
use App\Controllers\DashboardController;
use App\Controllers\ExerciseController;
use App\Controllers\MeasurementController;
use App\Controllers\SessionController;
use App\Controllers\SettingController;
use App\Controllers\WorkoutModeController;
use App\Controllers\BodyWeightController;

$router->get('/dashboard',[DashboardController::class,'index'],'dashboard');
$router->post('/body-weight',[BodyWeightController::class,'store'],'body-weight.store');

$router->get('/workouts',[SessionController::class,'index'],'workouts.index');
$router->get('/workouts/new',[WorkoutModeController::class,'create'],'workouts.create');
$router->get('/workouts/custom/new',[SessionController::class,'create'],'workouts.custom.create');
$router->post('/workouts',[SessionController::class,'store'],'workouts.store');
$router->get('/workouts/{id}/edit',[SessionController::class,'edit'],'workouts.edit');
$router->post('/workouts/{id}/edit',[SessionController::class,'update'],'workouts.update');
$router->post('/workouts/{id}/delete',[SessionController::class,'destroy'],'workouts.destroy');

$router->get('/exercises',[ExerciseController::class,'index'],'exercises.index');
$router->get('/exercises/new',[ExerciseController::class,'create'],'exercises.create');
$router->post('/exercises',[ExerciseController::class,'store'],'exercises.store');
$router->get('/exercises/{id}/edit',[ExerciseController::class,'edit'],'exercises.edit');
$router->post('/exercises/{id}/edit',[ExerciseController::class,'update'],'exercises.update');
$router->post('/exercises/{id}/delete',[ExerciseController::class,'destroy'],'exercises.destroy');
$router->get('/exercises/{id}',[ExerciseController::class,'show'],'exercises.show');

$router->get('/measurements',[MeasurementController::class,'index'],'measurements.index');
$router->post('/measurements',[MeasurementController::class,'store'],'measurements.store');
$router->post('/measurements/{id}/delete',[MeasurementController::class,'destroy'],'measurements.destroy');
$router->get('/settings',[SettingController::class,'index'],'settings.index');
$router->post('/settings',[SettingController::class,'store'],'settings.store');
$router->get('/achievements',[AchievementController::class,'index'],'achievements.index');

$router->post('/workouts/start',[WorkoutModeController::class,'start'],'workouts.start');
$router->get('/workouts/active',[WorkoutModeController::class,'active'],'workouts.active');
$router->post('/workouts/active/set',[WorkoutModeController::class,'set'],'workouts.active.set');
$router->post('/workouts/active/add-set',[WorkoutModeController::class,'addSet'],'workouts.active.add-set');
$router->post('/workouts/active/remove-set',[WorkoutModeController::class,'removeSet'],'workouts.active.remove-set');
$router->post('/workouts/active/body-weight',[WorkoutModeController::class,'bodyWeight'],'workouts.active.body-weight');
$router->post('/workouts/active/timer',[WorkoutModeController::class,'timer'],'workouts.active.timer');
$router->get('/workouts/active/summary',[WorkoutModeController::class,'summary'],'workouts.active.summary');
$router->post('/workouts/active/finish',[WorkoutModeController::class,'finish'],'workouts.active.finish');
$router->post('/workouts/active/end',[WorkoutModeController::class,'abandon'],'workouts.active.end');
$router->post('/workouts/active/cancel',[WorkoutModeController::class,'cancel'],'workouts.active.cancel');
$router->get('/workouts/{id}/result',[WorkoutModeController::class,'result'],'workouts.result');
$router->get('/workouts/{id}',[SessionController::class,'show'],'workouts.show');

// Temporary compatibility redirects for old bookmarks.
$router->redirect('/','/dashboard');
$router->redirect('/seances','/workouts');
$router->redirect('/seances/nouvelle','/workouts/custom/new');
$router->redirect('/workout/new','/workouts/new');
$router->redirect('/workout/active','/workouts/active');
$router->redirect('/exercices','/exercises');
$router->redirect('/exercices/nouveau','/exercises/new');
$router->redirect('/mensurations','/measurements');
$router->redirect('/parametres','/settings');
$router->get('/seances/{id}/modifier',static function(string $id):void{header('Location: /workouts/'.rawurlencode($id).'/edit',true,302);exit;});
$router->get('/seances/{id}',static function(string $id):void{header('Location: /workouts/'.rawurlencode($id),true,302);exit;});
$router->get('/exercices/{id}/modifier',static function(string $id):void{header('Location: /exercises/'.rawurlencode($id).'/edit',true,302);exit;});
$router->get('/exercices/{id}',static function(string $id):void{header('Location: /exercises/'.rawurlencode($id),true,302);exit;});

// Legacy POST endpoints remain accepted during the transition.
$router->post('/seances',[SessionController::class,'store']);
$router->post('/seances/{id}/modifier',[SessionController::class,'update']);
$router->post('/seances/{id}/supprimer',[SessionController::class,'destroy']);
$router->post('/exercices',[ExerciseController::class,'store']);
$router->post('/exercices/{id}/modifier',[ExerciseController::class,'update']);
$router->post('/exercices/{id}/supprimer',[ExerciseController::class,'destroy']);
$router->post('/mensurations',[MeasurementController::class,'store']);
$router->post('/mensurations/{id}/supprimer',[MeasurementController::class,'destroy']);
$router->post('/parametres',[SettingController::class,'store']);
$router->post('/workout/start',[WorkoutModeController::class,'start']);
$router->post('/workout/set',[WorkoutModeController::class,'set']);
$router->post('/workout/timer',[WorkoutModeController::class,'timer']);
$router->get('/workout/summary',[WorkoutModeController::class,'summary']);
$router->post('/workout/finish',[WorkoutModeController::class,'finish']);
$router->get('/workout/result/{id}',[WorkoutModeController::class,'result']);
$router->post('/workout/abandon',[WorkoutModeController::class,'abandon']);
$router->post('/workout/cancel',[WorkoutModeController::class,'cancel']);
