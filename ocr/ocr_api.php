<?php
// ocr_api.php — replaces ocr_scan.py on live server

function ocr_space_scan($file_path) {
    // Load environment variables
    $env_file = dirname(__FILE__) . '/../.env';
    $env_vars = [];
    if (file_exists($env_file)) {
        $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') && strpos($line, '#') !== 0) {
                list($key, $value) = explode('=', $line, 2);
                $env_vars[trim($key)] = trim($value);
            }
        }
    }
    
    $api_key  = $env_vars['OCR_SPACE_API_KEY'] ?? 'K87893142388957';
    $ext      = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $mime_map = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'bmp'  => 'image/bmp',
        'tiff' => 'image/tiff',
        'tif'  => 'image/tiff',
        'pdf'  => 'application/pdf',
    ];
    $mime = $mime_map[$ext] ?? 'image/jpeg';

    // Convert file to base64
    $file_data   = file_get_contents($file_path);
    $base64_file = base64_encode($file_data);
    $base64_src  = "data:{$mime};base64,{$base64_file}";

    $post_fields = [
        'apikey'               => $api_key,
        'base64Image'          => $base64_src,
        'language'             => 'eng',
        'isOverlayRequired'    => 'false',
        'detectOrientation'    => 'true',
        'scale'                => 'true',
        'OCREngine'            => '2', // Engine 2 is more accurate
        'isTable'              => 'true',
    ];

    // If PDF, add special flag
    if ($ext === 'pdf') {
        $post_fields['isCreateSearchablePdf'] = 'false';
        $post_fields['isSearchablePdfHideTextLayer'] = 'false';
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://api.ocr.space/parse/image',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $post_fields,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);
    $curl_err = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        return [
            'success' => false,
            'error'   => 'cURL error: ' . $curl_err,
        ];
    }

    $data = json_decode($response, true);

    // Check for API-level errors
    if (!$data || ($data['IsErroredOnProcessing'] ?? false)) {
        $err = $data['ErrorMessage'][0] ?? ($data['ErrorMessage'] ?? 'OCR API error');
        return [
            'success' => false,
            'error'   => $err,
        ];
    }

    // Combine text from all parsed results (handles multi-page PDFs)
    $all_text = [];
    $parsed_results = $data['ParsedResults'] ?? [];
    foreach ($parsed_results as $i => $result) {
        $page_text = trim($result['ParsedText'] ?? '');
        if ($page_text !== '') {
            $page_label = count($parsed_results) > 1 ? "--- Page " . ($i + 1) . " ---\n" : '';
            $all_text[] = $page_label . $page_text;
        }
    }

    $text = implode("\n\n", $all_text);

    return [
        'success'    => true,
        'text'       => $text,
        'type'       => classify_document($text),
        'char_count' => strlen($text),
        'filename'   => basename($file_path),
    ];
}

function classify_document($text) {
    $text_lower = strtolower($text);

    $lab_keywords = [
        'result', 'hemoglobin', 'wbc', 'rbc', 'platelet', 'glucose',
        'cholesterol', 'creatinine', 'uric acid', 'hematocrit',
        'sodium', 'potassium', 'laboratory', 'normal range', 'reference'
    ];
    $rx_keywords = [
        'prescription', 'sig:', 'rx', 'dispense', 'refill', 'tablet',
        'capsule', 'mg', 'ml', 'take', 'once daily', 'twice daily',
        'morning', 'bedtime', 'amoxicillin', 'metformin', 'losartan'
    ];

    $lab_score = 0;
    $rx_score  = 0;

    foreach ($lab_keywords as $kw) {
        if (str_contains($text_lower, $kw)) $lab_score++;
    }
    foreach ($rx_keywords as $kw) {
        if (str_contains($text_lower, $kw)) $rx_score++;
    }

    if ($lab_score > $rx_score)  return 'lab_result';
    if ($rx_score  > $lab_score) return 'prescription';
    return 'unknown';
}