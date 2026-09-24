<?php
date_default_timezone_set('Asia/Manila');
require_once 'includes/auth.php';

$schedules = [];
$stmt = $conn->prepare("SELECT day_of_week, TIME_FORMAT(start_time,'%H:%i') start_time, TIME_FORMAT(end_time,'%H:%i') end_time FROM doctor_schedules WHERE doctor_id=? ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),start_time");
$stmt->bind_param('i', $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) $schedules[] = $row;

$appointments = [];
$stmt = $conn->prepare("SELECT a.id,a.appointment_date,TIME_FORMAT(a.appointment_time,'%H:%i') appointment_time,a.type,a.status,p.full_name patient_name FROM appointments a JOIN patients p ON p.id=a.patient_id WHERE a.doctor_id=? AND a.appointment_date>=CURDATE() ORDER BY a.appointment_date,a.appointment_time");
$stmt->bind_param('i', $doctor_id);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$toast = $_SESSION['toast'] ?? null;
$error = $_SESSION['toast_error'] ?? null;
unset($_SESSION['toast'], $_SESSION['toast_error']);

$page_title = 'Schedule — TELE-CARE';
$page_title_short = 'Schedule';
$active_nav = 'schedule';
require_once 'includes/header.php';
?>

<style>
*{box-sizing:border-box}
.availability-page{width:100%;max-width:1600px;margin:auto;padding:clamp(10px,2vw,24px)}
.availability-card{background:var(--surface);border:1px solid var(--border-color);border-radius:var(--radius-md);box-shadow:var(--shadow-sm);padding:clamp(8px,1.5vw,16px);width:100%}
.availability-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px;flex-wrap:wrap}
.availability-head h1{margin:0;color:var(--neutral-900);font-size:clamp(1.05rem,1.4vw,1.2rem)}
.availability-head p{margin:4px 0 0;color:var(--neutral-500);font-size:.75rem}
.calendar-nav{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.calendar-nav h2{margin:0;color:var(--neutral-900);font-size:clamp(.9rem,1.2vw,1rem)}
.calendar-nav button{width:34px;height:34px;flex-shrink:0;border:0;border-radius:8px;background:var(--neutral-100);color:var(--neutral-700);cursor:pointer;font-size:1.1rem}
.calendar{display:grid;grid-template-columns:repeat(7,1fr);width:100%;border:1px solid var(--border-color);border-radius:8px;overflow:hidden}
.calendar-day{padding:clamp(5px,1vw,9px) 2px;background:var(--neutral-100);border-right:1px solid var(--border-color);color:var(--neutral-500);font-size:clamp(.5rem,1.4vw,.68rem);font-weight:700;text-align:center;overflow:hidden;white-space:nowrap}
.calendar-day:nth-child(7n){border-right:0}
.calendar-cell{min-width:0;min-height:clamp(52px,9vw,105px);padding:clamp(3px,.8vw,7px);border-top:1px solid var(--border-color);border-right:1px solid var(--border-color);background:var(--surface);overflow:hidden}
.calendar-cell:nth-child(7n){border-right:0}
.calendar-cell.empty{background:var(--neutral-50)}
.calendar-cell.today{background:var(--primary-soft)}
.calendar-number{display:grid;place-items:center;width:20px;height:20px;color:var(--neutral-700);font-size:clamp(.55rem,1.2vw,.68rem);font-weight:700}
.calendar-number.today{border-radius:50%;background:var(--primary);color:#fff}
.calendar-event{display:block;margin-top:3px;padding:3px 4px;border-left:3px solid var(--secondary);border-radius:5px;background:var(--secondary-soft);color:var(--secondary-dark);font-size:clamp(.42rem,1vw,.58rem);text-decoration:none;overflow:hidden;white-space:nowrap;text-overflow:ellipsis}
.calendar-event strong{display:block;overflow:hidden;white-space:nowrap;text-overflow:ellipsis}
.schedule-link{display:flex;align-items:center;justify-content:center;padding:10px 16px;border-radius:8px;background:var(--primary);color:#fff;font-size:.75rem;font-weight:700;text-decoration:none;white-space:nowrap}
.schedule-link:hover{opacity:.92}
.toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%);padding:11px 18px;border-radius:30px;background:#198c83;color:#fff;font-size:.78rem;font-weight:600;z-index:500;max-width:90vw;text-align:center}
.toast.error{background:#bd1d2b}

@media(min-width:1601px){
.calendar-cell{min-height:130px}
.calendar-event{font-size:.62rem}
}

@media(max-width:600px){
.calendar-event strong{display:none}
.calendar-cell{padding:2px}
}

@media(max-width:400px){
.calendar-day{font-size:.48rem}
.calendar-number{width:16px;height:16px}
}
</style>

<?php if ($toast): ?><div class="toast"><?= htmlspecialchars($toast) ?></div><?php endif; ?>
<?php if ($error): ?><div class="toast error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<main class="page availability-page">
    <div class="availability-head">
        <div>
            <h1>Schedule</h1>
            <p>View your appointments and configured availability.</p>
        </div>
        <a class="schedule-link" href="availability_settings.php">Schedule Settings</a>
    </div>

    <section class="availability-card">
        <div class="calendar-nav">
            <button type="button" id="prev-month" aria-label="Previous month">‹</button>
            <h2 id="calendar-month"></h2>
            <button type="button" id="next-month" aria-label="Next month">›</button>
        </div>
        <div class="calendar" id="calendar"></div>
    </section>
</main>

<script>
const schedules=<?= json_encode($schedules,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
const appointments=<?= json_encode($appointments,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
const dayNames=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
let currentMonth=new Date();

const key=d=>`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
const time=v=>{const [h,m]=v.split(':').map(Number);return `${h%12||12}:${String(m).padStart(2,'0')} ${h>=12?'PM':'AM'}`};
const safe=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

function renderCalendar(){
    const year=currentMonth.getFullYear(),month=currentMonth.getMonth(),grid=document.getElementById('calendar');
    document.getElementById('calendar-month').textContent=currentMonth.toLocaleDateString('en-US',{month:'long',year:'numeric'});
    const w=grid.getBoundingClientRect().width;
    const isNarrow=w<420;
    const dayLabels=isNarrow?['S','M','T','W','T','F','S']:['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    grid.innerHTML=dayLabels.map(d=>`<div class="calendar-day">${d}</div>`).join('');
    const first=new Date(year,month,1).getDay(),total=new Date(year,month+1,0).getDate(),today=key(new Date());
    const maxEvents=w<500?2:3;

    for(let i=0;i<first;i++) grid.innerHTML+='<div class="calendar-cell empty"></div>';

    for(let day=1;day<=total;day++){
        const date=new Date(year,month,day),dateKey=key(date);
        const events=appointments.filter(a=>a.appointment_date===dateKey);
        let html=`<div class="calendar-cell ${dateKey===today?'today':''}"><span class="calendar-number ${dateKey===today?'today':''}">${day}</span>`;
        events.slice(0,maxEvents).forEach(a=>{
            html+=`<a class="calendar-event" href="appointments.php?patient_id=${Number(a.patient_id||0)}"><strong>${time(a.appointment_time)}</strong>${safe(a.patient_name)}</a>`;
        });
        grid.innerHTML+=html+'</div>';
    }
}

document.getElementById('prev-month').onclick=()=>{currentMonth.setMonth(currentMonth.getMonth()-1);renderCalendar()};
document.getElementById('next-month').onclick=()=>{currentMonth.setMonth(currentMonth.getMonth()+1);renderCalendar()};
new ResizeObserver(renderCalendar).observe(document.getElementById('calendar'));
renderCalendar();
</script>

<?php require_once 'includes/nav.php'; ?>
</body>
</html>