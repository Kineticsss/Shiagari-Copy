<?php
require_once '../config/firebase.php';

function uploadFile(string $localFilePath, string $destinationName, string $idToken): array {
    $bucket   = FIREBASE_STORAGE_BUCKET;
    $encoded  = rawurlencode('uploads/' . $destinationName);
    $url      = "https://firebasestorage.googleapis.com/v0/b/{$bucket}/o?uploadType=media&name={$encoded}";

    $fileData = file_get_contents($localFilePath);
    $mimeType = mime_content_type($localFilePath);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => $fileData,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $idToken,
            'Content-Type: ' . $mimeType,
        ],
    ]);

    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (isset($response['name'])) {
        $publicUrl = "https://firebasestorage.googleapis.com/v0/b/{$bucket}/o/{$encoded}?alt=media";
        return ['success' => true, 'url' => $publicUrl];
    }

    return ['success' => false, 'error' => $response['error']['message'] ?? 'Upload failed'];
}