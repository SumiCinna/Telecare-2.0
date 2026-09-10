<?php
// doctor/availability.php
date_default_timezone_set('Asia/Manila');
require_once 'includes/auth.php';

$conn->query("CREATE TABLE IF NOT EXISTS doctor_schedule_settings (
  doctor_id INT NOT NULL,
  consultation_duration SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  appointment_interval SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  break_start TIME NULL,
  break_end TIME NULL,
  consultation_types VARCHAR(255) NOT NULL DEFAULT 'In-person',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (doctor_id),
  CONSTRAINT doctor_schedule_settings_doctor_fk FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

function generateTimeOptions(): array {
    $opts = [];
    for ($h = 0; $h < 24; $h++) {
        foreach ([0, 30] as $m) {
            $val = str_pad((string)$h, 2, '0', STR_PAD_LEFT) . ':' . str_pad((string)$m, 2, '0', STR_PAD_LEFT);
            $ap = $h >= 12 ? 'PM' : 'AM';
            $hr = $h % 12 ?: 12;
            $lbl = $hr . ':' . str_pad((string)$m, 2, '0', STR_PAD_LEFT) . ' ' . $ap;
            $opts[] = ['val' => $val, 'lbl' => $lbl];
        }
    }
    return $opts;
}

function validateScheduleSlots(array $days, array $starts, array $ends): array {
    $slots = [];
    $daysAllowed = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

  $hasAnyTime = false;
  foreach ($starts as $i => $start) {
    if (trim((string)$start) !== '' || trim((string)($ends[$i] ?? '')) !== '') {
      $hasAnyTime = true;
      break;
    }
  }
  if (!$hasAnyTime) return [true, [], ''];

    foreach ($days as $i => $day) {
        $day = trim((string)$day);
        $start = trim((string)($starts[$i] ?? ''));
        $end = trim((string)($ends[$i] ?? ''));

        if ($day === '' && $start === '' && $end === '') {
            continue;
        }

        if ($day === '' || $start === '' || $end === '') {
            return [false, [], 'Please complete all schedule row fields.'];
        }

        if (!in_array($day, $daysAllowed, true)) {
            return [false, [], 'Invalid day selected.'];
        }

        if (!preg_match('/^([01]\d|2[0-3]):(00|30)$/', $start) || !preg_match('/^([01]\d|2[0-3]):(00|30)$/', $end)) {
            return [false, [], 'Invalid time format. Please use 30-minute intervals.'];
        }

        if ($start >= $end) {
            return [false, [], "End time must be after start time for {$day}."];
        }

        foreach ($slots as $ex) {
            if ($ex['day'] === $day && $start < $ex['end'] && $ex['start'] < $end) {
                return [
                    false,
                    [],
                    "Schedule conflict on {$day}: {$start}–{$end} overlaps with {$ex['start']}–{$ex['end']}."
                ];
            }
        }

        $slots[] = ['day' => $day, 'start' => $start, 'end' => $end];
    }

    return [true, $slots, ''];
}

function validScheduleSetting(int $value, array $allowed): bool {
  return in_array($value, $allowed, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'update_fee') {
        $feeRaw = trim((string)($_POST['consultation_fee'] ?? ''));
        if ($feeRaw === '' || !is_numeric($feeRaw)) {
            $_SESSION['toast_error'] = 'Please enter a valid consultation fee.';
            header('Location: availability.php');
            exit;
        }

        $fee = (float)$feeRaw;
        if ($fee < 0 || $fee > 100000) {
            $_SESSION['toast_error'] = 'Consultation fee must be between ₱0 and ₱100,000.';
            header('Location: availability.php');
            exit;
        }

        $stmt = $conn->prepare('UPDATE doctors SET consultation_fee=? WHERE id=?');
        $stmt->bind_param('di', $fee, $doctor_id);
        $stmt->execute();

        $_SESSION['toast'] = 'Consultation fee updated.';
        header('Location: availability.php');
        exit;
    }

    if ($action === 'update_schedule') {
        $days = $_POST['sched_day'] ?? [];
        $starts = $_POST['sched_start'] ?? [];
        $ends = $_POST['sched_end'] ?? [];
      $duration = (int)($_POST['consultation_duration'] ?? 30);
      $interval = (int)($_POST['appointment_interval'] ?? 5);
      $breakStart = trim((string)($_POST['break_start'] ?? ''));
      $breakEnd = trim((string)($_POST['break_end'] ?? ''));
      $types = [];
      foreach ((array)($_POST['consultation_types'] ?? []) as $type) {
        $type = trim((string)$type);
        if ($type !== '' && !in_array($type, $types, true)) $types[] = $type;
      }

      if (!validScheduleSetting($duration, [15, 30, 45, 60]) || !validScheduleSetting($interval, [5, 10, 15, 30, 60])) {
        $_SESSION['toast_error'] = 'Choose a valid consultation duration and appointment interval.';
        header('Location: availability.php');
        exit;
      }
      if (($breakStart !== '' || $breakEnd !== '') && ($breakStart === '' || $breakEnd === '' || $breakStart >= $breakEnd)) {
        $_SESSION['toast_error'] = 'Enter a valid break time range.';
        header('Location: availability.php');
        exit;
      }
      if (!$types) $types = ['In-person'];

        [$ok, $slots, $error] = validateScheduleSlots($days, $starts, $ends);
        if (!$ok) {
            $_SESSION['toast_error'] = $error;
            header('Location: availability.php');
            exit;
        }

        $conn->begin_transaction();
        try {
            $del = $conn->prepare('DELETE FROM doctor_schedules WHERE doctor_id=?');
            $del->bind_param('i', $doctor_id);
            $del->execute();

            if (!empty($slots)) {
                $ins = $conn->prepare('INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time) VALUES (?,?,?,?)');
                foreach ($slots as $slot) {
                    $start = $slot['start'] . ':00';
                    $end = $slot['end'] . ':00';
                    $ins->bind_param('isss', $doctor_id, $slot['day'], $start, $end);
                    $ins->execute();
                }
            }

              $settings = $conn->prepare("INSERT INTO doctor_schedule_settings
                (doctor_id, consultation_duration, appointment_interval, break_start, break_end, consultation_types)
                VALUES (?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?)
                ON DUPLICATE KEY UPDATE consultation_duration=VALUES(consultation_duration), appointment_interval=VALUES(appointment_interval), break_start=VALUES(break_start), break_end=VALUES(break_end), consultation_types=VALUES(consultation_types)");
              $typesValue = implode(',', $types);
              $settings->bind_param('iiisss', $doctor_id, $duration, $interval, $breakStart, $breakEnd, $typesValue);
              $settings->execute();

            $conn->commit();
            $_SESSION['toast'] = 'Weekly schedule updated.';
        } catch (Throwable $e) {
            $conn->rollback();
            $_SESSION['toast_error'] = 'Failed to update schedule. Please try again.';
        }

        header('Location: availability.php');
        exit;
    }
}

$toast = $_SESSION['toast'] ?? null;
$toast_error = $_SESSION['toast_error'] ?? null;
unset($_SESSION['toast'], $_SESSION['toast_error']);

$schedules = [];
$sstmt = $conn->prepare("SELECT day_of_week, TIME_FORMAT(start_time, '%H:%i') AS start_time, TIME_FORMAT(end_time, '%H:%i') AS end_time
                         FROM doctor_schedules
                         WHERE doctor_id=?
                         ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), start_time");
$sstmt->bind_param('i', $doctor_id);
$sstmt->execute();
$sres = $sstmt->get_result();
while ($s = $sres->fetch_assoc()) {
    $schedules[] = $s;
}

$settings = [
  'consultation_duration' => 30,
  'appointment_interval' => 5,
  'break_start' => '',
  'break_end' => '',
  'consultation_types' => 'In-person',
];
$settingsStmt = $conn->prepare('SELECT consultation_duration, appointment_interval, TIME_FORMAT(break_start, "%H:%i") break_start, TIME_FORMAT(break_end, "%H:%i") break_end, consultation_types FROM doctor_schedule_settings WHERE doctor_id=?');
$settingsStmt->bind_param('i', $doctor_id);
$settingsStmt->execute();
$savedSettings = $settingsStmt->get_result()->fetch_assoc();
if ($savedSettings) {
  $settings = array_merge($settings, $savedSettings);
}

// Migrate legacy schedules once to the new default requested for this module.
if (!$savedSettings && !empty($schedules)) {
  $conn->begin_transaction();
  try {
    $resetSchedule = $conn->prepare('DELETE FROM doctor_schedules WHERE doctor_id=?');
    $resetSchedule->bind_param('i', $doctor_id);
    $resetSchedule->execute();

    $defaultSchedule = $conn->prepare("INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time) VALUES (?, 'Monday', '09:00:00', '17:00:00')");
    $defaultSchedule->bind_param('i', $doctor_id);
    $defaultSchedule->execute();

    $defaultSettings = $conn->prepare("INSERT INTO doctor_schedule_settings (doctor_id, consultation_duration, appointment_interval, break_start, break_end, consultation_types) VALUES (?, 30, 5, NULL, NULL, 'In-person')");
    $defaultSettings->bind_param('i', $doctor_id);
    $defaultSettings->execute();
    $conn->commit();

    $schedules = [[
      'day_of_week' => 'Monday',
      'start_time' => '09:00',
      'end_time' => '17:00',
    ]];
    $settings['consultation_types'] = 'In-person';
  } catch (Throwable $e) {
    $conn->rollback();
  }
}
$selectedTypes = array_filter(array_map('trim', explode(',', (string)$settings['consultation_types'])));

$daysOfWeek = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
$activeDays = array_values(array_unique(array_column($schedules, 'day_of_week')));
$timeOptions = generateTimeOptions();

$page_title       = 'Schedule — TELE-CARE';
$page_title_short = 'Schedule';
$active_nav       = 'appointments';
require_once 'includes/header.php';
?>

<style>
  .availability-layout{display:grid;grid-template-columns:minmax(0,1.65fr) minmax(260px,.8fr);gap:1rem;align-items:start;}
  .sched-row{display:grid;grid-template-columns:minmax(120px,1fr) minmax(110px,1fr) minmax(110px,1fr) auto;gap:0.6rem;align-items:center;}
  .sched-input{padding:0.6rem 0.7rem;border:1.5px solid var(--border-color);border-radius:var(--radius-md);font-family:inherit;font-size:0.85rem;color:var(--neutral-900);outline:none;width:100%;background:var(--surface);transition:border-color .2s,box-shadow .2s;}
  .sched-input:focus{border-color:var(--primary);box-shadow:0 0 0 3px var(--focus-ring);}
  select.sched-input{cursor:pointer;}
  .sched-remove{background:var(--primary-soft);border:none;border-radius:var(--radius-sm);width:34px;height:34px;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--primary);transition:all .2s;flex-shrink:0;}
  .sched-remove:hover{background:#f8d4d7;}
  .btn-add-sched{display:inline-flex;align-items:center;gap:0.4rem;background:var(--secondary-soft);color:var(--secondary-dark);border:none;border-radius:var(--radius-md);padding:0.45rem 1rem;font-size:0.8rem;font-weight:700;cursor:pointer;font-family:inherit;transition:all .2s;}
  .btn-add-sched:hover{background:#c9ece9;}
  .day-picker{display:flex;flex-wrap:wrap;gap:.45rem;margin:.65rem 0 1rem;}
  .day-toggle{border:1px solid var(--border-color);background:var(--surface);border-radius:999px;color:var(--neutral-700);cursor:pointer;font-size:.72rem;font-weight:700;padding:.42rem .7rem;}
  .day-toggle.active{background:var(--primary);border-color:var(--primary);color:#fff;}
  .settings-card{background:var(--surface);border:1px solid var(--border-color);border-radius:var(--radius-md);padding:1rem;}
  .settings-grid{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;}
  .settings-card .field-label{margin-top:.2rem;}
  .settings-card .btn-submit{margin-top:.75rem;}
  .settings-check{display:flex;align-items:center;gap:.45rem;color:var(--neutral-700);font-size:.78rem;margin-top:.55rem;}
  .settings-check input{accent-color:var(--primary);}
  .apply-monday{background:none;border:0;color:var(--primary);cursor:pointer;font-size:.72rem;font-weight:700;padding:0;}
  .schedule-empty{border:1px dashed var(--border-color);border-radius:var(--radius-md);color:var(--neutral-500);font-size:.8rem;padding:1rem;text-align:center;}
  .success-modal-backdrop{position:fixed;inset:0;background:rgba(23,32,51,.35);display:grid;place-items:center;padding:1rem;z-index:500;}
  .success-modal{background:var(--surface);border:1px solid var(--border-color);border-radius:var(--radius-lg);box-shadow:var(--shadow-md);max-width:360px;padding:1.5rem;text-align:center;width:100%;}
  .success-icon{align-items:center;background:var(--secondary-soft);border-radius:50%;color:var(--secondary-dark);display:flex;height:44px;justify-content:center;margin:0 auto .75rem;width:44px;}
  .success-icon svg{height:22px;width:22px;}
  .toast-bar{position:fixed;bottom:5rem;left:50%;transform:translateX(-50%);z-index:400;padding:0.75rem 1.4rem;border-radius:50px;font-size:0.85rem;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,0.15);white-space:nowrap;background:var(--green);color:#fff;animation:toastIn 0.3s ease,toastOut 0.4s 3s ease forwards;}
  .toast-bar.err{background:var(--red);}
  @keyframes toastIn{from{opacity:0;transform:translateX(-50%) translateY(12px)}to{opacity:1;transform:translateX(-50%) translateY(0)}}
  @keyframes toastOut{from{opacity:1}to{opacity:0;pointer-events:none}}
  .sched-pagination{display:none;align-items:center;justify-content:space-between;gap:.6rem;flex-wrap:wrap;margin-top:.75rem;}
  .sched-pagination button{background:var(--neutral-100);color:var(--neutral-700);border:none;border-radius:999px;padding:.4rem .9rem;font-size:.78rem;font-weight:700;cursor:pointer;font-family:inherit;}
  .sched-pagination button:disabled{opacity:.4;cursor:not-allowed;}
  .availability-layout > .card:first-child{grid-column:2;grid-row:1;}
  .availability-layout > .card:last-child{grid-column:1;grid-row:1;}
  @media(max-width:900px){.availability-layout{grid-template-columns:1fr;}.availability-layout > .card:first-child{grid-column:auto;grid-row:auto;order:2;}.availability-layout > .card:last-child{grid-column:auto;grid-row:auto;order:1;}.settings-card{order:-1;}}
  @media(max-width:600px){.sched-row{grid-template-columns:1fr 1fr auto;}.sched-row select:first-child{grid-column:1/-1;}.settings-grid{grid-template-columns:1fr;}.page{padding-left:.75rem!important;padding-right:.75rem!important;}}
</style>

<?php if ($toast): ?>
<div class="toast-bar">✓ <?= htmlspecialchars($toast) ?></div>
<div class="success-modal-backdrop" id="schedule-success-modal" role="dialog" aria-modal="true" aria-labelledby="schedule-success-title">
  <div class="success-modal">
    <div class="success-icon"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/></svg></div>
    <h2 id="schedule-success-title" style="font-size:1rem;margin-bottom:.35rem;">Schedule Saved Successfully</h2>
    <p style="color:var(--neutral-500);font-size:.8rem;margin-bottom:1rem;">Your availability is ready for appointments.</p>
    <div style="display:flex;gap:.55rem;"><a href="dashboard.php" class="btn-submit" style="text-decoration:none;">Dashboard</a><a href="appointments.php" class="btn-submit" style="text-decoration:none;">View Schedule</a></div>
  </div>
</div>
<?php elseif ($toast_error): ?>
<div class="toast-bar err">✕ <?= htmlspecialchars($toast_error) ?></div>
<?php endif; ?>

<div class="page">

  <div class="section-label">Schedule</div>

  <div class="availability-layout">
  <div class="card">
    <div class="section-label" style="margin-bottom:.6rem;">Consultation Fee</div>
    <form method="POST">
      <input type="hidden" name="action" value="update_fee"/>
      <div class="form-field">
        <label class="field-label" for="consultation_fee">Fee Amount (PHP)</label>
        <input
          id="consultation_fee"
          type="number"
          name="consultation_fee"
          class="field-input"
          min="0"
          max="100000"
          step="0.01"
          value="<?= htmlspecialchars((string)$doc['consultation_fee']) ?>"
          required
        />
      </div>
      <button type="submit" class="btn-submit">Save Fee</button>
    </form>
  </div>

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;flex-wrap:wrap;margin-bottom:.8rem;">
      <div class="section-label" style="margin-bottom:0;">Weekly Schedule</div>
      <button type="button" class="btn-add-sched" onclick="addSchedRow()">+ Add Row</button>
    </div>

    <form method="POST" id="schedule-form">
      <input type="hidden" name="action" value="update_schedule"/>

      <div class="section-label" style="margin-bottom:.35rem;">Working Days</div>
      <div class="day-picker" aria-label="Working days">
        <?php foreach ($daysOfWeek as $day): ?>
          <button type="button" class="day-toggle <?= in_array($day, $activeDays, true) ? 'active' : '' ?>" data-day="<?= $day ?>" onclick="toggleWorkingDay('<?= $day ?>')"><?= substr($day, 0, 3) ?></button>
        <?php endforeach; ?>
      </div>

      <div id="sched-rows" style="display:flex;flex-direction:column;gap:.6rem;">
        <?php if (!empty($schedules)): ?>
          <?php foreach ($schedules as $s): ?>
            <div class="sched-row">
              <select name="sched_day[]" class="sched-input" required>
                <?php foreach ($daysOfWeek as $d): ?>
                <option value="<?= $d ?>" <?= $s['day_of_week'] === $d ? 'selected' : '' ?>><?= $d ?></option>
                <?php endforeach; ?>
              </select>
              <select name="sched_start[]" class="sched-input" required>
                <option value="">-- Start --</option>
                <?php foreach ($timeOptions as $opt): ?>
                <option value="<?= $opt['val'] ?>" <?= $s['start_time'] === $opt['val'] ? 'selected' : '' ?>><?= $opt['lbl'] ?></option>
                <?php endforeach; ?>
              </select>
              <select name="sched_end[]" class="sched-input" required>
                <option value="">-- End --</option>
                <?php foreach ($timeOptions as $opt): ?>
                <option value="<?= $opt['val'] ?>" <?= $s['end_time'] === $opt['val'] ? 'selected' : '' ?>><?= $opt['lbl'] ?></option>
                <?php endforeach; ?>
              </select>
              <button type="button" onclick="removeSchedRow(this)" class="sched-remove">✕</button>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="sched-row">
            <select name="sched_day[]" class="sched-input" required>
              <?php foreach ($daysOfWeek as $d): ?>
              <option value="<?= $d ?>"><?= $d ?></option>
              <?php endforeach; ?>
            </select>
            <select name="sched_start[]" class="sched-input" required>
              <option value="">-- Start --</option>
              <?php foreach ($timeOptions as $opt): ?>
              <option value="<?= $opt['val'] ?>" <?= $opt['val'] === '09:00' ? 'selected' : '' ?>><?= $opt['lbl'] ?></option>
              <?php endforeach; ?>
            </select>
            <select name="sched_end[]" class="sched-input" required>
              <option value="">-- End --</option>
              <?php foreach ($timeOptions as $opt): ?>
              <option value="<?= $opt['val'] ?>" <?= $opt['val'] === '17:00' ? 'selected' : '' ?>><?= $opt['lbl'] ?></option>
              <?php endforeach; ?>
            </select>
            <button type="button" onclick="removeSchedRow(this)" class="sched-remove">✕</button>
          </div>
        <?php endif; ?>
      </div>

      <div id="sched-pagination" class="sched-pagination">
        <button type="button" id="sched-btn-prev" onclick="changeSchedPage(-1)">← Prev</button>
        <span id="sched-page-info" style="font-size:.78rem;color:var(--muted);"></span>
        <button type="button" id="sched-btn-next" onclick="changeSchedPage(1)">Next →</button>
      </div>

      <div class="settings-card" style="margin-top:1rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;margin-bottom:.8rem;">
          <div class="section-label" style="margin:0;">Consultation Settings</div>
          <button type="button" class="apply-monday" onclick="applyMondayHours()">Apply Monday hours to all</button>
        </div>
        <div class="settings-grid">
          <div><label class="field-label" for="consultation_duration">Consultation Duration</label><select class="sched-input" id="consultation_duration" name="consultation_duration"><option value="15" <?= (int)$settings['consultation_duration'] === 15 ? 'selected' : '' ?>>15 minutes</option><option value="30" <?= (int)$settings['consultation_duration'] === 30 ? 'selected' : '' ?>>30 minutes</option><option value="45" <?= (int)$settings['consultation_duration'] === 45 ? 'selected' : '' ?>>45 minutes</option><option value="60" <?= (int)$settings['consultation_duration'] === 60 ? 'selected' : '' ?>>60 minutes</option></select></div>
          <div><label class="field-label" for="appointment_interval">Appointment Interval</label><select class="sched-input" id="appointment_interval" name="appointment_interval"><option value="5" <?= (int)$settings['appointment_interval'] === 5 ? 'selected' : '' ?>>5 minutes</option><option value="10" <?= (int)$settings['appointment_interval'] === 10 ? 'selected' : '' ?>>10 minutes</option><option value="15" <?= (int)$settings['appointment_interval'] === 15 ? 'selected' : '' ?>>15 minutes</option><option value="30" <?= (int)$settings['appointment_interval'] === 30 ? 'selected' : '' ?>>30 minutes</option><option value="60" <?= (int)$settings['appointment_interval'] === 60 ? 'selected' : '' ?>>60 minutes</option></select></div>
          <div><label class="field-label" for="break_start">Break Start</label><select class="sched-input" id="break_start" name="break_start"><option value="">No break</option><?php foreach ($timeOptions as $opt): ?><option value="<?= $opt['val'] ?>" <?= $settings['break_start'] === $opt['val'] ? 'selected' : '' ?>><?= $opt['lbl'] ?></option><?php endforeach; ?></select></div>
          <div><label class="field-label" for="break_end">Break End</label><select class="sched-input" id="break_end" name="break_end"><option value="">No break</option><?php foreach ($timeOptions as $opt): ?><option value="<?= $opt['val'] ?>" <?= $settings['break_end'] === $opt['val'] ? 'selected' : '' ?>><?= $opt['lbl'] ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="field-label" style="margin-top:.85rem;">Consultation Type</div>
        <label class="settings-check"><input type="checkbox" name="consultation_types[]" value="Teleconsult" <?= in_array('Teleconsult', $selectedTypes, true) ? 'checked' : '' ?>/> Teleconsultation</label>
        <label class="settings-check"><input type="checkbox" name="consultation_types[]" value="In-person" <?= in_array('In-person', $selectedTypes, true) ? 'checked' : '' ?>/> In-person</label>
      </div>

      <div id="sched-error" class="alert-error" style="display:none;margin-top:.65rem;"></div>

      <button type="submit" class="btn-submit" style="margin-top:.9rem;">Save Schedule</button>
      <div style="display:flex;justify-content:flex-end;gap:.8rem;align-items:center;margin-top:.75rem;">
        <button type="button" class="apply-monday" onclick="resetScheduleDefaults()">Reset to Default</button>
        <a href="dashboard.php" class="apply-monday" style="text-decoration:none;">Cancel</a>
      </div>
    </form>
  </div>
  </div>

</div>

<script>
  const DAYS = <?= json_encode($daysOfWeek) ?>;
  const TIME_OPTIONS = <?= json_encode($timeOptions) ?>;
  const SCHED_PAGE_SIZE = 10;
  let schedPage = 1;

  function buildTimeSelect(name) {
    let html = `<select name="${name}" class="sched-input" required><option value="">-- ${name.includes('start') ? 'Start' : 'End'} --</option>`;
    TIME_OPTIONS.forEach(opt => {
      html += `<option value="${opt.val}">${opt.lbl}</option>`;
    });
    html += '</select>';
    return html;
  }

  function addSchedRow(day = 'Monday', start = '', end = '') {
    const row = document.createElement('div');
    row.className = 'sched-row';
    row.innerHTML = `
      <select name="sched_day[]" class="sched-input" required>
        ${DAYS.map(d => `<option value="${d}" ${d === day ? 'selected' : ''}>${d}</option>`).join('')}
      </select>
      ${buildTimeSelect('sched_start[]').replace('<select ', `<select data-value="${start}" `)}
      ${buildTimeSelect('sched_end[]').replace('<select ', `<select data-value="${end}" `)}
      <button type="button" onclick="removeSchedRow(this)" class="sched-remove">✕</button>
    `;
    document.getElementById('sched-rows').appendChild(row);
    row.querySelector('select[name="sched_start[]"]').value = start;
    row.querySelector('select[name="sched_end[]"]').value = end;
    attachRowRealtimeValidation(row);
    const totalRows = document.querySelectorAll('#sched-rows .sched-row').length;
    schedPage = Math.max(1, Math.ceil(totalRows / SCHED_PAGE_SIZE));
    renderSchedPage();
    validateScheduleClient(false);
    syncDayButtons();
  }

  function toggleWorkingDay(day) {
    const rows = Array.from(document.querySelectorAll('#sched-rows .sched-row'));
    const dayRows = rows.filter(row => row.querySelector('select[name="sched_day[]"]').value === day);
    if (dayRows.length) {
      dayRows.forEach(row => row.remove());
      if (!document.querySelector('#sched-rows .sched-row')) addSchedRow(day);
    } else {
      addSchedRow(day, '09:00', '17:00');
    }
    renderSchedPage();
    validateScheduleClient(false);
    syncDayButtons();
  }

  function syncDayButtons() {
    const active = new Set(Array.from(document.querySelectorAll('#sched-rows .sched-row select[name="sched_day[]"]')).map(select => select.value));
    document.querySelectorAll('.day-toggle').forEach(button => button.classList.toggle('active', active.has(button.dataset.day)));
  }

  function applyMondayHours() {
    const monday = Array.from(document.querySelectorAll('#sched-rows .sched-row')).find(row => row.querySelector('select[name="sched_day[]"]').value === 'Monday');
    if (!monday) {
      showScheduleError('Add Monday hours before applying them to other days.');
      return;
    }
    const start = monday.querySelector('select[name="sched_start[]"]').value;
    const end = monday.querySelector('select[name="sched_end[]"]').value;
    if (!start || !end) {
      showScheduleError('Complete Monday hours before applying them to other days.');
      return;
    }
    document.querySelectorAll('#sched-rows .sched-row').forEach(row => {
      const day = row.querySelector('select[name="sched_day[]"]').value;
      if (day !== 'Monday') {
        row.querySelector('select[name="sched_start[]"]').value = start;
        row.querySelector('select[name="sched_end[]"]').value = end;
      }
    });
    validateScheduleClient(false);
  }

  function resetScheduleDefaults() {
    document.querySelectorAll('#sched-rows .sched-row').forEach(row => row.remove());
    addSchedRow('Monday', '09:00', '17:00');
    document.getElementById('break_start').value = '';
    document.getElementById('break_end').value = '';
    document.getElementById('consultation_duration').value = '30';
    document.getElementById('appointment_interval').value = '5';
    document.querySelectorAll('input[name="consultation_types[]"]').forEach(input => input.checked = input.value === 'In-person');
    syncDayButtons();
  }

  function showScheduleError(message) {
    const errorEl = document.getElementById('sched-error');
    errorEl.textContent = message;
    errorEl.style.display = 'block';
  }

  function removeSchedRow(btn) {
    const rows = document.querySelectorAll('#sched-rows .sched-row');
    if (rows.length === 1) {
      const row = btn.closest('.sched-row');
      row.querySelectorAll('select').forEach(sel => {
        if (sel.name !== 'sched_day[]') sel.value = '';
      });
      renderSchedPage();
      validateScheduleClient(false);
      return;
    }
    btn.closest('.sched-row').remove();
    renderSchedPage();
    validateScheduleClient(false);
  }

  function renderSchedPage() {
    const rows = Array.from(document.querySelectorAll('#sched-rows .sched-row'));
    const total = rows.length;
    const totalPages = Math.max(1, Math.ceil(total / SCHED_PAGE_SIZE));

    schedPage = Math.min(Math.max(1, schedPage), totalPages);
    const start = (schedPage - 1) * SCHED_PAGE_SIZE;
    const end = start + SCHED_PAGE_SIZE;

    rows.forEach((row, idx) => {
      row.style.display = (idx >= start && idx < end) ? 'grid' : 'none';
    });

    const pager = document.getElementById('sched-pagination');
    const info = document.getElementById('sched-page-info');
    const prev = document.getElementById('sched-btn-prev');
    const next = document.getElementById('sched-btn-next');

    if (total > SCHED_PAGE_SIZE) {
      pager.style.display = 'flex';
      const viewStart = start + 1;
      const viewEnd = Math.min(end, total);
      info.textContent = `${viewStart}–${viewEnd} of ${total}`;
    } else {
      pager.style.display = 'none';
      info.textContent = total > 0 ? `1–${total} of ${total}` : '0 rows';
    }

    prev.disabled = schedPage <= 1;
    next.disabled = schedPage >= totalPages;
  }

  function changeSchedPage(delta) {
    schedPage += delta;
    renderSchedPage();
  }

  function validateScheduleClient(strictMode = true) {
    const rows = document.querySelectorAll('#sched-rows .sched-row');
    const errorEl = document.getElementById('sched-error');
    errorEl.style.display = 'none';
    errorEl.textContent = '';

    rows.forEach(r => r.style.outline = '');

    const list = [];
    const hasAnyTime = Array.from(rows).some(row => row.querySelector('select[name="sched_start[]"]').value || row.querySelector('select[name="sched_end[]"]').value);
    if (!hasAnyTime) return true;
    for (let i = 0; i < rows.length; i++) {
      const row = rows[i];
      const day = row.querySelector('select[name="sched_day[]"]').value;
      const start = row.querySelector('select[name="sched_start[]"]').value;
      const end = row.querySelector('select[name="sched_end[]"]').value;

      if (!day || !start || !end) {
        if (strictMode) {
          errorEl.textContent = 'Please complete all schedule row fields.';
          errorEl.style.display = 'block';
          row.style.outline = '2px solid rgba(195,54,67,.45)';
          return false;
        }
        continue;
      }

      if (start >= end) {
        errorEl.textContent = `End time must be after start time for ${day}.`;
        errorEl.style.display = 'block';
        row.style.outline = '2px solid rgba(195,54,67,.45)';
        return false;
      }

      list.push({day, start, end, row});
    }

    for (let i = 0; i < list.length; i++) {
      for (let j = i + 1; j < list.length; j++) {
        const a = list[i];
        const b = list[j];
        if (a.day === b.day && a.start < b.end && b.start < a.end) {
          errorEl.textContent = `Schedule conflict on ${a.day}: ${a.start}–${a.end} overlaps with ${b.start}–${b.end}.`;
          errorEl.style.display = 'block';
          a.row.style.outline = '2px solid rgba(195,54,67,.45)';
          b.row.style.outline = '2px solid rgba(195,54,67,.45)';
          return false;
        }
      }
    }

    const breakStart = document.getElementById('break_start').value;
    const breakEnd = document.getElementById('break_end').value;
    if ((breakStart && !breakEnd) || (!breakStart && breakEnd) || (breakStart && breakEnd && breakStart >= breakEnd)) {
      showScheduleError('Enter a valid break time range.');
      return false;
    }

    return true;
  }

  function attachRowRealtimeValidation(row) {
    row.querySelectorAll('select').forEach(sel => {
      sel.addEventListener('change', () => validateScheduleClient(false));
      sel.addEventListener('input', () => validateScheduleClient(false));
    });
  }

  document.querySelectorAll('#sched-rows .sched-row').forEach(attachRowRealtimeValidation);
  renderSchedPage();
  syncDayButtons();
  validateScheduleClient(false);

  document.getElementById('schedule-form').addEventListener('submit', function(e) {
    if (!validateScheduleClient(true)) {
      e.preventDefault();
    }
  });
</script>

<?php require_once 'includes/nav.php'; ?>
</body>
</html>
