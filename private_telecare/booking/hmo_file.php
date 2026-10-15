<?php
// private_telecare/booking/hmo_file.php — the patient opens one of their OWN uploaded HMO documents.
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/hmo_patient_helpers.php';

$id = (int)($_GET['id'] ?? 0);
$st = $conn->prepare("SELECT d.* FROM appointment_hmo_docs d JOIN appointments a ON a.id = d.appointment_id WHERE d.id = ? AND a.patient_id = ?");
$st->bind_param('ii', $id, $patient_id);
$st->execute();
$doc = $st->get_result()->fetch_assoc();
if (!$doc) { http_response_code(404); echo 'File not found.'; exit; }
hmo_stream_doc($doc, isset($_GET['download']));
