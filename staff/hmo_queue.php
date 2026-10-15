<?php
// staff/hmo_queue.php — HMO verification queue
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/hmo_helpers.php';

date_default_timezone_set('Asia/Manila');
$schemaOk = hmo_ensure_schema($conn);


$toast       = $_SESSION['toast'] ?? null;
$toast_error = $_SESSION['toast_error'] ?? null;
unset($_SESSION['toast'], $_SESSION['toast_error']);

$active_page = 'HMO';
$csrf   = hmo_csrf();
$counts = hmo_counts($conn);

$tab = (string)($_GET['status'] ?? 'Open');
$tabs = ['Open' => 'Needs work', 'Pending' => 'Pending', 'In Verification' => 'In verification', 'Action Required' => 'Action required',
         'Approved' => 'Approved', 'Not Approved' => 'Not approved', 'All' => 'All'];
if (!isset($tabs[$tab])) $tab = 'Open';

$rows = [];
if ($schemaOk) {
    $where = "a.status <> 'Cancelled'";
    $types = '';
    $args  = [];
    if ($tab === 'Open')      $where .= " AND h.coverage_status IN ('Pending','In Verification','Action Required')";
    elseif ($tab !== 'All')   { $where .= " AND h.coverage_status = ?"; $types .= 's'; $args[] = $tab; }

    $sql = "SELECT a.id, a.reference_no, a.appointment_date, a.appointment_time,
                   p.full_name AS patient_name, h.hmo_provider, h.coverage_status, h.loa_number, h.assistance_requested, h.patient_submitted_at,
                   (SELECT s2.full_name FROM hmo_case_events ev JOIN staff_accounts s2 ON s2.id = ev.staff_id
                    WHERE ev.appointment_id = h.appointment_id AND ev.staff_id IS NOT NULL ORDER BY ev.id DESC LIMIT 1) AS staff_name
            FROM appointment_hmo h
            JOIN appointments a ON a.id = h.appointment_id
            JOIN patients p ON p.id = a.patient_id
            WHERE $where
            ORDER BY (h.coverage_status IN ('Approved','Not Approved')) ASC, a.appointment_date ASC, a.appointment_time ASC
            LIMIT 300";
    $st = $conn->prepare($sql);
    if ($types !== '') $st->bind_param($types, ...$args);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
}

require_once 'includes/header.php';
$e = fn($x) => htmlspecialchars((string)$x, ENT_QUOTES);
$today = date('Y-m-d');
$holds = booking_hmo_holds($conn);
?>
<style>
.hq-stats{display:grid;grid-template-columns:repeat(6,1fr);gap:14px;margin-bottom:20px}
.hq-stat{display:block;text-decoration:none;background:var(--white);border:1px solid var(--border);border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);padding:16px;transition:.15s}
.hq-stat:hover{transform:translateY(-1px);box-shadow:var(--shadow-md)}
.hq-stat .n{font-size:24px;font-weight:800;line-height:1.1;margin-bottom:5px}
.hq-stat .l{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted)}
.hq-tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px;align-items:center}
.hq-tab{padding:7px 13px;border:1px solid var(--border);border-radius:50px;font-size:12px;font-weight:700;color:var(--neutral-700);text-decoration:none;background:#fff}
.hq-tab.active{background:var(--primary);border-color:var(--primary);color:#fff}
.hq-mine{margin-left:auto;font-size:12px;font-weight:600;color:var(--neutral-700);display:flex;gap:6px;align-items:center}
.hq-soon{color:var(--primary);font-weight:700}
.hq-note{background:#FFF8E8;border:1px solid #F5D9A0;color:#8a5a00;border-radius:var(--radius-md);padding:12px 14px;font-size:12px;margin-bottom:16px}
@media(max-width:1100px){.hq-stats{grid-template-columns:repeat(3,1fr)}}
@media(max-width:640px){.hq-stats{grid-template-columns:repeat(2,1fr)}.hq-tbl-scroll{overflow-x:auto}}
</style>

<div class="sec-head">
  <h2>HMO Verification Queue</h2>
  <input class="search-bar" placeholder="Search reference, patient, HMO…" oninput="filterTable('hq-tbody', this.value)"/>
</div>

<?php if (!$schemaOk): ?>
  <div class="hq-note">The HMO tables could not be prepared. Ask whoever manages the database to allow ALTER/CREATE for this app, or run <b>database/booking_payment_methods.sql</b> and <b>database/staff_hmo_verification.sql</b> once.</div>
<?php endif; ?>

<div class="hq-stats">
  <a class="hq-stat" href="?status=Pending"><div class="n" style="color:#B54708"><?= $counts['new'] ?></div><div class="l">New requests</div></a>
  <a class="hq-stat" href="?status=Open"><div class="n" style="color:var(--secondary-dark)"><?= $counts['pending'] ?></div><div class="l">Pending verification</div></a>
  <a class="hq-stat" href="?status=Action+Required"><div class="n" style="color:var(--primary)"><?= $counts['action'] ?></div><div class="l">Action required</div></a>
  <a class="hq-stat" href="?status=Approved"><div class="n" style="color:#067647"><?= $counts['approved'] ?></div><div class="l">Approved</div></a>
  <a class="hq-stat" href="?status=Not+Approved"><div class="n" style="color:var(--primary-dark)"><?= $counts['rejected'] ?></div><div class="l">Not approved</div></a>
  <a class="hq-stat" href="?status=Open"><div class="n" style="color:var(--neutral-900)"><?= $counts['upcoming_open'] ?></div><div class="l">Upcoming, unresolved</div></a>
</div>

<div class="hq-tabs">
  <?php foreach ($tabs as $k => $label): ?>
    <a class="hq-tab <?= $tab === $k ? 'active' : '' ?>" href="?status=<?= urlencode($k) ?>"><?= $e($label) ?></a>
  <?php endforeach; ?>
</div>

<div class="tbl-wrap hq-tbl-scroll">
  <table>
    <thead><tr>
      <th>Reference No.</th><th>Patient</th><th>HMO Provider</th><th>Appointment</th><th>HMO Status</th><th>Last handled by</th><th>Action</th>
    </tr></thead>
    <tbody id="hq-tbody">
    <?php if (!$rows): ?>
      <tr><td colspan="7" class="empty-row">No HMO cases here.</td></tr>
    <?php else: foreach ($rows as $r):
        $open = in_array($r['coverage_status'], HMO_OPEN_STATUSES, true);
        $soon = $open && $r['appointment_date'] <= date('Y-m-d', strtotime('+2 days'));
    ?>
      <tr data-search="<?= $e(strtolower($r['reference_no'] . ' ' . $r['patient_name'] . ' ' . $r['hmo_provider'])) ?>">
        <td><b><?= $e($r['reference_no'] ?: ('#' . $r['id'])) ?></b></td>
        <td><?= $e($r['patient_name']) ?></td>
        <td><?= $e($r['hmo_provider']) ?>
          <?php if (isset($holds[$r['hmo_provider']])): ?><div class="hq-soon" style="color:#B42318">On hold</div><?php endif; ?>
          <?php if (trim((string)$r['loa_number']) !== ''): ?><div class="hq-soon" style="color:#067647">LOA given</div><?php endif; ?>
          <?php if (!empty($r['assistance_requested'])): ?><div class="hq-soon" style="color:#B54708">Wants help with LOA</div><?php endif; ?>
        </td>
        <td>
          <?= $e(date('M j, Y', strtotime($r['appointment_date']))) ?> <?= $e(date('g:i A', strtotime($r['appointment_time']))) ?>
          <?php if ($soon): ?><div class="hq-soon"><?= $r['appointment_date'] < $today ? 'Date passed' : ($r['appointment_date'] === $today ? 'Today' : 'Soon') ?></div><?php endif; ?>
        </td>
        <td><span class="badge <?= hmo_status_class($r['coverage_status']) ?>"><?= $e($r['coverage_status']) ?></span></td>
        <td><?= $r['staff_name'] ? $e($r['staff_name']) : '<span style="color:var(--muted)">Not yet</span>' ?></td>
        <td style="white-space:nowrap">
          <a class="btn-primary btn-sm" href="hmo_case.php?id=<?= (int)$r['id'] ?>">Open</a>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php require_once 'includes/footer.php'; ?>
