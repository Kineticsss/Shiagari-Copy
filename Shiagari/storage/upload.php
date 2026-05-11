<?php
// storage/upload.php
// Authenticated file upload → Firebase Storage → saves photoURL to Firestore user profile

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../config/firebase-firestore.php';
require_once __DIR__ . '/../config/http.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../api/user-profile.php';

// ── Constants ─────────────────────────────────────────────────────────────────
const MAX_FILE_BYTES   = 5 * 1024 * 1024; // 5 MB
const ALLOWED_MIME     = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
const ALLOWED_EXT      = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Upload a local file to Firebase Storage and return its public URL.
 */
function upload_to_firebase_storage(string $localPath, string $storagePath, string $mimeType, string $idToken): array {
    $bucket  = FIREBASE_STORAGE_BUCKET;
    $encoded = rawurlencode($storagePath);
    $url     = "https://firebasestorage.googleapis.com/v0/b/{$bucket}/o?uploadType=media&name={$encoded}";

    $fileData = file_get_contents($localPath);
    if ($fileData === false) {
        return ['success' => false, 'error' => 'Could not read uploaded file'];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => $fileData,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $idToken,
            'Content-Type: ' . $mimeType,
        ],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
    ]);

    $response   = curl_exec($ch);
    $curlError  = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'error' => $curlError ?: 'Upload request failed'];
    }

    $decoded = json_decode($response, true);
    if (!isset($decoded['name'])) {
        $msg = $decoded['error']['message'] ?? 'Firebase Storage upload failed';
        return ['success' => false, 'error' => $msg];
    }

    // Build the public download URL (requires Storage rules to allow reads, or use the token)
    $publicUrl = "https://firebasestorage.googleapis.com/v0/b/{$bucket}/o/{$encoded}?alt=media";
    return ['success' => true, 'url' => $publicUrl];
}

// ── Main handler ──────────────────────────────────────────────────────────────

start_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Method not allowed'], 405);
}

// Auth check
$uid   = $_SESSION['uid']   ?? null;
$token = $_SESSION['token'] ?? null;

if (!$uid || !$token) {
    json_response(['success' => false, 'error' => 'Not authenticated'], 401);
}

// CSRF check
$csrfFromHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$csrfFromPost   = $_POST['csrf_token']          ?? '';
$csrfCandidate  = $csrfFromHeader ?: $csrfFromPost;

if (!csrf_token_is_valid($csrfCandidate)) {
    json_response(['success' => false, 'error' => 'Security check failed. Refresh and try again.'], 403);
}

// File presence check
if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    $uploadError = $_FILES['avatar']['error'] ?? -1;
    $errorMap = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form size limit.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server is missing a temp folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload.',
    ];
    $msg = $errorMap[$uploadError] ?? 'Unknown upload error.';
    json_response(['success' => false, 'error' => $msg], 400);
}

$file     = $_FILES['avatar'];
$tmpPath  = $file['tmp_name'];
$origName = basename($file['name']);
$fileSize = $file['size'];

// Size validation
if ($fileSize > MAX_FILE_BYTES) {
    json_response(['success' => false, 'error' => 'File too large. Maximum size is 5 MB.'], 400);
}

// MIME type validation — use finfo, not the client-supplied type
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($tmpPath);

if (!in_array($mimeType, ALLOWED_MIME, true)) {
    json_response(['success' => false, 'error' => 'Invalid file type. Only JPEG, PNG, WebP, and GIF are allowed.'], 400);
}

// Extension validation
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if (!in_array($ext, ALLOWED_EXT, true)) {
    // Derive extension from MIME as fallback
    $mimeToExt = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    $ext = $mimeToExt[$mimeType] ?? 'jpg';
}

// Build a unique storage path per user (overwrites previous avatar)
$storagePath = "avatars/{$uid}/avatar.{$ext}";

// Upload to Firebase Storage
$uploadResult = upload_to_firebase_storage($tmpPath, $storagePath, $mimeType, $token);

if (!$uploadResult['success']) {
    json_response(['success' => false, 'error' => $uploadResult['error']], 500);
}

$photoURL = $uploadResult['url'];

// Persist photoURL on the Firestore user profile
$updateResult = update_user_profile($uid, ['photoURL' => $photoURL], $token);

if (!$updateResult['success']) {
    // Upload succeeded but profile update failed — still return the URL so the UI can cache it
    json_response([
        'success'  => true,
        'photoURL' => $photoURL,
        'warning'  => 'Photo uploaded but profile could not be updated: ' . ($updateResult['error'] ?? 'unknown'),
    ]);
}

json_response(['success' => true, 'photoURL' => $photoURL]);