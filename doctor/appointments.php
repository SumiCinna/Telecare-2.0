<?php
// doctor/appointments.php
date_default_timezone_set('Asia/Manila');
require_once 'includes/auth.php';

$now = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');

$autoComplete = $conn->prepare("UPDATE appointments SET status='Completed' WHERE doctor_id=? AND status IN ('Confirmed','Pending') AND DATE_ADD(CONCAT(appointment_date,' ',appointment_time), INTERVAL 1 HOUR) <= ?");
$autoComplete->bind_param('is', $doctor_id, $now);
$autoComplete->execute();
$autoComplete->close();

$search=trim($_GET['search']??'');
$date=$_GET['date']??'';
$status=$_GET['status']??'';
$view=($_GET['view']??'upcoming')==='past'?'past':'upcoming';
$per_page=10;
$page=max(1,(int)($_GET['page']??1));

$where=" WHERE a.doctor_id=?";
$params=[$doctor_id];$types='i';
if($view==='past'){
    $where.=" AND DATE_ADD(CONCAT(a.appointment_date,' ',a.appointment_time), INTERVAL 1 HOUR) <= ?";
    $params[]=$now;$types.='s';
}else{
    $where.=" AND DATE_ADD(CONCAT(a.appointment_date,' ',a.appointment_time), INTERVAL 1 HOUR) > ?";
    $params[]=$now;$types.='s';
}
if($search!==''){$where.=" AND p.full_name LIKE ?";$params[]="%$search%";$types.='s';}
if($date!==''){$where.=" AND a.appointment_date=?";$params[]=$date;$types.='s';}
if($status!==''){$where.=" AND a.status=?";$params[]=$status;$types.='s';}

$countSql="SELECT COUNT(*) total FROM appointments a JOIN patients p ON p.id=a.patient_id".$where;
$countStmt=$conn->prepare($countSql);$countStmt->bind_param($types,...$params);$countStmt->execute();
$total=(int)$countStmt->get_result()->fetch_assoc()['total'];$countStmt->close();
$total_pages=max(1,(int)ceil($total/$per_page));
if($page>$total_pages)$page=$total_pages;
$offset=($page-1)*$per_page;

$orderSql=$view==='past'?" ORDER BY a.appointment_date DESC,a.appointment_time DESC":" ORDER BY a.appointment_date,a.appointment_time";
$sql="SELECT a.*,p.full_name patient_name,p.profile_photo patient_photo FROM appointments a JOIN patients p ON p.id=a.patient_id".$where.$orderSql." LIMIT ? OFFSET ?";
$dataTypes=$types.'ii';$dataParams=array_merge($params,[$per_page,$offset]);
$stmt=$conn->prepare($sql);$stmt->bind_param($dataTypes,...$dataParams);$stmt->execute();$appointments=$stmt->get_result();
$page_title=($view==='past'?'Past':'Upcoming').' Appointments — TELE-CARE';$page_title_short='Appointments';$active_nav='appointments';require_once 'includes/header.php';
function apptType($v){return stripos((string)$v,'tele')!==false?'Teleconsultation':($v?:'In-person');}
function apptPageUrl($p){$q=$_GET;$q['page']=$p;return '?'.http_build_query($q);}
?>
<style>
.appts-page{width:100%;max-width:1450px;padding:26px;box-sizing:border-box}.appts-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap}.appts-head h1{margin:0;color:var(--neutral-900);font-size:1.7rem}.appts-head p{margin:5px 0 20px;color:var(--neutral-500);font-size:.78rem}
.view-tabs{display:flex;gap:6px;margin-bottom:14px}.view-tab{padding:8px 16px;border-radius:8px;border:1px solid var(--border-color);background:#fff;color:var(--neutral-700);font-size:.75rem;font-weight:700;text-decoration:none}.view-tab.active{background:var(--primary);color:#fff;border-color:var(--primary)}.view-tab:hover{text-decoration:none}
.pagination{display:flex;align-items:center;gap:6px;justify-content:center;margin-top:22px;flex-wrap:wrap}.pagination a,.pagination span{min-width:34px;height:34px;padding:0 6px;display:flex;align-items:center;justify-content:center;border-radius:7px;border:1px solid var(--border-color);background:#fff;color:var(--neutral-700);font-size:.72rem;font-weight:700;text-decoration:none}.pagination a:hover{background:#f5f6fa;text-decoration:none}.pagination .current{background:var(--primary);color:#fff;border-color:var(--primary)}.pagination .disabled{opacity:.4;pointer-events:none}.pagination span:not(.current){border:none;background:none;color:var(--neutral-500)}
.filters{display:grid;grid-template-columns:minmax(220px,1fr) 150px 150px;gap:10px;padding:12px;margin-bottom:20px;background:#fff;border:1px solid var(--border-color);border-radius:12px}.control{width:100%;height:40px;padding:0 12px;border:1px solid var(--border-color);border-radius:8px;background:#fff;color:var(--neutral-700);font:inherit;font-size:.72rem}.cards{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.appt-card{position:relative;padding:16px;background:#fff;border:1px solid var(--border-color);border-left:3px solid var(--secondary,#0f8f83);border-radius:10px;box-shadow:var(--shadow-sm)}.patient{display:flex;align-items:center;gap:10px}.avatar{width:42px;height:42px;border-radius:50%;overflow:hidden;background:var(--neutral-100);display:grid;place-items:center;font-weight:700}.avatar img{width:100%;height:100%;object-fit:cover}.patient-name{font-size:.9rem;font-weight:800;color:var(--neutral-900)}.patient-id{font-size:.61rem;color:var(--neutral-500);margin-top:2px}.badge{position:absolute;right:14px;top:14px;padding:4px 9px;border-radius:999px;background:#e9fbf4;color:#087b53;border:1px solid #bdebd9;font-size:.58rem;font-weight:700}.info{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:14px 0;padding:11px;background:#f3f5ff;border-radius:8px}.info-label{font-size:.57rem;font-weight:800;text-transform:uppercase;color:var(--neutral-600)}.info-value{margin-top:3px;font-size:.69rem;color:var(--neutral-900);font-weight:600}.reason-label{font-size:.57rem;font-weight:800;text-transform:uppercase;color:var(--neutral-600)}.reason{min-height:34px;margin:4px 0 14px;color:var(--neutral-700);font-size:.69rem;line-height:1.45}.actions{display:grid;grid-template-columns:1fr 1fr;gap:8px}.btn{display:flex;align-items:center;justify-content:center;min-height:36px;border-radius:7px;font-size:.66rem;font-weight:800;text-decoration:none}.btn-light{border:1px solid var(--border-color);color:var(--neutral-800);background:#fff}.btn-primary{background:var(--primary);color:#fff}.btn-disabled{background:#e4a1a5;color:#fff;pointer-events:none}.empty{grid-column:1/-1;padding:50px;text-align:center;background:#fff;border:1px solid var(--border-color);border-radius:10px;color:var(--neutral-500)}
.instant-call-btn{display:flex;align-items:center;gap:8px;height:42px;padding:0 18px;border:none;border-radius:9px;background:var(--secondary,#0f8f83);color:#fff;font-size:.78rem;font-weight:800;cursor:pointer;white-space:nowrap;box-shadow:var(--shadow-sm);flex-shrink:0}
.instant-call-btn:hover{filter:brightness(1.05)}
.instant-call-btn svg{width:16px;height:16px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.ic-overlay{position:fixed;inset:0;background:rgba(20,22,30,.5);display:none;align-items:center;justify-content:center;z-index:200;padding:16px}
.ic-overlay.open{display:flex}
.ic-modal{width:100%;max-width:420px;max-height:80vh;background:#fff;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.3);display:flex;flex-direction:column;overflow:hidden}
.ic-modal-head{padding:16px 18px;border-bottom:1px solid var(--border-color);display:flex;align-items:center;justify-content:space-between}
.ic-modal-head h2{margin:0;font-size:1rem;color:var(--neutral-900)}
.ic-modal-head button{background:none;border:none;font-size:1.3rem;line-height:1;color:var(--neutral-500);cursor:pointer;padding:4px}
.ic-search{padding:12px 18px}
.ic-search input{width:100%;height:40px;padding:0 12px;border:1px solid var(--border-color);border-radius:8px;font:inherit;font-size:.78rem;box-sizing:border-box}
.ic-list{flex:1;overflow-y:auto;padding:0 10px 10px}
.ic-row{display:flex;align-items:center;gap:10px;padding:10px 8px;border-radius:9px;cursor:pointer}
.ic-row:hover{background:#f5f6fa}
.ic-row .avatar{width:36px;height:36px;font-size:.7rem}
.ic-row .patient-name{font-size:.8rem}
.ic-row .call-icon{margin-left:auto;width:32px;height:32px;border-radius:50%;background:var(--secondary,#0f8f83);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.ic-row .call-icon svg{width:14px;height:14px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.ic-empty,.ic-loading{padding:30px 10px;text-align:center;color:var(--neutral-500);font-size:.75rem}
.ic-row.disabled{opacity:.5;pointer-events:none}
@media(max-width:950px){.cards{grid-template-columns:1fr}.filters{grid-template-columns:1fr 1fr}}@media(max-width:600px){.appts-page{padding:15px}.filters{grid-template-columns:1fr}.info{grid-template-columns:1fr}.actions{grid-template-columns:1fr}.appts-head{flex-direction:column}.instant-call-btn{width:100%;justify-content:center}.pagination a,.pagination span{min-width:30px;height:30px;font-size:.68rem}}
</style>
<main class="page appts-page">
  <div class="appts-head">
    <div><h1><?= $view==='past'?'Past Appointments':'Upcoming Appointments' ?></h1><p><?= $view==='past'?'Review your past patient consultations.':'View and manage your upcoming patient appointments.' ?></p></div>
    <button type="button" class="instant-call-btn" onclick="openInstantCall()">
      <svg viewBox="0 0 24 24"><path d="M15 10l4.553-2.276A1 1 0 0121 8.723v6.554a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg>
      Instant Call
    </button>
  </div>
  <div class="view-tabs">
    <a class="view-tab <?= $view==='upcoming'?'active':'' ?>" href="?<?= htmlspecialchars(http_build_query(array_merge($_GET,['view'=>'upcoming','page'=>1]))) ?>">Upcoming</a>
    <a class="view-tab <?= $view==='past'?'active':'' ?>" href="?<?= htmlspecialchars(http_build_query(array_merge($_GET,['view'=>'past','page'=>1]))) ?>">Past</a>
  </div>
  <form class="filters" method="get">
    <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
    <input class="control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search patient">
    <input class="control" type="date" name="date" value="<?= htmlspecialchars($date) ?>">
    <select class="control" name="status" onchange="this.form.submit()"><option value="">All Statuses</option><?php foreach(['Pending', 'Confirmed','Completed'] as $s): ?><option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select>
  </form>
  <section class="cards">
    <?php if($appointments->num_rows): while($a=$appointments->fetch_assoc()): $tele=stripos((string)$a['type'],'tele')!==false;
    $a_ts = strtotime($a['appointment_date'].' '.$a['appointment_time']);
    $now_ts = time();
    $call_window_open = ($now_ts >= $a_ts - 900 && $now_ts <= $a_ts + 3600);
    $call_expired = $now_ts > $a_ts + 3600;
?>
    <article class="appt-card">
      <span class="badge"><?= htmlspecialchars($a['status']) ?></span>
      <div class="patient"><div class="avatar"><?php if(!empty($a['patient_photo'])): ?><img src="../<?= htmlspecialchars($a['patient_photo']) ?>" alt=""><?php else: ?><?= htmlspecialchars(strtoupper(substr($a['patient_name'],0,2))) ?><?php endif; ?></div><div><div class="patient-name"><?= htmlspecialchars($a['patient_name']) ?></div><div class="patient-id">ID: #PT-<?= str_pad((string)$a['patient_id'],4,'0',STR_PAD_LEFT) ?></div></div></div>
      <div class="info"><div><div class="info-label">Date &amp; Time</div><div class="info-value"><?= date('M d, Y',strtotime($a['appointment_date'])) ?><br><?= date('h:i A',strtotime($a['appointment_time'])) ?></div></div><div><div class="info-label">Type</div><div class="info-value"><?= htmlspecialchars(apptType($a['type'])) ?><?= !$tele&&!empty($a['department'])?'<br>'.htmlspecialchars($a['department']):'' ?></div></div></div>
      <div class="reason-label">Reason for Consultation</div><div class="reason"><?= htmlspecialchars($a['reason']?:'No reason provided.') ?></div>
<?php if(!empty($a['consultation_summary']) && strpos($a['consultation_summary'],'No consultation content was captured')===false): ?>
  <?php if(!empty($a['summary_reviewed_at'])): ?>
  <div style="margin:8px 0;padding:8px 10px;background:#e9fbf4;border:1px solid #bdebd9;border-radius:7px;font-size:.65rem;font-weight:700;color:#087b53;display:flex;align-items:center;gap:6px;">
    ✓ Summary published to patient
    <?php if(!empty($a['summary_pdf_path'])): ?>
    <a href="../consultation_summaries/<?= htmlspecialchars($a['summary_pdf_path']) ?>" target="_blank" style="margin-left:auto;color:#087b53;text-decoration:underline;">View PDF</a>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div style="margin:8px 0;padding:8px 10px;background:#fff7ed;border:1px solid #fed7aa;border-radius:7px;font-size:.65rem;font-weight:700;color:#c2410c;display:flex;align-items:center;gap:6px;">
    ⚠ Summary needs your review
    <a href="review_summary.php?appt_id=<?= (int)$a['id'] ?>" style="margin-left:auto;color:#c2410c;text-decoration:underline;">Review & Publish</a>
  </div>
  <?php endif; ?>
<?php endif; ?>
      <div class="actions">
       <a class="btn btn-light" href="appointment-details.php?appt_id=<?= (int)$a['id'] ?>" <?= ($view==='past'||$call_expired)?'style="grid-column:1/-1"':'' ?>>View Details</a>
<?php if($view==='upcoming' && !$call_expired): ?>
    <?php if($tele && in_array($a['status'], ['Confirmed','Completed'], true) && $call_window_open): ?>
    <a class="btn btn-primary" href="call.php?appt_id=<?= (int)$a['id'] ?>">Start Consult</a>
  <?php else: ?>
    <span class="btn btn-disabled">Waiting</span>
  <?php endif; ?>
<?php endif; ?>
      </div>
    </article>
    <?php endwhile; else: ?><div class="empty">No <?= $view==='past'?'past':'upcoming' ?> appointments match your filters.</div><?php endif; ?>
  </section>

  <?php if($total_pages>1): ?>
  <nav class="pagination" aria-label="Appointments pagination">
    <a class="<?= $page<=1?'disabled':'' ?>" href="<?= $page<=1?'#':apptPageUrl($page-1) ?>" aria-label="Previous page">&#8249;</a>
    <?php
      $window=2;
      for($i=1;$i<=$total_pages;$i++):
        if($i===1||$i===$total_pages||($i>=$page-$window&&$i<=$page+$window)):
    ?>
      <?php if($i===$page): ?><span class="current"><?= $i ?></span>
      <?php else: ?><a href="<?= apptPageUrl($i) ?>"><?= $i ?></a><?php endif; ?>
    <?php
        elseif($i===$page-$window-1||$i===$page+$window+1):
    ?>
      <span>…</span>
    <?php endif; endfor; ?>
    <a class="<?= $page>=$total_pages?'disabled':'' ?>" href="<?= $page>=$total_pages?'#':apptPageUrl($page+1) ?>" aria-label="Next page">&#8250;</a>
  </nav>
  <?php endif; ?>
</main>

<div class="ic-overlay" id="ic-overlay">
  <div class="ic-modal">
    <div class="ic-modal-head">
      <h2>Start an Instant Call</h2>
      <button type="button" onclick="closeInstantCall()">&#10005;</button>
    </div>
    <div class="ic-search">
      <input type="text" id="ic-search-input" placeholder="Search your patients..." oninput="icDebouncedSearch()">
    </div>
    <div class="ic-list" id="ic-list">
      <div class="ic-loading">Loading patients…</div>
    </div>
  </div>
</div>

<script>
let icSearchTimer = null;
let icBusy = false;

function openInstantCall() {
  document.getElementById('ic-overlay').classList.add('open');
  document.getElementById('ic-search-input').value = '';
  icLoadPatients('');
  setTimeout(() => document.getElementById('ic-search-input').focus(), 100);
}
function closeInstantCall() {
  document.getElementById('ic-overlay').classList.remove('open');
}
function icDebouncedSearch() {
  clearTimeout(icSearchTimer);
  const val = document.getElementById('ic-search-input').value;
  icSearchTimer = setTimeout(() => icLoadPatients(val), 300);
}

async function icLoadPatients(search) {
  const list = document.getElementById('ic-list');
  list.innerHTML = '<div class="ic-loading">Loading patients…</div>';
  try {
    const res = await fetch('instant_call.php?action=list&search=' + encodeURIComponent(search));
    const data = await res.json();
    if (!data.success) { list.innerHTML = '<div class="ic-empty">Could not load patients.</div>'; return; }
    if (data.patients.length === 0) { list.innerHTML = '<div class="ic-empty">No patients found.</div>'; return; }
    list.innerHTML = '';
    data.patients.forEach(p => {
      const row = document.createElement('div');
      row.className = 'ic-row';
      row.onclick = () => icStartCall(p.id, row);
      const avatarInner = p.profile_photo
        ? `<img src="../${p.profile_photo}" alt="">`
        : p.initials;
      row.innerHTML = `
        <div class="avatar">${avatarInner}</div>
        <div class="patient-name">${icEsc(p.full_name)}</div>
        <div class="call-icon">
          <svg viewBox="0 0 24 24"><path d="M15 10l4.553-2.276A1 1 0 0121 8.723v6.554a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg>
        </div>`;
      list.appendChild(row);
    });
  } catch (e) {
    list.innerHTML = '<div class="ic-empty">Something went wrong. Try again.</div>';
  }
}

async function icStartCall(patientId, rowEl) {
  if (icBusy) return;
  icBusy = true;
  document.querySelectorAll('.ic-row').forEach(r => r.classList.add('disabled'));
  if (rowEl) rowEl.style.opacity = '0.6';
  try {
    const res = await fetch('instant_call.php?action=start', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'patient_id=' + encodeURIComponent(patientId)
    });
    const data = await res.json();
    if (data.success) {
      window.location.href = 'call.php?appt_id=' + data.appt_id;
    } else {
      alert(data.error || 'Could not start the call.');
      document.querySelectorAll('.ic-row').forEach(r => r.classList.remove('disabled'));
      icBusy = false;
    }
  } catch (e) {
    alert('Something went wrong starting the call.');
    document.querySelectorAll('.ic-row').forEach(r => r.classList.remove('disabled'));
    icBusy = false;
  }
}

function icEsc(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

document.getElementById('ic-overlay').addEventListener('click', (e) => {
  if (e.target.id === 'ic-overlay') closeInstantCall();
});
</script>

<?php require_once 'includes/nav.php'; ?>
</body></html>