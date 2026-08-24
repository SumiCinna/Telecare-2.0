<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Test Kits — TELE-CARE</title>
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

    .btn-primary{background:var(--green);color:#fff;border:none;padding:0.65rem 1.3rem;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.85rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:0.4rem;transition:opacity 0.2s;margin-bottom:1.5rem}
    .btn-primary:hover{opacity:0.88}

    .two-col{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem}
    @media(max-width:1000px){.two-col{grid-template-columns:1fr}}

    .form-card{background:var(--white);border-radius:14px;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04);padding:1.5rem}
    .form-card h3{font-size:0.96rem;font-weight:700;margin-bottom:1.2rem;color:var(--green)}

    .form-field{margin-bottom:1rem}
    .form-field label{display:block;font-size:0.76rem;font-weight:700;color:#9ab0ae;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.4rem}
    .form-field input,.form-field select{width:100%;padding:0.6rem 0.8rem;border-radius:10px;border:1px solid rgba(36,68,65,0.15);font-family:'DM Sans',sans-serif;font-size:0.87rem;color:var(--green)}
    .form-field input:focus,.form-field select:focus{outline:none;border-color:var(--blue)}

    .items-list{display:flex;flex-direction:column;gap:0.8rem;max-height:400px;overflow-y:auto}
    .item-checkbox{display:flex;align-items:center;gap:0.7rem;padding:0.8rem;border-radius:10px;border:1px solid rgba(36,68,65,0.1);cursor:pointer;background:rgba(36,68,65,0.01);transition:all 0.15s}
    .item-checkbox:hover{background:rgba(36,68,65,0.04);border-color:rgba(36,68,65,0.2)}
    .item-checkbox input[type="checkbox"]{width:18px;height:18px;cursor:pointer;accent-color:var(--green)}
    .item-info{flex:1;min-width:0}
    .item-name{font-weight:700;font-size:0.88rem}
    .item-sub{font-size:0.75rem;color:#9ab0ae;margin-top:0.15rem}
    .item-qty{display:flex;align-items:center;gap:0.3rem;width:80px}
    .item-qty input{width:100%;padding:0.4rem 0.6rem;border-radius:8px;border:1px solid rgba(36,68,65,0.15);font-size:0.8rem;text-align:center}
    .qty-label{font-size:0.7rem;color:#9ab0ae;white-space:nowrap}

    .kits-list{background:var(--white);border-radius:14px;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04);padding:1.5rem}
    .kits-list h3{font-size:0.96rem;font-weight:700;margin-bottom:1.2rem;color:var(--green)}

    .kit-card{background:rgba(36,68,65,0.02);border:1px solid rgba(36,68,65,0.08);border-radius:12px;padding:1rem;margin-bottom:0.8rem;display:flex;align-items:flex-start;justify-content:space-between}
    .kit-info{flex:1}
    .kit-name{font-weight:700;font-size:0.9rem}
    .kit-service{font-size:0.75rem;color:#9ab0ae;margin-top:0.15rem}
    .kit-items{font-size:0.76rem;color:#7a8f8c;margin-top:0.4rem}
    .btn-delete{background:none;border:1px solid rgba(195,54,67,0.3);color:var(--red);padding:0.45rem 0.85rem;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:0.77rem;font-weight:700;cursor:pointer}

    .empty-state{text-align:center;padding:2rem;color:#9ab0ae;font-size:0.88rem}

    .toast{position:fixed;bottom:2rem;right:2rem;z-index:300;background:var(--green);color:#fff;padding:0.9rem 1.5rem;border-radius:14px;font-size:0.88rem;font-weight:600;box-shadow:0 8px 30px rgba(0,0,0,0.15);animation:slideIn 0.4s ease,fadeOut 0.4s 3s ease forwards}
    @keyframes slideIn{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
    @keyframes fadeOut{from{opacity:1}to{opacity:0;pointer-events:none}}
    @media(max-width:900px){.sidebar{display:none}}
  </style>
</head>
<body>

<aside class="sidebar"><div class="sidebar-logo">TELE<span>-</span>CARE</div><div class="sidebar-admin">Medtech Portal<br/><strong>Maria Santos</strong></div><nav class="nav-links"><a href="dashboard.php" class="nav-link">Dashboard</a><a href="notifications.php" class="nav-link">Notifications</a><a href="testlog.php" class="nav-link">Test Log</a><a href="inventory.php" class="nav-link">Kits Inventory</a><a href="kits.php" class="nav-link active">Test Kits</a></nav><div class="sidebar-logout"><a href="logout.php" class="logout-btn">Log Out</a></div></aside>

<div class="main">
  <div class="topbar">
    <div>
      <div style="font-size:0.75rem;color:#9ab0ae;font-weight:600;">Configuration</div>
      <div style="font-size:0.95rem;font-weight:700;">Test Kits</div>
    </div>
    <span style="font-size:0.82rem;color:#9ab0ae;">Create kits from inventory items</span>
  </div>

  <div class="page-content">

    <button class="btn-primary" onclick="showCreateForm()">
      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
      Create New Kit
    </button>

    <div class="two-col">

      <!-- Create Form -->
      <div class="form-card" id="createForm" style="display:none;">
        <h3>Create Test Kit</h3>
        <form id="kitForm" onsubmit="saveKit(event)">
          <div class="form-field">
            <label>Service</label>
            <select id="serviceSelect" required>
              <option value="">— Select service —</option>
              <option value="1" data-name="Complete Blood Count (CBC)">Complete Blood Count (CBC)</option><option value="2" data-name="Urinalysis">Urinalysis</option><option value="3" data-name="Blood Glucose Test">Blood Glucose Test</option><option value="4" data-name="Lipid Panel">Lipid Panel</option><option value="5" data-name="COVID-19 Rapid Antigen Test">COVID-19 Rapid Antigen Test</option>
            </select>
          </div>
          <div class="form-field">
            <label>Kit Name</label>
            <input type="text" id="kitName" placeholder="e.g. CBC Starter Kit" required/>
          </div>
          <div class="form-field" style="margin-bottom:0.6rem;">
            <label>Select Items</label>
          </div>
          <div class="items-list">
              <div style="font-size:0.75rem;font-weight:700;color:#9ab0ae;text-transform:uppercase;letter-spacing:0.04em;margin-top:0.6rem;margin-bottom:0.3rem;">Collection Kits</div>
                <label class="item-checkbox">
                  <input type="checkbox" class="kitItem" value="1" data-item-id="1" data-item-name="Urine Specimen Container" onchange="toggleQtyInput(this)"/>
                  <div class="item-info"><div class="item-name">Urine Specimen Container</div><div class="item-sub">BioPlas · 15ml</div></div>
                  <div class="item-qty" style="display:none;" id="qty-1"><input type="number" class="itemQty" data-item-id="1" value="1" min="1" style="margin:0;"/>
                    <span class="qty-label">pcs</span>
                  </div>
                </label>
              <label class="item-checkbox"><input type="checkbox" class="kitItem" value="2" data-item-id="2" data-item-name="Blood Collection Tube (EDTA)" onchange="toggleQtyInput(this)"/><div class="item-info"><div class="item-name">Blood Collection Tube (EDTA)</div><div class="item-sub">Greiner · 3ml</div></div><div class="item-qty" style="display:none;" id="qty-2"><input type="number" class="itemQty" data-item-id="2" value="1" min="1" style="margin:0;"/><span class="qty-label">pcs</span></div></label>
              <div style="font-size:0.75rem;font-weight:700;color:#9ab0ae;text-transform:uppercase;letter-spacing:0.04em;margin-top:0.6rem;margin-bottom:0.3rem;">Diagnostic Kits</div>
              <label class="item-checkbox"><input type="checkbox" class="kitItem" value="4" data-item-id="4" data-item-name="COVID-19 Rapid Antigen Test" onchange="toggleQtyInput(this)"/><div class="item-info"><div class="item-name">COVID-19 Rapid Antigen Test</div><div class="item-sub">Abbott · Individual</div></div><div class="item-qty" style="display:none;" id="qty-4"><input type="number" class="itemQty" data-item-id="4" value="1" min="1" style="margin:0;"/><span class="qty-label">pcs</span></div></label>
          </div>
          <div style="display:flex;gap:0.6rem;margin-top:1.3rem;">
            <button type="submit" class="btn-primary" style="flex:1;margin-bottom:0;">Save Kit</button>
            <button type="button" class="btn-ghost" style="flex:1;background:none;border:1px solid rgba(36,68,65,0.15);color:var(--green);padding:0.65rem;border-radius:10px;font-weight:700;cursor:pointer;" onclick="hideCreateForm()">Cancel</button>
          </div>
        </form>
      </div>

      <!-- Existing Kits -->
      <div class="kits-list">
        <h3>Created Kits (3)</h3>
        <div class="kit-card"><div class="kit-info"><div class="kit-name">Standard CBC Kit</div><div class="kit-service">Complete Blood Count (CBC)</div><div class="kit-items">3 items</div></div><button class="btn-delete" onclick="deleteKit(1)">Delete</button></div>
        <div class="kit-card"><div class="kit-info"><div class="kit-name">COVID-19 Screening Kit</div><div class="kit-service">COVID-19 Rapid Antigen Test</div><div class="kit-items">2 items</div></div><button class="btn-delete" onclick="deleteKit(2)">Delete</button></div>
        <div class="kit-card"><div class="kit-info"><div class="kit-name">Thyroid Screening Kit</div><div class="kit-service">Thyroid Function Test</div><div class="kit-items">4 items</div></div><button class="btn-delete" onclick="deleteKit(3)">Delete</button></div>
      </div>

    </div>

  </div>
</div>

<script>
  let kits = JSON.parse(localStorage.getItem('medtech_kits')) || [];

  function showCreateForm(){
    document.getElementById('createForm').style.display = 'block';
  }
  function hideCreateForm(){
    document.getElementById('createForm').style.display = 'none';
    document.getElementById('kitForm').reset();
    document.querySelectorAll('.item-qty').forEach(el => el.style.display = 'none');
  }
  function toggleQtyInput(checkbox){
    const itemId = checkbox.dataset.itemId;
    const qtyContainer = document.getElementById('qty-' + itemId);
    qtyContainer.style.display = checkbox.checked ? 'flex' : 'none';
  }
  
  function saveKit(event){
    event.preventDefault();
    
    const serviceSelect = document.getElementById('serviceSelect');
    const serviceName = serviceSelect.options[serviceSelect.selectedIndex].dataset.name;
    const servicId = serviceSelect.value;
    const kitName = document.getElementById('kitName').value;
    
    const selectedItems = Array.from(document.querySelectorAll('.kitItem:checked')).map(cb => {
      const itemId = parseInt(cb.value);
      const qtyInput = document.querySelector(`.itemQty[data-item-id="${itemId}"]`);
      return {
        item_id: itemId,
        item_name: cb.dataset.itemName,
        quantity: parseInt(qtyInput.value)
      };
    });
    
    if (selectedItems.length === 0) {
      alert('Please select at least one item.');
      return;
    }
    
    // Create kit object
    const kit = {
      id: Date.now(),
      name: kitName,
      service_id: parseInt(servicId),
      service_name: serviceName,
      items: selectedItems
    };
    
    kits.push(kit);
    localStorage.setItem('medtech_kits', JSON.stringify(kits));
    
    // Add to list visually
    addKitToList(kit);
    
    // Reset form
    hideCreateForm();
    
    // Show success toast
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.textContent = '✓ Test kit created successfully!';
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3500);
  }
  
  function addKitToList(kit){
    const kitsList = document.querySelector('.kits-list');
    const emptyState = kitsList.querySelector('.empty-state');
    if (emptyState) emptyState.remove();
    
    const kitCard = document.createElement('div');
    kitCard.className = 'kit-card';
    kitCard.id = 'kit-' + kit.id;
    kitCard.innerHTML = `
      <div class="kit-info">
        <div class="kit-name">${escapeHtml(kit.name)}</div>
        <div class="kit-service">${escapeHtml(kit.service_name)}</div>
        <div class="kit-items">${kit.items.length} item${kit.items.length==1?'':'s'}</div>
      </div>
      <button class="btn-delete" onclick="deleteKit(${kit.id})">Delete</button>
    `;
    
    const kitsList_body = document.querySelector('.kits-list');
    kitsList_body.appendChild(kitCard);
  }
  
  function deleteKit(id){
    if (confirm('Delete this kit? This cannot be undone.')) {
      const card = document.getElementById('kit-' + id);
      card.style.opacity = '0';
      card.style.transition = 'opacity 0.3s ease';
      
      setTimeout(() => {
        card.remove();
        kits = kits.filter(k => k.id !== id);
        localStorage.setItem('medtech_kits', JSON.stringify(kits));
        
        // Show empty state if no kits left
        const remaining = document.querySelectorAll('.kit-card').length;
        if (remaining === 0) {
          const kitsList = document.querySelector('.kits-list');
          kitsList.innerHTML += '<div class="empty-state">No test kits created yet.</div>';
        }
      }, 300);
    }
  }
  
  function escapeHtml(text){
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
  }
  
  // Load stored kits on page load
  document.addEventListener('DOMContentLoaded', function(){
    const h3 = document.querySelector('.kits-list h3');
    const count = kits.length;
    h3.textContent = 'Created Kits (' + count + ')';
    
    const kitsList = document.querySelector('.kits-list');
    if (count > 0) {
      const emptyState = kitsList.querySelector('.empty-state');
      if (emptyState) emptyState.remove();
      
      kits.forEach(kit => {
        addKitToList(kit);
      });
    }
  });

  setTimeout(() => { const t = document.querySelector('.toast'); if(t) t.remove(); }, 3500);
</script>
</body>
</html>