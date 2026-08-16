<?php
session_start();
require './config.php';

$erro    = '';
$sucesso = '';
$step    = 'form';

$logoUrl   = 'https://lucky-plum-7yxiihmoh3.edgeone.app/logom.png';
$siteName  = 'Reporta Évora';
$publicUrl = 'http://localhost/PAP/index.php?evora_p=inicio';

function sendNewUserVerificationCode(string $toEmail, string $code, string &$errorOut = null): bool {
    global $logoUrl, $siteName, $publicUrl;

    $subject = "Código de verificação de email - {$siteName}";

    $body  = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    $body .= '<title>Código de verificação de email</title></head>';
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
    $body .= '<a href="' . htmlspecialchars($publicUrl, ENT_QUOTES, 'UTF-8') . '" style="text-decoration:none;display:inline-block;">';
    $body .= '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '"'
          . ' style="max-width:220px;height:auto;display:block;margin:0 auto 6px auto;border:0;">';
    $body .= '</a>';
    $body .= '<div style="font-size:12px;color:#6b7280;">Área pública</div>';
    $body .= '</div>';
    $body .= '</td></tr>';

    $body .= '<tr><td style="padding-top:18px;padding-bottom:8px;">';
    $body .= '<h2 style="margin:0;font-size:20px;line-height:1.3;color:#111827;">';
    $body .= 'Verifique o seu endereço de email</h2>';
    $body .= '</td></tr>';

    $body .= '<tr><td style="padding-bottom:10px;">';
    $body .= '<p style="margin:0;color:#4b5563;font-size:14px;line-height:1.6;">';
    $body .= 'Foi solicitado o registo de uma nova conta em ';
    $body .= '<strong>' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '</strong>. ';
    $body .= 'Introduza o código abaixo na página de registo para confirmar que este email é válido.';
    $body .= '</p>';
    $body .= '</td></tr>';

    $body .= '<tr><td style="padding-top:10px;padding-bottom:6px;">';
    $body .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" ';
    $body .= 'style="background-color:#f9fafb;border-radius:12px;border:1px solid #e5e7eb;';
    $body .= 'padding:14px 16px;text-align:center;">';

    $body .= '<tr><td style="padding-bottom:8px;">';
    $body .= '<div style="font-size:12px;color:#6b7280;font-weight:600;margin-bottom:4px;">Código de verificação</div>';
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
    $body .= 'Se não reconhece este pedido, pode simplesmente ignorar este email.';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup'])) {
    $nome       = trim($_POST['nome'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $username   = trim($_POST['username'] ?? '');
    $birthday   = $_POST['birthday'] ?? '';
    $gender     = $_POST['gender'] ?? '';
    $phone_full = trim($_POST['phone_full'] ?? '');
    $pass1      = $_POST['password'] ?? '';
    $pass2      = $_POST['password2'] ?? '';

    if (
        $nome === '' || $email === '' || $username === '' ||
        $birthday === '' || $gender === '' ||
        $phone_full === '' || $pass1 === '' || $pass2 === ''
    ) {
        $erro = 'Preencha todos os campos.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Email inválido.';
    } elseif (!preg_match('/^[a-zA-Z]{3,32}$/', $username)) {
        $erro = "Nome de utilizador inválido. Deve conter apenas letras e ter entre 3 e 32 caracteres (sem números).";
    } elseif (strlen($pass1) < 8) {
        $erro = 'A palavra-passe deve ter pelo menos 8 caracteres.';
    } elseif ($pass1 !== $pass2) {
        $erro = 'As palavras-passe não coincidem.';
    } elseif (!preg_match('/^\+[1-9][0-9]{6,14}$/', $phone_full)) {
        $erro = "Número de telefone inválido.";
    }

    if ($erro === '' && $birthday !== '') {
        $dataNasc = DateTime::createFromFormat('Y-m-d', $birthday);

        if (!$dataNasc) {
            $erro = "Data de nascimento inválida. Use o seletor de data.";
        } else {
            $hoje = new DateTime('today');

            if ($dataNasc > $hoje) {
                $dataNasc = $hoje;
                $birthday = $hoje->format('Y-m-d');
            } else {
                $birthday = $dataNasc->format('Y-m-d');
            }

            $idade = $dataNasc->diff($hoje)->y;
            if ($idade < 18) {
                $erro = "Tem de ter pelo menos 18 anos para criar uma conta.";
            }
        }
    }

    if ($erro === '') {
        $stmt = $conn->prepare("
            SELECT id FROM users_public
            WHERE email = ? OR username = ? OR phone = ?
        ");
        if ($stmt) {
            $stmt->bind_param("sss", $email, $username, $phone_full);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $erro = 'Já existe uma conta com esse email, nome de utilizador ou telefone.';
            }
            $stmt->close();
        } else {
            $erro = 'Erro interno na base de dados.';
        }
    }

    if ($erro === '') {
        $hash       = password_hash($pass1, PASSWORD_DEFAULT);
        $criado_em  = date('Y-m-d H:i:s');
        $code       = (string) random_int(100000, 999999);
        $expires_at = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');

        $del = $conn->prepare("DELETE FROM pending_user_verifications WHERE email = ?");
        if ($del) {
            $del->bind_param("s", $email);
            $del->execute();
            $del->close();
        }

        $stmtIns = $conn->prepare("
            INSERT INTO pending_user_verifications
                (email, username, password_hash, name, birthday, gender, phone, code, expires_at, used, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)
        ");
        if ($stmtIns) {
            $stmtIns->bind_param(
                "ssssssssss",
                $email,
                $username,
                $hash,
                $nome,
                $birthday,
                $gender,
                $phone_full,
                $code,
                $expires_at,
                $criado_em
            );

            if ($stmtIns->execute()) {
                $stmtIns->close();

                $mailError = null;
                if (sendNewUserVerificationCode($email, $code, $mailError)) {
                    $sucesso = "Enviámos um código de verificação para o seu email. Introduza o código abaixo para concluir o registo.";
                    $step    = 'verify';
                    $_SESSION['pending_signup_public_email'] = $email;
                } else {
                    $erro = "Não foi possível enviar o email com o código. " . $mailError;
                }
            } else {
                $erro = 'Erro ao criar registo pendente.';
                $stmtIns->close();
            }
        } else {
            $erro = 'Erro interno na base de dados (prepare).';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_code'])) {
    $code_input = trim($_POST['verification_code'] ?? '');

    if ($code_input === '') {
        $erro = "Tem de introduzir o código recebido por email.";
        $step = 'verify';
    } elseif (empty($_SESSION['pending_signup_public_email'])) {
        $erro = "Sessão de verificação expirada. Volte a efetuar o registo.";
        $step = 'form';
    } else {
        $email_pending = $_SESSION['pending_signup_public_email'];

        $stmt = $conn->prepare("
            SELECT id, username, password_hash, name, birthday, gender, phone, expires_at, used
            FROM pending_user_verifications
            WHERE email = ? AND code = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param("ss", $email_pending, $code_input);
            $stmt->execute();
            $stmt->bind_result(
                $pend_id,
                $pend_username,
                $pend_password_hash,
                $pend_nome,
                $pend_birthday,
                $pend_gender,
                $pend_phone,
                $expires_at,
                $used
            );

            if ($stmt->fetch()) {
                $stmt->close();

                $now = new DateTime();
                $exp = DateTime::createFromFormat('Y-m-d H:i:s', $expires_at);

                if ($used) {
                    $erro = "Este código já foi utilizado. Volte a efetuar o registo.";
                    $step = 'form';
                } elseif ($exp === false || $now > $exp) {
                    $erro = "O código expirou. Volte a efetuar o registo.";
                    $step = 'form';
                } else {
                    $stmtUp = $conn->prepare("UPDATE pending_user_verifications SET used = 1 WHERE id = ?");
                    if ($stmtUp) {
                        $stmtUp->bind_param("i", $pend_id);
                        $stmtUp->execute();
                        $stmtUp->close();
                    }

                    $criado_em = date('Y-m-d H:i:s');
                    $insert = $conn->prepare("
                        INSERT INTO users_public
                            (nome, email, username, password_hash, phone, birthday, gender, criado_em)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    if ($insert) {
                        $insert->bind_param(
                            "ssssssss",
                            $pend_nome,
                            $email_pending,
                            $pend_username,
                            $pend_password_hash,
                            $pend_phone,
                            $pend_birthday,
                            $pend_gender,
                            $criado_em
                        );

                        if ($insert->execute()) {
                            $insert->close();
                            unset($_SESSION['pending_signup_public_email']);
                            header('Location: index.php?evora_p=login&msg=verified');
                            exit;
                        } else {
                            $erro = 'Erro ao criar conta.';
                            $insert->close();
                            $step = 'form';
                        }
                    } else {
                        $erro = 'Erro interno na base de dados (prepare insert).';
                        $step = 'form';
                    }
                }
            } else {
                $stmt->close();
                $erro = "Código inválido. Verifique o código que recebeu no email.";
                $step = 'verify';
            }
        } else {
            $erro = "Erro interno na base de dados (prepare select).";
            $step = 'form';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Criar conta</title>

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

  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.min.css">

  <style>
    .password-wrapper {
      position: relative;
    }
    .password-wrapper input {
      padding-right: 3rem;
    }
    .password-toggle-icon {
      position: absolute;
      top: 50%;
      right: 0.95rem;
      transform: translateY(-50%);
      cursor: pointer;
      color: #6b7280;
      font-size: 1.1rem;
    }
    .password-toggle-icon:hover {
      color: #111827;
    }
    .iti {
      width: 100%;
    }
  </style>
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
        <div class="col-md-9 col-lg-7">

          <div class="text-center mb-4">
            <h2 class="fw-semibold" style="color:#f9fafb; font-size:1.7rem;">
              Criar Conta
            </h2>
            <p class="mb-0" style="color:#9ca3af; font-size:0.9rem;">
              Registe-se para poder iniciar sessão na área reservada.
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
                  <i class="bi bi-person-plus-fill text-white fs-3"></i>
                </div>
              </div>

              <?php if ($erro): ?>
                <div class="alert alert-danger py-2" role="alert">
                  <?php echo htmlspecialchars($erro); ?>
                </div>
              <?php elseif ($sucesso): ?>
                <div class="alert alert-success py-2" role="alert">
                  <?php echo htmlspecialchars($sucesso); ?>
                </div>
              <?php endif; ?>

              <?php if ($step === 'form'): ?>
              <form action="index.php?evora_p=signup" method="post" autocomplete="off" id="signupForm">
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label for="nome" class="form-label mb-1" style="color:#111827; font-weight:600;">Nome completo
                    </label>
                    <input type="text"
                           name="nome"
                           id="nome"
                           autocomplete="off"
                           class="form-control form-control-lg"
                           style="border-color:#d1d5db; font-size:0.95rem;"
                           value="<?php echo isset($nome) ? htmlspecialchars($nome) : ''; ?>"
                           required>
                  </div>

                  <div class="col-md-6 mb-3">
                    <label for="username" class="form-label mb-1" style="color:#111827; font-weight:600;">Nome de utilizador
                    </label>
                    <input type="text"
                           name="username"
                           id="username"
                           autocomplete="off"
                           class="form-control form-control-lg"
                           style="border-color:#d1d5db; font-size:0.95rem;"
                           pattern="[a-zA-Z]{3,32}"
                           title="3 a 32 letras maiúsculas ou minúsculas, sem números nem caracteres especiais"
                           required>
                  </div>
                </div>

                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label for="email" class="form-label mb-1" style="color:#111827; font-weight:600;">Email
                    </label>
                    <input type="email"
                           name="email"
                           id="email"
                           autocomplete="off"
                           class="form-control form-control-lg"
                           style="border-color:#d1d5db; font-size:0.95rem;"
                           value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>"
                           required>
                  </div>

                  <div class="col-md-6 mb-3">
                    <label for="birthday" class="form-label mb-1" style="color:#111827; font-weight:600;">Data de nascimento
                    </label>
                    <input type="date"
                           name="birthday"
                           id="birthday"
                           class="form-control form-control-lg"
                           style="border-color:#d1d5db; font-size:0.95rem;"
                           value="<?php echo isset($birthday) ? htmlspecialchars($birthday) : ''; ?>"
                           required
                           min="1950-01-01"
                           max="<?php echo date('Y-m-d'); ?>">
                  </div>
                </div>

                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label for="gender" class="form-label mb-1" style="color:#111827; font-weight:600;">Género
                    </label>
                    <select name="gender"
                            id="gender"
                            class="form-control form-control-lg"
                            style="border-color:#d1d5db; font-size:0.95rem;"
                            required>
                      <option value="">Selecione...</option>
                      <option value="male"   <?php echo (isset($gender) && $gender === 'male')   ? 'selected' : ''; ?>>Masculino</option>
                      <option value="female" <?php echo (isset($gender) && $gender === 'female') ? 'selected' : ''; ?>>Feminino</option>
                      <option value="other"  <?php echo (isset($gender) && $gender === 'other')  ? 'selected' : ''; ?>>Outro</option>
                    </select>
                  </div>

                  <div class="col-md-6 mb-3">
                    <label for="phone" class="form-label mb-1" style="color:#111827; font-weight:600;">Telefone
                    </label>
                    <input type="tel"
                           id="phone"
                           name="phone"
                           class="form-control form-control-lg"
                           style="border-color:#d1d5db; font-size:0.95rem;"
                           required>
                    <input type="hidden" name="phone_full" id="phone_full">
                  </div>
                </div>

                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label for="password" class="form-label mb-1" style="color:#111827; font-weight:600;">Palavra-passe
                    </label>
                    <div class="password-wrapper">
                      <input type="password"
                             name="password"
                             id="password"
                             autocomplete="off"
                             class="form-control form-control-lg"
                             style="border-color:#d1d5db; font-size:0.95rem;"
                             minlength="8"
                             required>
                      <span class="password-toggle-icon" id="togglePassword">
                        <i class="bi bi-eye"></i>
                      </span>
                    </div>
                  </div>

                  <div class="col-md-6 mb-3">
                    <label for="password2" class="form-label mb-1" style="color:#111827; font-weight:600;">Confirmar palavra-passe
                    </label>
                    <div class="password-wrapper">
                      <input type="password"
                             name="password2"
                             id="password2"
                             autocomplete="off"
                             class="form-control form-control-lg"
                             style="border-color:#d1d5db; font-size:0.95rem;"
                             minlength="8"
                             required>
                      <span class="password-toggle-icon" id="togglePassword2">
                        <i class="bi bi-eye"></i>
                      </span>
                    </div>
                  </div>
                </div>

                <div class="d-grid mb-2">
                  <button type="submit"
                          name="signup"
                          class="btn btn-lg rounded-pill"
                          style="
                            background:linear-gradient(135deg,#0d6efd,#2563eb);
                            border:none; color:#fff; font-weight:600;
                           ">
                    Criar conta (enviar código)
                  </button>
                </div>

                <p class="text-center mb-0" style="font-size:0.82rem; color:#6b7280;">
                  Já tem conta?
                  <a href="index.php?evora_p=login"
                     style="color:#0d6efd; text-decoration:none; font-weight:600;">
                    Iniciar sessão
                  </a>
                </p>
              </form>
              <?php endif; ?>

              <?php if ($step === 'verify'): ?>
              <div class="mt-4">
                <div class="text-center mb-3">
                  <div style="
                    width:64px;height:64px;border-radius:999px;
                    background:linear-gradient(135deg,#0d6efd,#2563eb);
                    display:flex;align-items:center;justify-content:center;
                    box-shadow:0 14px 32px rgba(37,99,235,0.55);
                    margin:0 auto 10px auto;
                  ">
                    <i class="bi bi-shield-lock-fill text-white fs-2"></i>
                  </div>
                  <h5 class="fw-semibold mb-1" style="color:#111827;">Verificar o seu email</h5>
                  <p style="font-size:0.9rem;color:#6b7280;max-width:320px;margin:0 auto;">
                    Introduza o código de segurança de 6 dígitos que foi enviado para o seu email para concluir o registo.
                  </p>
                </div>

                <div class="border rounded-3 p-3 p-md-4" style="background:#f9fafb;border-color:#e5e7eb;">
                  <form method="post" autocomplete="off" class="mt-1">
                    <div class="d-flex justify-content-center mb-3">
                      <div style="max-width:260px;width:100%;">
                        <label for="verification_code" class="form-label mb-1" style="color:#111827; font-weight:600;">
                          Código de segurança
                        </label>
                        <div class="input-group input-group-lg">
                          <span class="input-group-text bg-white" style="border-color:#d1d5db;">
                            <i class="bi bi-lock-fill text-secondary"></i>
                          </span>
                          <input type="text"
                                 name="verification_code"
                                 id="verification_code"
                                 class="form-control"
                                 style="border-color:#d1d5db; font-size:1.1rem; letter-spacing:0.35em; text-align:center;"
                                 maxlength="6"
                                 required
                                 placeholder="• • • • • •">
                        </div>
                        <small class="d-block mt-2 text-muted" style="font-size:0.78rem;">
                          O código expira em 10 minutos. Se não pediu este registo, pode ignorar este email.
                        </small>
                      </div>
                    </div>

                    <div class="d-grid mb-1">
                      <button type="submit" name="verify_code"
                              class="btn btn-lg rounded-pill"
                              style="background:linear-gradient(135deg,#0d6efd,#2563eb);border:none;color:#fff;font-weight:600;">
                        Confirmar código e criar conta
                      </button>
                    </div>
                  </form>
                </div>
              </div>
              <?php endif; ?>

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

<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js"></script>

<script>
  function setupPasswordToggle(inputId, toggleId) {
    const input  = document.getElementById(inputId);
    const toggle = document.getElementById(toggleId);
    const icon   = toggle.querySelector('i');

    toggle.addEventListener('click', function () {
      const isPassword = input.getAttribute('type') === 'password';
      input.setAttribute('type', isPassword ? 'text' : 'password');
      icon.classList.toggle('bi-eye');
      icon.classList.toggle('bi-eye-slash');
    });
  }

  setupPasswordToggle('password', 'togglePassword');
  setupPasswordToggle('password2', 'togglePassword2');

  const phoneInput   = document.querySelector("#phone");
  const fullPhoneInp = document.querySelector("#phone_full");
  const form         = document.querySelector("#signupForm");

  let iti = null;
  if (phoneInput) {
    iti = window.intlTelInput(phoneInput, {
      initialCountry: "pt",
      separateDialCode: true,
      preferredCountries: ["pt","br","fr","es","uk"],
      utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js"
    });
  }

  if (form && iti) {
    form.addEventListener("submit", function (e) {
      if (phoneInput.value.trim()) {
        if (!iti.isValidNumber()) {
          e.preventDefault();
          alert("Por favor introduza um número de telefone válido.");
          return;
        }
        fullPhoneInp.value = iti.getNumber();
      } else {
        fullPhoneInp.value = "";
      }
    });
  }
</script>

</body>
</html>
