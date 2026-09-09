<?php
// includes/settings_helpers.php
//
// Helper functions for the Settings page (private_telecare/profile.php).
// Main job: make sure the "patients" table has the columns that page
// reads/writes (2FA flag, per-type notification prefs, account status),
// adding any that are missing so the app doesn't fatal on a fresh DB.

if (!function_exists('tc_ensure_patient_settings_columns')) {
    function tc_ensure_patient_settings_columns($conn) {
        // column_name => full ALTER TABLE column definition
        $required_columns = [
            'two_factor_enabled'           => "TINYINT(1) NOT NULL DEFAULT 0",
            'notif_appointment_reminders'  => "TINYINT(1) NOT NULL DEFAULT 1",
            'notif_payment_notifications'  => "TINYINT(1) NOT NULL DEFAULT 1",
            'notif_medical_record_updates' => "TINYINT(1) NOT NULL DEFAULT 1",
            'account_status'               => "VARCHAR(20) NOT NULL DEFAULT 'active'",
        ];

        // Find out which of the required columns already exist.
        $existing = [];
        $res = $conn->query("SHOW COLUMNS FROM patients");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $existing[$row['Field']] = true;
            }
            $res->free();
        }

        foreach ($required_columns as $name => $definition) {
            if (!isset($existing[$name])) {
                // Column doesn't exist yet — add it.
                $sql = "ALTER TABLE patients ADD COLUMN `$name` $definition";
                // Don't let a failure here take down the whole page;
                // surface it quietly so profile.php can keep going.
                @$conn->query($sql);
            }
        }
    }
}

if (!function_exists('tc_calculate_age')) {
    function tc_calculate_age($date_of_birth) {
        if (empty($date_of_birth)) {
            return null;
        }
        try {
            $dob = new DateTime($date_of_birth);
            $now = new DateTime('now');
            return $now->diff($dob)->y;
        } catch (Exception $e) {
            return null;
        }
    }
}