<?php
// api/ideas.php
// Idea management API with Firestore persistence

require_once __DIR__ . '/../config/auth-middleware.php';

/**
 * Get ideas for a project
 */
function get_project_ideas(string $projectId, string $idToken): array {
    $result = firestore_query('ideas', ['projectId' => $projectId], 500, $idToken);
    
    if ($result['success']) {
        return ['success' => true, 'ideas' => $result['data']];
    }

    return ['success' => false, 'error' => 'Failed to fetch ideas'];
}

/**
 * Create new idea
 */
function create_idea(string $projectId, string $uid, string $title, string $description, string $category, string $idToken): array {
    if (!$title || strlen($title) > 200) {
        return ['success' => false, 'error' => 'Invalid idea title'];
    }

    $ideaId = 'idea_' . bin2hex(random_bytes(8));

    $ideaData = [
        'id' => $ideaId,
        'projectId' => $projectId,
        'uid' => $uid,
        'title' => trim($title),
        'description' => trim($description),
        'category' => $category,
        'status' => 'new',
        'votes' => 0,
        'votedBy' => [],
        'createdAt' => date('c'),
        'updatedAt' => date('c'),
    ];

    $result = firestore_set('ideas', $ideaId, $ideaData, $idToken);
    
    if ($result['success']) {
        return ['success' => true, 'idea' => $result['data']];
    }

    return ['success' => false, 'error' => 'Failed to create idea'];
}

/**
 * Update idea
 */
function update_idea(string $ideaId, string $uid, array $updates, string $idToken): array {
    // Note: In production, verify user is project member
    $updates['updatedAt'] = date('c');

    $result = firestore_update('ideas', $ideaId, $updates, $idToken);
    
    if ($result['success']) {
        return ['success' => true, 'idea' => $result['data']];
    }

    return ['success' => false, 'error' => 'Failed to update idea'];
}

/**
 * Delete idea
 */
function delete_idea(string $ideaId, string $uid, string $idToken): array {
    $result = firestore_delete('ideas', $ideaId, $idToken);
    
    if ($result['success']) {
        return ['success' => true];
    }

    return ['success' => false, 'error' => 'Failed to delete idea'];
}

// Handle API requests
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $user = require_auth();
        $projectId = $_GET['project_id'] ?? null;

        if (!$projectId) {
            json_response(['success' => false, 'error' => 'Missing project_id'], 400);
        }

        $result = get_project_ideas($projectId, $user['token']);
        json_response($result, $result['success'] ? 200 : 400);
    }

    if ($method === 'POST') {
        $input = read_json_body();
        $user = require_auth_and_csrf($input);

        $action = $input['action'] ?? 'create';
        $projectId = trim((string)($input['project_id'] ?? ''));

        if (!$projectId) {
            json_response(['success' => false, 'error' => 'Missing project_id'], 400);
        }

        if ($action === 'create') {
            $title = trim((string)($input['title'] ?? ''));
            $description = trim((string)($input['description'] ?? ''));
            $category = trim((string)($input['category'] ?? 'general'));

            $result = create_idea($projectId, $user['uid'], $title, $description, $category, $user['token']);
            json_response($result, $result['success'] ? 201 : 400);
        }

        if ($action === 'update') {
            $ideaId = trim((string)($input['idea_id'] ?? ''));
            if (!$ideaId) {
                json_response(['success' => false, 'error' => 'Missing idea_id'], 400);
            }

            $updates = [];
            if (isset($input['title'])) {
                $updates['title'] = trim((string)$input['title']);
            }
            if (isset($input['description'])) {
                $updates['description'] = trim((string)$input['description']);
            }
            if (isset($input['status'])) {
                $updates['status'] = trim((string)$input['status']);
            }

            $result = update_idea($ideaId, $user['uid'], $updates, $user['token']);
            json_response($result, $result['success'] ? 200 : 400);
        }

        if ($action === 'delete') {
            $ideaId = trim((string)($input['idea_id'] ?? ''));
            if (!$ideaId) {
                json_response(['success' => false, 'error' => 'Missing idea_id'], 400);
            }

            $result = delete_idea($ideaId, $user['uid'], $user['token']);
            json_response($result, $result['success'] ? 200 : 400);
        }

        json_response(['success' => false, 'error' => 'Invalid action'], 400);
    }

    json_response(['success' => false, 'error' => 'Method not allowed'], 405);
}
