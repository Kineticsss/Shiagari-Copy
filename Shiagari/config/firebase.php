<?php
// config/firebase.php

$env = parse_ini_file(__DIR__ . '/../.env') ?: [];

define('FIREBASE_PROJECT_ID', $env['FIREBASE_PROJECT_ID'] ?? '');
define('FIREBASE_API_KEY', $env['FIREBASE_API_KEY'] ?? '');
define('FIREBASE_STORAGE_BUCKET', $env['FIREBASE_STORAGE_BUCKET'] ?? '');

/**
 * Generic Firebase REST API caller
 */
function firebase_request(string $method, string $url, array $data = [], string $token = ''): array {
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $body = $data ? json_encode($data) : '';

    if (function_exists('curl_init')) {
        return firebase_curl_request($method, $url, $headers, $body);
    }

    return firebase_stream_request($method, $url, $headers, $body);
}

function firebase_curl_request(string $method, string $url, array $headers, string $body): array {
    $ch = curl_init($url);
    if ($ch === false) {
        return ['error' => ['message' => 'Firebase connection could not start']];
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $body !== '' ? $body : null,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 15,
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['error' => ['message' => $curlError ?: 'Firebase request failed']];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return ['error' => ['message' => 'Invalid Firebase response'], 'status' => $statusCode];
    }

    $decoded['status'] = $statusCode;

    return $decoded;
}

function firebase_stream_request(string $method, string $url, array $headers, string $body): array {
    if (!ini_get('allow_url_fopen')) {
        return ['error' => ['message' => 'No available HTTP transport for Firebase']];
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'ignore_errors' => true,
            'timeout' => 15,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return ['error' => ['message' => 'Firebase request failed']];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return ['error' => ['message' => 'Invalid Firebase response']];
    }

    $decoded['status'] = firebase_stream_status_code($http_response_header ?? []);

    return $decoded;
}

function firebase_stream_status_code(array $headers): int {
    if (!isset($headers[0]) || !preg_match('/\s(\d{3})\s/', $headers[0], $matches)) {
        return 0;
    }

    return (int) $matches[1];
}
