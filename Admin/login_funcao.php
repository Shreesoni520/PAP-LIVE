<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

include './config.php';

// garantir fuso horário de Portugal (Lisboa)
date_default_timezone_set('Europe/Lisbon');

// Config do email HTML (mesmo layout dos outros)
$logoUrl   = 'https://lucky-plum-7yxiihmoh3.edgeone.app/logom.png';
$siteName  = 'Reporta Évora';
$publicUrl = 'http://localhost/PAP/index.php?evora_p=inicio';

// Se não for POST, volta ao login
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit();
}

$action = $_POST['action'] ?? 'login';

// Função de envio de email 2FA em HTML com no‑reply
function sendTwoFaCodeByEmail(string $toEmail, string $code, string &$errorOut = null): bool {
    global $logoUrl, $siteName, $publicUrl;

    $subject = "Código de autenticação em dois fatores - {$siteName}";

    // HTML do email
    $body  = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    $body .= '<title>Código de autenticação em dois fatores</title></head>';
    $body .= '<body style="margin:0;padding:0;background-color:#f3f4f6;';
    $body .= 'font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;">';

    $body .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" ';
    $body .= 'style="background-color:#f3f4f6;padding:24px 0;">';
    $body .= '<tr><td align="center">';

    $body .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" ';
    $body .= 'style="max-width:640px;background-color:#ffffff;border-radius:16px;';
    $body .= 'border:1px solid #e5e7eb;padding:20px 24px;color:#111827;">';

    // HEADER LOGO
    $body .= '<tr><td style="padding-bottom:18px;border-bottom:1px solid #e5e7eb;">';
    $body .= '<div style="text-align:center;">';
    $body .= '<a href="' . htmlspecialchars($publicUrl, ENT_QUOTES, 'UTF-8') . '" '
          . 'style="text-decoration:none;display:inline-block;">';
    $body .= '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '"'
          . ' style="max-width:220px;height:auto;display:block;margin:0 auto 6px auto;border:0;">';
    $body .= '</a>';
    $body .= '<div style="font-size:12px;color:#6b7280;">Plataforma digital de registo de ocorrências urbanas</div>';
    $body .= '</div>';
    $body .= '</td></tr>';

    // TÍTULO
    $body .= '<tr><td style="padding-top:18px;padding-bottom:8px;">';
    $body .= '<h2 style="margin:0;font-size:20px;line-height:1.3;color:#111827;">';
    $body .= 'Código de autenticação em dois fatores</h2>';
    $body .= '</td></tr>';

    // TEXTO INTRO
    $body .= '<tr><td style="padding-bottom:10px;">';
    $body .= '<p style="margin:0;color:#4b5563;font-size:14px;line-height:1.6;">';
    $body .= 'Utilize o código abaixo para concluir o seu login em ';
    $body .= '<strong>' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '</strong>.';
    $body .= '</p>';
    $body .= '</td></tr>';

    // CARD COM O CÓDIGO
    $body .= '<tr><td style="padding-top:10px;padding-bottom:6px;">';
    $body .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" ';
    $body .= 'style="background-color:#f9fafb;border-radius:12px;border:1px solid #e5e7eb;';
    $body .= 'padding:14px 16px;text-align:center;">';

    $body .= '<tr><td style="padding-bottom:8px;">';
    $body .= '<div style="font-size:12px;color:#6b7280;font-weight:600;margin-bottom:4px;">O seu código de verificação</div>';
    $body .= '<div style="font-size:26px;letter-spacing:.25em;font-weight:700;color:#111827;">';
    $body .= htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    $body .= '</div>';
    $body .= '</td></tr>';

    $body .= '<tr><td style="padding-top:4px;">';
    $body .= '<div style="font-size:12px;color:#6b7280;">Este código é válido durante 10 minutos.</div>';
    $body .= '</td></tr>';

    $body .= '</table>';
    $body .= '</td></tr>';

    // TEXTO FINAL
    $body .= '<tr><td style="padding-top:10px;padding-bottom:4px;">';
    $body .= '<p style="margin:0;color:#6b7280;font-size:12px;line-height:1.6;">';
    $body .= 'Se não pediu este código, pode ignorar este email. A sua conta permanece protegida.';
    $body .= '</p>';
    $body .= '</td></tr>';

    // FOOTER
    $body .= '<tr><td style="padding-top:16px;font-size:12px;color:#6b7280;border-top:1px solid #e5e7eb;padding-bottom:4px;">';
    $body .= 'Este email foi enviado automaticamente pelo sistema ';
    $body .= '<strong>' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '</strong>. Por favor, não responda diretamente a esta mensagem.';
    $body .= '</td></tr>';

    $body .= '</table>'; // inner
    $body .= '</td></tr></table>'; // outer
    $body .= '</body></html>';

    // Headers para HTML
    $headers  = "From: No Reply <no-reply@example.com>\r\n";
    $headers .= "Reply-To: no-reply@example.com\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    $ok = mail($toEmail, $subject, $body, $headers);

    if (!$ok) {
        $errorOut = "mail() devolveu false.";
    }

    return $ok;
}

/*
 * AÇÃO 1: LOGIN (username + password)
 * - Se twofa_enabled = 0 → login direto, sem pedir código
 * - Se twofa_enabled = 1 → gera código, envia email, vai para passo 2
 */
if ($action === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        header('Location: login.php?error=1');
        exit();
    }

    // Buscar user (inclui twofa_enabled)
    $stmt = $conn->prepare(
        'SELECT id, username, email, password, is_admin, twofa_enabled
         FROM users
         WHERE username = ?'
    );
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user   = $result->fetch_assoc();
    $stmt->close();

    // Username inexistente ou password errada
    if (!$user || !password_verify($password, $user['password'])) {
        header('Location: login.php?error=1');
        exit();
    }

    $userId       = (int)$user['id'];
    $userEmail    = $user['email'];
    $isAdmin      = (int)$user['is_admin'];
    $twofaEnabled = isset($user['twofa_enabled']) ? (int)$user['twofa_enabled'] : 0;

    // 1) Se 2FA NÃO estiver ativa → login completo direto
    if ($twofaEnabled !== 1) {
        $_SESSION['user_id']  = $userId;
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_admin'] = $isAdmin;

        // Atualizar last_activity
        $stmtAct = $conn->prepare("
            UPDATE users
            SET last_activity = NOW()
            WHERE id = ?
        ");
        if ($stmtAct) {
            $stmtAct->bind_param("i", $userId);
            $stmtAct->execute();
            $stmtAct->close();
        }

        // Registar login sem 2FA (opcional)
        $acao    = 'Login';
        $detalhe = "Login sem 2FA para {$user['username']} ({$user['email']})";

        $stmtAt = $conn->prepare("
            INSERT INTO atividade (user_id, acao, detalhe)
            VALUES (?, ?, ?)
        ");
        if ($stmtAt) {
            $stmtAt->bind_param("iss", $userId, $acao, $detalhe);
            $stmtAt->execute();
            $stmtAt->close();
        }

        header('Location: index.php?evora=inicio');
        exit();
    }

    // 2) Se 2FA ESTÁ ativa → gerar código e guardar em user_twofa_codes
    $code       = (string) random_int(100000, 999999);
    $expires_at = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');

    $stmt = $conn->prepare("INSERT INTO user_twofa_codes (user_id, code, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $userId, $code, $expires_at);
    if ($stmt->execute()) {
        $stmt->close();

        $mailError = null;
        if (!sendTwoFaCodeByEmail($userEmail, $code, $mailError)) {
            // Se falhar o email, não continuamos o login
            header('Location: login.php?error=email');
            exit();
        }

        // Guardar dados pendentes de 2FA na sessão (ainda não autenticado)
        $_SESSION['pending_2fa_user_id']  = $userId;
        $_SESSION['pending_2fa_username'] = $user['username'];

        // Vai para o passo 2: inserir código 2FA
        header('Location: login.php?step=2');
        exit();

    } else {
        $stmt->close();
        header('Location: login.php?error=email');
        exit();
    }
}

/*
 * AÇÃO 2: VERIFICAR CÓDIGO 2FA
 * Só funciona se existir pending_2fa_user_id na sessão.
 */
if ($action === 'verify_2fa') {
    // Tem de existir um login pendente
    if (!isset($_SESSION['pending_2fa_user_id'])) {
        header('Location: login.php');
        exit();
    }

    $userId     = (int) $_SESSION['pending_2fa_user_id'];
    $twofa_code = trim($_POST['twofa_code'] ?? '');

    if ($twofa_code === '') {
        header('Location: login.php?step=2&error=2fa');
        exit();
    }

    // Verificar código na tabela
    $stmt = $conn->prepare("
        SELECT id, expires_at, used
        FROM user_twofa_codes
        WHERE user_id = ? AND code = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->bind_param("is", $userId, $twofa_code);
    $stmt->execute();
    $stmt->bind_result($code_id, $expires_at, $used);
    if ($stmt->fetch()) {
        $stmt->close();

        $now = new DateTime();
        $exp = new DateTime($expires_at);

        if ($used || $now > $exp) {
            header('Location: login.php?step=2&error=2fa');
            exit();
        }

        // Marcar como usado
        $stmt = $conn->prepare("UPDATE user_twofa_codes SET used = 1 WHERE id = ?");
        $stmt->bind_param("i", $code_id);
        $stmt->execute();
        $stmt->close();

        // Buscar outra vez dados do user para pôr na sessão
        $stmt = $conn->prepare(
            'SELECT id, username, email, is_admin
             FROM users
             WHERE id = ?'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            header('Location: login.php');
            exit();
        }

        // Login final OK
        $_SESSION['user_id']  = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_admin'] = (int)$user['is_admin'];

        // Limpar pendentes de 2FA
        unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_username']);

        // Atualizar last_activity
        $stmtAct = $conn->prepare("
            UPDATE users
            SET last_activity = NOW()
            WHERE id = ?
        ");
        if ($stmtAct) {
            $stmtAct->bind_param("i", $user['id']);
            $stmtAct->execute();
            $stmtAct->close();
        }

        // Registar em atividade
        $acao    = 'Login';
        $detalhe = "Login com 2FA para {$user['username']} ({$user['email']})";

        $stmtAt = $conn->prepare("
            INSERT INTO atividade (user_id, acao, detalhe)
            VALUES (?, ?, ?)
        ");
        if ($stmtAt) {
            $stmtAt->bind_param("iss", $user['id'], $acao, $detalhe);
            $stmtAt->execute();
            $stmtAt->close();
        }

        header('Location: index.php?evora=inicio');
        exit();

    } else {
        $stmt->close();
        header('Location: login.php?step=2&error=2fa');
        exit();
    }
}

// Se action não for reconhecida, volta ao login
header('Location: login.php');
exit();
