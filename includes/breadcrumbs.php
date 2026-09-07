<?php
// Shared, role-aware breadcrumb metadata and renderer.

if (!function_exists('tc_breadcrumb_url')) {
    function tc_breadcrumb_url($role, $route) {
        if ($role === 'patient') {
            $prefix = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'router.php' ? '' : '../';
            return $prefix . 'router.php?page=' . rawurlencode($route);
        }
        return $route;
    }
}

if (!function_exists('tc_breadcrumb_items')) {
    function tc_breadcrumb_items($role, $page, array $context = []) {
        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        $route = $_GET['page'] ?? '';
        if ($role === 'patient') {
            $page = [
                'step1_details.php' => 'booking', 'step2_doctor.php' => 'booking',
                'step3_schedule.php' => 'booking', 'step4_review.php' => 'booking',
                'payment.php' => 'booking', 'confirmed.php' => 'booking', 'success.php' => 'booking',
            ][$script] ?? ([
                'booking/step1_details' => 'booking', 'booking/step2_doctor' => 'booking',
                'booking/step3_schedule' => 'booking', 'booking/step4_review' => 'booking',
                'booking/payment' => 'booking', 'booking/confirmed' => 'booking', 'booking/success' => 'booking',
                'receipt' => 'receipt', 'chat' => 'chat', 'meds' => 'meds', 'profile' => 'profile', 'visits' => 'visits',
            ][$route] ?? $page);
        }
        if ($role === 'doctor' && $script === 'patient-records.php') { $page = 'patient-records'; }
        if ($role === 'doctor' && $script === 'review_summary.php') { $page = 'review-summary'; }

        $home = ['label' => 'Home', 'href' => tc_breadcrumb_url($role, $role === 'patient' ? 'dashboard' : 'dashboard')];
        $maps = [
            'patient' => [
                'home' => ['Dashboard', 'dashboard'],
                'visits' => ['Appointments', 'visits'],
                'booking' => ['Appointments', 'visits'],
                'chat' => ['Teleconsultation', 'chat'],
                'meds' => ['Medical Records', 'meds'],
                'profile' => ['Profile', 'profile'],
                'receipt' => ['Appointments', 'visits'],
            ],
            'doctor' => [
                'home' => ['Dashboard', 'dashboard.php'],
                'appointments' => ['Appointments', 'appointments.php'],
                'availability' => ['Appointments', 'appointments.php'],
                'chat' => ['Teleconsultation', 'chat.php'],
                'patients' => ['Patients', 'patients.php'],
                'patient-records' => ['Patients', 'patients.php'],
                'review-summary' => ['Appointments', 'appointments.php'],
                'credentials' => ['Credentials', 'credentials.php'],
                'profile' => ['Profile', 'profile.php'],
            ],
            'staff' => [
                'dashboard' => ['Dashboard', 'dashboard.php'],
                'appointments' => ['Appointments', 'appointments.php'],
                'doctors' => ['Doctors', 'doctors.php'],
                'patients' => ['Patients', 'patients.php'],
                'pos' => ['Services', 'pos_services.php'],
            ],
            'admin' => [
                'dashboard' => ['Dashboard', 'dashboard.php'],
                'users' => ['User Management', 'Users.php'],
                'assignments' => ['Appointments', 'assignments.php'],
                'templates' => ['Document Templates', 'templates.php'],
                'pos-products' => ['Inventory', 'inventory.php'],
                'pos-services' => ['Services', 'services.php'],
                'pos-discounts' => ['Discounts', 'discounts.php'],
                'pos-prices' => ['Prices', 'prices.php'],
                'pos-hmo' => ['HMO', 'hmo.php'],
                'pos-receipt' => ['Receipt Configuration', 'receipt_config.php'],
            ],
            'super_admin' => [
                'dashboard' => ['Dashboard', 'dashboard.php'],
                'users' => ['User Management', 'users.php'],
                'clinics' => ['Clinic Management', 'clinics.php'],
                'appointments' => ['Appointments', 'appointments.php'],
                'consultations' => ['Consultations', 'consultations.php'],
                'payments' => ['Payments', 'prices.php'],
                'reports' => ['Reports', 'reports.php'],
                'legal_policies' => ['Legal Policies', 'legal_policies.php'],
                'audit_logs' => ['Audit Logs', 'audit_logs.php'],
                'system_settings' => ['System Settings', 'system_settings.php'],
            ],
        ];

        if (!empty($context['items'])) {
            $items = [['label' => 'Home', 'href' => tc_breadcrumb_url($role, 'dashboard.php')]];
            foreach ($context['items'] as $item) {
                if (!empty($item['label'])) {
                    $items[] = ['label' => $item['label'], 'href' => $item['href'] ?? null];
                }
            }
            return $items;
        }

        $items = [$home];
        $entry = $maps[$role][$page] ?? null;
        if ($entry && $page !== 'home' && $page !== 'dashboard') {
            $items[] = ['label' => $entry[0], 'href' => tc_breadcrumb_url($role, $entry[1])];
        }

        foreach (($context['parents'] ?? []) as $parent) {
            if (!empty($parent['label'])) {
                $items[] = ['label' => $parent['label'], 'href' => $parent['href'] ?? null];
            }
        }

        $current = $context['current'] ?? ($entry[0] ?? ($context['label'] ?? 'Current Page'));
        if ($page === 'home' || $page === 'dashboard') {
            $current = 'Dashboard';
            if (count($items) === 1) {
                $items[] = ['label' => $current, 'href' => null];
            }
        } elseif ($role === 'patient' && $page === 'booking') {
            $current = $context['current'] ?? ([
                'booking/step1_details' => 'Book Appointment',
                'booking/step2_doctor' => 'Select Doctor',
                'booking/step3_schedule' => 'Select Schedule',
                'booking/step4_review' => 'Review Appointment',
                'booking/payment' => 'Payment',
                'booking/confirmed' => 'Appointment Details',
                'booking/success' => 'Booking Confirmed',
            ][$route] ?? 'Book Appointment');
        } elseif ($role === 'doctor' && $page === 'patient-records') {
            $current = 'Patient Details';
        } elseif ($role === 'doctor' && $page === 'review-summary') {
            $current = 'Consultation Details';
        } elseif (!$items || end($items)['label'] !== $current) {
            $items[] = ['label' => $current, 'href' => null];
        } else {
            $items[count($items) - 1]['href'] = null;
        }
        return $items;
    }
}

if (!function_exists('tc_render_breadcrumbs')) {
    function tc_render_breadcrumbs($role, $page, array $context = []) {
        $items = tc_breadcrumb_items($role, $page, $context);
        ob_start();
        ?>
        <nav class="tc-breadcrumb" aria-label="Breadcrumb">
          <?php foreach ($items as $index => $item): ?>
            <?php if ($index > 0): ?><span class="tc-breadcrumb-sep" aria-hidden="true">/</span><?php endif; ?>
            <?php if (!empty($item['href'])): ?>
              <a href="<?= htmlspecialchars($item['href'], ENT_QUOTES) ?>"><?= htmlspecialchars($item['label']) ?></a>
            <?php else: ?>
              <span class="tc-breadcrumb-current" aria-current="page"><?= htmlspecialchars($item['label']) ?></span>
            <?php endif; ?>
          <?php endforeach; ?>
        </nav>
        <?php
        return trim(ob_get_clean());
    }
}
