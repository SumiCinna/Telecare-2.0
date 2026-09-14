<?php
// includes/emailjs.php  (same folder as includes/auth.php)

require_once __DIR__ . '/env_loader.php';
function emailjs_send(string $templateId, array $templateParams): bool {
    $serviceId  = $_ENV['EMAILJS_SERVICE_ID']  ?? (getenv('EMAILJS_SERVICE_ID')  ?: '');
    $publicKey  = $_ENV['EMAILJS_PUBLIC_KEY']  ?? (getenv('EMAILJS_PUBLIC_KEY')  ?: '');
    $privateKey = $_ENV['EMAILJS_PRIVATE_KEY'] ?? (getenv('EMAILJS_PRIVATE_KEY') ?: '');

    if (!$serviceId || !$publicKey || !$privateKey) {
        error_log('emailjs_send: missing EMAILJS_SERVICE_ID / EMAILJS_PUBLIC_KEY / EMAILJS_PRIVATE_KEY env vars');
        return false;
    }

    $payload = [
        'service_id'      => $serviceId,
        'template_id'     => $templateId,
        'user_id'         => $publicKey,
        'accessToken'     => $privateKey, // required for non-browser (server-side) sends
        'template_params' => $templateParams,
    ];

    $ch = curl_init('https://api.emailjs.com/api/v1.0/email/send');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 10,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($code !== 200) {
        error_log("emailjs_send: EmailJS returned HTTP $code — $body $err");
        return false;
    }
    return true;
}

/**
 * Sends the "Appointment Confirmed" notification (template_6wzqmmg) to the
 * patient right after their payment is verified in pay_success.php.
 *
 * $appt must include: patient_email, patient_name, doctor_name, specialty,
 * appointment_date, appointment_time, reference_no, consultation_fee.
 */
function send_appointment_confirmation_email(array $appt, string $receiptNo): bool {
    if (empty($appt['patient_email'])) return false;

    $dateStr = (new DateTime($appt['appointment_date']))->format('M j, Y');
    $timeStr = date('g:i A', strtotime($appt['appointment_time']));

    return emailjs_send('template_6wzqmmg', [
        'to_email'         => $appt['patient_email'],
        'to_name'          => $appt['patient_name'],
        'email'            => $appt['patient_email'],
        'header_subtitle'  => 'Your consultation is booked and paid.',
        'role_message'     => sprintf(
            'Your appointment with Dr. %s (%s) on %s at %s has been confirmed. Reference: %s. Receipt No: %s.',
            $appt['doctor_name'],
            $appt['specialty'],
            $dateStr,
            $timeStr,
            $appt['reference_no'],
            $receiptNo
        ),
        'reference_no'     => $appt['reference_no'],
        'doctor_name'      => $appt['doctor_name'],
        'specialty'        => $appt['specialty'],
        'appointment_date' => $dateStr,
        'appointment_time' => $timeStr,
        'receipt_number'   => $receiptNo,
        'amount'           => number_format((float)$appt['consultation_fee'], 2),
    ]);
}