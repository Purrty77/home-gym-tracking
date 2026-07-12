document.addEventListener('DOMContentLoaded', () => {
  document.querySelector('[data-back-button]')?.addEventListener('click',event=>{const fallback=event.currentTarget.dataset.fallback;if(document.referrer.startsWith(location.origin)&&history.length>1)history.back();else location.href=fallback;});
  document.querySelector('[data-theme-select]')?.addEventListener('change',event=>{document.documentElement.dataset.theme=event.target.value;document.querySelector('meta[name="theme-color"]')?.setAttribute('content',event.target.value==='light'?'#f4f4f5':'#020617');});
  const confirmModal=document.querySelector('#confirm-modal');
  const confirmMessage=document.querySelector('#confirm-modal-message');
  const confirmAccept=document.querySelector('#confirm-modal-accept');
  const confirmDismiss=document.querySelector('#confirm-modal-dismiss');
  let pendingForm=null;
  let previousFocus=null;
  const closeConfirm=()=>{confirmModal.classList.add('hidden');confirmModal.classList.remove('flex');document.body.classList.remove('overflow-hidden');pendingForm=null;previousFocus?.focus();};
  const openConfirm=form=>{pendingForm=form;previousFocus=document.activeElement;confirmMessage.textContent=form.dataset.confirm;confirmAccept.textContent=form.dataset.confirmButton||'Confirm';confirmAccept.disabled=false;confirmModal.classList.remove('hidden');confirmModal.classList.add('flex');document.body.classList.add('overflow-hidden');confirmDismiss.focus();};
  document.querySelectorAll('[data-confirm]').forEach(form=>form.addEventListener('submit',event=>{event.preventDefault();openConfirm(form);}));
  confirmDismiss?.addEventListener('click',closeConfirm);
  confirmAccept?.addEventListener('click',()=>{if(!pendingForm)return;const form=pendingForm;confirmAccept.disabled=true;confirmAccept.textContent='Please wait…';HTMLFormElement.prototype.submit.call(form);});
  confirmModal?.addEventListener('click',event=>{if(event.target===confirmModal)closeConfirm();});
  document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!confirmModal?.classList.contains('hidden'))closeConfirm();});
  document.querySelectorAll('[data-chart]').forEach(canvas => {
    if (!window.Chart) return;
    const data = JSON.parse(canvas.dataset.chart);
    const labels=data.labels??data.rows.map(r=>r.label);const datasets=data.datasets??[{label:data.label,data:data.rows.map(r=>r.value),borderColor:'#4f8a73',backgroundColor:'rgba(79,138,115,.14)',fill:true,tension:.3}];
    new Chart(canvas, {type: data.type, data: {labels,datasets}, options: {responsive:true, maintainAspectRatio:false, plugins:{legend:{labels:{color:'#d4d4d8'}}}, scales:{x:{ticks:{color:'#a1a1aa'},grid:{color:'#27272a'}},y:{ticks:{color:'#a1a1aa'},grid:{color:'#27272a'}}}}});
  });

  const exerciseLibrary=document.querySelector('[data-exercise-library]');
  if(exerciseLibrary){
    const accordions=[...exerciseLibrary.querySelectorAll('[data-muscle-group]')];
    const stored=sessionStorage.getItem('openMuscleGroup');
    if(stored){accordions.forEach(section=>section.open=section.dataset.muscleGroup===stored);}
    accordions.forEach(section=>section.addEventListener('toggle',()=>{if(section.open){sessionStorage.setItem('openMuscleGroup',section.dataset.muscleGroup);accordions.filter(other=>other!==section).forEach(other=>other.open=false);}}));
  }

  const form = document.querySelector('#workout-form');
  if (!form) return;
  const list = document.querySelector('#exercise-list');
  const exerciseTemplate = document.querySelector('#exercise-template');
  const setTemplate = document.querySelector('#set-template');
  const renumber = () => [...list.querySelectorAll('.exercise-block')].forEach((block, ei) => {
    block.querySelector('[data-name="exercise_id"]').name = `exercises[${ei}][exercise_id]`;
    [...block.querySelectorAll('.set-row')].forEach((row, si) => row.querySelectorAll('[data-set-name]').forEach(input => input.name=`exercises[${ei}][sets][${si}][${input.dataset.setName}]`));
  });
  const addSet = (block,data={}) => { const row=setTemplate.content.cloneNode(true); block.querySelector('.sets').append(row); const rest=block.querySelector('select option:checked')?.dataset.rest||''; const rows=block.querySelectorAll('.set-row'),last=rows[rows.length-1];last.querySelectorAll('[data-set-name]').forEach(input=>{input.value=data[input.dataset.setName]??(input.dataset.setName==='rest_seconds'?rest:input.value);});renumber(); };
  const addExercise = (data={}) => { const node=exerciseTemplate.content.cloneNode(true); list.append(node); const block=list.lastElementChild,select=block.querySelector('select'),target=block.querySelector('.exercise-target');select.value=data.exercise_id??'';if(data.target){target.textContent=`Target: ${data.target}`;target.classList.remove('hidden');}(data.sets?.length?data.sets:[{}]).forEach(set=>addSet(block,set));block.querySelector('.add-set').onclick=()=>addSet(block);block.querySelector('.remove-exercise').onclick=()=>{block.remove();renumber();};select.onchange=()=>{block.querySelectorAll('[data-set-name="rest_seconds"]').forEach(i=>{if(!i.value)i.value=select.querySelector('option:checked')?.dataset.rest||'';});}; };
  document.querySelector('#add-exercise').onclick=addExercise;
  let timerInterval;
  const timerBox=document.querySelector('#rest-timer'), timerValue=document.querySelector('#timer-value');
  const stopTimer=()=>{clearInterval(timerInterval);timerBox.classList.add('hidden');};
  document.querySelector('#stop-timer').onclick=stopTimer;
  list.addEventListener('click', e=>{
    if(e.target.matches('.remove-set')){e.target.closest('.set-row').remove();renumber();}
    if(e.target.matches('.start-timer')){stopTimer();let remaining=parseInt(e.target.closest('.set-row').querySelector('[data-set-name="rest_seconds"]').value||'90',10);timerBox.classList.remove('hidden');const render=()=>timerValue.textContent=`${String(Math.floor(remaining/60)).padStart(2,'0')}:${String(remaining%60).padStart(2,'0')}`;render();timerInterval=setInterval(()=>{remaining--;render();if(remaining<=0){clearInterval(timerInterval);timerBox.classList.add('animate-pulse');setTimeout(()=>timerBox.classList.remove('animate-pulse'),3000);}},1000);}
  });
  const initial=JSON.parse(form.dataset.initial||'[]');initial.length?initial.forEach(addExercise):addExercise();
  const templateSelect=document.querySelector('#workout_template');if(templateSelect)templateSelect.onchange=()=>{const date=document.querySelector('#performed_at').value.slice(0,10);location.href=`/workouts/custom/new?${templateSelect.value?'template='+encodeURIComponent(templateSelect.value)+'&':''}date=${encodeURIComponent(date)}`;};
});
