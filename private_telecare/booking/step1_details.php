<?php
// private_telecare/booking/step1_details.php
// Step 1 of 4: the patient describes their concern in their own words.
// The system then recommends a department (and doctors) in step 2.
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/booking_helpers.php';
require_once __DIR__ . '/../../ocr/ocr_api.php';

const MAX_MEDICAL_DOCS = 5;
const CONCERN_MIN = 10;
const CONCERN_MAX = 500; // appointments.reason is varchar(500)

$available_departments = booking_available_departments($conn);

$concern = $_SESSION['booking']['concern'] ?? '';
$error   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $concern = booking_clean($_POST['concern'] ?? '', CONCERN_MAX);

    if (empty($available_departments)) {
        $error = 'No departments are available for booking right now. Please check back later.';
    } elseif (mb_strlen($concern) < CONCERN_MIN) {
        $error = 'Please describe your concern in a little more detail (at least ' . CONCERN_MIN . ' characters).';
    } else {
        // A different concern means a different recommendation: drop everything chosen after this step.
        $prev = $_SESSION['booking']['concern'] ?? '';
        if ($prev !== $concern) {
            unset($_SESSION['booking']['rec'], $_SESSION['booking']['department'], $_SESSION['booking']['doctor_id'],
                  $_SESSION['booking']['appt_date'], $_SESSION['booking']['appt_time']);
        }
        $_SESSION['booking']['concern'] = $concern;

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

        if (!$error) {
            // Work out the recommendation now (the button shows "Finding..." while this runs),
            // so step 2 opens instantly.
            if (empty($_SESSION['booking']['rec'])) {
                $_SESSION['booking']['rec'] = booking_recommend_department($conn, $concern);
            }
            header('Location: router.php?page=booking/step2_doctor'); exit;
        }
    }
}

$page_title = 'Book Appointment — TELE-CARE';
$active_nav = 'visits';
require_once __DIR__ . '/../../includes/header.php';
echo booking_wizard_css();
?>
<style>
.concern-box{width:100%;box-sizing:border-box;min-height:140px;resize:vertical;padding:.85rem 1rem;border:1.5px solid rgba(36,68,65,0.15);border-radius:14px;font-family:'DM Sans',sans-serif;font-size:.95rem;line-height:1.5;color:var(--green)}
.concern-box:focus{outline:none;border-color:var(--red)}
.concern-meta{display:flex;justify-content:space-between;gap:1rem;margin-top:.45rem;font-size:.72rem;color:var(--muted)}
.concern-tips{margin-top:.9rem;font-size:.78rem;color:var(--green);background:rgba(36,68,65,.04);border-radius:12px;padding:.7rem .9rem;line-height:1.55}
.concern-tips strong{display:block;margin-bottom:.15rem}
.upload-box{border:1.5px dashed rgba(36,68,65,0.2);border-radius:14px;padding:1.4rem;text-align:center;color:var(--green);font-size:0.85rem}
.upload-box input{margin-top:0.7rem;display:block;margin-left:auto;margin-right:auto}
.file-list{display:flex;flex-direction:column;gap:0.5rem;margin-top:1rem;text-align:left}
.file-item{display:flex;align-items:center;justify-content:space-between;gap:0.6rem;background:rgba(195,54,67,0.06);border:1.5px solid rgba(195,54,67,0.25);color:var(--green);font-weight:600;font-size:.8rem;border-radius:10px;padding:.5rem .8rem}
.file-item .fname{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.file-item .fremove{cursor:pointer;color:var(--red);font-weight:800;flex-shrink:0;padding:0 0.2rem}
.upload-warn{color:var(--red);font-weight:700;font-size:0.78rem;margin-top:0.6rem}
.wiz-btn:disabled{opacity:.5;cursor:not-allowed}
</style>

<div class="wiz-page">
  <div class="wiz-title">Book Appointment</div>
  <div class="wiz-sub">Tell us what's bothering you. We'll suggest the right department and doctors.</div>

  <?php render_stepper(1); ?>

  <?php if ($error): ?><div class="wiz-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <form method="POST" enctype="multipart/form-data" id="step1-form">
    <div class="wiz-card">
      <h3>1. What is your concern?</h3>
      <textarea class="concern-box" name="concern" id="concern" maxlength="<?= CONCERN_MAX ?>" required
        placeholder="e.g. I've had a dry cough and mild fever for 3 days, and I get short of breath at night."><?= htmlspecialchars($concern) ?></textarea>
      <div class="concern-meta">
        <span>You can write in English or Filipino.</span>
        <span><span id="concern-count"><?= mb_strlen($concern) ?></span>/<?= CONCERN_MAX ?></span>
      </div>
      <div class="concern-tips">
        <strong>Helpful details</strong>
        What you feel, where, how long it has been going on, and anything you've already taken or tried.
        <br>Your description is analyzed by an AI service only to suggest a department. It is not a diagnosis.
      </div>
    </div>

    <div class="wiz-card">
      <h3>2. Upload Past Medical Document <span style="font-weight:400;font-size:0.72rem;text-transform:none;">(optional, up to 5 images)</span></h3>
      <div class="upload-box" id="upload-box">
        <div id="upload-label">Upload a prescription or lab result — <strong>image files only</strong> (JPG, PNG, WEBP). You can select up to 5. We'll scan them automatically so they're on file for the doctor.</div>
        <input type="file" name="medical_doc[]" id="medical-doc-input" accept="image/*" multiple/>
        <div id="file-list" class="file-list"></div>
      </div>
    </div>

    <div class="wiz-actions">
      <a href="../router.php?page=visits" class="wiz-btn ghost">Cancel</a>
      <button type="submit" id="go-btn" class="wiz-btn primary" <?= empty($available_departments) ? 'disabled' : '' ?>>Find the Right Doctor</button>
    </div>
  </form>
</div>

<script>
const concernEl = document.getElementById('concern');
concernEl.addEventListener('input', () => {
  document.getElementById('concern-count').textContent = concernEl.value.length;
});

// The recommendation can take a few seconds: show progress and stop double submits.
document.getElementById('step1-form').addEventListener('submit', () => {
  const b = document.getElementById('go-btn');
  setTimeout(() => { b.disabled = true; b.textContent = 'Finding the right doctor…'; }, 0);
});

const docInput = document.getElementById('medical-doc-input');
const fileList  = document.getElementById('file-list');
const MAX_FILES = 5;

let selectedFiles = [];

function fileKey(f) {
  return f.name + '|' + f.size + '|' + f.lastModified;
}

function syncInputFiles() {
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
