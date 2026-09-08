<?php
// includes/document_delivery.php

if (!function_exists('telecare_document_delivery_schema')) {
    function telecare_document_delivery_schema(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $completedCol = $conn->query("SHOW COLUMNS FROM appointments LIKE 'completed_at'");
        if ($completedCol && $completedCol->num_rows === 0) {
            @$conn->query("ALTER TABLE appointments ADD COLUMN completed_at datetime DEFAULT NULL AFTER summary_reviewed_at");
        }

        $docTypeCol = $conn->query("SHOW COLUMNS FROM lab_results LIKE 'doc_type'");
        if ($docTypeCol && ($row = $docTypeCol->fetch_assoc())) {
            $typeDef = (string)($row['Type'] ?? '');
            if (stripos($typeDef, 'lab_request') === false || stripos($typeDef, 'med_cert') === false) {
                @$conn->query("ALTER TABLE lab_results MODIFY doc_type enum('lab_result','prescription','lab_request','med_cert','unknown') DEFAULT 'unknown'");
            }
        }

        @$conn->query("CREATE TABLE IF NOT EXISTS appointment_document_drafts (
            id int NOT NULL AUTO_INCREMENT,
            appointment_id int NOT NULL,
            doc_type enum('prescription','lab_request','med_cert') NOT NULL,
            draft_text longtext NOT NULL,
            source_summary longtext DEFAULT NULL,
            source_model varchar(100) DEFAULT NULL,
            created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_appt_doc (appointment_id, doc_type),
            KEY idx_appointment_id (appointment_id),
            CONSTRAINT appointment_document_drafts_ibfk_1 FOREIGN KEY (appointment_id) REFERENCES appointments (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    function telecare_document_type_label(string $docType): string
    {
        return match ($docType) {
            'prescription' => 'Prescription',
            'lab_request'  => 'Laboratory Request',
            'med_cert'     => 'Medical Certificate',
            default        => ucfirst(str_replace('_', ' ', $docType)),
        };
    }

    function telecare_document_type_title(string $docType): string
    {
        return match ($docType) {
            'prescription' => 'PRESCRIPTION',
            'lab_request'  => 'LABORATORY REQUEST',
            'med_cert'     => 'MEDICAL CERTIFICATE',
            default        => strtoupper(str_replace('_', ' ', $docType)),
        };
    }

    function telecare_get_document_template(mysqli $conn, string $docType): array
{
    $fallback = [
        'doc_type'    => $docType,
        'clinic_name' => '',
        'address'     => '',
        'contact_no'  => '',
        'footer_note' => '',
        'updated_at'  => null,
    ];

    $stmt = $conn->prepare(
        "SELECT * FROM document_templates
         WHERE doc_type = ? AND status = 'Active' AND is_default = 1
         ORDER BY updated_at DESC
         LIMIT 1"
    );
    if (!$stmt) {
        return $fallback;
    }
    $stmt->bind_param('s', $docType);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Fall back to any active template for this type if somehow none is
    // flagged default (shouldn't normally happen, but keeps sends from
    // failing outright).
    if (!$row) {
        $stmt2 = $conn->prepare(
            "SELECT * FROM document_templates
             WHERE doc_type = ? AND status = 'Active'
             ORDER BY updated_at DESC
             LIMIT 1"
        );
        if ($stmt2) {
            $stmt2->bind_param('s', $docType);
            $stmt2->execute();
            $row = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
        }
    }

    return $row ?: $fallback;
}

    function telecare_get_latest_completed_appointment(mysqli $conn, int $doctorId, int $patientId): ?array
    {
        telecare_document_delivery_schema($conn);
        $stmt = $conn->prepare("SELECT id, appointment_date, appointment_time, status, completed_at, consultation_summary FROM appointments WHERE doctor_id = ? AND patient_id = ? AND status = 'Completed' ORDER BY completed_at DESC, appointment_date DESC, appointment_time DESC LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('ii', $doctorId, $patientId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    function telecare_document_send_window_open(?string $completedAt, int $minutes = 60): bool
    {
        if (!$completedAt) {
            return false;
        }
        $expiresAt = strtotime($completedAt . ' +' . $minutes . ' minutes');
        return $expiresAt !== false && time() <= $expiresAt;
    }

    function telecare_document_send_window_remaining(?string $completedAt, int $minutes = 60): int
    {
        if (!$completedAt) {
            return 0;
        }
        $expiresAt = strtotime($completedAt . ' +' . $minutes . ' minutes');
        if ($expiresAt === false) {
            return 0;
        }
        return max(0, $expiresAt - time());
    }

    function telecare_document_suggest_types(string $summary): array
    {
        $text = strtolower($summary);
        $types = [];

        if (preg_match('/\b(prescribe|prescription|rx|medication|tablet|capsule|syrup|dose|mg|ml|take .*daily|dispense)\b/', $text)) {
            $types[] = 'prescription';
        }
        if (preg_match('/\b(lab|laboratory|cbc|urinalysis|fbs|bun|creatinine|sgpt|sgot|lipid|cholesterol|uric acid|sodium|potassium|x-?ray|ecg|hba1c|thyroid|ft3|ft4|tsh|dengue)\b/', $text)) {
            $types[] = 'lab_request';
        }
        if (preg_match('/\b(medical certificate|certificate|rest|excuse|fit to work|fit to travel|cleared|advised to rest|days? of rest)\b/', $text)) {
            $types[] = 'med_cert';
        }

        return array_values(array_unique($types));
    }

    function telecare_document_draft_text(string $docType, array $patient, array $doctor, array $template, string $summary): string
    {
        $summary = trim($summary);
        $summaryBlock = $summary !== '' ? $summary : 'Not discussed.';

        if ($docType === 'prescription') {
            return trim("Rx [MEDICATION NAME / STRENGTH / FORM]\n[INSTRUCTIONS / DOSAGE]\nDispense: [QUANTITY / VOLUME]\nLabel: [DIRECTIONS FOR USE]\n[ADDITIONAL INSTRUCTIONS]\n\nClinical note based on consultation summary:\n{$summaryBlock}");
        }

        if ($docType === 'lab_request') {
            return trim("Blood Chemistry: [FASTING REQUIREMENT / OTHER INSTRUCTIONS]\nFBS\nBUN\nCrea\nSGPT\nSGOT\nLipid Profile\nUric Acid\nNa\nK\nOthers:\nOther Tests:\nCBC\nPlatelet\nUrinalysis\nFecalysis\nDengue NS1 / IgM / IgG\nHbA1c\nFT3\nFT4\nTSH\nChest X-ray PA\nChest X-ray APL\nChest X-ray Apicolordotic\n12-L ECG\nOthers:\n\nClinical note based on consultation summary:\n{$summaryBlock}");
        }

        return trim("To whom it may concern:\nThis is to certify that [PATIENT NAME]\npresently residing at [PATIENT ADDRESS]\nis [AGE] years old, [SEX] and was [CONSULTED / EXAMINED / TREATED]\non [DATE / PERIOD] and advised to rest for [NUMBER OF DAYS / PERIOD]\nAssessment/Impression:\n[ASSESSMENT / IMPRESSION]\nRecommendations/Remarks:\n[RECOMMENDATIONS / REMARKS]\n[ADDITIONAL NOTES]\n\nClinical note based on consultation summary:\n{$summaryBlock}");
    }

    function telecare_store_document_draft(mysqli $conn, int $appointmentId, string $docType, string $draftText, string $summary, ?string $sourceModel = null): void
    {
        telecare_document_delivery_schema($conn);
        $stmt = $conn->prepare("INSERT INTO appointment_document_drafts (appointment_id, doc_type, draft_text, source_summary, source_model) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE draft_text = VALUES(draft_text), source_summary = VALUES(source_summary), source_model = VALUES(source_model), updated_at = CURRENT_TIMESTAMP");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('issss', $appointmentId, $docType, $draftText, $summary, $sourceModel);
        $stmt->execute();
        $stmt->close();
    }

    function telecare_get_document_draft(mysqli $conn, int $appointmentId, string $docType): ?array
    {
        telecare_document_delivery_schema($conn);
        $stmt = $conn->prepare("SELECT * FROM appointment_document_drafts WHERE appointment_id = ? AND doc_type = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('is', $appointmentId, $docType);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}
