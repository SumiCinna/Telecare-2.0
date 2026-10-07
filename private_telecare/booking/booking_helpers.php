<?php
// private_telecare/booking/booking_helpers.php
// Shared constants + guards for the multi-step booking wizard.
// Included by every step*.php / payment.php / success.php / confirmed.php.

const BOOKING_DEPARTMENTS = [
    'General Medicine / General Practice' => [
        'desc' => 'Common illnesses, symptoms, initial assessment, prescriptions, referrals',
        'icon' => 'stethoscope',
    ],
    'Cardiology' => [
        'desc' => 'Hypertension, heart disease follow-ups, ECG/result discussion',
        'icon' => 'heart',
    ],
    'Pulmonology' => [
        'desc' => 'Asthma, COPD, respiratory symptoms, follow-ups',
        'icon' => 'lungs',
    ],
    'Neurology' => [
        'desc' => 'Headaches, migraines, seizures, neuropathy, follow-ups',
        'icon' => 'brain',
    ],
];

// Reasons for consultation, grouped by department. Step 1 only shows/accepts
// the group matching whichever department the patient has selected.
const BOOKING_REASONS_BY_DEPT = [
    'General Medicine / General Practice' => [
        'Fever', 'Cough', 'Colds / Runny nose', 'Sore throat', 'Headache', 'Dizziness',
        'Body pain', 'Fatigue / Weakness', 'Nausea', 'Vomiting', 'Diarrhea',
        'Abdominal pain', 'Loss of appetite', 'Mild difficulty breathing', 'General health concern',
    ],
    'Cardiology' => [
        'Chest pain / Chest discomfort', 'Shortness of breath', 'Palpitations / Fast heartbeat',
        'Irregular heartbeat', 'Dizziness', 'Fainting / Near-fainting', 'Fatigue',
        'Swelling of the legs or feet', 'High blood pressure', 'Low blood pressure',
        'Exercise intolerance', 'Unexplained sweating', 'Follow-up for heart condition',
        'ECG result discussion', 'Blood pressure monitoring',
    ],
    'Pulmonology' => [
        'Cough', 'Persistent cough', 'Shortness of breath', 'Difficulty breathing', 'Wheezing',
        'Chest tightness', 'Chest pain when breathing', 'Excessive phlegm / Mucus',
        'Coughing with phlegm', 'Coughing up blood', 'Frequent respiratory infections',
        'Snoring / Breathing problems during sleep', 'Reduced exercise tolerance',
        'Asthma symptoms', 'Follow-up for COPD or other lung condition',
    ],
    'Neurology' => [
        'Headache', 'Migraine', 'Dizziness / Vertigo', 'Fainting', 'Seizures', 'Tremors',
        'Numbness', 'Tingling sensation', 'Muscle weakness', 'Difficulty walking',
        'Balance problems', 'Memory problems', 'Confusion', 'Difficulty speaking',
        'Vision changes', 'Sleep problems', 'Chronic nerve pain',
        'Follow-up for neurological condition',
    ],
];

/**
 * Redirects back to Step 1 if any required piece of session state is
 * missing. Guards against a patient bookmarking / hitting a later step's
 * URL directly without going through the wizard.
 */
function booking_require(array $keys): void {
    foreach ($keys as $k) {
        if (empty($_SESSION['booking'][$k])) {
            header('Location: router.php?page=booking/step1_details');
            exit;
        }
    }
}

/** Inline stroke-SVG icon for a department card. */
function dept_icon(string $key): string {
    $icons = [
        'stethoscope' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clipboard-plus-icon lucide-clipboard-plus"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M9 14h6"/><path d="M12 17v-6"/></svg>',
        'smile'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><circle cx="12" cy="12" r="9"/><path d="M8 14s1.5 2 4 2 4-2 4-2M9 9h.01M15 9h.01"/></svg>',
        'heart'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M12 21s-7-4.35-9.5-8.5C.8 9 2 5 5.5 4.5 8 4.1 10 6 12 8c2-2 4-3.9 6.5-3.5C22 5 23.2 9 21.5 12.5 19 16.65 12 21 12 21z"/></svg>',
        'leaf'        => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M11 20A7 7 0 019 6c1.5 0 3 .5 4 1.5C15 4.5 18 4 21 3c0 4-1.5 8-5 10.5-1 3-4 6.5-5 6.5z"/></svg>',
        'hand'        => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M9 11V4a1.5 1.5 0 013 0v6M12 10.5V3a1.5 1.5 0 013 0v7M15 10.5V5a1.5 1.5 0 013 0v9c0 4-2.5 7-6.5 7C7 21 5 18 5 15v-4a1.5 1.5 0 013 0"/></svg>',
        'lungs'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M9 3v7.5c0 1-.5 1.8-1.3 2.4L5.5 14.7C4 15.8 3 17.6 3 19.5A2.5 2.5 0 005.5 22c1.9 0 3.5-1.2 4.1-3l1-3c.2-.7.4-1.4.4-2.1V3M15 3v7.5c0 1 .5 1.8 1.3 2.4l2.2 1.8c1.5 1.1 2.5 2.9 2.5 4.8a2.5 2.5 0 01-2.5 2.5c-1.9 0-3.5-1.2-4.1-3l-1-3c-.2-.7-.4-1.4-.4-2.1V3M9 3h6"/></svg>',
        'brain'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M9.5 3a3 3 0 00-3 3v.3A3.5 3.5 0 004 9.5 3.5 3.5 0 004.8 15 3.5 3.5 0 007 21a3 3 0 003-3V6a3 3 0 00-.5-3zM14.5 3a3 3 0 013 3v.3A3.5 3.5 0 0120 9.5 3.5 3.5 0 0119.2 15 3.5 3.5 0 0117 21a3 3 0 01-3-3V6a3 3 0 01.5-3z"/></svg>',
    ];
    return $icons[$key] ?? '';
}

/** Shared CSS for the wizard pages (kept in one place so all steps match). */
function booking_wizard_css(): string {
    return <<<CSS
    <style>
    :root{--red:#C33643;--green:#244441;--blue:#3F82E3;--muted:#9ab0ae}
    .wiz-page{max-width:920px;margin:0 auto;padding:1.8rem 2rem 5rem}
    .wiz-title{font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:900;color:var(--green)}
    .wiz-sub{color:var(--muted);font-size:0.88rem;margin-top:0.3rem;margin-bottom:1.6rem}
    .stepper{display:flex;align-items:flex-start;background:#fff;border:1px solid rgba(36,68,65,0.08);border-radius:16px;padding:1.2rem 1.5rem;margin-bottom:1.6rem}
    .step-col{display:flex;flex-direction:column;align-items:center}
    .step-dot{width:30px;height:30px;border-radius:50%;background:rgba(36,68,65,0.08);color:var(--muted);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.82rem;flex-shrink:0}
    .step-dot.active{background:var(--red);color:#fff}
    .step-dot.done{background:#16a34a;color:#fff}
    .step-line{flex:1;height:2px;background:rgba(36,68,65,0.12);margin:14px 0.4rem 0}
    .step-line.done{background:#16a34a}
    .step-label{font-size:0.7rem;color:var(--muted);text-align:center;margin-top:0.4rem;width:74px}
    .wiz-card{background:#fff;border:1px solid rgba(36,68,65,0.08);border-radius:18px;padding:1.5rem;margin-bottom:1.4rem}
    .wiz-card h3{font-family:'Playfair Display',serif;font-size:1.1rem;color:var(--green);margin-bottom:1rem}
    .wiz-btn{padding:0.85rem 1.8rem;border-radius:50px;border:none;font-weight:700;font-size:0.9rem;cursor:pointer;font-family:'DM Sans',sans-serif;text-decoration:none;display:inline-block}
    .wiz-btn.primary{background:var(--red);color:#fff;box-shadow:0 4px 14px rgba(195,54,67,0.28)}
    .wiz-btn.primary:hover{background:#a82d38}
    .wiz-btn.ghost{background:transparent;color:var(--green);border:1.5px solid rgba(36,68,65,0.15)}
    .wiz-btn:disabled{opacity:0.45;cursor:not-allowed}
    .wiz-actions{display:flex;justify-content:space-between;align-items:center;margin-top:1rem}
    .wiz-err{background:rgba(195,54,67,0.08);color:var(--red);border:1px solid rgba(195,54,67,0.2);padding:0.7rem 1rem;border-radius:12px;font-size:0.83rem;margin-bottom:1rem}
    @media(max-width:520px){.wiz-page{padding:1.2rem 1rem 5rem}.stepper{padding:1rem .6rem}.step-label{width:54px;font-size:.6rem}.step-line{margin:14px .15rem 0}}
    </style>
    CSS;
}

/** Renders the 5-step progress bar. $active = 1..5 */
function render_stepper(int $active): void {
    $labels = ['Details', 'Doctor', 'Schedule', 'Payment', 'Review'];
    echo '<div class="stepper">';
    foreach ($labels as $i => $label) {
        $n = $i + 1;
        $cls = $n < $active ? 'done' : ($n === $active ? 'active' : '');
        echo '<div class="step-col"><div class="step-dot ' . $cls . '">' . ($n < $active ? '&#10003;' : $n) . '</div><div class="step-label">' . $label . '</div></div>';
        if ($n < 5) echo '<div class="step-line ' . ($n < $active ? 'done' : '') . '"></div>';
    }
    echo '</div>';
}

// ─────────────────────────────────────────────────────────────────────────
// Payment method (Regular / PhilHealth YAKAP / HMO)
// ─────────────────────────────────────────────────────────────────────────

const BOOKING_HMO_PROVIDERS = [
    'Maxicare', 'Intellicare', 'Medicard', 'PhilCare', 'Cocolife',
    'Pacific Cross', 'Etiqa', 'Avega', 'Generali', 'Other',
];

function booking_payment_methods(): array {
    return [
        'Regular' => ['label' => 'Regular Payment',   'desc' => 'Pay using cash, e-wallet, or online payment.',          'icon' => 'card'],
        'YAKAP'   => ['label' => 'PhilHealth YAKAP',  'desc' => 'Use your PhilHealth YAKAP benefits for Primary Care services.', 'icon' => 'yakap'],
        'HMO'     => ['label' => 'HMO',               'desc' => 'Use your HMO coverage for this consultation.',          'icon' => 'shield'],
    ];
}

function booking_payment_label(string $method): string {
    return booking_payment_methods()[$method]['label'] ?? 'Regular Payment';
}

function booking_method_icon(string $key): string {
    $i = [
        'card'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="22" height="22"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>',
        'yakap'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="22" height="22"><path d="M12 21s-7-4.35-9.5-8.5C.8 9 2 5 5.5 4.5 8 4.1 10 6 12 8c2-2 4-3.9 6.5-3.5C22 5 23.2 9 21.5 12.5 19 16.65 12 21 12 21z"/></svg>',
        'shield' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="22" height="22"><path d="M12 3l8 3v6c0 4.5-3.2 8-8 9-4.8-1-8-4.5-8-9V6z"/><path d="M12 9v6M9 12h6"/></svg>',
    ];
    return $i[$key] ?? '';
}

/** Trim, collapse whitespace, cap length. */
function booking_clean($v, int $max = 255): string {
    $v = preg_replace('/\s+/u', ' ', trim((string)$v));
    return mb_substr($v, 0, $max);
}

/** 12-digit PhilHealth PIN -> 12-345678901-2 */
function booking_format_pin(string $digits): string {
    $d = preg_replace('/\D/', '', $digits);
    return strlen($d) === 12 ? substr($d, 0, 2) . '-' . substr($d, 2, 9) . '-' . substr($d, 11, 1) : $d;
}

/** Hide all but the last 4 digits for display on review / confirmation pages. */
function booking_mask_pin(string $digits): string {
    $d = preg_replace('/\D/', '', $digits);
    return str_repeat('•', max(0, strlen($d) - 4)) . substr($d, -4);
}

/** Philippine-friendly phone check: 10-13 digits. */
function booking_valid_phone(string $v): bool {
    $d = preg_replace('/\D/', '', $v);
    return strlen($d) >= 10 && strlen($d) <= 13;
}

/**
 * Makes sure appointments.payment_method and the two coverage tables exist.
 * Safe to call repeatedly. Returns false if the DB user is not allowed to
 * alter/create (then run database/booking_payment_methods.sql by hand).
 */
function booking_ensure_payment_schema(mysqli $conn): bool {
    try {
        $r = $conn->query("SHOW COLUMNS FROM appointments LIKE 'payment_method'");
        if ($r && $r->num_rows === 0) {
            $conn->query("ALTER TABLE appointments ADD COLUMN payment_method ENUM('Regular','YAKAP','HMO') NOT NULL DEFAULT 'Regular' AFTER payment_status");
        }

        $conn->query("CREATE TABLE IF NOT EXISTS appointment_yakap (
            id INT NOT NULL AUTO_INCREMENT,
            appointment_id INT NOT NULL,
            philhealth_pin VARCHAR(14) NOT NULL,
            patient_name VARCHAR(150) NOT NULL,
            date_of_birth DATE NULL,
            member_type ENUM('Member','Dependent') NOT NULL,
            contact_number VARCHAR(20) NOT NULL,
            address VARCHAR(255) NOT NULL,
            yakap_clinic VARCHAR(150) NOT NULL,
            empanelment_status ENUM('Empaneled','Not Yet Empaneled') NOT NULL,
            fpe_status ENUM('Completed','Not Yet Completed') NOT NULL,
            consent TINYINT(1) NOT NULL DEFAULT 0,
            consent_at DATETIME NULL,
            verification_status ENUM('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending',
            diagnosis_icd10 TEXT NULL,
            consultation_notes TEXT NULL,
            prescription TEXT NULL,
            pcu_reference_no VARCHAR(60) NULL,
            provider_confirmed TINYINT(1) NOT NULL DEFAULT 0,
            provider_confirmed_at DATETIME NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_yakap_appt (appointment_id),
            CONSTRAINT appointment_yakap_fk FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $conn->query("CREATE TABLE IF NOT EXISTS appointment_hmo (
            id INT NOT NULL AUTO_INCREMENT,
            appointment_id INT NOT NULL,
            hmo_provider VARCHAR(100) NOT NULL,
            hmo_member_id VARCHAR(60) NOT NULL,
            member_type ENUM('Member','Dependent') NOT NULL,
            principal_member_name VARCHAR(150) NOT NULL,
            company_employer VARCHAR(150) NULL,
            hmo_plan VARCHAR(100) NULL,
            patient_name VARCHAR(150) NOT NULL,
            date_of_birth DATE NULL,
            contact_number VARCHAR(20) NOT NULL,
            service_type ENUM('Online Consultation','Follow-up Consultation') NOT NULL,
            loa_number VARCHAR(60) NULL,
            coverage_status ENUM('Pending','Approved','Not Covered') NOT NULL DEFAULT 'Pending',
            hmo_coverage_amount DECIMAL(10,2) NULL,
            patient_share DECIMAL(10,2) NULL,
            diagnosis TEXT NULL,
            consultation_notes TEXT NULL,
            prescription TEXT NULL,
            supporting_documents VARCHAR(255) NULL,
            claim_reference_no VARCHAR(60) NULL,
            claim_status ENUM('Pending','Approved','Denied','Processed') NOT NULL DEFAULT 'Pending',
            consent TINYINT(1) NOT NULL DEFAULT 0,
            consent_at DATETIME NULL,
            provider_confirmed TINYINT(1) NOT NULL DEFAULT 0,
            provider_confirmed_at DATETIME NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_hmo_appt (appointment_id),
            CONSTRAINT appointment_hmo_fk FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $r = $conn->query("SHOW COLUMNS FROM appointments LIKE 'payment_method'");
        return $r && $r->num_rows === 1;
    } catch (Throwable $e) {
        error_log('booking_ensure_payment_schema: ' . $e->getMessage());
        return false;
    }
}

/** CSS for the payment-method cards and the YAKAP / HMO forms. */
function booking_form_css(): string {
    return <<<CSS
    <style>
    .method-list{display:grid;gap:.8rem}
    .method-card{display:flex;align-items:center;gap:1rem;background:#fff;border:1.5px solid rgba(36,68,65,.12);border-radius:16px;padding:1rem 1.2rem;cursor:pointer;transition:border-color .15s,box-shadow .15s}
    .method-card:hover{border-color:rgba(195,54,67,.4)}
    .method-card.selected{border-color:var(--red);box-shadow:0 0 0 3px rgba(195,54,67,.1)}
    .method-card input{position:absolute;opacity:0;pointer-events:none}
    .method-icon{width:46px;height:46px;border-radius:12px;background:rgba(36,68,65,.07);color:var(--green);display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .method-card.selected .method-icon{background:rgba(195,54,67,.1);color:var(--red)}
    .method-card strong{display:block;color:var(--green);font-size:.98rem}
    .method-card small{display:block;color:var(--muted);font-size:.8rem;margin-top:.15rem}
    .f-grid{display:grid;grid-template-columns:1fr 1fr;gap:.9rem 1.1rem}
    .f-full{grid-column:1/-1}
    .f-field label.f-lbl{display:block;font-size:.72rem;font-weight:700;color:var(--green);margin-bottom:.3rem}
    .f-field label.f-lbl .req{color:var(--red)}
    .f-field input[type=text],.f-field input[type=tel],.f-field select,.f-field textarea{width:100%;box-sizing:border-box;padding:.65rem .8rem;border:1.5px solid rgba(36,68,65,.15);border-radius:10px;font:inherit;font-size:.88rem;background:#fff;color:#151c27}
    .f-field input:focus,.f-field select:focus{outline:none;border-color:var(--red)}
    .f-field input[readonly]{background:#f3f5f5;color:#5b6b69}
    .f-hint{font-size:.7rem;color:var(--muted);margin-top:.25rem}
    .f-choice{display:flex;gap:1.1rem;flex-wrap:wrap;padding:.35rem 0}
    .f-choice label{display:flex;align-items:center;gap:.4rem;font-size:.86rem;color:#151c27;cursor:pointer}
    .f-consent{display:flex;gap:.6rem;align-items:flex-start;background:rgba(36,68,65,.04);border-radius:12px;padding:.8rem 1rem;font-size:.82rem;color:var(--green);line-height:1.5}
    .f-consent input{margin-top:.25rem;flex-shrink:0}
    .f-note{border-left:3px solid var(--blue);background:#f3f6ff;border-radius:8px;padding:.6rem .8rem;font-size:.76rem;color:var(--green);line-height:1.45;margin-bottom:1rem}
    .f-sec{font-size:.72rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);margin:1.2rem 0 .6rem}
    .f-sec:first-child{margin-top:0}
    @media(max-width:640px){.f-grid{grid-template-columns:1fr}}
    </style>
    CSS;
}
