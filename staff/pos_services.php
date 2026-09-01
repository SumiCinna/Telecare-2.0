<?php
// staff/pos_services.php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

/* ══════════════════════════════════════════════
   Ensure POS tables exist
   ══════════════════════════════════════════════ */
$conn->query("CREATE TABLE IF NOT EXISTS `pos_sales` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `patient_name` varchar(150) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `pos_sale_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sale_id` int NOT NULL,
  `service_id` int NOT NULL,
  `service_name` varchar(150) NOT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `service_id` (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* ══════════════════════════════════════════════
   CHECKOUT
   Cart comes in as JSON: [{"service_id":1,"qty":2}, ...]
   For every service in the cart we look up its
   service_requirements (testing kit + qty_used),
   multiply by the cart qty, sum everything across
   the whole cart per product, THEN check stock and
   deduct — all inside one DB transaction so a sale
   never half-completes.
   ══════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $cart_json    = $_POST['cart_json'] ?? '[]';
    $patient_name = trim($_POST['patient_name'] ?? '');
    $cart         = json_decode($cart_json, true);

    // Normalize into service_id => qty, de-duped
    $items = [];
    if (is_array($cart)) {
        foreach ($cart as $row) {
            $sid = (int)($row['service_id'] ?? 0);
            $qty = (int)($row['qty'] ?? 0);
            if ($sid > 0 && $qty > 0) {
                $items[$sid] = ($items[$sid] ?? 0) + $qty;
            }
        }
    }

    if (empty($items)) {
        $_SESSION['toast_error'] = 'Cart is empty.';
        header('Location: pos_services.php'); exit;
    }

    $conn->begin_transaction();
    try {
        // Re-fetch services server-side (never trust price/name from the client)
        $ids = implode(',', array_map('intval', array_keys($items)));
        $svc_res = $conn->query("SELECT * FROM services WHERE id IN ($ids) AND status='Active'");
        $services = [];
        if ($svc_res) { while ($row = $svc_res->fetch_assoc()) { $services[$row['id']] = $row; } }

        foreach ($items as $sid => $qty) {
            if (!isset($services[$sid])) {
                throw new Exception('One of the selected services is no longer available. Please refresh and try again.');
            }
        }

        // Aggregate required testing-kit quantities across the whole cart
        $required = []; // product_id => total qty needed
        $reqStmt = $conn->prepare("SELECT product_id, quantity_used FROM service_requirements WHERE service_id = ?");
        foreach ($items as $sid => $qty) {
            $reqStmt->bind_param("i", $sid);
            $reqStmt->execute();
            $rres = $reqStmt->get_result();
            while ($r = $rres->fetch_assoc()) {
                $need = (int)$r['quantity_used'] * $qty;
                $required[$r['product_id']] = ($required[$r['product_id']] ?? 0) + $need;
            }
        }

        // Lock the affected inventory rows and validate stock before touching anything
        if (!empty($required)) {
            $pids = implode(',', array_map('intval', array_keys($required)));
            $lockRes = $conn->query("SELECT id, name, unit, stock_quantity FROM products WHERE id IN ($pids) FOR UPDATE");
            $stockNow = [];
            if ($lockRes) { while ($p = $lockRes->fetch_assoc()) { $stockNow[$p['id']] = $p; } }

            $shortages = [];
            foreach ($required as $pid => $need) {
                $have = (int)($stockNow[$pid]['stock_quantity'] ?? 0);
                if ($have < $need) {
                    $shortages[] = ($stockNow[$pid]['name'] ?? "Item #$pid") . " (need {$need}, have {$have} " . ($stockNow[$pid]['unit'] ?? '') . ")";
                }
            }
            if (!empty($shortages)) {
                throw new Exception('Not enough stock to complete this sale: ' . implode('; ', $shortages) . '.');
            }

            $updStmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
            foreach ($required as $pid => $need) {
                $updStmt->bind_param("ii", $need, $pid);
                $updStmt->execute();
            }
        }

        // Record the sale
        $total = 0.0;
        foreach ($items as $sid => $qty) { $total += (float)$services[$sid]['price'] * $qty; }

        $pname = $patient_name !== '' ? $patient_name : null;
        $saleStmt = $conn->prepare("INSERT INTO pos_sales (staff_id, patient_name, total_amount) VALUES (?, ?, ?)");
        $saleStmt->bind_param("isd", $staff_id, $pname, $total);
        $saleStmt->execute();
        $sale_id = $conn->insert_id;

        $itemStmt = $conn->prepare(
            "INSERT INTO pos_sale_items (sale_id, service_id, service_name, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?)"
        );
        foreach ($items as $sid => $qty) {
            $unit_price = (float)$services[$sid]['price'];
            $subtotal   = $unit_price * $qty;
            $sname      = $services[$sid]['name'];
            $itemStmt->bind_param("iisidd", $sale_id, $sid, $sname, $qty, $unit_price, $subtotal);
            $itemStmt->execute();
        }

        $conn->commit();
        $_SESSION['toast']         = 'Sale completed — ₱' . number_format($total, 2) . ' recorded, inventory updated.';
        $_SESSION['last_sale_id']  = $sale_id;
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['toast_error'] = $e->getMessage();
    }

    header('Location: pos_services.php'); exit;
}

$toast        = $_SESSION['toast']        ?? null;
$toast_error  = $_SESSION['toast_error']  ?? null;
$last_sale_id = $_SESSION['last_sale_id'] ?? null;
unset($_SESSION['toast'], $_SESSION['toast_error'], $_SESSION['last_sale_id']);

$active_page  = 'pos';
$stat_pending = (int)$conn->query("SELECT COUNT(*) c FROM appointments WHERE status='Pending'")->fetch_assoc()['c'];

/* ══════════════════════════════════════════════
   Data for the POS grid
   ══════════════════════════════════════════════ */
$CATEGORIES = ['Laboratory', 'X-ray', 'Chemical', 'Consultation'];

$allServices = [];
$sres = $conn->query("SELECT * FROM services WHERE status='Active' ORDER BY name ASC");
if ($sres) { while ($row = $sres->fetch_assoc()) { $allServices[] = $row; } }

$requirementsByService = [];
$rres = $conn->query(
    "SELECT r.service_id, r.product_id, r.quantity_used, p.name AS product_name, p.unit AS product_unit
     FROM service_requirements r
     JOIN products p ON p.id = r.product_id
     ORDER BY p.name ASC"
);
if ($rres) {
    while ($row = $rres->fetch_assoc()) {
        $requirementsByService[$row['service_id']][] = $row;
    }
}

// Receipt for the sale that was just completed (auto-shown once)
$receipt = null;
if ($last_sale_id) {
    $rstmt = $conn->prepare("SELECT * FROM pos_sales WHERE id = ?");
    $rstmt->bind_param("i", $last_sale_id);
    $rstmt->execute();
    $receipt = $rstmt->get_result()->fetch_assoc();
    if ($receipt) {
        $istmt = $conn->prepare("SELECT * FROM pos_sale_items WHERE sale_id = ? ORDER BY id ASC");
        $istmt->bind_param("i", $last_sale_id);
        $istmt->execute();
        $receipt['items'] = $istmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

require_once 'includes/header.php';
?>

<style>
  .pos-layout{display:grid;grid-template-columns:1fr 340px;gap:1.2rem;align-items:start}
  .pos-controls{display:flex;gap:.6rem;flex-wrap:wrap;align-items:center;margin-bottom:1rem}
  .pos-filter-tabs{display:flex;gap:.4rem;flex-wrap:wrap}
  .pos-filter-tab{padding:.4rem .85rem;border-radius:50px;font-size:.76rem;font-weight:700;border:1.5px solid rgba(36,68,65,.12);background:#fff;color:var(--text);cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .15s}
  .pos-filter-tab.active{background:var(--green);border-color:var(--green);color:#fff}
  .pos-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem}
  .pos-tile{background:#fff;border-radius:16px;padding:1.1rem;border:1px solid rgba(36,68,65,.07);box-shadow:0 2px 8px rgba(0,0,0,.04);display:flex;flex-direction:column;gap:.5rem}
  .pos-tile-top{display:flex;justify-content:space-between;align-items:center}
  .pos-tile-price{font-family:'Playfair Display',serif;font-weight:900;font-size:1.05rem;color:var(--green)}
  .pos-tile-name{font-weight:700;font-size:.92rem;line-height:1.25}
  .pos-tile-reqs{font-size:.72rem;color:var(--muted);line-height:1.4;min-height:1.4em}
  .pos-add-btn{margin-top:auto}

  .cart-panel{background:#fff;border-radius:16px;padding:1.2rem;border:1px solid rgba(36,68,65,.07);box-shadow:0 2px 8px rgba(0,0,0,.04);position:sticky;top:80px}
  .cart-panel h3{font-size:1rem;margin-bottom:.9rem}
  .cart-items{max-height:42vh;overflow-y:auto;margin-bottom:.8rem}
  .cart-line{display:grid;grid-template-columns:1fr auto auto auto;gap:.5rem;align-items:center;padding:.6rem 0;border-bottom:1px solid rgba(36,68,65,.06)}
  .cart-line:last-child{border-bottom:none}
  .cart-line-name{font-weight:700;font-size:.82rem}
  .cart-line-price{font-size:.7rem;color:var(--muted)}
  .cart-line-qty{display:flex;align-items:center;gap:.4rem}
  .cart-line-qty button{width:22px;height:22px;border-radius:50%;border:1px solid rgba(36,68,65,.15);background:#fff;cursor:pointer;font-weight:700;line-height:1;display:flex;align-items:center;justify-content:center;font-size:.8rem}
  .cart-line-qty span{min-width:16px;text-align:center;font-weight:700;font-size:.82rem}
  .cart-line-sub{font-weight:700;font-size:.82rem;white-space:nowrap}
  .cart-line-remove{background:none;border:none;color:var(--red);cursor:pointer;font-size:.9rem;padding:0 .2rem}
  .cart-total-row{display:flex;justify-content:space-between;align-items:center;padding-top:.7rem;border-top:1.5px solid rgba(36,68,65,.1);margin-bottom:.9rem}
  .cart-total-label{font-size:.8rem;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.05em}
  .cart-total-value{font-family:'Playfair Display',serif;font-weight:900;font-size:1.3rem;color:var(--green)}

  .receipt-line{display:flex;justify-content:space-between;font-size:.85rem;padding:.35rem 0}

  @media(max-width:960px){.pos-layout{grid-template-columns:1fr}.cart-panel{position:relative;top:0}}
</style>

<div class="sec-head">
  <h2>Services POS</h2>
  <input id="posSearch" class="search-bar" placeholder="Search service…"/>
</div>

<div class="pos-controls">
  <div class="pos-filter-tabs" id="posFilterTabs">
    <button type="button" class="pos-filter-tab active" data-filter="all" onclick="setPosFilter('all')">All</button>
    <?php foreach ($CATEGORIES as $cat): ?>
      <button type="button" class="pos-filter-tab" data-filter="<?= htmlspecialchars($cat) ?>" onclick="setPosFilter('<?= htmlspecialchars($cat) ?>')"><?= htmlspecialchars($cat) ?></button>
    <?php endforeach; ?>
  </div>
</div>

<div class="pos-layout">

  <div>
    <div class="pos-grid" id="posGrid"></div>
  </div>

  <div class="cart-panel">
    <h3> Current Sale</h3>
    <div class="cart-items" id="cartItems"></div>
    <div class="cart-total-row">
      <span class="cart-total-label">Total</span>
      <span class="cart-total-value" id="cartTotal">₱0.00</span>
    </div>
    <button type="button" class="btn-submit" id="checkoutBtn" onclick="openCheckout()" disabled>Checkout</button>
  </div>

</div>

<!-- Checkout confirm + patient name -->
<div class="modal-overlay" id="modal-checkout">
  <div class="modal">
    <h3>Confirm Sale</h3>
    <div id="checkout-summary" style="margin-bottom:.6rem;max-height:240px;overflow-y:auto;"></div>
    <div style="display:flex;justify-content:space-between;font-weight:800;font-size:1rem;padding-top:.6rem;border-top:1px solid rgba(36,68,65,.08);margin-bottom:1rem;">
      <span>Total</span><span id="checkout-total">₱0.00</span>
    </div>
    <form method="POST" id="pos-checkout-form">
      <input type="hidden" name="checkout" value="1"/>
      <input type="hidden" name="cart_json" id="cart_json"/>
      <label class="f-label">Patient / Walk-in Name (optional)</label>
      <input type="text" name="patient_name" class="f-input" placeholder="e.g. Juan Dela Cruz"/>
      <button type="submit" class="btn-submit">Confirm &amp; Complete Sale</button>
      <button type="button" class="btn-cancel-modal" onclick="closeModal('modal-checkout')">Cancel</button>
    </form>
  </div>
</div>

<?php if ($receipt): ?>
<!-- Receipt (auto-shown once after a completed sale) -->
<div class="modal-overlay" id="modal-receipt">
  <div class="modal">
    <h3>✓ Sale Completed</h3>
    <div style="font-size:.78rem;color:var(--muted);margin-bottom:.8rem;">
      Receipt #<?= (int)$receipt['id'] ?> · <?= date('M j, Y g:i A', strtotime($receipt['created_at'])) ?>
      <?php if (!empty($receipt['patient_name'])): ?> · <?= htmlspecialchars($receipt['patient_name']) ?><?php endif; ?>
    </div>
    <div style="border-top:1px solid rgba(36,68,65,.08);border-bottom:1px solid rgba(36,68,65,.08);padding:.4rem 0;margin-bottom:.6rem;">
      <?php foreach ($receipt['items'] as $it): ?>
      <div class="receipt-line">
        <span><?= htmlspecialchars($it['service_name']) ?> ×<?= (int)$it['quantity'] ?></span>
        <span>₱<?= number_format((float)$it['subtotal'], 2) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="display:flex;justify-content:space-between;font-weight:800;font-size:1.05rem;margin-bottom:1rem;">
      <span>Total</span><span>₱<?= number_format((float)$receipt['total_amount'], 2) ?></span>
    </div>
    <button type="button" class="btn-submit" onclick="closeModal('modal-receipt')">Done</button>
  </div>
</div>
<?php endif; ?>

<script>
const SERVICES     = <?= json_encode(array_values($allServices), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const REQUIREMENTS = <?= json_encode($requirementsByService, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

let cart = {};            // service_id -> qty
let currentFilter = 'all';

function money(n) { return '₱' + Number(n).toFixed(2); }

function escHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function renderTiles() {
  const q = document.getElementById('posSearch').value.trim().toLowerCase();
  const grid = document.getElementById('posGrid');

  const filtered = SERVICES.filter(s => {
    if (currentFilter !== 'all' && s.category !== currentFilter) return false;
    if (q && !s.name.toLowerCase().includes(q)) return false;
    return true;
  });

  if (filtered.length === 0) {
    grid.innerHTML = '<div class="empty-row" style="grid-column:1/-1;">No services match.</div>';
    return;
  }

  grid.innerHTML = filtered.map(s => {
    const reqs = REQUIREMENTS[s.id] || [];
    const reqText = reqs.length
      ? 'Uses: ' + reqs.map(r => escHtml(r.product_name) + ' ×' + r.quantity_used).join(', ')
      : 'No inventory items attached';
    const inCart = cart[s.id] || 0;
    return `<div class="pos-tile">
      <div class="pos-tile-top">
        <span class="badge bg-blue">${escHtml(s.category)}</span>
        <span class="pos-tile-price">${money(s.price)}</span>
      </div>
      <div class="pos-tile-name">${escHtml(s.name)}</div>
      <div class="pos-tile-reqs">${reqText}</div>
      <button type="button" class="btn-primary pos-add-btn" onclick="addToCart(${s.id})">
        ${inCart > 0 ? 'Added ×' + inCart + ' — Add another' : 'Add to Cart'}
      </button>
    </div>`;
  }).join('');
}

function addToCart(id) {
  cart[id] = (cart[id] || 0) + 1;
  renderTiles();
  renderCart();
}
function decCart(id) {
  if (!cart[id]) return;
  cart[id]--;
  if (cart[id] <= 0) delete cart[id];
  renderTiles();
  renderCart();
}
function removeFromCart(id) {
  delete cart[id];
  renderTiles();
  renderCart();
}

function renderCart() {
  const wrap = document.getElementById('cartItems');
  const ids = Object.keys(cart);
  const totalEl = document.getElementById('cartTotal');
  const checkoutBtn = document.getElementById('checkoutBtn');

  if (ids.length === 0) {
    wrap.innerHTML = '<div class="empty-row">Cart is empty. Click a service to add it.</div>';
    totalEl.textContent = money(0);
    checkoutBtn.disabled = true;
    return;
  }

  let total = 0;
  wrap.innerHTML = ids.map(id => {
    const s = SERVICES.find(x => x.id == id);
    if (!s) return '';
    const qty = cart[id];
    const sub = s.price * qty;
    total += sub;
    return `<div class="cart-line">
      <div>
        <div class="cart-line-name">${escHtml(s.name)}</div>
        <div class="cart-line-price">${money(s.price)} each</div>
      </div>
      <div class="cart-line-qty">
        <button type="button" onclick="decCart(${id})">−</button>
        <span>${qty}</span>
        <button type="button" onclick="addToCart(${id})">+</button>
      </div>
      <div class="cart-line-sub">${money(sub)}</div>
      <button type="button" class="cart-line-remove" onclick="removeFromCart(${id})" title="Remove">✕</button>
    </div>`;
  }).join('');

  totalEl.textContent = money(total);
  checkoutBtn.disabled = false;
}

function setPosFilter(f) {
  currentFilter = f;
  document.querySelectorAll('.pos-filter-tab').forEach(btn => btn.classList.toggle('active', btn.dataset.filter === f));
  renderTiles();
}

function openCheckout() {
  const ids = Object.keys(cart);
  if (ids.length === 0) return;

  const items = ids.map(id => ({ service_id: Number(id), qty: cart[id] }));
  document.getElementById('cart_json').value = JSON.stringify(items);
  document.getElementById('checkout-summary').innerHTML = document.getElementById('cartItems').innerHTML;
  document.getElementById('checkout-total').textContent = document.getElementById('cartTotal').textContent;
  openModal('modal-checkout');
}

document.getElementById('posSearch').addEventListener('input', renderTiles);

renderTiles();
renderCart();

<?php if ($receipt): ?>
openModal('modal-receipt');
<?php endif; ?>
</script>

<?php require_once 'includes/footer.php'; ?>