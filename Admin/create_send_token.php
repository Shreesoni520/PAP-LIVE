<?php
session_start();
include './config.php';

header('Content-Type: application/json');

// must be logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authorized']);
    exit();
}

// must be admin
$isAdmin = !empty($_SESSION['is_admin']) && (int)$_SESSION['is_admin'] === 1;
if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['error' => 'Admin only']);
    exit();
}

$noticia_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($noticia_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid id']);
    exit();
}

// gerar token seguro (64 chars hex)
$token = bin2hex(random_bytes(32));

$stmt = $conn->prepare("
    INSERT INTO newsletter_send_tokens (noticia_id, token)
    VALUES (?, ?)
");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error']);
    exit();
}

$stmt->bind_param('is', $noticia_id, $token);
$stmt->execute();
$stmt->close();

echo json_encode(['token' => $token]);
