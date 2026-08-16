<?php
session_start();
require './config.php';

if (empty($_SESSION['public_user_id'])) {
    header('Location: index.php?evora_p=login');
    exit;
}

$sucesso = '';
$erro    = '';
$user_id = (int) $_SESSION['public_user_id'];

$stmt = $conn->prepare("SELECT password_hash, email, twofa_enabled FROM users_public WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_password_hash, $user_email, $twofa_enabled_db);
$stmt->fetch();
$stmt->close();

$twofa_email_enabled = (int)$twofa_enabled_db === 1 ? 1 : 0;

$logoUrl   = 'https://lucky-plum-7yxiihmoh3.edgeone.app/logom.png';
$siteName  = 'Reporta Évora';
$publicUrl = 'http://localhost/PAP/index.php?evora_p=inicio';

function sendTwoFaCodeByEmail_public(string $toEmail, string $code, string &$errorOut = null): bool {
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
    $body .= '<div style="font-size:12px;color:#6b7280;">Plataforma digital de registo de ocorrências urbanas</div>';
    $body .= '</div>';
    $body .= '</td></tr>';

    $body .= '<tr><td style="padding-top:18px;padding-bottom:8px;">';
    $body .= '<h2 style="margin:0;font-size:20px;line-height:1.3;color:#111827;">';
    $body .= 'Código de autenticação em dois fatores</h2>';
    $body .= '</td></tr>';

    $body .= '<tr><td style="padding-bottom:10px;">';
    $body .= '<p style="margin:0;color:#4b5563;font-size:14px;line-height:1.6;">';
    $body .= 'Utilize o código abaixo para ativar a autenticação em dois fatores na sua conta em ';
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
        $erro = "Todos os campos são obrigatórios.";
    } elseif (!password_verify($current_pass, $user_password_hash)) {
        $erro = "A palavra-passe atual está incorreta.";
    } elseif (strlen($new_pass) < 8) {
        $erro = "A nova palavra-passe deve ter pelo menos 8 caracteres.";
    } elseif ($new_pass !== $conf_pass) {
        $erro = "As novas palavras-passe não coincidem.";
    } else {
        $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt     = $conn->prepare("UPDATE users_public SET password_hash = ? WHERE id = ?");
        $stmt->bind_param("si", $new_hash, $user_id);

        if ($stmt->execute()) {
            $sucesso = "A sua palavra-passe foi atualizada com sucesso.";
        } else {
            $erro = "Não foi possível atualizar a palavra-passe.";
        }
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enable_2fa_email']) && !$twofa_email_enabled) {
    $stmt = $conn->prepare("DELETE FROM user_twofa_codes_public WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    $code       = (string) random_int(100000, 999999);
    $expires_at = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');

    $stmt = $conn->prepare("INSERT INTO user_twofa_codes_public (user_id, code, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $code, $expires_at);
    if ($stmt->execute()) {
        $stmt->close();

        $mailError = null;
        if (sendTwoFaCodeByEmail_public($user_email, $code, $mailError)) {
            $sucesso = "Enviámos um código para o seu email. Introduza-o abaixo para ativar a autenticação em dois fatores.";
        } else {
            $erro = "Não foi possível enviar o email com o código. " . $mailError;
        }
    } else {
        $erro = "Não foi possível gerar o código de verificação.";
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_2fa_email']) && !$twofa_email_enabled) {
    $code_input = trim($_POST['twofa_code'] ?? '');

    if ($code_input === '') {
        $erro = "Introduza o código que recebeu por email.";
    } else {
        $stmt = $conn->prepare("
            SELECT id, expires_at, used
            FROM user_twofa_codes_public
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
                $erro = "Este código já foi utilizado. Peça um novo código.";
            } elseif ($exp === false || $now > $exp) {
                $erro = "O código expirou. Peça um novo código.";
            } else {
                $stmt = $conn->prepare("UPDATE user_twofa_codes_public SET used = 1 WHERE id = ?");
                $stmt->bind_param("i", $code_id);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("UPDATE users_public SET twofa_enabled = 1 WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                if ($stmt->execute()) {
                    $twofa_email_enabled = 1;
                    $sucesso             = "Autenticação em dois fatores por email ativada com sucesso.";
                } else {
                    $erro = "O código foi validado, mas não foi possível guardar a alteração.";
                }
                $stmt->close();
            }
        } else {
            $stmt->close();
            $erro = "O código inserido não é válido. Verifique o email e tente novamente.";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['disable_2fa_email']) && $twofa_email_enabled) {
    $pass_2fa = $_POST['password_2fa_disable'] ?? '';

    if ($pass_2fa === '') {
        $erro = "Introduza a palavra-passe para desativar a autenticação em dois fatores.";
    } elseif (!password_verify($pass_2fa, $user_password_hash)) {
        $erro = "A palavra-passe indicada está incorreta.";
    } else {
        $stmt = $conn->prepare("UPDATE users_public SET twofa_enabled = 0 WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $twofa_email_enabled = 0;
            $sucesso             = "Autenticação em dois fatores por email desativada.";
        } else {
            $erro = "Não foi possível desativar a autenticação em dois fatores.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Segurança da conta</title>

  <link href="assets/img/favicon.png" rel="icon">
  <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/aos/aos.css" rel="stylesheet">
  <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
  <link href="assets/css/main.css" rel="stylesheet">

  <style>
    body.index-page {
      background:
        radial-gradient(circle at top, #1f2933 0, #020617 45%, #000 100%);
    }
    .security-wrapper {
      max-width: 1100px;
      margin: 0 auto;
    }
    .security-card-main {
      background: #ffffff;
      border-radius: 22px;
      border: 1px solid #e5e7eb;
      box-shadow: 0 30px 70px rgba(15,23,42,0.85);
      padding: 30px 28px 26px 28px;
    }
    .security-header {
      display: flex;
      align-items: center;
      gap: 14px;
      margin-bottom: 14px;
    }
    .security-icon {
      width: 56px;
      height: 56px;
      border-radius: 999px;
      background: linear-gradient(135deg,#0d6efd,#38bdf8);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #ffffff;
      box-shadow: 0 18px 40px rgba(37,99,235,0.55);
    }
    .security-title {
      font-size: 1.8rem;
      font-weight: 700;
      color: #0f172a;
    }
    .security-subtitle {
      font-size: 0.94rem;
      color: #6b7280;
    }
    .section-block {
      border-radius: 18px;
      border: 1px solid #e5e7eb;
      background: #f9fafb;
      padding: 20px 18px;
      margin-top: 18px;
      margin-bottom: 18px;
    }
    .section-title {
      font-size: 1.05rem;
      font-weight: 600;
      color: #111827;
      margin-bottom: 4px;
      text-align: left;
    }
    .section-desc {
      font-size: 0.87rem;
      color: #6b7280;
      margin-bottom: 16px;
    }
    .section-block .form-label {
      font-size: 0.86rem;
      font-weight: 600;
      color: #374151;
    }
    .section-block .form-control {
      border-radius: 10px;
      border: 1px solid #d1d5db;
      font-size: 0.94rem;
    }
    .section-block .form-control:focus {
      border-color: #0d6efd;
      box-shadow: 0 0 0 2px rgba(37,99,235,0.15);
      outline: none;
    }
    .btn-pill-primary {
      border-radius: 999px;
      padding: 9px 26px;
      font-size: 0.9rem;
      font-weight: 600;
      border: none;
      background: linear-gradient(135deg,#0d6efd,#2563eb);
      color: #ffffff;
      box-shadow: 0 12px 26px rgba(37,99,235,0.4);
    }
    .btn-pill-secondary {
      border-radius: 999px;
      padding: 9px 26px;
      font-size: 0.9rem;
      font-weight: 600;
      border: 1px solid #d1d5db;
      background: #ffffff;
      color: #374151;
    }
    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 4px 10px;
      border-radius: 999px;
      font-size: 0.78rem;
      font-weight: 600;
    }
    .status-on {
      background: #dcfce7;
      color: #166534;
    }
    .status-off {
      background: #fee2e2;
      color: #991b1b;
    }
    .password-wrapper {
      position: relative;
    }
    .password-toggle {
      position: absolute;
      top: 50%;
      right: 10px;
      transform: translateY(-50%);
      cursor: pointer;
      color: #9ca3af;
      font-size: 1rem;
    }
    .password-toggle:hover {
      color: #4b5563;
    }
  </style>
</head>
<body class="index-page">
<header id="header" class="header d-flex align-items-center fixed-top">
  <?php include "menu.php"; ?>
</header>

<main class="main" style="min-height:100vh; padding-top:120px; padding-bottom:80px;">
  <div class="container security-wrapper" data-aos="fade-up" data-aos-delay="120">
    <div class="security-card-main">
      <div class="security-header">
        <div class="security-icon">
          <i class="bi bi-shield-lock-fill"></i>
        </div>
        <div>
          <div class="security-title">Segurança da conta</div>
          <div class="security-subtitle">
            Ajuste a palavra-passe e a autenticação em dois fatores para manter a sua conta sempre protegida.
          </div>
        </div>
      </div>

      <?php if ($sucesso): ?>
        <div class="alert alert-success py-2 px-3 mb-3" style="border-radius:12px;">
          <i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($sucesso); ?>
        </div>
      <?php elseif ($erro): ?>
        <div class="alert alert-danger py-2 px-3 mb-3" style="border-radius:12px;">
          <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($erro); ?>
        </div>
      <?php endif; ?>

      <div class="section-block">
        <div class="section-title"><i class="bi bi-key-fill me-1"></i> Atualizar palavra-passe</div>
        <div class="section-desc">
          Recomendamos uma palavra-passe longa, com letras, números e símbolos para maximizar a segurança.
        </div>

        <form method="post" autocomplete="off">
          <div class="row g-3">
            <div class="col-md-4">
              <label for="current_password" class="form-label">Palavra-passe atual</label>
              <div class="password-wrapper">
                <input type="password" class="form-control" name="current_password" id="current_password" required>
                <i class="bi bi-eye-slash password-toggle" data-target="current_password"></i>
              </div>
            </div>
            <div class="col-md-4">
              <label for="new_password" class="form-label">Nova palavra-passe</label>
              <div class="password-wrapper">
                <input type="password" class="form-control" name="new_password" id="new_password" required>
                <i class="bi bi-eye-slash password-toggle" data-target="new_password"></i>
              </div>
            </div>
            <div class="col-md-4">
              <label for="confirm_password" class="form-label">Confirmar nova palavra-passe</label>
              <div class="password-wrapper">
                <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
                <i class="bi bi-eye-slash password-toggle" data-target="confirm_password"></i>
              </div>
            </div>
          </div>
          <div class="mt-3 d-flex justify-content-end">
            <button type="submit" name="change_password" class="btn btn-pill-primary">
              <i class="bi bi-check-lg me-1"></i> Guardar alterações
            </button>
          </div>
        </form>
      </div>

      <div class="section-block">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div>
            <div class="section-title"><i class="bi bi-shield-check me-1"></i> Autenticação em dois fatores (email)</div>
            <div class="section-desc mb-1">
              Ative para receber um código de confirmação no email sempre que iniciar sessão na sua conta.
            </div>
          </div>
          <div>
            <?php if ($twofa_email_enabled): ?>
              <span class="status-badge status-on">
                <i class="bi bi-check-circle-fill"></i> 2FA ativa
              </span>
            <?php else: ?>
              <span class="status-badge status-off">
                <i class="bi bi-x-circle-fill"></i> 2FA desativada
              </span>
            <?php endif; ?>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label">Email de verificação</label>
            <input type="email"
                   class="form-control"
                   value="<?php echo htmlspecialchars($user_email); ?>"
                   readonly>
          </div>
        </div>

        <?php if ($twofa_email_enabled): ?>
          <form method="post" autocomplete="off">
            <div class="row g-3">
              <div class="col-md-6">
                <label for="password_2fa_disable" class="form-label">Palavra-passe para desativar 2FA</label>
                <div class="password-wrapper">
                  <input type="password"
                         class="form-control"
                         name="password_2fa_disable"
                         id="password_2fa_disable"
                         required>
                  <i class="bi bi-eye-slash password-toggle" data-target="password_2fa_disable"></i>
                </div>
              </div>
            </div>
            <div class="mt-3 d-flex justify-content-end">
              <button type="submit" name="disable_2fa_email" class="btn btn-pill-secondary">
                <i class="bi bi-x-circle me-1"></i> Desativar autenticação em dois fatores
              </button>
            </div>
          </form>
        <?php else: ?>
          <form method="post" autocomplete="off" class="mb-3">
            <div class="d-flex justify-content-end">
              <button type="submit" name="enable_2fa_email" class="btn btn-pill-primary">
                <i class="bi bi-envelope me-1"></i> Enviar código para ativar 2FA
              </button>
            </div>
          </form>

          <form method="post" autocomplete="off">
            <div class="row g-3">
              <div class="col-md-4">
                <label for="twofa_code" class="form-label">Código recebido por email</label>
                <input type="text"
                       maxlength="6"
                       class="form-control"
                       name="twofa_code"
                       id="twofa_code"
                       placeholder="123456">
              </div>
            </div>
            <div class="mt-3 d-flex justify-content-end">
              <button type="submit" name="confirm_2fa_email" class="btn btn-pill-primary">
                <i class="bi bi-check-circle me-1"></i> Confirmar e ativar 2FA
              </button>
            </div>
          </form>
        <?php endif; ?>
      </div>

      <div class="d-flex justify-content-center mt-2">
        <a href="index.php?evora_p=inicio" class="btn btn-pill-secondary">
          <i class="bi bi-arrow-left me-1"></i> Voltar à página inicial
        </a>
      </div>
    </div>
  </div>
</main>

<a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center">
  <i class="bi bi-arrow-up-short"></i>
</a>
<div id="preloader"></div>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/aos/aos.js"></script>
<script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
<script src="assets/js/main.js"></script>

<script>
  document.querySelectorAll('.password-toggle').forEach(function(icon) {
    icon.addEventListener('click', function () {
      const targetId = this.getAttribute('data-target');
      const input = document.getElementById(targetId);
      if (!input) return;

      const isPassword = input.getAttribute('type') === 'password';
      input.setAttribute('type', isPassword ? 'text' : 'password');

      this.classList.toggle('bi-eye-slash', !isPassword);
      this.classList.toggle('bi-eye', isPassword);
    });
  });
</script>

</body>
</html>
