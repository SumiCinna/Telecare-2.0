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
if (!hmo_staff_can($conn, (int)$staff_id, 'can_view_documents')) { http_response_code(403); echo 'You do not have permission to open patient documents. Ask an admin.'; exit; }
hmo_log_access($conn, 'staff', (int)$staff_id, 'view_document', 'hmo_document', (int)$doc['id'], (int)$doc['appointment_id'], ($doc['doc_type'] ?? '') . ': ' . $doc['original_name']);
hmo_stream_doc($doc, isset($_GET['download']));
