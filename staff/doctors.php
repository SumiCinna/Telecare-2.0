<?php
require_once 'includes/auth.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctorId = (int)($_POST['doctor_id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');

    if ($doctorId <= 0) {
        $_SESSION['toast_error'] = 'Invalid doctor selected.';
        header('Location: doctors.php');
        exit;
    }

    $docCheck = $conn->prepare('SELECT id FROM doctors WHERE id=? LIMIT 1');
    $docCheck->bind_param('i', $doctorId);
    $docCheck->execute();
    if (!$docCheck->get_result()->fetch_assoc()) {
        $_SESSION['toast_error'] = 'Doctor record not found.';
        header('Location: doctors.php');
        exit;
    }

    if ($action === 'update_fee') {
        $feeRaw = trim((string)($_POST['consultation_fee'] ?? ''));
        if ($feeRaw === '' || !is_numeric($feeRaw)) {
            $_SESSION['toast_error'] = 'Please enter a valid consultation fee.';
            header('Location: doctors.php?doctor_id=' . $doctorId);
            exit;
        }

        $fee = (float)$feeRaw;
        if ($fee < 0 || $fee > 100000) {
            $_SESSION['toast_error'] = 'Consultation fee must be between ₱0 and ₱100,000.';
            header('Location: doctors.php?doctor_id=' . $doctorId);
            exit;
        }

        $stmt = $conn->prepare('UPDATE doctors SET consultation_fee=? WHERE id=?');
        $stmt->bind_param('di', $fee, $doctorId);
        $stmt->execute();

        $_SESSION['toast'] = 'Doctor consultation fee updated.';
        header('Location: doctors.php?doctor_id=' . $doctorId);
        exit;
    }

    if ($action === 'update_schedule') {
        $days = $_POST['sched_day'] ?? [];
        $starts = $_POST['sched_start'] ?? [];
        $ends = $_POST['sched_end'] ?? [];

        [$ok, $slots, $error] = validateScheduleSlots($days, $starts, $ends);
        if (!$ok) {
            $_SESSION['toast_error'] = $error;
            header('Location: doctors.php?doctor_id=' . $doctorId);
            exit;
        }

        $conn->begin_transaction();
        try {
            $del = $conn->prepare('DELETE FROM doctor_schedules WHERE doctor_id=?');
            $del->bind_param('i', $doctorId);
            $del->execute();

            if (!empty($slots)) {
                $ins = $conn->prepare('INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time) VALUES (?,?,?,?)');
                foreach ($slots as $slot) {
                    $start = $slot['start'] . ':00';
                    $end = $slot['end'] . ':00';
                    $ins->bind_param('isss', $doctorId, $slot['day'], $start, $end);
                    $ins->execute();
                }
            }

            $conn->commit();
            $_SESSION['toast'] = 'Doctor schedule updated.';
        } catch (Throwable $e) {
            $conn->rollback();
            $_SESSION['toast_error'] = 'Failed to update schedule. Please try again.';
        }

        header('Location: doctors.php?doctor_id=' . $doctorId);
        exit;
    }
}

$toast = $_SESSION['toast'] ?? null;
$toast_error = $_SESSION['toast_error'] ?? null;
unset($_SESSION['toast'], $_SESSION['toast_error']);

$active_page = 'doctors';
$stat_pending = (int)$conn->query("SELECT COUNT(*) c FROM appointments WHERE status='Pending'")->fetch_assoc()['c'];

$doctorRows = $conn->query("SELECT id, full_name, specialty, consultation_fee, status FROM doctors ORDER BY full_name ASC");
$doctors = [];
if ($doctorRows) {
    while ($r = $doctorRows->fetch_assoc()) {
        $doctors[] = $r;
    }
}

$selectedDoctorId = (int)($_GET['doctor_id'] ?? 0);
if ($selectedDoctorId <= 0 && !empty($doctors)) {
    $selectedDoctorId = (int)$doctors[0]['id'];
}

$selectedDoctor = null;
foreach ($doctors as $doc) {
    if ((int)$doc['id'] === $selectedDoctorId) {
        $selectedDoctor = $doc;
        break;
    }
}

$schedules = [];
if ($selectedDoctor) {
    $sstmt = $conn->prepare("SELECT day_of_week, TIME_FORMAT(start_time, '%H:%i') AS start_time, TIME_FORMAT(end_time, '%H:%i') AS end_time
                             FROM doctor_schedules
                             WHERE doctor_id=?
                             ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), start_time");
    $sstmt->bind_param('i', $selectedDoctorId);
    $sstmt->execute();
    $sres = $sstmt->get_result();
    while ($s = $sres->fetch_assoc()) {
        $schedules[] = $s;
    }
}

$daysOfWeek = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
$timeOptions = generateTimeOptions();

require_once 'includes/header.php';
?>

<div class="sec-head">
  <h2>Doctor Fees &amp; Schedule</h2>
</div>

<div class="card" style="margin-bottom:1rem;">
  <div style="font-size:.74rem;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.35rem;">Select Doctor</div>
  <form method="GET" style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;">
    <select name="doctor_id" class="f-input" style="max-width:360px;margin-bottom:0;" onchange="this.form.submit()">
      <?php foreach ($doctors as $doc): ?>
      <option value="<?= (int)$doc['id'] ?>" <?= (int)$doc['id'] === $selectedDoctorId ? 'selected' : '' ?>>
        Dr. <?= htmlspecialchars($doc['full_name']) ?><?= !empty($doc['specialty']) ? ' — ' . htmlspecialchars($doc['specialty']) : '' ?>
      </option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<?php if (!$selectedDoctor): ?>
  <div class="card"><div class="empty-row">No doctor records found.</div></div>
<?php else: ?>

<div style="display:grid;grid-template-columns:minmax(260px,360px) 1fr;gap:1rem;align-items:start;">
  <div class="card" style="margin-bottom:0;">
    <div style="font-size:.74rem;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.55rem;">Consultation Fee</div>
    <div style="font-weight:700;font-size:.92rem;margin-bottom:.3rem;">Dr. <?= htmlspecialchars($selectedDoctor['full_name']) ?></div>
    <div style="font-size:.77rem;color:var(--muted);margin-bottom:1rem;"><?= htmlspecialchars($selectedDoctor['specialty'] ?? 'General') ?></div>

    <form method="POST">
      <input type="hidden" name="action" value="update_fee"/>
      <input type="hidden" name="doctor_id" value="<?= (int)$selectedDoctorId ?>"/>
      <label class="f-label" for="consultation_fee">Fee Amount (PHP)</label>
      <input
        id="consultation_fee"
        type="number"
        name="consultation_fee"
        class="f-input"
        min="0"
        max="100000"
        step="0.01"
        value="<?= htmlspecialchars((string)$selectedDoctor['consultation_fee']) ?>"
        required
      />
      <button type="submit" class="btn-primary">Save Fee</button>
    </form>
  </div>

  <div class="card" style="margin-bottom:0;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;flex-wrap:wrap;margin-bottom:.7rem;">
      <div style="font-size:.74rem;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.06em;">Weekly Schedule</div>
      <button type="button" class="btn-sm" style="background:rgba(63,130,227,.1);color:var(--blue);" onclick="addSchedRow()">+ Add Row</button>
    </div>

    <form method="POST" id="schedule-form">
      <input type="hidden" name="action" value="update_schedule"/>
      <input type="hidden" name="doctor_id" value="<?= (int)$selectedDoctorId ?>"/>

      <div id="sched-rows" style="display:flex;flex-direction:column;gap:.6rem;">
        <?php if (!empty($schedules)): ?>
          <?php foreach ($schedules as $s): ?>
            <div class="sched-row" style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:.55rem;align-items:center;">
              <select name="sched_day[]" class="f-input" style="margin-bottom:0;" required>
                <?php foreach ($daysOfWeek as $d): ?>
                <option value="<?= $d ?>" <?= $s['day_of_week'] === $d ? 'selected' : '' ?>><?= $d ?></option>
                <?php endforeach; ?>
              </select>
              <select name="sched_start[]" class="f-input" style="margin-bottom:0;" required>
                <option value="">-- Start --</option>
                <?php foreach ($timeOptions as $opt): ?>
                <option value="<?= $opt['val'] ?>" <?= $s['start_time'] === $opt['val'] ? 'selected' : '' ?>><?= $opt['lbl'] ?></option>
                <?php endforeach; ?>
              </select>
              <select name="sched_end[]" class="f-input" style="margin-bottom:0;" required>
                <option value="">-- End --</option>
                <?php foreach ($timeOptions as $opt): ?>
                <option value="<?= $opt['val'] ?>" <?= $s['end_time'] === $opt['val'] ? 'selected' : '' ?>><?= $opt['lbl'] ?></option>
                <?php endforeach; ?>
              </select>
              <button type="button" onclick="removeSchedRow(this)" class="btn-sm" style="background:rgba(195,54,67,.1);color:var(--red);">✕</button>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="sched-row" style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:.55rem;align-items:center;">
            <select name="sched_day[]" class="f-input" style="margin-bottom:0;" required>
              <?php foreach ($daysOfWeek as $d): ?>
              <option value="<?= $d ?>"><?= $d ?></option>
              <?php endforeach; ?>
            </select>
            <select name="sched_start[]" class="f-input" style="margin-bottom:0;" required>
              <option value="">-- Start --</option>
              <?php foreach ($timeOptions as $opt): ?>
              <option value="<?= $opt['val'] ?>"><?= $opt['lbl'] ?></option>
              <?php endforeach; ?>
            </select>
            <select name="sched_end[]" class="f-input" style="margin-bottom:0;" required>
              <option value="">-- End --</option>
              <?php foreach ($timeOptions as $opt): ?>
              <option value="<?= $opt['val'] ?>"><?= $opt['lbl'] ?></option>
              <?php endforeach; ?>
            </select>
            <button type="button" onclick="removeSchedRow(this)" class="btn-sm" style="background:rgba(195,54,67,.1);color:var(--red);">✕</button>
          </div>
        <?php endif; ?>
      </div>

      <div id="sched-pagination" style="display:none;align-items:center;justify-content:space-between;gap:.6rem;flex-wrap:wrap;margin-top:.75rem;">
        <button type="button" id="sched-btn-prev" class="btn-sm" style="background:rgba(36,68,65,.07);color:var(--text);" onclick="changeSchedPage(-1)">← Prev</button>
        <span id="sched-page-info" style="font-size:.78rem;color:var(--muted);"></span>
        <button type="button" id="sched-btn-next" class="btn-sm" style="background:rgba(36,68,65,.07);color:var(--text);" onclick="changeSchedPage(1)">Next →</button>
      </div>

      <div id="sched-error" style="display:none;margin-top:.65rem;padding:.55rem .7rem;border-radius:10px;background:rgba(195,54,67,.09);border:1px solid rgba(195,54,67,.2);color:var(--red);font-size:.78rem;font-weight:600;"></div>

      <button type="submit" class="btn-primary" style="margin-top:.9rem;">Save Schedule</button>
    </form>
  </div>
</div>

<script>
  const DAYS = <?= json_encode($daysOfWeek) ?>;
  const TIME_OPTIONS = <?= json_encode($timeOptions) ?>;
  const SCHED_PAGE_SIZE = 10;
  let schedPage = 1;

  function buildTimeSelect(name) {
    let html = `<select name="${name}" class="f-input" style="margin-bottom:0;" required><option value="">-- ${name.includes('start') ? 'Start' : 'End'} --</option>`;
    TIME_OPTIONS.forEach(opt => {
      html += `<option value="${opt.val}">${opt.lbl}</option>`;
    });
    html += '</select>';
    return html;
  }

  function addSchedRow() {
    const row = document.createElement('div');
    row.className = 'sched-row';
    row.style.cssText = 'display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:.55rem;align-items:center;';
    row.innerHTML = `
      <select name="sched_day[]" class="f-input" style="margin-bottom:0;" required>
        ${DAYS.map(d => `<option value="${d}">${d}</option>`).join('')}
      </select>
      ${buildTimeSelect('sched_start[]')}
      ${buildTimeSelect('sched_end[]')}
      <button type="button" onclick="removeSchedRow(this)" class="btn-sm" style="background:rgba(195,54,67,.1);color:var(--red);">✕</button>
    `;
    document.getElementById('sched-rows').appendChild(row);
    attachRowRealtimeValidation(row);
    const totalRows = document.querySelectorAll('#sched-rows .sched-row').length;
    schedPage = Math.max(1, Math.ceil(totalRows / SCHED_PAGE_SIZE));
    renderSchedPage();
    validateScheduleClient(false);
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
  validateScheduleClient(false);

  document.getElementById('schedule-form').addEventListener('submit', function(e) {
    if (!validateScheduleClient(true)) {
      e.preventDefault();
    }
  });
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
