<?php
require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../config/firebase-firestore.php';
require_once __DIR__ . '/../config/http.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../api/user-profile.php';

function loginUser(string $email, string $password): array {
    $url = 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key=' . FIREBASE_API_KEY;

    $result = firebase_request('POST', $url, [
        'email'             => $email,
        'password'          => $password,
        'returnSecureToken' => true,
    ]);

    if (isset($result['idToken'])) {
        $uid = $result['localId'];
        $token = $result['idToken'];
        
        start_secure_session();
        regenerate_session();
        $_SESSION['uid']   = $uid;
        $_SESSION['token'] = $token;
        $_SESSION['email'] = $result['email'];

        // Get user profile from Firestore to load full details
        $profileResult = get_user_profile($uid, $token);
        if ($profileResult['success']) {
            $_SESSION['full_name'] = $profileResult['profile']['fullName'] ?? '';
            $_SESSION['username'] = $profileResult['profile']['username'] ?? '';
        }

        return ['success' => true];
    }

    return ['success' => false, 'error' => $result['error']['message'] ?? 'Login failed'];
}

function safeLoginError(string $firebaseError): string {
    $setupErrors = [
        'OPERATION_NOT_ALLOWED' => 'Email/password login is disabled in Firebase Authentication.',
        'CONFIGURATION_NOT_FOUND' => 'Firebase Authentication is not configured for this project.',
        'Firebase request failed' => 'Could not reach Firebase. Check your internet connection and Firebase settings.',
        'No available HTTP transport for Firebase' => 'PHP cannot make HTTPS requests. Enable curl or allow_url_fopen.',
    ];

    return $setupErrors[$firebaseError] ?? 'Invalid email or password.';
}

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    start_secure_session();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['success' => false, 'error' => 'Method not allowed.'], 405);
    }

    $input = read_json_body();
    $email = trim((string)($input['email'] ?? ''));
    $password = (string)($input['password'] ?? '');

    if (!csrf_token_is_valid($input['csrf_token'] ?? null)) {
        json_response(['success' => false, 'error' => 'Security check failed. Refresh and try again.'], 403);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        json_response(['success' => false, 'error' => 'Enter a valid email and password.'], 422);
    }

    $result = loginUser($email, $password);

    if (!$result['success']) {
        json_response(['success' => false, 'error' => safeLoginError($result['error'] ?? '')], 401);
    }

    json_response(['success' => true, 'redirect' => 'landing/landing.php']);
}
