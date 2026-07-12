<?php
namespace App\Controllers;
use App\Core\Controller;
use App\Services\AchievementService;
final class AchievementController extends Controller { public function index(): void { $achievements=(new AchievementService())->allWithProgress();$this->view('achievements/index',compact('achievements')); } }
