<?php
// staff/includes/hmo_helpers.php
// Shared helpers for the staff HMO verification screens (hmo_queue.php, hmo_case.php, dashboard card).

require_once __DIR__ . '/../../private_telecare/booking/booking_helpers.php';
require_once __DIR__ . '/../../private_telecare/booking/hmo_patient_helpers.php';

const HMO_OPEN_STATUSES = ['Pending', 'In Verification', 'Action Required'];
const HMO_ALL_STATUSES  = ['Pending', 'In Verification', 'Action Required', 'Approved', 'Not Approved'];
const HMO_CONTACT_CHANNELS = ['Phone call', 'Email', 'HMO portal', 'Provider hotline', 'Other'];
const HMO_VERIFY_METHODS   = ['Phone call to HMO', 'HMO portal', 'Email with HMO', 'Provider hotline', 'LOA/eLOA presented by patient', 'Other'];

/** Add a column only if it is missing. */
function hmo_add_col(mysqli $conn, string $table, string $col, string $ddl): void {
    $r = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
    if ($r && $r->num_rows === 0) $conn->query("ALTER TABLE `$table` ADD COLUMN `$col` $ddl");
}

/**
 * Makes sure everything the staff HMO screens need exists. Cheap; safe to call on every page load.
 * Returns false (and logs) if the database user is not allowed to alter/create.
 */
function hmo_ensure_schema(mysqli $conn): bool {
    static $done = null;
    if ($done !== null) return $done;
    try {
        // Base tables from the booking feature (appointment_hmo etc.)
        if (!booking_ensure_payment_schema($conn)) return $done = false;

        // Workflow status: Pending -> In Verification -> Action Required / Approved / Not Approved
        $c = $conn->query("SHOW COLUMNS FROM appointment_hmo LIKE 'coverage_status'");
        $col = $c ? $c->fetch_assoc() : null;
        if ($col && stripos((string)$col['Type'], "'In Verification'") === false) {
            $conn->query("ALTER TABLE appointment_hmo MODIFY COLUMN coverage_status
                ENUM('Pending','In Verification','Action Required','Approved','Not Approved','Not Covered') NOT NULL DEFAULT 'Pending'");
        }

        hmo_add_col($conn, 'appointment_hmo', 'staff_instructions', 'TEXT NULL');
        hmo_add_col($conn, 'appointment_hmo', 'approval_loa_no',    'VARCHAR(60) NULL');
        hmo_add_col($conn, 'appointment_hmo', 'valid_from',         'DATE NULL');
        hmo_add_col($conn, 'appointment_hmo', 'valid_until',        'DATE NULL');
        hmo_add_col($conn, 'appointment_hmo', 'approved_service',   'VARCHAR(150) NULL');
        hmo_add_col($conn, 'appointment_hmo', 'benefit_note',       'VARCHAR(255) NULL');
        hmo_add_col($conn, 'appointment_hmo', 'verification_method','VARCHAR(60) NULL');
        hmo_add_col($conn, 'appointment_hmo', 'decision_reason',    'VARCHAR(255) NULL');
        hmo_add_col($conn, 'appointment_hmo', 'decided_by',         'INT NULL');
        hmo_add_col($conn, 'appointment_hmo', 'decided_at',         'DATETIME NULL');
        hmo_add_col($conn, 'appointment_hmo', 'status_updated_at',  'DATETIME NULL');

        $conn->query("CREATE TABLE IF NOT EXISTS hmo_contact_log (
            id INT NOT NULL AUTO_INCREMENT,
            appointment_id INT NOT NULL,
            staff_id INT NULL,
            channel VARCHAR(40) NOT NULL,
            contact_detail VARCHAR(150) NULL,
            contacted_at DATETIME NOT NULL,
            hmo_reference_no VARCHAR(80) NULL,
            response TEXT NOT NULL,
            follow_up_date DATE NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_hmo_contact_appt (appointment_id),
            CONSTRAINT hmo_contact_log_fk FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $conn->query("CREATE TABLE IF NOT EXISTS hmo_case_events (
            id INT NOT NULL AUTO_INCREMENT,
            appointment_id INT NOT NULL,
            staff_id INT NULL,
            event_type VARCHAR(30) NOT NULL,
            status_from VARCHAR(30) NULL,
            status_to VARCHAR(30) NULL,
            summary VARCHAR(255) NOT NULL,
            detail TEXT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_hmo_events_appt (appointment_id, id),
            CONSTRAINT hmo_case_events_fk FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        // Patient-side pieces: documents table, submit / assistance columns, provider hold columns
        hmo_patient_ensure_schema($conn);
        booking_ensure_hold_columns($conn);

        return $done = true;
    } catch (Throwable $e) {
        error_log('hmo_ensure_schema: ' . $e->getMessage());
        return $done = false;
    }
}

function hmo_status_class(string $s): string {
    return ['Pending' => 'bg-gray', 'In Verification' => 'bg-blue', 'Action Required' => 'bg-orange',
            'Approved' => 'bg-green', 'Not Approved' => 'bg-red'][$s] ?? 'bg-gray';
}

/** Case timeline entry. */
function hmo_add_event(mysqli $conn, int $appt_id, ?int $staff_id, string $type, string $summary, ?string $detail = null, ?string $from = null, ?string $to = null): void {
    $st = $conn->prepare("INSERT INTO hmo_case_events (appointment_id, staff_id, event_type, status_from, status_to, summary, detail) VALUES (?,?,?,?,?,?,?)");
    $st->bind_param('iisssss', $appt_id, $staff_id, $type, $from, $to, $summary, $detail);
    $st->execute();
}

/** In-app notification for the patient (shows in their bell). One per type per appointment; re-sending refreshes it. */
function hmo_notify_patient(mysqli $conn, int $patient_id, int $appt_id, string $type, string $title, string $message): void {
    $link = 'router.php?page=booking/hmo_status&appt_id=' . $appt_id;
    $st = $conn->prepare("INSERT INTO notifications (patient_id, type, title, message, link, reference_type, reference_id)
        VALUES (?, ?, ?, ?, ?, 'appointment', ?)
        ON DUPLICATE KEY UPDATE title = VALUES(title), message = VALUES(message), link = VALUES(link), is_read = 0, created_at = NOW()");
    $st->bind_param('issssi', $patient_id, $type, $title, $message, $link, $appt_id);
    $st->execute();
}

/** Simple per-session CSRF token for the HMO forms. */
function hmo_csrf(): string {
    if (empty($_SESSION['hmo_csrf'])) $_SESSION['hmo_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['hmo_csrf'];
}
function hmo_csrf_ok(): bool {
    return !empty($_SESSION['hmo_csrf']) && hash_equals($_SESSION['hmo_csrf'], (string)($_POST['csrf'] ?? ''));
}

/** Counts for the queue header and the staff dashboard. Cancelled appointments are left out. */
function hmo_counts(mysqli $conn): array {
    $out = ['new' => 0, 'pending' => 0, 'action' => 0, 'approved' => 0, 'rejected' => 0, 'upcoming_open' => 0, 'ok' => false];
    try {
        if (!hmo_ensure_schema($conn)) return $out;
        $r = $conn->query("
            SELECT
              SUM(h.coverage_status = 'Pending')                                                  AS new_cnt,
              SUM(h.coverage_status IN ('Pending','In Verification'))                             AS pend_cnt,
              SUM(h.coverage_status = 'Action Required')                                          AS act_cnt,
              SUM(h.coverage_status = 'Approved')                                                 AS appr_cnt,
              SUM(h.coverage_status = 'Not Approved')                                             AS rej_cnt,
              SUM(h.coverage_status IN ('Pending','In Verification','Action Required')
                  AND a.appointment_date >= CURDATE())                                            AS up_cnt
            FROM appointment_hmo h JOIN appointments a ON a.id = h.appointment_id
            WHERE a.status <> 'Cancelled'");
        $row = $r ? $r->fetch_assoc() : null;
        if ($row) {
            $out = ['new' => (int)$row['new_cnt'], 'pending' => (int)$row['pend_cnt'], 'action' => (int)$row['act_cnt'],
                    'approved' => (int)$row['appr_cnt'], 'rejected' => (int)$row['rej_cnt'], 'upcoming_open' => (int)$row['up_cnt'], 'ok' => true];
        }
    } catch (Throwable $e) { error_log('hmo_counts: ' . $e->getMessage()); }
    return $out;
}
