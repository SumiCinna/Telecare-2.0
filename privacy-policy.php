<?php
date_default_timezone_set('Asia/Manila');
require_once 'includes/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Privacy Policy · TELE-CARE</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --teal: #0d9488; --teal2: #0f766e; --green: #16a34a;
      --muted: #6b7280; --border: #e5e7eb; --bg: #f9fafb;
      --white: #ffffff; --text: #111827; --radius: 12px; --red: #ef4444;
    }
    body { font-family:'DM Sans',sans-serif; background:var(--bg); color:var(--text); line-height:1.6; }

    .policy-container { max-width:900px; margin:0 auto; padding:2rem 1rem; }
    .policy-header { text-align:center; margin-bottom:3rem; padding-bottom:2rem; border-bottom:2px solid var(--border); }
    .policy-header h1 { font-family:'Plus Jakarta Sans',sans-serif; font-size:2.2rem; font-weight:800; color:var(--text); margin-bottom:0.5rem; }
    .policy-header .last-updated { font-size:0.85rem; color:var(--muted); }

    .policy-section { margin-bottom:2.5rem; }
    .policy-section h2 { font-family:'Plus Jakarta Sans',sans-serif; font-size:1.2rem; font-weight:800; color:var(--text); margin-bottom:0.8rem; padding-bottom:0.5rem; border-bottom:1.5px solid var(--teal); display:inline-block; }
    .policy-section p { font-size:0.95rem; color:var(--text); margin-bottom:1rem; line-height:1.7; }
    .policy-section ul { margin-left:1.5rem; margin-bottom:1rem; }
    .policy-section li { margin-bottom:0.6rem; color:var(--text); font-size:0.95rem; line-height:1.7; }
    .policy-section strong { color:var(--text); font-weight:700; }
    .policy-section em { color:var(--muted); }

    .highlight-box { background:rgba(13,148,136,0.08); border-left:4px solid var(--teal); padding:1.2rem; border-radius:8px; margin:1.5rem 0; }
    .highlight-box p { margin-bottom:0; font-size:0.93rem; }

    .data-table { width:100%; border-collapse:collapse; margin:1.5rem 0; font-size:0.93rem; }
    .data-table th { background:rgba(13,148,136,0.12); border:1px solid var(--border); padding:0.8rem; text-align:left; font-weight:700; color:var(--text); }
    .data-table td { border:1px solid var(--border); padding:0.8rem; }
    .data-table tr:nth-child(even) { background:rgba(249,250,251,1); }

    .policy-footer { background:var(--white); border:1.5px solid var(--border); border-radius:var(--radius); padding:1.5rem; margin-top:3rem; }
    .policy-footer h3 { font-family:'Plus Jakarta Sans',sans-serif; font-weight:700; margin-bottom:0.8rem; }
    .policy-footer p { font-size:0.92rem; color:var(--muted); margin-bottom:0.5rem; }

    .close-btn { display:none; position:fixed; top:1rem; right:1rem; z-index:1000; }
    @media(max-width:600px) {
      .policy-header h1 { font-size:1.6rem; }
      .policy-section h2 { font-size:1rem; }
      .data-table { font-size:0.85rem; }
      .data-table th, .data-table td { padding:0.6rem; }
    }
  </style>
</head>
<body>

<div class="policy-container">
  <div class="policy-header">
    <h1>Privacy Policy</h1>
    <div class="last-updated">TELE-CARE Telehealth Platform</div>
    <div class="last-updated" style="margin-top:0.5rem;font-size:0.8rem;">Last Updated: March 2026</div>
  </div>

  <!-- 1. Overview -->
  <div class="policy-section">
    <h2>1. Overview</h2>
    <p>TELE-CARE is a telehealth platform that connects patients with licensed doctors for remote consultations. This Privacy Policy explains how we collect, use, and protect your personal data and information shared during consultations.</p>
    <p>By using TELE-CARE, you acknowledge that you have read and agree to this Privacy Policy.</p>
  </div>

  <!-- 2. Information We Collect -->
  <div class="policy-section">
    <h2>2. Information We Collect</h2>
    <p>We collect the following categories of information:</p>
    <ul>
      <li><strong>Account Information:</strong> Full name, email address, phone number, date of birth, and password.</li>
      <li><strong>Medical Information:</strong> Health records, consultation notes, diagnoses, and prescriptions shared during appointments.</li>
      <li><strong>Payment Information:</strong> Billing name, email, and phone number. Payment card details are NOT stored—they are transmitted directly to PayMongo for secure processing.</li>
      <li><strong>Communication Records:</strong> Chat messages, voice recordings, and video session metadata during consultations.</li>
      <li><strong>Usage Data:</strong> IP address, browser type, appointment history, and access logs.</li>
    </ul>
  </div>

  <!-- 3. Voice and Video Recording -->
  <div class="policy-section">
    <h2>3. Voice Recording and Consultation Recording</h2>
    <p>TELE-CARE may record voice and video during consultations for the following purposes:</p>
    <ul>
      <li><strong>Medical Record Keeping:</strong> To maintain an accurate record of your consultation for continuity of care.</li>
      <li><strong>Quality Assurance:</strong> To review consultation quality and ensure compliance with medical standards.</li>
      <li><strong>Patient Safety:</strong> To verify treatment recommendations and protect both patient and provider.</li>
    </ul>
    <div class="highlight-box">
      <p><strong>Permission:</strong> By proceeding with a consultation on TELE-CARE, you explicitly consent to the recording of voice and video during the appointment. You will be notified at the start of each consultation that recording is in progress.</p>
    </div>
    <p>Recordings are encrypted and stored securely. You may request access to your consultation recording by contacting our support team.</p>
  </div>

  <!-- 4. Payment Security -->
  <div class="policy-section">
    <h2>4. Payment Information and Security</h2>
    <p>TELE-CARE uses <strong>PayMongo</strong> for all payment processing. We do not store, process, or have access to your credit/debit card details.</p>
    
    <table class="data-table">
      <tr>
        <th>Payment Detail</th>
        <th>Handling</th>
      </tr>
      <tr>
        <td>Card Number, Expiry, CVV</td>
        <td>Transmitted directly to PayMongo via encrypted connection. Not stored on TELE-CARE servers.</td>
      </tr>
      <tr>
        <td>Billing Name, Email, Phone</td>
        <td>Collected by TELE-CARE and securely transmitted to PayMongo for billing purposes only.</td>
      </tr>
      <tr>
        <td>Payment Status & Receipts</td>
        <td>Stored on TELE-CARE servers for records and appointment confirmation.</td>
      </tr>
    </table>

    <div class="highlight-box">
      <p><strong>PCI DSS Compliance:</strong> PayMongo is PCI DSS compliant and handles all sensitive payment data. Your payment information is never exposed to TELE-CARE doctors or staff members.</p>
    </div>
  </div>

  <!-- 5. How We Use Your Information -->
  <div class="policy-section">
    <h2>5. How We Use Your Information</h2>
    <ul>
      <li>To provide telehealth consultation services and schedule appointments.</li>
      <li>To process payments through PayMongo securely.</li>
      <li>To maintain medical records and consultation history.</li>
      <li>To send appointment reminders and follow-up communications.</li>
      <li>To improve platform performance, security, and user experience.</li>
      <li>To comply with legal and regulatory obligations.</li>
      <li>To investigate and prevent fraud or unauthorized access.</li>
    </ul>
  </div>

  <!-- 6. Who Can Access Your Information -->
  <div class="policy-section">
    <h2>6. Who Can Access Your Information</h2>
    <ul>
      <li><strong>Your Assigned Doctor:</strong> Full access to your medical records, consultation notes, and health information for treatment purposes only.</li>
      <li><strong>TELE-CARE Support Team:</strong> Limited access to billing and account information to resolve technical issues.</li>
      <li><strong>Administrative Staff:</strong> Access only to non-medical scheduling and appointment data.</li>
      <li><strong>PayMongo:</strong> Access to billing details for payment processing only. PayMongo does not receive medical information.</li>
      <li><strong>Legal/Compliance:</strong> Information may be disclosed to comply with legal requirements, court orders, or government requests.</li>
    </ul>
    <p style="margin-top:1rem;"><em>Your consultation recordings and medical records are NOT shared with third parties without your explicit consent, except as required by law.</em></p>
  </div>

  <!-- 7. Data Security -->
  <div class="policy-section">
    <h2>7. Data Security</h2>
    <p>TELE-CARE implements industry-standard security measures to protect your information:</p>
    <ul>
      <li><strong>Encryption:</strong> All data transmitted between you and TELE-CARE is encrypted using TLS/SSL.</li>
      <li><strong>Secure Storage:</strong> Medical records and consultation data are stored on secure servers with access controls.</li>
      <li><strong>Payment Security:</strong> Payment processing is delegated entirely to PayMongo, a certified payment processor.</li>
      <li><strong>Session Security:</strong> Consultation sessions require authentication and are protected from unauthorized access.</li>
    </ul>
  </div>

  <!-- 8. Your Rights -->
  <div class="policy-section">
    <h2>8. Your Rights</h2>
    <ul>
      <li><strong>Access:</strong> You have the right to request access to your personal data and medical records.</li>
      <li><strong>Correction:</strong> You can request correction of inaccurate or incomplete information in your account.</li>
      <li><strong>Consultation Recording Access:</strong> You may request a copy of your consultation recording by submitting a request to our support team.</li>
      <li><strong>Account Closure:</strong> You can request closure of your TELE-CARE account at any time.</li>
    </ul>
  </div>

  <!-- 9. Third-Party Services -->
  <div class="policy-section">
    <h2>9. Third-Party Services</h2>
    <p>TELE-CARE integrates with the following third-party services:</p>
    <ul>
      <li><strong>PayMongo:</strong> Payment processing and secure card tokenization. See PayMongo's <a href="https://paymongo.com/privacy" target="_blank" style="color:var(--teal);text-decoration:underline;">Privacy Policy</a>.</li>
      <li><strong>WebRTC/Communication Infrastructure:</strong> Enables secure voice and video consultations.</li>
    </ul>
    <p>We are not responsible for the privacy practices of third-party services. Please review their privacy policies independently.</p>
  </div>

  <!-- 10. Children's Privacy -->
  <div class="policy-section">
    <h2>10. Children's Privacy</h2>
    <p>TELE-CARE is not intended for users under 18 years of age. If a minor requires consultation, a parent or legal guardian must create and manage the account. We do not knowingly collect information from children under 18 without parental consent.</p>
  </div>

  <!-- 11. Policy Changes -->
  <div class="policy-section">
    <h2>11. Changes to This Privacy Policy</h2>
    <p>TELE-CARE may update this Privacy Policy periodically to reflect changes in our practices or legal requirements. We will notify you of significant changes by email or by displaying a notice on the platform. Your continued use of TELE-CARE after changes constitutes acceptance of the updated policy.</p>
  </div>

  <!-- 12. Contact Information -->
  <div class="policy-section">
    <div class="policy-footer">
      <h3>12. Contact Us</h3>
      <p>If you have questions, concerns, or requests regarding this Privacy Policy or your personal data, please contact us:</p>
      <p style="margin-top:1rem;"><strong>TELE-CARE Support</strong><br/>
      Email: <a href="mailto:support@telecare.local" style="color:var(--teal);text-decoration:none;">telecareteamsystem@gmail.com</a><br/>
      Platform: TELE-CARE Telehealth System</p>
    </div>
  </div>
</div>

<script>
  // If opened as modal popup, close button
  const closeBtn = document.querySelector('.close-btn');
  if (window.opener) {
    closeBtn.style.display = 'block';
  }
</script>

</body>
</html>
