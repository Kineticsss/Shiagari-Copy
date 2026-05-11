<?php
// api/messages.php
// Message/chat management API with Firestore persistence

require_once __DIR__ . '/../config/auth-middleware.php';

/**
 * Get conversation between two users
 */
function get_conversation(string $uid, string $otherUid, string $idToken): array {
    // Create sorted conversation ID
    $conversationId = $uid < $otherUid ? "{$uid}_{$otherUid}" : "{$otherUid}_{$uid}";
    
    $result = firestore_get('conversations', $conversationId, $idToken);
    
    if ($result['success']) {
        return ['success' => true, 'conversation' => $result['data']];
    }

    // Return empty conversation if not found
    return ['success' => true, 'conversation' => ['messages' => []]];
}

/**
 * Get all conversations for user, shaped for the frontend
 */
function get_user_conversations(string $uid, string $idToken): array {
    // Fetch ALL conversations (Firestore has no OR filter, so we pull and filter server-side)
    $result = firestore_query('conversations', [], 100, $idToken);

    if (!$result['success']) {
        return ['success' => false, 'error' => 'Failed to fetch conversations'];
    }

    $shaped = [];
    foreach ($result['data'] as $conv) {
        $user1 = $conv['user1'] ?? '';
        $user2 = $conv['user2'] ?? '';

        // Only include conversations this user is part of
        if ($user1 !== $uid && $user2 !== $uid) {
            continue;
        }

        $otherUid = ($user1 === $uid) ? $user2 : $user1;

        // Get the other person's name from the messages array
        $messages  = $conv['messages'] ?? [];
        $otherName = $otherUid; // fallback to UID if no name found
        $lastMsg   = '';
        $unread    = 0;

        foreach ($messages as $msg) {
            if (($msg['from'] ?? '') === $otherUid && !empty($msg['fromName'])) {
                $otherName = $msg['fromName'];
            }
            if (($msg['from'] ?? '') === $otherUid && !($msg['read'] ?? true)) {
                $unread++;
            }
        }

        if (!empty($messages)) {
            $lastMsg = $messages[count($messages) - 1]['content'] ?? '';
        }

        $shaped[] = [
            'id'           => $conv['id'] ?? '',
            'otherUid'     => $otherUid,
            'otherName'    => $otherName,
            'lastMessage'  => mb_strimwidth($lastMsg, 0, 60, '…'),
            'lastMessageAt'=> $conv['lastMessageAt'] ?? '',
            'unreadCount'  => $unread,
        ];
    }

    // Sort newest first
    usort($shaped, fn($a, $b) => strcmp($b['lastMessageAt'], $a['lastMessageAt']));

    return ['success' => true, 'conversations' => $shaped];
}

/**
 * Send message
 */
function send_message(string $fromUid, string $fromName, string $toUid, string $content, string $idToken): array {
    if (!$content || strlen($content) > 5000) {
        return ['success' => false, 'error' => 'Invalid message content'];
    }

    // Create sorted conversation ID
    $conversationId = $fromUid < $toUid ? "{$fromUid}_{$toUid}" : "{$toUid}_{$fromUid}";

    // Get existing conversation
    $convResult = firestore_get('conversations', $conversationId, $idToken);
    $messages = [];
    
    if ($convResult['success'] && isset($convResult['data']['messages'])) {
        $messages = $convResult['data']['messages'];
    }

    // Add new message
    $messages[] = [
        'id' => bin2hex(random_bytes(8)),
        'from' => $fromUid,
        'fromName' => $fromName,
        'to' => $toUid,
        'content' => trim($content),
        'timestamp' => date('c'),
        'read' => false,
    ];

    // Save conversation
    $conversationData = [
        'id' => $conversationId,
        'user1' => $fromUid < $toUid ? $fromUid : $toUid,
        'user2' => $fromUid < $toUid ? $toUid : $fromUid,
        'messages' => $messages,
        'lastMessageAt' => date('c'),
        'updatedAt' => date('c'),
    ];

    $result = firestore_set('conversations', $conversationId, $conversationData, $idToken);
    
    if ($result['success']) {
        return ['success' => true, 'message' => $messages[count($messages) - 1]];
    }

    return ['success' => false, 'error' => 'Failed to send message'];
}

/**
 * Mark messages as read
 */
function mark_messages_read(string $conversationId, string $idToken): array {
    $result = firestore_get('conversations', $conversationId, $idToken);
    
    if (!$result['success']) {
        return ['success' => false, 'error' => 'Conversation not found'];
    }

    $messages = $result['data']['messages'] ?? [];
    
    // Mark all as read
    foreach ($messages as &$msg) {
        $msg['read'] = true;
    }

    $updateResult = firestore_update('conversations', $conversationId, ['messages' => $messages], $idToken);
    
    if ($updateResult['success']) {
        return ['success' => true];
    }

    return ['success' => false, 'error' => 'Failed to update messages'];
}

// Handle API requests
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $user = require_auth();
        $otherUid = $_GET['user_id'] ?? null;

        if (!$otherUid) {
            // Get all conversations
            $result = get_user_conversations($user['uid'], $user['token']);
            json_response($result, $result['success'] ? 200 : 400);
        } else {
            // Get specific conversation
            $result = get_conversation($user['uid'], $otherUid, $user['token']);
            json_response($result, $result['success'] ? 200 : 400);
        }
    }

    if ($method === 'POST') {
        $input = read_json_body();
        $user = require_auth_and_csrf($input);

        $action = $input['action'] ?? 'send';

        if ($action === 'send') {
            $toUid = trim((string)($input['to_uid'] ?? ''));
            $content = trim((string)($input['content'] ?? ''));

            if (!$toUid) {
                json_response(['success' => false, 'error' => 'Missing to_uid'], 400);
            }

            $result = send_message($user['uid'], $user['profile']['fullName'] ?? 'User', $toUid, $content, $user['token']);
            json_response($result, $result['success'] ? 201 : 400);
        }

        if ($action === 'mark_read') {
            $conversationId = trim((string)($input['conversation_id'] ?? ''));

            if (!$conversationId) {
                json_response(['success' => false, 'error' => 'Missing conversation_id'], 400);
            }

            $result = mark_messages_read($conversationId, $user['token']);
            json_response($result, $result['success'] ? 200 : 400);
        }

        json_response(['success' => false, 'error' => 'Invalid action'], 400);
    }

    json_response(['success' => false, 'error' => 'Method not allowed'], 405);
}
