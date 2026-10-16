<?php
// private_telecare/booking/hmo_config_helpers.php
// HMO admin configuration shared by the booking pages, the staff pages and the admin pages:
// provider settings, per-provider rules, doctor participation, agent contacts, staff permissions, access log.
// Depends on nothing except a mysqli connection.

const HMO_RULE_DOCS    = ['card' => 'HMO card / membership proof', 'id' => 'Valid ID', 'loa' => 'LOA / LOG / eLOA', 'referral' => "Doctor's request / referral"];
const HMO_RULE_FIELDS  = ['company' => 'Company / employer', 'plan' => 'Plan / account type'];   // Member ID + principal name are always required
const HMO_RULE_METHODS = ['portal' => 'HMO portal', 'api' => 'API', 'phone' => 'Phone', 'email' => 'Email'];
const HMO_CONSULT_TYPES = ['Online Consultation', 'Follow-up Consultation'];
const HMO_PERMS = ['can_verify' => 'Verify members / log HMO contact', 'can_update_status' => 'Update case status (approve, not approved, ask patient)', 'can_view_documents' => 'Open patient documents'];

function hmo_cfg_ensure_schema(mysqli $conn): bool {
    static $ok = null;
    if ($ok !== null) return $ok;
    try {
        $t = $conn->query("SHOW TABLES LIKE 'hmo_providers'");
        if (!$t || $t->num_rows === 0) return $ok = false;
        $add = function (string $table, string $col, string $ddl) use ($conn) {
            $r = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
            if ($r && $r->num_rows === 0) $conn->query("ALTER TABLE `$table` ADD COLUMN `$col` $ddl");
        };
        $add('hmo_providers', 'on_hold', 'TINYINT(1) NOT NULL DEFAULT 0');
        $add('hmo_providers', 'hold_reason', 'VARCHAR(255) NULL');
        $add('hmo_providers', 'hold_since', 'DATETIME NULL');
        $add('hmo_providers', 'provider_code', 'VARCHAR(20) NULL');
        $add('hmo_providers', 'support_channels', 'VARCHAR(255) NULL');
        $add('hmo_providers', 'teleconsult_available', 'TINYINT(1) NOT NULL DEFAULT 1');
        $add('hmo_providers', 'effective_from', 'DATE NULL');

        $eng = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci';
        $conn->query("CREATE TABLE IF NOT EXISTS hmo_provider_contacts (
            id INT NOT NULL AUTO_INCREMENT, provider_id INT NOT NULL, agent_name VARCHAR(120) NOT NULL, role_title VARCHAR(80) NULL,
            phone VARCHAR(40) NULL, email VARCHAR(120) NULL, channel VARCHAR(60) NULL, notes VARCHAR(255) NULL, is_primary TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_hpc_provider (provider_id),
            CONSTRAINT hmo_provider_contacts_fk FOREIGN KEY (provider_id) REFERENCES hmo_providers(id) ON DELETE CASCADE) $eng");
        $conn->query("CREATE TABLE IF NOT EXISTS hmo_provider_doctors (
            provider_id INT NOT NULL, doctor_id INT NOT NULL, PRIMARY KEY (provider_id, doctor_id),
            CONSTRAINT hmo_provider_doctors_fk FOREIGN KEY (provider_id) REFERENCES hmo_providers(id) ON DELETE CASCADE) $eng");
        $conn->query("CREATE TABLE IF NOT EXISTS hmo_rules (
            provider_id INT NOT NULL, loa_requirement ENUM('required','optional','not_applicable') NOT NULL DEFAULT 'optional',
            member_fields VARCHAR(255) NOT NULL DEFAULT '', required_docs VARCHAR(255) NOT NULL DEFAULT 'card',
            verification_method ENUM('portal','api','phone','email') NOT NULL DEFAULT 'phone', validity_days INT NULL,
            consult_types VARCHAR(255) NOT NULL DEFAULT 'Online Consultation,Follow-up Consultation', copay_note VARCHAR(255) NULL,
            effective_from DATE NULL, updated_by INT NULL, updated_at DATETIME NULL, PRIMARY KEY (provider_id),
            CONSTRAINT hmo_rules_fk FOREIGN KEY (provider_id) REFERENCES hmo_providers(id) ON DELETE CASCADE) $eng");
        $conn->query("CREATE TABLE IF NOT EXISTS hmo_staff_permissions (
            staff_id INT NOT NULL, can_verify TINYINT(1) NOT NULL DEFAULT 1, can_update_status TINYINT(1) NOT NULL DEFAULT 1,
            can_view_documents TINYINT(1) NOT NULL DEFAULT 1, updated_by INT NULL, updated_at DATETIME NULL, PRIMARY KEY (staff_id)) $eng");
        $conn->query("CREATE TABLE IF NOT EXISTS hmo_access_log (
            id INT NOT NULL AUTO_INCREMENT, actor_type ENUM('admin','staff') NOT NULL, actor_id INT NOT NULL, action VARCHAR(40) NOT NULL,
            entity VARCHAR(30) NOT NULL, entity_id INT NULL, appointment_id INT NULL, detail VARCHAR(255) NULL, ip VARCHAR(45) NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_hal_actor (actor_type, actor_id), KEY idx_hal_appt (appointment_id)) $eng");
        $conn->query("CREATE TABLE IF NOT EXISTS audit_logs (
            id INT NOT NULL AUTO_INCREMENT, admin_id INT NOT NULL, action VARCHAR(50) NOT NULL, entity_type VARCHAR(30) NOT NULL, entity_id INT NOT NULL,
            old_values JSON DEFAULT NULL, new_values JSON DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id),
            KEY admin_id (admin_id), KEY entity (entity_type, entity_id)) $eng");
        return $ok = true;
    } catch (Throwable $e) {
        error_log('hmo_cfg_ensure_schema: ' . $e->getMessage());
        return $ok = false;
    }
}

function hmo_csv_list(?string $v): array { return array_values(array_filter(array_map('trim', explode(',', (string)$v)), 'strlen')); }

/** Rules for a provider by name. Providers without saved rules get safe defaults. "Other" gets the defaults too. */
function hmo_rules_for(mysqli $conn, string $providerName): array {
    $d = ['loa_requirement' => 'optional', 'member_fields' => [], 'required_docs' => ['card'], 'verification_method' => 'phone',
          'validity_days' => null, 'consult_types' => HMO_CONSULT_TYPES, 'copay_note' => '', 'saved' => false];
    if (!hmo_cfg_ensure_schema($conn)) return $d;
    try {
        $st = $conn->prepare("SELECT r.* FROM hmo_rules r JOIN hmo_providers p ON p.id = r.provider_id WHERE p.name = ?");
        $st->bind_param('s', $providerName); $st->execute();
        if ($r = $st->get_result()->fetch_assoc()) {
            $d = ['loa_requirement' => $r['loa_requirement'], 'member_fields' => hmo_csv_list($r['member_fields']),
                  'required_docs' => hmo_csv_list($r['required_docs']), 'verification_method' => $r['verification_method'],
                  'validity_days' => $r['validity_days'] !== null ? (int)$r['validity_days'] : null,
                  'consult_types' => hmo_csv_list($r['consult_types']) ?: HMO_CONSULT_TYPES, 'copay_note' => (string)$r['copay_note'], 'saved' => true];
        }
    } catch (Throwable $e) { }
    return $d;
}

/**
 * HMOs that can NOT be used for this booking: name => short label shown in the dropdown.
 * Reasons: suspended by the clinic (e.g. unpaid bills), teleconsultation switched off, or the doctor is not on the provider's participation list.
 */
function hmo_unavailable(mysqli $conn, ?int $doctor_id = null): array {
    $out = [];
    if (!hmo_cfg_ensure_schema($conn)) return $out;
    try {
        $r = $conn->query("SELECT p.id, p.name, p.on_hold, p.teleconsult_available,
                (SELECT COUNT(*) FROM hmo_provider_doctors d WHERE d.provider_id = p.id) AS n_docs,
                (SELECT COUNT(*) FROM hmo_provider_doctors d WHERE d.provider_id = p.id AND d.doctor_id = " . (int)$doctor_id . ") AS has_doc
            FROM hmo_providers p WHERE p.status = 'Active'");
        while ($r && ($p = $r->fetch_assoc())) {
            if ((int)$p['on_hold'] === 1)                    $out[$p['name']] = 'not accepted right now';
            elseif ((int)$p['teleconsult_available'] === 0)  $out[$p['name']] = 'teleconsultation not offered';
            elseif ($doctor_id && (int)$p['n_docs'] > 0 && (int)$p['has_doc'] === 0) $out[$p['name']] = 'not available with this doctor';
        }
    } catch (Throwable $e) { }
    return $out;
}

function hmo_staff_can(mysqli $conn, int $staff_id, string $perm): bool {
    if (!isset(HMO_PERMS[$perm]) || !hmo_cfg_ensure_schema($conn)) return true;
    try {
        $st = $conn->prepare("SELECT `$perm` AS v FROM hmo_staff_permissions WHERE staff_id = ?");
        $st->bind_param('i', $staff_id); $st->execute();
        $row = $st->get_result()->fetch_assoc();
        return $row ? (int)$row['v'] === 1 : true;   // no row = everything allowed
    } catch (Throwable $e) { return true; }
}

function hmo_log_access(mysqli $conn, string $actorType, int $actorId, string $action, string $entity, ?int $entityId = null, ?int $apptId = null, ?string $detail = null): void {
    try {
        if (!hmo_cfg_ensure_schema($conn)) return;
        $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $detail = $detail !== null ? mb_substr($detail, 0, 255) : null;
        $st = $conn->prepare("INSERT INTO hmo_access_log (actor_type, actor_id, action, entity, entity_id, appointment_id, detail, ip) VALUES (?,?,?,?,?,?,?,?)");
        $st->bind_param('sissiiss', $actorType, $actorId, $action, $entity, $entityId, $apptId, $detail, $ip);
        $st->execute();
    } catch (Throwable $e) { error_log('hmo_log_access: ' . $e->getMessage()); }
}

/** Admin configuration change -> audit_logs with previous and new values. */
function hmo_admin_audit(mysqli $conn, int $adminId, string $action, string $entityType, int $entityId, $old = null, $new = null): void {
    try {
        hmo_cfg_ensure_schema($conn);
        $o = $old === null ? null : json_encode($old, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $n = $new === null ? null : json_encode($new, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $st = $conn->prepare("INSERT INTO audit_logs (admin_id, action, entity_type, entity_id, old_values, new_values) VALUES (?,?,?,?,?,?)");
        $st->bind_param('ississ', $adminId, $action, $entityType, $entityId, $o, $n);
        $st->execute();
    } catch (Throwable $e) { error_log('hmo_admin_audit: ' . $e->getMessage()); }
}

/** Mask a name ("Juan Dela Cruz" -> "Juan D.") and an ID ("ABC12345" -> "••••2345"). */
function hmo_mask_name(string $n): string {
    $p = preg_split('/\s+/u', trim($n)); if (!$p || $p[0] === '') return '—';
    return count($p) === 1 ? $p[0] : $p[0] . ' ' . mb_substr(end($p), 0, 1) . '.';
}
function hmo_mask_member(string $id): string {
    $id = trim($id); $n = mb_strlen($id);
    return $n <= 4 ? str_repeat('•', $n) : str_repeat('•', $n - 4) . mb_substr($id, -4);
}
