<?php
// includes/settings_action.php
// AJAX endpoint used by profile.php's Notification Preferences toggles
// and the Deactivate Account button.

require_once __DIR__ . '/auth.php';            // gives us $conn, $patient_id
require_once __DIR__ . '/settings_helpers.php'; // tc_ensure_patient_settings_columns()

header('Content-Type: application/json');

tc_ensure_patient_settings_columns($conn);

$action = $_POST['action'] ?? '';

if ($action === 'toggle_notification') {
    // Map the JS "pref" values to their actual DB columns.
    $allowed_prefs = [
        'appointment_reminders'  => 'notif_appointment_reminders',
        'payment_notifications'  => 'notif_payment_notifications',
        'medical_record_updates' => 'notif_medical_record_updates',
    ];

    $pref  = $_POST['pref'] ?? '';
    $value = (isset($_POST['value']) && $_POST['value'] === '1') ? 1 : 0;

    if (!isset($allowed_prefs[$pref])) {
        echo json_encode(['success' => false, 'error' => 'Invalid preference']);
        exit;
    }

    $column = $allowed_prefs[$pref];
    $stmt = $conn->prepare("UPDATE patients SET `$column` = ? WHERE id = ?");
    $stmt->bind_param('ii', $value, $patient_id);
    $ok = $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => (bool) $ok]);
    exit;
}

if ($action === 'deactivate_account') {
    $stmt = $conn->prepare("UPDATE patients SET account_status = 'inactive' WHERE id = ?");
    $stmt->bind_param('i', $patient_id);
    $ok = $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => (bool) $ok]);
    exit;
}

// Unknown / removed action (e.g. toggle_two_factor, if you still have that
// case being called from somewhere after removing the 2FA UI).
echo json_encode(['success' => false, 'error' => 'Unknown action']);