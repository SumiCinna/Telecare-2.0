<?php
// admin/templates.php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
require_once '../database/config.php';
// Development preview: allow this page to render without an admin login.
// Restore the session guard before deploying this page.

$page_title       = 'Document Templates — TELE-CARE';
$page_title_short = 'Templates';
$activeNav         = 'templates';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($page_title) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="assets/admin.css" rel="stylesheet"/>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div>
      <div style="font-size:0.75rem;color:#9ab0ae;font-weight:600;">Admin Portal</div>
      <div style="font-size:0.95rem;font-weight:700;">Document Templates</div>
    </div>
    <span style="font-size:0.82rem;color:#9ab0ae;">Customize clinic documents</span>
  </div>

  <div class="page-content">

<style>
  .card{background:var(--white);border-radius:16px;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04);padding:1.8rem;margin-bottom:1.2rem;}
  .alert-success{background:rgba(34,197,94,0.1);color:#16803c;border:1px solid rgba(34,197,94,0.2);border-radius:12px;padding:0.75rem 1rem;margin-bottom:1.2rem;font-size:0.85rem;font-weight:600;}

  /* ── Doc-type tabs ── */
  .tpl-tabs{display:flex;gap:0.4rem;background:rgba(36,68,65,0.06);border-radius:14px;padding:0.3rem;margin-bottom:1rem;overflow-x:auto;}
  .tpl-tab{flex:1;text-align:center;padding:0.55rem 0.7rem;border-radius:10px;font-size:0.8rem;font-weight:700;color:var(--muted);cursor:pointer;transition:all .2s;user-select:none;white-space:nowrap;}
  .tpl-tab.active{background:#fff;color:var(--green);box-shadow:0 1px 4px rgba(0,0,0,0.08);}
  .tpl-panel{display:none;}
  .tpl-panel.active{display:block;}

  /* ── Locked-field note ── */
  .locked-note{display:flex;gap:0.5rem;align-items:flex-start;background:rgba(63,130,227,0.08);border-radius:12px;padding:0.7rem 0.85rem;font-size:0.78rem;color:#2a5da3;margin-bottom:1rem;}
  .locked-note svg{width:16px;height:16px;flex-shrink:0;margin-top:0.1rem;}

  /* ── Logo uploader ── */
  .logo-row{display:flex;align-items:center;gap:0.9rem;}
  .logo-preview{width:64px;height:64px;border-radius:14px;border:1.5px dashed rgba(36,68,65,0.2);background:#fcfcfa;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;}
  .logo-preview img{width:100%;height:100%;object-fit:contain;}
  .logo-preview svg{width:22px;height:22px;color:var(--muted);}
  .logo-actions{display:flex;flex-direction:column;gap:0.4rem;}
  .logo-btn{display:inline-flex;align-items:center;gap:0.35rem;background:rgba(36,68,65,0.06);color:var(--green);border:none;border-radius:50px;padding:0.4rem 0.9rem;font-size:0.78rem;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s;width:fit-content;}
  .logo-btn:hover{background:rgba(36,68,65,0.12);}
  .logo-hint{font-size:0.72rem;color:var(--muted);}

  /* ── Field grid ── */
  .field-grid{display:grid;grid-template-columns:1fr 1fr;gap:0.9rem;}
  @media (max-width:560px){.field-grid{grid-template-columns:1fr;}}

  /* ── Color swatches (accent) ── */
  .accent-row{display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;}
  .accent-swatch{width:26px;height:26px;border-radius:50%;cursor:pointer;border:2px solid transparent;transition:all .15s;}
  .accent-swatch.active{border-color:var(--blue);transform:scale(1.15);}
  .accent-custom{width:26px;height:26px;border-radius:50%;border:1.5px dashed rgba(36,68,65,0.3);display:flex;align-items:center;justify-content:center;cursor:pointer;background:#fff;}
  .accent-custom svg{width:12px;height:12px;color:var(--muted);}

  /* ── Paper previews ── */
  .doc-preview{display:flex;justify-content:center;background:#eef1f0;border:1px solid rgba(36,68,65,0.1);border-radius:14px;padding:1.2rem;overflow:auto;}
  .paper{width:min(100%,470px);min-height:530px;background:#fff;border:1px solid #cfd5d3;box-shadow:0 6px 18px rgba(36,68,65,0.12);padding:1.25rem 1.1rem;color:#202525;font-family:Arial,sans-serif;font-size:0.68rem;line-height:1.45;}
  .paper-head{text-align:center;border-bottom:2px solid var(--preview-accent,#244441);padding-bottom:0.6rem;margin-bottom:0.7rem;}
  .paper-logo{width:38px;height:38px;border:1px solid #d8dddb;border-radius:50%;margin:0 auto 0.3rem;display:flex;align-items:center;justify-content:center;color:#83918e;font-size:0.55rem;overflow:hidden;}
  .paper-logo img{width:100%;height:100%;object-fit:contain;}
  .paper-clinic{font-weight:700;font-size:0.92rem;color:var(--preview-accent,#244441);}
  .paper-meta{font-size:0.59rem;color:#66716f;}
  .paper-title{text-align:center;font-weight:700;text-decoration:underline;font-size:0.85rem;margin:0.8rem 0;}
  .paper-fields{display:grid;gap:0.38rem;margin-bottom:0.8rem;}
  .paper-line{border-bottom:1px solid #aeb8b5;min-height:1.1rem;}
  .paper-two{display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;}
  .paper-three{display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.55rem;}
  .paper-section{font-weight:700;margin:0.7rem 0 0.25rem;}
  .paper-list{display:grid;grid-template-columns:1fr 1fr;gap:0.25rem 0.8rem;}
  .paper-list span:before{content:'□ ';}
  .paper-rx{font-family:Georgia,serif;font-size:2rem;margin:0.5rem 0;}
  .paper-space{min-height:190px;border-bottom:1px solid #aeb8b5;}
  .paper-footer{display:flex;justify-content:flex-end;text-align:center;margin-top:1.1rem;}
  .paper-signature{min-width:135px;border-top:1px solid #6e7a77;padding-top:0.25rem;font-size:0.62rem;}

  .save-bar{display:flex;gap:0.6rem;align-items:center;margin-top:1.1rem;}
  .save-bar .btn-submit{flex:1;}
  .reset-btn{display:inline-flex;align-items:center;gap:0.35rem;background:rgba(36,68,65,0.06);color:var(--muted);border:none;border-radius:50px;padding:0.65rem 1rem;font-size:0.8rem;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s;white-space:nowrap;}
  .reset-btn:hover{background:rgba(36,68,65,0.12);}
  @media (max-width:600px){
    .topbar{padding:1rem;}
    .topbar > span{display:none;}
    .page-content{padding:1rem;}
    .card{padding:1.2rem;}
  }
</style>

<div class="page">

  <div class="alert-success" style="display:none;" id="successBanner">✓ Template updated successfully.</div>

  <!-- Header -->
  <div class="card" style="text-align:center;padding:1.6rem 1rem;">
    <div style="font-family:'Playfair Display',serif;font-size:1.2rem;font-weight:700;">Document Templates</div>
    <div style="font-size:0.83rem;color:var(--muted);margin-top:0.2rem;">
      Customize how your clinic's branding appears on prescriptions, lab requests, and medical certificates.
    </div>
  </div>

  <div class="locked-note">
    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
    <span>Layout and required fields are set platform-wide by TELE-CARE Admin. You can customize your clinic's branding, contact details, document numbering, and footer below.</span>
  </div>

  <div class="tpl-tabs">
    <div class="tpl-tab active" data-tab="rx">E-Prescription</div>
    <div class="tpl-tab" data-tab="lab">Lab Request</div>
    <div class="tpl-tab" data-tab="cert">Med Certificate</div>
  </div>

  <!-- Each panel shares the branding form fields but keeps independent footer/doc-no/signature settings per document type -->
  <?php
    $doc_types = [
      'rx'   => ['label' => 'E-Prescription', 'title' => 'PRESCRIPTION', 'default_prefix' => 'RX', 'footer' => 'This prescription is issued electronically via TELE-CARE.', 'signature' => 'Attending Physician'],
      'lab'  => ['label' => 'Laboratory Request', 'title' => 'LABORATORY REQUEST', 'default_prefix' => 'LAB', 'footer' => 'Please bring this request to an accredited laboratory.', 'signature' => 'Requesting Physician'],
      'cert' => ['label' => 'Medical Certificate', 'title' => 'MEDICAL CERTIFICATE', 'default_prefix' => 'MC', 'footer' => 'This document is issued for non-medico-legal purposes only.', 'signature' => 'Attending Physician'],
    ];
    foreach ($doc_types as $key => $meta):
  ?>
  <div class="tpl-panel <?= $key === 'rx' ? 'active' : '' ?>" id="panel-<?= $key ?>" data-doctype="<?= $key ?>">

    <!-- Clinic Identity -->
    <div class="card">
      <div class="section-label">Clinic Identity</div>

      <div class="form-field">
        <label class="field-label">Clinic Logo</label>
        <div class="logo-row">
          <div class="logo-preview" id="logoPreview-<?= $key ?>">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3 8.25V15a2.25 2.25 0 002.25 2.25h13.5A2.25 2.25 0 0021 15V8.25m-18 0A2.25 2.25 0 015.25 6h13.5A2.25 2.25 0 0121 8.25m-18 0v.008l18-.008"/></svg>
          </div>
          <div class="logo-actions">
            <label class="logo-btn">
              Upload logo
              <input type="file" accept="image/png, image/jpeg" class="logo-input" data-target="logoPreview-<?= $key ?>" style="display:none;"/>
            </label>
            <span class="logo-hint">PNG, transparent background · Max 2MB</span>
          </div>
        </div>
      </div>

      <div class="field-grid">
        <div class="form-field">
          <label class="field-label">Clinic Name</label>
          <input type="text" name="clinic_name" class="field-input" placeholder="e.g. ExcellCare Medical Clinic" maxlength="120"/>
        </div>
        <div class="form-field">
          <label class="field-label">PhilHealth / Accreditation No.</label>
          <input type="text" name="accreditation_no" class="field-input" placeholder="Optional" maxlength="40"/>
        </div>
        <div class="form-field" style="grid-column:1 / -1;">
          <label class="field-label">Clinic Address</label>
          <input type="text" name="clinic_address" class="field-input" placeholder="Street, Barangay, City" maxlength="180"/>
        </div>
        <div class="form-field">
          <label class="field-label">Contact Number</label>
          <input type="text" name="clinic_contact" class="field-input" placeholder="e.g. (02) 8123 4567" maxlength="40"/>
        </div>
        <div class="form-field">
          <label class="field-label">Clinic Email</label>
          <input type="email" name="clinic_email" class="field-input" placeholder="e.g. frontdesk@clinic.com" maxlength="120"/>
        </div>
      </div>

      <div class="form-field" style="margin-top:0.9rem;">
        <label class="field-label">Accent Color</label>
        <div class="accent-row">
          <div class="accent-swatch active" data-color="#244441" style="background:#244441;"></div>
          <div class="accent-swatch" data-color="#3f82e3" style="background:#3f82e3;"></div>
          <div class="accent-swatch" data-color="#0f766e" style="background:#0f766e;"></div>
          <div class="accent-swatch" data-color="#92400e" style="background:#92400e;"></div>
          <div class="accent-swatch" data-color="#7c3aed" style="background:#7c3aed;"></div>
          <label class="accent-custom">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            <input type="color" class="accent-custom-input" style="display:none;"/>
          </label>
        </div>
      </div>
    </div>

    <!-- Document Numbering & Footer -->
    <div class="card">
      <div class="section-label"><?= htmlspecialchars($meta['label']) ?> Settings</div>

      <div class="field-grid">
        <div class="form-field">
          <label class="field-label">Document Number Prefix</label>
          <input type="text" name="doc_prefix" class="field-input" value="<?= htmlspecialchars($meta['default_prefix']) ?>" data-default="<?= htmlspecialchars($meta['default_prefix'], ENT_QUOTES) ?>" maxlength="10"/>
        </div>
        <div class="form-field">
          <label class="field-label">Next Number Starts At</label>
          <input type="number" name="doc_start_no" class="field-input" placeholder="e.g. 1" min="1"/>
        </div>
      </div>

      <div class="form-field">
        <label class="field-label">Footer / Disclaimer Text</label>
        <textarea name="footer_text" class="field-input" rows="2" maxlength="300" data-default="<?= htmlspecialchars($meta['footer'], ENT_QUOTES) ?>" placeholder="e.g. This document is issued electronically via TELE-CARE and is valid without a physical signature."><?= htmlspecialchars($meta['footer']) ?></textarea>
      </div>

      <div class="form-field">
        <label class="field-label">Signature Area Label</label>
        <input type="text" name="signature_label" class="field-input" value="<?= htmlspecialchars($meta['signature']) ?>" data-default="<?= htmlspecialchars($meta['signature'], ENT_QUOTES) ?>" placeholder="e.g. Attending Physician" maxlength="60"/>
        <span class="logo-hint" style="display:block;margin-top:0.3rem;">The doctor's saved e-signature (set under their own Credentials page) is stamped above this label.</span>
      </div>
    </div>

    <!-- Live Preview -->
    <div class="card">
      <div class="section-label">Live Preview</div>
      <div class="doc-preview" id="preview-<?= $key ?>" style="--preview-accent:#244441;">
        <div class="paper paper-<?= $key ?>">
          <div class="paper-head">
            <div class="paper-logo" id="previewLogo-<?= $key ?>">LOGO</div>
            <div class="paper-clinic" id="previewName-<?= $key ?>">Your Clinic Name</div>
            <div class="paper-meta" id="previewMeta-<?= $key ?>">Clinic address · Contact number</div>
          </div>
          <div class="paper-title"><?= htmlspecialchars($meta['title']) ?></div>
          <?php if ($key === 'rx'): ?>
            <div class="paper-fields"><div>Patient: <span class="paper-line"></span></div><div>Address: <span class="paper-line"></span></div><div class="paper-two"><span>Age: __________</span><span>Date: __________</span></div><div>Ward / OR No.: <span class="paper-line"></span></div></div>
            <div class="paper-rx">Rx</div><div class="paper-space"></div>
          <?php elseif ($key === 'lab'): ?>
            <div class="paper-fields"><div class="paper-two"><span>Name: __________</span><span>Age/Sex: ______</span></div><div class="paper-two"><span>Address: ________</span><span>Date: ________</span></div></div>
            <div class="paper-section">Blood Chemistry</div><div class="paper-list"><span>FBS</span><span>SGPT</span><span>BUN</span><span>SGOT</span><span>Creatinine</span><span>Lipid Profile</span><span>Uric Acid</span><span>Electrolytes</span></div>
            <div class="paper-section">Other Tests</div><div class="paper-list"><span>CBC</span><span>HbA1c</span><span>Urinalysis</span><span>Chest X-ray</span><span>Platelet</span><span>ECG</span></div><div class="paper-space" style="min-height:90px"></div>
          <?php else: ?>
            <div class="paper-fields"><div>To whom it may concern:</div><div>This is to certify that</div><div class="paper-line"></div><div>is presently residing at</div><div class="paper-line"></div><div>Assessment / Impression:</div><div class="paper-line"></div><div>Recommendations / Remarks:</div><div class="paper-line"></div><div class="paper-line"></div></div>
          <?php endif; ?>
          <div class="paper-footer"><div class="paper-signature"><div id="previewSigLabel-<?= $key ?>"><?= htmlspecialchars($meta['signature']) ?></div><small>License No. __________</small></div></div>
          <div class="paper-meta" id="previewFooter-<?= $key ?>" style="margin-top:.8rem;text-align:center;"><?= htmlspecialchars($meta['footer']) ?></div>
          <div class="paper-meta" id="previewDocNo-<?= $key ?>" style="text-align:center;margin-top:.3rem;"><?= htmlspecialchars($meta['default_prefix']) ?>-000001</div>
        </div>
      </div>
    </div>

    <div class="save-bar">
      <button type="button" class="reset-btn" data-action="reset" data-doctype="<?= $key ?>">Reset to Default</button>
      <button type="button" class="btn-submit" data-action="save" data-doctype="<?= $key ?>">Save <?= htmlspecialchars($meta['label']) ?> Template</button>
    </div>

  </div>
  <?php endforeach; ?>

</div>

<script>
  // ── Tab switching ──
  document.querySelectorAll('.tpl-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.tpl-tab').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.tpl-panel').forEach(p => p.classList.remove('active'));
      tab.classList.add('active');
      document.getElementById('panel-' + tab.dataset.tab).classList.add('active');
    });
  });

  // ── Per-panel live preview wiring ──
  document.querySelectorAll('.tpl-panel').forEach(panel => {
    const key = panel.dataset.doctype;
    const nameInput    = panel.querySelector('input[name="clinic_name"]');
    const addressInput = panel.querySelector('input[name="clinic_address"]');
    const contactInput = panel.querySelector('input[name="clinic_contact"]');
    const prefixInput  = panel.querySelector('input[name="doc_prefix"]');
    const startInput   = panel.querySelector('input[name="doc_start_no"]');
    const footerInput  = panel.querySelector('textarea[name="footer_text"]');
    const sigLabelInput= panel.querySelector('input[name="signature_label"]');

    const previewName    = document.getElementById('previewName-' + key);
    const previewMeta    = document.getElementById('previewMeta-' + key);
    const previewDocNo   = document.getElementById('previewDocNo-' + key);
    const previewFooter  = document.getElementById('previewFooter-' + key);
    const previewSigLabel= document.getElementById('previewSigLabel-' + key);
    const previewBox     = document.getElementById('preview-' + key);

    function syncPreview() {
      previewName.textContent = nameInput.value.trim() || 'Your Clinic Name';
      const metaParts = [addressInput.value.trim(), contactInput.value.trim()].filter(Boolean);
      previewMeta.textContent = metaParts.length ? metaParts.join(' · ') : 'Clinic address · Contact number';
      const prefix = (prefixInput.value.trim() || 'DOC').toUpperCase();
      const start = String(parseInt(startInput.value, 10) || 1).padStart(6, '0');
      previewDocNo.textContent = prefix + '-' + start;
      previewFooter.textContent = footerInput.value.trim() || 'Footer / disclaimer text appears here.';
      previewSigLabel.textContent = sigLabelInput.value.trim() || 'Attending Physician';
    }
    [nameInput, addressInput, contactInput, prefixInput, startInput, footerInput, sigLabelInput]
      .forEach(el => el.addEventListener('input', syncPreview));

    // Accent color swatches
    panel.querySelectorAll('.accent-swatch').forEach(sw => {
      sw.addEventListener('click', () => {
        panel.querySelectorAll('.accent-swatch').forEach(s => s.classList.remove('active'));
        sw.classList.add('active');
        previewBox.style.setProperty('--preview-accent', sw.dataset.color);
      });
    });
    const customColorInput = panel.querySelector('.accent-custom-input');
    customColorInput.addEventListener('input', () => {
      panel.querySelectorAll('.accent-swatch').forEach(s => s.classList.remove('active'));
      previewBox.style.setProperty('--preview-accent', customColorInput.value);
    });

    // Logo upload → preview (form field + live preview thumbnail)
    const logoInput = panel.querySelector('.logo-input');
    const logoPreview = document.getElementById(logoInput.dataset.target);
    const previewLogo = document.getElementById('previewLogo-' + key);
    logoInput.addEventListener('change', () => {
      const file = logoInput.files && logoInput.files[0];
      if (!file) return;
      if (!file.type.match(/^image\/(png|jpeg)$/)) { alert('Please upload a PNG or JPG image.'); return; }
      if (file.size > 2 * 1024 * 1024) { alert('Logo is too large. Max size is 2MB.'); return; }
      const reader = new FileReader();
      reader.onload = (e) => {
        logoPreview.innerHTML = `<img src="${e.target.result}" alt="Clinic logo"/>`;
        previewLogo.innerHTML = `<img src="${e.target.result}" alt="Clinic logo"/>`;
      };
      reader.readAsDataURL(file);
    });

    syncPreview();
  });

  // ── Save / Reset (frontend placeholder — no backend wired yet) ──
  document.querySelectorAll('[data-action="save"]').forEach(btn => {
    btn.addEventListener('click', () => {
      // TODO: wire to backend — POST branding fields + logo + doc numbering + footer
      // for this doctype (btn.dataset.doctype) to clinicadmin/templates_save.php
      const banner = document.getElementById('successBanner');
      banner.style.display = 'block';
      banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
  });
  document.querySelectorAll('[data-action="reset"]').forEach(btn => {
    btn.addEventListener('click', () => {
      if (!confirm('Reset this template to the TELE-CARE default? Your clinic branding will be cleared.')) return;
      // TODO: wire to backend — DELETE/clear this clinic's override row for this doctype
      const panel = document.getElementById('panel-' + btn.dataset.doctype);
      panel.querySelectorAll('input[type="text"], input[type="email"], input[type="number"], textarea').forEach(el => {
        el.value = el.dataset.default || '';
        el.dispatchEvent(new Event('input', { bubbles: true }));
      });
      panel.querySelectorAll('.logo-preview, [id^="previewLogo-"]').forEach(el => {
        if (el.classList.contains('logo-preview')) {
          el.innerHTML = '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3 8.25V15a2.25 2.25 0 002.25 2.25h13.5A2.25 2.25 0 0021 15V8.25m-18 0A2.25 2.25 0 015.25 6h13.5A2.25 2.25 0 0121 8.25m-18 0v.008l18-.008"/></svg>';
        } else {
          el.textContent = 'LOGO';
        }
      });
    });
  });
</script>
</div>
</body>
</html>