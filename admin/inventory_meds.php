<?php
// admin/inventory_meds.php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once '../database/config.php';

if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);

/*
  Expected table: medicines
  id, brand, name, size, production_date, expiry_date, quantity,
  reorder_level, unit_price, last_resupply_date, updated_by, updated_at

  Thresholds:
    - Expired:        expiry_date < CURDATE()
    - Expiring soon:  expiry_date BETWEEN CURDATE() AND CURDATE() + 30 days
    - Low stock:      quantity <= reorder_level
*/

$meds = $conn->query("
    SELECT * FROM medicines
    ORDER BY
      (expiry_date < CURDATE()) DESC,
      (expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)) DESC,
      brand ASC, name ASC
");

$totalItems   = $meds ? $meds->num_rows : 0;
$expiredCount = 0;
$soonCount    = 0;
$lowCount     = 0;

$rows = [];
if ($meds) {
    while ($m = $meds->fetch_assoc()) {
        $isExpired = strtotime($m['expiry_date']) < strtotime(date('Y-m-d'));
        $isSoon    = !$isExpired && strtotime($m['expiry_date']) <= strtotime('+30 days');
        $isLow     = (int)$m['quantity'] <= (int)($m['reorder_level'] ?? 0);
        if ($isExpired) $expiredCount++;
        elseif ($isSoon) $soonCount++;
        if ($isLow) $lowCount++;
        $m['_expired'] = $isExpired;
        $m['_soon']    = $isSoon;
        $m['_low']     = $isLow;
        $rows[] = $m;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Medicine Inventory — TELE-CARE</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root{--red:#C33643;--green:#244441;--blue:#3F82E3;--bg:#F2F2F2;--white:#FFFFFF}
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--green);display:flex;min-height:100vh}
    h1,h2,h3{font-family:'Playfair Display',serif}
    .sidebar{width:230px;min-width:230px;background:var(--green);display:flex;flex-direction:column;position:sticky;top:0;height:100vh;overflow-y:auto}
    .sidebar-logo{padding:1.8rem 1.5rem 1.2rem;font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:900;color:#fff;border-bottom:1px solid rgba(255,255,255,0.08)}
    .sidebar-logo span{color:var(--red)}
    .sidebar-admin{padding:1rem 1.5rem;font-size:0.78rem;color:rgba(255,255,255,0.45);border-bottom:1px solid rgba(255,255,255,0.08)}
    .sidebar-admin strong{color:rgba(255,255,255,0.8);font-weight:600;display:block;font-size:0.88rem}
    .nav-links{padding:1rem 0;flex:1}
    .nav-link{display:flex;align-items:center;gap:0.8rem;padding:0.8rem 1.5rem;color:rgba(255,255,255,0.55);font-size:0.88rem;font-weight:500;width:100%;text-align:left;font-family:'DM Sans',sans-serif;transition:all 0.2s;border-left:3px solid transparent;text-decoration:none}
    .nav-link svg{width:18px;height:18px;stroke:currentColor;flex-shrink:0}
    .nav-link:hover{color:#fff;background:rgba(255,255,255,0.06)}
    .nav-link.active{color:#fff;background:rgba(255,255,255,0.1);border-left-color:var(--red)}
    .sidebar-logout{padding:1rem 1.5rem;border-top:1px solid rgba(255,255,255,0.08)}
    .logout-btn{display:flex;align-items:center;gap:0.6rem;color:rgba(255,255,255,0.45);font-size:0.82rem;text-decoration:none;transition:color 0.2s}
    .logout-btn:hover{color:var(--red)}
    .main{flex:1;overflow-y:auto}
    .topbar{background:var(--white);padding:1rem 2rem;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(36,68,65,0.07);position:sticky;top:0;z-index:50}
    .page-content{padding:2rem}

    /* Summary stat chips */
    .stat-row{display:flex;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap}
    .stat-chip{background:var(--white);border-radius:14px;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04);padding:0.9rem 1.3rem;min-width:150px}
    .stat-chip .num{font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:900;line-height:1}
    .stat-chip .lbl{font-size:0.74rem;color:#9ab0ae;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;margin-top:0.2rem}
    .stat-chip.warn .num{color:#d97706}
    .stat-chip.danger .num{color:var(--red)}

    /* Toolbar */
    .toolbar{display:flex;align-items:center;gap:0.8rem;margin-bottom:1.2rem;flex-wrap:wrap}
    .search-input{flex:1;min-width:220px;padding:0.6rem 1rem;border-radius:10px;border:1px solid rgba(36,68,65,0.15);font-family:'DM Sans',sans-serif;font-size:0.85rem;background:var(--white)}
    .search-input:focus{outline:none;border-color:var(--blue)}
    select.filter-select{padding:0.6rem 0.9rem;border-radius:10px;border:1px solid rgba(36,68,65,0.15);font-family:'DM Sans',sans-serif;font-size:0.83rem;background:var(--white);color:var(--green)}
    .btn-primary{background:var(--green);color:#fff;border:none;padding:0.65rem 1.3rem;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.85rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:0.4rem;transition:opacity 0.2s}
    .btn-primary:hover{opacity:0.88}

    /* Table card */
    .table-card{background:var(--white);border-radius:18px;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04);overflow-x:auto}
    table{width:100%;border-collapse:collapse;min-width:920px}
    thead th{text-align:left;font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#9ab0ae;padding:0.9rem 1rem;border-bottom:1px solid rgba(36,68,65,0.08);white-space:nowrap}
    tbody td{padding:0.85rem 1rem;font-size:0.86rem;border-bottom:1px solid rgba(36,68,65,0.05);vertical-align:middle}
    tbody tr:last-child td{border-bottom:none}
    tbody tr:hover{background:rgba(36,68,65,0.02)}
    tbody tr.row-expired{background:rgba(195,54,67,0.04)}
    tbody tr.row-low{background:rgba(245,158,11,0.04)}
    .cell-name{font-weight:700}
    .cell-sub{font-size:0.75rem;color:#9ab0ae}

    .badge{display:inline-block;padding:0.22rem 0.65rem;border-radius:50px;font-size:0.7rem;font-weight:700;letter-spacing:0.04em;white-space:nowrap}
    .badge-green{background:rgba(34,197,94,0.1);color:#16a34a}
    .badge-orange{background:rgba(245,158,11,0.1);color:#d97706}
    .badge-red{background:rgba(195,54,67,0.1);color:var(--red)}
    .badge-gray{background:rgba(0,0,0,0.06);color:#888}

    .icon-btn{width:30px;height:30px;border-radius:8px;border:1px solid rgba(36,68,65,0.12);background:#fff;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;color:var(--green);transition:all 0.15s}
    .icon-btn:hover{background:rgba(36,68,65,0.06)}
    .icon-btn svg{width:14px;height:14px;stroke:currentColor}
    .action-cell{display:flex;gap:0.4rem}

    .empty-state{text-align:center;padding:3.5rem 1rem;color:#9ab0ae;font-size:0.88rem}

    .pagination{display:flex;align-items:center;justify-content:center;gap:0.35rem;padding:1rem;border-top:1px solid rgba(36,68,65,0.06)}
    .page-btn{border:1px solid rgba(36,68,65,0.15);background:#fff;color:var(--green);font-family:'DM Sans',sans-serif;font-size:0.76rem;font-weight:600;border-radius:8px;padding:0.3rem 0.6rem;cursor:pointer;min-width:32px}
    .page-btn:hover{border-color:var(--green)}
    .page-btn.active{background:var(--green);color:#fff;border-color:var(--green)}
    .page-btn:disabled{opacity:0.45;cursor:not-allowed}

    /* Modal */
    .modal-overlay{position:fixed;inset:0;background:rgba(15,30,28,0.45);display:none;align-items:center;justify-content:center;z-index:200;padding:1.5rem}
    .modal-overlay.open{display:flex}
    .modal{background:#fff;border-radius:18px;width:100%;max-width:560px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.2)}
    .modal-head{padding:1.3rem 1.6rem;border-bottom:1px solid rgba(36,68,65,0.08);display:flex;align-items:center;justify-content:space-between}
    .modal-head h2{font-size:1.2rem;font-weight:900}
    .modal-close{cursor:pointer;color:#9ab0ae;background:none;border:none;font-size:1.3rem;line-height:1}
    .modal-body{padding:1.4rem 1.6rem}
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:0.9rem}
    .form-grid .full{grid-column:1/-1}
    .form-field label{display:block;font-size:0.76rem;font-weight:700;color:#9ab0ae;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.35rem}
    .form-field input,.form-field select{width:100%;padding:0.6rem 0.8rem;border-radius:10px;border:1px solid rgba(36,68,65,0.15);font-family:'DM Sans',sans-serif;font-size:0.87rem}
    .form-field input:focus,.form-field select:focus{outline:none;border-color:var(--blue)}
    .modal-foot{padding:1.1rem 1.6rem;border-top:1px solid rgba(36,68,65,0.08);display:flex;justify-content:flex-end;gap:0.7rem}
    .btn-ghost{background:none;border:1px solid rgba(36,68,65,0.15);color:var(--green);padding:0.6rem 1.2rem;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.85rem;font-weight:600;cursor:pointer}

    .toast{position:fixed;bottom:2rem;right:2rem;z-index:300;background:var(--green);color:#fff;padding:0.9rem 1.5rem;border-radius:14px;font-size:0.88rem;font-weight:600;box-shadow:0 8px 30px rgba(0,0,0,0.15);animation:slideIn 0.4s ease,fadeOut 0.4s 3s ease forwards}
    @keyframes slideIn{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
    @keyframes fadeOut{from{opacity:1}to{opacity:0;pointer-events:none}}
    @media(max-width:900px){.sidebar{display:none}.form-grid{grid-template-columns:1fr}}
  </style>
</head>
<body>

<?php if ($toast): ?><div class="toast">✓ <?= htmlspecialchars($toast) ?></div><?php endif; ?>

<?php $activeNav = 'inventory'; include 'sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div>
      <div style="font-size:0.75rem;color:#9ab0ae;font-weight:600;">Inventory</div>
      <div style="font-size:0.95rem;font-weight:700;">Medicine Stock — Pharmacy</div>
    </div>
    <button class="btn-primary" onclick="openModal()">
      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
      Add Medicine
    </button>
  </div>

  <div class="page-content">

    <div class="stat-row">
      <div class="stat-chip"><div class="num"><?= $totalItems ?></div><div class="lbl">Total Items</div></div>
      <div class="stat-chip warn"><div class="num"><?= $soonCount ?></div><div class="lbl">Expiring ≤30 Days</div></div>
      <div class="stat-chip danger"><div class="num"><?= $expiredCount ?></div><div class="lbl">Expired</div></div>
      <div class="stat-chip warn"><div class="num"><?= $lowCount ?></div><div class="lbl">Low Stock</div></div>
    </div>

    <div class="toolbar">
      <input type="text" id="searchInput" class="search-input" placeholder="Search by brand or medicine name..." oninput="filterTable()"/>
      <select id="statusFilter" class="filter-select" onchange="filterTable()">
        <option value="all">All Statuses</option>
        <option value="low">Low Stock</option>
        <option value="soon">Expiring Soon</option>
        <option value="expired">Expired</option>
      </select>
    </div>

    <div class="table-card">
      <table id="medsTable">
        <thead>
          <tr>
            <th>Brand / Name</th>
            <th>Size</th>
            <th>Production Date</th>
            <th>Expiry Date</th>
            <th>Qty on Hand</th>
            <th>Last Resupplied</th>
            <th>Updated By</th>
            <th>Price</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="10"><div class="empty-state">No medicines in inventory yet. Click "Add Medicine" to get started.</div></td></tr>
          <?php else: foreach ($rows as $m):
            $rowClass = $m['_expired'] ? 'row-expired' : ($m['_low'] ? 'row-low' : '');
            $statusFilterVal = $m['_expired'] ? 'expired' : ($m['_soon'] ? 'soon' : ($m['_low'] ? 'low' : 'ok'));
          ?>
          <tr class="<?= $rowClass ?>" data-status="<?= $statusFilterVal ?>" data-search="<?= strtolower(htmlspecialchars($m['brand'].' '.$m['name'])) ?>">
            <td>
              <div class="cell-name"><?= htmlspecialchars($m['brand']) ?></div>
              <div class="cell-sub"><?= htmlspecialchars($m['name']) ?></div>
            </td>
            <td><?= htmlspecialchars($m['size']) ?></td>
            <td><?= date('M d, Y', strtotime($m['production_date'])) ?></td>
            <td><?= date('M d, Y', strtotime($m['expiry_date'])) ?></td>
            <td style="font-weight:700;<?= $m['_low'] ? 'color:#d97706' : '' ?>"><?= (int)$m['quantity'] ?></td>
            <td><?= !empty($m['last_resupply_date']) ? date('M d, Y', strtotime($m['last_resupply_date'])) : '—' ?></td>
            <td><?= htmlspecialchars($m['updated_by'] ?? '—') ?></td>
            <td>₱<?= number_format((float)$m['unit_price'], 2) ?></td>
            <td>
              <?php if ($m['_expired']): ?>
                <span class="badge badge-red">Expired</span>
              <?php elseif ($m['_low']): ?>
                <span class="badge badge-orange">Low Stock</span>
              <?php elseif ($m['_soon']): ?>
                <span class="badge badge-orange">Expiring Soon</span>
              <?php else: ?>
                <span class="badge badge-green">Good</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="action-cell">
                <button class="icon-btn" title="Restock" onclick="restock(<?= (int)$m['id'] ?>)">
                  <svg viewBox="0 0 24 24" fill="none" stroke-width="2.3"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                </button>
                <button class="icon-btn" title="Edit" onclick="editItem(<?= (int)$m['id'] ?>)">
                  <svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 4H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2v-5m-1.5-9.5a2.1 2.1 0 013 3L12 15l-4 1 1-4 8.5-8.5z"/></svg>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
      <div class="pagination" id="pagination"></div>
    </div>

  </div>
</div>

<!-- Add / Edit Modal -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal">
    <div class="modal-head">
      <h2 id="modalTitle">Add Medicine</h2>
      <button class="modal-close" onclick="closeModal()">&times;</button>
    </div>
    <form method="POST" action="inventory_meds_save.php">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-field"><label>Brand</label><input type="text" name="brand" required/></div>
          <div class="form-field"><label>Medicine Name</label><input type="text" name="name" required/></div>
          <div class="form-field"><label>Size / Dosage</label><input type="text" name="size" placeholder="e.g. 500mg, 100mL" required/></div>
          <div class="form-field"><label>Unit Price (₱)</label><input type="number" step="0.01" name="unit_price" required/></div>
          <div class="form-field"><label>Production Date</label><input type="date" name="production_date" required/></div>
          <div class="form-field"><label>Expiry Date</label><input type="date" name="expiry_date" required/></div>
          <div class="form-field"><label>Quantity</label><input type="number" name="quantity" required/></div>
          <div class="form-field"><label>Reorder Level</label><input type="number" name="reorder_level" placeholder="Alert threshold" required/></div>
          <div class="form-field full"><label>Last Resupply Date</label><input type="date" name="last_resupply_date"/></div>
        </div>
        <input type="hidden" name="id" id="editId"/>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn-ghost" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-primary">Save Item</button>
      </div>
    </form>
  </div>
</div>

<script>
  function openModal(){ document.getElementById('modalTitle').textContent='Add Medicine'; document.getElementById('modalOverlay').classList.add('open'); }
  function closeModal(){ document.getElementById('modalOverlay').classList.remove('open'); }
  function editItem(id){
    document.getElementById('modalTitle').textContent='Edit Medicine';
    document.getElementById('editId').value = id;
    // TODO: fetch item details via AJAX and populate the form fields
    document.getElementById('modalOverlay').classList.add('open');
  }
  function restock(id){
    // TODO: open a lighter "restock" prompt (qty added, date, updated_by) and POST to inventory_meds_restock.php
    window.location.href = 'inventory_meds_restock.php?id=' + id;
  }

  const PER_PAGE = 10;
  let currentPage = 1;

  function getVisibleRows(){
    const q = document.getElementById('searchInput').value.toLowerCase();
    const status = document.getElementById('statusFilter').value;
    return Array.from(document.querySelectorAll('#medsTable tbody tr[data-status]')).filter(row => {
      const matchesSearch = row.dataset.search.includes(q);
      const matchesStatus = status === 'all' || row.dataset.status === status || (status==='low' && row.dataset.status==='low');
      return matchesSearch && matchesStatus;
    });
  }

  function filterTable(){ currentPage = 1; renderPage(); }

  function renderPage(){
    const allRows = Array.from(document.querySelectorAll('#medsTable tbody tr[data-status]'));
    const visible = getVisibleRows();
    allRows.forEach(r => r.style.display = 'none');

    const totalPages = Math.max(1, Math.ceil(visible.length / PER_PAGE));
    currentPage = Math.min(currentPage, totalPages);
    const start = (currentPage - 1) * PER_PAGE;
    visible.slice(start, start + PER_PAGE).forEach(r => r.style.display = 'table-row');

    const pager = document.getElementById('pagination');
    if (visible.length <= PER_PAGE){ pager.innerHTML = ''; return; }
    let html = `<button class="page-btn" ${currentPage===1?'disabled':''} onclick="currentPage--;renderPage()">Prev</button>`;
    for(let p=1;p<=totalPages;p++){
      html += `<button class="page-btn ${p===currentPage?'active':''}" onclick="currentPage=${p};renderPage()">${p}</button>`;
    }
    html += `<button class="page-btn" ${currentPage===totalPages?'disabled':''} onclick="currentPage++;renderPage()">Next</button>`;
    pager.innerHTML = html;
  }

  document.addEventListener('DOMContentLoaded', renderPage);
  setTimeout(() => { const t = document.querySelector('.toast'); if(t) t.remove(); }, 3500);
</script>
</body>
</html>