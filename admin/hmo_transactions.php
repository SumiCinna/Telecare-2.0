<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$activeNav = 'hmo-transactions';
$transactions = [
  ['HMO-000125','Cruz, Juan D.','Maxicare','General Consultation','LOA-2025-00123','Jan 10, 2025 – Jan 24, 2025','Approved','Maria S.','Approved for one consultation session. Follow-up subject to reauthorization.','Cruz, Juan Dela'],
  ['HMO-000124','Reyes, Ana L.','Intellicare','Laboratory Tests','LOG-45891','Jan 08, 2025 – Feb 07, 2025','Pending','Carlos P.','Waiting for member eligibility confirmation.','Reyes, Ana Lucia'],
  ['HMO-000123','Santos, Maria K.','Medicare','Ultrasound','LOA-77821','Jan 05, 2025 – Jan 20, 2025','Rejected','Ramon L.','Requested service is not covered under the member plan.','Santos, Maria Katrina'],
  ['HMO-000122','Dela Cruz, Carlo','PhilCare','Follow-up Consultation','LOA-55120','Jan 03, 2025 – Jan 17, 2025','Approved','Ana R.','Approved after review of the submitted clinical notes.','Dela Cruz, Carlo'],
  ['HMO-000121','Lim, Patricia','Avega','X-ray','LOA-99031','Dec 28, 2024 – Jan 28, 2025','Expired','Marisol K.','Authorization validity period has ended.','Lim, Patricia'],
  ['HMO-000120','Garcia, Eric','Etiqa','General Consultation','LOA-66321','Jan 02, 2025 – Jan 16, 2025','Approved','Maria S.','Approved for one consultation session.','Garcia, Eric'],
  ['HMO-000119','Tan, Michelle','Maxicare','Laboratory Tests','LOA-10234','Jan 06, 2025 – Jan 21, 2025','Pending','John R.','Additional supporting document requested.','Tan, Michelle'],
  ['HMO-000118','Villanueva, Kevin','Intellicare','Ultrasound','LOA-33455','Jan 01, 2025 – Jan 31, 2025','Approved','Carlos P.','Approved after eligibility verification.','Villanueva, Kevin'],
  ['HMO-000117','Ramos, Daniel','Pacific Cross','General Consultation','LOA-77210','Dec 27, 2024 – Jan 27, 2025','Rejected','Ana R.','Member limit for this benefit has been reached.','Ramos, Daniel'],
  ['HMO-000116','Morales, Bianca','Medicare','ECG','LOG-88123','Jan 04, 2025 – Feb 04, 2025','Pending','Ramon L.','Pending review by the HMO coordinator.','Morales, Bianca'],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HMO Transactions | TELE-CARE</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/hmo.css">
</head>
<body>
<?php include 'sidebar.php'; ?>
<main class="hmo-main">
  <header class="hmo-topbar"><button class="mobile-menu" type="button" aria-label="Toggle navigation">☰</button><div class="breadcrumbs"><span>Admin</span><b>›</b><span>HMO Management</span><b>›</b><strong>Transactions &amp; Authorization</strong></div><div class="topbar-user"><span class="notification">♧<i>3</i></span><span class="user-avatar">A</span><span><b>Admin</b><small>Administrator</small></span><span>⌄</span></div></header>
  <section class="hmo-content">
    <div class="page-heading"><div><div class="eyebrow">HMO MANAGEMENT</div><h1>HMO Transactions &amp; Authorization Monitoring</h1><p>Monitor all HMO transactions processed by clinic staff.</p></div><button class="primary-btn" type="button" data-open-transaction>＋ New HMO Transaction</button></div>
    <div class="metric-grid transaction-metrics">
      <article class="metric-card blue"><span class="metric-icon">▤</span><div><small>Total Transactions</small><strong>124</strong></div></article>
      <article class="metric-card green"><span class="metric-icon">✓</span><div><small>Approved</small><strong>78</strong></div></article>
      <article class="metric-card amber"><span class="metric-icon">◷</span><div><small>Pending</small><strong>22</strong></div></article>
      <article class="metric-card red"><span class="metric-icon">×</span><div><small>Rejected</small><strong>14</strong></div></article>
      <article class="metric-card violet"><span class="metric-icon">▣</span><div><small>Expired Authorizations</small><strong>10</strong></div></article>
    </div>
    <div class="transaction-layout">
      <section class="panel transaction-table-panel">
        <div class="transaction-toolbar">
          <label class="search-box">⌕<input id="transactionSearch" type="search" placeholder="Search by patient name, HMO reference, or LOA number..."></label>
          <select id="providerFilter"><option value="">All HMO Providers</option><option>Maxicare</option><option>Intellicare</option><option>Medicare</option><option>PhilCare</option><option>Avega</option><option>Etiqa</option><option>Pacific Cross</option></select>
          <select id="transactionStatus"><option value="">All Status</option><option>Approved</option><option>Pending</option><option>Rejected</option><option>Expired</option></select>
          <button class="date-filter" type="button">▣ Jan 1, 2025 – Dec 31, 2025</button><button class="more-filter" type="button">⌯ More Filters</button>
        </div>
        <div class="table-scroll"><table><thead><tr><th></th><th>Transaction No.</th><th>Patient Name</th><th>HMO Provider</th><th>Requested Service</th><th>Authorization No.</th><th>Validity</th><th>Status</th><th>Staff</th><th>Actions</th></tr></thead><tbody id="transactionRows">
        <?php foreach ($transactions as $index => $transaction): ?><tr data-index="<?= $index ?>" data-provider="<?= htmlspecialchars($transaction[2]) ?>" data-status="<?= htmlspecialchars($transaction[6]) ?>" data-search="<?= strtolower(implode(' ', $transaction)) ?>"><td><input type="checkbox" aria-label="Select transaction"></td><td><strong><?= htmlspecialchars($transaction[0]) ?></strong></td><td><?= htmlspecialchars($transaction[1]) ?></td><td><span class="provider-logo"><?= strtoupper(substr($transaction[2],0,1)) ?></span><strong><?= htmlspecialchars($transaction[2]) ?></strong></td><td><?= htmlspecialchars($transaction[3]) ?></td><td><?= htmlspecialchars($transaction[4]) ?></td><td><?= htmlspecialchars($transaction[5]) ?></td><td><span class="status-pill <?= strtolower($transaction[6]) ?>"><?= htmlspecialchars($transaction[6]) ?></span></td><td><?= htmlspecialchars($transaction[7]) ?></td><td><div class="row-actions"><button type="button" title="View details" data-view-transaction="<?= $index ?>">◉</button><button type="button" title="More actions">⋮</button></div></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <div class="table-footer"><span id="transactionCount">Showing 1–10 of 124 transactions</span><div><button type="button">‹</button><button class="current" type="button">1</button><button type="button">2</button><button type="button">3</button><button type="button">4</button><button type="button">5</button><button type="button">›</button></div></div>
      </section>
      <aside class="panel transaction-detail" id="transactionDetail"></aside>
    </div>
  </section>
</main>
<div class="modal-backdrop" id="transactionModal"><form class="modal-card transaction-form" id="transactionForm"><button class="modal-close" type="button" data-close-transaction>×</button><h2>New HMO Transaction</h2><p>Enter transaction details for this frontend preview. No database changes will be made.</p><div class="form-grid"><label>Patient Name *<input required placeholder="Search or enter patient name"></label><label>HMO Provider *<select required><option value="">Select provider</option><option>Maxicare</option><option>Intellicare</option><option>Medicare</option><option>PhilCare</option></select></label><label>Membership Number *<input required placeholder="Enter membership number"></label><label>Requested Service *<select required><option value="">Select service</option><option>General Consultation</option><option>Laboratory Tests</option><option>Ultrasound</option><option>X-ray</option></select></label><label>Authorization / LOA No.<input placeholder="Enter reference number"></label><label>Authorization Status<select><option>Pending</option><option>Approved</option><option>Rejected</option></select></label><label>Validity Start Date<input type="date"></label><label>Validity End Date<input type="date"></label><label class="full-field">Transaction Remarks<textarea placeholder="Add remarks or rejection reason"></textarea></label></div><div class="modal-actions"><button type="button" class="secondary-btn" data-close-transaction>Cancel</button><button class="primary-btn" type="submit">Save Transaction</button></div></form></div>
<script>
const transactions = <?= json_encode($transactions) ?>;
const detail = document.getElementById('transactionDetail');
function renderTransaction(index = 0) {
  const t = transactions[index] || transactions[0], status = t[6].toLowerCase();
  detail.innerHTML = `<div class="detail-head"><div><h2>Transaction Details</h2><small>${t[0]}</small><small>Created on Jan 10, 2025 · 10:24 AM</small></div><span class="status-pill ${status}">${t[6]}</span><button type="button">×</button></div><div class="detail-tabs"><b>Overview</b><span>Patient &amp; Membership</span><span>Services</span><span>Documents</span><span>History</span></div><div class="detail-body"><h3>Transaction Information</h3><dl><dt>Patient Name</dt><dd>${t[9]}</dd><dt>HMO Provider</dt><dd>${t[2]}</dd><dt>Requested Service</dt><dd>${t[3]}</dd><dt>Authorization No.</dt><dd>${t[4]}</dd><dt>Authorization Status</dt><dd><span class="status-pill ${status}">${t[6]}</span></dd><dt>Validity Period</dt><dd>${t[5]}</dd><dt>Processed By</dt><dd>${t[7]} (HMO Staff)</dd><dt>Clinic / Department</dt><dd>North Caloocan - General Medicine</dd><dt>Appointment Date</dt><dd>Jan 12, 2025 · 10:00 AM</dd><dt>Rejection Reason</dt><dd>${t[6] === 'Rejected' ? t[8] : '—'}</dd><dt>Remarks</dt><dd><span class="remarks">${t[8]}</span></dd></dl><div class="membership-card"><b>Patient membership</b><span>Member ID: MAX-2024-009812</span><span>Plan: Family Care Plus · Valid until Dec 31, 2025</span></div><h3 class="documents-title">Documents (3) <a href="#">View All</a></h3><div class="document-list"><div><b>PDF</b><span>LOA-2025-00123.pdf<small>Uploaded Jan 10, 2025 · 245 KB</small></span><button>⇩</button></div><div><b>IMG</b><span>HMO Card.jpg<small>Uploaded Jan 10, 2025 · 512 KB</small></span><button>⇩</button></div><div><b>IMG</b><span>Valid ID.jpg<small>Uploaded Jan 10, 2025 · 380 KB</small></span><button>⇩</button></div></div></div>`;
}
renderTransaction();
function filterTransactions() { const q = document.getElementById('transactionSearch').value.toLowerCase(), provider = document.getElementById('providerFilter').value, status = document.getElementById('transactionStatus').value; let shown = 0; document.querySelectorAll('#transactionRows tr').forEach(row => { const visible = row.dataset.search.includes(q) && (!provider || row.dataset.provider === provider) && (!status || row.dataset.status === status); row.hidden = !visible; if (visible) shown++; }); document.getElementById('transactionCount').textContent = `Showing ${shown ? 1 : 0}–${shown} of ${shown} transactions`; }
document.getElementById('transactionSearch').addEventListener('input', filterTransactions); document.getElementById('providerFilter').addEventListener('change', filterTransactions); document.getElementById('transactionStatus').addEventListener('change', filterTransactions);
document.querySelectorAll('[data-view-transaction]').forEach(button => button.addEventListener('click', () => renderTransaction(Number(button.dataset.viewTransaction))));
const transactionModal = document.getElementById('transactionModal'); document.querySelectorAll('[data-open-transaction]').forEach(button => button.addEventListener('click', () => transactionModal.classList.add('open'))); document.querySelectorAll('[data-close-transaction]').forEach(button => button.addEventListener('click', () => transactionModal.classList.remove('open'))); transactionModal.addEventListener('click', e => { if (e.target === transactionModal) transactionModal.classList.remove('open'); }); document.getElementById('transactionForm').addEventListener('submit', e => { e.preventDefault(); transactionModal.classList.remove('open'); alert('Transaction saved in this frontend preview. No database changes were made.'); e.target.reset(); });
document.querySelector('.mobile-menu')?.addEventListener('click', () => document.querySelector('.sidebar')?.classList.toggle('mobile-open'));
</script>
</body></html>
