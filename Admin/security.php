<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include './config.php';
include './log.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$success = '';
$error   = '';
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT password, email, twofa_enabled FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_password_hash, $user_email, $twofa_enabled_db);
$stmt->fetch();
$stmt->close();

$twofa_email_enabled = (int)$twofa_enabled_db === 1 ? 1 : 0;

$logoUrl   = 'https://lucky-plum-7yxiihmoh3.edgeone.app/logom.png';
$siteName  = 'Reporta Évora';
$publicUrl = 'http://localhost/PAP/index.php?evora_p=inicio';

function sendTwoFaCodeByEmail(string $toEmail, string $code, string &$errorOut = null): bool {
    global $logoUrl, $siteName, $publicUrl;

    $subject = "Código de autenticação em dois fatores - {$siteName}";

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

    $body .= '<tr><td style="padding-bottom:18px;border-bottom:1px solid #e5e7eb;">';
    $body .= '<div style="text-align:center;">';
    $body .= '<a href="' . htmlspecialchars($publicUrl, ENT_QUOTES, 'UTF-8') . '" '
          . 'style="text-decoration:none;display:inline-block;">';
    $body .= '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '"'
          . ' style="max-width:220px;height:auto;display:block;margin:0 auto 6px auto;border:0;">';
    $body .= '</a>';
    $body .= '<div style="font-size:12px;color:#6b7280;">Painel de administração</div>';
    $body .= '</div>';
    $body .= '</td></tr>';

    $body .= '<tr><td style="padding-top:18px;padding-bottom:8px;">';
    $body .= '<h2 style="margin:0;font-size:20px;line-height:1.3;color:#111827;">';
    $body .= 'Código de autenticação em dois fatores (admin)</h2>';
    $body .= '</td></tr>';

    $body .= '<tr><td style="padding-bottom:10px;">';
    $body .= '<p style="margin:0;color:#4b5563;font-size:14px;line-height:1.6;">';
    $body .= 'Utilize o código abaixo para ativar a autenticação em dois fatores no painel de administração de ';
    $body .= '<strong>' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '</strong>.';
    $body .= '</p>';
    $body .= '</td></tr>';

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

    $body .= '<tr><td style="padding-top:10px;padding-bottom:4px;">';
    $body .= '<p style="margin:0;color:#6b7280;font-size:12px;line-height:1.6;">';
    $body .= 'Não partilhe este código com ninguém. Se não foi você que pediu este código, pode ignorar este email.';
    $body .= '</p>';
    $body .= '</td></tr>';

    $body .= '<tr><td style="padding-top:16px;font-size:12px;color:#6b7280;border-top:1px solid #e5e7eb;padding-bottom:4px;">';
    $body .= 'Este email foi enviado automaticamente pelo sistema ';
    $body .= '<strong>' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '</strong>. Por favor, não responda diretamente a esta mensagem.';
    $body .= '</td></tr>';

    $body .= '</table>';
    $body .= '</td></tr></table>';
    $body .= '</body></html>';

    $headers  = "From: No Reply <no-reply@example.com>\r\n";
    $headers .= "Reply-To: no-reply@example.com\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    $ok = mail($toEmail, $subject, $body, $headers);

    if (!$ok) {
        $errorOut = "mail() devolveu false.";
    }

    return $ok;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_pass = $_POST['current_password'] ?? '';
    $new_pass     = $_POST['new_password'] ?? '';
    $conf_pass    = $_POST['confirm_password'] ?? '';

    if ($current_pass === '' || $new_pass === '' || $conf_pass === '') {
        $error = "Todos os campos são obrigatórios!";
    } elseif (!password_verify($current_pass, $user_password_hash)) {
        $error = "A palavra-passe atual está incorreta.";
    } elseif (strlen($new_pass) < 8) {
        $error = "A nova palavra-passe deve ter pelo menos 8 caracteres.";
    } elseif ($new_pass !== $conf_pass) {
        $error = "As novas palavras-passe não coincidem.";
    } else {
        $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt     = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $new_hash, $user_id);

        if ($stmt->execute()) {
            $success = "Palavra-passe alterada com sucesso.";
            regista_log($conn, $user_id, "alterar", "password", $user_id, "Password alterada pelo utilizador.");
        } else {
            $error = "Erro ao atualizar palavra-passe.";
        }
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enable_2fa_email']) && !$twofa_email_enabled) {
    $code       = (string) random_int(100000, 999999);
    $expires_at = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');

    $stmt = $conn->prepare("INSERT INTO user_twofa_codes (user_id, code, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $code, $expires_at);
    if ($stmt->execute()) {
        $stmt->close();

        $mailError = null;
        if (sendTwoFaCodeByEmail($user_email, $code, $mailError)) {
            $success = "Enviámos um código de verificação para o seu email. Introduza-o abaixo para ativar a autenticação em dois fatores.";
        } else {
            $error = "Não foi possível enviar o email com o código. " . $mailError;
        }
    } else {
        $error = "Não foi possível gerar o código de verificação.";
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_2fa_email']) && !$twofa_email_enabled) {
    $code_input = trim($_POST['twofa_code'] ?? '');

    if ($code_input === '') {
        $error = "Tem de introduzir o código recebido por email.";
    } else {
        $stmt = $conn->prepare("
            SELECT id, expires_at, used
            FROM user_twofa_codes
            WHERE user_id = ? AND code = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->bind_param("is", $user_id, $code_input);
        $stmt->execute();
        $stmt->bind_result($code_id, $expires_at, $used);
        if ($stmt->fetch()) {
            $stmt->close();

            $now = new DateTime();
            $exp = DateTime::createFromFormat('Y-m-d H:i:s', $expires_at);

            if ($used) {
                $error = "Este código já foi utilizado. Peça um novo código.";
            } elseif ($exp === false || $now > $exp) {
                $error = "O código expirou. Peça um novo código.";
            } else {
                $stmt = $conn->prepare("UPDATE user_twofa_codes SET used = 1 WHERE id = ?");
                $stmt->bind_param("i", $code_id);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("UPDATE users SET twofa_enabled = 1 WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                if ($stmt->execute()) {
                    $twofa_email_enabled = 1;
                    $success             = "Autenticação em dois fatores por email ativada com sucesso.";
                    regista_log($conn, $user_id, "ativar", "2fa_email", $user_id, "2FA por email ativada.");
                } else {
                    $error = "2FA foi validada, mas não conseguimos guardar o estado na base de dados.";
                }
                $stmt->close();
            }
        } else {
            $stmt->close();
            $error = "Código inválido. Verifique o código que recebeu no email.";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['disable_2fa_email']) && $twofa_email_enabled) {
    $pass_2fa = $_POST['password_2fa_disable'] ?? '';

    if ($pass_2fa === '') {
        $error = "Tem de inserir a palavra-passe para desativar a 2FA.";
    } elseif (!password_verify($pass_2fa, $user_password_hash)) {
        $error = "A palavra-passe está incorreta.";
    } else {
        $stmt = $conn->prepare("UPDATE users SET twofa_enabled = 0 WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $twofa_email_enabled = 0;
            $success             = "Autenticação em dois fatores por email desativada.";
            regista_log($conn, $user_id, "desativar", "2fa_email", $user_id, "2FA por email desativada.");
        } else {
            $error = "Erro ao desativar a autenticação em dois fatores.";
        }
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    $delete_password = $_POST['delete_password'] ?? '';

    if (empty($_POST['confirm_delete'])) {
        $error = "Tem de confirmar que realmente deseja remover a conta.";
    } elseif ($delete_password === '') {
        $error = "Tem de inserir a palavra-passe para apagar a conta.";
    } elseif (!password_verify($delete_password, $user_password_hash)) {
        $error = "A palavra-passe para apagar a conta está incorreta.";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        regista_log($conn, $user_id, "remover", "utilizador", $user_id, "Conta apagada pelo próprio utilizador.");
        session_destroy();
        header("Location: login.php?deleted=1");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Segurança da conta</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link
        href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/app.css">

    <style>
        :root {
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-soft: #6b7280;
            --border-subtle: #e5e7eb;
            --primary: #435ebe;
            --primary-dark: #2e467f;
        }
        html, body {
            height: 100%;
        }
        body {
            font-family:'Nunito',sans-serif!important;
            background: linear-gradient(135deg, #eef2ff, #f9fafb);
            color: var(--text-main);
            margin: 0;
        }
        #app, #main {
            min-height: 100%;
        }
        .security-shell {
            padding-bottom: 32px;
        }
        .security-container {
            max-width: 1100px;
            margin: 18px auto 0 auto;
            padding: 0 12px 24px 12px;
        }
        .page-heading h2 {
            font-weight: 800;
            font-size: 1.9rem;
            color: var(--text-main);
        }
        .page-heading p {
            color: var(--text-soft);
            margin-bottom: 0;
        }
        .security-card {
            background: var(--card-bg);
            border-radius: 18px;
            box-shadow: 0 10px 28px rgba(15,23,42,0.12);
            border: 1px solid rgba(148,163,184,0.20);
            padding: 24px 24px 20px 24px;
            margin-bottom: 20px;
        }
        .security-card h5 {
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 4px;
        }
        .security-card small {
            color: var(--text-soft);
        }
        .security-card label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 4px;
            font-size: 0.9rem;
        }
        .security-card .form-control {
            background: #f9fafb;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            font-size: 0.95rem;
        }
        .security-card .form-control:focus {
            background: #f3f4ff;
            border-color:#6366f1;
            box-shadow: 0 0 0 1px rgba(99,102,241,0.16);
        }
        .btn-primary {
            background: var(--primary);
            border:none;
            border-radius: 999px;
            font-weight:600;
            padding-inline:22px;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        .btn-danger {
            border-radius: 999px;
            font-weight:600;
            padding-inline:22px;
        }
        .danger-area {
            border:1px solid #fecaca;
            background:#fef2f2;
        }
        .danger-area h5 {
            color:#b91c1c;
        }
        .danger-area p {
            color:#991b1b;
        }
        a, a:hover, a:focus, a:active,
        .btn, h1, h2, h3, h4, h5, h6,
        th, td, label, .sidebar, .menu, .navbar {
            text-decoration: none !important;
            border-bottom: none !important;
        }
        @media (max-width: 992px) {
            #main {
                margin-left: 0 !important;
            }
            .sidebar-wrapper {
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                z-index: 1050;
            }
            #app {
                overflow-x: hidden;
            }
        }
    </style>
</head>
<body>
<div id="app">
    <?php include "menu.php"; ?>

    <div id="main" class="security-shell">
        <header class="mb-3 d-flex align-items-center">
            <a href="#" class="burger-btn d-block d-xl-none me-2">
                <i class="bi bi-justify fs-3"></i>
            </a>
        </header>

        <div class="security-container">
            <div class="page-heading mb-3">
                <h2>Segurança da conta</h2>
                <p>Altere a palavra-passe, ative a autenticação em dois fatores e, se necessário, remova a sua conta.</p>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php elseif ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="security-card mb-3">
                <h5>Alterar palavra-passe</h5>
                <small>Use uma palavra-passe longa, com letras maiúsculas, minúsculas, números e símbolos.</small>

                <form method="post" autocomplete="off" class="mt-3">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="current_password">Palavra-passe atual</label>
                            <input
                                type="password"
                                class="form-control"
                                name="current_password"
                                id="current_password"
                                required
                            >
                        </div>
                        <div class="col-md-4">
                            <label for="new_password">Nova palavra-passe</label>
                            <input
                                type="password"
                                class="form-control"
                                name="new_password"
                                id="new_password"
                                required
                            >
                        </div>
                        <div class="col-md-4">
                            <label for="confirm_password">Confirmar nova palavra-passe</label>
                            <input
                                type="password"
                                class="form-control"
                                name="confirm_password"
                                id="confirm_password"
                                required
                            >
                        </div>
                    </div>

                    <div class="mt-3 d-flex justify-content-end">
                        <button
                            type="submit"
                            name="change_password"
                            class="btn btn-primary"
                        >
                            Guardar alterações
                        </button>
                    </div>
                </form>
            </div>

            <div class="security-card mb-3">
                <h5>Autenticação em dois fatores (email)</h5>

                <?php if ($twofa_email_enabled): ?>
                    <small>A sua conta está protegida com um código enviado por email em cada login.</small>

                    <div class="row g-3 mt-3">
                        <div class="col-md-6">
                            <label for="current_email">Email atual</label>
                            <input
                                type="email"
                                class="form-control"
                                id="current_email"
                                value="<?= htmlspecialchars($user_email) ?>"
                                readonly
                            >
                        </div>
                    </div>

                    <form method="post" autocomplete="off" class="mt-3">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="password_2fa_disable">Palavra-passe para desativar 2FA</label>
                                <input
                                    type="password"
                                    class="form-control"
                                    name="password_2fa_disable"
                                    id="password_2fa_disable"
                                    required
                                >
                            </div>
                        </div>
                        <div class="mt-3 d-flex justify-content-end">
                            <button
                                type="submit"
                                name="disable_2fa_email"
                                class="btn btn-danger"
                            >
                                Desativar autenticação em dois fatores
                            </button>
                        </div>
                    </form>

                <?php else: ?>
                    <small>
                        Ative a autenticação em dois fatores para receber um código de segurança por email sempre que iniciar sessão.
                    </small>

                    <div class="row g-3 mt-3">
                        <div class="col-md-6">
                            <label for="current_email">Email onde irá receber os códigos</label>
                            <input
                                type="email"
                                class="form-control"
                                id="current_email"
                                value="<?= htmlspecialchars($user_email) ?>"
                                readonly
                            >
                        </div>
                    </div>

                    <form method="post" autocomplete="off" class="mt-3">
                        <button
                            type="submit"
                            name="enable_2fa_email"
                            class="btn btn-primary"
                        >
                            Enviar código para ativar 2FA
                        </button>
                    </form>

                    <form method="post" autocomplete="off" class="mt-3">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="twofa_code">Código recebido por email</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    name="twofa_code"
                                    id="twofa_code"
                                    placeholder="Ex: 123456"
                                >
                            </div>
                        </div>
                        <div class="mt-3 d-flex justify-content-end">
                            <button
                                type="submit"
                                name="confirm_2fa_email"
                                class="btn btn-primary"
                            >
                                Confirmar código e ativar
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <div class="security-card danger-area">
                <h5>Apagar conta</h5>
                <p class="mb-2">
                    Esta ação é permanente e não pode ser desfeita. Todos os seus dados serão removidos.
                </p>

                <form method="post" autocomplete="off">
                    <div class="form-check mb-2">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="confirm_delete"
                            name="confirm_delete"
                            value="1"
                            required
                        >
                        <label class="form-check-label" for="confirm_delete">
                            Confirmo que quero apagar permanentemente a minha conta.
                        </label>
                    </div>

                    <div class="row g-3 mb-2">
                        <div class="col-md-5">
                            <label for="delete_password">Palavra-passe atual</label>
                            <input
                                type="password"
                                class="form-control"
                                name="delete_password"
                                id="delete_password"
                                required
                            >
                        </div>
                    </div>

                    <button
                        type="submit"
                        name="delete_account"
                        class="btn btn-danger"
                    >
                        Apagar conta
                    </button>
                </form>
            </div>

        </div>
    </div>
</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
