<?php
/**
 * SHIAGARI Projects API
 * 
 * REST endpoints for project management, including CRUD operations
 * and project member management.
 * 
 * Requires authenticated session (see config/session.php)
 */

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
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
        case 'list':
            handleListProjects($user_id);
            break;
            
        case 'get':
            handleGetProject($user_id);
            break;
            
        case 'members':
            handleGetMembers($user_id);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}
// POST endpoints
elseif ($method === 'POST') {
    // Verify CSRF token for POST requests
    $csrf_token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (!csrf_token_is_valid($csrf_token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'CSRF token invalid']);
        exit;
    }
    
    switch ($action) {
        case 'create':
            handleCreateProject($user_id);
            break;
            
        case 'update':
            handleUpdateProject($user_id);
            break;
            
        case 'delete':
            handleDeleteProject($user_id);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}
// PUT endpoints
elseif ($method === 'PUT') {
    parse_str(file_get_contents("php://input"), $_PUT);
    $csrf_token = $_PUT['csrf_token'] ?? '';
    if (!csrf_token_is_valid($csrf_token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'CSRF token invalid']);
        exit;
    }
    
    if ($action === 'update') {
        handleUpdateProject($user_id, $_PUT);
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
    
    if ($action === 'delete') {
        handleDeleteProject($user_id);
    }
}

/**
 * List all projects for current user
 */
function handleListProjects($user_id) {
    $limit = intval($_GET['limit'] ?? 50);
    $offset = intval($_GET['offset'] ?? 0);
    
    $projects = getUserProjects($user_id, $limit, $offset);
    
    if ($projects === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to fetch projects']);
        return;
    }
    
    // Fetch members for each project
    foreach ($projects as &$project) {
        $project['members'] = getProjectMembers($project['id']);
        // Count pending invitations
        $pending = queryDB(
            'SELECT COUNT(*) as count FROM project_invitations WHERE project_id = ? AND status = ? AND expires_at > NOW()',
            [$project['id'], 'pending']
        );
        $project['pending_invitations'] = $pending ? $pending[0]['count'] : 0;
    }
    
    echo json_encode([
        'success' => true,
        'projects' => $projects
    ]);
}

/**
 * Get single project details
 */
function handleGetProject($user_id) {
    $project_id = intval($_GET['id'] ?? 0);
    
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
    
    // Check if user has access (owner or member)
    if ($project['owner_id'] != $user_id) {
        $member = getRowDB(
            'SELECT id FROM project_members WHERE project_id = ? AND user_id = ? LIMIT 1',
            [$project_id, $user_id]
        );
        if (!$member) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            return;
        }
    }
    
    $project['members'] = getProjectMembers($project_id);
    
    echo json_encode([
        'success' => true,
        'project' => $project
    ]);
}

/**
 * Create new project
 */
function handleCreateProject($user_id) {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $status = $_POST['status'] ?? 'active';
    
    if (!$name || !trim($name)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Project name required']);
        return;
    }
    
    $project = createProject($user_id, $name, $description, $status);
    
    if (!$project) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to create project']);
        return;
    }
    
    $project['members'] = getProjectMembers($project['id']);
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Project created successfully',
        'project' => $project
    ]);
}

/**
 * Update project
 */
function handleUpdateProject($user_id, $data = null) {
    if ($data === null) {
        $data = $_POST;
    }
    
    $project_id = intval($data['id'] ?? 0);
    
    if (!$project_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Project ID required']);
        return;
    }
    
    $project = getProject($project_id);
    if (!$project || $project['owner_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Only project owner can update']);
        return;
    }
    
    $name = $data['name'] ?? '';
    $description = $data['description'] ?? '';
    $status = $data['status'] ?? '';
    
    if (!updateProject($project_id, $name, $description, $status)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update project']);
        return;
    }
    
    $updated = getProject($project_id);
    $updated['members'] = getProjectMembers($project_id);
    
    echo json_encode([
        'success' => true,
        'message' => 'Project updated successfully',
        'project' => $updated
    ]);
}

/**
 * Delete project
 */
function handleDeleteProject($user_id) {
    $project_id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
    
    if (!$project_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Project ID required']);
        return;
    }
    
    $project = getProject($project_id);
    if (!$project || $project['owner_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Only project owner can delete']);
        return;
    }
    
    if (!deleteProject($project_id)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to delete project']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Project deleted successfully'
    ]);
}

/**
 * Get project members
 */
function handleGetMembers($user_id) {
    $project_id = intval($_GET['id'] ?? 0);
    
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
    
    // Check access
    if ($project['owner_id'] != $user_id) {
        $member = getRowDB(
            'SELECT id FROM project_members WHERE project_id = ? AND user_id = ? LIMIT 1',
            [$project_id, $user_id]
        );
        if (!$member) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            return;
        }
    }
    
    $members = getProjectMembers($project_id);
    
    echo json_encode([
        'success' => true,
        'members' => $members
    ]);
}

?>
