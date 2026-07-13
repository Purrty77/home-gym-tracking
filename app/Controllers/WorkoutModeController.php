<?php
namespace App\Controllers;
use App\Core\Controller;
use App\Services\ActiveWorkoutService;
use App\Services\AchievementService;
use App\Models\WorkoutSession;
use App\Models\WorkoutTemplate;
use DateTimeImmutable;
use RuntimeException;

final class WorkoutModeController extends Controller
{
    private ActiveWorkoutService $service;
    public function __construct(){ $this->service=new ActiveWorkoutService(); }
    public function create(): void
    {
        $activeWorkout=$this->service->active();
        $templates=WorkoutTemplate::all();
        $todayTemplate=WorkoutTemplate::forDay((int)(new DateTimeImmutable('today'))->format('N'));
        $this->view('workout-mode/create',compact('activeWorkout','templates','todayTemplate'));
    }
    public function start(): void { $template=(int)($_POST['template_id']??0);if(!$template){$this->redirect(route('dashboard'),'No workout plan selected.');}$this->service->start($template);$this->redirect(route('workouts.active')); }
    public function active(): void { $state=$this->service->active();if(!$state){$this->redirect(route('dashboard'));}if(!$state['current']){$this->redirect(route('workouts.active.summary'));}$this->view('workout-mode/active',compact('state')); }
    public function set(): void { header('Content-Type: application/json');try{echo json_encode($this->service->completeSet($_POST),JSON_THROW_ON_ERROR);}catch(RuntimeException $e){http_response_code(422);echo json_encode(['error'=>$e->getMessage()]);} }
    public function warmup(): void { header('Content-Type: application/json');try{echo json_encode($this->service->decideWarmup(($_POST['choice']??'no')==='yes'),JSON_THROW_ON_ERROR);}catch(RuntimeException $e){http_response_code(422);echo json_encode(['error'=>$e->getMessage()]);} }
    public function addSet(): void { header('Content-Type: application/json');try{echo json_encode($this->service->addSet(),JSON_THROW_ON_ERROR);}catch(RuntimeException $e){http_response_code(422);echo json_encode(['error'=>$e->getMessage()]);} }
    public function removeSet(): void { header('Content-Type: application/json');try{echo json_encode($this->service->removeSet((int)($_POST['set_id']??0)),JSON_THROW_ON_ERROR);}catch(RuntimeException $e){http_response_code(422);echo json_encode(['error'=>$e->getMessage()]);} }
    public function reorder(): void { header('Content-Type: application/json');try{$ids=json_decode((string)($_POST['ordered_ids']??'[]'),true);echo json_encode($this->service->reorderExercises(is_array($ids)?$ids:[],isset($_POST['activate_id'])?(int)$_POST['activate_id']:null),JSON_THROW_ON_ERROR);}catch(RuntimeException $e){http_response_code(422);echo json_encode(['error'=>$e->getMessage()]);} }
    public function bodyWeight(): void { header('Content-Type: application/json');try{echo json_encode($this->service->bodyWeight($_POST['weight_kg']??null),JSON_THROW_ON_ERROR);}catch(RuntimeException $e){http_response_code(422);echo json_encode(['error'=>$e->getMessage()]);} }
    public function replaceExercise(): void { header('Content-Type: application/json');try{echo json_encode($this->service->replaceExercise((int)($_POST['exercise_id']??0)),JSON_THROW_ON_ERROR);}catch(RuntimeException $e){http_response_code(422);echo json_encode(['error'=>$e->getMessage()]);} }
    public function timer(): void { header('Content-Type: application/json');echo json_encode($this->service->timer($_POST['action']??'skip')); }
    public function summary(): void { $state=$this->service->summary();$this->view('workout-mode/summary',compact('state')); }
    public function finish(): void { $id=$this->service->finish($_POST['notes']??null,isset($_POST['update_template_order']),isset($_POST['update_template_sets']));(new AchievementService())->evaluateWorkout($id);$this->redirect(route('workouts.result',['id'=>$id])); }
    public function result(string $id): void { $session=WorkoutSession::find((int)$id);if(!$session){http_response_code(404);$this->view('errors/404');return;}$achievements=(new AchievementService())->unlockedForWorkout((int)$id);$this->view('workout-mode/result',compact('session','achievements')); }
    public function abandon(): void { $this->service->abandon();$this->redirect(route('dashboard'),'Workout ended.'); }
    public function cancel(): void { $this->service->cancel();$this->redirect(route('dashboard'),'Workout cancelled. Nothing was saved.'); }
}
