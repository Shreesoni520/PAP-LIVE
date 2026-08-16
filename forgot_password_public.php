<?php
session_start();
require './config.php';

$sucesso = '';
$erro    = '';

// Config do email HTML público (usa o mesmo branding)
$logoUrl   = 'https://lucky-plum-7yxiihmoh3.edgeone.app/logom.png';
$siteName  = 'Reporta Évora';
$publicUrl = 'http://localhost/PAP/index.php?evora_p=inicio';

function sendPasswordResetLink_public(string $toEmail, string $resetUrl, string &$errorOut = null): bool {
    global $logoUrl, $siteName, $publicUrl;

    $subject = "Recuperar palavra-passe - {$siteName}";

    $body  = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    $body .= '<title>Recuperar palavra-passe</title></head>';
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
    $body .= 'Pedido de recuperação de palavra-passe</h2>';
    $body .= '</td></tr>';

    // TEXTO INTRO
    $body .= '<tr><td style="padding-bottom:10px;">';
    $body .= '<p style="margin:0;color:#4b5563;font-size:14px;line-height:1.6;">';
    $body .= 'Foi pedido um link para alterar a palavra-passe da sua conta em ';
    $body .= '<strong>' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '</strong>.';
    $body .= '</p>';
    $body .= '</td></tr>';

    // CARD COM BOTÃO
    $body .= '<tr><td style="padding-top:10px;padding-bottom:6px;">';
    $body .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" ';
    $body .= 'style="background-color:#f9fafb;border-radius:12px;border:1px solid #e5e7eb;';
    $body .= 'padding:14px 16px;text-align:left;">';

    $body .= '<tr><td style="padding-bottom:8px;">';
    $body .= '<div style="font-size:13px;color:#6b7280;margin-bottom:4px;">Para continuar, clique no botão abaixo.</div>';
    $body .= '<div style="font-size:12px;color:#6b7280;">Este link é de uso único e expira em 15 minutos.</div>';
    $body .= '</td></tr>';

    $body .= '<tr><td style="padding-top:10px;padding-bottom:4px;">';
    $body .= '<a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '" ';
    $body .= 'style="display:inline-block;padding:10px 18px;border-radius:999px;';
    $body .= 'background:linear-gradient(135deg,#0ea5e9,#6366f1);color:#ffffff;';
    $body .= 'text-decoration:none;font-size:14px;font-weight:600;">';
    $body .= 'Alterar palavra-passe';
    $body .= '</a>';
    $body .= '</td></tr>';

    $body .= '</table>';
    $body .= '</td></tr>';

    // LINK EM TEXTO
    $body .= '<tr><td style="padding-top:6px;padding-bottom:4px;">';
    $body .= '<p style="margin:0;color:#6b7280;font-size:12px;line-height:1.5;">';
    $body .= 'Se o botão acima não funcionar, copie e cole este endereço no seu navegador:<br>';
    $body .= '<span style="word-break:break-all;color:#0d6efd;">' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '</span>';
    $body .= '</p>';
    $body .= '</td></tr>';

    // AVISO
    $body .= '<tr><td style="padding-top:10px;padding-bottom:4px;">';
    $body .= '<p style="margin:0;color:#6b7280;font-size:12px;line-height:1.6;">';
    $body .= 'Se não foi você que pediu esta recuperação, pode ignorar este email. A sua palavra-passe permanecerá inalterada.';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $erro = "Introduza o seu email.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "O email não tem um formato válido.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users_public WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($user_id);
        if ($stmt->fetch()) {
            $stmt->close();

            // apaga pedidos anteriores
            $stmtDel = $conn->prepare("DELETE FROM user_password_resets_public WHERE user_id = ?");
            $stmtDel->bind_param("i", $user_id);
            $stmtDel->execute();
            $stmtDel->close();

            $token      = bin2hex(random_bytes(32));
            $expires_at = (new DateTime('+15 minutes'))->format('Y-m-d H:i:s');

            $stmtIns = $conn->prepare("
                INSERT INTO user_password_resets_public (user_id, token, expires_at)
                VALUES (?, ?, ?)
            ");
            $stmtIns->bind_param("iss", $user_id, $token, $expires_at);
            if ($stmtIns->execute()) {
                $stmtIns->close();

                $resetUrl = sprintf(
                    "%s://%s%sindex.php?evora_p=reset_password_public&token=%s",
                    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http',
                    $_SERVER['HTTP_HOST'],
                    rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/',
                    urlencode($token)
                );

                $mailError = null;
                if (sendPasswordResetLink_public($email, $resetUrl, $mailError)) {
                    $sucesso = "Se o email existir na nossa base de dados, receberá um link de recuperação.";
                } else {
                    error_log(
                        'Password reset email failed: ' . ($mailError ?? 'unknown error')
                    );
                    $erro = "Não foi possível enviar o email com o link. Tente novamente mais tarde.";
                }
            } else {
                $stmtIns->close();
                $erro = "Erro ao gerar o pedido de recuperação. Tente novamente.";
            }

        } else {
            $stmt->close();
            $sucesso = "Se o email existir na nossa base de dados, receberá um link de recuperação.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Recuperar palavra-passe</title>

  <link href="assets/img/favicon.png" rel="icon">
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/main.css" rel="stylesheet">
</head>
<body class="index-page">
<header id="header" class="header d-flex align-items-center fixed-top">
  <?php include "menu.php"; ?>
</header>

<main class="main">
  <section class="section"
           style="
             min-height:100vh;
             display:flex;
             align-items:center;
             justify-content:center;
             padding-top:90px;
             padding-bottom:40px;
             background: radial-gradient(circle at top, #1f2933 0, #020617 45%, #000 100%);
           ">
    <div class="container" style="position:relative; z-index:1;">
      <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">

          <div class="card border-0"
               style="
                 border-radius:18px;
                 box-shadow:0 20px 55px rgba(15,23,42,0.7);
                 background-color:#ffffff;
               ">
            <div class="card-body p-4 p-md-5">
              <div class="text-center mb-3">
                <h2 class="fw-semibold" style="color:#111827; font-size:1.6rem;">
                  Esqueci-me da palavra-passe
                </h2>
                <p class="mb-0" style="color:#6b7280; font-size:0.9rem;">
                  Introduza o seu email para receber um link de recuperação.
                </p>
              </div>

              <?php if ($sucesso): ?>
                <div class="alert alert-success py-2" role="alert">
                  <?php echo htmlspecialchars($sucesso); ?>
                </div>
              <?php elseif ($erro): ?>
                <div class="alert alert-danger py-2" role="alert">
                  <?php echo htmlspecialchars($erro); ?>
                </div>

                <?php if ($erro === 'Este link já foi utilizado. Faça um novo pedido de recuperação.'): ?>
                  <!-- Quando esta mensagem aparece, NÃO mostramos o formulário -->
                  <div class="text-center mt-3">
                    <a href="index.php?evora_p=forgot_password_public"
                       style="font-size:0.9rem; color:#0d6efd; font-weight:600;">
                      Fazer novo pedido de recuperação
                    </a>
                  </div>
                <?php endif; ?>

              <?php endif; ?>

              <?php
              // Só mostra o formulário se NÃO for o erro do link já utilizado
              if (
                !$erro
                || $erro !== 'Este link já foi utilizado. Faça um novo pedido de recuperação.'
              ):
              ?>
                <form method="post" autocomplete="off">
                  <div class="mb-3">
                    <label for="email" class="form-label mb-1" style="color:#111827; font-weight:600;">
                      Email
                    </label>
                    <input type="email"
                           name="email"
                           id="email"
                           class="form-control form-control-lg"
                           style="border-color:#d1d5db; font-size:0.95rem;"
                           placeholder="Introduza o seu email"
                           required>
                  </div>

                  <div class="d-grid mb-2">
                    <button type="submit"
                            class="btn btn-lg rounded-pill"
                            style="
                              background:linear-gradient(135deg,#0d6efd,#2563eb);
                              border:none; color:#fff; font-weight:600;
                            ">
                      Enviar link
                    </button>
                  </div>
                </form>
              <?php endif; ?>

              <div class="text-center mt-3">
                <a href="index.php?evora_p=login" style="font-size:0.85rem; color:#0d6efd;">
                  Voltar ao início de sessão
                </a>
              </div>

            </div>
          </div>

        </div>
      </div>
    </div>
  </section>
</main>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
