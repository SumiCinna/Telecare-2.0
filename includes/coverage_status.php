<?php
// includes/coverage_status.php
// Used by includes/header.php (patient bell). An HMO / YAKAP appointment is saved as "Confirmed" so the
// 10-minute payment timer does not cancel it, but it is only REALLY confirmed once the clinic approved the coverage.
function tc_coverage_pending(mysqli $conn, int $appt_id, string $method): ?array {
    try {
        if ($method === 'HMO') {
            $r = $conn->query("SELECT coverage_status AS s, loa_number FROM appointment_hmo WHERE appointment_id = " . (int)$appt_id);
            $row = $r ? $r->fetch_assoc() : null;
            if (!$row || $row['s'] === 'Approved') return null;
            if (in_array($row['s'], ['Not Approved', 'Not Covered'], true)) return ['HMO not approved', 'Your HMO was not approved for this appointment. Please choose another payment method or cancel.'];
            if ($row['s'] === 'Action Required')                               return ['Action needed for your HMO booking', 'The clinic needs something from you before your HMO booking can be approved.'];
            return trim((string)$row['loa_number']) !== ''
                ? ['LOA received', 'The clinic is checking your LOA. Your appointment is confirmed once it is accepted.']
                : ['Waiting for HMO approval', 'The clinic is asking your HMO for approval. Your appointment is confirmed once the HMO approves.'];
        }
        if ($method === 'YAKAP') {
            $r = $conn->query("SELECT verification_status AS s FROM appointment_yakap WHERE appointment_id = " . (int)$appt_id);
            $row = $r ? $r->fetch_assoc() : null;
            if (!$row || $row['s'] === 'Verified') return null;
            return ['Waiting for YAKAP verification', 'The clinic is verifying your PhilHealth YAKAP details. Your appointment is confirmed once they finish.'];
        }
    } catch (Throwable $e) { }
    return null;
}
