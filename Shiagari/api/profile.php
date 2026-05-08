<?php
/**
 * SHIAGARI User Profile API
 * 
 * Provides secure retrieval of authenticated user profile data from database.
 * All requests must include valid Firebase authentication token.
 */

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/http.php';

/**
 * Get authenticated user's profile from database
 * 
 * @return array|false User profile data or false if not authenticated
 */
function getUserProfile() {
    // Verify user is authenticated
    if (empty($_SESSION['uid']) || empty($_SESSION['user_id'])) {
        return false;
    }
    
    // Fetch from database
    $user = getRowDB(
        'SELECT id, firebase_uid, email, full_name, username, role, created_at, updated_at, last_login 
         FROM users WHERE id = ? AND firebase_uid = ? LIMIT 1',
        [$_SESSION['user_id'], $_SESSION['uid']]
    );
    
    if (!$user) {
        return false;
    }
    
    // Format for frontend
    return [
        'id' => (int)$user['id'],
        'email' => $user['email'],
        'full_name' => $user['full_name'],
        'username' => $user['username'],
        'role' => $user['role'],
        'member_since' => date('F Y', strtotime($user['created_at'])),
        'last_login' => $user['last_login'],
        'joined_at' => $user['created_at'],
    ];
}

// Handle API request
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    start_secure_session();

    // Support returning CSRF token for frontend initialization
    $action = $_GET['action'] ?? '';
    if ($action === 'csrf') {
        // Return or create CSRF token (no authentication required)
        json_response(['success' => true, 'csrf_token' => csrf_token()]);
    }

    // Only allow GET requests for profile
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        json_response(['success' => false, 'error' => 'Method not allowed'], 405);
    }

    // Check if user is authenticated
    if (empty($_SESSION['uid']) || empty($_SESSION['user_id'])) {
        json_response(['success' => false, 'error' => 'Not authenticated'], 401);
    }

    // Get and return user profile
    $profile = getUserProfile();

    if ($profile === false) {
        json_response(['success' => false, 'error' => 'User profile not found'], 404);
    }

    // Return under 'user' key for frontend compatibility and include CSRF token
    json_response(['success' => true, 'user' => $profile, 'csrf_token' => csrf_token()]);
}

?>
