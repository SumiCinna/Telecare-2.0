<?php
// admin/inventory_labkits.php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once '../database/config.php';

if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);

/*
  Expected table: lab_test_kits
  id, category, item_name, brand, size, production_date, expiry_date,
  quantity, reorder_level, unit_price, last_resupply_date, updated_by

  "category" groups items into test panels, e.g.:
    - Urinalysis (4P/10P) & Urine Chemistry
    - Fecalysis & Stool Tests (FOBT, H. Pylori Ag)
    - Routine Blood Tests (CBC, ESR, Blood Typing)
    - Blood Chemistry, Enzymes & Electrolytes
    - Coagulation Tests (PT, APTT, CTBT)
  Each category can hold several consumable/equipment rows.
*/

$kits = $conn->query("
    SELECT * FROM lab_test_kits
    ORDER BY category ASC, item_name ASC
");

$categories = []; // category => [items...]
$globalTotal = 0; $globalExpired = 0; $globalSoon = 0; $globalLow = 0;

if ($kits) {
    while ($k = $kits->fetch_assoc()) {
        $isExpired = strtotime($k['expiry_date']) < strtotime(date('Y-m-d'));
        $isSoon    = !$isExpired && strtotime($k['expiry_date']) <= strtotime('+30 days');
        $isLow     = (int)$k['quantity'] <= (int)($k['reorder_level'] ?? 0);
        $k['_expired'] = $isExpired;
        $k['_soon']    = $isSoon;
        $k['_low']     = $isLow;

        $globalTotal++;
        if ($isExpired) $globalExpired++;
        if ($isSoon) $globalSoon++;
        if ($isLow) $globalLow++;

        $categories[$k['category']][] = $k;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Lab Test Kits Inventory — TELE-CARE</title>
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

    .stat-row{display:flex;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap}
    .stat-chip{background:var(--white);border-radius:14px;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04);padding:0.9rem 1.3rem;min-width:150px}
    .stat-chip .num{font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:900;line-height:1}
    .stat-chip .lbl{font-size:0.74rem;color:#9ab0ae;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;margin-top:0.2rem}
    .stat-chip.warn .num{color:#d97706}
    .stat-chip.danger .num{color:var(--red)}

    .toolbar{display:flex;align-items:center;gap:0.8rem;margin-bottom:1.2rem;flex-wrap:wrap}
    .search-input{flex:1;min-width:220px;padding:0.6rem 1rem;border-radius:10px;border:1px solid rgba(36,68,65,0.15);font-family:'DM Sans',sans-serif;font-size:0.85rem;background:var(--white)}
    .search-input:focus{outline:none;border-color:var(--blue)}
    .btn-primary{background:var(--green);color:#fff;border:none;padding:0.65rem 1.3rem;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.85rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:0.4rem;transition:opacity 0.2s}
    .btn-primary:hover{opacity:0.88}
    .btn-ghost{background:none;border:1px solid rgba(36,68,65,0.15);color:var(--green);padding:0.6rem 1.2rem;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.85rem;font-weight:600;cursor:pointer}

    /* Category card — mirrors .doc-card from assignments.php */
    .cat-card{background:var(--white);border-radius:18px;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04);margin-bottom:1.2rem;overflow:hidden}
    .cat-header{display:flex;align-items:center;gap:1rem;padding:1.2rem 1.5rem;cursor:pointer;user-select:none}
    .cat-header:hover{background:rgba(36,68,65,0.02)}
    .cat-icon{width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,var(--blue),#2563C4);color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .cat-icon svg{width:20px;height:20px;stroke:#fff}
    .chevron{margin-left:auto;transition:transform 0.25s;color:#9ab0ae}
    .cat-card.open .chevron{transform:rotate(180deg)}
    .cat-body{display:none;padding:0 1.5rem 1.3rem}
    .cat-card.open .cat-body{display:block}

    .kit-table-wrap{overflow-x:auto;border-top:1px solid rgba(36,68,65,0.06);margin-top:0.4rem}
    table{width:100%;border-collapse:collapse;min-width:880px}
    thead th{text-align:left;font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#9ab0ae;padding:0.8rem 0.6rem;border-bottom:1px solid rgba(36,68,65,0.08);white-space:nowrap}
    tbody td{padding:0.75rem 0.6rem;font-size:0.85rem;border-bottom:1px solid rgba(36,68,65,0.05);vertical-align:middle}
    tbody tr:last-child td{border-bottom:none}
    tbody tr.row-expired{background:rgba(195,54,67,0.04)}
    tbody tr.row-low{background:rgba(245,158,11,0.04)}
    .cell-name{font-weight:700}
    .cell-sub{font-size:0.74rem;color:#9ab0ae}

    .badge{display:inline-block;padding:0.22rem 0.65rem;border-radius:50px;font-size:0.7rem;font-weight:700;letter-spacing:0.04em;white-space:nowrap}
    .badge-green{background:rgba(34,197,94,0.1);color:#16a34a}
    .badge-orange{background:rgba(245,158,11,0.1);color:#d97706}
    .badge-red{background:rgba(195,54,67,0.1);color:var(--red)}
    .badge-gray{background:rgba(0,0,0,0.06);color:#888}

    .icon-btn{width:28px;height:28px;border-radius:8px;border:1px solid rgba(36,68,65,0.12);background:#fff;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;color:var(--green)}
    .icon-btn:hover{background:rgba(36,68,65,0.06)}
    .icon-btn svg{width:13px;height:13px;stroke:currentColor}
    .action-cell{display:flex;gap:0.35rem}

    .empty-state{text-align:center;padding:4rem;color:#9ab0ae;font-size:0.9rem}
    .add-item-row{padding:0.8rem 0.6rem 0}

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
      <div style="font-size:0.95rem;font-weight:700;">Lab Test Kits — Diagnostic Supplies</div>
    </div>
    <button class="btn-primary" onclick="openModal()">
      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
      Add Kit Item
    </button>
  </div>

  <div class="page-content">

    <div class="stat-row">
      <div class="stat-chip"><div class="num"><?= $globalTotal ?></div><div class="lbl">Total Items</div></div>
      <div class="stat-chip"><div class="num"><?= count($categories) ?></div><div class="lbl">Test Panels</div></div>
      <div class="stat-chip warn"><div class="num"><?= $globalSoon ?></div><div class="lbl">Expiring ≤30 Days</div></div>
      <div class="stat-chip danger"><div class="num"><?= $globalExpired ?></div><div class="lbl">Expired</div></div>
      <div class="stat-chip warn"><div class="num"><?= $globalLow ?></div><div class="lbl">Low Stock</div></div>
    </div>

    <div class="toolbar">
      <input type="text" id="searchInput" class="search-input" placeholder="Search category or item name..." oninput="filterCategories()"/>
    </div>

    <?php if (empty($categories)): ?>
      <div class="cat-card"><div class="empty-state">No lab test kits in inventory yet. Click "Add Kit Item" to get started.</div></div>
    <?php else: $catIndex = 0; foreach ($categories as $catName => $items):
      $catIndex++;
      $catExpired = 0; $catSoon = 0; $catLow = 0;
      foreach ($items as $it) { if ($it['_expired']) $catExpired++; if ($it['_soon']) $catSoon++; if ($it['_low']) $catLow++; }
    ?>
    <div class="cat-card" id="cat-<?= $catIndex ?>" data-cat-search="<?= strtolower(htmlspecialchars($catName)) ?>">
      <div class="cat-header" onclick="toggleCat(<?= $catIndex ?>)">
        <div class="cat-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 3h6v4l4 9a2 2 0 01-2 3H7a2 2 0 01-2-3l4-9V3z"/><path stroke-linecap="round" d="M8 14h8"/></svg>
        </div>
        <div style="flex:1;">
          <div style="font-weight:700;font-size:0.97rem;"><?= htmlspecialchars($catName) ?></div>
          <div style="font-size:0.78rem;color:#9ab0ae;"><?= count($items) ?> item<?= count($items)==1?'':'s' ?> in this panel</div>
        </div>
        <div style="display:flex;gap:0.5rem;align-items:center;margin-right:0.8rem;">
          <?php if ($catExpired > 0): ?><span class="badge badge-red"><?= $catExpired ?> expired</span><?php endif; ?>
          <?php if ($catLow > 0): ?><span class="badge badge-orange"><?= $catLow ?> low</span><?php endif; ?>
          <?php if ($catSoon > 0 && $catExpired == 0): ?><span class="badge badge-orange"><?= $catSoon ?> expiring</span><?php endif; ?>
          <span class="badge badge-gray"><?= count($items) ?> total</span>
        </div>
        <svg class="chevron" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
        </svg>
      </div>

      <div class="cat-body">
        <div class="kit-table-wrap">
          <table>
            <thead>
              <tr>
                <th>Item / Brand</th>
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
              <?php foreach ($items as $k):
                $rowClass = $k['_expired'] ? 'row-expired' : ($k['_low'] ? 'row-low' : '');
              ?>
              <tr class="<?= $rowClass ?>" data-item-search="<?= strtolower(htmlspecialchars($k['item_name'].' '.$k['brand'])) ?>">
                <td>
                  <div class="cell-name"><?= htmlspecialchars($k['item_name']) ?></div>
                  <div class="cell-sub"><?= htmlspecialchars($k['brand']) ?></div>
                </td>
                <td><?= htmlspecialchars($k['size']) ?></td>
                <td><?= date('M d, Y', strtotime($k['production_date'])) ?></td>
                <td><?= date('M d, Y', strtotime($k['expiry_date'])) ?></td>
                <td style="font-weight:700;<?= $k['_low'] ? 'color:#d97706' : '' ?>"><?= (int)$k['quantity'] ?></td>
                <td><?= !empty($k['last_resupply_date']) ? date('M d, Y', strtotime($k['last_resupply_date'])) : '—' ?></td>
                <td><?= htmlspecialchars($k['updated_by'] ?? '—') ?></td>
                <td>₱<?= number_format((float)$k['unit_price'], 2) ?></td>
                <td>
                  <?php if ($k['_expired']): ?><span class="badge badge-red">Expired</span>
                  <?php elseif ($k['_low']): ?><span class="badge badge-orange">Low Stock</span>
                  <?php elseif ($k['_soon']): ?><span class="badge badge-orange">Expiring Soon</span>
                  <?php else: ?><span class="badge badge-green">Good</span><?php endif; ?>
                </td>
                <td>
                  <div class="action-cell">
                    <button class="icon-btn" title="Restock" onclick="restock(<?= (int)$k['id'] ?>)">
                      <svg viewBox="0 0 24 24" fill="none" stroke-width="2.3"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                    </button>
                    <button class="icon-btn" title="Edit" onclick="editItem(<?= (int)$k['id'] ?>)">
                      <svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 4H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2v-5m-1.5-9.5a2.1 2.1 0 013 3L12 15l-4 1 1-4 8.5-8.5z"/></svg>
                    </button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="add-item-row">
          <button class="btn-ghost" style="font-size:0.78rem;padding:0.45rem 0.9rem;" onclick="openModal('<?= htmlspecialchars($catName, ENT_QUOTES) ?>')">+ Add item to this panel</button>
        </div>
      </div>
    </div>
    <?php endforeach; endif; ?>

  </div>
</div>

<!-- Add / Edit Modal -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal">
    <div class="modal-head">
      <h2 id="modalTitle">Add Lab Kit Item</h2>
      <button class="modal-close" onclick="closeModal()">&times;</button>
    </div>
    <form method="POST" action="inventory_labkits_save.php">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-field full">
            <label>Test Panel / Category</label>
            <input type="text" name="category" id="categoryField" list="categoryList" placeholder="e.g. Urinalysis (4P/10P) & Urine Chemistry" required/>
            <datalist id="categoryList">
              <?php foreach (array_keys($categories) as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>"></option>
              <?php endforeach; ?>
            </datalist>
          </div>
          <div class="form-field"><label>Item Name</label><input type="text" name="item_name" placeholder="e.g. Urine reagent strips" required/></div>
          <div class="form-field"><label>Brand</label><input type="text" name="brand" required/></div>
          <div class="form-field"><label>Size / Pack</label><input type="text" name="size" placeholder="e.g. 100 strips/box"/></div>
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
  function openModal(prefillCategory){
    document.getElementById('modalTitle').textContent = 'Add Lab Kit Item';
    document.getElementById('editId').value = '';
    document.getElementById('categoryField').value = prefillCategory || '';
    document.getElementById('modalOverlay').classList.add('open');
  }
  function closeModal(){ document.getElementById('modalOverlay').classList.remove('open'); }
  function editItem(id){
    document.getElementById('modalTitle').textContent = 'Edit Lab Kit Item';
    document.getElementById('editId').value = id;
    // TODO: fetch item details via AJAX and populate the form fields
    document.getElementById('modalOverlay').classList.add('open');
  }
  function restock(id){
    // TODO: lightweight restock prompt (qty added, date, updated_by) posting to inventory_labkits_restock.php
    window.location.href = 'inventory_labkits_restock.php?id=' + id;
  }

  function toggleCat(id){
    document.getElementById('cat-' + id).classList.toggle('open');
  }

  function filterCategories(){
    const q = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('.cat-card[data-cat-search]').forEach(card => {
      const catMatches = card.dataset.catSearch.includes(q);
      const rows = card.querySelectorAll('tbody tr[data-item-search]');
      let anyItemMatches = false;
      rows.forEach(row => {
        const itemMatches = row.dataset.itemSearch.includes(q);
        if (itemMatches) anyItemMatches = true;
        row.style.display = (q === '' || catMatches || itemMatches) ? 'table-row' : 'none';
      });
      const show = q === '' || catMatches || anyItemMatches;
      card.style.display = show ? 'block' : 'none';
      if (q !== '' && show) card.classList.add('open');
    });
  }

  setTimeout(() => { const t = document.querySelector('.toast'); if(t) t.remove(); }, 3500);
</script>
</body>
</html>