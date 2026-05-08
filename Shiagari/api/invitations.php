<?php
/**
 * SHIAGARI Project Invitations API
 * 
 * REST endpoints for managing project collaboration invitations.
 * Users can search for collaborators by email/username, send invitations,
 * and accept/decline pending invitations.
 * 
 * Requires authenticated session (see config/session.php)
 */

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// GET endpoints
if ($method === 'GET') {
    switch ($action) {
        case 'search':
            handleSearchUsers();
            break;
            
        case 'pending':
            handleGetPendingInvitations($user_id);
            break;
            
        case 'project-invitations':
            handleGetProjectInvitations($user_id);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}
// POST endpoints
elseif ($method === 'POST') {
    // Verify CSRF token
    $csrf_token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (!csrf_token_is_valid($csrf_token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'CSRF token invalid']);
        exit;
    }
    
    switch ($action) {
        case 'send':
            handleSendInvitation($user_id);
            break;
            
        case 'accept':
            handleAcceptInvitation($user_id);
            break;
            
        case 'decline':
            handleDeclineInvitation($user_id);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}
// DELETE endpoints
elseif ($method === 'DELETE') {
    $csrf_token = $_GET['csrf_token'] ?? '';
    if (!csrf_token_is_valid($csrf_token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'CSRF token invalid']);
        exit;
    }
    
    if ($action === 'cancel') {
        handleCancelInvitation($user_id);
    }
}

/**
 * Search users by email or username
 */
function handleSearchUsers() {
    $query = trim($_GET['q'] ?? '');
    
    if (strlen($query) < 2) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Search query must be at least 2 characters']);
        return;
    }
    
    $limit = intval($_GET['limit'] ?? 10);
    if ($limit < 1 || $limit > 50) {
        $limit = 10;
    }
    
    $users = searchUsers($query, $limit);
    
    if ($users === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Search failed']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'users' => $users
    ]);
}

/**
 * Get pending invitations for current user
 */
function handleGetPendingInvitations($user_id) {
    $invitations = getPendingInvitations($user_id);
    
    if ($invitations === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to fetch invitations']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'invitations' => $invitations,
        'count' => count($invitations)
    ]);
}

/**
 * Get all invitations for a project (for owner to see status)
 */
function handleGetProjectInvitations($user_id) {
    $project_id = intval($_GET['project_id'] ?? 0);
    
    if (!$project_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Project ID required']);
        return;
    }
    
    $project = getProject($project_id);
    if (!$project) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Project not found']);
        return;
    }
    
    // Only project owner can view all invitations
    if ($project['owner_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Only project owner can view invitations']);
        return;
    }
    
    $invitations = queryDB(
        'SELECT pi.*, inv.full_name as inviter_name, invt.full_name as invitee_name
         FROM project_invitations pi
         LEFT JOIN users inv ON pi.inviter_id = inv.id
         LEFT JOIN users invt ON pi.invitee_id = invt.id
         WHERE pi.project_id = ?
         ORDER BY pi.created_at DESC',
        [$project_id]
    );
    
    if ($invitations === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to fetch invitations']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'invitations' => $invitations
    ]);
}

/**
 * Send invitation to a user
 */
function handleSendInvitation($user_id) {
    $project_id = intval($_POST['project_id'] ?? 0);
    $invitee_id = intval($_POST['invitee_id'] ?? 0);
    $invitee_email = trim($_POST['invitee_email'] ?? '');
    $role = trim($_POST['role'] ?? 'editor');
    
    if (!$project_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Project ID required']);
        return;
    }
    
    // Verify project exists and user is owner or editor
    $project = getProject($project_id);
    if (!$project) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Project not found']);
        return;
    }
    
    $member = getRowDB(
        'SELECT role FROM project_members WHERE project_id = ? AND user_id = ? LIMIT 1',
        [$project_id, $user_id]
    );
    
    // Only owner and editors can invite
    if ($project['owner_id'] != $user_id && (!$member || $member['role'] === 'viewer')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You do not have permission to invite members']);
        return;
    }
    
    // Get invitee email
    if ($invitee_id) {
        $invitee = getRowDB('SELECT id, email FROM users WHERE id = ? LIMIT 1', [$invitee_id]);
        if (!$invitee) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'User not found']);
            return;
        }
        $invitee_email = $invitee['email'];
    } elseif ($invitee_email) {
        // Validate email format
        if (!filter_var($invitee_email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid email address']);
            return;
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invitee ID or email required']);
        return;
    }
    
    // Send invitation
    $invitation = sendProjectInvitation($project_id, $user_id, $invitee_id ?: null, $invitee_email, $role);
    
    if (!$invitation) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Failed to send invitation (user may already be a member)']);
        return;
    }
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Invitation sent successfully',
        'invitation' => $invitation
    ]);
}

/**
 * Accept invitation
 */
function handleAcceptInvitation($user_id) {
    $invitation_id = intval($_POST['invitation_id'] ?? 0);
    
    if (!$invitation_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invitation ID required']);
        return;
    }
    
    if (!acceptInvitation($invitation_id, $user_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Failed to accept invitation']);
        return;
    }
    
    $invitation = getProjectInvitation($invitation_id);
    
    echo json_encode([
        'success' => true,
        'message' => 'Invitation accepted',
        'invitation' => $invitation
    ]);
}

/**
 * Decline invitation
 */
function handleDeclineInvitation($user_id) {
    $invitation_id = intval($_POST['invitation_id'] ?? 0);
    
    if (!$invitation_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invitation ID required']);
        return;
    }
    
    if (!declineInvitation($invitation_id, $user_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Failed to decline invitation']);
        return;
    }
    
    $invitation = getProjectInvitation($invitation_id);
    
    echo json_encode([
        'success' => true,
        'message' => 'Invitation declined',
        'invitation' => $invitation
    ]);
}

/**
 * Cancel invitation (owner only)
 */
function handleCancelInvitation($user_id) {
    $invitation_id = intval($_GET['id'] ?? 0);
    
    if (!$invitation_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invitation ID required']);
        return;
    }
    
    $invitation = getProjectInvitation($invitation_id);
    if (!$invitation) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Invitation not found']);
        return;
    }
    
    // Only inviter or project owner can cancel
    $project = getProject($invitation['project_id']);
    if ($invitation['inviter_id'] != $user_id && $project['owner_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You do not have permission to cancel this invitation']);
        return;
    }
    
    if (!queryDB('DELETE FROM project_invitations WHERE id = ?', [$invitation_id])) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to cancel invitation']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Invitation cancelled'
    ]);
}

?>
