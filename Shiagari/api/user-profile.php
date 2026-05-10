<?php
require_once __DIR__ . '/../config/firebase-firestore.php';
require_once __DIR__ . '/../config/http.php';
require_once __DIR__ . '/../config/session.php';

/**
 * Create or update user profile in Firestore
 */
function save_user_profile(string $uid, string $email, string $fullName = '', string $username = '', string $idToken = ''): array {
    // Check if profile already exists so we don't overwrite createdAt
    $existing = firestore_get('users', $uid, $idToken);
    $createdAt = ($existing['success'] && !empty($existing['data']['createdAt']))
        ? $existing['data']['createdAt']
        : date('c');

    $userData = [
        'uid'       => $uid,
        'email'     => $email,
        'fullName'  => $fullName,
        'username'  => $username,
        'createdAt' => $createdAt,
        'updatedAt' => date('c'),
        'lastLogin' => date('c'),
    ];

    $result = firestore_set('users', $uid, $userData, $idToken);

    return $result;
}

/**
 * Get user profile from Firestore
 */
function get_user_profile(string $uid, string $idToken = ''): array {
    $result = firestore_get('users', $uid, $idToken);
    
    if ($result['success']) {
        return ['success' => true, 'profile' => $result['data']];
    }

    return ['success' => false, 'error' => 'User not found'];
}

/**
 * Find user by email
 */
function find_user_by_email(string $email, string $idToken = ''): array {
    $result = firestore_query('users', ['email' => $email], 1, $idToken);
    
    if ($result['success'] && count($result['data']) > 0) {
        return ['success' => true, 'profile' => $result['data'][0]];
    }

    return ['success' => false, 'error' => 'User not found'];
}

/**
 * Find user by username
 */
function find_user_by_username(string $username, string $idToken = ''): array {
    $result = firestore_query('users', ['username' => $username], 1, $idToken);
    
    if ($result['success'] && count($result['data']) > 0) {
        return ['success' => true, 'profile' => $result['data'][0]];
    }

    return ['success' => false, 'error' => 'User not found'];
}

/**
 * Update user profile
 */
function update_user_profile(string $uid, array $updates, string $idToken = ''): array {
    $updates['updatedAt'] = date('c');
    
    $result = firestore_update('users', $uid, $updates, $idToken);
    
    if ($result['success']) {
        return ['success' => true, 'profile' => $result['data']];
    }

    return $result;
}

// Handle API requests
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    start_secure_session();

    $method = $_SERVER['REQUEST_METHOD'];
    $input = read_json_body();

    // Get current user from session
    $currentUid = $_SESSION['uid'] ?? null;
    $currentToken = $_SESSION['token'] ?? null;

    // Route: GET /api/user-profile.php?uid=USER_ID - Get user profile
    if ($method === 'GET') {
        $uid = $_GET['uid'] ?? $currentUid;
        $idToken = $_GET['token'] ?? $currentToken;

        if (!$uid) {
            json_response(['success' => false, 'error' => 'No user specified'], 400);
        }

        if (!$idToken) {
            json_response(['success' => false, 'error' => 'Not authenticated'], 401);
        }

        $result = get_user_profile($uid, $idToken);
        json_response($result, $result['success'] ? 200 : 404);
    }

    // Route: POST /api/user-profile.php - Create/update user profile
    if ($method === 'POST') {
        if (!$currentUid || !$currentToken) {
            json_response(['success' => false, 'error' => 'Not authenticated'], 401);
        }

        $action = $input['action'] ?? 'create';

        if ($action === 'create') {
            $email = trim((string)($input['email'] ?? ''));
            $fullName = trim((string)($input['full_name'] ?? ''));
            $username = trim((string)($input['username'] ?? ''));

            // Validate
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                json_response(['success' => false, 'error' => 'Invalid email'], 422);
            }

            if (!$fullName || strlen($fullName) > 80) {
                json_response(['success' => false, 'error' => 'Invalid full name'], 422);
            }

            if (!preg_match('/^[a-zA-Z0-9_.-]{3,30}$/', $username)) {
                json_response(['success' => false, 'error' => 'Invalid username'], 422);
            }

            // Check if email or username already exists
            $existingEmail = find_user_by_email($email, $currentToken);
            if ($existingEmail['success'] && $existingEmail['profile']['uid'] !== $currentUid) {
                json_response(['success' => false, 'error' => 'Email already in use'], 409);
            }

            $existingUsername = find_user_by_username($username, $currentToken);
            if ($existingUsername['success'] && $existingUsername['profile']['uid'] !== $currentUid) {
                json_response(['success' => false, 'error' => 'Username already in use'], 409);
            }

            $result = save_user_profile($currentUid, $email, $fullName, $username, $currentToken);
            json_response($result, $result['success'] ? 201 : 400);
        }

        if ($action === 'update') {
            $updates = [];
            if (isset($input['full_name'])) {
                $updates['fullName'] = trim((string)$input['full_name']);
            }
            if (isset($input['username'])) {
                $updates['username'] = trim((string)$input['username']);
            }

            $result = update_user_profile($currentUid, $updates, $currentToken);
            json_response($result, $result['success'] ? 200 : 400);
        }

        json_response(['success' => false, 'error' => 'Invalid action'], 400);
    }

    json_response(['success' => false, 'error' => 'Method not allowed'], 405);
}
