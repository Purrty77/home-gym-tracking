<?php
$editing=isset($session);$title=$editing?'Edit workout':'New workout';$initial=$session['exercises']??$preset;
?>
<header class="mb-6 flex flex-wrap items-end justify-between gap-3">
  <div><p class="text-sm font-semibold <?= $editing?'text-violet-400':'text-emerald-400' ?>"><?= $editing?'HISTORICAL EDITOR':'CLASSIC EDITOR' ?></p><h1 class="mt-1 text-3xl font-black"><?= e($title) ?></h1><p class="mt-2 text-zinc-400"><?= $editing?'Correct recorded details without changing the workout until you confirm Save changes.':'Record a workout manually.' ?></p></div>
  <?php if($editing): ?><a class="btn-secondary" href="<?= e(route('workouts.show',['id'=>$session['id']])) ?>">Cancel editing</a><?php endif; ?>
</header>

<form method="post" action="<?= e($editing?route('workouts.update',['id'=>$session['id']]):route('workouts.store')) ?>" id="workout-form" class="space-y-6" data-editing="<?= $editing?'1':'0' ?>" data-initial='<?= e(json_encode($initial,JSON_UNESCAPED_UNICODE)) ?>'><?= csrf_field() ?>
  <section class="card grid gap-4 sm:grid-cols-2">
    <div><label for="workout_template_id">Workout plan</label><select id="workout_template_id" name="workout_template_id"><option value="">Free workout</option><?php foreach($templates as $template): ?><option value="<?= (int)$template['id'] ?>" <?= (int)($session['workout_template_id']??$selectedTemplate['id']??0)===(int)$template['id']?'selected':'' ?>><?= e($template['name']) ?></option><?php endforeach; ?></select></div>
    <div><label for="performed_at">Date and time</label><input id="performed_at" type="datetime-local" name="performed_at" required value="<?= e($editing?date('Y-m-d\TH:i',strtotime($session['performed_at'])):old('performed_at',$formDate.'T'.date('H:i'))) ?>"></div>
    <div><label for="session_type">Workout name</label><input id="session_type" name="session_type" required placeholder="e.g. Legs" value="<?= e($session['session_type']??$selectedTemplate['session_type']??old('session_type')) ?>"></div>
    <div><label for="body_weight_kg">Body weight (kg)</label><input id="body_weight_kg" type="number" step="any" min="0" name="body_weight_kg" value="<?= e(format_number($session['body_weight_kg']??old('body_weight_kg'))) ?>"></div>
    <div class="sm:col-span-2"><label for="notes">Workout notes</label><textarea id="notes" name="notes" rows="2"><?= e($session['notes']??old('notes')) ?></textarea></div>
  </section>

  <div id="exercise-list" class="space-y-4"></div>

  <template id="exercise-template"><section class="card exercise-block" data-original="0">
    <div class="mb-4 flex items-start gap-2"><div class="grid min-w-0 flex-1 gap-3 sm:grid-cols-2"><div><label>Exercise</label><select data-name="exercise_id" required><option value="">Choose an exercise</option><?php foreach($exercises as $exercise): ?><option value="<?= (int)$exercise['id'] ?>" data-rest="<?= (int)$exercise['recommended_rest_seconds'] ?>" data-load="<?= e($exercise['load_semantics']) ?>" data-dumbbell="<?= ($exercise['equipment']??'')==='Dumbbells'?'1':'0' ?>"><?= e($exercise['muscle_group'].' · '.$exercise['name']) ?></option><?php endforeach; ?></select></div><div><label>Completion status</label><select data-name="status"><option value="completed">Completed</option><option value="skipped">Skipped</option></select></div></div><div class="flex gap-1"><button type="button" class="move-exercise-up rounded-lg p-2 text-zinc-400" title="Move up">↑</button><button type="button" class="move-exercise-down rounded-lg p-2 text-zinc-400" title="Move down">↓</button><button class="remove-exercise rounded-lg p-2 font-bold text-red-400" type="button" title="Remove exercise">×</button></div></div>
    <p class="exercise-target mb-3 hidden text-sm font-semibold text-emerald-500"></p>
    <input type="hidden" data-name="load_semantics"><div class="mb-4 grid gap-3 sm:grid-cols-2"><div><label>Exercise notes</label><input data-name="notes" placeholder="Optional notes"></div><div data-load-mode-wrap><label>Recorded dumbbell weight</label><select data-load-semantics-control><option value="per_dumbbell">Per dumbbell</option><option value="total">Combined total</option></select></div></div>
    <label data-add-template-wrap class="mb-4 hidden items-center gap-3 rounded-xl bg-violet-950/25 p-3 text-sm"><input type="checkbox" data-name="add_to_template" value="1"><span>Add this missing exercise to the selected workout plan</span></label>
    <div class="sets space-y-3"></div><button type="button" class="add-set mt-3 text-sm font-semibold text-emerald-500">+ Add missing set</button>
  </section></template>

  <template id="set-template"><div class="set-row rounded-2xl border border-zinc-800 bg-zinc-950/60 p-3">
    <div class="mb-3 flex items-center justify-between gap-2"><strong data-set-label>Set</strong><div class="flex gap-1"><button type="button" class="move-set-up rounded-lg p-2 text-zinc-400" title="Move up">↑</button><button type="button" class="move-set-down rounded-lg p-2 text-zinc-400" title="Move down">↓</button><button type="button" class="remove-set rounded-lg p-2 text-red-400" title="Delete set">Delete</button></div></div>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-5"><div><label>Type</label><select data-set-name="set_type"><option value="warmup">Warm-up</option><option value="ramp">Ramp-up</option><option value="working" selected>Working</option></select></div><div data-normal-performance><label>Weight kg</label><input data-set-name="weight_kg" inputmode="decimal" type="number" min="0" step="any"></div><div data-normal-performance><label>Repetitions</label><input data-set-name="repetitions" type="number" min="0"></div><div><label>Rest seconds</label><input data-set-name="rest_seconds" type="number" min="0"></div><div><label>Set notes</label><input data-set-name="notes"></div></div>
    <div data-segment-list class="mt-3 hidden space-y-2"></div><button type="button" class="add-segment mt-3 text-sm font-semibold text-orange-400">↘ Add weight change</button>
  </div></template>

  <template id="segment-template"><div class="segment-row grid grid-cols-[1fr_1fr_auto] gap-2 rounded-xl border border-orange-900/50 bg-orange-950/20 p-3"><div><label data-segment-label>Segment</label><input data-segment-name="weight_kg" inputmode="decimal" type="number" min="0" step="any" placeholder="Weight kg"></div><div><label>Repetitions</label><input data-segment-name="repetitions" type="number" min="0"></div><button type="button" class="remove-segment self-end rounded-lg p-3 text-red-400">×</button></div></template>

  <button type="button" id="add-exercise" class="btn-secondary w-full">+ Add missing exercise</button>
  <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><?php if($editing): ?><a class="btn-secondary" href="<?= e(route('workouts.show',['id'=>$session['id']])) ?>">Cancel</a><?php endif; ?><button class="btn-primary" type="submit"><?= $editing?'Review changes':'Save workout' ?></button></div>
</form>

<div id="delete-set-modal" class="fixed inset-0 z-[110] hidden items-center justify-center bg-black/80 p-4 backdrop-blur-sm"><div class="w-full max-w-sm rounded-3xl border border-zinc-700 bg-zinc-900 p-6"><h2 class="text-xl font-black">Delete this completed set?</h2><p class="mt-2 text-sm text-zinc-400">The set will only be removed after you save the workout.</p><div class="mt-6 grid grid-cols-2 gap-3"><button type="button" data-cancel-set-delete class="btn-secondary">Keep set</button><button type="button" data-confirm-set-delete class="btn-danger">Delete set</button></div></div></div>

<?php if($editing): ?><div id="workout-change-modal" class="fixed inset-0 z-[115] hidden items-center justify-center bg-black/80 p-4 backdrop-blur-sm"><div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-3xl border border-zinc-700 bg-zinc-900 p-6"><p class="text-sm font-bold text-violet-400">REVIEW CHANGES</p><h2 class="mt-1 text-2xl font-black">You changed:</h2><ul id="workout-change-summary" class="mt-4 space-y-2 text-sm text-zinc-300"></ul><p class="mt-4 rounded-xl bg-zinc-950/60 p-3 text-xs text-zinc-500">Personal records and current progress will be recalculated. Achievements remain permanently unlocked once earned.</p><div class="mt-6 grid grid-cols-2 gap-3"><button type="button" data-cancel-workout-save class="btn-secondary">Cancel</button><button type="button" data-confirm-workout-save class="btn-primary">Save changes</button></div></div></div><?php endif; ?>

<div id="rest-timer" class="fixed bottom-6 right-4 z-50 hidden rounded-2xl border border-emerald-800 bg-zinc-900 p-4 shadow-2xl"><p class="text-xs uppercase text-emerald-500">Rest timer</p><p id="timer-value" class="text-3xl font-black tabular-nums">00:00</p><button id="stop-timer" class="mt-1 text-sm text-zinc-400">Stop</button></div>
