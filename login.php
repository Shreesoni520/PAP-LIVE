<?php
session_start();
require './config.php';

// garantir fuso horário de Portugal (Lisboa)
date_default_timezone_set('Europe/Lisbon');

$erro = '';

// Config do email HTML público
$logoUrl   = 'https://lucky-plum-7yxiihmoh3.edgeone.app/logom.png';
$siteName  = 'Reporta Évora';
$publicUrl = 'http://localhost/PAP/index.php?evora_p=inicio';

function sendTwoFaCodeByEmail_public(string $toEmail, string $code, string $verifyUrl, string &$errorOut = null): bool {
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
    $body .= 'Utilize o código abaixo para concluir a sua sessão em ';
    $body .= '<strong>' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '</strong>.';
    $body .= '</p>';
    $body .= '</td></tr>';

    // CARD COM CÓDIGO
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

    // BOTÃO / LINK PARA A PÁGINA DE VERIFICAÇÃO
    $body .= '<tr><td style="padding-top:12px;padding-bottom:4px;text-align:left;">';
    $body .= '<a href="' . htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8') . '" ';
    $body .= 'style="display:inline-block;padding:10px 18px;border-radius:999px;';
    $body .= 'background:linear-gradient(135deg,#0ea5e9,#6366f1);color:#ffffff;';
    $body .= 'text-decoration:none;font-size:14px;font-weight:600;">';
    $body .= 'Introduzir código e concluir sessão';
    $body .= '</a>';
    $body .= '</td></tr>';

    // LINK EM TEXTO, CASO O BOTÃO NÃO FUNCIONE
    $body .= '<tr><td style="padding-top:6px;padding-bottom:4px;">';
    $body .= '<p style="margin:0;color:#6b7280;font-size:12px;line-height:1.5;">';
    $body .= 'Se o botão acima não funcionar, copie e cole este endereço no seu navegador:<br>';
    $body .= '<span style="word-break:break-all;color:#0d6efd;">' . htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8') . '</span>';
    $body .= '</p>';
    $body .= '</td></tr>';

    // AVISO
    $body .= '<tr><td style="padding-top:10px;padding-bottom:4px;">';
    $body .= '<p style="margin:0;color:#6b7280;font-size:12px;line-height:1.6;">';
    $body .= 'Não partilhe este código com ninguém. Se não pediu este código, pode ignorar este email.';
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
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $erro = 'Preencha todos os campos.';
    } else {
        $stmt = $conn->prepare(
            "SELECT id, nome, email, password_hash, twofa_enabled
             FROM users_public
             WHERE username = ? OR email = ?"
        );

        if ($stmt) {
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $user   = $result->fetch_assoc();
            $stmt->close();

            if ($user && password_verify($password, $user['password_hash'])) {

                if (!empty($user['twofa_enabled']) && (int)$user['twofa_enabled'] === 1) {

                    $stmtDel = $conn->prepare("DELETE FROM user_twofa_codes_public WHERE user_id = ?");
                    $stmtDel->bind_param("i", $user['id']);
                    $stmtDel->execute();
                    $stmtDel->close();

                    $code       = (string) random_int(100000, 999999);
                    $expires_at = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');

                    $stmtCode = $conn->prepare("
                        INSERT INTO user_twofa_codes_public (user_id, code, expires_at)
                        VALUES (?, ?, ?)
                    ");
                    $stmtCode->bind_param("iss", $user['id'], $code, $expires_at);
                    if ($stmtCode->execute()) {
                        $stmtCode->close();

                        $verifyUrl = sprintf(
                            "%s://%s%sindex.php?evora_p=verify_2fa_public",
                            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http',
                            $_SERVER['HTTP_HOST'],
                            rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/'
                        );

                        $mailError = null;
                        sendTwoFaCodeByEmail_public($user['email'], $code, $verifyUrl, $mailError);

                        $_SESSION['public_2fa_pending'] = $user['id'];

                        header("Location: index.php?evora_p=verify_2fa_public");
                        exit;
                    } else {
                        $stmtCode->close();
                        $erro = 'Erro ao gerar o código de autenticação. Tente novamente.';
                    }

                } else {
                    $_SESSION['public_user_id']   = $user['id'];
                    $_SESSION['public_user_nome'] = $user['nome'];

                    header("Location: index.php?evora_p=inicio");
                    exit;
                }

            } else {
                $erro = 'Credenciais inválidas.';
            }
        } else {
            $erro = 'Erro na base de dados.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>Iniciar sessão</title>

  <link href="assets/img/favicon.png" rel="icon">
  <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">

  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/aos/aos.css" rel="stylesheet">
  <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
  <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">

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
    <div class="container" style="position:relative; z-index:1;" data-aos="fade-up" data-aos-delay="100">
      <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">

          <div class="text-center mb-4">
            <h2 class="fw-semibold" style="color:#f9fafb; font-size:1.7rem;">
              Iniciar sessão
            </h2>
            <p class="mb-0" style="color:#9ca3af; font-size:0.9rem;">
              Entre na área de gestão de espaços verdes de Évora.
            </p>
          </div>

          <div class="card border-0"
               style="
                 border-radius:18px;
                 box-shadow:0 20px 55px rgba(15,23,42,0.7);
                 background-color:#ffffff;
               ">
            <div class="card-body p-4 p-md-5">

              <div class="d-flex align-items-center justify-content-center mb-3">
                <div style="
                      width:54px;height:54px;border-radius:999px;
                      background:#0d6efd;
                      display:flex;align-items:center;justify-content:center;
                      box-shadow:0 10px 25px rgba(13,110,253,0.45);
                    ">
                  <i class="bi bi-person-fill-lock text-white fs-3"></i>
                </div>
              </div>

              <p class="text-center mb-4" style="color:#6b7280; font-size:0.86rem;">
                Preencha os campos de forma clara para evitar erros de autenticação.
              </p>

              <?php if ($erro): ?>
                <div class="alert alert-danger py-2" role="alert">
                  <?php echo htmlspecialchars($erro); ?>
                </div>
              <?php endif; ?>

              <form action="index.php?evora_p=login" method="post" autocomplete="off">
                <div class="mb-3">
                  <label for="username" class="form-label mb-1" style="color:#111827; font-weight:600;">
                    Utilizador
                  </label>
                  <input type="text"
                         name="username"
                         id="username"
                         autocomplete="off"
                         class="form-control form-control-lg"
                         style="border-color:#d1d5db; font-size:0.95rem;"
                         placeholder="Introduza o seu nome de utilizador ou email"
                         required>
                </div>

                <div class="mb-3">
                  <label for="password" class="form-label mb-1" style="color:#111827; font-weight:600;">
                    Palavra-passe
                  </label>
                  <input type="password"
                         name="password"
                         id="password"
                         autocomplete="off"
                         class="form-control form-control-lg"
                         style="border-color:#d1d5db; font-size:0.95rem;"
                         placeholder="Introduza a sua palavra-passe"
                         required>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3"
                     style="font-size:0.82rem; color:#6b7280;">
                  <a href="index.php?evora_p=forgot_password_public"
                     style="color:#0d6efd; text-decoration:none; font-weight:600;">
                    Esqueci-me da palavra-passe
                  </a>
                  <a href="index.php?evora_p=signup"
                     style="color:#0d6efd; text-decoration:none; font-weight:600;">
                    Criar conta
                  </a>
                </div>

                <div class="d-grid mb-2">
                  <button type="submit"
                          class="btn btn-lg rounded-pill"
                          style="
                            background:linear-gradient(135deg,#0d6efd,#2563eb);
                            border:none; color:#fff; font-weight:600;
                          ">
                    Iniciar sessão
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div style="position:absolute;inset:0;pointer-events:none;z-index:0;overflow:hidden;">
      <div style="
            position:absolute;width:420px;height:420px;border-radius:999px;
            background:radial-gradient(circle,#22d3ee,transparent 60%);
            opacity:0.22;filter:blur(6px);top:10%;left:5%;
           "></div>
      <div style="
            position:absolute;width:360px;height:360px;border-radius:999px;
            background:radial-gradient(circle,#6366f1,transparent 60%);
            opacity:0.2;filter:blur(6px);bottom:-5%;right:5%;
           "></div>
    </div>
  </section>
</main>

<a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center">
  <i class="bi bi-arrow-up-short"></i>
</a>

<div id="preloader"></div>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/aos/aos.js"></script>
<script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
<script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
<script src="assets/vendor/waypoints/noframework.waypoints.js"></script>
<script src="assets/vendor/imagesloaded/imagesloaded.pkgd.min.js"></script>
<script src="assets/vendor/isotope-layout/isotope.pkgd.min.js"></script>
<script src="assets/js/main.js"></script>

</body>
</html>
