<?php
// api/example-protected.php
// Example protected API endpoint showing how to use auth middleware
// DELETE THIS FILE - it's just for reference

require_once __DIR__ . '/../config/auth-middleware.php';

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        auth_error('Method not allowed', 405);
    }

    // Require authentication
    $user = require_auth();

    // Now you can safely use:
    // $user['uid']      - User's Firebase UID
    // $user['token']    - User's Firebase token
    // $user['email']    - User's email
    // $user['profile']  - Full user profile from Firestore

    auth_success([
        'message' => 'You are authenticated!',
        'user' => [
            'uid' => $user['uid'],
            'email' => $user['email'],
            'fullName' => $user['profile']['fullName'] ?? '',
            'username' => $user['profile']['username'] ?? '',
        ]
    ]);
}
