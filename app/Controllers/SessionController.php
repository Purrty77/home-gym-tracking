<?php
namespace App\Controllers;
use App\Core\Controller;
use App\Models\Exercise;
use App\Models\WorkoutSession;
use App\Models\WorkoutTemplate;
use App\Core\Validator;
use App\Services\AchievementService;
use App\Models\BodyWeightEntry;
use App\Services\HistoricalWorkoutRecalculationService;
final class SessionController extends Controller
{
    public function index(): void
    {
        $filters=['search'=>trim((string)($_GET['search']??'')),'plan'=>trim((string)($_GET['plan']??'all')),'period'=>trim((string)($_GET['period']??'all'))];
        $this->view('sessions/index',['sessions'=>WorkoutSession::history($filters),'summary'=>WorkoutSession::historySummary(),'plans'=>WorkoutSession::planNames(),'filters'=>$filters]);
    }
    public function create(): void
    {
        $templates=WorkoutTemplate::all();$date=$_GET['date']??date('Y-m-d');$selected=null;
        if(isset($_GET['template'])&&ctype_digit((string)$_GET['template']))$selected=WorkoutTemplate::find((int)$_GET['template']);
        if(!$selected)$selected=WorkoutTemplate::forDay((int)date('N',strtotime($date)));
        $preset=$selected?WorkoutTemplate::asFormData($selected):[];
        $this->view('sessions/create',['exercises'=>Exercise::all(),'templates'=>$templates,'selectedTemplate'=>$selected,'preset'=>$preset,'formDate'=>$date]);
    }
    public function store(): void
    {
        $_SESSION['_old']=$_POST;
        if ($errors=Validator::workout($_POST)) { $_SESSION['_flash']['error']=implode(' ', $errors); $this->redirect(route('workouts.custom.create')); }
        $id=WorkoutSession::create($_POST);$this->syncBodyWeight($_POST);(new AchievementService())->evaluateWorkout($id);unset($_SESSION['_old']);$this->redirect(route('workouts.result',['id'=>$id]));
    }
    public function show(string $id): void { $session=WorkoutSession::find((int)$id); if(!$session){http_response_code(404);$this->view('errors/404');return;} $this->view('sessions/show',compact('session')); }
    public function edit(string $id): void { $session=WorkoutSession::forEdit((int)$id);if(!$session){http_response_code(404);$this->view('errors/404');return;}$this->view('sessions/create',['exercises'=>Exercise::all(),'session'=>$session,'templates'=>WorkoutTemplate::all(),'selectedTemplate'=>null,'preset'=>[],'formDate'=>date('Y-m-d',strtotime($session['performed_at']))]); }
    public function update(string $id): void { if($errors=Validator::workout($_POST)){$_SESSION['_flash']['error']=implode(' ',$errors);$this->redirect(route('workouts.edit',['id'=>$id]));}$existing=WorkoutSession::find((int)$id);if(!$existing){http_response_code(404);$this->view('errors/404');return;}WorkoutSession::update((int)$id,$_POST);BodyWeightEntry::syncWorkoutEdit(date('Y-m-d',strtotime($existing['performed_at'])),date('Y-m-d',strtotime((string)$_POST['performed_at'])),$_POST['body_weight_kg']??null);(new HistoricalWorkoutRecalculationService())->recalculate((int)$id);$this->redirect(route('workouts.show',['id'=>$id]),'Workout updated successfully.'); }
    public function destroy(string $id): void { WorkoutSession::delete((int)$id); $this->redirect(route('workouts.index'),'Workout deleted.'); }

    private function syncBodyWeight(array $data): void
    {
        if(!is_numeric($data['body_weight_kg']??null))return;$date=date('Y-m-d',strtotime((string)($data['performed_at']??'now')));BodyWeightEntry::record($date,(float)$data['body_weight_kg'],'workout');
    }
}
