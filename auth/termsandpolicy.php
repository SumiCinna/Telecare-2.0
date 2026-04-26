<?php
/**
 * termsandpolicy.php
 * Include this file in any page that needs the Terms & Privacy modal.
 * Usage: <?php include 'termsandpolicy.php'; ?>
 * Then call: openTermsModal(onAcceptCallback) from JS
 */
?>
<!-- ===================== TERMS & PRIVACY MODAL ===================== -->
<style>
  /* Overlay */
  #tpOverlay{
    display:none;position:fixed;inset:0;z-index:99999;
    background:rgba(20,40,38,.72);backdrop-filter:blur(6px);
    animation:tpFadeIn .3s ease;
  }
  #tpOverlay.visible{display:flex;align-items:center;justify-content:center;padding:1rem}
  @keyframes tpFadeIn{from{opacity:0}to{opacity:1}}

  /* Modal box */
  #tpModal{
    background:#fff;border-radius:20px;width:100%;max-width:640px;max-height:90vh;
    display:flex;flex-direction:column;box-shadow:0 24px 80px rgba(0,0,0,.22);
    animation:tpSlideUp .35s cubic-bezier(.22,.68,0,1.2);overflow:hidden;
  }
  @keyframes tpSlideUp{from{opacity:0;transform:translateY(28px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}

  /* Header */
  #tpHeader{
    background:linear-gradient(135deg,#244441 0%,#1a3330 100%);
    padding:1.4rem 1.6rem 1.2rem;flex-shrink:0;position:relative;
  }
  #tpHeader h2{font-family:'Playfair Display',serif;font-size:1.3rem;color:#fff;margin:0 0 .2rem}
  #tpHeader p{font-size:.78rem;color:rgba(255,255,255,.55);margin:0}
  #tpCloseBtn{
    position:absolute;top:1rem;right:1rem;width:32px;height:32px;border-radius:50%;
    background:rgba(255,255,255,.12);border:none;cursor:pointer;color:#fff;
    display:flex;align-items:center;justify-content:center;transition:background .2s;
  }
  #tpCloseBtn:hover{background:rgba(255,255,255,.22)}

  /* Tab bar */
  #tpTabs{display:flex;border-bottom:2px solid rgba(36,68,65,.1);flex-shrink:0;background:#fafafa}
  .tp-tab{
    flex:1;padding:.85rem 1rem;border:none;background:transparent;cursor:pointer;
    font-family:'DM Sans',sans-serif;font-size:.82rem;font-weight:600;
    color:#9ab0ae;letter-spacing:.04em;text-transform:uppercase;
    border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .25s;
  }
  .tp-tab.active{color:#244441;border-bottom-color:#C33643}
  .tp-tab:hover:not(.active){color:#244441;background:rgba(36,68,65,.04)}

  /* Scroll notice */
  #tpScrollNotice{
    background:rgba(195,54,67,.07);border-bottom:1px solid rgba(195,54,67,.15);
    padding:.6rem 1.4rem;font-size:.76rem;color:#C33643;font-weight:600;
    display:flex;align-items:center;gap:.5rem;flex-shrink:0;transition:opacity .4s;
  }
  #tpScrollNotice svg{flex-shrink:0}

  /* Content panels */
  #tpBody{flex:1;overflow-y:auto;padding:1.4rem 1.6rem;scroll-behavior:smooth}
  #tpBody::-webkit-scrollbar{width:5px}
  #tpBody::-webkit-scrollbar-track{background:transparent}
  #tpBody::-webkit-scrollbar-thumb{background:rgba(36,68,65,.2);border-radius:4px}

  .tp-panel{display:none}.tp-panel.active{display:block}

  /* Typography inside modal */
  .tp-section-title{
    font-family:'Playfair Display',serif;font-size:1rem;color:#244441;
    margin:1.2rem 0 .4rem;padding-bottom:.3rem;
    border-bottom:1px solid rgba(36,68,65,.08);
  }
  .tp-section-title:first-child{margin-top:0}
  .tp-body-text{font-size:.84rem;color:#4a6a67;line-height:1.75;margin-bottom:.6rem}
  .tp-list{font-size:.84rem;color:#4a6a67;line-height:1.75;padding-left:1.2rem;margin-bottom:.6rem}
  .tp-list li{margin-bottom:.25rem}

  /* Footer */
  #tpFooter{
    padding:1.1rem 1.6rem;border-top:1.5px solid rgba(36,68,65,.08);
    display:flex;flex-direction:column;gap:.75rem;flex-shrink:0;background:#fafafa;
  }
  #tpCheckRow{display:flex;align-items:flex-start;gap:.7rem;cursor:pointer}
  #tpCheckbox{
    width:18px;height:18px;border:1.5px solid rgba(36,68,65,.3);border-radius:5px;
    flex-shrink:0;margin-top:1px;cursor:pointer;accent-color:#244441;
  }
  #tpCheckLabel{font-size:.82rem;color:#4a6a67;line-height:1.5;cursor:pointer;user-select:none}
  #tpCheckLabel a{color:#C33643;font-weight:600;text-decoration:none}

  #tpAcceptBtn{
    width:100%;padding:.85rem;border-radius:50px;background:#C33643;color:#fff;
    font-weight:700;font-size:.9rem;border:none;cursor:pointer;
    transition:all .3s;box-shadow:0 6px 18px rgba(195,54,67,.3);
    font-family:'DM Sans',sans-serif;
  }
  #tpAcceptBtn:hover:not(:disabled){background:#a82d38;transform:translateY(-1px)}
  #tpAcceptBtn:disabled{background:#ccc;box-shadow:none;cursor:not-allowed;transform:none}

  #tpScrollHint{
    text-align:center;font-size:.74rem;color:#9ab0ae;
  }

  /* Scroll-to-bottom indicator */
  #tpScrollProgress{
    height:3px;background:rgba(36,68,65,.08);flex-shrink:0;
  }
  #tpScrollBar{height:100%;width:0%;background:linear-gradient(90deg,#244441,#C33643);transition:width .1s linear;border-radius:0 2px 2px 0}

  @media(max-width:480px){
    #tpModal{max-height:96vh;border-radius:16px 16px 0 0;margin-top:auto}
    #tpOverlay{align-items:flex-end;padding:0}
    #tpHeader{padding:1.1rem 1.2rem 1rem}
    #tpBody{padding:1rem 1.2rem}
    #tpFooter{padding:.9rem 1.2rem}
  }
</style>

<div id="tpOverlay">
  <div id="tpModal" role="dialog" aria-modal="true" aria-labelledby="tpModalTitle">

    <div id="tpHeader">
      <h2 id="tpModalTitle">Terms & Privacy Policy</h2>
      <p>Please read and accept before continuing</p>
      <button id="tpCloseBtn" onclick="closeTermsModal()" aria-label="Close">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M1 1l12 12M13 1L1 13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      </button>
    </div>

    <div id="tpScrollProgress"><div id="tpScrollBar"></div></div>

    <div id="tpTabs">
      <button class="tp-tab active" onclick="tpSwitchTab('terms')">📋 Terms of Use</button>
      <button class="tp-tab" onclick="tpSwitchTab('privacy')">🔒 Privacy Policy</button>
    </div>

    <div id="tpScrollNotice">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
      Please scroll to the bottom to read the full document
    </div>

    <div id="tpBody">

      <!-- TERMS PANEL -->
      <div class="tp-panel active" id="tp-terms">
        <h3 class="tp-section-title">1. Introduction</h3>
        <p class="tp-body-text">Welcome to the TELE-CARE AI System. By registering and using this platform, you agree to comply with and be bound by the following Terms and Conditions.</p>

        <h3 class="tp-section-title">2. Purpose of the System</h3>
        <p class="tp-body-text">The TELE-CARE AI System is designed to provide healthcare-related digital services, including appointment scheduling, teleconsultation support, AI-powered assistance, and system monitoring. The system is intended to support healthcare services but does not replace professional medical advice, diagnosis, or treatment.</p>

        <h3 class="tp-section-title">3. Collection and Processing of Personal Data</h3>
        <p class="tp-body-text">By registering, you consent to the collection, processing, storage, and use of your personal data for legitimate healthcare and system-related purposes. All data processing activities are conducted in accordance with the Data Privacy Act of 2012 (RA 10173) and other applicable laws and regulations.</p>

        <h3 class="tp-section-title">4. Data Protection and Confidentiality</h3>
        <p class="tp-body-text">Your personal information will be treated with strict confidentiality and protected using appropriate technical and organizational security measures. Access to data is limited to authorized healthcare personnel and system administrators only.</p>

        <h3 class="tp-section-title">5. User Responsibilities</h3>
        <p class="tp-body-text">Users agree to:</p>
        <ul class="tp-list">
          <li>Provide accurate, complete, and updated information.</li>
          <li>Maintain the confidentiality of their login credentials.</li>
          <li>Use the system only for lawful and legitimate healthcare purposes.</li>
          <li>Refrain from unauthorized access, misuse, or disruption of the system.</li>
        </ul>

        <h3 class="tp-section-title">6. User Rights</h3>
        <p class="tp-body-text">In accordance with applicable data privacy laws, users have the right to:</p>
        <ul class="tp-list">
          <li>Access their personal data.</li>
          <li>Request correction or updating of inaccurate information.</li>
          <li>Request deletion or restriction of processing, subject to legal limitations.</li>
          <li>Withdraw consent when applicable.</li>
        </ul>
        <p class="tp-body-text">Requests may be submitted through official TELE-CARE AI support channels.</p>

        <h3 class="tp-section-title">7. Data Retention</h3>
        <p class="tp-body-text">Personal data shall be retained only for as long as necessary to fulfill healthcare, operational, and legal obligations, unless a longer retention period is required by law.</p>

        <h3 class="tp-section-title">8. AI Disclaimer</h3>
        <p class="tp-body-text">The AI-powered chatbot and automated features are designed to provide general healthcare assistance and system guidance. They do not replace licensed medical professionals. Users are strongly advised to consult qualified healthcare providers for medical diagnosis and treatment.</p>

        <h3 class="tp-section-title">9. Limitation of Liability</h3>
        <p class="tp-body-text">The TELE-CARE AI System administrators shall not be liable for:</p>
        <ul class="tp-list">
          <li>Damages resulting from inaccurate information provided by users.</li>
          <li>Misuse of the platform.</li>
          <li>Service interruptions due to technical issues beyond reasonable control.</li>
        </ul>

        <h3 class="tp-section-title">10. Amendments</h3>
        <p class="tp-body-text">The TELE-CARE AI System reserves the right to modify these Terms and Conditions at any time. Users will be notified of significant changes.</p>

        <h3 class="tp-section-title">11. Payment Gateway and Transactions</h3>
        <p class="tp-body-text">The TELE-CARE AI System may integrate third-party payment gateways to facilitate secure online payments for consultations and other healthcare services. By making a payment through the system, you agree to comply with the terms and conditions of the selected payment provider.</p>
        <p class="tp-body-text">The TELE-CARE AI System does not store full credit/debit card details. Payment information is processed securely through accredited third-party payment processors using industry-standard encryption and security protocols.</p>
        <p class="tp-body-text">The system administrators shall not be held responsible for:</p>
        <ul class="tp-list">
          <li>Payment failures due to banking issues.</li>
          <li>Delays caused by third-party payment providers.</li>
          <li>Unauthorized transactions resulting from user negligence.</li>
        </ul>
        <p class="tp-body-text">Users are responsible for ensuring that payment details provided are accurate and authorized.</p>

        <h3 class="tp-section-title">12. Acceptance of Terms</h3>
        <p class="tp-body-text">By proceeding with registration, you confirm that you have read, understood, and agreed to these Terms and Conditions.</p>
      </div>

      <!-- PRIVACY PANEL -->
      <div class="tp-panel" id="tp-privacy">
        <h3 class="tp-section-title">1. Introduction</h3>
        <p class="tp-body-text">The TELE-CARE AI System is committed to protecting your privacy and ensuring the security of your personal data. This Privacy Policy explains how we collect, use, store, and protect your information in compliance with the Data Privacy Act of 2012 (RA 10173) and other applicable laws and regulations.</p>

        <h3 class="tp-section-title">2. Information We Collect</h3>
        <p class="tp-body-text"><strong>a. Personal Information</strong></p>
        <ul class="tp-list">
          <li>Full name, Date of birth</li>
          <li>Contact details (email address, phone number)</li>
          <li>Address, Account login credentials</li>
        </ul>
        <p class="tp-body-text"><strong>b. Health Information</strong></p>
        <ul class="tp-list">
          <li>Medical history, Consultation records</li>
          <li>Appointment details</li>
          <li>Health-related concerns submitted through the system</li>
        </ul>
        <p class="tp-body-text"><strong>c. System Data</strong></p>
        <ul class="tp-list">
          <li>Login activity</li>
          <li>Usage logs for security and system improvement purposes</li>
        </ul>

        <h3 class="tp-section-title">3. Purpose of Data Collection</h3>
        <p class="tp-body-text">Your information is collected and processed for the following purposes:</p>
        <ul class="tp-list">
          <li>Appointment scheduling and management</li>
          <li>Teleconsultation support</li>
          <li>AI-powered healthcare assistance</li>
          <li>System monitoring and security</li>
          <li>Compliance with legal and regulatory requirements</li>
        </ul>

        <h3 class="tp-section-title">4. Legal Basis for Processing</h3>
        <p class="tp-body-text">Personal data is processed based on your consent, fulfillment of healthcare services, compliance with legal obligations, and legitimate interests in maintaining system security and functionality.</p>

        <h3 class="tp-section-title">5. Data Protection and Security</h3>
        <p class="tp-body-text">We implement appropriate technical and organizational security measures to protect your personal data against unauthorized access, disclosure, alteration, or destruction. Access to sensitive information is restricted to authorized healthcare personnel and system administrators only.</p>

        <h3 class="tp-section-title">6. Data Sharing</h3>
        <p class="tp-body-text">Your personal data will not be sold or shared with third parties except:</p>
        <ul class="tp-list">
          <li>When required by law</li>
          <li>When necessary for healthcare service delivery</li>
          <li>With authorized personnel under strict confidentiality obligations</li>
        </ul>

        <h3 class="tp-section-title">7. Data Retention</h3>
        <p class="tp-body-text">Personal data will be retained only for as long as necessary to fulfill healthcare services, operational needs, and legal obligations. After this period, data will be securely deleted or anonymized.</p>

        <h3 class="tp-section-title">8. Your Rights Under the Data Privacy Act</h3>
        <p class="tp-body-text">You have the right to:</p>
        <ul class="tp-list">
          <li>Access your personal data</li>
          <li>Request correction of inaccurate information</li>
          <li>Request deactivation of processing</li>
          <li>Withdraw consent (subject to legal limitations)</li>
          <li>File a complaint with the appropriate regulatory authority</li>
        </ul>

        <h3 class="tp-section-title">9. Updates to This Privacy Policy</h3>
        <p class="tp-body-text">We reserve the right to update this Privacy Policy as needed. Users will be informed of significant changes through the system.</p>

        <h3 class="tp-section-title">10. Payment Information and Processing</h3>
        <p class="tp-body-text">When you make payments through the TELE-CARE AI System, your payment details are processed through secure third-party payment gateways. We do not store complete credit card or debit card numbers on our servers.</p>
        <p class="tp-body-text">The information collected during payment processing may include:</p>
        <ul class="tp-list">
          <li>Name of account holder, Billing address</li>
          <li>Payment amount, Transaction reference number</li>
        </ul>
        <p class="tp-body-text">This information is used solely for transaction verification, record-keeping, and compliance with legal and financial regulations.</p>

        <h3 class="tp-section-title">11. Contact Information</h3>
        <p class="tp-body-text">For questions, concerns, or requests regarding your personal data, please contact the TELE-CARE AI System Administrator through official communication channels.</p>
      </div>

    </div><!-- /#tpBody -->

    <div id="tpFooter">
      <p id="tpScrollHint" style="color:#C33643;font-size:.76rem;font-weight:600;text-align:center">
        ↓ Scroll to the bottom to enable the checkbox
      </p>
      <label id="tpCheckRow" style="pointer-events:none;opacity:.45">
        <input type="checkbox" id="tpCheckbox" disabled/>
        <span id="tpCheckLabel">
          I have read and agree to the <a onclick="tpSwitchTab('terms');return false;" href="#">Terms and Conditions</a>
          and <a onclick="tpSwitchTab('privacy');return false;" href="#">Privacy Policy</a> of TELE-CARE.
        </span>
      </label>
      <button id="tpAcceptBtn" disabled onclick="tpAccept()">Accept & Continue</button>
    </div>

  </div>
</div>

<script>
(function(){
  let _tpCallback     = null;
  let _tpScrolledTerms   = false;
  let _tpScrolledPrivacy = false;
  let _tpCurrentTab   = 'terms';

  /* Public: call openTermsModal(fn) to show the modal.
     fn() is called when user clicks Accept & Continue.      */
  window.openTermsModal = function(callback) {
    _tpCallback = callback || null;
    _tpScrolledTerms   = false;
    _tpScrolledPrivacy = false;
    _tpCurrentTab = 'terms';

    // Reset state
    tpSwitchTab('terms');
    document.getElementById('tpCheckbox').checked  = false;
    document.getElementById('tpCheckbox').disabled  = true;
    document.getElementById('tpCheckRow').style.pointerEvents = 'none';
    document.getElementById('tpCheckRow').style.opacity = '.45';
    document.getElementById('tpAcceptBtn').disabled = true;
    document.getElementById('tpScrollNotice').style.opacity = '1';
    document.getElementById('tpScrollHint').style.display   = 'block';
    document.getElementById('tpScrollHint').textContent = '↓ Scroll to the bottom to enable the checkbox';
    document.getElementById('tpScrollBar').style.width = '0%';
    document.getElementById('tpBody').scrollTop = 0;

    const overlay = document.getElementById('tpOverlay');
    overlay.classList.add('visible');
    document.body.style.overflow = 'hidden';
  };

  window.closeTermsModal = function() {
    document.getElementById('tpOverlay').classList.remove('visible');
    document.body.style.overflow = '';
  };

  window.tpSwitchTab = function(tab) {
    _tpCurrentTab = tab;
    document.querySelectorAll('.tp-tab').forEach((el, i) => {
      el.classList.toggle('active', (i === 0 && tab === 'terms') || (i === 1 && tab === 'privacy'));
    });
    document.getElementById('tp-terms').classList.toggle('active', tab === 'terms');
    document.getElementById('tp-privacy').classList.toggle('active', tab === 'privacy');
    document.getElementById('tpBody').scrollTop = 0;
    document.getElementById('tpScrollBar').style.width = '0%';

    // Re-check if this tab was already scrolled
    tpUpdateScrollState();
  };

  function tpUpdateScrollState() {
    const scrolled = _tpCurrentTab === 'terms' ? _tpScrolledTerms : _tpScrolledPrivacy;
    const notice   = document.getElementById('tpScrollNotice');
    const hint     = document.getElementById('tpScrollHint');
    const checkbox = document.getElementById('tpCheckbox');
    const checkRow = document.getElementById('tpCheckRow');
    const btn      = document.getElementById('tpAcceptBtn');

    if (scrolled) {
      notice.style.opacity = '0';
    } else {
      notice.style.opacity = '1';
    }

    // Enable checkbox only if BOTH tabs have been scrolled
    const bothScrolled = _tpScrolledTerms && _tpScrolledPrivacy;
    if (bothScrolled) {
      checkbox.disabled = false;
      checkRow.style.pointerEvents = 'auto';
      checkRow.style.opacity = '1';
      hint.textContent = 'Check the box below to accept';
      hint.style.color = '#244441';
    } else {
      hint.textContent = _tpScrolledTerms
        ? '↓ Switch to Privacy Policy tab and scroll to the bottom'
        : '↓ Scroll to the bottom to enable the checkbox';
      hint.style.color = '#C33643';
    }

    btn.disabled = !(bothScrolled && checkbox.checked);
  }

  window.tpAccept = function() {
    if (!document.getElementById('tpCheckbox').checked) return;
    closeTermsModal();
    if (typeof _tpCallback === 'function') _tpCallback();
  };

  // Scroll tracking
  document.getElementById('tpBody').addEventListener('scroll', function() {
    const el       = this;
    const scrolled = el.scrollTop + el.clientHeight >= el.scrollHeight - 10;
    const progress = Math.min(100, Math.round((el.scrollTop / (el.scrollHeight - el.clientHeight)) * 100));
    document.getElementById('tpScrollBar').style.width = progress + '%';

    if (scrolled) {
      if (_tpCurrentTab === 'terms')   _tpScrolledTerms   = true;
      if (_tpCurrentTab === 'privacy') _tpScrolledPrivacy = true;
    }
    tpUpdateScrollState();
  });

  // Checkbox toggle
  document.getElementById('tpCheckbox').addEventListener('change', function() {
    document.getElementById('tpAcceptBtn').disabled = !this.checked;
  });

  // Close on overlay click (outside modal)
  document.getElementById('tpOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeTermsModal();
  });

  // ESC key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeTermsModal();
  });
})();
</script>
<!-- ============================================================= -->