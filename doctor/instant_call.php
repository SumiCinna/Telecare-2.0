<?php
// doctor/instant_call.php
date_default_timezone_set('Asia/Manila');
require_once 'includes/auth.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'list') {
    $search = trim($_GET['search'] ?? '');
    $sql = "SELECT DISTINCT p.id, p.full_name, p.profile_photo
            FROM appointments a
            JOIN patients p ON p.id = a.patient_id
            WHERE a.doctor_id = ?";
    $params = [$doctor_id];
    $types = 'i';
    if ($search !== '') {
        $sql .= " AND p.full_name LIKE ?";
        $params[] = "%$search%";
        $types .= 's';
    }
    $sql .= " ORDER BY p.full_name LIMIT 25";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $patients = [];
    while ($row = $res->fetch_assoc()) {
        $patients[] = [
            'id' => (int)$row['id'],
            'full_name' => $row['full_name'],
            'profile_photo' => $row['profile_photo'],
            'initials' => strtoupper(substr($row['full_name'], 0, 2)),
        ];
    }
    echo json_encode(['success' => true, 'patients' => $patients]);
    exit;
}

if ($action === 'start' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = (int)($_POST['patient_id'] ?? 0);
    if (!$patient_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing patient_id']);
        exit;
    }

    // Confirm this doctor actually has a relationship with this patient
    $check = $conn->prepare("SELECT id FROM appointments WHERE doctor_id = ? AND patient_id = ? LIMIT 1");
    $check->bind_param("ii", $doctor_id, $patient_id);
    $check->execute();
    if (!$check->get_result()->fetch_assoc()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Not one of your patients']);
        exit;
    }

    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $date = $now->format('Y-m-d');
    $time = $now->format('H:i:s');
    $reference_no = 'IC-' . strtoupper(bin2hex(random_bytes(4)));

    $ins = $conn->prepare("
        INSERT INTO appointments
            (reference_no, patient_id, doctor_id, appointment_date, appointment_time, type, status, reason, payment_status)
        VALUES (?, ?, ?, ?, ?, 'Teleconsult', 'Confirmed', 'Instant call started by doctor', 'Paid')
    ");
    $ins->bind_param("siiss", $reference_no, $patient_id, $doctor_id, $date, $time);
    $ins->execute();
    $appt_id = $ins->insert_id;

    // Notify the patient
    $doc_name = $doc['full_name'] ?? 'your doctor';
    $title = 'Incoming Instant Call';
    $message = "Dr. {$doc_name} started an instant video consultation with you. Tap to join.";
    $link = 'router.php?page=visits';
    $notif = $conn->prepare("
        INSERT INTO notifications (patient_id, type, title, message, link, reference_type, reference_id)
        VALUES (?, 'instant_call', ?, ?, ?, 'appointment', ?)
    ");
    $notif->bind_param("isssi", $patient_id, $title, $message, $link, $appt_id);
    $notif->execute();

    echo json_encode(['success' => true, 'appt_id' => $appt_id]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid action']);