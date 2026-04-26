<?php
function logAction($conn, $appt_id, $staff_id, $action, $notes = '') {
    $stmt = $conn->prepare("INSERT INTO appointment_logs (appointment_id,staff_id,action,notes) VALUES (?,?,?,?)");
    $stmt->bind_param("iiss", $appt_id, $staff_id, $action, $notes);
    $stmt->execute();
}