<?php
namespace App\Controllers;
use App\Core\Controller;
use App\Models\Measurement;
use App\Core\Validator;
use App\Services\AchievementService;
final class MeasurementController extends Controller
{
    public function index(): void { $this->view('measurements/index',Measurement::pageData()); }
    public function store(): void { if($errors=Validator::measurement($_POST)){$_SESSION['_flash']['error']=implode(' ',$errors);$this->redirect(route('measurements.index'));} Measurement::create($_POST);$unlocked=(new AchievementService())->evaluateTracking();$message='Measurements saved.'.($unlocked?' 🏆 Achievement unlocked: '.$unlocked[0]['name']:'' );$this->redirect(route('measurements.index'),$message); }
    public function destroy(string $id): void { Measurement::delete((int)$id); $this->redirect(route('measurements.index'),'Measurement deleted.'); }
}
