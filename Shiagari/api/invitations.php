<?php
/**
 * SHIAGARI Project Invitations API
 *
 * REST endpoints: search users, send/accept/decline/cancel invitations.
 * Requires authenticated session.
 */

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

start_secure_session();

// Must be authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action  = $_GET['action'] ?? '';
$method  = $_SERVER['REQUEST_METHOD'];

// ── GET ─────────────────────────────────────────────────────────────────────
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
// ── POST ────────────────────────────────────────────────────────────────────
elseif ($method === 'POST') {
    $body = [];
    $ct   = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($ct, 'application/json') !== false) {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
    }
    $csrf_token = $_POST['csrf_token'] ?? $body['csrf_token'] ?? $_GET['csrf_token'] ?? '';

    if (!csrf_token_is_valid($csrf_token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'CSRF token invalid']);
        exit;
    }

    switch ($action) {
        case 'send':    handleSendInvitation($user_id);    break;
        case 'accept':  handleAcceptInvitation($user_id);  break;
        case 'decline': handleDeclineInvitation($user_id); break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}
// ── DELETE ───────────────────────────────────────────────────────────────────
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

/* ── HANDLERS ──────────────────────────────────────────────────────────────── */

/**
 * Search users by name, username, or email.
 * Uses a broad wildcard so passing `@` returns many users.
 */
function handleSearchUsers() {
    $query = trim($_GET['q'] ?? '');
    $limit = min(50, max(1, intval($_GET['limit'] ?? 20)));

    // Allow single-char search (front-end sends "@" for "list all")
    if (strlen($query) === 0) {
        echo json_encode(['success' => true, 'users' => []]);
        return;
    }

    // Strip leading @ so "@john" searches for "john"
    $query = ltrim($query, '@');

    $search = '%' . $query . '%';
    $sql    = 'SELECT id, email, full_name, username
               FROM users
               WHERE email LIKE ? OR username LIKE ? OR full_name LIKE ?
               ORDER BY full_name ASC
               LIMIT ?';

    $users = queryDB($sql, [$search, $search, $search, $limit]);

    if ($users === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Search failed']);
        return;
    }

    echo json_encode(['success' => true, 'users' => $users]);
}

/**
 * Pending invitations for the current user.
 */
function handleGetPendingInvitations($user_id) {
    $invitations = getPendingInvitations($user_id);

    if ($invitations === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to fetch invitations']);
        return;
    }

    echo json_encode([
        'success'     => true,
        'invitations' => $invitations,
        'count'       => count($invitations)
    ]);
}

/**
 * All invitations for a project (owner only).
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

    if ($project['owner_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Only project owner can view invitations']);
        return;
    }

    $invitations = queryDB(
        'SELECT pi.*, inv.full_name AS inviter_name, invt.full_name AS invitee_name
         FROM project_invitations pi
         LEFT JOIN users inv  ON pi.inviter_id  = inv.id
         LEFT JOIN users invt ON pi.invitee_id  = invt.id
         WHERE pi.project_id = ?
         ORDER BY pi.created_at DESC',
        [$project_id]
    );

    if ($invitations === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to fetch invitations']);
        return;
    }

    echo json_encode(['success' => true, 'invitations' => $invitations]);
}

/**
 * Send invitation.
 */
function handleSendInvitation($user_id) {
    $project_id    = intval($_POST['project_id']    ?? 0);
    $invitee_id    = intval($_POST['invitee_id']    ?? 0) ?: null;
    $invitee_email = trim($_POST['invitee_email']   ?? '');
    $role          = trim($_POST['role']            ?? 'editor');

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

    // Only owner or editors may invite
    $member = getRowDB(
        'SELECT role FROM project_members WHERE project_id = ? AND user_id = ? LIMIT 1',
        [$project_id, $user_id]
    );
    if ($project['owner_id'] != $user_id && (!$member || $member['role'] === 'viewer')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You do not have permission to invite members']);
        return;
    }

    // Resolve invitee
    if ($invitee_id) {
        $invitee = getRowDB('SELECT id, email FROM users WHERE id = ? LIMIT 1', [$invitee_id]);
        if (!$invitee) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'User not found']);
            return;
        }
        $invitee_email = $invitee['email'];
    } elseif ($invitee_email) {
        if (!filter_var($invitee_email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid email address']);
            return;
        }
        // Try to find user by email
        $found = getRowDB('SELECT id FROM users WHERE email = ? LIMIT 1', [$invitee_email]);
        if ($found) $invitee_id = $found['id'];
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invitee ID or email required']);
        return;
    }

    // Cannot invite self
    if ($invitee_id && $invitee_id == $user_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'You cannot invite yourself']);
        return;
    }

    $invitation = sendProjectInvitation($project_id, $user_id, $invitee_id, $invitee_email, $role);

    if (!$invitation) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Failed to send invitation (user may already be a member or invited)']);
        return;
    }

    http_response_code(201);
    echo json_encode([
        'success'    => true,
        'message'    => 'Invitation sent successfully',
        'invitation' => $invitation
    ]);
}

/**
 * Accept invitation.
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
        echo json_encode(['success' => false, 'error' => 'Failed to accept invitation (it may have expired or already been responded to)']);
        return;
    }

    $invitation = getProjectInvitation($invitation_id);
    echo json_encode(['success' => true, 'message' => 'Invitation accepted', 'invitation' => $invitation]);
}

/**
 * Decline invitation.
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
    echo json_encode(['success' => true, 'message' => 'Invitation declined', 'invitation' => $invitation]);
}

/**
 * Cancel invitation (inviter / project owner).
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

    echo json_encode(['success' => true, 'message' => 'Invitation cancelled']);
}