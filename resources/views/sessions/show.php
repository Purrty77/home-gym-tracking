<?php
$title=$session['session_type'];
$grouped=[];
foreach($session['rows'] as $row)$grouped[$row['workout_exercise_id']][]=$row;
$loadLabels=['per_dumbbell'=>'Per dumbbell','total'=>'Combined / total load','machine_stack'=>'Machine stack','added_plates'=>'Added plates'];
?>
<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
  <div>
    <p class="text-sm text-emerald-500"><?= e(date('M j, Y · H:i',strtotime($session['performed_at']))) ?></p>
    <h1 class="text-3xl font-black"><?= e($session['session_type']) ?></h1>
    <?php if($session['body_weight_kg']): ?><p class="text-zinc-400">Body weight: <?= e(format_number($session['body_weight_kg'])) ?> kg</p><?php endif; ?>
    <?php if($session['last_edited_at']??null): ?><p class="mt-1 text-xs text-zinc-500">Edited on <?= e(date('M j, Y · H:i',strtotime($session['last_edited_at']))) ?></p><?php endif; ?>
  </div>
  <a class="btn-secondary shrink-0" href="<?= e(route('workouts.edit',['id'=>$session['id']])) ?>">✏️ Edit workout</a>
</div>
<?php if($session['notes']): ?><div class="card mb-4"><p class="mb-1 text-xs font-semibold uppercase tracking-wide text-zinc-500">Workout notes</p><?= nl2br(e($session['notes'])) ?></div><?php endif; ?>
<div class="space-y-4">
<?php foreach($grouped as $rows):$first=$rows[0];$skipped=$first['exercise_status']==='skipped'; ?>
  <section class="card <?= $skipped?'opacity-60':'' ?>">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
      <div><h2 class="text-lg font-bold"><?= e($first['name']) ?></h2><p class="text-xs text-zinc-500"><?= e($loadLabels[$first['load_semantics']]??'Recorded load') ?></p></div>
      <?php if($skipped): ?><span class="rounded-full bg-zinc-800 px-2 py-1 text-xs text-zinc-400">Skipped</span><?php endif; ?>
    </div>
    <?php if($first['exercise_notes']??null): ?><p class="mb-3 text-sm text-zinc-400"><?= nl2br(e($first['exercise_notes'])) ?></p><?php endif; ?>
    <?php if($skipped): ?><p class="text-sm text-zinc-500">No performance recorded.</p><?php else: ?>
      <div class="overflow-x-auto"><table><thead><tr><th>#</th><th>Type</th><th>Performance</th><th>Rest</th><th>Note</th></tr></thead><tbody>
      <?php foreach($rows as $row):if(!$row['id'])continue;$segments=$row['segments']??[]; ?>
        <tr><td><?= (int)$row['position'] ?></td><td><?= e(['warmup'=>'Warm-up','ramp'=>'Ramp-up','working'=>'Working'][$row['set_type']]??$row['set_type']) ?></td><td>
          <?php if(count($segments)>1): ?><span class="font-semibold text-orange-300">Drop set · </span><?php foreach($segments as $index=>$segment): ?><?= $index?' → ':'' ?><?= e(format_number($segment['weight_kg'])) ?> kg × <?= (int)$segment['repetitions'] ?><?= (int)$segment['is_personal_record']===1?' 🏆':'' ?><?php endforeach; ?>
          <?php else: ?><?= e(format_number($row['weight_kg']??'—')) ?> kg × <?= e($row['repetitions']??'—') ?><?= (int)$row['is_personal_record']===1?' 🏆':'' ?><?php endif; ?>
        </td><td><?= $row['rest_seconds']!==null?e($row['rest_seconds']).' s':'—' ?></td><td class="text-zinc-400"><?= e($row['notes']??'—') ?></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
</div>
<form method="post" action="<?= e(route('workouts.destroy',['id'=>$session['id']])) ?>" class="mt-6" data-confirm="Permanently delete this workout and all its sets?"><?= csrf_field() ?><button class="btn-danger">Delete workout</button></form>
