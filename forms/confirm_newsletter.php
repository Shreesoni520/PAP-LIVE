<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';

function redirect_home_newsletter() {
    header('Location: /PAP/index.php?evora_p=inicio#footer');
    exit;
}

$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if ($token === '') {
    $_SESSION['newsletter_error'] = 'Token de confirmação inválido.';
    redirect_home_newsletter();
}

// 1) Procurar o subscritor pelo token ainda não confirmado
$stmt = $conn->prepare("
    SELECT id, email 
    FROM newsletter_subscribers
    WHERE confirm_token = ? AND is_confirmed = 0
    LIMIT 1
");
if (!$stmt) {
    $_SESSION['newsletter_error'] = 'Erro interno ao confirmar subscrição.';
    redirect_home_newsletter();
}

$stmt->bind_param('s', $token);
$stmt->execute();
$result = $stmt->get_result();
$sub = $result->fetch_assoc();
$stmt->close();

if (!$sub) {
    $_SESSION['newsletter_error'] = 'Este link de confirmação é inválido ou já foi utilizado.';
    redirect_home_newsletter();
}

// 2) Confirmar a subscrição
$stmt2 = $conn->prepare("
    UPDATE newsletter_subscribers
    SET is_confirmed = 1,
        confirm_token = NULL
    WHERE id = ?
");
if (!$stmt2) {
    $_SESSION['newsletter_error'] = 'Erro interno ao confirmar subscrição.';
    redirect_home_newsletter();
}

$stmt2->bind_param('i', $sub['id']);
$stmt2->execute();
$stmt2->close();

$conn->close();

// Mensagem de sucesso
$_SESSION['newsletter_success'] = 'Subscrição confirmada com sucesso. Obrigado!';
unset($_SESSION['newsletter_error']);

// Se não estiver logado, vai para login; se estiver, volta ao início
if (empty($_SESSION['public_user_id'])) {
    header('Location: /PAP/index.php?evora_p=login');
} else {
    redirect_home_newsletter();
}
exit;
