<?php
// api/posts.php
// Post/announcement management API with Firestore persistence

require_once __DIR__ . '/../config/auth-middleware.php';

/**
 * Get posts for a project
 */
function get_project_posts(string $projectId, string $idToken): array {
    $result = firestore_query('posts', ['projectId' => $projectId], 500, $idToken);
    
    if ($result['success']) {
        // Sort by timestamp descending
        usort($result['data'], function($a, $b) {
            return strtotime($b['createdAt'] ?? '0') - strtotime($a['createdAt'] ?? '0');
        });
        return ['success' => true, 'posts' => $result['data']];
    }

    return ['success' => false, 'error' => 'Failed to fetch posts'];
}

/**
 * Create new post
 */
function create_post(string $projectId, string $uid, string $authorName, string $content, bool $isAnnouncement = false, string $announcementTitle = '', string $idToken = ''): array {
    if (!$content || strlen($content) > 5000) {
        return ['success' => false, 'error' => 'Invalid post content'];
    }

    $postId = 'post_' . bin2hex(random_bytes(8));

    $postData = [
        'id' => $postId,
        'projectId' => $projectId,
        'uid' => $uid,
        'author' => $authorName,
        'content' => trim($content),
        'isAnnouncement' => $isAnnouncement,
        'announcementTitle' => $announcementTitle,
        'likes' => 0,
        'comments' => 0,
        'likedBy' => [],
        'createdAt' => date('c'),
        'updatedAt' => date('c'),
    ];

    $result = firestore_set('posts', $postId, $postData, $idToken);
    
    if ($result['success']) {
        return ['success' => true, 'post' => $result['data']];
    }

    return ['success' => false, 'error' => 'Failed to create post'];
}

/**
 * Update post
 */
function update_post(string $postId, array $updates, string $idToken): array {
    $updates['updatedAt'] = date('c');

    $result = firestore_update('posts', $postId, $updates, $idToken);
    
    if ($result['success']) {
        return ['success' => true, 'post' => $result['data']];
    }

    return ['success' => false, 'error' => 'Failed to update post'];
}

/**
 * Delete post
 */
function delete_post(string $postId, string $idToken): array {
    $result = firestore_delete('posts', $postId, $idToken);
    
    if ($result['success']) {
        return ['success' => true];
    }

    return ['success' => false, 'error' => 'Failed to delete post'];
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

        $result = get_project_posts($projectId, $user['token']);
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
            $content = trim((string)($input['content'] ?? ''));
            $isAnnouncement = (bool)($input['is_announcement'] ?? false);
            $announcementTitle = trim((string)($input['announcement_title'] ?? ''));

            $result = create_post(
                $projectId,
                $user['uid'],
                $user['profile']['fullName'] ?? 'You',
                $content,
                $isAnnouncement,
                $announcementTitle,
                $user['token']
            );
            json_response($result, $result['success'] ? 201 : 400);
        }

        if ($action === 'update') {
            $postId = trim((string)($input['post_id'] ?? ''));
            if (!$postId) {
                json_response(['success' => false, 'error' => 'Missing post_id'], 400);
            }

            $updates = [];
            if (isset($input['content'])) {
                $updates['content'] = trim((string)$input['content']);
            }

            $result = update_post($postId, $updates, $user['token']);
            json_response($result, $result['success'] ? 200 : 400);
        }

        if ($action === 'delete') {
            $postId = trim((string)($input['post_id'] ?? ''));
            if (!$postId) {
                json_response(['success' => false, 'error' => 'Missing post_id'], 400);
            }

            $result = delete_post($postId, $user['token']);
            json_response($result, $result['success'] ? 200 : 400);
        }

        json_response(['success' => false, 'error' => 'Invalid action'], 400);
    }

    json_response(['success' => false, 'error' => 'Method not allowed'], 405);
}
