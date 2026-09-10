<?php
date_default_timezone_set('Asia/Manila');
require_once 'includes/auth.php';

$search=trim($_GET['search']??'');
$date=$_GET['date']??'';
$type=$_GET['type']??'';
$status=$_GET['status']??'';
$sql="SELECT a.*,p.full_name patient_name,p.profile_photo patient_photo FROM appointments a JOIN patients p ON p.id=a.patient_id WHERE a.doctor_id=? AND a.appointment_date>=CURDATE()";
$params=[$doctor_id];$types='i';
if($search!==''){$sql.=" AND p.full_name LIKE ?";$params[]="%$search%";$types.='s';}
if($date!==''){$sql.=" AND a.appointment_date=?";$params[]=$date;$types.='s';}
if($type!==''){$sql.=" AND a.type=?";$params[]=$type;$types.='s';}
if($status!==''){$sql.=" AND a.status=?";$params[]=$status;$types.='s';}
$sql.=" ORDER BY a.appointment_date,a.appointment_time";
$stmt=$conn->prepare($sql);$stmt->bind_param($types,...$params);$stmt->execute();$appointments=$stmt->get_result();
$page_title='Appointments — TELE-CARE';$page_title_short='Appointments';$active_nav='appointments';require_once 'includes/header.php';
function apptType($v){return stripos((string)$v,'tele')!==false?'Teleconsultation':($v?:'In-person');}
?>
<style>
.appts-page{width:100%;max-width:1450px;padding:26px;box-sizing:border-box}.appts-head h1{margin:0;color:var(--neutral-900);font-size:1.7rem}.appts-head p{margin:5px 0 20px;color:var(--neutral-500);font-size:.78rem}.filters{display:grid;grid-template-columns:minmax(220px,1fr) 150px 150px 150px;gap:10px;padding:12px;margin-bottom:20px;background:#fff;border:1px solid var(--border-color);border-radius:12px}.control{width:100%;height:40px;padding:0 12px;border:1px solid var(--border-color);border-radius:8px;background:#fff;color:var(--neutral-700);font:inherit;font-size:.72rem}.cards{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.appt-card{position:relative;padding:16px;background:#fff;border:1px solid var(--border-color);border-left:3px solid var(--secondary,#0f8f83);border-radius:10px;box-shadow:var(--shadow-sm)}.patient{display:flex;align-items:center;gap:10px}.avatar{width:42px;height:42px;border-radius:50%;overflow:hidden;background:var(--neutral-100);display:grid;place-items:center;font-weight:700}.avatar img{width:100%;height:100%;object-fit:cover}.patient-name{font-size:.9rem;font-weight:800;color:var(--neutral-900)}.patient-id{font-size:.61rem;color:var(--neutral-500);margin-top:2px}.badge{position:absolute;right:14px;top:14px;padding:4px 9px;border-radius:999px;background:#e9fbf4;color:#087b53;border:1px solid #bdebd9;font-size:.58rem;font-weight:700}.info{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:14px 0;padding:11px;background:#f3f5ff;border-radius:8px}.info-label{font-size:.57rem;font-weight:800;text-transform:uppercase;color:var(--neutral-600)}.info-value{margin-top:3px;font-size:.69rem;color:var(--neutral-900);font-weight:600}.reason-label{font-size:.57rem;font-weight:800;text-transform:uppercase;color:var(--neutral-600)}.reason{min-height:34px;margin:4px 0 14px;color:var(--neutral-700);font-size:.69rem;line-height:1.45}.actions{display:grid;grid-template-columns:1fr 1fr;gap:8px}.btn{display:flex;align-items:center;justify-content:center;min-height:36px;border-radius:7px;font-size:.66rem;font-weight:800;text-decoration:none}.btn-light{border:1px solid var(--border-color);color:var(--neutral-800);background:#fff}.btn-primary{background:var(--primary);color:#fff}.btn-disabled{background:#e4a1a5;color:#fff;pointer-events:none}.empty{grid-column:1/-1;padding:50px;text-align:center;background:#fff;border:1px solid var(--border-color);border-radius:10px;color:var(--neutral-500)}
@media(max-width:950px){.cards{grid-template-columns:1fr}.filters{grid-template-columns:1fr 1fr}}@media(max-width:600px){.appts-page{padding:15px}.filters{grid-template-columns:1fr}.info{grid-template-columns:1fr}.actions{grid-template-columns:1fr}}
</style>
<main class="page appts-page">
  <div class="appts-head"><h1>Upcoming Appointments</h1><p>View and manage your upcoming patient appointments.</p></div>
  <form class="filters" method="get">
    <input class="control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search patient">
    <input class="control" type="date" name="date" value="<?= htmlspecialchars($date) ?>">
    <select class="control" name="type"><option value="">All Types</option><option value="Teleconsult" <?= $type==='Teleconsult'?'selected':'' ?>>Teleconsultation</option><option value="In-person" <?= $type==='In-person'?'selected':'' ?>>In-person</option></select>
    <select class="control" name="status" onchange="this.form.submit()"><option value="">All Statuses</option><?php foreach(['Pending','DoctorApproved','Confirmed','Completed'] as $s): ?><option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select>
  </form>
  <section class="cards">
    <?php if($appointments->num_rows): while($a=$appointments->fetch_assoc()): $tele=stripos((string)$a['type'],'tele')!==false; ?>
    <article class="appt-card">
      <span class="badge"><?= htmlspecialchars($a['status']) ?></span>
      <div class="patient"><div class="avatar"><?php if(!empty($a['patient_photo'])): ?><img src="../<?= htmlspecialchars($a['patient_photo']) ?>" alt=""><?php else: ?><?= htmlspecialchars(strtoupper(substr($a['patient_name'],0,2))) ?><?php endif; ?></div><div><div class="patient-name"><?= htmlspecialchars($a['patient_name']) ?></div><div class="patient-id">ID: #PT-<?= str_pad((string)$a['patient_id'],4,'0',STR_PAD_LEFT) ?></div></div></div>
      <div class="info"><div><div class="info-label">Date &amp; Time</div><div class="info-value"><?= date('M d, Y',strtotime($a['appointment_date'])) ?><br><?= date('h:i A',strtotime($a['appointment_time'])) ?></div></div><div><div class="info-label">Type</div><div class="info-value"><?= htmlspecialchars(apptType($a['type'])) ?><?= !$tele&&!empty($a['department'])?'<br>'.htmlspecialchars($a['department']):'' ?></div></div></div>
      <div class="reason-label">Reason for Consultation</div><div class="reason"><?= htmlspecialchars($a['reason']?:'No reason provided.') ?></div>
      <div class="actions"><a class="btn btn-light" href="appointment-details.php?appt_id=<?= (int)$a['id'] ?>">View Details</a><?php if($tele&&$a['status']==='Confirmed'): ?><a class="btn btn-primary" href="call.php?appt_id=<?= (int)$a['id'] ?>">Start Consult</a><?php else: ?><span class="btn btn-disabled"><?= $tele?'Waiting':'Check In' ?></span><?php endif; ?></div>
    </article>
    <?php endwhile; else: ?><div class="empty">No upcoming appointments match your filters.</div><?php endif; ?>
  </section>
</main>
<?php require_once 'includes/nav.php'; ?>
</body></html>
