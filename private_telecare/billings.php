<?php
// private_telecare/billings.php
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../includes/auth.php';
// billings.php — Patient's billing & payment history

// ── Auto-cancel any Pending/Unpaid appointment past its 10-minute payment window ──
$conn->query("UPDATE appointments SET status='Cancelled' WHERE status='Pending' AND payment_status='Unpaid' AND created_at < (NOW() - INTERVAL 10 MINUTE)");

// ── Summary stats ──
$stats_stmt = $conn->prepare("
    SELECT
      COALESCE(SUM(CASE WHEN a.payment_status='Unpaid' AND a.status='Pending' THEN d.consultation_fee ELSE 0 END),0) AS balance,
      COALESCE(SUM(CASE WHEN a.payment_status='Paid' THEN d.consultation_fee ELSE 0 END),0) AS total_paid,
      COALESCE(SUM(CASE WHEN a.payment_status='Paid' THEN 1 ELSE 0 END),0) AS paid_count
    FROM appointments a
    JOIN doctors d ON d.id = a.doctor_id
    WHERE a.patient_id = ?
");
$stats_stmt->bind_param("i", $patient_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// ── Oldest unpaid appointment (powers the "Pay Now" shortcut) ──
$due_stmt = $conn->prepare("
    SELECT a.id, d.consultation_fee
    FROM appointments a
    JOIN doctors d ON d.id = a.doctor_id
    WHERE a.patient_id = ? AND a.status='Pending' AND a.payment_status='Unpaid'
    ORDER BY a.created_at ASC LIMIT 1
");
$due_stmt->bind_param("i", $patient_id);
$due_stmt->execute();
$due = $due_stmt->get_result()->fetch_assoc();

// ── Transaction history: anything actually paid, or still awaiting payment ──
$tx_stmt = $conn->prepare("
    SELECT a.id, a.reference_no, a.appointment_date, a.appointment_time, a.type, a.status,
           a.payment_status, a.paid_at, a.created_at, a.receipt_number,
           d.full_name AS doctor_name, d.specialty, d.consultation_fee
    FROM appointments a
    JOIN doctors d ON d.id = a.doctor_id
    WHERE a.patient_id = ? AND (a.payment_status='Paid' OR (a.status='Pending' AND a.payment_status='Unpaid'))
    ORDER BY COALESCE(a.paid_at, a.created_at) DESC
");
$tx_stmt->bind_param("i", $patient_id);
$tx_stmt->execute();
$transactions = $tx_stmt->get_result();

$toast       = $_SESSION['toast']       ?? null;
$toast_error = $_SESSION['toast_error'] ?? null;
unset($_SESSION['toast'], $_SESSION['toast_error']);

$page_title = 'Billing — TELE-CARE';
$active_nav = 'billing';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');

:root{
  --tc-red:#B31118; --tc-red-dark:#8a000b;
  --tc-teal:#006a61; --tc-teal-light:#0D9488;
  --tc-ink:#151c27; --tc-muted:rgba(21,28,39,0.55);
  --tc-line:rgba(21,28,39,0.08);
  --tc-red-tint:#FEF2F2; --tc-teal-tint:#ECFDF5;
}

.page {
  max-width: 1320px !important;
  margin: 0 auto !important;
  padding: 1.8rem 2rem 5rem !important;
  background: transparent !important;
  overflow-x: clip;
  font-family:'Inter',sans-serif;
}
.page-head{ margin-bottom:1.25rem; }
.page-title{
  font-family:'Inter',sans-serif; font-weight:800; font-size:1.9rem;
  color:var(--tc-ink); line-height:1.15; margin-bottom:0.3rem;
}
.page-sub{ color:var(--tc-muted); font-size:0.92rem; }

.toast-bar{position:fixed;bottom:5.5rem;left:50%;transform:translateX(-50%);z-index:400;padding:0.75rem 1.4rem;border-radius:16px;font-size:0.82rem;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,0.15);white-space:normal;max-width:88vw;text-align:center;}
.toast-bar.success{background:var(--tc-teal);color:#fff;animation:toastOut 0.4s 3.5s ease forwards;}
.toast-bar.error{background:#C33643;color:#fff;}
@keyframes toastOut{from{opacity:1}to{opacity:0;pointer-events:none}}

/* ── STATS ROW ── */
.stats-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.1rem;
  margin-bottom: 1.1rem;
}
.stat-card {
  background: #fff;
  border-radius: 16px;
  padding: 1.2rem 1.3rem;
  border: 1px solid var(--tc-line);
  box-shadow: 0 2px 12px rgba(21,28,39,0.05);
  display: flex; flex-direction: column; gap: 0.55rem;
  position: relative; overflow: hidden;
}
.stat-card::after {
  content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 2px;
}
.stat-card.s-balance::after { background: var(--tc-red); }
.stat-card.s-paid::after    { background: var(--tc-teal); }
.stat-card.s-count::after   { background: var(--tc-teal-light); }
.stat-top{ display:flex; align-items:center; gap:0.7rem; }
.stat-icon {
  width: 38px; height: 38px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.stat-icon svg { width: 18px; height: 18px; }
.si-red  { background: var(--tc-red-tint);  color: var(--tc-red); }
.si-teal { background: var(--tc-teal-tint); color: var(--tc-teal); }
.stat-num {
  font-family: 'Inter', sans-serif;
  font-size: 1.75rem; font-weight: 800; line-height: 1;
  color: var(--tc-ink);
}
.stat-num.red  { color: var(--tc-red); }
.stat-num.teal { color: var(--tc-teal); }
.stat-lbl { font-size: 0.72rem; color: var(--tc-muted); font-weight: 600; }
.stat-pay-btn{
  display:inline-flex; align-items:center; justify-content:center; gap:0.35rem;
  background:var(--tc-red); color:#fff; border:none; border-radius:9px;
  padding:0.48rem 0.8rem; font-size:0.76rem; font-weight:700; text-decoration:none;
  box-shadow:0 4px 12px rgba(179,17,24,0.22); transition:all 0.2s; margin-top:0.2rem;
  width:fit-content;
}
.stat-pay-btn svg{ width:15px; height:15px; flex-shrink:0; }
.stat-pay-btn:hover{ background:var(--tc-red-dark); transform:translateY(-1px); }
.stat-note{ font-size:0.72rem; color:var(--tc-muted); margin-top:0.2rem; }

/* ── LAYOUT ── */
.bill-grid{ display:grid; grid-template-columns: 2fr 1fr; gap: 1.1rem; align-items:start; }

.bx-card {
  background: #fff;
  border-radius: 16px;
  border: 1px solid var(--tc-line);
  box-shadow: 0 2px 12px rgba(21,28,39,0.05);
  overflow: hidden;
}
.bx-head {
  display: flex; justify-content: space-between; align-items: center; gap:0.8rem;
  padding: 1.2rem 1.4rem 0;
  flex-wrap: wrap;
}
.bx-title {
  font-size: 0.95rem; font-weight: 700; color: var(--tc-ink);
  display: flex; align-items: center; gap: 0.5rem;
}
.bx-title svg { width: 16px; height: 16px; flex-shrink: 0; color: var(--tc-red); }
.bx-body { padding: 0.9rem 1.4rem 1.4rem; }

/* ── FILTER TABS ── */
.tx-tabs{ display:flex; gap:0.4rem; padding:1rem 1.4rem 0; }
.tx-tab{
  border:1px solid var(--tc-line); background:#fff; color:var(--tc-muted);
  font-size:0.76rem; font-weight:700; padding:0.4rem 0.85rem; border-radius:50px;
  cursor:pointer; transition:all 0.2s;
}
.tx-tab.active{ background:var(--tc-red); border-color:var(--tc-red); color:#fff; }

/* ── TABLE ── */
.tx-table-wrap{ overflow-x:auto; }
.tx-table{ width:100%; border-collapse:collapse; font-size:0.83rem; }
.tx-table th{
  text-align:left; font-size:0.68rem; font-weight:700; letter-spacing:0.04em;
  text-transform:uppercase; color:var(--tc-muted); padding:0.7rem 0.6rem;
  border-bottom:1px solid var(--tc-line); white-space:nowrap;
}
.tx-table td{
  padding:0.85rem 0.6rem; border-bottom:1px solid rgba(21,28,39,0.05);
  color:var(--tc-ink); vertical-align:middle;
}
.tx-table tr:last-child td{ border-bottom:none; }
.tx-desc-main{ font-weight:700; font-size:0.85rem; }
.tx-desc-sub{ font-size:0.74rem; color:var(--tc-muted); margin-top:0.1rem; }
.tx-amount{ font-weight:800; font-family:'Inter',sans-serif; white-space:nowrap; }
.tx-pay{ display:flex; align-items:center; gap:0.35rem; color:var(--tc-muted); font-size:0.78rem; white-space:nowrap; }
.tx-pay svg{ width:13px; height:13px; flex-shrink:0; }

.badge {
  font-size: 0.64rem; font-weight: 700; letter-spacing: 0.05em;
  text-transform: uppercase; padding: 0.22rem 0.65rem; border-radius: 50px;
  white-space: nowrap; display:inline-block;
}
.badge-green  { background: var(--tc-teal-tint);  color: var(--tc-teal); }
.badge-orange { background: rgba(234,179,8,0.12);  color: #ca8a04; }

.tx-action-link{
  display:inline-flex; align-items:center; gap:0.3rem;
  font-size:0.76rem; font-weight:700; text-decoration:none; white-space:nowrap;
}
.tx-action-link svg{ width:13px; height:13px; flex-shrink:0; }
.tx-action-view{ color:var(--tc-teal); }
.tx-action-pay{ color:var(--tc-red); }

/* ── INVOICES SIDE CARD ── */
.inv-item{
  display:flex; align-items:center; justify-content:space-between; gap:0.6rem;
  padding:0.75rem 0; border-bottom:1px solid rgba(21,28,39,0.05);
}
.inv-item:last-child{ border-bottom:none; }
.inv-no{ font-weight:700; font-size:0.82rem; color:var(--tc-ink); }
.inv-meta{ font-size:0.72rem; color:var(--tc-muted); margin-top:0.15rem; }
.inv-amt{ font-size:0.72rem; color:var(--tc-muted); margin-top:0.15rem; }
.inv-view{
  width:30px; height:30px; border-radius:8px; flex-shrink:0;
  display:flex; align-items:center; justify-content:center;
  background:var(--tc-teal-tint); color:var(--tc-teal); text-decoration:none;
}
.inv-view svg{ width:15px; height:15px; }

/* ── EMPTY STATE ── */
.empty-state {
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; text-align: center;
  padding: 2.2rem 1rem; color: rgba(21,28,39,0.28);
  gap: 0.7rem;
}
.empty-state svg { width: 34px; height: 34px; opacity: 0.5; }
.empty-state p { font-size: 0.85rem; font-weight: 500; }

@media (max-width: 900px) {
  .page { padding: 1rem 1rem 6rem !important; }
  .page-title{ font-size: 1.55rem; }
  .stats-row { grid-template-columns: 1fr; gap: 0.75rem; }
  .bill-grid{ grid-template-columns: 1fr; }
  .bx-head{ padding: 1rem 1.1rem 0; }
  .bx-body{ padding: 0.8rem 1.1rem 1.1rem; }
  .tx-tabs{ padding: 1rem 1.1rem 0; }
}
</style>

<?php if ($toast): ?><div class="toast-bar success">✓ <?= htmlspecialchars($toast) ?></div><?php endif; ?>
<?php if ($toast_error): ?><div class="toast-bar error">✕ <?= htmlspecialchars($toast_error) ?></div><?php endif; ?>

<div class="page">

  <div class="page-head">
    <div class="page-title">Billing &amp; Payments</div>
    <p class="page-sub">View and manage your medical invoices, payments, and consultation history.</p>
  </div>

  <!-- ══ STATS ══ -->
  <div class="stats-row">

    <div class="stat-card s-balance">
      <div class="stat-top">
        <div class="stat-icon si-red">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h3"/></svg>
        </div>
        <div>
          <div class="stat-num red">₱<?= number_format($stats['balance'], 2) ?></div>
          <div class="stat-lbl">Current Balance</div>
        </div>
      </div>
      <?php if ($stats['balance'] > 0 && $due): ?>
        <a href="router.php?page=pay&appt_id=<?= (int)$due['id'] ?>" class="stat-pay-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
          Pay Now
        </a>
      <?php else: ?>
        <div class="stat-note">Nothing due right now.</div>
      <?php endif; ?>
    </div>

    <div class="stat-card s-paid">
      <div class="stat-top">
        <div class="stat-icon si-teal">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8c-1.66 0-3 .9-3 2s1.34 2 3 2 3 .9 3 2-1.34 2-3 2m0-8V6m0 12v-2m0-8c1.11 0 2.08.402 2.6 1"/><circle cx="12" cy="12" r="9"/></svg>
        </div>
        <div>
          <div class="stat-num teal">₱<?= number_format($stats['total_paid'], 2) ?></div>
          <div class="stat-lbl">Total Paid (All Time)</div>
        </div>
      </div>
    </div>

    <div class="stat-card s-count">
      <div class="stat-top">
        <div class="stat-icon si-teal">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2M9 12h6M9 16h6"/></svg>
        </div>
        <div>
          <div class="stat-num teal"><?= (int)$stats['paid_count'] ?></div>
          <div class="stat-lbl">Paid Transactions</div>
        </div>
      </div>
    </div>

  </div>

  <div class="bill-grid">

    <!-- ══ TRANSACTIONS ══ -->
    <div class="bx-card">
      <div class="bx-head">
        <div class="bx-title">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h9l3 3v15H6a2 2 0 01-2-2V5a2 2 0 012-2z"/><path d="M14 3v4h4M8 11h6M8 15h6M8 19h4"/></svg>
          Transaction History
        </div>
      </div>
      <div class="tx-tabs">
        <button type="button" class="tx-tab active" data-filter="all">All</button>
        <button type="button" class="tx-tab" data-filter="paid">Paid</button>
        <button type="button" class="tx-tab" data-filter="unpaid">Unpaid</button>
      </div>
      <div class="bx-body">
        <div class="tx-table-wrap">
        <table class="tx-table" id="txTable">
          <thead>
            <tr>
              <th>Date</th>
              <th>Description</th>
              <th>Amount</th>
              <th>Payment</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php
            $has = false;
            if ($transactions && $transactions->num_rows > 0):
              while ($t = $transactions->fetch_assoc()):
                $has     = true;
                $isPaid  = $t['payment_status'] === 'Paid';
                $rowDate = $isPaid && !empty($t['paid_at']) ? new DateTime($t['paid_at']) : new DateTime($t['appointment_date']);
                $fee     = floatval($t['consultation_fee'] ?? 0);
                $rowClass = $isPaid ? 'paid' : 'unpaid';
            ?>
            <tr data-status="<?= $rowClass ?>">
              <td><?= $rowDate->format('M j, Y') ?></td>
              <td>
                <div class="tx-desc-main"><?= htmlspecialchars($t['type']) ?> Consultation</div>
                <div class="tx-desc-sub">Dr. <?= htmlspecialchars($t['doctor_name']) ?><?= !empty($t['specialty']) ? ' · '.htmlspecialchars($t['specialty']) : '' ?></div>
              </td>
              <td class="tx-amount">₱<?= number_format($fee, 2) ?></td>
              <td>
                <?php if ($isPaid): ?>
                <span class="tx-pay">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                  PayMongo
                </span>
                <?php else: ?>
                <span class="tx-pay">—</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($isPaid): ?>
                  <span class="badge badge-green">Paid</span>
                <?php else: ?>
                  <span class="badge badge-orange">Unpaid</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($isPaid): ?>
                  <a href="router.php?page=receipt&appt_id=<?= (int)$t['id'] ?>" class="tx-action-link tx-action-view">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                    View
                  </a>
                <?php else: ?>
                  <a href="router.php?page=pay&appt_id=<?= (int)$t['id'] ?>" class="tx-action-link tx-action-pay">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    Pay
                  </a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endwhile; endif; ?>
          </tbody>
        </table>
        </div>
        <?php if (!$has): ?>
        <div class="empty-state">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h9l3 3v15H6a2 2 0 01-2-2V5a2 2 0 012-2z"/><path d="M14 3v4h4M8 11h6M8 15h6M8 19h4"/></svg>
          <p>No billing history yet.</p>
        </div>
        <?php endif; ?>
        <div class="empty-state" id="txEmptyFiltered" style="display:none;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
          <p>No transactions match this filter.</p>
        </div>
      </div>
    </div>

    <!-- ══ INVOICES & RECEIPTS ══ -->
    <div class="bx-card">
      <div class="bx-head">
        <div class="bx-title">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h9l3 3v15H6a2 2 0 01-2-2V5a2 2 0 012-2z"/><path d="M14 3v4h4M8 11h6M8 15h6M8 19h4"/></svg>
          Invoices &amp; Receipts
        </div>
      </div>
      <div class="bx-body">
        <?php
        $has_inv = false;
        if ($transactions && $transactions->num_rows > 0) {
            $transactions->data_seek(0);
        }
        if ($transactions):
          while ($t = $transactions->fetch_assoc()):
            if ($t['payment_status'] !== 'Paid') continue;
            $has_inv = true;
            $paidAt  = !empty($t['paid_at']) ? new DateTime($t['paid_at']) : new DateTime($t['appointment_date']);
            $fee     = floatval($t['consultation_fee'] ?? 0);
            $invNo   = !empty($t['receipt_number']) ? $t['receipt_number'] : ('APT-' . $t['id']);
        ?>
        <div class="inv-item">
          <div>
            <div class="inv-no"><?= htmlspecialchars($invNo) ?></div>
            <div class="inv-meta"><?= $paidAt->format('M j, Y') ?> · Dr. <?= htmlspecialchars($t['doctor_name']) ?></div>
            <div class="inv-amt">₱<?= number_format($fee, 2) ?></div>
          </div>
          <a href="router.php?page=receipt&appt_id=<?= (int)$t['id'] ?>" class="inv-view" title="View receipt">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
          </a>
        </div>
        <?php endwhile; endif; ?>
        <?php if (!$has_inv): ?>
        <div class="empty-state">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h9l3 3v15H6a2 2 0 01-2-2V5a2 2 0 012-2z"/><path d="M14 3v4h4M8 11h6M8 15h6M8 19h4"/></svg>
          <p>No receipts yet.</p>
        </div>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /bill-grid -->
</div><!-- /page -->

<script>
(function(){
  var tabs   = document.querySelectorAll('.tx-tab');
  var rows   = document.querySelectorAll('#txTable tbody tr');
  var emptyF = document.getElementById('txEmptyFiltered');
  tabs.forEach(function(tab){
    tab.addEventListener('click', function(){
      tabs.forEach(function(t){ t.classList.remove('active'); });
      tab.classList.add('active');
      var filter = tab.getAttribute('data-filter');
      var visible = 0;
      rows.forEach(function(row){
        var show = filter === 'all' || row.getAttribute('data-status') === filter;
        row.style.display = show ? '' : 'none';
        if (show) visible++;
      });
      if (emptyF) emptyF.style.display = (rows.length > 0 && visible === 0) ? 'flex' : 'none';
    });
  });
})();
</script>

<?php require_once __DIR__ . '/../includes/nav.php'; ?>
</body>
</html>