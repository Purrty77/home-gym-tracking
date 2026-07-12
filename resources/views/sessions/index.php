<?php
$title='Workouts';$grouped=[];foreach($sessions as $session)$grouped[date('F Y',strtotime($session['performed_at']))][]=$session;
$style=function(string $type):array{$type=strtolower($type);if(str_contains($type,'leg'))return ['🦵','border-l-emerald-600','text-emerald-400','bg-emerald-950/40'];if(str_contains($type,'back')||str_contains($type,'biceps'))return ['💪','border-l-sky-600','text-sky-400','bg-sky-950/40'];if(str_contains($type,'shoulder')||str_contains($type,'neck'))return ['🏋️','border-l-violet-600','text-violet-400','bg-violet-950/40'];if(str_contains($type,'chest')||str_contains($type,'triceps'))return ['💪','border-l-orange-600','text-orange-400','bg-orange-950/40'];return ['🏋️','border-l-teal-600','text-teal-400','bg-teal-950/40'];};
?>
<header class="mb-6"><p class="text-sm font-semibold text-emerald-400">TRAINING HISTORY</p><h1 class="mt-1 text-3xl font-black">🏋️ Workouts</h1><p class="mt-2 text-zinc-400">Review your training history and progression.</p></header>

<section class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
  <div class="card border-sky-900/60"><p class="text-xs text-sky-400">📅 Workouts this month</p><p class="mt-1 text-2xl font-black"><?= (int)$summary['workouts_month'] ?></p></div>
  <div class="card border-violet-900/60"><p class="text-xs text-violet-400">💪 Working sets</p><p class="mt-1 text-2xl font-black"><?= (int)$summary['working_sets_month'] ?></p></div>
  <div class="card border-emerald-900/60"><p class="text-xs text-emerald-400">🏆 Most trained</p><p class="mt-1 truncate text-lg font-black"><?= e($summary['most_trained']??'—') ?></p></div>
  <div class="card border-orange-900/60"><p class="text-xs text-orange-400">Latest workout</p><p class="mt-1 text-lg font-black"><?= $summary['latest_workout']?e(date('M j, Y',strtotime($summary['latest_workout']))):'—' ?></p></div>
</section>

<form method="get" action="<?= e(route('workouts.index')) ?>" class="card mb-6 grid gap-3 p-3 md:grid-cols-[1fr_220px_180px_auto]">
  <label class="sr-only" for="workout-search">Search workouts</label><input id="workout-search" name="search" type="search" placeholder="Search workouts or exercises…" value="<?= e($filters['search']) ?>">
  <select name="plan" aria-label="Workout plan"><option value="all">All plans</option><?php foreach($plans as $plan): ?><option <?= $filters['plan']===$plan?'selected':'' ?> value="<?= e($plan) ?>"><?= e($plan) ?></option><?php endforeach; ?></select>
  <select name="period" aria-label="Period"><option value="all">All time</option><option value="month" <?= $filters['period']==='month'?'selected':'' ?>>This month</option><option value="3months" <?= $filters['period']==='3months'?'selected':'' ?>>Last 3 months</option><option value="year" <?= $filters['period']==='year'?'selected':'' ?>>This year</option></select>
  <button class="btn-info min-h-0">Filter</button>
</form>

<?php if(!$sessions): ?>
<section class="card py-12 text-center"><div class="text-5xl">🏋️</div><h2 class="mt-4 text-xl font-black"><?= array_filter($filters,fn($v)=>$v!==''&&$v!=='all')?'No workouts found':'No workouts yet' ?></h2><p class="mx-auto mt-2 max-w-md text-zinc-400"><?= array_filter($filters,fn($v)=>$v!==''&&$v!=='all')?'Try another search or remove a filter.':'Start your first workout to begin tracking your progress.' ?></p><a class="btn-primary mt-5 inline-flex" href="<?= e(array_filter($filters,fn($v)=>$v!==''&&$v!=='all')?route('workouts.index'):route('workouts.create')) ?>"><?= array_filter($filters,fn($v)=>$v!==''&&$v!=='all')?'Clear filters':'🏋️ Start first workout' ?></a></section>
<?php else: ?>
  <?php if(count($sessions)<3): ?><div class="mb-6 rounded-2xl border border-sky-900/50 bg-sky-950/20 p-4 text-sm text-sky-200"><strong>Your training history starts here.</strong><span class="text-sky-300/70"> Complete more workouts to unlock richer comparisons and progression trends.</span></div><?php endif; ?>
  <div class="space-y-8"><?php foreach($grouped as $month=>$monthSessions): ?><section><h2 class="mb-3 text-sm font-bold uppercase tracking-wider text-zinc-500"><?= e($month) ?></h2><div class="space-y-3"><?php foreach($monthSessions as $session):[$icon,$border,$accent,$badge]=$style($session['session_type']); ?>
    <a href="<?= e(route('workouts.show',['id'=>$session['id']])) ?>" class="group block rounded-2xl border border-zinc-800 border-l-4 <?= $border ?> bg-zinc-900 p-4 transition hover:-translate-y-0.5 hover:border-y-zinc-700 hover:border-r-zinc-700 hover:bg-zinc-800/80 hover:shadow-xl sm:p-5">
      <div class="flex items-start justify-between gap-3"><div class="flex min-w-0 gap-3"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl <?= $badge ?> text-xl"><?= $icon ?></span><div class="min-w-0"><h3 class="truncate text-lg font-black"><?= e($session['session_type']) ?></h3><p class="mt-1 truncate text-sm text-zinc-400"><?= e($session['exercise_names']?:'No exercises recorded') ?></p></div></div><div class="shrink-0 text-right"><p class="text-sm font-semibold"><?= e(date('M j, Y',strtotime($session['performed_at']))) ?></p><?php if($session['status']!=='completed'): ?><span class="mt-1 inline-block rounded-full bg-amber-950 px-2 py-1 text-xs text-amber-300">Incomplete</span><?php endif; ?></div></div>
      <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-zinc-800 pt-3 text-sm text-zinc-400"><span><?= (int)$session['working_set_count'] ?> working sets</span><?php if($session['body_weight_kg']!==null): ?><span>⚖️ <?= e(number_format((float)$session['body_weight_kg'],1)) ?> kg</span><?php endif; ?><?php if((int)$session['personal_record_count']>0): ?><span class="font-semibold text-emerald-400">🏆 <?= (int)$session['personal_record_count'] ?> PR<?= (int)$session['personal_record_count']===1?'':'s' ?></span><?php endif; ?><span class="ml-auto font-semibold <?= $accent ?>">View <span class="inline-block transition group-hover:translate-x-1">→</span></span></div>
    </a>
  <?php endforeach; ?></div></section><?php endforeach; ?></div>
<?php endif; ?>
