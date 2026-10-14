<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$activeNav = 'hmo-providers';
$providers = [
  ['Maxicare','MAX001','Maria Santos','0917 123 4567','Active','support@maxicare.com.ph'],
  ['Intellicare','INT001','John Reyes','0918 765 4321','Active','support@intellicare.com.ph'],
  ['Medicare','MED001','Ana Cruz','0917 987 6543','Active','help@medicare.com.ph'],
  ['PhilCare','PHC001','Ramon Dela Cruz','0916 555 7788','Active','support@philcare.com.ph'],
  ['Avega','AVE001','Liza Santos','0918 444 3322','Active','hello@avega.com.ph'],
  ['Generika','GEN001','Mark Lopez','0917 222 1100','Inactive','admin@generika.com.ph'],
  ['Etiqa','ETI001','Sarah Tan','0918 333 6677','Suspended','support@etiqa.com.ph'],
  ['Pacific Cross','PAC001','Daniel Ramos','0916 888 9900','Active','care@pacificcross.com.ph'],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HMO Providers | TELE-CARE</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/hmo.css">
</head>
<body>
<?php include 'sidebar.php'; ?>
<main class="hmo-main">
  <header class="hmo-topbar"><button class="mobile-menu" type="button" aria-label="Toggle navigation">☰</button><div class="breadcrumbs"><span>Admin</span><b>›</b><span>HMO Management</span><b>›</b><strong>HMO Providers</strong></div><div class="topbar-user"><span class="notification">♧<i>3</i></span><span class="user-avatar">A</span><span><b>Admin</b><small>Administrator</small></span><span>⌄</span></div></header>
  <section class="hmo-content">
    <div class="page-heading"><div><div class="eyebrow">HMO MANAGEMENT</div><h1>HMO Providers</h1><p>Manage the HMO providers accredited by the clinic.</p></div><button class="primary-btn" type="button" data-open-modal>＋ Add HMO Provider</button></div>
    <div class="metric-grid provider-metrics"><article class="metric-card blue"><span class="metric-icon">♧</span><div><small>Total Providers</small><strong>8</strong></div></article><article class="metric-card green"><span class="metric-icon">✓</span><div><small>Active Providers</small><strong>6</strong></div></article><article class="metric-card amber"><span class="metric-icon">◷</span><div><small>Inactive Providers</small><strong>1</strong></div></article><article class="metric-card red"><span class="metric-icon">−</span><div><small>Suspended Providers</small><strong>1</strong></div></article></div>
    <div class="provider-layout">
      <section class="panel provider-table-panel">
        <div class="table-toolbar"><label class="search-box">⌕<input id="providerSearch" type="search" placeholder="Search provider name or code..."></label><select id="statusFilter"><option value="">All Status</option><option>Active</option><option>Inactive</option><option>Suspended</option></select><select id="coverageFilter"><option>All</option><option>Corporate</option><option>Individual</option></select></div>
        <div class="table-scroll"><table><thead><tr><th>Provider Name</th><th>Code</th><th>Contact Person</th><th>Contact Number</th><th>Status</th><th>Actions</th></tr></thead><tbody id="providerRows">
        <?php foreach ($providers as $index => $provider): ?><tr data-provider="<?= $index ?>" data-status="<?= $provider[4] ?>" data-search="<?= strtolower(implode(' ', $provider)) ?>"><td><span class="provider-logo"><?= strtoupper(substr($provider[0], 0, 1)) ?></span><strong><?= htmlspecialchars($provider[0]) ?></strong></td><td><?= htmlspecialchars($provider[1]) ?></td><td><?= htmlspecialchars($provider[2]) ?></td><td><?= htmlspecialchars($provider[3]) ?></td><td><span class="status-pill <?= strtolower($provider[4]) ?>"><?= htmlspecialchars($provider[4]) ?></span></td><td><div class="row-actions"><button type="button" title="View" data-select="<?= $index ?>">◉</button><button type="button" title="Edit" data-open-modal>✎</button><button type="button" title="Toggle status" data-toggle-status="<?= $index ?>">◉</button></div></td></tr><?php endforeach; ?>
        </tbody></table></div><div class="table-footer"><span id="rowCount">Showing 1–8 of 8 providers</span><div><button type="button">‹</button><button class="current" type="button">1</button><button type="button">›</button></div></div>
      </section>
      <aside class="panel provider-detail" id="providerDetail"></aside>
    </div>
  </section>
</main>
<div class="modal-backdrop" id="providerModal"><form class="modal-card" id="providerForm"><button class="modal-close" type="button" data-close-modal>×</button><h2>Add HMO Provider</h2><p>Enter the provider information below. This is a frontend preview only.</p><div class="form-grid"><label>Provider Name *<input required name="name" placeholder="Enter provider name"></label><label>Contact Person *<input required name="contact" placeholder="Enter contact person"></label><label>Provider Code *<input required name="code" placeholder="Enter provider code"></label><label>Contact Number *<input required name="phone" placeholder="Enter contact number"></label><label>Status *<select name="status"><option>Active</option><option>Inactive</option><option>Suspended</option></select></label><label>Email Address *<input required type="email" name="email" placeholder="Enter email address"></label><label>Accreditation Start Date<input type="date" name="start"></label><label>Accreditation End Date<input type="date" name="end"></label><label class="full-field">Portal URL<input type="url" name="url" placeholder="https://"></label><label class="full-field">Notes<textarea name="notes" placeholder="Enter additional notes"></textarea></label></div><div class="modal-actions"><button type="button" class="secondary-btn" data-close-modal>Cancel</button><button class="primary-btn" type="submit">Save Provider</button></div></form></div>
<script>
const providers = <?= json_encode($providers) ?>;
const detail = document.getElementById('providerDetail');
function renderDetail(index = 0) {
  const p = providers[index] || providers[0]; const status = p[4].toLowerCase();
  detail.innerHTML = `<div class="detail-head"><span class="provider-logo large">${p[0][0]}</span><div><h2>${p[0]}</h2><small>Code: ${p[1]}</small></div><button type="button">×</button></div><div class="detail-tabs"><b>Details</b><span>Contact Info</span><span>Service Coverage</span><span>History</span></div><div class="detail-body"><h3>Provider Information</h3><dl><dt>Provider Name</dt><dd>${p[0]}</dd><dt>Provider Code</dt><dd>${p[1]}</dd><dt>Contact Person</dt><dd>${p[2]}</dd><dt>Contact Number</dt><dd>${p[3]}</dd><dt>Email Address</dt><dd>${p[5]}</dd><dt>Accreditation Validity</dt><dd>Jan 1, 2024 – Dec 31, 2025</dd><dt>Status</dt><dd><span class="status-pill ${status}">${p[4]}</span></dd><dt>Teleconsultation</dt><dd>Yes</dd><dt>Supported Clinics</dt><dd>All Clinics</dd></dl><div class="detail-note"><b>ⓘ</b>This provider is currently active and can be selected for new transactions.</div></div><div class="detail-actions"><button class="secondary-btn">✎ Edit</button><button class="secondary-btn">◷ Deactivate</button><button class="danger-btn">⊖ Suspend</button></div>`;
}
renderDetail();
function filterRows() { const q = document.getElementById('providerSearch').value.toLowerCase(); const status = document.getElementById('statusFilter').value; let shown = 0; document.querySelectorAll('#providerRows tr').forEach(row => { const visible = row.dataset.search.includes(q) && (!status || row.dataset.status === status); row.hidden = !visible; if (visible) shown++; }); document.getElementById('rowCount').textContent = `Showing ${shown ? 1 : 0}–${shown} of ${shown} providers`; }
document.getElementById('providerSearch').addEventListener('input', filterRows); document.getElementById('statusFilter').addEventListener('change', filterRows);
document.querySelectorAll('[data-select]').forEach(button => button.addEventListener('click', () => renderDetail(Number(button.dataset.select))));
document.querySelectorAll('[data-toggle-status]').forEach(button => button.addEventListener('click', () => { const row = button.closest('tr'); const pill = row.querySelector('.status-pill'); const active = pill.textContent === 'Active'; pill.textContent = active ? 'Inactive' : 'Active'; pill.className = `status-pill ${active ? 'inactive' : 'active'}`; row.dataset.status = active ? 'Inactive' : 'Active'; }));
const modal = document.getElementById('providerModal'); document.querySelectorAll('[data-open-modal]').forEach(button => button.addEventListener('click', () => modal.classList.add('open'))); document.querySelectorAll('[data-close-modal]').forEach(button => button.addEventListener('click', () => modal.classList.remove('open'))); modal.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('open'); }); document.getElementById('providerForm').addEventListener('submit', e => { e.preventDefault(); modal.classList.remove('open'); alert('Provider saved in this frontend preview. No database changes were made.'); e.target.reset(); });
document.querySelector('.mobile-menu')?.addEventListener('click', () => document.querySelector('.sidebar')?.classList.toggle('mobile-open'));
</script>
</body></html>
