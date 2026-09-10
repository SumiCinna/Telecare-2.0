<?php
date_default_timezone_set('Asia/Manila');
require_once 'includes/auth.php';

function generateTimeOptions(): array {
    $options=[];
    for($h=0;$h<24;$h++) foreach([0,30] as $m){
        $value=sprintf('%02d:%02d',$h,$m);
        $hour=$h%12?:12;
        $options[]=['val'=>$value,'lbl'=>sprintf('%d:%02d %s',$hour,$m,$h>=12?'PM':'AM')];
    }
    return $options;
}

function validSetting(int $value,array $allowed): bool {
    return in_array($value,$allowed,true);
}

function validateSlots(array $days,array $starts,array $ends): array {
    $slots=[];
    $allowed=['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    foreach($days as $i=>$day){
        $day=trim((string)$day);
        $start=trim((string)($starts[$i]??''));
        $end=trim((string)($ends[$i]??''));
        if($day===''&&$start===''&&$end==='') continue;
        if($day===''||$start===''||$end==='') return [false,[],'Please complete all schedule row fields.'];
        if(!in_array($day,$allowed,true)) return [false,[],'Invalid day selected.'];
        if(!preg_match('/^([01]\d|2[0-3]):(00|30)$/',$start)||!preg_match('/^([01]\d|2[0-3]):(00|30)$/',$end)) return [false,[],'Invalid time format.'];
        if($start>=$end) return [false,[],"End time must be after start time for {$day}."];
        foreach($slots as $old) if($old['day']===$day&&$start<$old['end']&&$old['start']<$end) return [false,[],"Schedule conflict on {$day}: {$start}–{$end} overlaps with {$old['start']}–{$old['end']}."];
        $slots[]=['day'=>$day,'start'=>$start,'end'=>$end];
    }
    return [true,$slots,''];
}

$days=['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
$timeOptions=generateTimeOptions();

if($_SERVER['REQUEST_METHOD']==='POST'){
    $action=$_POST['action']??'';

    if($action==='update_fee'){
        $raw=trim((string)($_POST['consultation_fee']??''));
        $fee=(float)$raw;
        if($raw===''||!is_numeric($raw)||$fee<0||$fee>100000){
            $_SESSION['toast_error']='Please enter a valid consultation fee between ₱0 and ₱100,000.';
        }else{
            $stmt=$conn->prepare('UPDATE doctors SET consultation_fee=? WHERE id=?');
            $stmt->bind_param('di',$fee,$doctor_id);
            $stmt->execute();
            $_SESSION['toast']='Consultation fee updated.';
        }
        header('Location: availability_settings.php');exit;
    }

    if($action==='update_schedule'){
        $schedDays=$_POST['sched_day']??[];
        $starts=$_POST['sched_start']??[];
        $ends=$_POST['sched_end']??[];
        $duration=(int)($_POST['consultation_duration']??30);
        $interval=(int)($_POST['appointment_interval']??5);
        $breakStart=trim((string)($_POST['break_start']??''));
        $breakEnd=trim((string)($_POST['break_end']??''));
        $types=[];

        foreach((array)($_POST['consultation_types']??[]) as $type){
            $type=trim((string)$type);
            if($type!==''&&!in_array($type,$types,true)) $types[]=$type;
        }

        if(!validSetting($duration,[15,30,45,60])||!validSetting($interval,[5,10,15,30,60])){
            $_SESSION['toast_error']='Choose a valid consultation duration and appointment interval.';
        }elseif(($breakStart!==''||$breakEnd!=='')&&($breakStart===''||$breakEnd===''||$breakStart>=$breakEnd)){
            $_SESSION['toast_error']='Enter a valid break time range.';
        }else{
            if(!$types)$types=['In-person'];
            [$ok,$slots,$message]=validateSlots($schedDays,$starts,$ends);

            if(!$ok){
                $_SESSION['toast_error']=$message;
            }else{
                try{
                    $conn->begin_transaction();

                    $del=$conn->prepare('DELETE FROM doctor_schedules WHERE doctor_id=?');
                    $del->bind_param('i',$doctor_id);
                    $del->execute();

                    if($slots){
                        $ins=$conn->prepare('INSERT INTO doctor_schedules (doctor_id,day_of_week,start_time,end_time) VALUES (?,?,?,?)');
                        foreach($slots as $slot){
                            $start=$slot['start'].':00';
                            $end=$slot['end'].':00';
                            $ins->bind_param('isss',$doctor_id,$slot['day'],$start,$end);
                            $ins->execute();
                        }
                    }

                    $typeValue=implode(',',$types);
                    $stmt=$conn->prepare("INSERT INTO doctor_schedule_settings (doctor_id,consultation_duration,appointment_interval,break_start,break_end,consultation_types) VALUES (?,?,?,NULLIF(?,''),NULLIF(?,''),?) ON DUPLICATE KEY UPDATE consultation_duration=VALUES(consultation_duration),appointment_interval=VALUES(appointment_interval),break_start=VALUES(break_start),break_end=VALUES(break_end),consultation_types=VALUES(consultation_types)");
                    $stmt->bind_param('iiisss',$doctor_id,$duration,$interval,$breakStart,$breakEnd,$typeValue);
                    $stmt->execute();

                    $conn->commit();
                    $_SESSION['toast']='Weekly schedule updated.';
                }catch(Throwable $e){
                    $conn->rollback();
                    $_SESSION['toast_error']='Failed to update schedule. Please try again.';
                }
            }
        }

        header('Location: availability_settings.php');exit;
    }
}

$schedules=[];
$stmt=$conn->prepare("SELECT day_of_week,TIME_FORMAT(start_time,'%H:%i') start_time,TIME_FORMAT(end_time,'%H:%i') end_time FROM doctor_schedules WHERE doctor_id=? ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),start_time");
$stmt->bind_param('i',$doctor_id);
$stmt->execute();
$result=$stmt->get_result();
while($row=$result->fetch_assoc())$schedules[]=$row;

$settings=['consultation_duration'=>30,'appointment_interval'=>5,'break_start'=>'','break_end'=>'','consultation_types'=>'In-person'];
$stmt=$conn->prepare('SELECT consultation_duration,appointment_interval,TIME_FORMAT(break_start,"%H:%i") break_start,TIME_FORMAT(break_end,"%H:%i") break_end,consultation_types FROM doctor_schedule_settings WHERE doctor_id=?');
$stmt->bind_param('i',$doctor_id);
$stmt->execute();
$saved=$stmt->get_result()->fetch_assoc();
if($saved)$settings=array_merge($settings,$saved);

$selectedTypes=array_filter(array_map('trim',explode(',',$settings['consultation_types'])));
$activeDays=array_unique(array_column($schedules,'day_of_week'));
$toast=$_SESSION['toast']??null;
$error=$_SESSION['toast_error']??null;
unset($_SESSION['toast'],$_SESSION['toast_error']);

$page_title='Schedule Settings — TELE-CARE';
$page_title_short='Schedule Settings';
$active_nav='appointments';
require_once 'includes/header.php';
?>

<style>
.settings-page{width:100%;max-width:1200px;margin:auto;padding:24px;box-sizing:border-box}
.settings-head{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:18px}
.settings-head h1{margin:0;color:var(--neutral-900);font-size:1.25rem}
.settings-head p{margin:4px 0 0;color:var(--neutral-500);font-size:.75rem}
.back-link{color:var(--primary);font-size:.75rem;font-weight:700;text-decoration:none}
.settings-grid{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:18px;align-items:start}
.card{background:var(--surface);border:1px solid var(--border-color);border-radius:var(--radius-md);box-shadow:var(--shadow-sm);padding:16px}
.section-title{margin:0 0 12px;color:var(--neutral-900);font-size:.9rem;font-weight:700}
.day-picker{display:flex;flex-wrap:wrap;gap:7px;margin-bottom:14px}
.day-toggle{border:1px solid var(--border-color);background:var(--surface);border-radius:999px;padding:7px 12px;color:var(--neutral-700);font-size:.7rem;font-weight:700;cursor:pointer}
.day-toggle.active{background:var(--primary);border-color:var(--primary);color:#fff}
.rows{display:flex;flex-direction:column;gap:8px}
.sched-row{display:grid;grid-template-columns:1fr 1fr 1fr 36px;gap:8px}
.input{width:100%;padding:10px;border:1px solid var(--border-color);border-radius:8px;background:var(--surface);color:var(--neutral-900);font:inherit;font-size:.75rem}
.input:focus{outline:none;border-color:var(--primary)}
.remove{border:0;border-radius:8px;background:var(--primary-soft);color:var(--primary);cursor:pointer}
.add{margin-top:12px;border:0;border-radius:8px;padding:9px 14px;background:var(--secondary-soft);color:var(--secondary-dark);font-size:.72rem;font-weight:700;cursor:pointer}
.field{margin-bottom:14px}
.label{display:block;margin-bottom:6px;color:var(--neutral-700);font-size:.7rem;font-weight:700}
.check{display:flex;align-items:center;gap:7px;margin-top:8px;color:var(--neutral-700);font-size:.72rem}
.check input{accent-color:var(--primary)}
.actions{display:flex;gap:8px;margin-top:18px}
.btn{flex:1;border:0;border-radius:8px;padding:11px;text-align:center;font-size:.72rem;font-weight:700;text-decoration:none;cursor:pointer}
.btn-primary{background:var(--primary);color:#fff}
.btn-light{background:var(--neutral-100);color:var(--neutral-700)}
.apply{margin-left:auto;border:0;background:none;color:var(--primary);font-size:.68rem;font-weight:700;cursor:pointer}
.toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%);padding:11px 18px;border-radius:30px;background:#198c83;color:#fff;font-size:.78rem;font-weight:600;z-index:500}
.toast.error{background:#bd1d2b}
@media(max-width:850px){.settings-grid{grid-template-columns:1fr}}
@media(max-width:600px){.settings-page{padding:12px}.settings-head{align-items:flex-start}.sched-row{grid-template-columns:1fr 1fr 36px}.sched-row select:first-child{grid-column:1/-1}.actions{flex-direction:column}}
</style>

<?php if($toast): ?><div class="toast"><?= htmlspecialchars($toast) ?></div><?php endif; ?>
<?php if($error): ?><div class="toast error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<main class="page settings-page">
    <div class="settings-head">
        <div>
            <h1>Schedule Settings</h1>
            <p>Set your working days, consultation hours, and consultation options.</p>
        </div>
        <a class="back-link" href="availability.php">← Back to Calendar</a>
    </div>

    <div class="settings-grid">
        <section class="card">
            <h2 class="section-title">Working Days</h2>

            <div class="day-picker">
                <?php foreach($days as $day): ?>
                    <button type="button" class="day-toggle <?= in_array($day,$activeDays,true)?'active':'' ?>" data-day="<?= $day ?>"><?= substr($day,0,3) ?></button>
                <?php endforeach; ?>
            </div>

            <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
                <h2 class="section-title" style="margin:0">Weekly Schedule</h2>
                <button type="button" class="apply" id="apply-monday">Apply Monday hours to all</button>
            </div>

            <form method="POST" id="schedule-form">
                <input type="hidden" name="action" value="update_schedule">

                <div class="rows" id="rows">
                    <?php if($schedules): foreach($schedules as $row): ?>
                        <div class="sched-row">
                            <select name="sched_day[]" class="input" required>
                                <?php foreach($days as $day): ?><option value="<?= $day ?>" <?= $row['day_of_week']===$day?'selected':'' ?>><?= $day ?></option><?php endforeach; ?>
                            </select>
                            <select name="sched_start[]" class="input" required>
                                <option value="">Start</option>
                                <?php foreach($timeOptions as $time): ?><option value="<?= $time['val'] ?>" <?= $row['start_time']===$time['val']?'selected':'' ?>><?= $time['lbl'] ?></option><?php endforeach; ?>
                            </select>
                            <select name="sched_end[]" class="input" required>
                                <option value="">End</option>
                                <?php foreach($timeOptions as $time): ?><option value="<?= $time['val'] ?>" <?= $row['end_time']===$time['val']?'selected':'' ?>><?= $time['lbl'] ?></option><?php endforeach; ?>
                            </select>
                            <button type="button" class="remove" onclick="removeRow(this)">×</button>
                        </div>
                    <?php endforeach; else: ?>
                        <div class="sched-row"><?= str_replace(['__DAY__','__START__','__END__'],['Monday','09:00','17:00'], '<select name="sched_day[]" class="input" required>'.implode('',array_map(fn($d)=>"<option value=\"$d\" ".($d==='__DAY__'?'selected':'').">$d</option>",$days)).'</select>'.'<select name="sched_start[]" class="input" required><option value="">Start</option>'.implode('',array_map(fn($t)=>"<option value=\"{$t['val']}\" ".($t['val']==='__START__'?'selected':'').">{$t['lbl']}</option>",$timeOptions)).'</select>'.'<select name="sched_end[]" class="input" required><option value="">End</option>'.implode('',array_map(fn($t)=>"<option value=\"{$t['val']}\" ".($t['val']==='__END__'?'selected':'').">{$t['lbl']}</option>",$timeOptions)).'</select><button type="button" class="remove" onclick="removeRow(this)">×</button>') ?></div>
                    <?php endif; ?>
                </div>

                <button type="button" class="add" onclick="addRow()">+ Add Row</button>

                <div class="card" style="margin-top:16px;box-shadow:none;background:var(--neutral-50)">
                    <h2 class="section-title">Consultation Settings</h2>

                    <div class="field">
                        <label class="label">Consultation Duration</label>
                        <select class="input" name="consultation_duration">
                            <?php foreach([15,30,45,60] as $value): ?><option value="<?= $value ?>" <?= (int)$settings['consultation_duration']===$value?'selected':'' ?>><?= $value ?> minutes</option><?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label class="label">Appointment Interval</label>
                        <select class="input" name="appointment_interval">
                            <?php foreach([5,10,15,30,60] as $value): ?><option value="<?= $value ?>" <?= (int)$settings['appointment_interval']===$value?'selected':'' ?>><?= $value ?> minutes</option><?php endforeach; ?>
                        </select>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                        <div class="field">
                            <label class="label">Break Start</label>
                            <select class="input" name="break_start">
                                <option value="">No break</option>
                                <?php foreach($timeOptions as $time): ?><option value="<?= $time['val'] ?>" <?= $settings['break_start']===$time['val']?'selected':'' ?>><?= $time['lbl'] ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label class="label">Break End</label>
                            <select class="input" name="break_end">
                                <option value="">No break</option>
                                <?php foreach($timeOptions as $time): ?><option value="<?= $time['val'] ?>" <?= $settings['break_end']===$time['val']?'selected':'' ?>><?= $time['lbl'] ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="label">Consultation Type</div>
                    <label class="check"><input type="checkbox" name="consultation_types[]" value="Teleconsult" <?= in_array('Teleconsult',$selectedTypes,true)?'checked':'' ?>> Teleconsultation</label>
                    <label class="check"><input type="checkbox" name="consultation_types[]" value="In-person" <?= in_array('In-person',$selectedTypes,true)?'checked':'' ?>> In-person</label>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">Save Schedule</button>
                    <a href="availability.php" class="btn btn-light">Cancel</a>
                </div>
            </form>
        </section>

        <aside class="card">
            <h2 class="section-title">Consultation Fee</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_fee">
                <div class="field">
                    <label class="label" for="consultation_fee">Fee Amount (PHP)</label>
                    <input id="consultation_fee" class="input" type="number" name="consultation_fee" min="0" max="100000" step="0.01" value="<?= htmlspecialchars((string)$doc['consultation_fee']) ?>" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%">Save Fee</button>
            </form>
        </aside>
    </div>
</main>

<script>
const days=<?= json_encode($days) ?>;
const times=<?= json_encode($timeOptions) ?>;

function select(name,label,options,value=''){
    return `<select name="${name}" class="input" required><option value="">${label}</option>${options.map(o=>`<option value="${o.val}" ${o.val===value?'selected':''}>${o.lbl}</option>`).join('')}</select>`;
}

function addRow(day='Monday',start='09:00',end='17:00'){
    const row=document.createElement('div');
    row.className='sched-row';
    row.innerHTML=select('sched_day[]','Day',days.map(d=>({val:d,lbl:d})),day)+select('sched_start[]','Start',times,start)+select('sched_end[]','End',times,end)+'<button type="button" class="remove" onclick="removeRow(this)">×</button>';
    document.getElementById('rows').appendChild(row);
    syncDays();
}

function removeRow(button){
    const rows=document.querySelectorAll('#rows .sched-row');
    if(rows.length===1){alert('At least one schedule row is required.');return}
    button.closest('.sched-row').remove();
    syncDays();
}

function syncDays(){
    const active=new Set([...document.querySelectorAll('select[name="sched_day[]"]')].map(s=>s.value));
    document.querySelectorAll('.day-toggle').forEach(b=>b.classList.toggle('active',active.has(b.dataset.day)));
}

document.querySelectorAll('.day-toggle').forEach(button=>{
    button.onclick=()=>{
        const day=button.dataset.day;
        const rows=[...document.querySelectorAll('#rows .sched-row')];
        const exists=rows.some(row=>row.querySelector('select[name="sched_day[]"]').value===day);
        if(exists){
            rows.filter(row=>row.querySelector('select[name="sched_day[]"]').value===day).forEach(row=>row.remove());
            if(!document.querySelector('#rows .sched-row')) addRow(day);
        }else addRow(day);
        syncDays();
    };
});

document.getElementById('apply-monday').onclick=()=>{
    const monday=[...document.querySelectorAll('#rows .sched-row')].find(row=>row.querySelector('select[name="sched_day[]"]').value==='Monday');
    if(!monday){alert('Add Monday hours first.');return}
    const start=monday.querySelector('select[name="sched_start[]"]').value;
    const end=monday.querySelector('select[name="sched_end[]"]').value;
    if(!start||!end){alert('Complete Monday hours first.');return}
    document.querySelectorAll('#rows .sched-row').forEach(row=>{
        if(row.querySelector('select[name="sched_day[]"]').value!=='Monday'){
            row.querySelector('select[name="sched_start[]"]').value=start;
            row.querySelector('select[name="sched_end[]"]').value=end;
        }
    });
};

document.getElementById('schedule-form').onsubmit=e=>{
    const rows=[...document.querySelectorAll('#rows .sched-row')];
    const used=[];
    for(const row of rows){
        const day=row.querySelector('select[name="sched_day[]"]').value;
        const start=row.querySelector('select[name="sched_start[]"]').value;
        const end=row.querySelector('select[name="sched_end[]"]').value;
        if(!day||!start||!end){alert('Please complete all schedule fields.');e.preventDefault();return}
        if(start>=end){alert(`End time must be after start time for ${day}.`);e.preventDefault();return}
        if(used.some(x=>x.day===day&&start<x.end&&x.start<end)){alert(`Schedule conflict on ${day}.`);e.preventDefault();return}
        used.push({day,start,end});
    }
};
</script>

<?php require_once 'includes/nav.php'; ?>
</body>
</html>
