<?php
// api/test.php
// Simple test endpoint to verify API is working

header('Content-Type: application/json');

// Test 1: Check if session is working
session_start();
$sessionTest = [
    'session_id' => session_id(),
    'uid' => $_SESSION['uid'] ?? 'NOT SET',
    'token' => $_SESSION['token'] ? 'SET (length: ' . strlen($_SESSION['token']) . ')' : 'NOT SET',
];

// Test 2: Check if required files exist
$filesTest = [
    'auth-middleware' => file_exists(__DIR__ . '/../config/auth-middleware.php'),
    'firebase-firestore' => file_exists(__DIR__ . '/../config/firebase-firestore.php'),
    'http' => file_exists(__DIR__ . '/../config/http.php'),
    'session' => file_exists(__DIR__ . '/../config/session.php'),
];

// Test 3: Try to require auth (without dying)
$authTest = ['attempted' => false, 'error' => null];
try {
    if (file_exists(__DIR__ . '/../config/auth-middleware.php')) {
        require_once __DIR__ . '/../config/auth-middleware.php';
        $authTest['attempted'] = true;
        // Try to get auth without the json_response dying
        if (function_exists('require_auth')) {
            $user = require_auth();
            $authTest['success'] = true;
            $authTest['uid'] = $user['uid'] ?? 'unknown';
        }
    }
} catch (Throwable $e) {
    $authTest['error'] = $e->getMessage();
}

json_encode([
    'status' => 'API Test',
    'session' => $sessionTest,
    'files' => $filesTest,
    'auth' => $authTest,
    'timestamp' => date('c'),
]);

echo json_encode([
    'status' => 'API Test',
    'session' => $sessionTest,
    'files' => $filesTest,
    'auth' => $authTest,
    'timestamp' => date('c'),
]);
?>
