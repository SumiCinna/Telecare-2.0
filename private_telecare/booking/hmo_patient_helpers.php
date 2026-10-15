<?php
// private_telecare/booking/hmo_patient_helpers.php
// Patient-side HMO pages: documents, review, status, resolve. Shared with the staff case page (documents).
require_once __DIR__ . '/booking_helpers.php';

const HMO_DOC_TYPES = [
    'card'     => 'HMO card / membership proof',
    'id'       => 'Valid ID',
    'loa'      => 'LOA / LOG / eLOA',
    'referral' => "Doctor's request / referral",
    'other'    => 'Other document',
];
const HMO_DOC_MAX_BYTES = 5242880; // 5 MB
const HMO_DOC_MAX_FILES = 12;      // per appointment

function hmo_docs_dir(): string {
    return realpath(__DIR__ . '/../..') . '/uploads/hmo_docs';
}

/** Docs table + the extra columns the patient pages use. Safe to call on every request. */
function hmo_patient_ensure_schema(mysqli $conn): bool {
    static $ok = null;
    if ($ok !== null) return $ok;
    try {
        if (!booking_ensure_payment_schema($conn)) return $ok = false;
        $conn->query("CREATE TABLE IF NOT EXISTS appointment_hmo_docs (
            id INT NOT NULL AUTO_INCREMENT,
            appointment_id INT NOT NULL,
            doc_type VARCHAR(20) NOT NULL,
            stored_name VARCHAR(80) NOT NULL,
            original_name VARCHAR(150) NOT NULL,
            mime VARCHAR(60) NOT NULL,
            size_bytes INT NOT NULL DEFAULT 0,
            uploaded_by ENUM('patient','staff') NOT NULL DEFAULT 'patient',
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_hmo_docs_appt (appointment_id),
            CONSTRAINT appointment_hmo_docs_fk FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        foreach (['patient_submitted_at' => 'DATETIME NULL', 'assistance_requested' => 'TINYINT(1) NOT NULL DEFAULT 0',
                  'assistance_note' => 'VARCHAR(255) NULL', 'patient_updated_at' => 'DATETIME NULL'] as $col => $ddl) {
            $r = $conn->query("SHOW COLUMNS FROM appointment_hmo LIKE '$col'");
            if ($r && $r->num_rows === 0) $conn->query("ALTER TABLE appointment_hmo ADD COLUMN `$col` $ddl");
        }
        return $ok = true;
    } catch (Throwable $e) {
        error_log('hmo_patient_ensure_schema: ' . $e->getMessage());
        return $ok = false;
    }
}

/** The patient's own HMO appointment + case, or null. */
function hmo_patient_case(mysqli $conn, int $appt_id, int $patient_id): ?array {
    $st = $conn->prepare("
        SELECT h.*, a.reference_no, a.appointment_date, a.appointment_time, a.status AS appt_status, a.payment_status,
               a.payment_method, a.type AS appt_type, a.reason, d.full_name AS doctor_name, d.department AS doctor_dept
        FROM appointment_hmo h
        JOIN appointments a ON a.id = h.appointment_id
        JOIN doctors d ON d.id = a.doctor_id
        WHERE h.appointment_id = ? AND a.patient_id = ? AND a.payment_method = 'HMO'");
    $st->bind_param('ii', $appt_id, $patient_id);
    $st->execute();
    return $st->get_result()->fetch_assoc() ?: null;
}

function hmo_mask_id(string $id): string {
    $id = trim($id);
    $n = mb_strlen($id);
    if ($n <= 4) return str_repeat('•', $n);
    return str_repeat('•', $n - 4) . mb_substr($id, -4);
}

function hmo_docs_for(mysqli $conn, int $appt_id): array {
    $r = $conn->query("SELECT * FROM appointment_hmo_docs WHERE appointment_id = " . (int)$appt_id . " ORDER BY id ASC");
    return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
}

/** Writes a case event (shown to staff in the case history). */
function hmo_patient_event(mysqli $conn, int $appt_id, string $summary, ?string $detail = null): void {
    try {
        $st = $conn->prepare("INSERT INTO hmo_case_events (appointment_id, staff_id, event_type, summary, detail) VALUES (?, NULL, 'Patient', ?, ?)");
        $st->bind_param('iss', $appt_id, $summary, $detail);
        $st->execute();
    } catch (Throwable $e) { error_log('hmo_patient_event: ' . $e->getMessage()); }
}

/**
 * Saves uploaded files from $_FILES[$field] (multiple) under one document type.
 * Returns ['saved'=>int,'errors'=>string[]]. JPG, PNG, WEBP or PDF up to 5 MB, checked by content (not file name).
 */
function hmo_save_uploads(mysqli $conn, int $appt_id, string $type, string $field, string $by = 'patient'): array {
    $out = ['saved' => 0, 'errors' => []];
    if (!isset(HMO_DOC_TYPES[$type])) { $out['errors'][] = 'Choose what kind of document you are uploading.'; return $out; }
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]['name'])) { $out['errors'][] = 'Choose a file to upload.'; return $out; }
    $dir = hmo_docs_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) { $out['errors'][] = 'Uploads are not available right now. Please contact the clinic.'; return $out; }
    if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\nOptions -Indexes\n");
    if (!is_file($dir . '/index.html')) @file_put_contents($dir . '/index.html', '');

    $cnt = $conn->query("SELECT COUNT(*) c FROM appointment_hmo_docs WHERE appointment_id = " . (int)$appt_id)->fetch_assoc()['c'] ?? 0;
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
    $fi = new finfo(FILEINFO_MIME_TYPE);
    $n = count($_FILES[$field]['name']);
    for ($i = 0; $i < $n; $i++) {
        $err = $_FILES[$field]['error'][$i];
        if ($err === UPLOAD_ERR_NO_FILE) continue;
        $orig = mb_substr(preg_replace('/[^\p{L}\p{N}._ \-()]/u', '_', basename((string)$_FILES[$field]['name'][$i])), 0, 120);
        if ($err !== UPLOAD_ERR_OK) { $out['errors'][] = "$orig could not be uploaded" . ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE ? ' (too large, max 5 MB).' : '.'); continue; }
        $tmp = $_FILES[$field]['tmp_name'][$i];
        if (!is_uploaded_file($tmp)) continue;
        if ($cnt + $out['saved'] >= HMO_DOC_MAX_FILES) { $out['errors'][] = 'You reached the limit of ' . HMO_DOC_MAX_FILES . ' documents.'; break; }
        $size = (int)filesize($tmp);
        if ($size <= 0 || $size > HMO_DOC_MAX_BYTES) { $out['errors'][] = "$orig is too large (max 5 MB)."; continue; }
        $mime = (string)$fi->file($tmp);
        if (!isset($allowed[$mime])) { $out['errors'][] = "$orig is not allowed. Upload a JPG, PNG, WEBP or PDF."; continue; }
        $stored = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($tmp, $dir . '/' . $stored)) { $out['errors'][] = "$orig could not be saved."; continue; }
        $st = $conn->prepare("INSERT INTO appointment_hmo_docs (appointment_id, doc_type, stored_name, original_name, mime, size_bytes, uploaded_by) VALUES (?,?,?,?,?,?,?)");
        $st->bind_param('issssis', $appt_id, $type, $stored, $orig, $mime, $size, $by);
        $st->execute();
        $out['saved']++;
    }
    if ($out['saved'] > 0) hmo_patient_event($conn, $appt_id, 'Patient uploaded ' . $out['saved'] . ' document' . ($out['saved'] > 1 ? 's' : '') . '.', HMO_DOC_TYPES[$type]);
    return $out;
}

/** Streams one document (caller already checked access). */
function hmo_stream_doc(array $doc, bool $download = false): void {
    $path = hmo_docs_dir() . '/' . basename($doc['stored_name']);
    if (!is_file($path)) { http_response_code(404); echo 'File not found.'; exit; }
    header('Content-Type: ' . $doc['mime']);
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'");
    header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . str_replace('"', '', $doc['original_name']) . '"');
    header('Cache-Control: private, no-store');
    readfile($path);
    exit;
}

function hmo_pat_csrf(): string {
    if (empty($_SESSION['hmo_pat_csrf'])) $_SESSION['hmo_pat_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['hmo_pat_csrf'];
}
function hmo_pat_csrf_ok(): bool {
    return !empty($_SESSION['hmo_pat_csrf']) && hash_equals($_SESSION['hmo_pat_csrf'], (string)($_POST['csrf'] ?? ''));
}

/** Shared CSS for the patient HMO pages (uses the wizard CSS variables). */
function hmo_patient_css(): string {
    return <<<'CSS'
<style>
.hp-wrap{max-width:860px;margin:0 auto;padding:1.4rem 1.2rem 5rem}
.hp-card{background:#fff;border:1px solid rgba(36,68,65,.1);border-radius:16px;padding:1.2rem 1.3rem;margin-bottom:1rem}
.hp-card h3{font-family:'DM Sans',sans-serif;font-size:.95rem;font-weight:800;color:var(--green);margin:0 0 .6rem}
.hp-kv{display:grid;grid-template-columns:1fr 1fr;gap:.55rem .9rem;font-size:.8rem}
.hp-kv .k{font-size:.66rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--muted)}
.hp-kv .v{font-weight:600;color:var(--green);word-break:break-word}
.hp-full{grid-column:1/-1}
.hp-flash{border-radius:12px;padding:.7rem .9rem;font-size:.8rem;margin-bottom:.9rem;line-height:1.45}
.hp-flash.ok{background:rgba(34,197,94,.12);color:#15803d}.hp-flash.wait{background:rgba(217,119,6,.12);color:#b45309}
.hp-flash.no{background:rgba(195,54,67,.1);color:#b4232f}.hp-flash.info{background:rgba(63,130,227,.1);color:#2a62b3}
.hp-pill{display:inline-block;border-radius:50px;padding:.25rem .7rem;font-size:.72rem;font-weight:800}
.hp-pill.ok{background:rgba(34,197,94,.14);color:#15803d}.hp-pill.wait{background:rgba(217,119,6,.14);color:#b45309}
.hp-pill.act{background:rgba(234,88,12,.14);color:#c2410c}.hp-pill.no{background:rgba(195,54,67,.12);color:#b4232f}
.hp-list{list-style:none;margin:0;padding:0}
.hp-list li{display:flex;justify-content:space-between;gap:.6rem;align-items:center;border:1px solid rgba(36,68,65,.08);border-radius:10px;padding:.5rem .7rem;margin-bottom:.4rem;font-size:.78rem}
.hp-list a{color:var(--red);font-weight:700;text-decoration:none}
.hp-lbl{display:block;font-size:.72rem;font-weight:700;color:var(--green);margin:.7rem 0 .25rem}
.hp-in{width:100%;box-sizing:border-box;border:1.5px solid rgba(36,68,65,.18);border-radius:10px;padding:.6rem .7rem;font:inherit;font-size:.82rem;background:#fff}
.hp-actions{display:flex;gap:.6rem;flex-wrap:wrap;margin-top:1rem}
.hp-muted{font-size:.74rem;color:var(--muted);line-height:1.5}
.hp-steps{display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:1rem;font-size:.7rem;font-weight:700}
.hp-steps span{padding:.3rem .7rem;border-radius:50px;background:rgba(36,68,65,.07);color:var(--muted)}
.hp-steps span.on{background:var(--red);color:#fff}
.hp-tl{list-style:none;margin:0;padding:0}.hp-tl li{font-size:.76rem;border-left:2px solid rgba(36,68,65,.12);padding:0 0 .7rem .8rem;margin-left:.3rem}
.hp-tl .m{color:var(--muted);font-size:.68rem}
@media(max-width:560px){.hp-kv{grid-template-columns:1fr}}
</style>
CSS;
}
