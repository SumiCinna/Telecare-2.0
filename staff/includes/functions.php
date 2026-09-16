<?php
// staff/includes/functions.php

function logAction($conn, $appt_id, $staff_id, $action, $notes = '') {
    $stmt = $conn->prepare("INSERT INTO appointment_logs (appointment_id,staff_id,action,notes) VALUES (?,?,?,?)");
    $stmt->bind_param("iiss", $appt_id, $staff_id, $action, $notes);
    $stmt->execute();
}



const STAFF_NOTIF_AUDIENCE = 'staff';
const STAFF_NOTIF_PATIENT  = 0;     // sentinel: not owned by any patient

// Make sure a notification row exists for a given (type, reference_id) pair.
function ensureNotification($conn, $type, $reference_id, $title, $message, $link) {
    $stmt = $conn->prepare("
        INSERT IGNORE INTO notifications
            (audience, patient_id, type, reference_type, reference_id, title, message, link, is_read)
        VALUES ('staff', 0, ?, 'appointment', ?, ?, ?, ?)
    ");
    $stmt->bind_param('sisss', $type, $reference_id, $title, $message, $link);
    $stmt->execute();
}

// Scan current data for anything notification-worthy and make sure it's recorded.
// Cheap (indexed, small result sets) and safe to call on every page load.
function syncNotifications($conn) {
    if (!$conn) return;

    // Doctor approved it — staff still needs to confirm before the patient is asked to pay.
    $res = $conn->query("
        SELECT a.id, a.appointment_date, a.appointment_time, p.full_name patient_name
        FROM appointments a JOIN patients p ON p.id = a.patient_id
        WHERE a.status = 'DoctorApproved'
    ");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $when = date('M j, g:i A', strtotime($row['appointment_date'] . ' ' . $row['appointment_time']));
            ensureNotification(
                $conn, 'awaiting_confirmation', (int)$row['id'],
                'Appointment awaiting confirmation',
                $row['patient_name'] . "'s appointment ($when) was approved by the doctor and needs your confirmation.",
                'appointments.php?tab=DoctorApproved'
            );
        }
    }

    // Freshly booked appointment still waiting on the doctor — front desk visibility.
    $res = $conn->query("
        SELECT a.id, a.appointment_date, a.appointment_time, p.full_name patient_name
        FROM appointments a JOIN patients p ON p.id = a.patient_id
        WHERE a.status = 'Pending' AND a.appointment_date >= CURDATE()
    ");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $when = date('M j, g:i A', strtotime($row['appointment_date'] . ' ' . $row['appointment_time']));
            ensureNotification(
                $conn, 'new_booking', (int)$row['id'],
                'New appointment request',
                $row['patient_name'] . " booked an appointment ($when) that's waiting for the doctor's review.",
                'appointments.php?tab=Pending'
            );
        }
    }
}

// Shared WHERE fragment: staff-audience rows whose appointment is still in
// the state that made them notification-worthy, and unread by this staff member.
function staffNotifWhereSql() {
    return "
        FROM notifications n
        JOIN appointments a
          ON a.id = n.reference_id
         AND n.reference_type = 'appointment'
        LEFT JOIN notification_reads r
          ON r.notification_id = n.id AND r.staff_id = ?
        WHERE n.audience = 'staff'
          AND n.patient_id = 0
          AND r.notification_id IS NULL
          AND (
                (n.type = 'awaiting_confirmation' AND a.status = 'DoctorApproved')
             OR (n.type = 'new_booking'           AND a.status = 'Pending')
          )
    ";
}

// Notifications still "live" and not yet read by this staff member.
function getActiveNotifications($conn, $staff_id, $limit = 20) {
    $sql = "SELECT n.id, n.type, n.title, n.message, n.link, n.created_at"
         . staffNotifWhereSql()
         . " ORDER BY n.created_at DESC, n.id DESC LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $staff_id, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getUnreadNotificationCount($conn, $staff_id) {
    $sql = "SELECT COUNT(*) c" . staffNotifWhereSql();
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $staff_id);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['c'];
}

function markNotificationRead($conn, $notification_id, $staff_id) {
    // Guard: only staff-audience rows can be marked read this way, so a crafted
    // id can't touch a patient's notification.
    $stmt = $conn->prepare("
        INSERT IGNORE INTO notification_reads (notification_id, staff_id)
        SELECT n.id, ? FROM notifications n
        WHERE n.id = ? AND n.audience = 'staff'
    ");
    $stmt->bind_param('ii', $staff_id, $notification_id);
    return $stmt->execute();
}

function markAllNotificationsRead($conn, $staff_id) {
    $sql = "INSERT IGNORE INTO notification_reads (notification_id, staff_id) SELECT n.id, ?"
         . staffNotifWhereSql();
    $stmt = $conn->prepare($sql);
    // first ? = inserted staff_id, second ? = the LEFT JOIN staff_id
    $stmt->bind_param('ii', $staff_id, $staff_id);
    return $stmt->execute();
}

function timeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', strtotime($datetime));
}