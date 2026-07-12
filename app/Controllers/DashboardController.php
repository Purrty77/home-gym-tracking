<?php
namespace App\Controllers;
use App\Core\Controller;
use App\Models\WorkoutSession;
use App\Services\StatisticsService;
use App\Services\ActiveWorkoutService;
use App\Services\MotivationalMessageService;
final class DashboardController extends Controller { public function index(): void { $this->view('dashboard/index',['sessions'=>WorkoutSession::recent(5),'stats'=>(new StatisticsService())->dashboard(),'activeWorkout'=>(new ActiveWorkoutService())->active(),'dailyMessage'=>(new MotivationalMessageService())->current()]); } }
