<?php
require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../config/http.php';
require_once __DIR__ . '/../config/session.php';

function registerUser(string $email, string $password, string $fullName = '', string $username = ''): array {
    $url = 'https://identitytoolkit.googleapis.com/v1/accounts:signUp?key=' . FIREBASE_API_KEY;

    $result = firebase_request('POST', $url, [
        'email'             => $email,
        'password'          => $password,
        'returnSecureToken' => true,
    ]);

    if (isset($result['idToken'])) {
        // Store token in session
        start_secure_session();
        regenerate_session();
        $_SESSION['uid']     = $result['localId'];
        $_SESSION['token']   = $result['idToken'];
        $_SESSION['email']   = $result['email'];
        $_SESSION['full_name'] = $fullName;
        $_SESSION['username'] = $username;
        return ['success' => true, 'uid' => $result['localId']];
    }

    return ['success' => false, 'error' => $result['error']['message'] ?? 'Registration failed'];
}

function safeRegistrationError(string $firebaseError): string {
    $map = [
        'EMAIL_EXISTS' => 'That email is already registered. Try signing in instead.',
        'OPERATION_NOT_ALLOWED' => 'Email/password signup is disabled in Firebase Authentication.',
        'CONFIGURATION_NOT_FOUND' => 'Firebase Authentication is not configured for this project.',
        'INVALID_EMAIL' => 'Enter a valid email address.',
        'WEAK_PASSWORD' => 'Password is too weak. Use at least 8 characters.',
        'TOO_MANY_ATTEMPTS_TRY_LATER' => 'Too many attempts. Try again later.',
        'Firebase request failed' => 'Could not reach Firebase. Check your internet connection and Firebase settings.',
        'No available HTTP transport for Firebase' => 'PHP cannot make HTTPS requests. Enable curl or allow_url_fopen.',
    ];

    return $map[$firebaseError] ?? 'Could not create the account. Try a different email or password.';
}

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    start_secure_session();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['success' => false, 'error' => 'Method not allowed.'], 405);
    }

    $input = read_json_body();
    $fullName = trim((string)($input['full_name'] ?? ''));
    $username = trim((string)($input['username'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    $password = (string)($input['password'] ?? '');
    $confirmPassword = (string)($input['confirm_password'] ?? '');

    if (!csrf_token_is_valid($input['csrf_token'] ?? null)) {
        json_response(['success' => false, 'error' => 'Security check failed. Refresh and try again.'], 403);
    }

    if ($fullName === '' || strlen($fullName) > 80) {
        json_response(['success' => false, 'error' => 'Enter a valid full name.'], 422);
    }

    if (!preg_match('/^[a-zA-Z0-9_.-]{3,30}$/', $username)) {
        json_response(['success' => false, 'error' => 'Username must be 3-30 letters, numbers, dots, dashes, or underscores.'], 422);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(['success' => false, 'error' => 'Enter a valid email address.'], 422);
    }

    if (strlen($password) < 8) {
        json_response(['success' => false, 'error' => 'Password must be at least 8 characters.'], 422);
    }

    if ($password !== $confirmPassword) {
        json_response(['success' => false, 'error' => 'Passwords do not match.'], 422);
    }

    $result = registerUser($email, $password, $fullName, $username);

    if (!$result['success']) {
        json_response(['success' => false, 'error' => safeRegistrationError($result['error'] ?? '')], 400);
    }

    json_response(['success' => true, 'redirect' => 'landing/landing.html']);
}
