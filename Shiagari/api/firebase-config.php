<?php
/**
 * SHIAGARI Firebase Config API
 *
 * Returns Firebase client-side configuration to authenticated users.
 * Only exposes public Firebase SDK keys (these are safe to expose to the browser).
 */

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../config/http.php';

start_secure_session();

// Only authenticated users may receive the config
if (empty($_SESSION['uid'])) {
    json_response(['error' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['error' => 'Method not allowed'], 405);
}

// Read additional config values from .env
$env = parse_ini_file(__DIR__ . '/../.env') ?: [];

$config = [
    'apiKey'            => FIREBASE_API_KEY,
    'authDomain'        => ($env['FIREBASE_AUTH_DOMAIN']    ?? FIREBASE_PROJECT_ID . '.firebaseapp.com'),
    'databaseURL'       => ($env['FIREBASE_DATABASE_URL']   ?? 'https://' . FIREBASE_PROJECT_ID . '-default-rtdb.firebaseio.com'),
    'projectId'         => FIREBASE_PROJECT_ID,
    'storageBucket'     => FIREBASE_STORAGE_BUCKET,
    'messagingSenderId' => ($env['FIREBASE_MESSAGING_SENDER_ID'] ?? ''),
    'appId'             => ($env['FIREBASE_APP_ID']         ?? ''),
];

// Return the config as JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode($config);
exit;