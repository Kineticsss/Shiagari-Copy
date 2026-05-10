<?php
// api/projects.php
// Project management API with Firestore persistence

require_once __DIR__ . '/../config/auth-middleware.php';

/**
 * Get all projects for current user
 */
function get_user_projects(string $uid, string $idToken): array {
    $result = firestore_query('projects', ['uid' => $uid], 100, $idToken);
    
    if ($result['success']) {
        return ['success' => true, 'projects' => $result['data']];
    }

    return ['success' => false, 'error' => 'Failed to fetch projects'];
}

/**
 * Get single project by ID
 */
function get_project(string $projectId, string $idToken): array {
    $result = firestore_get('projects', $projectId, $idToken);
    
    if ($result['success']) {
        return ['success' => true, 'project' => $result['data']];
    }

    return ['success' => false, 'error' => 'Project not found'];
}

/**
 * Create new project
 */
function create_project(string $uid, string $name, string $description, string $status, array $memberEmails, string $idToken): array {
    if (!$name || strlen($name) > 100) {
        return ['success' => false, 'error' => 'Invalid project name'];
    }

    if (!in_array($status, ['active', 'planning', 'hold'])) {
        return ['success' => false, 'error' => 'Invalid status'];
    }

    $projectId = 'proj_' . bin2hex(random_bytes(8));
    
    $projectData = [
        'id' => $projectId,
        'uid' => $uid,
        'name' => trim($name),
        'description' => trim($description),
        'status' => $status,
        'author' => $uid,
        'members' => $memberEmails ?: [],
        'createdAt' => date('c'),
        'updatedAt' => date('c'),
    ];

    $result = firestore_set('projects', $projectId, $projectData, $idToken);
    
    if ($result['success']) {
        return ['success' => true, 'project' => $result['data']];
    }

    return ['success' => false, 'error' => 'Failed to create project'];
}

/**
 * Update project
 */
function update_project(string $projectId, string $uid, array $updates, string $idToken): array {
    // Verify ownership
    $project = get_project($projectId, $idToken);
    if (!$project['success']) {
        return ['success' => false, 'error' => 'Project not found'];
    }

    if ($project['project']['uid'] !== $uid) {
        return ['success' => false, 'error' => 'Unauthorized'];
    }

    $updates['updatedAt'] = date('c');

    $result = firestore_update('projects', $projectId, $updates, $idToken);
    
    if ($result['success']) {
        return ['success' => true, 'project' => $result['data']];
    }

    return ['success' => false, 'error' => 'Failed to update project'];
}

/**
 * Delete project
 */
function delete_project(string $projectId, string $uid, string $idToken): array {
    // Verify ownership
    $project = get_project($projectId, $idToken);
    if (!$project['success']) {
        return ['success' => false, 'error' => 'Project not found'];
    }

    if ($project['project']['uid'] !== $uid) {
        return ['success' => false, 'error' => 'Unauthorized'];
    }

    $result = firestore_delete('projects', $projectId, $idToken);
    
    if ($result['success']) {
        return ['success' => true];
    }

    return ['success' => false, 'error' => 'Failed to delete project'];
}

// Handle API requests
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    $method = $_SERVER['REQUEST_METHOD'];
    
    // GET: Fetch projects
    if ($method === 'GET') {
        $user = require_auth();
        
        $result = get_user_projects($user['uid'], $user['token']);
        json_response($result, $result['success'] ? 200 : 400);
    }

    // POST: Create or update project
    if ($method === 'POST') {
        $input = read_json_body();
        $user = require_auth_and_csrf($input);

        $action = $input['action'] ?? 'create';

        if ($action === 'create') {
            $name = trim((string)($input['name'] ?? ''));
            $description = trim((string)($input['description'] ?? ''));
            $status = trim((string)($input['status'] ?? 'active'));
            $members = $input['members'] ?? [];

            $result = create_project($user['uid'], $name, $description, $status, $members, $user['token']);
            json_response($result, $result['success'] ? 201 : 400);
        }

        if ($action === 'update') {
            $projectId = trim((string)($input['project_id'] ?? ''));
            if (!$projectId) {
                json_response(['success' => false, 'error' => 'Missing project_id'], 400);
            }

            $updates = [];
            if (isset($input['name'])) {
                $updates['name'] = trim((string)$input['name']);
            }
            if (isset($input['description'])) {
                $updates['description'] = trim((string)$input['description']);
            }
            if (isset($input['status'])) {
                $updates['status'] = trim((string)$input['status']);
            }
            if (isset($input['members'])) {
                $updates['members'] = $input['members'];
            }

            $result = update_project($projectId, $user['uid'], $updates, $user['token']);
            json_response($result, $result['success'] ? 200 : 400);
        }

        if ($action === 'delete') {
            $projectId = trim((string)($input['project_id'] ?? ''));
            if (!$projectId) {
                json_response(['success' => false, 'error' => 'Missing project_id'], 400);
            }

            $result = delete_project($projectId, $user['uid'], $user['token']);
            json_response($result, $result['success'] ? 200 : 400);
        }

        json_response(['success' => false, 'error' => 'Invalid action'], 400);
    }

    json_response(['success' => false, 'error' => 'Method not allowed'], 405);
}
