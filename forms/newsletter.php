<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';

// obrigar login para subscrever newsletter
if (empty($_SESSION['public_user_id'])) {
    $_SESSION['newsletter_error'] = 'Precisas de iniciar sessão para subscrever a newsletter.';
    header('Location: /PAP/index.php?evora_p=login');
    exit;
}

function redirect_back_newsletter() {
    header('Location: /PAP/index.php?evora_p=inicio#footer');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_back_newsletter();
}

$email = isset($_POST['email']) ? trim($_POST['email']) : '';

// 1) validação base PHP
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['newsletter_error'] = 'Por favor introduz um email válido.';
    redirect_back_newsletter();
}

// 2) comprimento mínimo
if (strlen($email) < 8) {
    $_SESSION['newsletter_error'] = 'Este email parece demasiado curto.';
    redirect_back_newsletter();
}

// 3) separar parte local e domínio
$parts  = explode('@', $email);
$local  = $parts[0] ?? '';
$domain = $parts[1] ?? '';

if (strlen($local) < 2 || strlen($domain) < 3 || strpos($domain, '.') === false) {
    $_SESSION['newsletter_error'] = 'Por favor introduz um email mais realista.';
    redirect_back_newsletter();
}

// 4) bloquear lixo óbvio
if (!preg_match('/[0-9\.]/', $local) && preg_match('/^[bcdfghjklmnpqrstvwxyz]{6,}$/i', $local)) {
    $_SESSION['newsletter_error'] = 'Este email não parece ser real.';
    redirect_back_newsletter();
}

// 5) limitar a alguns domínios comuns
$allowed_domains = ['gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com'];
if (!in_array(strtolower($domain), $allowed_domains, true)) {
    $_SESSION['newsletter_error'] = 'Neste momento só aceitamos emails Gmail, Hotmail, Outlook ou Yahoo.';
    redirect_back_newsletter();
}

// se chegou aqui, passa a validação extra
$token = bin2hex(random_bytes(16)); // 32 chars

try {
    $stmt = $conn->prepare("
        INSERT INTO newsletter_subscribers (email, is_confirmed, confirm_token)
        VALUES (?, 0, ?)
        ON DUPLICATE KEY UPDATE
            is_confirmed = 0,
            confirm_token = VALUES(confirm_token),
            created_at = CURRENT_TIMESTAMP
    ");

    if (!$stmt) {
        throw new Exception('Erro na preparação do statement.');
    }

    $stmt->bind_param('ss', $email, $token);

    if (!$stmt->execute()) {
        throw new Exception('Erro ao executar o statement.');
    }

    $stmt->close();

    // BASE URL DA APLICAÇÃO
    $baseUrl = 'http://localhost/PAP';

    // link de confirmação via router evora_p=confirmnewsletter
    $confirm_link = $baseUrl . "/index.php?evora_p=confirmnewsletter&token=" . urlencode($token);

    // Logo URL - EdgeOne direct link
    $logoUrl = 'https://lucky-plum-7yxiihmoh3.edgeone.app/logom.png';

    $subject = "Confirma a tua subscrição à newsletter do Reporta Évora";

    // HTML com o mesmo design (logo + card + botão gradient)
    $mensagem_html  = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    $mensagem_html .= '<title>Confirma a tua subscrição</title></head>';
    $mensagem_html .= '<body style="margin:0;padding:0;background-color:#f3f4f6;';
    $mensagem_html .= 'font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;">';

    $mensagem_html .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" ';
    $mensagem_html .= 'style="background-color:#f3f4f6;padding:24px 0;">';
    $mensagem_html .= '<tr><td align="center">';

    $mensagem_html .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" ';
    $mensagem_html .= 'style="max-width:640px;background-color:#ffffff;border-radius:16px;';
    $mensagem_html .= 'border:1px solid #e5e7eb;padding:20px 24px;color:#111827;">';

    // HEADER COM LOGO
    $mensagem_html .= '<tr><td style="text-align:center;padding-bottom:18px;border-bottom:1px solid #e5e7eb;">'
        . '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="Reporta Évora"'
        . ' style="max-width:220px;height:auto;display:block;margin:0 auto 6px auto;">'
        . '<div style="font-size:12px;color:#6b7280;">Plataforma digital de registo de ocorrências urbanas</div>'
        . '</td></tr>';

    // TÍTULO
    $mensagem_html .= '<tr><td style="padding-top:18px;padding-bottom:8px;">';
    $mensagem_html .= '<h2 style="margin:0;font-size:20px;line-height:1.3;color:#111827;">';
    $mensagem_html .= 'Confirmar subscrição à newsletter</h2>';
    $mensagem_html .= '</td></tr>';

    // TEXTO PRINCIPAL
    $mensagem_html .= '<tr><td style="padding-bottom:10px;">';
    $mensagem_html .= '<p style="margin:0;color:#4b5563;font-size:14px;line-height:1.6;">';
    $mensagem_html .= 'Recebemos um pedido para subscreveres a newsletter do ';
    $mensagem_html .= '<strong>Reporta Évora</strong> com este endereço de email.';
    $mensagem_html .= '</p>';
    $mensagem_html .= '</td></tr>';

    // BOTÃO
    $mensagem_html .= '<tr><td style="padding-top:14px;padding-bottom:6px;">';
    $mensagem_html .= '<a href="' . htmlspecialchars($confirm_link, ENT_QUOTES, 'UTF-8') . '" ';
    $mensagem_html .= 'style="display:inline-block;padding:10px 18px;border-radius:999px;';
    $mensagem_html .= 'background:linear-gradient(135deg,#0ea5e9,#22c55e);color:#ffffff;';
    $mensagem_html .= 'text-decoration:none;font-size:14px;font-weight:600;">';
    $mensagem_html .= 'Confirmar subscrição</a>';
    $mensagem_html .= '</td></tr>';

    // LINK EM TEXTO
    $mensagem_html .= '<tr><td style="padding-top:12px;font-size:12px;color:#6b7280;">';
    $mensagem_html .= 'Se o botão não funcionar, podes copiar e colar este link no teu navegador:<br>';
    $mensagem_html .= '<span style="word-break:break-all;">';
    $mensagem_html .= htmlspecialchars($confirm_link, ENT_QUOTES, 'UTF-8');
    $mensagem_html .= '</span>';
    $mensagem_html .= '</td></tr>';

    // AVISO
    $mensagem_html .= '<tr><td style="padding-top:16px;font-size:12px;color:#6b7280;">';
    $mensagem_html .= 'Se não foste tu que fizeste este pedido, podes ignorar este email.';
    $mensagem_html .= '</td></tr>';

    // ASSINATURA
    $mensagem_html .= '<tr><td style="padding-top:24px;font-size:12px;color:#6b7280;">';
    $mensagem_html .= 'Cumprimentos,<br>Equipa Reporta Évora';
    $mensagem_html .= '</td></tr>';

    $mensagem_html .= '</table>'; // inner
    $mensagem_html .= '</td></tr></table>'; // outer
    $mensagem_html .= '</body></html>';

    // headers para HTML (no-reply)
    $fromEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'no-reply@localhost';

    $headers  = "From: Reporta Évora <" . $fromEmail . ">\r\n";
    $headers .= "Reply-To: " . $fromEmail . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";

    @mail($email, $subject, $mensagem_html, $headers);

    $_SESSION['newsletter_success'] = 'Enviámos um email de confirmação. Verifica a tua caixa de correio.';
    unset($_SESSION['newsletter_error']);

} catch (Exception $e) {
    $_SESSION['newsletter_error'] = 'Ocorreu um erro ao processar o pedido. Tenta novamente mais tarde.';
    unset($_SESSION['newsletter_success']);
}

$conn->close();
redirect_back_newsletter();
