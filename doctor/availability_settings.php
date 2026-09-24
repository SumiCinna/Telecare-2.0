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

function fmt12(string $hm): string {
    [$h,$m]=explode(':',$hm);
    $h=(int)$h;
    $ap=$h>=12?'PM':'AM';
    $hr=$h%12?:12;
    return $hr.':'.str_pad((string)$m,2,'0',STR_PAD_LEFT).' '.$ap;
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

        [$ok,$slots,$message]=validateSlots($schedDays,$starts,$ends);

        if(!$ok){
            $_SESSION['toast_error']=$message;
        }elseif(empty($slots)){
            $_SESSION['toast_error']='Please add at least one schedule row before submitting.';
        }else{
            $slotsJson=json_encode($slots);

            $existing=$conn->prepare("SELECT id FROM doctor_schedule_requests WHERE doctor_id=? AND status='Pending' LIMIT 1");
            $existing->bind_param('i',$doctor_id);
            $existing->execute();
            $existingRow=$existing->get_result()->fetch_assoc();
            $existing->close();

            if($existingRow){
                $upd=$conn->prepare("UPDATE doctor_schedule_requests SET slots_json=?, submitted_at=NOW(), status='Pending', reviewed_at=NULL, reviewed_by=NULL, staff_note=NULL, doctor_seen=0 WHERE id=?");
                $upd->bind_param('si',$slotsJson,$existingRow['id']);
                $upd->execute();
            }else{
                $ins=$conn->prepare("INSERT INTO doctor_schedule_requests (doctor_id, slots_json, status) VALUES (?, ?, 'Pending')");
                $ins->bind_param('is',$doctor_id,$slotsJson);
                $ins->execute();
            }

            $_SESSION['toast']='Schedule change submitted. It will apply once staff approves it.';
        }

        header('Location: availability_settings.php');exit;
    }

    if($action==='cancel_schedule_request'){
        $reqId=(int)($_POST['request_id']??0);
        $del=$conn->prepare("DELETE FROM doctor_schedule_requests WHERE id=? AND doctor_id=? AND status='Pending'");
        $del->bind_param('ii',$reqId,$doctor_id);
        $del->execute();
        $_SESSION['toast']=$del->affected_rows?'Pending schedule request cancelled.':'Nothing to cancel.';
        header('Location: availability_settings.php');exit;
    }
}

// Current LIVE schedule (what's actually used for booking)
$schedules=[];
$stmt=$conn->prepare("SELECT day_of_week,TIME_FORMAT(start_time,'%H:%i') start_time,TIME_FORMAT(end_time,'%H:%i') end_time FROM doctor_schedules WHERE doctor_id=? ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),start_time");
$stmt->bind_param('i',$doctor_id);
$stmt->execute();
$result=$stmt->get_result();
while($row=$result->fetch_assoc())$schedules[]=$row;

// Any pending request awaiting staff review
$pendingRequest=null;
$pstmt=$conn->prepare("SELECT * FROM doctor_schedule_requests WHERE doctor_id=? AND status='Pending' ORDER BY submitted_at DESC LIMIT 1");
$pstmt->bind_param('i',$doctor_id);
$pstmt->execute();
$pendingRequest=$pstmt->get_result()->fetch_assoc();
$pstmt->close();
$pendingSlots=$pendingRequest?json_decode($pendingRequest['slots_json'],true):null;

// Most recent resolved request the doctor hasn't seen yet -> surface as a banner once
$resolvedNotice=null;
$rstmt=$conn->prepare("SELECT * FROM doctor_schedule_requests WHERE doctor_id=? AND status IN ('Approved','Rejected') AND doctor_seen=0 ORDER BY reviewed_at DESC LIMIT 1");
$rstmt->bind_param('i',$doctor_id);
$rstmt->execute();
$resolvedNotice=$rstmt->get_result()->fetch_assoc();
$rstmt->close();
if($resolvedNotice){
    $mark=$conn->prepare("UPDATE doctor_schedule_requests SET doctor_seen=1 WHERE id=?");
    $mark->bind_param('i',$resolvedNotice['id']);
    $mark->execute();
}

// Prefill the form with the pending request if one exists, otherwise the live schedule
$formSlots=$pendingSlots!==null?$pendingSlots:array_map(fn($s)=>['day'=>$s['day_of_week'],'start'=>$s['start_time'],'end'=>$s['end_time']],$schedules);
$activeDays=array_unique(array_column($formSlots,'day'));

$toast=$_SESSION['toast']??null;
$error=$_SESSION['toast_error']??null;
unset($_SESSION['toast'],$_SESSION['toast_error']);

$page_title='Schedule Settings — TELE-CARE';
$page_title_short='Schedule Settings';
$active_nav='schedule';
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
.rows{display:flex;flex-direction:column;gap:4px}
.sched-item{display:flex;flex-direction:column;gap:4px}
.sched-row{display:grid;grid-template-columns:1fr 1fr 1fr 36px;gap:8px}
.input{width:100%;padding:10px;border:1.5px solid var(--border-color);border-radius:8px;background:var(--surface);color:var(--neutral-900);font:inherit;font-size:.75rem;transition:border-color .15s}
.input:focus{outline:none;border-color:var(--primary)}
.sched-item.invalid .input{border-color:#bd1d2b}
.row-error{display:none;align-items:center;gap:5px;color:#bd1d2b;font-size:.66rem;font-weight:600;padding:0 2px}
.sched-item.invalid .row-error{display:flex}
.remove{border:0;border-radius:8px;background:var(--primary-soft);color:var(--primary);cursor:pointer}
.add{margin-top:10px;border:0;border-radius:8px;padding:9px 14px;background:var(--secondary-soft);color:var(--secondary-dark);font-size:.72rem;font-weight:700;cursor:pointer}
.field{margin-bottom:14px}
.label{display:block;margin-bottom:6px;color:var(--neutral-700);font-size:.7rem;font-weight:700}
.actions{display:flex;gap:8px;margin-top:18px}
.btn{flex:1;border:0;border-radius:8px;padding:11px;text-align:center;font-size:.72rem;font-weight:700;text-decoration:none;cursor:pointer}
.btn-primary{background:var(--primary);color:#fff}
.btn-primary:disabled{background:var(--neutral-300,#c7cbd1);color:#fff;cursor:not-allowed}
.btn-light{background:var(--neutral-100);color:var(--neutral-700)}
.apply{margin-left:auto;border:0;background:none;color:var(--primary);font-size:.68rem;font-weight:700;cursor:pointer}
.toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%);padding:11px 18px;border-radius:30px;background:#198c83;color:#fff;font-size:.78rem;font-weight:600;z-index:500;max-width:90vw;text-align:center}
.toast.error{background:#bd1d2b}
.pending-banner{display:flex;flex-direction:column;gap:10px;padding:14px 16px;border-radius:10px;background:#fff7ed;border:1px solid #fed7aa;margin-bottom:16px}
.pending-banner-head{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
.pending-banner-title{font-size:.78rem;font-weight:800;color:#c2410c;display:flex;align-items:center;gap:6px}
.pending-banner-sub{font-size:.68rem;color:#9a3412}
.pending-list{display:flex;flex-direction:column;gap:6px}
.pending-list div{display:flex;justify-content:space-between;padding:8px 10px;border-radius:7px;background:#fff;border:1px solid #fed7aa;font-size:.72rem}
.pending-list span:first-child{font-weight:700;color:var(--neutral-900)}
.pending-list span:last-child{color:#c2410c;font-weight:700}
.cancel-req-btn{align-self:flex-start;border:0;background:none;color:#9a3412;font-size:.68rem;font-weight:700;text-decoration:underline;cursor:pointer;padding:0}
.resolved-banner{padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:.76rem;font-weight:600}
.resolved-banner.approved{background:#e9fbf4;border:1px solid #bdebd9;color:#087b53}
.resolved-banner.rejected{background:rgba(189,29,43,.08);border:1px solid rgba(189,29,43,.2);color:#bd1d2b}
.live-list{display:flex;flex-direction:column;gap:8px}
.live-list div{display:flex;justify-content:space-between;padding:9px 11px;border-radius:8px;background:var(--neutral-50,#f8fafc)}
.live-list span:last-child{color:var(--neutral-700);font-weight:600;font-size:.78rem}
@media(max-width:850px){.settings-grid{grid-template-columns:1fr}}
@media(max-width:600px){.settings-page{padding:12px}.settings-head{align-items:flex-start}.sched-row{grid-template-columns:1fr 1fr 36px}.sched-row select:first-child{grid-column:1/-1}.actions{flex-direction:column}}
</style>

<?php if($toast): ?><div class="toast"><?= htmlspecialchars($toast) ?></div><?php endif; ?>
<?php if($error): ?><div class="toast error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<main class="page settings-page">
    <div class="settings-head">
        <div>
            <h1>Schedule Settings</h1>
            <p>Set your working days and consultation hours. Changes need staff approval before they go live.</p>
        </div>
        <a class="back-link" href="availability.php">← Back to Calendar</a>
    </div>

    <?php if($resolvedNotice): ?>
        <div class="resolved-banner <?= $resolvedNotice['status']==='Approved'?'approved':'rejected' ?>">
            <?php if($resolvedNotice['status']==='Approved'): ?>
                ✓ Your last schedule change was approved and is now live.
            <?php else: ?>
                ✕ Your last schedule change was rejected<?= !empty($resolvedNotice['staff_note'])?': '.htmlspecialchars($resolvedNotice['staff_note']):'.' ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if($pendingRequest): ?>
        <div class="pending-banner">
            <div class="pending-banner-head">
                <div class="pending-banner-title">⏳ Awaiting staff approval</div>
                <div class="pending-banner-sub">Submitted <?= date('M d, Y g:i A',strtotime($pendingRequest['submitted_at'])) ?></div>
            </div>
            <div class="pending-list">
                <?php foreach($pendingSlots as $slot): ?>
                    <div><span><?= htmlspecialchars($slot['day']) ?></span><span><?= fmt12($slot['start']) ?> – <?= fmt12($slot['end']) ?></span></div>
                <?php endforeach; ?>
            </div>
            <form method="POST" onsubmit="return confirm('Cancel this pending schedule request?');">
                <input type="hidden" name="action" value="cancel_schedule_request">
                <input type="hidden" name="request_id" value="<?= (int)$pendingRequest['id'] ?>">
                <button type="submit" class="cancel-req-btn">Cancel this request</button>
            </form>
        </div>
    <?php endif; ?>

    <div class="settings-grid">
        <section class="card">
            <h2 class="section-title"><?= $pendingRequest?'Requested Working Days':'Working Days' ?></h2>

            <div class="day-picker">
                <?php foreach($days as $day): ?>
                    <button type="button" class="day-toggle <?= in_array($day,$activeDays,true)?'active':'' ?>" data-day="<?= $day ?>"><?= substr($day,0,3) ?></button>
                <?php endforeach; ?>
            </div>

            <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
                <h2 class="section-title" style="margin:0"><?= $pendingRequest?'Requested Weekly Schedule':'Weekly Schedule' ?></h2>
                <button type="button" class="apply" id="apply-monday">Apply Monday hours to all</button>
            </div>

            <form method="POST" id="schedule-form">
                <input type="hidden" name="action" value="update_schedule">

                <div class="rows" id="rows">
                    <?php if($formSlots): foreach($formSlots as $row): ?>
                        <div class="sched-item">
                            <div class="sched-row">
                                <select name="sched_day[]" class="input" required>
                                    <?php foreach($days as $day): ?><option value="<?= $day ?>" <?= $row['day']===$day?'selected':'' ?>><?= $day ?></option><?php endforeach; ?>
                                </select>
                                <select name="sched_start[]" class="input" required>
                                    <option value="">Start</option>
                                    <?php foreach($timeOptions as $time): ?><option value="<?= $time['val'] ?>" <?= $row['start']===$time['val']?'selected':'' ?>><?= $time['lbl'] ?></option><?php endforeach; ?>
                                </select>
                                <select name="sched_end[]" class="input" required>
                                    <option value="">End</option>
                                    <?php foreach($timeOptions as $time): ?><option value="<?= $time['val'] ?>" <?= $row['end']===$time['val']?'selected':'' ?>><?= $time['lbl'] ?></option><?php endforeach; ?>
                                </select>
                                <button type="button" class="remove" onclick="removeRow(this)">×</button>
                            </div>
                            <div class="row-error"></div>
                        </div>
                    <?php endforeach; else: ?>
                        <div class="sched-item">
                            <div class="sched-row"><?= str_replace(['__DAY__','__START__','__END__'],['Monday','09:00','17:00'], '<select name="sched_day[]" class="input" required>'.implode('',array_map(fn($d)=>"<option value=\"$d\" ".($d==='__DAY__'?'selected':'').">$d</option>",$days)).'</select>'.'<select name="sched_start[]" class="input" required><option value="">Start</option>'.implode('',array_map(fn($t)=>"<option value=\"{$t['val']}\" ".($t['val']==='__START__'?'selected':'').">{$t['lbl']}</option>",$timeOptions)).'</select>'.'<select name="sched_end[]" class="input" required><option value="">End</option>'.implode('',array_map(fn($t)=>"<option value=\"{$t['val']}\" ".($t['val']==='__END__'?'selected':'').">{$t['lbl']}</option>",$timeOptions)).'</select><button type="button" class="remove" onclick="removeRow(this)">×</button>') ?></div>
                            <div class="row-error"></div>
                        </div>
                    <?php endif; ?>
                </div>

                <button type="button" class="add" onclick="addRow()">+ Add Row</button>

                <div class="actions">
                    <button type="submit" class="btn btn-primary" id="save-schedule-btn"><?= $pendingRequest?'Resubmit for Approval':'Submit for Approval' ?></button>
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

    <section class="card" style="margin-top:18px">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:12px">
            <h2 class="section-title" style="margin:0">Current Live Schedule</h2>
            <span style="font-size:.65rem;font-weight:700;color:var(--neutral-500)">What patients currently see &amp; can book</span>
        </div>
        <?php if(!$schedules): ?>
            <div style="font-size:.78rem;color:var(--neutral-500)">No live schedule set yet.</div>
        <?php else: ?>
            <div class="live-list">
                <?php foreach($schedules as $s): ?>
                    <div><span style="font-weight:700;font-size:.8rem"><?= htmlspecialchars($s['day_of_week']) ?></span><span><?= fmt12($s['start_time']) ?> – <?= fmt12($s['end_time']) ?></span></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<script>
const days=<?= json_encode($days) ?>;
const times=<?= json_encode($timeOptions) ?>;

function select(name,label,options,value=''){
    return `<select name="${name}" class="input" required><option value="">${label}</option>${options.map(o=>`<option value="${o.val}" ${o.val===value?'selected':''}>${o.lbl}</option>`).join('')}</select>`;
}

function bindRowEvents(item){
    item.querySelectorAll('select').forEach(sel=>sel.addEventListener('change',validateAll));
}

function addRow(day='Monday',start='09:00',end='17:00'){
    const item=document.createElement('div');
    item.className='sched-item';
    item.innerHTML=`<div class="sched-row">${select('sched_day[]','Day',days.map(d=>({val:d,lbl:d})),day)}${select('sched_start[]','Start',times,start)}${select('sched_end[]','End',times,end)}<button type="button" class="remove" onclick="removeRow(this)">×</button></div><div class="row-error"></div>`;
    document.getElementById('rows').appendChild(item);
    bindRowEvents(item);
    syncDays();
    validateAll();
}

function removeRow(button){
    const items=document.querySelectorAll('#rows .sched-item');
    if(items.length===1){
        const item=button.closest('.sched-item');
        item.classList.add('invalid');
        item.querySelector('.row-error').textContent='At least one schedule row is required.';
        return;
    }
    button.closest('.sched-item').remove();
    syncDays();
    validateAll();
}

function syncDays(){
    const active=new Set([...document.querySelectorAll('select[name="sched_day[]"]')].map(s=>s.value));
    document.querySelectorAll('.day-toggle').forEach(b=>b.classList.toggle('active',active.has(b.dataset.day)));
}

function validateAll(){
    const items=[...document.querySelectorAll('#rows .sched-item')];
    const used=[];
    let allValid=true;

    items.forEach(item=>{
        const day=item.querySelector('select[name="sched_day[]"]').value;
        const start=item.querySelector('select[name="sched_start[]"]').value;
        const end=item.querySelector('select[name="sched_end[]"]').value;
        const errorEl=item.querySelector('.row-error');
        let message='';

        if(!day||!start||!end){
            message='Please complete all fields for this row.';
        }else if(start>=end){
            message=`End time must be after start time for ${day}. Overnight shifts aren't supported yet.`;
        }else{
            const conflict=used.find(x=>x.day===day&&start<x.end&&x.start<end);
            if(conflict) message=`Overlaps with ${conflict.day} ${formatTime(conflict.start)}–${formatTime(conflict.end)}.`;
        }

        if(message){
            item.classList.add('invalid');
            errorEl.textContent=message;
            allValid=false;
        }else{
            item.classList.remove('invalid');
            errorEl.textContent='';
            used.push({day,start,end});
        }
    });

    document.getElementById('save-schedule-btn').disabled=!allValid;
    return allValid;
}

function formatTime(v){
    const match=times.find(t=>t.val===v);
    return match?match.lbl:v;
}

document.querySelectorAll('.day-toggle').forEach(button=>{
    button.onclick=()=>{
        const day=button.dataset.day;
        const items=[...document.querySelectorAll('#rows .sched-item')];
        const exists=items.some(item=>item.querySelector('select[name="sched_day[]"]').value===day);
        if(exists){
            items.filter(item=>item.querySelector('select[name="sched_day[]"]').value===day).forEach(item=>item.remove());
            if(!document.querySelector('#rows .sched-item')) addRow(day);
        }else addRow(day);
        syncDays();
        validateAll();
    };
});

document.getElementById('apply-monday').onclick=()=>{
    const monday=[...document.querySelectorAll('#rows .sched-item')].find(item=>item.querySelector('select[name="sched_day[]"]').value==='Monday');
    if(!monday){
        alert('Add Monday hours first.');
        return;
    }
    const start=monday.querySelector('select[name="sched_start[]"]').value;
    const end=monday.querySelector('select[name="sched_end[]"]').value;
    if(!start||!end){
        alert('Complete Monday hours first.');
        return;
    }
    document.querySelectorAll('#rows .sched-item').forEach(item=>{
        if(item.querySelector('select[name="sched_day[]"]').value!=='Monday'){
            item.querySelector('select[name="sched_start[]"]').value=start;
            item.querySelector('select[name="sched_end[]"]').value=end;
        }
    });
    validateAll();
};

document.getElementById('schedule-form').onsubmit=e=>{
    if(!validateAll()) e.preventDefault();
};

document.querySelectorAll('#rows .sched-item').forEach(bindRowEvents);
validateAll();
</script>

<?php require_once 'includes/nav.php'; ?>
</body>
</html>