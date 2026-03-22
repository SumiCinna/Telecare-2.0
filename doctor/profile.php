<?php
require_once 'includes/auth.php';

$success = '';
$error   = '';

// ── Update profile ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $phone   = trim($_POST['phone_number']      ?? '');
    $clinic  = trim($_POST['clinic_name']       ?? '');
    $fee     = floatval($_POST['consultation_fee'] ?? 0);
    $langs   = trim($_POST['languages_spoken']  ?? '');
    $bio     = trim($_POST['bio']               ?? '');
    $avail   = isset($_POST['is_available'])    ? 1 : 0;

    $photo_path = null;
    if (!empty($_FILES['profile_photo']['name'])) {
        $dir = '../uploads/profiles/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $ext   = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
        $fname = uniqid('doc_') . '.' . $ext;
        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $dir . $fname)) {
            $photo_path = 'uploads/profiles/' . $fname;
        }
    }

    if ($photo_path) {
        $stmt = $conn->prepare("UPDATE doctors SET phone_number=?, clinic_name=?, consultation_fee=?, languages_spoken=?, bio=?, is_available=?, profile_photo=? WHERE id=?");
        $stmt->bind_param("ssdssisi", $phone, $clinic, $fee, $langs, $bio, $avail, $photo_path, $doctor_id);
    } else {
        $stmt = $conn->prepare("UPDATE doctors SET phone_number=?, clinic_name=?, consultation_fee=?, languages_spoken=?, bio=?, is_available=? WHERE id=?");
        $stmt->bind_param("ssdssi", $phone, $clinic, $fee, $langs, $bio, $avail, $doctor_id);
    }
    $stmt->execute();
    $success = 'Profile updated successfully.';
    $stmt2 = $conn->prepare("SELECT * FROM doctors WHERE id=? LIMIT 1");
    $stmt2->bind_param("i", $doctor_id);
    $stmt2->execute();
    $doc = $stmt2->get_result()->fetch_assoc();
}

// ── Change password ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!password_verify($current, $doc['password'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } else {
        $hashed = password_hash($new, PASSWORD_BCRYPT);
        $stmt   = $conn->prepare("UPDATE doctors SET password=? WHERE id=?");
        $stmt->bind_param("si", $hashed, $doctor_id);
        $stmt->execute();
        $success = 'Password changed successfully.';
    }
}

// ── Update schedules ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_schedules'])) {

    $days   = $_POST['sched_day']   ?? [];
    $starts = $_POST['sched_start'] ?? [];
    $ends   = $_POST['sched_end']   ?? [];

    // ── Server-side overlap + end-after-start validation ──
    $sched_error = '';
    $slots = [];
    foreach ($days as $i => $day) {
        $day   = trim($day);
        $start = trim($starts[$i] ?? '');
        $end   = trim($ends[$i]   ?? '');
        if (!$day || !$start || !$end) continue;

        if ($start >= $end) {
            $sched_error = "Schedule error: End time must be after start time for $day.";
            break;
        }
        foreach ($slots as $ex) {
            if ($ex['day'] === $day && $start < $ex['end'] && $ex['start'] < $end) {
                $sched_error = "Schedule conflict on $day: " . $start . "–" . $end . " overlaps with " . $ex['start'] . "–" . $ex['end'] . ".";
                break 2;
            }
        }
        $slots[] = ['day' => $day, 'start' => $start, 'end' => $end];
    }

    if ($sched_error) {
        $error = $sched_error;
    } else {
        // Delete existing and reinsert
        $del = $conn->prepare("DELETE FROM doctor_schedules WHERE doctor_id=?");
        $del->bind_param("i", $doctor_id);
        $del->execute();

        $ins = $conn->prepare("INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time) VALUES (?,?,?,?)");
        foreach ($slots as $sl) {
            $ins->bind_param("isss", $doctor_id, $sl['day'], $sl['start'], $sl['end']);
            $ins->execute();
        }
        $success = 'Schedule updated successfully.';

        // Refresh schedules
        $sres = $conn->query("SELECT * FROM doctor_schedules WHERE doctor_id=$doctor_id ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')");
        $schedules = [];
        if ($sres) while ($s = $sres->fetch_assoc()) $schedules[] = $s;
    }
}

// ── Generate 30-min time options ──
function generateTimeOptions(): array {
    $opts = [];
    for ($h = 0; $h < 24; $h++) {
        foreach ([0, 30] as $m) {
            $val  = str_pad($h,2,'0',STR_PAD_LEFT).':'.str_pad($m,2,'0',STR_PAD_LEFT);
            $ap   = $h >= 12 ? 'PM' : 'AM';
            $hr   = $h % 12 ?: 12;
            $lbl  = $hr.':'.str_pad($m,2,'0',STR_PAD_LEFT).' '.$ap;
            $opts[] = ['val'=>$val,'lbl'=>$lbl];
        }
    }
    return $opts;
}

// ── Fetch schedules ──
$schedules = [];
$sres = $conn->query("SELECT * FROM doctor_schedules WHERE doctor_id=$doctor_id ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')");
if ($sres) while ($s = $sres->fetch_assoc()) $schedules[] = $s;

$page_title       = 'Profile — TELE-CARE';
$page_title_short = 'My Profile';
$active_nav       = 'profile';
require_once 'includes/header.php';

$days_of_week = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
?>

<style>
  .sched-row{display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:0.6rem;align-items:center;margin-bottom:0.6rem;}
  .sched-input{padding:0.6rem 0.7rem;border:1.5px solid rgba(36,68,65,0.12);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.85rem;color:var(--green);outline:none;width:100%;background:#fff;transition:border-color .2s;}
  .sched-input:focus{border-color:var(--blue);}
  select.sched-input{cursor:pointer;}
  .sched-remove{background:rgba(195,54,67,0.08);border:none;border-radius:8px;width:32px;height:32px;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--red);transition:all .2s;flex-shrink:0;}
  .sched-remove:hover{background:rgba(195,54,67,0.18);}
  .sched-chip{display:inline-flex;align-items:center;gap:0.5rem;background:rgba(36,68,65,0.07);border-radius:10px;padding:0.5rem 0.8rem;font-size:0.8rem;font-weight:600;color:var(--green);margin-bottom:0.4rem;}
  .sched-chip .day{font-weight:700;min-width:72px;}
  .sched-chip .time{color:var(--blue);font-size:0.78rem;}
  .no-sched{font-size:0.85rem;color:var(--muted);font-style:italic;padding:0.5rem 0;}
  .btn-add-sched{display:inline-flex;align-items:center;gap:0.4rem;background:rgba(63,130,227,0.1);color:var(--blue);border:none;border-radius:50px;padding:0.45rem 1rem;font-size:0.8rem;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s;margin-top:0.4rem;}
  .btn-add-sched:hover{background:rgba(63,130,227,0.18);}

  /* Modal overlay */
  .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.45);display:none;align-items:flex-end;justify-content:center;z-index:300;backdrop-filter:blur(4px);}
  .modal-overlay.open{display:flex;}
  .modal-sheet{background:#fff;border-radius:24px 24px 0 0;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;padding:1.5rem 1.4rem 2rem;animation:slideUp .3s cubic-bezier(.16,1,.3,1);}
  @keyframes slideUp{from{transform:translateY(100%)}to{transform:translateY(0)}}
  .modal-handle{width:40px;height:4px;background:rgba(0,0,0,0.1);border-radius:2px;margin:0 auto 1.2rem;}

  .section-label{font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);margin-bottom:0.8rem;}
  .sched-header{display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:0.6rem;margin-bottom:0.4rem;}
  .sched-header span{font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--muted);}
</style>

<div class="page">

  <?php if ($success): ?><div class="alert-success">✓ <?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="alert-error">  <?= htmlspecialchars($error)   ?></div><?php endif; ?>

  <!-- Profile Header -->
  <div class="card" style="text-align:center;padding:1.8rem 1rem;">
    <div style="width:72px;height:72px;border-radius:18px;background:linear-gradient(135deg,var(--green),var(--green-dark));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.4rem;margin:0 auto 0.8rem;overflow:hidden;">
      <?php if (!empty($doc['profile_photo'])): ?>
        <img src="../<?= htmlspecialchars($doc['profile_photo']) ?>" style="width:100%;height:100%;object-fit:cover;"/>
      <?php else: echo strtoupper(substr($doc['full_name'],0,2)); endif; ?>
    </div>
    <div style="font-family:'Playfair Display',serif;font-size:1.2rem;font-weight:700;">Dr. <?= htmlspecialchars($doc['full_name']) ?></div>
    <div style="font-size:0.83rem;color:var(--muted);margin-top:0.2rem;"><?= htmlspecialchars($doc['specialty'] ?? 'General Practitioner') ?></div>
    <?php if (!empty($doc['subspecialty'])): ?>
    <div style="font-size:0.78rem;color:var(--muted);margin-top:0.1rem;font-style:italic;"><?= htmlspecialchars($doc['subspecialty']) ?></div>
    <?php endif; ?>
    <div style="margin-top:0.6rem;display:flex;justify-content:center;gap:0.5rem;flex-wrap:wrap;">
      <?php if ($doc['is_verified']): ?><span class="badge badge-green">✓ Verified</span><?php endif; ?>
      <span class="badge <?= $doc['is_available']?'badge-green':'badge-gray' ?>"><?= $doc['is_available']?'Available':'Unavailable' ?></span>
    </div>
  </div>

  <!-- ── MY SCHEDULE ── -->
  <div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.8rem;">
      <div class="section-label" style="margin-bottom:0;">My Schedule</div>
      <button onclick="openModal()" style="display:inline-flex;align-items:center;gap:0.4rem;background:var(--blue);color:#fff;padding:0.4rem 0.9rem;border-radius:50px;font-size:0.78rem;font-weight:700;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;">
        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
        Edit Schedule
      </button>
    </div>

    <?php if (empty($schedules)): ?>
      <div class="no-sched">No schedule set yet. Tap Edit Schedule to add your availability.</div>
    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:0.4rem;">
        <?php
        function fmt12($t){ [$h,$m]=explode(':',$t); $ap=$h>=12?'PM':'AM'; $hr=$h%12?:12; return $hr.':'.str_pad($m,2,'0',STR_PAD_LEFT).' '.$ap; }
        foreach ($schedules as $s): ?>
        <div class="sched-chip">
          <span class="day"><?= htmlspecialchars($s['day_of_week']) ?></span>
          <span class="time"><?= fmt12($s['start_time']) ?> — <?= fmt12($s['end_time']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Edit Profile -->
  <div class="card">
    <div class="section-label">Edit Profile</div>
    <form method="POST" enctype="multipart/form-data">
      <div class="form-field">
        <label class="field-label">Profile Photo</label>
        <input type="file" name="profile_photo" class="field-input" accept="image/*" style="padding:0.5rem;"/>
      </div>
      <div class="form-field">
        <label class="field-label">Phone Number</label>
        <input type="tel" name="phone_number" class="field-input" value="<?= htmlspecialchars($doc['phone_number'] ?? '') ?>" placeholder="09XXXXXXXXX"/>
      </div>
      <div class="form-field">
        <label class="field-label">Clinic / Hospital</label>
        <input type="text" name="clinic_name" class="field-input" value="<?= htmlspecialchars($doc['clinic_name'] ?? '') ?>" placeholder="e.g. St. Luke's"/>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;">
        <div class="form-field">
          <label class="field-label">Consultation Fee (₱)</label>
          <input type="number" name="consultation_fee" class="field-input" value="<?= $doc['consultation_fee'] ?? 0 ?>" min="0" step="0.01" style="-moz-appearance:textfield;" onwheel="this.blur()"/>
          <style>input[name="consultation_fee"]::-webkit-outer-spin-button,input[name="consultation_fee"]::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}</style>
        </div>
        <div class="form-field">
          <label class="field-label">Languages</label>
          <input type="text" name="languages_spoken" class="field-input" value="<?= htmlspecialchars($doc['languages_spoken'] ?? '') ?>" placeholder="e.g. English"/>
        </div>
      </div>
      <div class="form-field">
        <label class="field-label">Bio</label>
        <textarea name="bio" class="field-input" rows="3" placeholder="Brief professional background..."><?= htmlspecialchars($doc['bio'] ?? '') ?></textarea>
      </div>
      <div style="display:flex;align-items:center;gap:0.7rem;margin-bottom:1rem;">
        <input type="checkbox" name="is_available" id="avail" <?= $doc['is_available']?'checked':'' ?> style="width:18px;height:18px;accent-color:var(--green);"/>
        <label for="avail" style="font-size:0.88rem;font-weight:600;cursor:pointer;">Available for appointments</label>
      </div>
      <button type="submit" name="update_profile" class="btn-submit">Save Changes</button>
    </form>
  </div>

  <!-- Change Password -->
  <div class="card">
    <div class="section-label">Change Password</div>
    <form method="POST">
      <div class="form-field">
        <label class="field-label">Current Password</label>
        <input type="password" name="current_password" class="field-input" required/>
      </div>
      <div class="form-field">
        <label class="field-label">New Password</label>
        <input type="password" name="new_password" class="field-input" placeholder="At least 8 characters" required/>
      </div>
      <div class="form-field">
        <label class="field-label">Confirm New Password</label>
        <input type="password" name="confirm_password" class="field-input" required/>
      </div>
      <button type="submit" name="change_password" class="btn-submit btn-red-submit">Change Password</button>
    </form>
  </div>

  <!-- Logout -->
  <div class="card" style="text-align:center;">
    <a href="logout.php" style="color:var(--red);font-weight:600;font-size:0.88rem;text-decoration:none;">Sign Out</a>
  </div>

</div>

<!-- ── SCHEDULE EDIT MODAL ── -->
<div class="modal-overlay" id="sched-modal">
  <div class="modal-sheet">
    <div class="modal-handle"></div>
    <div style="font-family:'Playfair Display',serif;font-size:1.15rem;font-weight:800;margin-bottom:0.3rem;">Edit My Schedule</div>
    <div style="font-size:0.78rem;color:var(--muted);margin-bottom:1.2rem;">Set the days and hours you're available for teleconsultations.</div>

    <form method="POST" id="sched-form">
      <input type="hidden" name="update_schedules" value="1"/>

      <!-- Column headers -->
      <div class="sched-header">
        <span>Day</span><span>Start Time</span><span>End Time</span><span></span>
      </div>

      <div id="sched-rows">
        <?php if (!empty($schedules)): ?>
          <?php foreach ($schedules as $s):
            $sv = substr($s['start_time'],0,5);
            $ev = substr($s['end_time'],0,5);
          ?>
          <div class="sched-row">
            <select name="sched_day[]" class="sched-input" required>
              <?php foreach ($days_of_week as $dw): ?>
              <option value="<?= $dw ?>" <?= $s['day_of_week']===$dw?'selected':'' ?>><?= $dw ?></option>
              <?php endforeach ?>
            </select>
            <select name="sched_start[]" class="sched-input" required>
              <option value="">-- Start --</option>
              <?php foreach(generateTimeOptions() as $opt): ?>
              <option value="<?= $opt['val'] ?>" <?= $sv===$opt['val']?'selected':'' ?>><?= $opt['lbl'] ?></option>
              <?php endforeach ?>
            </select>
            <select name="sched_end[]" class="sched-input" required>
              <option value="">-- End --</option>
              <?php foreach(generateTimeOptions() as $opt): ?>
              <option value="<?= $opt['val'] ?>" <?= $ev===$opt['val']?'selected':'' ?>><?= $opt['lbl'] ?></option>
              <?php endforeach ?>
            </select>
            <button type="button" class="sched-remove" onclick="removeRow(this)">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <?php endforeach ?>
        <?php else: ?>
          <!-- default empty row -->
          <div class="sched-row">
            <select name="sched_day[]" class="sched-input" required>
              <?php foreach ($days_of_week as $dw): ?><option value="<?= $dw ?>"><?= $dw ?></option><?php endforeach ?>
            </select>
            <select name="sched_start[]" class="sched-input" required>
              <option value="">-- Start --</option>
              <?php foreach(generateTimeOptions() as $opt): ?>
              <option value="<?= $opt['val'] ?>"><?= $opt['lbl'] ?></option>
              <?php endforeach ?>
            </select>
            <select name="sched_end[]" class="sched-input" required>
              <option value="">-- End --</option>
              <?php foreach(generateTimeOptions() as $opt): ?>
              <option value="<?= $opt['val'] ?>"><?= $opt['lbl'] ?></option>
              <?php endforeach ?>
            </select>
            <button type="button" class="sched-remove" onclick="removeRow(this)">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
        <?php endif ?>
      </div>

      <button type="button" class="btn-add-sched" onclick="addRow()">
        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        Add Another Day
      </button>

      <div id="sched-conflict-msg" style="display:none;font-size:0.78rem;color:#C33643;margin-top:0.5rem;background:rgba(195,54,67,0.07);border:1px solid rgba(195,54,67,0.2);border-radius:10px;padding:0.6rem 0.8rem;"></div>

      <div style="display:flex;gap:0.6rem;margin-top:1.2rem;">
        <button type="button" onclick="closeModal()" style="flex:1;padding:0.8rem;border-radius:50px;border:1.5px solid rgba(36,68,65,0.15);background:transparent;font-weight:600;font-size:0.88rem;cursor:pointer;font-family:'DM Sans',sans-serif;color:var(--green);">Cancel</button>
        <button type="submit" style="flex:2;padding:0.8rem;border-radius:50px;background:var(--blue);color:#fff;font-weight:700;font-size:0.9rem;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;box-shadow:0 4px 14px rgba(63,130,227,0.3);">Save Schedule</button>
      </div>
    </form>
  </div>
</div>

<script>
const DAYS = <?= json_encode($days_of_week) ?>;
const TIME_OPTS = <?php
    $opts = generateTimeOptions();
    echo json_encode(array_map(fn($o)=>$o, $opts));
?>;

function timeSelectHTML(name) {
  let html = `<select name="${name}" class="sched-input" required><option value="">-- ${name.includes('start')?'Start':'End'} --</option>`;
  TIME_OPTS.forEach(o => { html += `<option value="${o.val}">${o.lbl}</option>`; });
  html += '</select>';
  return html;
}

function fmt12h(t) {
  const [h, m] = t.split(':').map(Number);
  const ap = h >= 12 ? 'PM' : 'AM', hr = h % 12 || 12;
  return hr + ':' + String(m).padStart(2,'0') + ' ' + ap;
}

function revalidateSched() {
  const rows  = document.querySelectorAll('#sched-rows .sched-row');
  const msgEl = document.getElementById('sched-conflict-msg');
  rows.forEach(r => r.style.outline = '');
  msgEl.textContent = '';
  msgEl.style.display = 'none';

  const filled = [];
  let hasErr = false;

  rows.forEach((row, idx) => {
    const d = row.querySelector('[name="sched_day[]"]').value;
    const s = row.querySelector('[name="sched_start[]"]').value;
    const e = row.querySelector('[name="sched_end[]"]').value;
    if (!d || !s || !e) return;
    if (e <= s) {
      row.style.outline = '2px solid #C33643';
      if (!hasErr) {
        msgEl.textContent = `⚠ End time must be after start time on ${d}.`;
        msgEl.style.display = 'block';
        hasErr = true;
      }
      return;
    }
    filled.push({ d, s, e, idx });
  });

  if (hasErr) return false;

  for (let i = 0; i < filled.length; i++) {
    for (let j = i + 1; j < filled.length; j++) {
      const a = filled[i], b = filled[j];
      if (a.d === b.d && a.s < b.e && b.s < a.e) {
        rows[a.idx].style.outline = '2px solid #C33643';
        rows[b.idx].style.outline = '2px solid #C33643';
        msgEl.textContent = `⚠ Conflict on ${a.d}: ${fmt12h(a.s)}–${fmt12h(a.e)} overlaps with ${fmt12h(b.s)}–${fmt12h(b.e)}.`;
        msgEl.style.display = 'block';
        return false;
      }
    }
  }
  return true;
}

// Attach live validation to all selects in modal
function attachValidation(row) {
  row.querySelectorAll('select').forEach(s => s.addEventListener('change', revalidateSched));
}
document.querySelectorAll('#sched-rows .sched-row').forEach(attachValidation);

// Block submit if invalid
document.getElementById('sched-form').addEventListener('submit', function(e) {
  if (!revalidateSched()) {
    e.preventDefault();
    document.getElementById('sched-conflict-msg').scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
});
document.getElementById('sched-modal').addEventListener('click', e => {
  if (e.target.id === 'sched-modal') closeModal();
});

function openModal()  { document.getElementById('sched-modal').classList.add('open'); }
function closeModal() { document.getElementById('sched-modal').classList.remove('open'); }

function addRow() {
  const container = document.getElementById('sched-rows');
  const row = document.createElement('div');
  row.className = 'sched-row';
  row.innerHTML = `
    <select name="sched_day[]" class="sched-input" required>
      ${DAYS.map(d=>`<option value="${d}">${d}</option>`).join('')}
    </select>
    ${timeSelectHTML('sched_start[]')}
    ${timeSelectHTML('sched_end[]')}
    <button type="button" class="sched-remove" onclick="removeRow(this)">
      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
  `;
  container.appendChild(row);
  attachValidation(row);
  row.querySelector('select').focus();
}

function removeRow(btn) {
  const rows = document.querySelectorAll('#sched-rows .sched-row');
  if (rows.length === 1) {
    // Reset selects instead of removing last row
    const row = btn.closest('.sched-row');
    row.querySelectorAll('select[name="sched_start[]"], select[name="sched_end[]"]').forEach(s => s.value = '');
    revalidateSched();
    return;
  }
  btn.closest('.sched-row').remove();
  revalidateSched();
}
</script>

<?php require_once 'includes/nav.php'; ?>
</body>
</html>