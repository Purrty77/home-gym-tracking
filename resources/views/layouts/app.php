<?php
$layoutSettings=\App\Models\Setting::all();$appearanceTheme=$layoutSettings['appearance_theme']??'dark';$currentPath=parse_url($_SERVER['REQUEST_URI']??'/dashboard',PHP_URL_PATH)?:'/dashboard';
$navActive=function(string $section)use($currentPath):bool{return $section==='dashboard'?$currentPath==='/dashboard':str_starts_with($currentPath,'/'.$section);};
$navClass=function(string $section)use($navActive):string{return 'min-w-0 rounded-xl px-1 py-2 text-center transition sm:whitespace-nowrap sm:px-3 '.($navActive($section)?'bg-zinc-800 font-bold text-white ring-1 ring-emerald-700/70':'text-zinc-400 hover:bg-zinc-900 hover:text-white');};
$topLevel=['/dashboard','/workouts','/exercises','/achievements','/measurements','/settings'];$showBack=!($workoutMode??false)&&!in_array($currentPath,$topLevel,true);$backFallback=str_starts_with($currentPath,'/workouts')?route('workouts.index'):(str_starts_with($currentPath,'/exercises')?route('exercises.index'):route('dashboard'));
?>
<!doctype html>
<html lang="en" data-theme="<?= e($appearanceTheme) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="<?= $appearanceTheme==='light'?'#f4f4f5':'#020617' ?>">
  <title><?= e(($title ?? 'Home Gym') . ' · Home Gym') ?></title>
  <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
  <script defer src="<?= e(asset('assets/vendor/chart.umd.js')) ?>"></script>
  <script defer src="<?= e(asset('assets/js/app.js')) ?>"></script>
</head>
<body class="min-h-screen">
  <header class="sticky top-0 z-40 border-b border-zinc-800 bg-zinc-950/95 backdrop-blur">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-3 px-4 py-3">
      <a href="<?= e(route('dashboard')) ?>" class="mr-auto text-xl font-black tracking-tight"><span class="text-emerald-600">H</span>ome Gym</a>
      <?php if(!($workoutMode??false)): ?>
      <nav class="top-nav order-3 grid w-full grid-cols-3 items-center gap-1 text-[11px] text-zinc-400 sm:order-none sm:flex sm:w-auto sm:text-sm">
        <a class="<?= e($navClass('dashboard')) ?>" <?= $navActive('dashboard')?'aria-current="page"':'' ?> href="<?= e(route('dashboard')) ?>">Dashboard</a><a class="<?= e($navClass('workouts')) ?>" <?= $navActive('workouts')?'aria-current="page"':'' ?> href="<?= e(route('workouts.index')) ?>">Workouts</a><a class="<?= e($navClass('exercises')) ?>" <?= $navActive('exercises')?'aria-current="page"':'' ?> href="<?= e(route('exercises.index')) ?>">Exercises</a><a class="<?= e($navClass('achievements')) ?>" <?= $navActive('achievements')?'aria-current="page"':'' ?> href="<?= e(route('achievements.index')) ?>">Achievements</a><a class="<?= e($navClass('measurements')) ?>" <?= $navActive('measurements')?'aria-current="page"':'' ?> href="<?= e(route('measurements.index')) ?>">Measurements</a><a class="<?= e($navClass('settings')) ?>" <?= $navActive('settings')?'aria-current="page"':'' ?> href="<?= e(route('settings.index')) ?>">Settings</a>
      </nav>
      <a href="<?= e(route('workouts.create')) ?>" class="btn-primary shrink-0"><span class="sm:hidden">🏋️</span><span class="hidden sm:inline">🏋️ New workout</span></a>
      <?php else: ?><a href="<?= e(route('dashboard')) ?>" class="text-sm text-zinc-400 hover:text-white">Save & exit</a><?php endif; ?>
    </div>
  </header>
  <main class="mx-auto max-w-6xl px-4 py-6">
    <?php if($showBack): ?><button type="button" data-back-button data-fallback="<?= e($backFallback) ?>" class="mb-5 inline-flex min-h-11 items-center gap-2 rounded-xl px-3 text-sm font-semibold text-zinc-400 transition hover:bg-zinc-900 hover:text-white">← Back</button><?php endif; ?>
    <?php if ($message = flash('success')): ?><div class="mb-4 rounded-xl border border-emerald-700 bg-emerald-950 p-3 text-emerald-200"><?= e($message) ?></div><?php endif; ?>
    <?php if ($message = flash('error')): ?><div class="mb-4 rounded-xl border border-red-700 bg-red-950 p-3 text-red-200"><?= e($message) ?></div><?php endif; ?>
    <?= $content ?>
  </main>
  <div id="confirm-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/75 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="confirm-modal-title" aria-describedby="confirm-modal-message">
    <div class="w-full max-w-md rounded-3xl border border-zinc-700 bg-zinc-900 p-6 shadow-2xl shadow-black/50">
      <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-red-950 text-2xl font-black text-red-300">!</div>
      <h2 id="confirm-modal-title" class="mt-4 text-xl font-black">Are you sure?</h2>
      <p id="confirm-modal-message" class="mt-2 leading-relaxed text-zinc-400"></p>
      <div class="mt-6 grid grid-cols-2 gap-3">
        <button id="confirm-modal-dismiss" type="button" class="btn-secondary">Go back</button>
        <button id="confirm-modal-accept" type="button" class="btn-danger">Confirm</button>
      </div>
    </div>
  </div>
</body>
</html>
