<?php

function json_response(array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload);
    exit;
}

function read_json_body(): array {
    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody, true);

    return is_array($data) ? $data : [];
}
