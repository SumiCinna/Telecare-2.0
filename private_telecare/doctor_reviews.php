<?php
// private_telecare/doctor_reviews.php
// Public (logged-in patient) read-only feed of a doctor's ratings +
// optional comments, plus the live average — used by the "See reviews"
// modal on the Select Doctor step. Every patient can see every rating.
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

try {

    $doctor_id = (int)($_GET['doctor_id'] ?? 0);
    if (!$doctor_id) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing doctor ID']);
        exit;
    }

    $doc_stmt = $conn->prepare("SELECT id, full_name, rating, rating_count FROM doctors WHERE id=?");
    if ($doc_stmt === false) {
        throw new RuntimeException('DB error (rating_count column missing? run doctor_ratings_migration.sql): ' . $conn->error);
    }
    $doc_stmt->bind_param('i', $doctor_id);
    $doc_stmt->execute();
    $doctor = $doc_stmt->get_result()->fetch_assoc();

    if (!$doctor) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Doctor not found']);
        exit;
    }

    // Star breakdown (5 -> 1), Play Store style bar chart.
    $breakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    $bd_res = $conn->prepare("SELECT rating, COUNT(*) c FROM doctor_ratings WHERE doctor_id=? GROUP BY rating");
    if ($bd_res === false) {
        throw new RuntimeException('DB error (doctor_ratings table missing? run doctor_ratings_migration.sql): ' . $conn->error);
    }
    $bd_res->bind_param('i', $doctor_id);
    $bd_res->execute();
    $bd = $bd_res->get_result();
    while ($row = $bd->fetch_assoc()) {
        $breakdown[(int)$row['rating']] = (int)$row['c'];
    }

    // Latest 30 written reviews (rating + comment), newest first.
    $rev_stmt = $conn->prepare("
        SELECT r.rating, r.comment, r.updated_at, p.full_name AS patient_name
        FROM doctor_ratings r
        JOIN patients p ON p.id = r.patient_id
        WHERE r.doctor_id = ? AND r.comment IS NOT NULL AND r.comment <> ''
        ORDER BY r.updated_at DESC
        LIMIT 30
    ");
    if ($rev_stmt === false) {
        throw new RuntimeException('DB error loading reviews: ' . $conn->error);
    }
    $rev_stmt->bind_param('i', $doctor_id);
    $rev_stmt->execute();
    $reviews_res = $rev_stmt->get_result();

    $reviews = [];
    while ($r = $reviews_res->fetch_assoc()) {
        // First name + last-initial only, for a bit of privacy in a shared review feed.
        $parts = preg_split('/\s+/', trim($r['patient_name']));
        $display_name = $parts[0] ?? 'Patient';
        if (count($parts) > 1) {
            $display_name .= ' ' . strtoupper(substr(end($parts), 0, 1)) . '.';
        }
        $reviews[] = [
            'rating'  => (int)$r['rating'],
            'comment' => $r['comment'],
            'date'    => date('M j, Y', strtotime($r['updated_at'])),
            'name'    => $display_name,
        ];
    }

    echo json_encode([
        'ok'           => true,
        'doctor_name'  => $doctor['full_name'],
        'avg_rating'   => (float)$doctor['rating'],
        'rating_count' => (int)$doctor['rating_count'],
        'breakdown'    => $breakdown,
        'reviews'      => $reviews,
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}
