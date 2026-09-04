<?php
// private_telecare/booking/booking_helpers.php
// Shared constants + guards for the multi-step booking wizard.
// Included by every step*.php / payment.php / success.php / confirmed.php.

const BOOKING_DEPARTMENTS = [
    'General Medicine / General Practice' => [
        'desc' => 'Common illnesses, symptoms, initial assessment, prescriptions, referrals',
        'icon' => 'stethoscope',
    ],
    'Internal Medicine' => [
        'desc' => 'Adult illnesses, chronic disease management, follow-ups',
        'icon' => 'leaf',
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
    'Internal Medicine' => [
        'Persistent fatigue', 'Weakness', 'Fever', 'Unexplained weight loss', 'Dizziness',
        'Swelling of the legs', 'Abdominal discomfort', 'Persistent cough', 'Shortness of breath',
        'Changes in appetite', 'Excessive thirst', 'Frequent urination', 'Chronic pain',
        'Follow-up for existing condition', 'Abnormal laboratory results',
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
        'stethoscope' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M4.5 3v7a5.5 5.5 0 0011 0V3M8 3H3M20 3h-5M18 10v2a6 6 0 01-12 0v-2M18 15a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
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
    </style>
    CSS;
}

/** Renders the 4-step progress bar. $active = 1..4 */
function render_stepper(int $active): void {
    $labels = ['Details', 'Doctor', 'Schedule', 'Review'];
    echo '<div class="stepper">';
    foreach ($labels as $i => $label) {
        $n = $i + 1;
        $cls = $n < $active ? 'done' : ($n === $active ? 'active' : '');
        echo '<div class="step-col"><div class="step-dot ' . $cls . '">' . ($n < $active ? '&#10003;' : $n) . '</div><div class="step-label">' . $label . '</div></div>';
        if ($n < 4) echo '<div class="step-line ' . ($n < $active ? 'done' : '') . '"></div>';
    }
    echo '</div>';
}