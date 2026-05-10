<?php
// api/roadmap.php
// Roadmap/epic management API with Firestore persistence

require_once __DIR__ . '/../config/auth-middleware.php';

/**
 * Get epics for a project
 */
function get_project_epics(string $projectId, string $idToken): array {
    $result = firestore_query('epics', ['projectId' => $projectId], 500, $idToken);
    
    if ($result['success']) {
        return ['success' => true, 'epics' => $result['data']];
    }

    return ['success' => false, 'error' => 'Failed to fetch epics'];
}

/**
 * Create new epic
 */
function create_epic(string $projectId, string $uid, string $name, string $color, int $startQuarter, int $duration, string $description, string $idToken): array {
    if (!$name || strlen($name) > 200) {
        return ['success' => false, 'error' => 'Invalid epic name'];
    }

    if ($startQuarter < 0 || $duration < 1 || $duration > 8) {
        return ['success' => false, 'error' => 'Invalid quarter or duration'];
    }

    $epicId = 'epic_' . bin2hex(random_bytes(8));

    $epicData = [
        'id' => $epicId,
        'projectId' => $projectId,
        'uid' => $uid,
        'name' => trim($name),
        'color' => trim($color),
        'startQuarter' => (int)$startQuarter,
        'duration' => (int)$duration,
        'description' => trim($description),
        'createdAt' => date('c'),
        'updatedAt' => date('c'),
    ];

    $result = firestore_set('epics', $epicId, $epicData, $idToken);
    
    if ($result['success']) {
        return ['success' => true, 'epic' => $result['data']];
    }

    return ['success' => false, 'error' => 'Failed to create epic'];
}

/**
 * Update epic
 */
function update_epic(string $epicId, array $updates, string $idToken): array {
    $updates['updatedAt'] = date('c');

    $result = firestore_update('epics', $epicId, $updates, $idToken);
    
    if ($result['success']) {
        return ['success' => true, 'epic' => $result['data']];
    }

    return ['success' => false, 'error' => 'Failed to update epic'];
}

/**
 * Delete epic
 */
function delete_epic(string $epicId, string $idToken): array {
    $result = firestore_delete('epics', $epicId, $idToken);
    
    if ($result['success']) {
        return ['success' => true];
    }

    return ['success' => false, 'error' => 'Failed to delete epic'];
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

        $result = get_project_epics($projectId, $user['token']);
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
            $color = trim((string)($input['color'] ?? 'blue'));
            $startQuarter = (int)($input['start_quarter'] ?? 0);
            $duration = (int)($input['duration'] ?? 1);
            $description = trim((string)($input['description'] ?? ''));

            $result = create_epic($projectId, $user['uid'], $name, $color, $startQuarter, $duration, $description, $user['token']);
            json_response($result, $result['success'] ? 201 : 400);
        }

        if ($action === 'update') {
            $epicId = trim((string)($input['epic_id'] ?? ''));
            if (!$epicId) {
                json_response(['success' => false, 'error' => 'Missing epic_id'], 400);
            }

            $updates = [];
            if (isset($input['name'])) {
                $updates['name'] = trim((string)$input['name']);
            }
            if (isset($input['color'])) {
                $updates['color'] = trim((string)$input['color']);
            }
            if (isset($input['start_quarter'])) {
                $updates['startQuarter'] = (int)$input['start_quarter'];
            }
            if (isset($input['duration'])) {
                $updates['duration'] = (int)$input['duration'];
            }
            if (isset($input['description'])) {
                $updates['description'] = trim((string)$input['description']);
            }

            $result = update_epic($epicId, $updates, $user['token']);
            json_response($result, $result['success'] ? 200 : 400);
        }

        if ($action === 'delete') {
            $epicId = trim((string)($input['epic_id'] ?? ''));
            if (!$epicId) {
                json_response(['success' => false, 'error' => 'Missing epic_id'], 400);
            }

            $result = delete_epic($epicId, $user['token']);
            json_response($result, $result['success'] ? 200 : 400);
        }

        json_response(['success' => false, 'error' => 'Invalid action'], 400);
    }

    json_response(['success' => false, 'error' => 'Method not allowed'], 405);
}
