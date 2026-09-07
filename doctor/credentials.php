<?php
// doctor/credentials.php
require_once 'includes/auth.php';

// ── FRONTEND ONLY ──
// No DB reads/writes or upload handling here yet.
// $doc is assumed available from auth.php (same as profile.php / dashboard.php).
// Placeholder values below stand in for future $doc['license_number'],
// $doc['license_verified'], $doc['esignature_path'].

$page_title       = 'Credentials — TELE-CARE';
$page_title_short = 'Credentials';
$active_nav       = 'credentials';
require_once 'includes/header.php';
?>

<style>
  /* ── Tabs (Draw / Upload) ── */
  .sig-tabs{display:flex;gap:0.4rem;background:rgba(36,68,65,0.06);border-radius:14px;padding:0.3rem;margin-bottom:1rem;}
  .sig-tab{flex:1;text-align:center;padding:0.55rem 0.6rem;border-radius:10px;font-size:0.82rem;font-weight:700;color:var(--muted);cursor:pointer;transition:all .2s;user-select:none;}
  .sig-tab.active{background:#fff;color:var(--green);box-shadow:0 1px 4px rgba(0,0,0,0.08);}
  .sig-panel{display:none;}
  .sig-panel.active{display:block;}

  /* ── Draw panel ── */
  .sig-canvas-wrap{position:relative;border:1.5px dashed rgba(36,68,65,0.2);border-radius:14px;background:#fcfcfa;overflow:hidden;}
  #sigCanvas{width:100%;height:220px;display:block;touch-action:none;cursor:crosshair;}
  .sig-baseline{position:absolute;left:5%;right:5%;bottom:22%;border-bottom:1.5px dashed rgba(36,68,65,0.18);pointer-events:none;}
  .sig-hint{position:absolute;top:50%;left:0;right:0;text-align:center;transform:translateY(-50%);font-size:0.78rem;color:rgba(36,68,65,0.3);font-style:italic;pointer-events:none;}
  .sig-toolbar{display:flex;justify-content:space-between;align-items:center;margin-top:0.7rem;gap:0.6rem;}
  .sig-colors{display:flex;gap:0.4rem;}
  .sig-color{width:22px;height:22px;border-radius:50%;cursor:pointer;border:2px solid transparent;transition:all .15s;}
  .sig-color.active{border-color:var(--blue);transform:scale(1.15);}
  .sig-clear-btn{display:inline-flex;align-items:center;gap:0.35rem;background:rgba(195,54,67,0.08);color:var(--red);border:none;border-radius:50px;padding:0.4rem 0.9rem;font-size:0.78rem;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s;}
  .sig-clear-btn:hover{background:rgba(195,54,67,0.18);}

  /* ── Upload panel ── */
  .sig-dropzone{border:1.5px dashed rgba(36,68,65,0.2);border-radius:14px;background:#fcfcfa;padding:1.6rem 1rem;text-align:center;cursor:pointer;transition:all .2s;}
  .sig-dropzone:hover,.sig-dropzone.dragover{border-color:var(--blue);background:rgba(63,130,227,0.05);}
  .sig-dropzone svg{width:30px;height:30px;color:var(--muted);margin-bottom:0.5rem;}
  .sig-dropzone .dz-title{font-size:0.85rem;font-weight:700;color:var(--green);}
  .sig-dropzone .dz-sub{font-size:0.74rem;color:var(--muted);margin-top:0.2rem;}
  .sig-upload-preview{display:none;text-align:center;padding:1rem;border:1.5px solid rgba(36,68,65,0.12);border-radius:14px;background:#fcfcfa;}
  .sig-upload-preview img{max-width:100%;max-height:180px;object-fit:contain;}
  .sig-upload-preview .dz-remove{display:inline-flex;align-items:center;gap:0.35rem;margin-top:0.7rem;background:rgba(195,54,67,0.08);color:var(--red);border:none;border-radius:50px;padding:0.4rem 0.9rem;font-size:0.78rem;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;}

  /* ── License section ── */
  .license-status-row{display:flex;align-items:center;gap:0.6rem;margin-top:0.5rem;}
  .verify-note{display:flex;gap:0.5rem;align-items:flex-start;background:rgba(217,119,6,0.08);border-radius:12px;padding:0.7rem 0.85rem;font-size:0.78rem;color:#92620a;margin-top:0.7rem;}
  .verify-note svg{width:16px;height:16px;flex-shrink:0;margin-top:0.1rem;}

  /* ── Sig preview on saved-state card ── */
  .sig-saved-box{border:1.5px solid rgba(36,68,65,0.1);border-radius:14px;background:#fcfcfa;padding:1rem;text-align:center;}
  .sig-saved-box img{max-height:80px;max-width:100%;object-fit:contain;}
</style>

<div class="page">

  <div class="alert-success" style="display:none;" id="successBanner">✓ Credentials updated successfully.</div>

  <!-- Header -->
  <div class="card" style="text-align:center;padding:1.6rem 1rem;">
    <div style="font-family:'Playfair Display',serif;font-size:1.2rem;font-weight:700;">Professional Credentials</div>
    <div style="font-size:0.83rem;color:var(--muted);margin-top:0.2rem;">
      Your license number and e-signature are used on prescriptions, referrals, and other official documents.
    </div>
    <div style="margin-top:0.6rem;display:flex;justify-content:center;gap:0.5rem;flex-wrap:wrap;">
      <span class="badge badge-orange" id="licenseStatusBadge">Pending Verification</span>
    </div>
  </div>

  <!-- License Number -->
  <div class="card">
    <div class="section-label">License Number</div>
    <form method="POST" id="licenseForm">
      <div class="form-field">
        <label class="field-label">PRC License Number</label>
        <input type="text" name="license_number" id="licenseNumber" class="field-input" placeholder="e.g. 0123456" maxlength="20"/>
      </div>
      <div class="verify-note">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>Changing your license number will require re-verification by TELE-CARE admin before it appears on documents.</span>
      </div>
      <button type="submit" name="update_license" class="btn-submit" style="margin-top:1rem;">Save License Number</button>
    </form>
  </div>

  <!-- E-Signature -->
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.8rem;">
      <div class="section-label" style="margin-bottom:0;">E-Signature</div>
      <span style="font-size:0.78rem;color:var(--muted);" id="sigSavedNote"></span>
    </div>

    <!-- Currently saved signature (placeholder / hidden until one exists) -->
    <div class="sig-saved-box" id="sigSavedBox" style="display:none;margin-bottom:1rem;">
      <img id="sigSavedImg" src="" alt="Current signature"/>
      <div style="font-size:0.74rem;color:var(--muted);margin-top:0.4rem;">Current signature on file</div>
    </div>

    <div class="sig-tabs">
      <div class="sig-tab active" data-tab="draw">Draw</div>
      <div class="sig-tab" data-tab="upload">Upload Image</div>
    </div>

    <!-- Draw panel -->
    <div class="sig-panel active" id="panel-draw">
      <div class="sig-canvas-wrap">
        <canvas id="sigCanvas"></canvas>
        <div class="sig-baseline"></div>
        <div class="sig-hint" id="sigHint">Sign here</div>
      </div>
      <div class="sig-toolbar">
        <div class="sig-colors">
          <div class="sig-color active" data-color="#1a1a1a" style="background:#1a1a1a;"></div>
          <div class="sig-color" data-color="#244441" style="background:#244441;"></div>
          <div class="sig-color" data-color="#3f82e3" style="background:#3f82e3;"></div>
        </div>
        <button type="button" class="sig-clear-btn" id="clearCanvasBtn">
          <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9.5 3h5l.5 4h-6l.5-4z"/></svg>
          Clear
        </button>
      </div>
    </div>

    <!-- Upload panel -->
    <div class="sig-panel" id="panel-upload">
      <div class="sig-dropzone" id="sigDropzone">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l-3.75 3.75M12 9.75l3.75 3.75M21 16.5v2.25A2.25 2.25 0 0118.75 21H5.25A2.25 2.25 0 013 18.75V16.5"/></svg>
        <div class="dz-title">Click to upload or drag & drop</div>
        <div class="dz-sub">PNG or JPG, transparent background recommended · Max 5MB</div>
        <input type="file" id="sigFileInput" accept="image/png, image/jpeg" style="display:none;"/>
      </div>
      <div class="sig-upload-preview" id="sigUploadPreview">
        <img id="sigUploadImg" src="" alt="Uploaded signature preview"/>
        <div>
          <button type="button" class="dz-remove" id="removeUploadBtn">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            Remove
          </button>
        </div>
      </div>
    </div>

    <button type="button" id="saveSignatureBtn" class="btn-submit" style="margin-top:1.1rem;">Save Signature</button>
  </div>

  <!-- Usage note -->
  <div class="card" style="display:flex;gap:0.7rem;align-items:flex-start;">
    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" style="color:var(--blue);flex-shrink:0;margin-top:0.1rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.86.416-1.451 1.245-1.451 2.227v.117M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z"/></svg>
    <div style="font-size:0.8rem;color:var(--muted);line-height:1.5;">
      Your saved signature will be automatically stamped on prescriptions and referral letters you issue through TELE-CARE. You can update it at any time.
    </div>
  </div>

</div>

<script>
  // ── Tab switching ──
  document.querySelectorAll('.sig-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.sig-tab').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.sig-panel').forEach(p => p.classList.remove('active'));
      tab.classList.add('active');
      document.getElementById('panel-' + tab.dataset.tab).classList.add('active');
    });
  });

  // ── Signature canvas (draw) ──
  const canvas = document.getElementById('sigCanvas');
  const ctx = canvas.getContext('2d');
  const hint = document.getElementById('sigHint');
  let drawing = false;
  let hasDrawn = false;
  let strokeColor = '#1a1a1a';

  function resizeCanvas() {
    const ratio = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();
    canvas.width = rect.width * ratio;
    canvas.height = rect.height * ratio;
    ctx.scale(ratio, ratio);
    ctx.lineWidth = 2.2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = strokeColor;
  }
  window.addEventListener('resize', resizeCanvas);
  resizeCanvas();

  function getPos(e) {
    const rect = canvas.getBoundingClientRect();
    const point = e.touches ? e.touches[0] : e;
    return { x: point.clientX - rect.left, y: point.clientY - rect.top };
  }

  function startDraw(e) {
    e.preventDefault();
    drawing = true;
    hasDrawn = true;
    hint.style.display = 'none';
    const p = getPos(e);
    ctx.beginPath();
    ctx.moveTo(p.x, p.y);
  }
  function moveDraw(e) {
    if (!drawing) return;
    e.preventDefault();
    const p = getPos(e);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
  }
  function endDraw() { drawing = false; }

  canvas.addEventListener('mousedown', startDraw);
  canvas.addEventListener('mousemove', moveDraw);
  canvas.addEventListener('mouseup', endDraw);
  canvas.addEventListener('mouseleave', endDraw);
  canvas.addEventListener('touchstart', startDraw);
  canvas.addEventListener('touchmove', moveDraw);
  canvas.addEventListener('touchend', endDraw);

  document.querySelectorAll('.sig-color').forEach(sw => {
    sw.addEventListener('click', () => {
      document.querySelectorAll('.sig-color').forEach(s => s.classList.remove('active'));
      sw.classList.add('active');
      strokeColor = sw.dataset.color;
      ctx.strokeStyle = strokeColor;
    });
  });

  document.getElementById('clearCanvasBtn').addEventListener('click', () => {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    hasDrawn = false;
    hint.style.display = 'block';
  });

  // ── Upload panel ──
  const dropzone = document.getElementById('sigDropzone');
  const fileInput = document.getElementById('sigFileInput');
  const uploadPreview = document.getElementById('sigUploadPreview');
  const uploadImg = document.getElementById('sigUploadImg');

  dropzone.addEventListener('click', () => fileInput.click());
  dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('dragover'); });
  dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));
  dropzone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropzone.classList.remove('dragover');
    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      handleFile(e.dataTransfer.files[0]);
    }
  });
  fileInput.addEventListener('change', () => {
    if (fileInput.files && fileInput.files[0]) handleFile(fileInput.files[0]);
  });

  function handleFile(file) {
    if (!file.type.match(/^image\/(png|jpeg)$/)) {
      alert('Please upload a PNG or JPG image.');
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      alert('File is too large. Max size is 5MB.');
      return;
    }
    const reader = new FileReader();
    reader.onload = (e) => {
      uploadImg.src = e.target.result;
      dropzone.style.display = 'none';
      uploadPreview.style.display = 'block';
    };
    reader.readAsDataURL(file);
  }

  document.getElementById('removeUploadBtn').addEventListener('click', () => {
    fileInput.value = '';
    uploadImg.src = '';
    uploadPreview.style.display = 'none';
    dropzone.style.display = 'block';
  });

  // ── Save signature (frontend placeholder — no backend wired yet) ──
  document.getElementById('saveSignatureBtn').addEventListener('click', () => {
    const activeTab = document.querySelector('.sig-tab.active').dataset.tab;
    if (activeTab === 'draw' && !hasDrawn) {
      alert('Please draw your signature first.');
      return;
    }
    if (activeTab === 'upload' && !uploadImg.src) {
      alert('Please upload a signature image first.');
      return;
    }
    // TODO: wire to backend — POST canvas.toDataURL('image/png') or the uploaded file
    const banner = document.getElementById('successBanner');
    banner.style.display = 'block';
    banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  });
</script>

<?php require_once 'includes/nav.php'; ?>
</body>
</html>