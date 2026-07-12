<?php
namespace App\Controllers;
use App\Core\Controller;
use App\Models\Exercise;
use App\Core\Validator;
use App\Services\ExerciseProgressService;
final class ExerciseController extends Controller
{
    public function index(): void
    {
        $filters=['search'=>trim((string)($_GET['search']??'')),'scope'=>trim((string)($_GET['scope']??'all')),'muscle'=>trim((string)($_GET['muscle']??'')),'equipment'=>trim((string)($_GET['equipment']??''))];
        $this->view('exercises/index',array_merge(Exercise::library($filters),['filters'=>$filters]));
    }
    public function create(): void { $this->view('exercises/form',Exercise::formOptions()); }
    public function store(): void
    {
        if ($errors=Validator::exercise($_POST)) { $_SESSION['_flash']['error']=implode(' ',$errors); $this->redirect(route('exercises.create')); }
        $id=Exercise::create($_POST); $this->redirect(route('exercises.show',['id'=>$id]),'Exercise created.');
    }
    public function show(string $id): void { $exercise=Exercise::find((int)$id); if(!$exercise){http_response_code(404);$this->view('errors/404');return;}$limit=max(10,min(50,(int)($_GET['limit']??10)));$progress=(new ExerciseProgressService())->build((int)$id,$limit);$this->view('exercises/show',compact('exercise','progress','limit')); }
    public function edit(string $id): void { $exercise=Exercise::find((int)$id); if(!$exercise){http_response_code(404);$this->view('errors/404');return;} $this->view('exercises/form',array_merge(Exercise::formOptions(),compact('exercise'))); }
    public function update(string $id): void { if($errors=Validator::exercise($_POST)){$_SESSION['_flash']['error']=implode(' ',$errors);$this->redirect(route('exercises.edit',['id'=>$id]));} Exercise::update((int)$id,$_POST);$this->redirect(route('exercises.show',['id'=>$id]),'Exercise updated.'); }
    public function destroy(string $id): void { Exercise::deactivate((int)$id); $this->redirect(route('exercises.index'),'Exercise deactivated. Its history was kept.'); }
}
