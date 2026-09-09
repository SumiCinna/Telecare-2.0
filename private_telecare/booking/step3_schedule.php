<?php
// private_telecare/booking/step3_schedule.php
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/booking_helpers.php';

booking_require(['department', 'doctor_id']);
$doctor_id = (int)$_SESSION['booking']['doctor_id'];

$dstmt = $conn->prepare("SELECT * FROM doctors WHERE id=?");
$dstmt->bind_param("i", $doctor_id);
$dstmt->execute();
$doctor = $dstmt->get_result()->fetch_assoc();
if (!$doctor) { header('Location: router.php?page=booking/step2_doctor'); exit; }

$sres = $conn->prepare("SELECT day_of_week, start_time, end_time FROM doctor_schedules WHERE doctor_id=?");
$sres->bind_param("i", $doctor_id);
$sres->execute();
$schedules = $sres->get_result()->fetch_all(MYSQLI_ASSOC);

$bres = $conn->prepare("SELECT appointment_date, appointment_time FROM appointments WHERE doctor_id=? AND status NOT IN ('Cancelled') AND appointment_date >= CURDATE()");
$bres->bind_param("i", $doctor_id);
$bres->execute();
$booked = $bres->get_result()->fetch_all(MYSQLI_ASSOC);

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = trim($_POST['appt_date'] ?? '');
    $time = trim($_POST['appt_time'] ?? '');
    if ($date && $time) {
    $dateObj = DateTime::createFromFormat('!Y-m-d', $date);
    $dateValid = $dateObj && $dateObj->format('Y-m-d') === $date;
    $timeValue = substr($time, 0, 5);
    $timeValid = preg_match('/^\d{2}:\d{2}$/', $timeValue) === 1;
    $dayName = $dateValid ? $dateObj->format('l') : '';
    $slotValid = false;

    if ($dateValid && $timeValid && $dateObj->format('Y-m-d') >= date('Y-m-d')) {
      foreach ($schedules as $schedule) {
        if ($schedule['day_of_week'] === $dayName && $timeValue >= substr($schedule['start_time'], 0, 5) && $timeValue < substr($schedule['end_time'], 0, 5)) {
          $slotValid = true;
          break;
        }
      }
      foreach ($booked as $bookedSlot) {
        if ($bookedSlot['appointment_date'] === $date && substr($bookedSlot['appointment_time'], 0, 5) === $timeValue) {
          $slotValid = false;
          break;
        }
      }
      if ($date === date('Y-m-d') && $timeValue <= date('H:i')) $slotValid = false;
    }

    if ($slotValid) {
      $_SESSION['booking']['appt_date'] = $date;
      $_SESSION['booking']['appt_time'] = $timeValue . ':00';
      header('Location: router.php?page=booking/step4_review'); exit;
    }
    $error = 'That date and time is no longer available. Please choose another slot.';
    }
  if (!$date || !$time) $error = 'Please pick both a date and a time.';
}

$page_title = 'Pick Schedule — TELE-CARE';
$active_nav = 'visits';
require_once __DIR__ . '/../../includes/header.php';
echo booking_wizard_css();
?>
<style>
.wiz-page{max-width:1180px;padding:1rem 2rem .5rem}
.cal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:0.8rem}
.cal-nav{background:none;border:none;cursor:pointer;color:var(--green);padding:0.3rem 0.6rem;border-radius:8px;font-size:1.1rem}
.cal-nav:hover{background:rgba(36,68,65,0.08)}
.cal-nav:disabled{opacity:0.25;cursor:default}
.cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:2px}
.cal-day-name{text-align:center;font-size:0.65rem;font-weight:700;color:var(--muted);padding:0.3rem 0}
.cal-cell{aspect-ratio:1;display:flex;align-items:center;justify-content:center;border-radius:10px;font-size:0.82rem;font-weight:500}
.cal-cell.past{color:rgba(36,68,65,0.18)}
.cal-cell.blocked{color:rgba(36,68,65,0.2);text-decoration:line-through}
.cal-cell.available{color:var(--green);background:rgba(36,68,65,0.05);cursor:pointer;font-weight:600}
.cal-cell.available:hover{background:rgba(63,130,227,0.1);color:var(--blue)}
.cal-cell.today.available{border:1.5px solid var(--blue);color:var(--blue)}
.cal-cell.selected{background:var(--blue)!important;color:#fff!important;font-weight:700}
.time-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:0.5rem;margin-top:1rem}
.time-slot{padding:0.55rem 0;border-radius:10px;border:1.5px solid rgba(36,68,65,0.1);text-align:center;font-size:0.8rem;font-weight:600;cursor:pointer;color:var(--green)}
.time-slot:hover{border-color:var(--blue);color:var(--blue);background:rgba(63,130,227,0.05)}
.time-slot.selected{background:var(--blue);color:#fff;border-color:var(--blue)}
.time-slot.booked{background:rgba(0,0,0,0.04);color:rgba(36,68,65,0.25);cursor:not-allowed;border-color:transparent}
.doctor-strip{display:flex;align-items:center;gap:0.8rem;margin-bottom:1.2rem}
.doctor-strip .doctor-avatar{width:44px;height:44px;border-radius:12px;background:var(--blue);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;overflow:hidden}
.doctor-strip .doctor-avatar img{width:100%;height:100%;object-fit:cover}
.schedule-layout{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(240px,.9fr);gap:1.2rem;align-items:start}
.schedule-summary{background:#fff;border:1px solid rgba(36,68,65,.1);border-radius:14px;padding:1rem;color:var(--green);position:sticky;top:1rem}
.schedule-summary h3{font-family:'DM Sans',sans-serif;font-size:.95rem;margin-bottom:.8rem}
.schedule-summary-row{padding:.65rem 0;border-top:1px solid rgba(36,68,65,.08);font-size:.78rem}
.schedule-summary-row strong{display:block;font-size:.7rem;color:var(--muted);font-weight:600;margin-bottom:.2rem}
.schedule-time-picker{margin-top:1rem;padding-top:.9rem;border-top:1px solid rgba(36,68,65,.08)}
.schedule-time-picker h4{font-size:.82rem;margin-bottom:.35rem;color:var(--green)}
.schedule-time-picker .time-grid{margin-top:.65rem;grid-template-columns:repeat(2,1fr);max-height:420px;overflow:auto;padding-right:.15rem}
@media(max-width:800px){.schedule-layout{grid-template-columns:1fr}.schedule-summary{order:-1;position:static}}
</style>

<div class="wiz-page">
  <div class="wiz-title">Pick a Schedule</div>
  <div class="wiz-sub">Choose an available date and time slot.</div>

  <?php render_stepper(3); ?>
  <?php if ($error): ?><div class="wiz-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="schedule-layout">
  <div class="wiz-card">
    <div class="doctor-strip">
      <div class="doctor-avatar">
        <?php if (!empty($doctor['profile_photo'])): ?><img src="../../<?= htmlspecialchars($doctor['profile_photo']) ?>" alt=""/><?php else: ?><?= strtoupper(substr($doctor['full_name'],0,1)) ?><?php endif; ?>
      </div>
      <div>
        <div style="font-weight:700;color:var(--green);">Dr. <?= htmlspecialchars($doctor['full_name']) ?></div>
        <div style="font-size:0.8rem;color:var(--muted);"><?= htmlspecialchars($doctor['specialty'] ?? '') ?></div>
      </div>
    </div>

    <div class="cal-header">
      <button type="button" class="cal-nav" id="cal-prev" onclick="calNav(-1)">&#8249;</button>
      <div style="font-weight:700;font-size:0.9rem;color:var(--green);" id="cal-month-label"></div>
      <button type="button" class="cal-nav" onclick="calNav(1)">&#8250;</button>
    </div>
    <div class="cal-grid" id="cal-grid"></div>

  </div>
  <aside class="schedule-summary">
    <h3>Appointment Summary</h3>
    <div class="schedule-summary-row"><strong>Department</strong><?= htmlspecialchars($_SESSION['booking']['department']) ?></div>
    <div class="schedule-summary-row"><strong>Doctor</strong>Dr. <?= htmlspecialchars($doctor['full_name']) ?></div>
    <div class="schedule-summary-row"><strong>Date &amp; Time</strong><span id="summary-date">Pending Selection</span><span id="summary-time"></span></div>
    <div class="schedule-time-picker">
      <h4>Select a time</h4>
      <div class="time-grid" id="time-grid"><div style="grid-column:1/-1;color:var(--muted);font-size:0.78rem;">Select a date to view times.</div></div>
    </div>
  </aside>
  </div>

  <form method="POST" id="schedule-form">
    <input type="hidden" name="appt_date" id="f-date"/>
    <input type="hidden" name="appt_time" id="f-time"/>
    <div class="wiz-actions">
      <a href="router.php?page=booking/step2_doctor" class="wiz-btn ghost">Back</a>
      <button type="submit" class="wiz-btn primary" id="btn-continue" disabled>Continue to Review</button>
    </div>
  </form>
</div>

<script>
const SCHEDULES = <?= json_encode($schedules, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
const BOOKED    = <?= json_encode($booked, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
const DAY_NAMES_SHORT = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
const DAY_NAMES_LONG  = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
const MONTH_NAMES     = ['January','February','March','April','May','June','July','August','September','October','November','December'];
const TODAY_STR       = new Date().toLocaleDateString('en-CA');

let calYear, calMonth, selDate = null, selTime = null;
(function init(){ const n = new Date(); calYear = n.getFullYear(); calMonth = n.getMonth(); renderCalendar(); })();

function fmt12h(t){ const [h,m]=t.split(':').map(Number); const ap=h>=12?'PM':'AM'; const hr=h%12||12; return hr+':'+String(m||0).padStart(2,'0')+' '+ap; }
function generateSlots(start,end){ const slots=[]; let [sh,sm]=start.split(':').map(Number); const [eh,em]=end.split(':').map(Number); const endMins=eh*60+em;
  while(sh*60+sm<endMins){ slots.push(String(sh).padStart(2,'0')+':'+String(sm).padStart(2,'0')); sm+=30; if(sm>=60){sh++;sm-=60;} if(sh>=24) break; } return slots; }

function calNav(dir){ calMonth+=dir; if(calMonth>11){calMonth=0;calYear++;} else if(calMonth<0){calMonth=11;calYear--;} renderCalendar(); }

function renderCalendar(){
  document.getElementById('cal-month-label').textContent = MONTH_NAMES[calMonth]+' '+calYear;
  const now = new Date();
  document.getElementById('cal-prev').disabled = (calYear===now.getFullYear() && calMonth===now.getMonth());
  const grid = document.getElementById('cal-grid');
  const availDays = SCHEDULES.map(s=>s.day_of_week);
  const today = new Date(); today.setHours(0,0,0,0);
  const nowMins = now.getHours()*60+now.getMinutes();
  const firstDay = new Date(calYear,calMonth,1).getDay();
  const daysInMonth = new Date(calYear,calMonth+1,0).getDate();
  grid.innerHTML = DAY_NAMES_SHORT.map(d=>`<div class="cal-day-name">${d}</div>`).join('');
  for(let i=0;i<firstDay;i++) grid.innerHTML += `<div class="cal-cell"></div>`;
  for(let day=1; day<=daysInMonth; day++){
    const d = new Date(calYear,calMonth,day); d.setHours(0,0,0,0);
    const longDay = DAY_NAMES_LONG[d.getDay()];
    const dateStr = calYear+'-'+String(calMonth+1).padStart(2,'0')+'-'+String(day).padStart(2,'0');
    const isPast = d<today, isToday = d.getTime()===today.getTime();
    const isInSched = availDays.includes(longDay);
    let isAvail = !isPast && isInSched;
    if(isAvail && isToday){
      const slots = SCHEDULES.filter(s=>s.day_of_week===longDay).flatMap(s=>generateSlots(s.start_time,s.end_time));
      isAvail = slots.some(t=>{const [h,m]=t.split(':').map(Number); return h*60+m>nowMins;});
    }
    let cls='cal-cell';
    if(isPast) cls+=' past'; else if(!isInSched) cls+=' blocked'; else if(!isAvail) cls+=' past'; else cls+=' available';
    if(selDate===dateStr && isAvail) cls+=' selected';
    if(isToday) cls+=' today';
    const onclick = isAvail ? `onclick="pickDate('${dateStr}')"` : '';
    grid.innerHTML += `<div class="${cls}" ${onclick}>${day}</div>`;
  }
}

function pickDate(dateStr){ selDate = dateStr; selTime = null; renderCalendar(); renderTimeSlots(); updateSummary(); updateContinue(); }

function renderTimeSlots(){
  const grid = document.getElementById('time-grid');
  if(!selDate){ grid.innerHTML = `<div style="grid-column:1/-1;color:var(--muted);font-size:0.82rem;">Pick a date first.</div>`; return; }
  const dayName = DAY_NAMES_LONG[new Date(selDate+'T00:00:00').getDay()];
  const scheds = SCHEDULES.filter(s=>s.day_of_week===dayName);
  if(!scheds.length){ grid.innerHTML = `<div style="grid-column:1/-1;color:var(--muted);font-size:0.82rem;">No schedule for this day.</div>`; return; }
  let allSlots = [...new Set(scheds.flatMap(s=>generateSlots(s.start_time,s.end_time)))].sort();
  const rawBooked = BOOKED.filter(b=>b.appointment_date===selDate).map(b=>b.appointment_time.slice(0,5));
  const DUR = 60;
  const bookedSlots = allSlots.filter(slot=>{
    const [sh,sm]=slot.split(':').map(Number); const slotMins=sh*60+sm;
    return rawBooked.some(b=>{const [bh,bm]=b.split(':').map(Number); const bm2=bh*60+bm; return slotMins>=bm2 && slotMins<bm2+DUR;});
  });
  const isToday = selDate===TODAY_STR, nowMins = isToday ? new Date().getHours()*60+new Date().getMinutes() : -1;
  let html=''; let any=false;
  allSlots.forEach(t=>{
    const [h,m]=t.split(':').map(Number); const slotMins=h*60+m;
    const disabled = (isToday && slotMins<=nowMins) || bookedSlots.includes(t);
    const isSel = selTime===t;
    if(!disabled) any=true;
    html += `<div class="time-slot ${disabled?'booked':''} ${isSel?'selected':''}" ${!disabled?`onclick="pickTime('${t}')"`:''}>${fmt12h(t)}</div>`;
  });
  grid.innerHTML = any ? html : `<div style="grid-column:1/-1;color:var(--muted);font-size:0.82rem;">No times available for this date.</div>`;
}

function pickTime(t){ selTime = t; renderTimeSlots(); updateSummary(); updateContinue(); }
function updateSummary(){
  document.getElementById('summary-date').textContent = selDate || 'Pending Selection';
  document.getElementById('summary-time').textContent = selTime ? ' at ' + fmt12h(selTime) : '';
}
function updateContinue(){ document.getElementById('btn-continue').disabled = !(selDate && selTime); }

document.getElementById('schedule-form').addEventListener('submit', function(e){
  if(!selDate || !selTime){ e.preventDefault(); return; }
  document.getElementById('f-date').value = selDate;
  document.getElementById('f-time').value = selTime + ':00';
});
</script>

<?php require_once __DIR__ . '/../../includes/nav.php'; ?>
</body>
</html>