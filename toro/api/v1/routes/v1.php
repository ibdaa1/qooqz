<?php
declare(strict_types=1);

header('Content-Type: application/json');

echo json_encode([
    'status' => 'v1 route working',
    'time' => date('c'),
    'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
    'method' => $_SERVER['REQUEST_METHOD'] ?? null
]);
exit;