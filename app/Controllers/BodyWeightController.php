<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\BodyWeightEntry;

final class BodyWeightController extends Controller
{
    public function store(): void { $date=(string)($_POST['recorded_on']??date('Y-m-d'));$weight=$_POST['weight_kg']??'';if(!is_numeric($weight)){$_SESSION['_flash']['error']='Enter a valid body weight.';$this->redirect(route('dashboard'));}try{BodyWeightEntry::record($date,(float)$weight,'dashboard');$this->redirect(route('dashboard'),'Body weight saved.');}catch(\InvalidArgumentException $e){$_SESSION['_flash']['error']=$e->getMessage();$this->redirect(route('dashboard'));} }
}
