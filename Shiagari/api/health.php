<?php
/**
 * SHIAGARI Database Health Check API
 * 
 * Verifies database connectivity and user data integrity.
 * Useful for debugging authentication and data sync issues.
 */

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/http.php';

/**
 * Check database connection status
 * 
 * @return array Status information
 */
function checkDatabaseHealth() {
    $health = [
        'connected' => false,
        'tables' => [],
        'errors' => []
    ];
    
    if (!isDatabaseConnected()) {
        $health['errors'][] = 'Cannot connect to database. Check credentials in config/database.php';
        return $health;
    }
    
    $health['connected'] = true;
    
    // Check required tables
    $requiredTables = ['users', 'projects', 'project_members', 'posts', 'post_comments'];
    
    try {
        foreach ($requiredTables as $table) {
            $result = queryDB("SHOW TABLES LIKE ?", [$table]);
            $health['tables'][$table] = is_array($result) && count($result) > 0 ? 'exists' : 'missing';
        }
    } catch (Exception $e) {
        $health['errors'][] = 'Error checking tables: ' . $e->getMessage();
    }
    
    return $health;
}

/**
 * Get user data integrity check
 * 
 * @param string $uid Firebase UID
 * 
 * @return array User integrity information
 */
function checkUserIntegrity(string $uid) {
    $check = [
        'firebase_uid' => $uid,
        'in_database' => false,
        'email' => null,
        'full_name' => null,
        'username' => null,
        'role' => null,
        'issues' => []
    ];
    
    $user = getUserByFirebaseUID($uid);
    
    if (!$user) {
        $check['issues'][] = 'User not found in database. Try logging in again to sync credentials.';
        return $check;
    }
    
    $check['in_database'] = true;
    $check['email'] = $user['email'];
    $check['full_name'] = $user['full_name'];
    $check['username'] = $user['username'];
    $check['role'] = $user['role'];
    
    // Validate data integrity
    if (!$check['email']) {
        $check['issues'][] = 'Email is missing';
    }
    if (!$check['full_name']) {
        $check['issues'][] = 'Full name is missing';
    }
    if (!$check['username']) {
        $check['issues'][] = 'Username is missing';
    }
    if (!$check['role']) {
        $check['issues'][] = 'Role is missing';
    }
    
    return $check;
}

// Handle API requests
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    start_secure_session();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        json_response(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    // Get action parameter
    $action = $_GET['action'] ?? 'health';
    
    if ($action === 'health') {
        // Check database health (no auth required)
        $health = checkDatabaseHealth();
        json_response(['success' => true, 'data' => $health]);
    } else if ($action === 'user') {
        // Check user integrity (auth required)
        if (empty($_SESSION['uid'])) {
            json_response(['success' => false, 'error' => 'Not authenticated'], 401);
        }
        
        $check = checkUserIntegrity($_SESSION['uid']);
        json_response(['success' => true, 'data' => $check]);
    } else {
        json_response(['success' => false, 'error' => 'Unknown action'], 400);
    }
}

?>
