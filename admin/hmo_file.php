<?php
// admin/hmo_file.php — admin opens a patient HMO document (every open is logged)
require_once 'includes/hmo_admin.php';
require_once __DIR__ . '/../private_telecare/booking/hmo_patient_helpers.php';
hmo_patient_ensure_schema($conn);
$id = (int)($_GET['id'] ?? 0);
$st = $conn->prepare("SELECT * FROM appointment_hmo_docs WHERE id = ?"); $st->bind_param('i', $id); $st->execute();
$doc = $st->get_result()->fetch_assoc();
if (!$doc) { http_response_code(404); echo 'File not found.'; exit; }
hmo_log_access($conn, 'admin', $admin_id, 'view_document', 'hmo_document', (int)$doc['id'], (int)$doc['appointment_id'], ($doc['doc_type'] ?? '') . ': ' . $doc['original_name']);
hmo_stream_doc($doc, isset($_GET['download']));
