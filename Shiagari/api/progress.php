<?php
// api/progress.php
// Progress/task management API with Firestore persistence

require_once __DIR__ . '/../config/auth-middleware.php';

/**
 * Get tasks for a project
 */
function get_project_tasks(string $projectId, string $idToken): array {
    $result = firestore_query('tasks', ['projectId' => $projectId], 500, $idToken);
    
    if ($result['success']) {
        return ['success' => true, 'tasks' => $result['data']];
    }

    return ['success' => false, 'error' => 'Failed to fetch tasks'];
}

/**
 * Create new task
 */
function create_task(string $projectId, string $uid, string $name, string $category, string $status, int $progress, string $idToken): array {
    if (!$name || strlen($name) > 200) {
        return ['success' => false, 'error' => 'Invalid task name'];
    }

    $taskId = 'task_' . bin2hex(random_bytes(8));

    $taskData = [
        'id' => $taskId,
        'projectId' => $projectId,
        'uid' => $uid,
        'name' => trim($name),
        'category' => $category,
        'status' => $status,
        'progress' => max(0, min(100, $progress)),
        'createdAt' => date('c'),
        'updatedAt' => date('c'),
    ];

    $result = firestore_set('tasks', $taskId, $taskData, $idToken);
    
    if ($result['success']) {
        return ['success' => true, 'task' => $result['data']];
    }

    return ['success' => false, 'error' => 'Failed to create task'];
}

/**
 * Update task
 */
function update_task(string $taskId, array $updates, string $idToken): array {
    // Validate status if provided
    if (isset($updates['status'])) {
        $validStatuses = ['notstarted', 'inprogress', 'finished'];
        if (!in_array($updates['status'], $validStatuses)) {
            return ['success' => false, 'error' => 'Invalid status'];
        }
    }

    // Validate progress if provided
    if (isset($updates['progress'])) {
        $updates['progress'] = max(0, min(100, (int)$updates['progress']));
    }

    $updates['updatedAt'] = date('c');

    $result = firestore_update('tasks', $taskId, $updates, $idToken);
    
    if ($result['success']) {
        return ['success' => true, 'task' => $result['data']];
    }

    return ['success' => false, 'error' => 'Failed to update task'];
}

/**
 * Delete task
 */
function delete_task(string $taskId, string $idToken): array {
    $result = firestore_delete('tasks', $taskId, $idToken);
    
    if ($result['success']) {
        return ['success' => true];
    }

    return ['success' => false, 'error' => 'Failed to delete task'];
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

        $result = get_project_tasks($projectId, $user['token']);
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
            $name = trim((string)($input['name'] ?? ''));
            $category = trim((string)($input['category'] ?? 'uiux'));
            $status = trim((string)($input['status'] ?? 'notstarted'));
            $progress = (int)($input['progress'] ?? 0);

            $result = create_task($projectId, $user['uid'], $name, $category, $status, $progress, $user['token']);
            json_response($result, $result['success'] ? 201 : 400);
        }

        if ($action === 'update') {
            $taskId = trim((string)($input['task_id'] ?? ''));
            if (!$taskId) {
                json_response(['success' => false, 'error' => 'Missing task_id'], 400);
            }

            $updates = [];
            if (isset($input['status'])) {
                $updates['status'] = trim((string)$input['status']);
            }
            if (isset($input['progress'])) {
                $updates['progress'] = (int)$input['progress'];
            }
            if (isset($input['name'])) {
                $updates['name'] = trim((string)$input['name']);
            }

            $result = update_task($taskId, $updates, $user['token']);
            json_response($result, $result['success'] ? 200 : 400);
        }

        if ($action === 'delete') {
            $taskId = trim((string)($input['task_id'] ?? ''));
            if (!$taskId) {
                json_response(['success' => false, 'error' => 'Missing task_id'], 400);
            }

            $result = delete_task($taskId, $user['token']);
            json_response($result, $result['success'] ? 200 : 400);
        }

        json_response(['success' => false, 'error' => 'Invalid action'], 400);
    }

    json_response(['success' => false, 'error' => 'Method not allowed'], 405);
}
