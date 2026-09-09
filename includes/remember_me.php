<?php
// includes/remember_me.php

function tc_patient_generate_selector(): string {
    return bin2hex(random_bytes(9));
}

function tc_patient_generate_validator(): string {
    return bin2hex(random_bytes(33));
}

function tc_patient_set_remember_cookie(mysqli $conn, int $patientId): void {
    $selector      = tc_patient_generate_selector();
    $validator     = tc_patient_generate_validator();
    $validatorHash = hash('sha256', $validator);
    $expiresAt     = time() + 60 * 60 * 24 * 30; // 30 days
    $expiresSql    = date('Y-m-d H:i:s', $expiresAt);

    $stmt = $conn->prepare("UPDATE patients SET remember_selector = ?, remember_validator_hash = ?, remember_expires = ? WHERE id = ?");
    $stmt->bind_param("sssi", $selector, $validatorHash, $expiresSql, $patientId);
    $stmt->execute();

    setcookie('patient_remember', $selector . ':' . $validator, [
        'expires'  => $expiresAt,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function tc_patient_clear_remember_cookie(mysqli $conn, ?int $patientId = null): void {
    if ($patientId) {
        $stmt = $conn->prepare("UPDATE patients SET remember_selector = NULL, remember_validator_hash = NULL, remember_expires = NULL WHERE id = ?");
        $stmt->bind_param("i", $patientId);
        $stmt->execute();
    }

    setcookie('patient_remember', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function tc_patient_attempt_auto_login(mysqli $conn): bool {
    if (isset($_SESSION['patient_id'])) {
        return true;
    }

    if (empty($_COOKIE['patient_remember'])) {
        return false;
    }

    $parts = explode(':', $_COOKIE['patient_remember'], 2);
    if (count($parts) !== 2) {
        return false;
    }
    [$selector, $validator] = $parts;

    $stmt = $conn->prepare("SELECT id, full_name, remember_validator_hash, remember_expires, is_verified, is_active FROM patients WHERE remember_selector = ?");
    $stmt->bind_param("s", $selector);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();

    if (!$patient || !$patient['remember_validator_hash'] || !$patient['remember_expires']) {
        tc_patient_clear_remember_cookie($conn);
        return false;
    }

    if (!$patient['is_verified'] || (isset($patient['is_active']) && !$patient['is_active'])) {
        tc_patient_clear_remember_cookie($conn, (int)$patient['id']);
        return false;
    }

    if (strtotime($patient['remember_expires']) < time()) {
        tc_patient_clear_remember_cookie($conn, (int)$patient['id']);
        return false;
    }

    if (!hash_equals($patient['remember_validator_hash'], hash('sha256', $validator))) {
        // Validator mismatch — possible token theft, wipe it
        tc_patient_clear_remember_cookie($conn, (int)$patient['id']);
        return false;
    }

    $_SESSION['patient_id']   = $patient['id'];
    $_SESSION['patient_name'] = $patient['full_name'];

    // Rotate the token so a stolen cookie value can't be reused indefinitely
    tc_patient_set_remember_cookie($conn, (int)$patient['id']);

    return true;
}