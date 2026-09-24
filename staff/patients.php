<?php
// staff/patients.php
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

<style>
/* ── Patients page refresh (scoped additions, matches appointments.php) ── */
.sec-head{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1.2rem}
.sec-head h2{font-family:'Playfair Display',serif;font-size:1.55rem;font-weight:800;color:var(--text,#1a1a1a);margin:0}
.search-bar{border:1.5px solid var(--border,#e5e7eb);border-radius:12px;padding:.6rem 1rem;font-family:'DM Sans',sans-serif;font-size:.85rem;min-width:260px;outline:none;transition:border-color .2s,box-shadow .2s;background:#fff}
.search-bar:focus{border-color:var(--red,#8B1E2B);box-shadow:0 0 0 3px rgba(139,30,43,.1)}

.tbl-wrap{background:#fff;border:1.5px solid var(--border,#e5e7eb);border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.03)}
.tbl-wrap table{width:100%;border-collapse:collapse}
.tbl-wrap thead th{text-align:left;font-size:.7rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--muted,#6b7280);padding:.85rem 1rem;background:#fafafa;border-bottom:1.5px solid var(--border,#e5e7eb)}
.tbl-wrap tbody td{padding:.9rem 1rem;font-size:.86rem;border-bottom:1px solid var(--border,#f0f0f0);vertical-align:middle}
.tbl-wrap tbody tr:last-child td{border-bottom:none}
.tbl-wrap tbody tr:hover{background:#fafafa}
.empty-row{text-align:center;padding:2.5rem 1rem;color:var(--muted,#6b7280);font-size:.88rem}

.badge{display:inline-block;padding:.28rem .7rem;border-radius:50px;font-size:.72rem;font-weight:700}
.bg-green{background:rgba(34,197,94,.12);color:#15803d}
.bg-orange{background:rgba(245,158,11,.12);color:#b45309}
.bg-blue{background:rgba(63,130,227,.12);color:#1d4ed8}
.bg-red{background:rgba(239,68,68,.12);color:#b91c1c}

.btn-sm{border:none;border-radius:50px;padding:.42rem .85rem;font-size:.76rem;font-weight:700;font-family:'DM Sans',sans-serif;cursor:pointer;transition:transform .15s,opacity .15s}
.btn-sm:hover{transform:translateY(-1px)}

/* ── Modals ── */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(20,20,20,.5);z-index:9998;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal{background:#fff;border-radius:18px;padding:1.6rem;max-width:480px;width:92%;max-height:88vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.25)}
.modal h3{font-family:'Playfair Display',serif;font-size:1.25rem;font-weight:800;margin:0 0 1rem;color:var(--text,#1a1a1a)}
.f-label{display:block;font-size:.75rem;font-weight:700;color:var(--muted,#6b7280);margin:.8rem 0 .35rem;text-transform:uppercase;letter-spacing:.04em}
.f-input{width:100%;padding:.65rem .85rem;border:1.5px solid var(--border,#e5e7eb);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:.88rem;outline:none;margin-bottom:.6rem}
.f-input:focus{border-color:var(--red,#8B1E2B);box-shadow:0 0 0 3px rgba(139,30,43,.1)}
.btn-submit{width:100%;background:var(--red,#8B1E2B);color:#fff;border:none;border-radius:50px;padding:.75rem;font-weight:700;font-size:.88rem;cursor:pointer;margin-top:.4rem}
.btn-submit:hover{background:#5c131c}
.btn-cancel-modal{width:100%;background:transparent;border:1.5px solid var(--border,#e5e7eb);border-radius:50px;padding:.7rem;font-weight:700;font-size:.85rem;cursor:pointer;margin-top:.55rem;color:var(--muted,#6b7280)}
.btn-cancel-modal:hover{border-color:var(--red,#8B1E2B);color:var(--red,#8B1E2B)}

/* ── Patient avatar / row polish ── */
.pt-avatar-photo{width:38px;height:38px;border-radius:10px;object-fit:cover;flex-shrink:0}
.pt-avatar-fallback{width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,var(--red,#8B1E2B),#5c131c);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.78rem;flex-shrink:0}
.pt-name{font-weight:700;font-size:.87rem;color:var(--text,#1a1a1a)}
.pt-email{font-size:.72rem;color:var(--muted,#6b7280);margin-top:.05rem}
.pt-action-edit{background:rgba(139,30,43,.08);color:var(--red,#8B1E2B)}
.pt-action-edit:hover{background:rgba(139,30,43,.15)}
.pt-action-history{background:#f3f4f6;color:var(--text,#1a1a1a)}
.pt-action-history:hover{background:#e5e7eb}

/* ── Phone input combo ── */
.pt-phone-combo{display:flex;border:1.5px solid var(--border,#e5e7eb);border-radius:11px;overflow:hidden;background:#fff;margin-bottom:.25rem;transition:border-color .2s,box-shadow .2s}
.pt-phone-combo:focus-within{border-color:var(--red,#8B1E2B);box-shadow:0 0 0 3px rgba(139,30,43,.1)}
.pt-phone-prefix{display:flex;align-items:center;justify-content:center;padding:.68rem .82rem;background:#fafafa;border-right:1.5px solid var(--border,#e5e7eb);font-weight:700;color:var(--red,#8B1E2B);font-size:.88rem;min-width:60px}
.pt-phone-input{border:none;outline:none;flex:1;padding:.68rem .9rem;font-family:'DM Sans',sans-serif;font-size:.88rem;background:transparent}
.pt-hint{font-size:.72rem;color:var(--muted,#6b7280);margin:0 0 .65rem}
.pt-modal-note{font-size:.76rem;color:var(--muted,#6b7280);margin-bottom:.9rem;background:#fafafa;border-radius:10px;padding:.6rem .8rem}

/* ── History list ── */
.hist-row{display:flex;justify-content:space-between;align-items:center;padding:.7rem 0;border-bottom:1px solid var(--border,#f0f0f0)}
.hist-row:last-child{border-bottom:none}
.hist-date{font-weight:700;font-size:.87rem;color:var(--text,#1a1a1a)}
.hist-meta{font-size:.75rem;color:var(--muted,#6b7280);margin-top:.1rem}
.hist-empty{text-align:center;padding:2.2rem 1rem;color:var(--muted,#6b7280);font-size:.85rem}
.hist-pagination{display:none;align-items:center;justify-content:space-between;margin-top:.85rem;padding-top:.7rem;border-top:1px solid var(--border,#e5e7eb)}
.hist-page-info{font-size:.78rem;color:var(--muted,#6b7280)}
</style>

<div class="sec-head">
  <h2>Patient Management</h2>
  <input class="search-bar" placeholder="Search patient…" oninput="filterTable('patients-tbody', this.value)"/>
</div>

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
        <div style="display:flex;align-items:center;gap:.65rem">
          <?php if (!empty($p['profile_photo'])): ?>
          <img src="../<?= htmlspecialchars($p['profile_photo']) ?>" class="pt-avatar-photo"/>
          <?php else: ?>
          <div class="pt-avatar-fallback"><?= strtoupper(substr($p['full_name'], 0, 2)) ?></div>
          <?php endif ?>
          <div>
            <div class="pt-name"><?= htmlspecialchars($p['full_name']) ?></div>
            <div class="pt-email"><?= htmlspecialchars($p['email']) ?></div>
          </div>
        </div>
      </td>
      <td><?= htmlspecialchars($p['phone_number'] ?? '—') ?></td>
      <td><?= htmlspecialchars($p['city'] ?? '—') ?></td>
      <td><span class="badge bg-blue"><?= $p['appt_count'] ?> total</span></td>
      <td style="display:flex;gap:.4rem">
        <button class="btn-sm pt-action-edit"
                onclick="openEditPatient(<?= htmlspecialchars(json_encode($p)) ?>)">
          Edit Info
        </button>
        <button class="btn-sm pt-action-history"
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

      <div class="pt-modal-note">
        Update only the patient's contact details below.
      </div>

      <label class="f-label">Phone Number</label>
      <div class="pt-phone-combo">
        <div class="pt-phone-prefix">+63</div>
        <input
          type="tel"
          id="ep-phone-local"
          class="pt-phone-input"
          placeholder="9XXXXXXXXX"
          maxlength="10"
          inputmode="numeric"
        />
      </div>
      <input type="hidden" name="phone_number" id="ep-phone" />
      <div class="pt-hint">Enter 10-digit PH mobile starting with 9</div>

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
    <div id="history-pagination" class="hist-pagination">
      <button id="history-btn-prev" class="btn-sm pt-action-history" onclick="historyChangePage(-1)">&#8592; Prev</button>
      <span id="history-page-info" class="hist-page-info"></span>
      <button id="history-btn-next" class="btn-sm pt-action-history" onclick="historyChangePage(1)">Next &#8594;</button>
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
    html = '<div class="hist-empty">No appointment history.</div>';
  } else {
    html = slice.map(r => {
      const sc = r.status === 'Completed' ? 'bg-green'
               : r.status === 'Confirmed' ? 'bg-blue'
               : r.status === 'Pending'   ? 'bg-orange' : 'bg-red';
      const dateStr = new Date(r.appointment_date + 'T00:00')
        .toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
      return `
        <div class="hist-row">
          <div>
            <div class="hist-date">${dateStr}</div>
            <div class="hist-meta">Dr. ${r.doctor_name} · ${r.type}</div>
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

// ── Modal open/close (in case not already defined globally in style.css/sidebar.php) ──
if (typeof openModal !== 'function') {
  window.openModal = function(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('open');
  };
}
if (typeof closeModal !== 'function') {
  window.closeModal = function(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('open');
  };
}

// ── Simple table search (in case not already defined globally) ──
if (typeof filterTable !== 'function') {
  window.filterTable = function(tbodyId, query) {
    const q = query.trim().toLowerCase();
    document.querySelectorAll('#' + tbodyId + ' tr[data-search]').forEach(row => {
      row.style.display = !q || row.dataset.search.includes(q) ? '' : 'none';
    });
  };
}
</script>

<?php require_once 'includes/footer.php'; ?>