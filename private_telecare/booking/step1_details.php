<?php
// private_telecare/booking/step1_details.php
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/booking_helpers.php';
require_once __DIR__ . '/../../ocr/ocr_api.php';

// Reason stays put even if the patient changes department later — it
// describes the patient's symptoms, not which department handles them.
$sel_department = $_SESSION['booking']['department']   ?? '';
$sel_reasons    = $_SESSION['booking']['reasons']       ?? [];
$sel_other      = $_SESSION['booking']['reason_other']  ?? '';

$error = null;
const MAX_MEDICAL_DOCS = 5;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dept    = trim($_POST['department'] ?? '');
    $reasons = $_POST['reasons'] ?? [];
    $other   = trim($_POST['reason_other'] ?? '');

    $valid_reasons = BOOKING_REASONS_BY_DEPT[$dept] ?? null;

    if (!array_key_exists($dept, BOOKING_DEPARTMENTS)) {
        $error = 'Please select a department.';
    } elseif (empty($reasons) && $other === '') {
        $error = 'Please select at least one reason for consultation.';
    } else {
        $_SESSION['booking']['department']   = $dept;
        $_SESSION['booking']['reasons']      = array_values(array_intersect($reasons, $valid_reasons ?? []));
        $_SESSION['booking']['reason_other'] = $other;

        // ── Optional: scan uploaded past medical documents (images only, up to 5) ──
        // Scanned text/type per file is stashed in session now and written to the
        // appointment row in process_booking.php. Where the patient/doctor
        // later *view* these documents is a separate feature — out of scope here.
        if (!empty($_FILES['medical_doc']['name'][0])) {
            $allowed_ext  = ['jpg', 'jpeg', 'png', 'webp'];
            $allowed_mime = ['image/jpeg', 'image/png', 'image/webp'];
            $upload_dir   = __DIR__ . '/../../uploads/patient_docs/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $names    = array_slice($_FILES['medical_doc']['name'],     0, MAX_MEDICAL_DOCS);
            $tmpNames = array_slice($_FILES['medical_doc']['tmp_name'], 0, MAX_MEDICAL_DOCS);

            $paths = [];
            $types = [];
            $texts = [];

            foreach ($names as $i => $origName) {
                $tmp = $tmpNames[$i] ?? '';
                if ($tmp === '' || !is_uploaded_file($tmp)) continue;

                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = $finfo ? finfo_file($finfo, $tmp) : '';
                if ($finfo) finfo_close($finfo);

                if (!in_array($ext, $allowed_ext, true) || !in_array($mime, $allowed_mime, true)) {
                    $error = 'One or more uploads were not valid image files (JPG, PNG, WEBP).';
                    continue;
                }

                $fname = uniqid('doc_' . $patient_id . '_') . '.' . $ext;

                if (move_uploaded_file($tmp, $upload_dir . $fname)) {
                    $paths[] = 'uploads/patient_docs/' . $fname;

                    $ocr = ocr_space_scan($upload_dir . $fname);
                    if ($ocr['success']) {
                        $types[] = $ocr['type'];
                        $texts[] = $ocr['text'];
                    } else {
                        $types[] = null;
                        $texts[] = null;
                    }
                }
            }

            if ($paths) {
                $_SESSION['booking']['attachment_paths']     = $paths;
                $_SESSION['booking']['attachment_types']     = $types;
                $_SESSION['booking']['attachment_ocr_texts'] = $texts;
            }
        }

        if (!$error) { header('Location: router.php?page=booking/step2_doctor'); exit; }
    }
}

$page_title = 'Book Appointment — TELE-CARE';
$active_nav = 'visits';
require_once __DIR__ . '/../../includes/header.php';
echo booking_wizard_css();
?>
<style>
.dept-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:0.9rem}
.dept-card{border:1.5px solid rgba(36,68,65,0.1);border-radius:14px;padding:1rem;cursor:pointer;transition:all .2s;position:relative}
.dept-card:hover{border-color:var(--blue)}
.dept-card.selected{border-color:var(--red);background:rgba(195,54,67,0.05)}
.dept-icon{width:38px;height:38px;border-radius:10px;background:rgba(195,54,67,0.08);color:var(--red);display:flex;align-items:center;justify-content:center;margin-bottom:0.6rem}
.dept-name{font-weight:700;font-size:0.9rem;color:var(--green)}
.dept-desc{font-size:0.76rem;color:var(--muted);margin-top:0.15rem}
.dept-check{position:absolute;top:0.6rem;right:0.6rem;width:20px;height:20px;border-radius:50%;background:var(--red);color:#fff;display:none;align-items:center;justify-content:center;font-size:0.7rem}
.dept-card.selected .dept-check{display:flex}
.reason-grid{display:grid;grid-template-columns:1fr 1fr;gap:0.7rem}
.reason-placeholder{font-size:0.85rem;color:var(--muted);padding:0.4rem 0;}
.reason-opt{display:flex;align-items:center;gap:0.6rem;border:1.5px solid rgba(36,68,65,0.1);border-radius:12px;padding:0.7rem 0.9rem;cursor:pointer;font-size:0.85rem;color:var(--green)}
.reason-opt input{accent-color:var(--red)}
.other-input{width:100%;margin-top:0.4rem;padding:0.65rem 0.9rem;border:1.5px solid rgba(36,68,65,0.12);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.85rem;color:var(--green)}
.upload-box{border:1.5px dashed rgba(36,68,65,0.2);border-radius:14px;padding:1.4rem;text-align:center;color:var(--green);font-size:0.85rem}
.upload-box input{margin-top:0.7rem;display:block;margin-left:auto;margin-right:auto}
.file-list{display:flex;flex-direction:column;gap:0.5rem;margin-top:1rem;text-align:left}
.file-item{display:flex;align-items:center;justify-content:space-between;gap:0.6rem;background:rgba(195,54,67,0.06);border:1.5px solid rgba(195,54,67,0.25);color:var(--green);font-weight:600;font-size:0.82rem;padding:0.55rem 0.9rem;border-radius:10px}
.file-item .fname{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.file-item .fremove{cursor:pointer;color:var(--red);font-weight:800;flex-shrink:0;padding:0 0.2rem}
.upload-warn{color:var(--red);font-weight:700;font-size:0.78rem;margin-top:0.6rem}
@media(max-width:700px){.dept-grid,.reason-grid{grid-template-columns:1fr}}
</style>

<div class="wiz-page">
  <div class="wiz-title">Book Appointment</div>
  <div class="wiz-sub">Schedule a new consultation with your preferred healthcare provider.</div>

  <?php render_stepper(1); ?>

  <?php if ($error): ?><div class="wiz-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <form method="POST" enctype="multipart/form-data" id="step1-form">
    <div class="wiz-card">
      <h3>1. Select Department</h3>
      <div class="dept-grid">
        <?php foreach (BOOKING_DEPARTMENTS as $name => $info): ?>
        <div class="dept-card <?= $sel_department === $name ? 'selected' : '' ?>" data-dept="<?= htmlspecialchars($name) ?>" onclick="pickDept(this)">
          <div class="dept-check">&#10003;</div>
          <div class="dept-icon"><?= dept_icon($info['icon']) ?></div>
          <div class="dept-name"><?= htmlspecialchars($name) ?></div>
          <div class="dept-desc"><?= htmlspecialchars($info['desc']) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <input type="hidden" name="department" id="department-input" value="<?= htmlspecialchars($sel_department) ?>"/>
    </div>

    <div class="wiz-card">
      <h3>2. Reason for Consultation</h3>
      <p id="reason-placeholder" class="reason-placeholder" style="<?= $sel_department ? 'display:none;' : '' ?>">Select a department above to see relevant consultation reasons.</p>
      <?php foreach (BOOKING_REASONS_BY_DEPT as $dept_name => $dept_reasons): ?>
      <div class="reason-grid" data-reason-group="<?= htmlspecialchars($dept_name) ?>" style="<?= $sel_department === $dept_name ? '' : 'display:none;' ?>">
        <?php foreach ($dept_reasons as $r): ?>
        <label class="reason-opt">
          <input type="checkbox" name="reasons[]" value="<?= htmlspecialchars($r) ?>" <?= ($sel_department === $dept_name && in_array($r, $sel_reasons, true)) ? 'checked' : '' ?> <?= $sel_department === $dept_name ? '' : 'disabled' ?>/>
          <?= htmlspecialchars($r) ?>
        </label>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
      <label style="display:block;font-size:0.72rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.05em;margin-top:0.9rem;">Other (optional)</label>
      <input type="text" name="reason_other" class="other-input" value="<?= htmlspecialchars($sel_other) ?>" placeholder="Describe your symptoms…"/>
      <p style="font-size:0.72rem;color:var(--muted);margin-top:0.6rem;">This stays the same even if you switch departments — it's just for the doctor's reference.</p>
    </div>

    <div class="wiz-card">
      <h3>3. Upload Past Medical Document <span style="font-weight:400;font-size:0.72rem;text-transform:none;">(optional, up to 5 images)</span></h3>
      <div class="upload-box" id="upload-box">
        <div id="upload-label">Upload a prescription or lab result — <strong>image files only</strong> (JPG, PNG, WEBP). You can select up to 5. We'll scan them automatically so they're on file for the doctor.</div>
        <input type="file" name="medical_doc[]" id="medical-doc-input" accept="image/*" multiple/>
        <div id="file-list" class="file-list"></div>
      </div>
    </div>

    <div class="wiz-actions">
            <a href="../router.php?page=visits" class="wiz-btn ghost">Cancel</a>
      <button type="submit" class="wiz-btn primary">Continue to Doctor Selection &rarr;</button>
    </div>
  </form>
</div>

<script>
function pickDept(el) {
  document.querySelectorAll('.dept-card').forEach(c => c.classList.remove('selected'));
  el.classList.add('selected');
  const dept = el.dataset.dept;
  document.getElementById('department-input').value = dept;

  document.getElementById('reason-placeholder').style.display = 'none';

  document.querySelectorAll('.reason-grid[data-reason-group]').forEach(group => {
    const isMatch = group.dataset.reasonGroup === dept;
    group.style.display = isMatch ? '' : 'none';
    group.querySelectorAll('input[type="checkbox"]').forEach(cb => {
      cb.disabled = !isMatch;
      if (!isMatch) cb.checked = false;
    });
  });
}

const docInput = document.getElementById('medical-doc-input');
const fileList  = document.getElementById('file-list');
const MAX_FILES = 5;

let selectedFiles = []; // persists across multiple picker openings

function fileKey(f) {
  return f.name + '|' + f.size + '|' + f.lastModified;
}

function syncInputFiles() {
  // Browsers won't let us append directly to input.files, so we rebuild
  // it from our own tracked array. This is also what actually submits.
  const dt = new DataTransfer();
  selectedFiles.forEach(f => dt.items.add(f));
  docInput.files = dt.files;
}

function renderFileList(warnMsg) {
  fileList.innerHTML = '';

  selectedFiles.forEach((f, idx) => {
    const item = document.createElement('div');
    item.className = 'file-item';

    const name = document.createElement('span');
    name.className = 'fname';
    name.textContent = f.name;

    const remove = document.createElement('span');
    remove.className = 'fremove';
    remove.textContent = '✕';
    remove.title = 'Remove';
    remove.addEventListener('click', () => {
      selectedFiles.splice(idx, 1);
      syncInputFiles();
      renderFileList();
    });

    item.appendChild(name);
    item.appendChild(remove);
    fileList.appendChild(item);
  });

  if (warnMsg) {
    const warn = document.createElement('div');
    warn.className = 'upload-warn';
    warn.textContent = warnMsg;
    fileList.appendChild(warn);
  }
}

docInput.addEventListener('change', () => {
  const incoming = Array.from(docInput.files);
  const existingKeys = new Set(selectedFiles.map(fileKey));

  let skippedDupes = 0;
  incoming.forEach(f => {
    const key = fileKey(f);
    if (existingKeys.has(key)) { skippedDupes++; return; }
    existingKeys.add(key);
    selectedFiles.push(f);
  });

  let warnMsg = null;
  if (selectedFiles.length > MAX_FILES) {
    const overflow = selectedFiles.length - MAX_FILES;
    selectedFiles = selectedFiles.slice(0, MAX_FILES);
    warnMsg = `Only the first ${MAX_FILES} images are kept (${overflow} extra removed).`;
  } else if (skippedDupes > 0) {
    warnMsg = `Skipped ${skippedDupes} file(s) already added.`;
  }

  syncInputFiles();
  renderFileList(warnMsg);
});
</script>

<?php require_once __DIR__ . '/../../includes/nav.php'; ?>
</body>
</html>