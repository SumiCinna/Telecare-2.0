<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

function normalizePhMobile(string $value): string {
  $digits = preg_replace('/\D+/', '', $value);

  if (str_starts_with($digits, '63')) {
    $digits = substr($digits, 2);
  }
  if (str_starts_with($digits, '0')) {
    $digits = substr($digits, 1);
  }

  if ($digits !== '' && $digits[0] !== '9') {
    $digits = '9' . substr($digits, 0, 9);
  }

  $digits = substr($digits, 0, 10);
  return '+63' . $digits;
}

// ── POST: update patient info ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_patient'])) {
    $pid   = (int)$_POST['patient_id'];
  $phone = normalizePhMobile((string)($_POST['phone_number'] ?? ''));
    $city  = trim($_POST['city'] ?? '');

  if (!preg_match('/^\+639\d{9}$/', $phone)) {
    $_SESSION['toast_error'] = 'Please enter a valid Philippine mobile number (e.g. +639XXXXXXXXX).';
    header('Location: patients.php');
    exit;
  }

  $stmt = $conn->prepare("UPDATE patients SET phone_number=?, city=? WHERE id=?");
  $stmt->bind_param("ssi", $phone, $city, $pid);
    $stmt->execute();

    $_SESSION['toast'] = "Patient info updated.";
    header('Location: patients.php');
    exit;
}

$toast = $_SESSION['toast'] ?? null;
$toast_error = $_SESSION['toast_error'] ?? null;
unset($_SESSION['toast'], $_SESSION['toast_error']);

$active_page = 'patients';

// ── Data ──
$stat_pending = (int)$conn->query("SELECT COUNT(*) c FROM appointments WHERE status='Pending'")->fetch_assoc()['c'];

$all_patients = $conn->query("
    SELECT p.*,
       (SELECT COUNT(*) FROM appointments WHERE patient_id = p.id) AS appt_count
    FROM patients p
    ORDER BY p.full_name ASC
");

// Appointment history keyed by patient_id (for JS)
$hist_rows = $conn->query("
    SELECT a.patient_id, a.appointment_date, a.appointment_time,
           a.status, a.type, d.full_name AS doctor_name
    FROM appointments a
    JOIN doctors d ON d.id = a.doctor_id
    ORDER BY a.appointment_date DESC
");
$history = [];
if ($hist_rows) {
    while ($h = $hist_rows->fetch_assoc()) {
        $history[$h['patient_id']][] = $h;
    }
}

require_once 'includes/header.php';
?>

<div class="sec-head">
  <h2>Patient Management</h2>
  <input class="search-bar" placeholder="Search patient…" oninput="filterTable('patients-tbody', this.value)"/>
</div>

<?php if ($toast): ?>
<div class="toast-bar success">✓ <?= htmlspecialchars($toast) ?></div>
<?php endif; ?>
<?php if ($toast_error): ?>
<div class="toast-bar error">✕ <?= htmlspecialchars($toast_error) ?></div>
<?php endif; ?>

<div class="tbl-wrap">
  <table>
    <thead>
      <tr>
        <th>Patient</th>
        <th>Contact</th>
        <th>City</th>
        <th>Appointments</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody id="patients-tbody">
    <?php
    if ($all_patients && $all_patients->num_rows > 0):
      while ($p = $all_patients->fetch_assoc()):
    ?>
    <tr data-search="<?= strtolower($p['full_name'] . ' ' . ($p['city'] ?? '')) ?>">
      <td>
        <div style="display:flex;align-items:center;gap:.6rem">
          <?php if (!empty($p['profile_photo'])): ?>
          <img src="../<?= htmlspecialchars($p['profile_photo']) ?>"
               style="width:32px;height:32px;border-radius:8px;object-fit:cover"/>
          <?php else: ?>
          <div style="width:32px;height:32px;border-radius:8px;background:var(--blue);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.8rem">
            <?= strtoupper(substr($p['full_name'], 0, 2)) ?>
          </div>
          <?php endif ?>
          <div>
            <div style="font-weight:700;font-size:.87rem"><?= htmlspecialchars($p['full_name']) ?></div>
            <div style="font-size:.72rem;color:var(--muted)"><?= htmlspecialchars($p['email']) ?></div>
          </div>
        </div>
      </td>
      <td><?= htmlspecialchars($p['phone_number'] ?? '—') ?></td>
      <td><?= htmlspecialchars($p['city'] ?? '—') ?></td>
      <td><span class="badge bg-blue"><?= $p['appt_count'] ?> total</span></td>
      <td style="display:flex;gap:.4rem">
        <button class="btn-sm"
                style="background:rgba(63,130,227,.1);color:var(--blue)"
                onclick="openEditPatient(<?= htmlspecialchars(json_encode($p)) ?>)">
          Edit Info
        </button>
        <button class="btn-sm"
                style="background:rgba(36,68,65,.07);color:var(--text)"
                onclick="openHistory(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['full_name'])) ?>')">
          History
        </button>
      </td>
    </tr>
    <?php endwhile; else: ?>
    <tr><td colspan="5" class="empty-row">No patients found.</td></tr>
    <?php endif ?>
    </tbody>
  </table>
</div>

<!-- ── Modal: Edit Patient ── -->
<div class="modal-overlay" id="modal-edit-patient">
  <div class="modal">
    <h3 id="edit-patient-title">Edit Patient Info</h3>
    <form method="POST">
      <input type="hidden" name="update_patient" value="1"/>
      <input type="hidden" name="patient_id" id="ep-id"/>

      <div style="font-size:.76rem;color:var(--muted);margin-bottom:.65rem;">
        Update only the patient's contact details below.
      </div>

      <label class="f-label">Phone Number</label>
      <div style="display:flex;border:1.5px solid rgba(36,68,65,.12);border-radius:11px;overflow:hidden;background:#fff;margin-bottom:.25rem;">
        <div style="display:flex;align-items:center;justify-content:center;padding:.68rem .82rem;background:rgba(36,68,65,.05);border-right:1.5px solid rgba(36,68,65,.12);font-weight:700;color:var(--green);font-size:.88rem;min-width:66px;">+63</div>
        <input
          type="tel"
          id="ep-phone-local"
          class="f-input"
          placeholder="9XXXXXXXXX"
          maxlength="10"
          inputmode="numeric"
          style="margin-bottom:0;border:none;border-radius:0;padding:.68rem .9rem;"
        />
      </div>
      <input type="hidden" name="phone_number" id="ep-phone" />
      <div style="font-size:.72rem;color:var(--muted);margin:0 0 .65rem;">Enter 10-digit PH mobile starting with 9</div>

      <label class="f-label">City / Municipality</label>
      <input type="text" name="city" id="ep-city" class="f-input" placeholder="Optional (e.g. Quezon City)"/>

      <button type="submit" class="btn-submit">Save Changes</button>
      <button type="button" class="btn-cancel-modal" onclick="closeModal('modal-edit-patient')">Cancel</button>
    </form>
  </div>
</div>

<!-- ── Modal: Appointment History ── -->
<div class="modal-overlay" id="modal-history">
  <div class="modal">
    <h3 id="history-title">Appointment History</h3>
    <div id="history-body" style="max-height:55vh;overflow-y:auto;margin-top:.5rem"></div>

    <!-- Pagination controls -->
    <div id="history-pagination"
         style="display:none;align-items:center;justify-content:space-between;
                margin-top:.85rem;padding-top:.6rem;border-top:1px solid rgba(36,68,65,.08)">
      <button id="history-btn-prev" class="btn-sm"
              style="background:rgba(36,68,65,.07);color:var(--text)"
              onclick="historyChangePage(-1)">&#8592; Prev</button>
      <span id="history-page-info" style="font-size:.78rem;color:var(--muted)"></span>
      <button id="history-btn-next" class="btn-sm"
              style="background:rgba(36,68,65,.07);color:var(--text)"
              onclick="historyChangePage(1)">Next &#8594;</button>
    </div>

    <button type="button" class="btn-cancel-modal" onclick="closeModal('modal-history')"
            style="margin-top:.6rem">Close</button>
  </div>
</div>

<!-- Patient history data injected for JS -->
<script>
const PATIENT_HISTORY = {};
<?php foreach ($history as $pid => $rows): ?>
PATIENT_HISTORY[<?= $pid ?>] = <?= json_encode($rows) ?>;
<?php endforeach ?>

const HISTORY_PAGE_SIZE = 10;
let _historyRows = [];
let _historyPage  = 1;

function renderHistoryPage() {
  const total      = _historyRows.length;
  const totalPages = Math.max(1, Math.ceil(total / HISTORY_PAGE_SIZE));
  _historyPage     = Math.min(Math.max(1, _historyPage), totalPages);

  const start = (_historyPage - 1) * HISTORY_PAGE_SIZE;
  const slice = _historyRows.slice(start, start + HISTORY_PAGE_SIZE);

  let html = '';
  if (slice.length === 0) {
    html = '<div style="text-align:center;padding:2rem;color:#9ab0ae">No appointment history.</div>';
  } else {
    html = slice.map(r => {
      const sc = r.status === 'Completed' ? 'bg-green'
               : r.status === 'Confirmed' ? 'bg-blue'
               : r.status === 'Pending'   ? 'bg-orange' : 'bg-red';
      const dateStr = new Date(r.appointment_date + 'T00:00')
        .toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
      return `
        <div style="display:flex;justify-content:space-between;align-items:center;
                    padding:.65rem 0;border-bottom:1px solid rgba(36,68,65,.06)">
          <div>
            <div style="font-weight:600;font-size:.87rem">${dateStr}</div>
            <div style="font-size:.75rem;color:#9ab0ae">Dr. ${r.doctor_name} · ${r.type}</div>
          </div>
          <span class="badge ${sc}">${r.status}</span>
        </div>`;
    }).join('');
  }

  document.getElementById('history-body').innerHTML = html;

  // Update pagination UI
  const end  = Math.min(start + HISTORY_PAGE_SIZE, total);
  const info = total > 0 ? `${start + 1}–${end} of ${total}` : '0 records';
  document.getElementById('history-page-info').textContent  = info;
  document.getElementById('history-btn-prev').disabled      = _historyPage <= 1;
  document.getElementById('history-btn-next').disabled      = _historyPage >= totalPages;
  document.getElementById('history-pagination').style.display =
    total > HISTORY_PAGE_SIZE ? 'flex' : 'none';
}

function historyChangePage(delta) {
  _historyPage += delta;
  renderHistoryPage();
}

function openHistory(pid, name) {
  _historyRows = PATIENT_HISTORY[pid] || [];
  _historyPage  = 1;
  document.getElementById('history-title').textContent = name + ' — History';
  renderHistoryPage();
  openModal('modal-history');
}

function openEditPatient(p) {
  document.getElementById('ep-id').value    = p.id;
  document.getElementById('ep-phone-local').value = getLocalPhMobileDigits(p.phone_number || '');
  document.getElementById('ep-phone').value = formatPhPhoneInput(document.getElementById('ep-phone-local').value);
  document.getElementById('ep-city').value  = p.city          || '';
  document.getElementById('edit-patient-title').textContent = 'Edit — ' + p.full_name;
  openModal('modal-edit-patient');
}

function getLocalPhMobileDigits(raw) {
  let digits = String(raw || '').replace(/\D/g, '');

  if (digits.startsWith('63')) digits = digits.slice(2);
  if (digits.startsWith('0')) digits = digits.slice(1);

  if (digits.length === 0) return '';
  if (digits[0] !== '9') digits = '9' + digits.slice(0, 9);
  return digits.slice(0, 10);
}

function formatPhPhoneInput(localDigits) {
  const mobile = getLocalPhMobileDigits(localDigits);
  return mobile ? ('+63' + mobile) : '';
}

const epPhoneLocal = document.getElementById('ep-phone-local');
const epPhone = document.getElementById('ep-phone');
if (epPhoneLocal && epPhone) {
  epPhoneLocal.addEventListener('input', () => {
    epPhoneLocal.value = getLocalPhMobileDigits(epPhoneLocal.value);
    epPhone.value = formatPhPhoneInput(epPhoneLocal.value);
  });

  epPhoneLocal.form?.addEventListener('submit', (e) => {
    epPhoneLocal.value = getLocalPhMobileDigits(epPhoneLocal.value);
    epPhone.value = formatPhPhoneInput(epPhoneLocal.value);
    if (!/^\+639\d{9}$/.test(epPhone.value)) {
      e.preventDefault();
      alert('Please enter a valid Philippine mobile number (e.g. +639XXXXXXXXX).');
      epPhoneLocal.focus();
    }
  });
}
</script>

<?php require_once 'includes/footer.php'; ?>