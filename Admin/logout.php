<?php

if (isset($_SESSION['user_id'])) {
    $userId = (int) $_SESSION['user_id'];

    $stmt = $conn->prepare("
        UPDATE users
        SET last_activity = NULL
        WHERE id = ?
    ");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    }

    $acao    = 'Logout';
    $detalhe = 'Sessão terminada pelo utilizador';

    $stmtAt = $conn->prepare("
        INSERT INTO atividade (user_id, acao, detalhe)
        VALUES (?, ?, ?)
    ");
    if ($stmtAt) {
        $stmtAt->bind_param('iss', $userId, $acao, $detalhe);
        $stmtAt->execute();
        $stmtAt->close();
    }
}

$adminSessionKeys = [
    'user_id',
    'username',
    'is_admin',
    'pending_2fa_user_id',
    'pending_2fa_username',
    'remove_public_verified',
    'remove_verified',
    'pending_email_change_user_id',
    'pending_profile_change_user',
    'pending_profile_change_email',
    'pending_new_user_email',
    'pending_new_user_role',
];

foreach ($adminSessionKeys as $key) {
    unset($_SESSION[$key]);
}

header('Location: login.php');
exit();
