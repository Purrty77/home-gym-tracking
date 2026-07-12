<?php
$title='New workout';
$dayNames=['1'=>'Monday','2'=>'Tuesday','3'=>'Wednesday','4'=>'Thursday','5'=>'Friday','6'=>'Saturday','7'=>'Sunday'];
$schedule=function(string $days)use($dayNames):string{return implode(' & ',array_map(fn($day)=>$dayNames[$day]??$day,explode(',',$days)));};
?>
<div class="mx-auto max-w-4xl">
  <div class="mb-6">
    <p class="text-sm font-semibold text-emerald-400">🏋️ WORKOUT MODE</p>
    <h1 class="mt-1 text-3xl font-black">Ready to train?</h1>
    <p class="mt-2 text-zinc-400">Choose a plan and the app will guide you through every set.</p>
  </div>

  <?php if($activeWorkout): ?>
  <section class="mb-6 overflow-hidden rounded-2xl border border-amber-700/60 bg-gradient-to-br from-amber-950/35 to-zinc-900 p-5">
    <p class="text-sm font-bold text-amber-400">● WORKOUT IN PROGRESS</p>
    <div class="mt-2 flex flex-wrap items-end justify-between gap-4">
      <div><h2 class="text-2xl font-black"><?= e($activeWorkout['session']['session_type']) ?></h2><p class="mt-1 text-zinc-400">Your completed sets are saved. Pick up exactly where you stopped.</p></div>
      <div class="flex flex-wrap items-center gap-3"><a href="<?= e(route('workouts.active')) ?>" class="btn-reminder">▶ Resume workout</a><form method="post" action="<?= e(route('workouts.active.cancel')) ?>" data-confirm="Cancel this workout? Every set entered in this workout will be permanently deleted." data-confirm-button="Cancel workout"><?= csrf_field() ?><button class="text-sm font-semibold text-red-400 hover:text-red-300">✕ Cancel</button></form></div>
    </div>
  </section>
  <?php endif; ?>

  <?php if(!$activeWorkout&&$todayTemplate): ?>
  <section class="mb-6 rounded-2xl border border-emerald-700/60 bg-gradient-to-br from-emerald-950/60 via-zinc-900 to-sky-950/30 p-5 shadow-xl shadow-emerald-950/10">
    <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-sm font-bold text-emerald-400">💪 TODAY'S PLAN</p><h2 class="mt-1 text-2xl font-black"><?= e($todayTemplate['session_type']) ?></h2><p class="text-sm text-zinc-400"><?= e($schedule($todayTemplate['scheduled_days'])) ?> · <?= count($todayTemplate['exercises']) ?> exercises</p></div><span class="rounded-full bg-emerald-950 px-3 py-1 text-xs font-bold text-emerald-300">Recommended</span></div>
    <div class="mt-4 grid gap-2 sm:grid-cols-2"><?php foreach($todayTemplate['exercises'] as $exercise): ?><p class="flex gap-2 text-sm text-zinc-300"><span class="text-emerald-500">✓</span><?= e($exercise['exercise_name']) ?> <span class="text-zinc-600">· <?= (int)$exercise['set_count'] ?> sets</span></p><?php endforeach; ?></div>
    <form method="post" action="<?= e(route('workouts.start')) ?>" class="mt-5"><?= csrf_field() ?><input type="hidden" name="template_id" value="<?= (int)$todayTemplate['id'] ?>"><button class="btn-primary w-full sm:w-auto">🏋️ Start today's workout</button></form>
  </section>
  <?php endif; ?>

  <?php if(!$activeWorkout): ?>
  <section>
    <div class="mb-3"><h2 class="text-lg font-bold">Choose another workout</h2><p class="text-sm text-zinc-500">Your usual plans are ready with their exercises, targets and rest times.</p></div>
    <div class="grid gap-3 sm:grid-cols-2"><?php foreach($templates as $template):if((int)($todayTemplate['id']??0)===(int)$template['id'])continue; ?>
      <form method="post" action="<?= e(route('workouts.start')) ?>" class="card group flex flex-col transition hover:-translate-y-0.5 hover:border-emerald-800"><?= csrf_field() ?><input type="hidden" name="template_id" value="<?= (int)$template['id'] ?>"><div class="flex-1"><p class="text-xs font-semibold text-sky-400"><?= e(strtoupper($schedule($template['scheduled_days']))) ?></p><h3 class="mt-1 text-lg font-black"><?= e($template['session_type']) ?></h3><p class="mt-2 text-sm text-zinc-500">Guided sets · automatic rest · progress saved</p></div><button class="mt-4 w-full rounded-xl border border-zinc-700 px-4 py-3 font-bold text-zinc-200 transition group-hover:border-emerald-700 group-hover:bg-emerald-950/40 group-hover:text-emerald-300">Start this workout →</button></form>
    <?php endforeach; ?></div>
  </section>

  <div class="mt-8 border-t border-zinc-800 pt-5 text-center"><p class="text-sm text-zinc-500">Need something outside your usual program?</p><a href="<?= e(route('workouts.custom.create')) ?>" class="mt-2 inline-block text-sm font-semibold text-zinc-400 hover:text-white">Create a free workout with the classic editor →</a></div>
  <?php endif; ?>
</div>
