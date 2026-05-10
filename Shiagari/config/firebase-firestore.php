<?php
// config/firebase-firestore.php
// Firestore helpers for SHIAGARI

require_once __DIR__ . '/firebase.php';

/**
 * Get a Firestore document
 */
function firestore_get(string $collection, string $documentId, string $idToken = ''): array {
    $url = 'https://firestore.googleapis.com/v1/projects/' . FIREBASE_PROJECT_ID . '/databases/(default)/documents/' . $collection . '/' . $documentId;

    $result = firebase_request('GET', $url, [], $idToken);
    
    if (isset($result['fields'])) {
        return ['success' => true, 'data' => firestore_decode_document($result)];
    }

    return ['success' => false, 'error' => $result['error']['message'] ?? 'Not found'];
}

/**
 * Create or update a Firestore document
 */
function firestore_set(string $collection, string $documentId, array $data, string $idToken = ''): array {
    // PATCH to the document URL creates-or-overwrites with the exact ID we specify.
    // POST to the collection URL (old code) ignores documentId and assigns a random ID.
    $url = 'https://firestore.googleapis.com/v1/projects/' . FIREBASE_PROJECT_ID . '/databases/(default)/documents/' . $collection . '/' . urlencode($documentId);

    $encodedData = firestore_encode_data($data);

    $result = firebase_request('PATCH', $url, ['fields' => $encodedData], $idToken);

    if (isset($result['fields'])) {
        return ['success' => true, 'data' => firestore_decode_document($result)];
    }

    return ['success' => false, 'error' => $result['error']['message'] ?? 'Failed to save'];
}

/**
 * Update a Firestore document (partial update)
 */
function firestore_update(string $collection, string $documentId, array $data, string $idToken = ''): array {
    $url = 'https://firestore.googleapis.com/v1/projects/' . FIREBASE_PROJECT_ID . '/databases/(default)/documents/' . $collection . '/' . $documentId;

    $encodedData = firestore_encode_data($data);

    $result = firebase_request('PATCH', $url, ['fields' => $encodedData], $idToken);

    if (isset($result['fields'])) {
        return ['success' => true, 'data' => firestore_decode_document($result)];
    }

    return ['success' => false, 'error' => $result['error']['message'] ?? 'Failed to update'];
}

/**
 * Delete a Firestore document
 */
function firestore_delete(string $collection, string $documentId, string $idToken = ''): array {
    $url = 'https://firestore.googleapis.com/v1/projects/' . FIREBASE_PROJECT_ID . '/databases/(default)/documents/' . $collection . '/' . $documentId;

    $result = firebase_request('DELETE', $url, [], $idToken);

    // Firestore DELETE returns an empty {} body with HTTP 200.
    // A successful response has no 'error' key.
    if (!isset($result['error'])) {
        return ['success' => true];
    }

    return ['success' => false, 'error' => $result['error']['message'] ?? 'Failed to delete'];
}

/**
 * Query Firestore documents
 */
function firestore_query(string $collection, array $filters = [], int $limit = 100, string $idToken = ''): array {
    $url = 'https://firestore.googleapis.com/v1/projects/' . FIREBASE_PROJECT_ID . '/databases/(default)/documents:runQuery';

    $structuredQuery = [
        'from' => [['collectionId' => $collection]],
        'limit' => $limit,
    ];

    if (!empty($filters)) {
        $whereConditions = [];
        foreach ($filters as $field => $value) {
            $whereConditions[] = [
                'fieldFilter' => [
                    'field' => ['fieldPath' => $field],
                    'op' => 'EQUAL',
                    'value' => firestore_encode_value($value),
                ],
            ];
        }
        if ($whereConditions) {
            $structuredQuery['where'] = ['compositeFilter' => ['op' => 'AND', 'filters' => $whereConditions]];
        }
    }

    $result = firebase_request('POST', $url, ['structuredQuery' => $structuredQuery], $idToken);

    if (is_array($result) && !isset($result['error'])) {
        $documents = [];
        foreach ($result as $item) {
            if (isset($item['document'])) {
                $documents[] = firestore_decode_document($item['document']);
            }
        }
        return ['success' => true, 'data' => $documents];
    }

    return ['success' => false, 'error' => $result['error']['message'] ?? 'Query failed'];
}

/**
 * Encode PHP values to Firestore format
 */
function firestore_encode_value($value): array {
    if ($value === null) {
        return ['nullValue' => null];
    }
    if (is_bool($value)) {
        return ['booleanValue' => $value];
    }
    if (is_int($value) || is_float($value)) {
        return ['doubleValue' => (double) $value];
    }
    if (is_string($value)) {
        return ['stringValue' => $value];
    }
    if (is_array($value)) {
        if (array_is_list($value)) {
            return ['arrayValue' => ['values' => array_map('firestore_encode_value', $value)]];
        }
        return ['mapValue' => ['fields' => firestore_encode_data($value)]];
    }
    return ['stringValue' => (string) $value];
}

/**
 * Encode array to Firestore data format
 */
function firestore_encode_data(array $data): array {
    $encoded = [];
    foreach ($data as $key => $value) {
        $encoded[$key] = firestore_encode_value($value);
    }
    return $encoded;
}

/**
 * Decode Firestore document to PHP array, including its document ID
 */
function firestore_decode_document(array $document): array {
    $data = [];

    // Extract the document ID from the resource name path
    // e.g. "projects/my-proj/databases/(default)/documents/users/abc123" → "abc123"
    if (isset($document['name'])) {
        $parts = explode('/', $document['name']);
        $data['id'] = end($parts);
    }

    if (!isset($document['fields'])) {
        return $data;
    }

    return array_merge($data, firestore_decode_fields($document['fields']));
}

/**
 * Decode Firestore fields
 */
function firestore_decode_fields(array $fields): array {
    $result = [];
    foreach ($fields as $key => $field) {
        $result[$key] = firestore_decode_value($field);
    }
    return $result;
}

/**
 * Decode a single Firestore value
 */
function firestore_decode_value(array $value) {
    if (isset($value['nullValue'])) {
        return null;
    }
    if (isset($value['booleanValue'])) {
        return $value['booleanValue'];
    }
    if (isset($value['integerValue'])) {
        return (int) $value['integerValue'];
    }
    if (isset($value['doubleValue'])) {
        return (float) $value['doubleValue'];
    }
    if (isset($value['stringValue'])) {
        return $value['stringValue'];
    }
    if (isset($value['arrayValue'])) {
        return array_map('firestore_decode_value', $value['arrayValue']['values'] ?? []);
    }
    if (isset($value['mapValue'])) {
        return firestore_decode_fields($value['mapValue']['fields'] ?? []);
    }
    return null;
}

/**
 * Verify Firebase ID token and get user info
 */
function verify_firebase_token(string $idToken): array {
    // For development, we'll use Firebase REST API to verify
    // In production, use Google's public keys: https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com
    
    $url = 'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . FIREBASE_API_KEY;

    $result = firebase_request('POST', $url, ['idToken' => $idToken], '');

    if (isset($result['users']) && count($result['users']) > 0) {
        $user = $result['users'][0];
        return [
            'success' => true,
            'uid' => $user['localId'] ?? '',
            'email' => $user['email'] ?? '',
            'displayName' => $user['displayName'] ?? '',
            'photoUrl' => $user['photoUrl'] ?? '',
        ];
    }

    return ['success' => false, 'error' => 'Invalid token'];
}
