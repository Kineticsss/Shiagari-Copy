<?php
/**
 * SHIAGARI Database Configuration & Connection Handler
 * 
 * Provides PDO database connection, query utilities, and user credential management.
 * Implements singleton pattern for database connections.
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'shiagari');
define('DB_USER', 'root');
define('DB_PASS', '');

/**
 * Database Connection Singleton
 * 
 * @return PDO|null Database connection or null if connection fails
 */
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo !== null) {
        return $pdo;
    }
    
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        // Log error but don't expose to user
        error_log('Database connection failed: ' . $e->getMessage());
        return null;
    }
}

/**
 * Execute a prepared statement query
 * 
 * @param string $sql SQL query with ? placeholders
 * @param array $params Parameters to bind to placeholders
 * 
 * @return array|bool Array of results or true for success, false on error
 */
function queryDB(string $sql, array $params = []) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
            // For SELECT queries, return all results
            if (stripos(trim($sql), 'SELECT') === 0) {
                return $stmt->fetchAll();
            }
            return true;
        }
        return false;
    } catch (PDOException $e) {
        error_log('Query error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get a single row from database
 * 
 * @param string $sql SQL query with ? placeholders
 * @param array $params Parameters to bind to placeholders
 * 
 * @return array|null Single row or null if not found
 */
function getRowDB(string $sql, array $params = []) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Query error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get the ID of the last inserted row
 * 
 * @return string Last insert ID
 */
function getLastInsertId() {
    $pdo = getDBConnection();
    return $pdo ? $pdo->lastInsertId() : '0';
}

/**
 * Begin database transaction
 * 
 * @return bool True if transaction started
 */
function beginTransaction() {
    $pdo = getDBConnection();
    if (!$pdo) {
        return false;
    }
    return $pdo->beginTransaction();
}

/**
 * Commit database transaction
 * 
 * @return bool True if transaction committed
 */
function commitTransaction() {
    $pdo = getDBConnection();
    if (!$pdo) {
        return false;
    }
    return $pdo->commit();
}

/**
 * Rollback database transaction
 * 
 * @return bool True if transaction rolled back
 */
function rollbackTransaction() {
    $pdo = getDBConnection();
    if (!$pdo) {
        return false;
    }
    return $pdo->rollBack();
}

/**
 * User Management Functions
 */

/**
 * Create or update user in database after Firebase authentication
 * 
 * @param string $uid Firebase UID
 * @param string $email User email
 * @param string $fullName User full name
 * @param string $username Username
 * 
 * @return array|bool User data if successful, false on error
 */
function syncUserToDatabase(string $uid, string $email, string $fullName = '', string $username = '') {
    $pdo = getDBConnection();
    if (!$pdo) {
        return false;
    }
    
    try {
        // Check if user exists
        $existing = getRowDB('SELECT id FROM users WHERE firebase_uid = ? LIMIT 1', [$uid]);
        
        if ($existing) {
            // Update existing user
            $sql = 'UPDATE users SET email = ?, full_name = ?, username = ?, updated_at = NOW() WHERE firebase_uid = ?';
            $pdo->prepare($sql)->execute([$email, $fullName, $username, $uid]);
        } else {
            // Create new user
            $sql = 'INSERT INTO users (firebase_uid, email, full_name, username, role, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())';
            $pdo->prepare($sql)->execute([$uid, $email, $fullName, $username, 'user']);
        }
        
        // Return user data
        return getUserByFirebaseUID($uid);
    } catch (PDOException $e) {
        error_log('User sync error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get user by Firebase UID
 * 
 * @param string $uid Firebase UID
 * 
 * @return array|null User data or null if not found
 */
function getUserByFirebaseUID(string $uid) {
    return getRowDB(
        'SELECT id, firebase_uid, email, full_name, username, role, created_at, updated_at FROM users WHERE firebase_uid = ? LIMIT 1',
        [$uid]
    );
}

/**
 * Get user by email
 * 
 * @param string $email User email
 * 
 * @return array|null User data or null if not found
 */
function getUserByEmail(string $email) {
    return getRowDB(
        'SELECT id, firebase_uid, email, full_name, username, role, created_at, updated_at FROM users WHERE email = ? LIMIT 1',
        [$email]
    );
}

/**
 * Get user by username
 * 
 * @param string $username Username
 * 
 * @return array|null User data or null if not found
 */
function getUserByUsername(string $username) {
    return getRowDB(
        'SELECT id, firebase_uid, email, full_name, username, role, created_at, updated_at FROM users WHERE username = ? LIMIT 1',
        [$username]
    );
}

/**
 * Check if email exists in database
 * 
 * @param string $email Email to check
 * 
 * @return bool True if email exists
 */
function emailExists(string $email) {
    $result = getRowDB('SELECT id FROM users WHERE email = ? LIMIT 1', [$email]);
    return $result !== null;
}

/**
 * Check if username exists in database
 * 
 * @param string $username Username to check
 * 
 * @return bool True if username exists
 */
function usernameExists(string $username) {
    $result = getRowDB('SELECT id FROM users WHERE username = ? LIMIT 1', [$username]);
    return $result !== null;
}

/**
 * Update user last login timestamp
 * 
 * @param string $uid Firebase UID
 * 
 * @return bool True if successful
 */
function updateUserLastLogin(string $uid) {
    return queryDB('UPDATE users SET last_login = NOW() WHERE firebase_uid = ?', [$uid]);
}

/**
 * Verify database connectivity
 * 
 * @return bool True if database is connected and operational
 */
function isDatabaseConnected() {
    $pdo = getDBConnection();
    if (!$pdo) {
        return false;
    }
    
    try {
        $pdo->query('SELECT 1');
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * PROJECT MANAGEMENT FUNCTIONS
 */

/**
 * Create a new project
 * 
 * @param int $owner_id User ID of project owner
 * @param string $name Project name
 * @param string $description Project description
 * @param string $status Project status (active, planning, hold, completed)
 * 
 * @return array|bool New project data or false on error
 */
function createProject(int $owner_id, string $name, string $description = '', string $status = 'active') {
    if (!$name || !trim($name)) {
        return false;
    }
    
    $uuid = bin2hex(random_bytes(18)); // 36-char UUID-like string
    
    $sql = 'INSERT INTO projects (uuid, name, description, status, owner_id, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())';
    
    if (queryDB($sql, [$uuid, $name, $description, $status, $owner_id])) {
        $project_id = getLastInsertId();
        
        // Add owner as project member with owner role
        queryDB(
            'INSERT INTO project_members (project_id, user_id, role, joined_at, created_at, updated_at) 
             VALUES (?, ?, ?, NOW(), NOW(), NOW())',
            [$project_id, $owner_id, 'owner']
        );
        
        return getProject($project_id);
    }
    
    return false;
}

/**
 * Get project by ID with owner and member information
 * 
 * @param int $project_id Project ID
 * 
 * @return array|null Project data or null if not found
 */
function getProject(int $project_id) {
    $sql = 'SELECT p.*, u.id as owner_id, u.full_name as owner_name, u.username as owner_username, u.email as owner_email
            FROM projects p
            LEFT JOIN users u ON p.owner_id = u.id
            WHERE p.id = ? LIMIT 1';
    
    return getRowDB($sql, [$project_id]);
}

/**
 * Get all projects for a user (owned or member of)
 * 
 * @param int $user_id User ID
 * @param int $limit Results limit
 * @param int $offset Results offset for pagination
 * 
 * @return array List of projects
 */
function getUserProjects(int $user_id, int $limit = 50, int $offset = 0) {
    $sql = 'SELECT DISTINCT p.*, u.full_name as owner_name, u.username as owner_username
            FROM projects p
            LEFT JOIN users u ON p.owner_id = u.id
            WHERE p.owner_id = ? OR p.id IN (
                SELECT project_id FROM project_members WHERE user_id = ?
            )
            ORDER BY p.updated_at DESC
            LIMIT ? OFFSET ?';
    
    return queryDB($sql, [$user_id, $user_id, $limit, $offset]);
}

/**
 * Update project details
 * 
 * @param int $project_id Project ID
 * @param string $name Project name
 * @param string $description Project description
 * @param string $status Project status
 * 
 * @return bool True if successful
 */
function updateProject(int $project_id, string $name, string $description = '', string $status = '') {
    $updates = [];
    $params = [];
    
    if ($name) {
        $updates[] = 'name = ?';
        $params[] = $name;
    }
    if ($description !== '') {
        $updates[] = 'description = ?';
        $params[] = $description;
    }
    if ($status && in_array($status, ['active', 'planning', 'hold', 'completed'])) {
        $updates[] = 'status = ?';
        $params[] = $status;
    }
    
    if (empty($updates)) {
        return false;
    }
    
    $updates[] = 'updated_at = NOW()';
    $params[] = $project_id;
    
    $sql = 'UPDATE projects SET ' . implode(', ', $updates) . ' WHERE id = ?';
    return queryDB($sql, $params);
}

/**
 * Delete a project
 * 
 * @param int $project_id Project ID
 * 
 * @return bool True if successful
 */
function deleteProject(int $project_id) {
    return queryDB('DELETE FROM projects WHERE id = ?', [$project_id]);
}

/**
 * Get project members
 * 
 * @param int $project_id Project ID
 * 
 * @return array List of project members with user details
 */
function getProjectMembers(int $project_id) {
    $sql = 'SELECT pm.*, u.id, u.email, u.full_name, u.username, u.created_at as user_created_at
            FROM project_members pm
            JOIN users u ON pm.user_id = u.id
            WHERE pm.project_id = ?
            ORDER BY pm.role DESC, pm.joined_at DESC';
    
    return queryDB($sql, [$project_id]);
}

/**
 * INVITATION MANAGEMENT FUNCTIONS
 */

/**
 * Search users by email or username
 * 
 * @param string $query Search query (email or username)
 * @param int $limit Max results
 * 
 * @return array List of matching users
 */
function searchUsers(string $query, int $limit = 10) {
    if (strlen($query) < 2) {
        return [];
    }
    
    $search = '%' . $query . '%';
    
    $sql = 'SELECT id, email, full_name, username 
            FROM users 
            WHERE email LIKE ? OR username LIKE ? OR full_name LIKE ?
            LIMIT ?';
    
    return queryDB($sql, [$search, $search, $search, $limit]);
}

/**
 * Send project invitation
 * 
 * @param int $project_id Project ID
 * @param int $inviter_id User ID who is inviting
 * @param int|null $invitee_id User ID of invitee (null if email-only)
 * @param string $invitee_email Email of invitee
 * @param string $role Role to assign (editor or viewer)
 * 
 * @return array|bool Invitation data or false on error
 */
function sendProjectInvitation(int $project_id, int $inviter_id, ?int $invitee_id, string $invitee_email, string $role = 'editor') {
    if (!in_array($role, ['editor', 'viewer'])) {
        $role = 'editor';
    }
    
    // Check if user is already a member
    if ($invitee_id) {
        $existing = getRowDB(
            'SELECT id FROM project_members WHERE project_id = ? AND user_id = ? LIMIT 1',
            [$project_id, $invitee_id]
        );
        if ($existing) {
            return false; // Already a member
        }
    }
    
    // Check for duplicate pending invitation
    $pending = getRowDB(
        'SELECT id FROM project_invitations 
         WHERE project_id = ? AND (invitee_id = ? OR invitee_email = ?) AND status = ? LIMIT 1',
        [$project_id, $invitee_id, $invitee_email, 'pending']
    );
    if ($pending) {
        return false; // Pending invitation already exists
    }
    
    // Generate unique token
    $token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    $sql = 'INSERT INTO project_invitations 
            (project_id, inviter_id, invitee_id, invitee_email, role, status, token, expires_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())';
    
    if (queryDB($sql, [$project_id, $inviter_id, $invitee_id, $invitee_email, $role, 'pending', $token, $expires_at])) {
        $invitation_id = getLastInsertId();
        return getProjectInvitation($invitation_id);
    }
    
    return false;
}

/**
 * Get single invitation
 * 
 * @param int $invitation_id Invitation ID
 * 
 * @return array|null Invitation data or null if not found
 */
function getProjectInvitation(int $invitation_id) {
    $sql = 'SELECT pi.*, 
                   inv.full_name as inviter_name, inv.username as inviter_username,
                   invt.full_name as invitee_name, invt.username as invitee_username,
                   p.name as project_name
            FROM project_invitations pi
            LEFT JOIN users inv ON pi.inviter_id = inv.id
            LEFT JOIN users invt ON pi.invitee_id = invt.id
            LEFT JOIN projects p ON pi.project_id = p.id
            WHERE pi.id = ? LIMIT 1';
    
    return getRowDB($sql, [$invitation_id]);
}

/**
 * Get pending invitations for a user
 * 
 * @param int $user_id User ID
 * 
 * @return array List of pending invitations
 */
function getPendingInvitations(int $user_id) {
    $sql = 'SELECT pi.*, 
                   inv.full_name as inviter_name, inv.username as inviter_username, inv.email as inviter_email,
                   p.name as project_name, p.description as project_description, p.status as project_status,
                   po.full_name as project_owner_name, po.username as project_owner_username
            FROM project_invitations pi
            JOIN users inv ON pi.inviter_id = inv.id
            JOIN projects p ON pi.project_id = p.id
            JOIN users po ON p.owner_id = po.id
            WHERE (pi.invitee_id = ? OR pi.invitee_email = (SELECT email FROM users WHERE id = ?))
            AND pi.status = ?
            AND pi.expires_at > NOW()
            ORDER BY pi.created_at DESC';
    
    return queryDB($sql, [$user_id, $user_id, 'pending']);
}

/**
 * Accept project invitation
 * 
 * @param int $invitation_id Invitation ID
 * @param int $user_id User ID accepting the invitation
 * 
 * @return bool True if successful
 */
function acceptInvitation(int $invitation_id, int $user_id) {
    $invitation = getProjectInvitation($invitation_id);
    
    if (!$invitation) {
        return false;
    }
    
    // Verify user is the invitee
    if ($invitation['invitee_id'] != $user_id && $invitation['invitee_email'] != getUserEmail($user_id)) {
        return false;
    }
    
    // Check invitation is still valid
    if ($invitation['status'] != 'pending' || strtotime($invitation['expires_at']) < time()) {
        return false;
    }
    
    // Start transaction
    beginTransaction();
    
    try {
        // Add user to project members
        queryDB(
            'INSERT INTO project_members (project_id, user_id, role, joined_at, created_at, updated_at)
             VALUES (?, ?, ?, NOW(), NOW(), NOW())',
            [$invitation['project_id'], $user_id, $invitation['role']]
        );
        
        // Update invitation status
        queryDB(
            'UPDATE project_invitations SET status = ?, responded_at = NOW(), updated_at = NOW() WHERE id = ?',
            ['accepted', $invitation_id]
        );
        
        commitTransaction();
        return true;
    } catch (Exception $e) {
        rollbackTransaction();
        error_log('Accept invitation error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Decline project invitation
 * 
 * @param int $invitation_id Invitation ID
 * @param int $user_id User ID declining the invitation
 * 
 * @return bool True if successful
 */
function declineInvitation(int $invitation_id, int $user_id) {
    $invitation = getProjectInvitation($invitation_id);
    
    if (!$invitation) {
        return false;
    }
    
    // Verify user is the invitee
    if ($invitation['invitee_id'] != $user_id && $invitation['invitee_email'] != getUserEmail($user_id)) {
        return false;
    }
    
    // Check invitation is still pending
    if ($invitation['status'] != 'pending') {
        return false;
    }
    
    return queryDB(
        'UPDATE project_invitations SET status = ?, responded_at = NOW(), updated_at = NOW() WHERE id = ?',
        ['declined', $invitation_id]
    );
}

/**
 * Get user email by user ID
 * 
 * @param int $user_id User ID
 * 
 * @return string|null User email or null
 */
function getUserEmail(int $user_id) {
    $user = getRowDB('SELECT email FROM users WHERE id = ? LIMIT 1', [$user_id]);
    return $user ? $user['email'] : null;
}

?>

