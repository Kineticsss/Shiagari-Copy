<?php
// api/user-search.php
// Search for existing users (safe, non-sensitive)

require_once __DIR__ . '/../config/auth-middleware.php';

/**
 * Case-insensitive search helper.
 * Firestore structured queries here use EQUAL, so we normalize input.
 * Assumption: user-profile POST stores email/username in original form.
 * To support case-insensitive lookup, we query by normalized fields only.
 */
function normalize_email(string $email): string {
    return mb_strtolower(trim($email));
}

function normalize_username(string $username): string {
    return mb_strtolower(trim($username));
}

/**
 * Search users by normalized email/username/full name.
 * - excludes current user
 * - returns: uid, fullName, email, username
 */
function search_users(string $query, string $uid, string $idToken): array {
    $q = trim($query);
    if ($q === '') {
        return ['success' => true, 'users' => []];
    }

    // Strategy:
    // 1) exact normalized match for email
    // 2) exact normalized match for username
    // 3) fallback: scan a limited set and do client/server-side case-insensitive contains
    //    Because project uses simple Firestore helpers without OR/contains filters.

    $emailN = normalize_email($q);
    $usernameN = normalize_username($q);

    $candidates = [];

    // Try email (using normalized field)
    $emailRes = firestore_query('users', ['emailNorm' => $emailN], 5, $idToken);
    if ($emailRes['success'] && !empty($emailRes['data'])) {
        $candidates = array_merge($candidates, $emailRes['data']);
    }

    // Try username (using normalized field)
    $unameRes = firestore_query('users', ['usernameNorm' => $usernameN], 5, $idToken);
    if ($unameRes['success'] && !empty($unameRes['data'])) {
        $candidates = array_merge($candidates, $unameRes['data']);
    }

    // If still empty, fetch a limited number of users and do contains match.
    if (empty($candidates)) {
        $allRes = firestore_query('users', [], 50, $idToken);
        if (!$allRes['success']) {
            return ['success' => false, 'error' => 'Failed to search users'];
        }

        $qLower = mb_strtolower($q);
        foreach ($allRes['data'] as $u) {
            $userUid = $u['uid'] ?? '';
            if (!$userUid || $userUid === $uid) continue;

            $email = mb_strtolower((string)($u['email'] ?? ''));
            $username = mb_strtolower((string)($u['username'] ?? ''));
            $fullName = mb_strtolower((string)($u['fullName'] ?? ''));

            $hit =
                ($email !== '' && str_contains($email, $qLower)) ||
                ($username !== '' && str_contains($username, $qLower)) ||
                ($fullName !== '' && str_contains($fullName, $qLower));

            if ($hit) $candidates[] = $u;
        }
    }

    // Deduplicate by uid and shape response.
    $seen = [];
    $users = [];
    foreach ($candidates as $u) {
        $userUid = $u['uid'] ?? ($u['id'] ?? '');
        if (!$userUid || $userUid === $uid) continue;
        if (isset($seen[$userUid])) continue;
        $seen[$userUid] = true;

        $users[] = [
            'uid' => $userUid,
            'displayName' => $u['fullName'] ?? ($u['displayName'] ?? $u['email'] ?? $userUid),
            'email' => $u['email'] ?? '',
        ];
    }

    // Sort by displayName
    usort($users, fn($a, $b) => strcmp((string)$a['displayName'], (string)$b['displayName']));

    return ['success' => true, 'users' => $users];
}

// Handle API requests
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method !== 'GET') {
        json_response(['success' => false, 'error' => 'Method not allowed'], 405);
    }

    $user = require_auth();

    $q = $_GET['q'] ?? '';

    $result = search_users((string)$q, $user['uid'], $user['token']);
    json_response($result, $result['success'] ? 200 : 400);
}

json_response(['success' => false, 'error' => 'Method not allowed'], 405);

