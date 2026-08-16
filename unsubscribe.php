<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

function redirect_home_unsub() {
    header('Location: /PAP/index.php?evora_p=inicio#footer');
    exit;
}

$email = isset($_GET['email']) ? trim($_GET['email']) : '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['newsletter_error'] = 'O link de cancelamento é inválido.';
    redirect_home_unsub();
}

// apagar subscrição se existir (confirmada ou não)
$stmt = $conn->prepare("
    DELETE FROM newsletter_subscribers
    WHERE email = ?
");
if (!$stmt) {
    $_SESSION['newsletter_error'] = 'Erro ao processar o cancelamento.';
    redirect_home_unsub();
}

$stmt->bind_param('s', $email);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    $_SESSION['newsletter_success'] = 'O teu email foi removido da newsletter.';
    unset($_SESSION['newsletter_error']);
} else {
    $_SESSION['newsletter_error'] = 'Não encontrámos nenhuma subscrição com este email.';
    unset($_SESSION['newsletter_success']);
}

$stmt->close();
$conn->close();

// se estiver logado, vai para o início; se não, força login para ver a mensagem
if (empty($_SESSION['public_user_id'])) {
    header('Location: /PAP/index.php?evora_p=login');
} else {
    redirect_home_unsub();
}
exit;
