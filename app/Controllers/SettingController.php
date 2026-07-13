<?php
namespace App\Controllers;
use App\Core\Controller;
use App\Models\Setting;
final class SettingController extends Controller
{
    public function index(): void { $this->view('settings/index',['settings'=>Setting::all()]); }
    public function store(): void { $goal=in_array($_POST['body_weight_goal_direction']??'unset',['unset','gain','lose','maintain'],true)?$_POST['body_weight_goal_direction']:'unset';$theme=in_array($_POST['appearance_theme']??'dark',['dark','light'],true)?$_POST['appearance_theme']:'dark';$warmup=in_array($_POST['warmup_default_behavior']??'ask',['ask','add','never'],true)?$_POST['warmup_default_behavior']:'ask';Setting::save(['measurement_reminder_enabled'=>isset($_POST['measurement_reminder_enabled'])?'1':'0','measurement_reminder_day'=>'1','timer_sound_enabled'=>isset($_POST['timer_sound_enabled'])?'1':'0','timer_vibration_enabled'=>isset($_POST['timer_vibration_enabled'])?'1':'0','body_weight_goal_direction'=>$goal,'appearance_theme'=>$theme,'warmup_default_behavior'=>$warmup]); $this->redirect(route('settings.index'),'Settings saved.'); }
}
