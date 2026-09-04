<?php
// private_telecare/booking/booking_helpers.php
// Shared constants + guards for the multi-step booking wizard.
// Included by every step*.php / payment.php / success.php / confirmed.php.

const BOOKING_DEPARTMENTS = [
    'General Medicine' => ['desc' => 'Primary care and general checkups', 'icon' => 'stethoscope'],
    'Pediatrics'        => ['desc' => 'Child healthcare and vaccinations', 'icon' => 'smile'],
    'Cardiology'        => ['desc' => 'Heart and vascular system',        'icon' => 'heart'],
    'Internal Medicine' => ['desc' => 'Adult diseases and prevention',    'icon' => 'leaf'],
    'Dermatology'       => ['desc' => 'Skin, hair, and nail care',        'icon' => 'hand'],
];

const BOOKING_REASONS = [
    'Fever', 'Cough / Cold', 'Headache', 'Stomach / Abdominal Pain',
    'Body Pain', 'Skin Problem', 'Follow-up Consultation', 'Request for Prescription',
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