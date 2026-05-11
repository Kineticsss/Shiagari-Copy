<?php
// config/auth-middleware.php
// Authentication middleware for API endpoints

require_once __DIR__ . '/firebase-firestore.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/http.php';
require_once __DIR__ . '/../api/user-profile.php';

/**
 * Verify user is authenticated and get their details
 * Returns ['uid' => string, 'token' => string, 'email' => string, 'profile' => array]
 */
function require_auth(): array {
    start_secure_session();

    $uid = $_SESSION['uid'] ?? null;
    $token = $_SESSION['token'] ?? null;

    if (!$uid || !$token) {
        error_log('Authentication failed: Missing UID or token in session');
        json_response([
            'success' => false,
            'error' => 'Not authenticated. Please log in first.'
        ], 401);
    }

    // Verify token with Firebase
    $verification = verify_firebase_token($token);
    if (!$verification['success']) {
        error_log('Token verification failed: ' . ($verification['error'] ?? 'Unknown error'));
        json_response([
            'success' => false,
            'error' => 'Invalid or expired session. Please log in again.'
        ], 401);
    }

    // Get user profile
    $profileResult = get_user_profile($uid, $token);
    if (!$profileResult['success']) {
        error_log('User profile not found for UID: ' . $uid . ' - Error: ' . ($profileResult['error'] ?? 'Unknown error'));
        json_response([
            'success' => false,
            'error' => 'User profile not found.'
        ], 401);
    }

    return [
        'uid' => $uid,
        'token' => $token,
        'email' => $verification['email'] ?? $_SESSION['email'] ?? '',
        'profile' => $profileResult['profile'] ?? []
    ];
}

/**
 * Verify user owns the resource (by uid)
 */
function verify_resource_owner(string $resourceUid, string $currentUid): bool {
    return $resourceUid === $currentUid;
}

/**
 * Verify CSRF token
 */
function verify_csrf(array $input): bool {
    return csrf_token_is_valid($input['csrf_token'] ?? null);
}

/**
 * Require both auth and CSRF validation
 */
function require_auth_and_csrf(array $input): array {
    if (!verify_csrf($input)) {
        json_response([
            'success' => false,
            'error' => 'Security check failed. Refresh and try again.'
        ], 403);
    }

    return require_auth();
}

/**
 * Create a standardized error response
 */
function auth_error(string $message, int $code = 400): void {
    json_response([
        'success' => false,
        'error' => $message
    ], $code);
}

/**
 * Create a standardized success response
 */
function auth_success(array $data = [], int $code = 200): void {
    json_response(array_merge(['success' => true], $data), $code);
}
