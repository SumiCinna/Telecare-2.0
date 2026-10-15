<?php
// staff/hmo_file.php — staff opens a document a patient uploaded for an HMO case
require_once 'includes/auth.php';
require_once 'includes/hmo_helpers.php';

$id = (int)($_GET['id'] ?? 0);
if (!hmo_ensure_schema($conn)) { http_response_code(404); echo 'Not available.'; exit; }
$st = $conn->prepare("SELECT * FROM appointment_hmo_docs WHERE id = ?");
$st->bind_param('i', $id);
$st->execute();
$doc = $st->get_result()->fetch_assoc();
if (!$doc) { http_response_code(404); echo 'File not found.'; exit; }
hmo_stream_doc($doc, isset($_GET['download']));
