<?php
// private_telecare/rate_doctor.php
// Lets a logged-in patient rate/review a doctor, and change that rating
// later, as long as the patient still has appointment history with the
// doctor (a non-Pending, non-Cancelled appointment — i.e. Completed or
// Confirmed). One rating per patient per doctor; submitting again just
// updates it (upsert), same as editing a Shopee / Play Store review.
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Everything below is wrapped in a try/catch so that no matter what goes
// wrong (missing table, DB error, etc.) we always return valid JSON
// instead of a PHP fatal-error page — which is what breaks the frontend
// with "Network error" (the browser can't parse HTML as JSON).
try {

    $doctor_id = (int)($_REQUEST['doctor_id'] ?? 0);

    if (!$doctor_id) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing doctor ID']);
        exit;
    }

    // A patient is eligible to rate a doctor once they have at least one
    // appointment with that doctor that actually happened / was accepted —
    // i.e. not just Pending or Cancelled.
    function tc_patient_has_doctor_history(mysqli $conn, int $patient_id, int $doctor_id): bool {
        $stmt = $conn->prepare("
            SELECT id FROM appointments
            WHERE patient_id = ? AND doctor_id = ?
              AND status IN ('Completed','Confirmed')
            LIMIT 1
        ");
        if ($stmt === false) {
            throw new RuntimeException('DB error checking appointment history: ' . $conn->error);
        }
        $stmt->bind_param('ii', $patient_id, $doctor_id);
        $stmt->execute();
        return (bool) $stmt->get_result()->fetch_assoc();
    }

    $eligible = tc_patient_has_doctor_history($conn, $patient_id, $doctor_id);

    // ── GET: return the patient's existing rating for this doctor (if any),
    //         plus whether they're currently allowed to rate. Used to
    //         pre-fill / open the rating modal. ──
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!$eligible) {
            echo json_encode(['ok' => true, 'eligible' => false]);
            exit;
        }

        $stmt = $conn->prepare("SELECT rating, comment FROM doctor_ratings WHERE patient_id=? AND doctor_id=?");
        if ($stmt === false) {
            throw new RuntimeException('DB error (doctor_ratings table missing? run doctor_ratings_migration.sql): ' . $conn->error);
        }
        $stmt->bind_param('ii', $patient_id, $doctor_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        echo json_encode([
            'ok'       => true,
            'eligible' => true,
            'rating'   => $existing ? (int)$existing['rating'] : null,
            'comment'  => $existing ? $existing['comment'] : null,
        ]);
        exit;
    }

    // ── POST: create or update the patient's rating for this doctor. ──
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$eligible) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'You can only rate a doctor after a confirmed or completed appointment with them.']);
            exit;
        }

        $rating  = (int)($_POST['rating'] ?? 0);
        $comment = trim((string)($_POST['comment'] ?? ''));
        if ($comment === '') $comment = null;
        if ($comment !== null && mb_strlen($comment) > 1000) {
            $comment = mb_substr($comment, 0, 1000);
        }

        if ($rating < 1 || $rating > 5) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Please choose a rating from 1 to 5 stars.']);
            exit;
        }

        // Confirm the doctor exists.
        $chk = $conn->prepare("SELECT id FROM doctors WHERE id=?");
        if ($chk === false) {
            throw new RuntimeException('DB error checking doctor: ' . $conn->error);
        }
        $chk->bind_param('i', $doctor_id);
        $chk->execute();
        if (!$chk->get_result()->fetch_assoc()) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Doctor not found.']);
            exit;
        }

        $stmt = $conn->prepare("
            INSERT INTO doctor_ratings (patient_id, doctor_id, rating, comment)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment)
        ");
        if ($stmt === false) {
            throw new RuntimeException('DB error preparing insert (doctor_ratings table missing? run doctor_ratings_migration.sql): ' . $conn->error);
        }
        $stmt->bind_param('iiis', $patient_id, $doctor_id, $rating, $comment);

        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Could not save rating: ' . $stmt->error]);
            exit;
        }

        // Triggers on doctor_ratings keep doctors.rating / rating_count in
        // sync automatically — pull the fresh average back for the UI.
        $avg = $conn->prepare("SELECT rating, rating_count FROM doctors WHERE id=?");
        if ($avg === false) {
            throw new RuntimeException('DB error reading doctor average: ' . $conn->error);
        }
        $avg->bind_param('i', $doctor_id);
        $avg->execute();
        $doc = $avg->get_result()->fetch_assoc() ?: ['rating' => $rating, 'rating_count' => 1];

        echo json_encode([
            'ok'           => true,
            'rating'       => $rating,
            'comment'      => $comment,
            'avg_rating'   => (float)$doc['rating'],
            'rating_count' => (int)$doc['rating_count'],
        ]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);

} catch (Throwable $e) {
    // Never let a PHP fatal/exception leak an HTML error page into what's
    // supposed to be a JSON response — always answer with clean JSON.
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}
