<?php
// config/firebase.php

$env = parse_ini_file(__DIR__ . '/../.env');

define('FIREBASE_PROJECT_ID',     $env['FIREBASE_PROJECT_ID']);
define('FIREBASE_API_KEY',        $env['FIREBASE_API_KEY']);
define('FIREBASE_STORAGE_BUCKET', $env['FIREBASE_STORAGE_BUCKET']);

/**
 * Generic Firebase REST API caller
 */
function firebase_request(string $method, string $url, array $data = [], string $token = ''): array {
    $ch = curl_init($url);

    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $data ? json_encode($data) : null,
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true) ?? [];
}