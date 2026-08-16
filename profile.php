<?php
session_start();
require './config.php';

if (empty($_SESSION['public_user_id'])) {
    header('Location: index.php?evora_p=login');
    exit;
}

$sucesso = '';
$erro    = '';
$step    = 'form';

$userId = (int) $_SESSION['public_user_id'];

$stmt = $conn->prepare("SELECT * FROM users_public WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result     = $stmt->get_result();
$utilizador = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$utilizador) {
    $erro = 'Utilizador não encontrado.';
    $utilizador = [
        'nome'      => '',
        'username'  => '',
        'email'     => '',
        'phone'     => '',
        'birthday'  => '',
        'gender'    => '',
        'photo'     => '',
        'criado_em' => ''
    ];
}

if (!empty($utilizador['photo']) && file_exists($utilizador['photo'])) {
    $_SESSION['public_user_avatar'] = $utilizador['photo'];
} else {
    unset($_SESSION['public_user_avatar']);
}

/**
 * Avatar logic: if there is a valid photo, use it; otherwise we will show initials in HTML.
 */
$avatarPath = (!empty($utilizador['photo']) && file_exists($utilizador['photo']))
    ? $utilizador['photo']
    : '';

$nomeBase = $utilizador['nome'] ?: ($utilizador['username'] ?? 'Utilizador');
$iniciais = '';
$partes   = preg_split('/\s+/', trim($nomeBase));
if (!empty($partes[0])) {
    $iniciais = mb_strtoupper(mb_substr($partes[0], 0, 1, 'UTF-8'), 'UTF-8');
}
if (count($partes) > 1) {
    $iniciais .= mb_strtoupper(mb_substr(end($partes), 0, 1, 'UTF-8'), 'UTF-8');
}
if ($iniciais === '') {
    $iniciais = 'U';
}

$logoUrl   = 'https://lucky-plum-7yxiihmoh3.edgeone.app/logom.png';
$siteName  = 'Reporta Évora';
$publicUrl = 'http://localhost/PAP/index.php?evora_p=inicio';

function sendEmailChangeVerificationCode(string $toEmail, string $code, string &$errorOut = null): bool
{
    global $logoUrl, $siteName, $publicUrl;

    $subject = "Código de verificação para alterar email - {$siteName}";

    $body  = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    $body .= '<title>Alteração de email</title></head>';
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
    $body .= '<div style="font-size:12px;color:#6b7280;">Área do utilizador</div>';
    $body .= '</div>';
    $body .= '</td></tr>';

    $body .= '<tr><td style="padding-top:18px;padding-bottom:8px;">';
    $body .= '<h2 style="margin:0;font-size:20px;line-height:1.3;color:#111827;">';
    $body .= 'Confirme a alteração do seu email</h2>';
    $body .= '</td></tr>';

    $body .= '<tr><td style="padding-bottom:10px;">';
    $body .= '<p style="margin:0;color:#4b5563;font-size:14px;line-height:1.6;">';
    $body .= 'Foi solicitado que o email associado à sua conta em ';
    $body .= '<strong>' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '</strong> fosse alterado.';
    $body .= ' Introduza o código abaixo na página do seu perfil para confirmar a alteração.';
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

    $body .= '<tr><td style="padding-top:16px;font-size:12px;color:#6b7280;border-top:1px solid #e5e7eb;padding-bottom:4px;">';
    $body .= 'Se não pediu esta alteração, pode ignorar este email.';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remover_foto']) && !$erro) {
    $fotoAtual = $utilizador['photo'] ?? '';

    if (!empty($fotoAtual) && file_exists($fotoAtual)) {
        @unlink($fotoAtual);
    }

    $stmt = $conn->prepare("
        UPDATE users_public
        SET photo = ''
        WHERE id = ?
    ");
    $stmt->bind_param("i", $userId);

    if ($stmt->execute()) {
        $utilizador['photo'] = '';
        unset($_SESSION['public_user_avatar']);
        $sucesso = 'Fotografia removida com sucesso.';
    } else {
        $erro = 'Erro ao remover fotografia: ' . $stmt->error;
    }

    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_perfil']) && !$erro) {
    $nome       = trim($_POST['nome'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['full_phone'] ?? '');
    $nascimento = $_POST['birthday'] ?? null;
    $genero     = $_POST['gender'] ?? null;
    $foto_name  = $utilizador['photo'] ?? null;

    $email_antigo = $utilizador['email'] ?? '';
    $email_mudou  = ($email !== '' && $email !== $email_antigo);

    if (!empty($_FILES['photo']['name'])) {
        $target_dir = 'uploads/fotos_public/';
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $ext     = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png'];
        $newname = 'userpub_' . $userId . '_' . time() . '.' . $ext;

        if (!in_array($ext, $allowed)) {
            $erro = "Formato de imagem inválido. Só são permitidos JPG, JPEG e PNG.";
        } elseif ($_FILES['photo']['size'] > 2 * 1024 * 1024) {
            $erro = "Tamanho máximo: 2MB.";
        } elseif (move_uploaded_file($_FILES['photo']['tmp_name'], $target_dir . $newname)) {
            $foto_name = $target_dir . $newname;
        } else {
            $erro = "Falha ao guardar a foto.";
        }
    }

    if ($erro === '') {
        if (
            $nome === '' || $email === '' || $phone === '' ||
            $nascimento === '' || $genero === ''
        ) {
            $erro = "Todos os campos são obrigatórios (Nome, Email, Telefone, Data de nascimento e Género).";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = "O email não tem um formato válido.";
        } elseif (!preg_match('/^\+[1-9][0-9]{6,14}$/', $phone)) {
            $erro = "Número de telefone inválido.";
        }
    }

    if ($erro === '' && !empty($nascimento)) {
        $dataNasc = DateTime::createFromFormat('Y-m-d', $nascimento);

        if (!$dataNasc) {
            $erro = "Data de nascimento inválida. Use o seletor de data.";
        } else {
            $hoje = new DateTime('today');

            if ($dataNasc > $hoje) {
                $dataNasc   = $hoje;
                $nascimento = $hoje->format('Y-m-d');
            } else {
                $nascimento = $dataNasc->format('Y-m-d');
            }

            $idade = $dataNasc->diff($hoje)->y;
            if ($idade < 18) {
                $erro = "Tem de ter pelo menos 18 anos para usar esta conta.";
            }
        }
    }

    if ($erro === '') {
        $stmt = $conn->prepare("
            SELECT id FROM users_public
            WHERE phone = ? AND id <> ? LIMIT 1
        ");
        $stmt->bind_param("si", $phone, $userId);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $erro = "Este número de telefone já está a ser utilizado por outro utilizador.";
        }

        $stmt->close();
    }

    if ($erro === '') {
        if (!$email_mudou) {
            $stmt = $conn->prepare("
                UPDATE users_public
                SET nome = ?, email = ?, phone = ?, birthday = ?, gender = ?, photo = ?
                WHERE id = ?
            ");
            $stmt->bind_param(
                "ssssssi",
                $nome,
                $email,
                $phone,
                $nascimento,
                $genero,
                $foto_name,
                $userId
            );

            if ($stmt->execute()) {
                $sucesso                = "Perfil atualizado com sucesso.";
                $utilizador['nome']     = $nome;
                $utilizador['email']    = $email;
                $utilizador['phone']    = $phone;
                $utilizador['birthday'] = $nascimento;
                $utilizador['gender']   = $genero;
                $utilizador['photo']    = $foto_name;

                $_SESSION['public_user_nome'] = $nome;

                if (!empty($foto_name) && file_exists($foto_name)) {
                    $_SESSION['public_user_avatar'] = $foto_name;
                } else {
                    unset($_SESSION['public_user_avatar']);
                }
            } else {
                $erro = "Erro ao atualizar perfil: " . $stmt->error;
            }

            $stmt->close();
        } else {
            $del = $conn->prepare("DELETE FROM pending_public_email_verifications WHERE user_id = ?");
            $del->bind_param("i", $userId);
            $del->execute();
            $del->close();

            $code       = (string) random_int(100000, 999999);
            $expires_at = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');
            $created_at = date('Y-m-d H:i:s');

            $stmt = $conn->prepare("
                INSERT INTO pending_public_email_verifications
                (user_id, old_email, new_email, code, expires_at, used, created_at)
                VALUES (?, ?, ?, ?, ?, 0, ?)
            ");
            $stmt->bind_param(
                "isssss",
                $userId,
                $email_antigo,
                $email,
                $code,
                $expires_at,
                $created_at
            );

            if ($stmt->execute()) {
                $stmt->close();

                $mailError = null;
                if (sendEmailChangeVerificationCode($email, $code, $mailError)) {
                    $_SESSION['pending_email_change_user']  = $userId;
                    $_SESSION['pending_email_change_nome']  = $nome;
                    $_SESSION['pending_email_change_phone'] = $phone;
                    $_SESSION['pending_email_change_birth'] = $nascimento;
                    $_SESSION['pending_email_change_gender']= $genero;
                    $_SESSION['pending_email_change_photo'] = $foto_name;

                    $sucesso = "Enviámos um código de verificação para o novo email. Introduza o código abaixo para confirmar a alteração.";
                    $step    = 'verify_email';
                } else {
                    $erro = "Não foi possível enviar o email com o código. " . $mailError;
                }
            } else {
                $erro = "Erro ao criar registo pendente: " . $stmt->error;
                $stmt->close();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_email_change']) && !$erro) {
    $code_input = trim($_POST['verification_code'] ?? '');

    if ($code_input === '') {
        $erro = "Tem de introduzir o código recebido por email.";
        $step = 'verify_email';
    } elseif (empty($_SESSION['pending_email_change_user'])) {
        $erro = "Sessão de verificação expirada. Volte a guardar as alterações com o novo email.";
        $step = 'form';
    } else {
        $pendingUserId = (int) $_SESSION['pending_email_change_user'];

        $stmt = $conn->prepare("
            SELECT id, old_email, new_email, code, expires_at, used
            FROM pending_public_email_verifications
            WHERE user_id = ? AND code = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->bind_param("is", $pendingUserId, $code_input);
        $stmt->execute();
        $stmt->bind_result($pend_id, $old_email, $new_email, $db_code, $expires_at, $used);

        if ($stmt->fetch()) {
            $stmt->close();

            $now = new DateTime();
            $exp = DateTime::createFromFormat('Y-m-d H:i:s', $expires_at);

            if ($used) {
                $erro = "Este código já foi utilizado. Volte a guardar o perfil com o novo email.";
                $step = 'form';
            } elseif ($exp === false || $now > $exp) {
                $erro = "O código expirou. Volte a guardar o perfil com o novo email.";
                $step = 'form';
            } else {
                $stmt = $conn->prepare("UPDATE pending_public_email_verifications SET used = 1 WHERE id = ?");
                $stmt->bind_param("i", $pend_id);
                $stmt->execute();
                $stmt->close();

                $nome       = $_SESSION['pending_email_change_nome']   ?? $utilizador['nome'];
                $phone      = $_SESSION['pending_email_change_phone']  ?? $utilizador['phone'];
                $nascimento = $_SESSION['pending_email_change_birth']  ?? $utilizador['birthday'];
                $genero     = $_SESSION['pending_email_change_gender'] ?? $utilizador['gender'];
                $foto_name  = $_SESSION['pending_email_change_photo']  ?? $utilizador['photo'];

                $stmt = $conn->prepare("
                    UPDATE users_public
                    SET nome = ?, email = ?, phone = ?, birthday = ?, gender = ?, photo = ?
                    WHERE id = ?
                ");
                $stmt->bind_param(
                    "ssssssi",
                    $nome,
                    $new_email,
                    $phone,
                    $nascimento,
                    $genero,
                    $foto_name,
                    $pendingUserId
                );

                if ($stmt->execute()) {
                    $stmt->close();

                    $sucesso                = "Email alterado e perfil atualizado com sucesso.";
                    $utilizador['nome']     = $nome;
                    $utilizador['email']    = $new_email;
                    $utilizador['phone']    = $phone;
                    $utilizador['birthday'] = $nascimento;
                    $utilizador['gender']   = $genero;
                    $utilizador['photo']    = $foto_name;

                    $_SESSION['public_user_nome'] = $nome;

                    if (!empty($foto_name) && file_exists($foto_name)) {
                        $_SESSION['public_user_avatar'] = $foto_name;
                    } else {
                        unset($_SESSION['public_user_avatar']);
                    }

                    unset($_SESSION['pending_email_change_user']);
                    unset($_SESSION['pending_email_change_nome']);
                    unset($_SESSION['pending_email_change_phone']);
                    unset($_SESSION['pending_email_change_birth']);
                    unset($_SESSION['pending_email_change_gender']);
                    unset($_SESSION['pending_email_change_photo']);

                    $step = 'form';
                } else {
                    $erro = "Erro ao atualizar email: " . $stmt->error;
                    $stmt->close();
                    $step = 'form';
                }
            }
        } else {
            $stmt->close();
            $erro = "Código inválido. Verifique o código que recebeu no email.";
            $step = 'verify_email';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>O meu perfil</title>

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

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.min.css">
</head>
<body class="index-page">

<header id="header" class="header d-flex align-items-center fixed-top">
    <?php include "menu.php"; ?>
</header>

<main class="main">
  <section class="section"
           style="
             min-height:100vh;
             padding-top:110px;
             padding-bottom:70px;
             background:#0b1120;
           ">
    <div class="container" data-aos="fade-up" data-aos-delay="60">

      <div class="row justify-content-center mb-3">
        <div class="col-lg-8 text-center">
          <h2 style="color:#ffffff;font-weight:600;font-size:1.9rem;margin-bottom:4px;">
            O meu Perfil
          </h2>
          <p style="color:#9ca3af;font-size:0.92rem;margin-bottom:0;">
            Atualize os seus dados pessoais e de contacto.
          </p>
        </div>
      </div>

      <div class="row justify-content-center">
        <div class="col-lg-10">
          <div class="card border-0"
               style="
                 border-radius:20px;
                 overflow:hidden;
                 box-shadow:0 14px 40px rgba(15,23,42,0.75);
               ">
            <div class="card-header border-0"
                 style="
                   background:linear-gradient(135deg,#0ea5e9,#2563eb);
                   padding:18px 24px;
                 ">
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                  <div style="
                        width:60px;height:60px;border-radius:999px;
                        padding:2px;
                        background:rgba(15,23,42,0.25);
                   ">
                    <div style="
                          width:100%;height:100%;border-radius:999px;
                          background:#eff6ff;overflow:hidden;position:relative;
                          display:flex;align-items:center;justify-content:center;
                    ">
                      <?php if (!empty($avatarPath)): ?>
                        <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="Avatar do utilizador"
                             style="width:100%;height:100%;object-fit:cover;border-radius:999px;">
                      <?php else: ?>
                        <span style="font-weight:700;font-size:1.4rem;letter-spacing:0.08em;color:#1d4ed8;">
                          <?php echo htmlspecialchars($iniciais); ?>
                        </span>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div>
                    <div style="color:#eff6ff;font-weight:600;font-size:1.05rem;">
                      <?php
                      $nomeMostrar = $utilizador['nome'] ?? ($utilizador['username'] ?? 'Utilizador');
                      echo htmlspecialchars($nomeMostrar);
                      ?>
                    </div>
                    <div style="color:#dbeafe;font-size:0.85rem;">
                      <?php echo htmlspecialchars($utilizador['email'] ?? ''); ?>
                    </div>
                  </div>
                </div>
                <div class="d-flex gap-2">
                  <a href="index.php?evora_p=inicio"
                     class="btn btn-outline-light btn-sm rounded-pill">
                    <i class="bi bi-arrow-left-short me-1"></i> Voltar
                  </a>
                  <a href="logout.php"
                     class="btn btn-light btn-sm rounded-pill">
                    <i class="bi bi-box-arrow-right me-1"></i> Terminar sessão
                  </a>
                </div>
              </div>
            </div>

            <div class="card-body p-4 p-md-5" style="background:#f9fafb;">
              <?php if ($sucesso): ?>
                <div class="alert alert-success py-2 mb-3 text-center">
                  <?php echo htmlspecialchars($sucesso); ?>
                </div>
              <?php elseif ($erro): ?>
                <div class="alert alert-danger py-2 mb-3 text-center">
                  <?php echo htmlspecialchars($erro); ?>
                </div>
              <?php endif; ?>

              <?php if ($step === 'form'): ?>
              <form
                action=""
                method="post"
                enctype="multipart/form-data"
                autocomplete="off"
                id="profile-form"
              >
                <div class="row g-4">
                  <div class="col-md-4">
                    <h6 style="font-size:0.85rem;font-weight:600;color:#4b5563;text-transform:uppercase;letter-spacing:0.12em;">
                      Fotografia
                    </h6>
                    <p style="font-size:0.86rem;color:#6b7280;">
                      A sua foto ajuda a identificar a sua conta.
                    </p>

                    <label for="photo"
                           class="btn btn-sm btn-outline-primary rounded-pill px-3 me-2">
                      <i class="bi bi-upload me-1"></i> Carregar nova foto
                    </label>
                    <input
                      type="file"
                      name="photo"
                      id="photo"
                      accept="image/png, image/jpeg, image/jpg"
                      style="display:none;"
                    >

                    <?php if (!empty($utilizador['photo']) && file_exists($utilizador['photo'])): ?>
                      <button
                        type="submit"
                        name="remover_foto"
                        value="1"
                        class="btn btn-sm btn-outline-danger rounded-pill px-3 mt-2"
                        onclick="return confirm('Tem a certeza que pretende remover a fotografia?');"
                      >
                        <i class="bi bi-trash me-1"></i> Remover foto
                      </button>
                    <?php endif; ?>

                    <small class="text-muted d-block mt-2">
                      JPG ou PNG, até 2MB.
                    </small>
                  </div>

                  <div class="col-md-8">
                    <h6 style="font-size:0.85rem;font-weight:600;color:#4b5563;text-transform:uppercase;letter-spacing:0.12em;">
                      Dados pessoais
                    </h6>

                    <div class="row g-3 mt-1">
                      <div class="col-md-6">
                        <label for="nome" class="form-label mb-1">Nome completo</label>
                        <input
                          type="text"
                          name="nome"
                          id="nome"
                          class="form-control"
                          required
                          value="<?php echo htmlspecialchars($utilizador['nome'] ?? ''); ?>"
                        >
                      </div>
                      <div class="col-md-6">
                        <label for="email" class="form-label mb-1">Email</label>
                        <input
                          type="email"
                          name="email"
                          id="email"
                          class="form-control"
                          required
                          value="<?php echo htmlspecialchars($utilizador['email'] ?? ''); ?>"
                        >
                      </div>
                    </div>

                    <div class="row g-3 mt-1">
                      <div class="col-md-4">
                        <label for="phone" class="form-label mb-1">Telefone</label>
                        <input
                          type="tel"
                          id="phone"
                          name="phone_display"
                          class="form-control"
                          required
                        >
                        <input type="hidden" name="full_phone" id="full_phone">
                      </div>
                      <div class="col-md-4">
                        <label for="birthday" class="form-label mb-1">Data de nascimento</label>
                        <input
                          type="date"
                          name="birthday"
                          id="birthday"
                          class="form-control"
                          required
                          value="<?php echo htmlspecialchars($utilizador['birthday'] ?? ''); ?>"
                          min="1950-01-01"
                          max="<?php echo date('Y-m-d'); ?>"
                        >
                      </div>
                      <div class="col-md-4">
                        <label for="gender" class="form-label mb-1">Género</label>
                        <select
                          name="gender"
                          id="gender"
                          class="form-control"
                          required
                        >
                          <option value="">Selecione</option>
                          <option value="male"   <?php echo ($utilizador['gender'] ?? '') === 'male'   ? 'selected' : ''; ?>>Masculino</option>
                          <option value="female" <?php echo ($utilizador['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Feminino</option>
                          <option value="other"  <?php echo ($utilizador['gender'] ?? '') === 'other'  ? 'selected' : ''; ?>>Outro</option>
                        </select>
                      </div>
                    </div>

                    <div class="mt-4 d-flex justify-content-end">
                      <button
                        type="submit"
                        name="atualizar_perfil"
                        class="btn btn-primary btn-sm rounded-pill px-4"
                        style="font-weight:600;"
                      >
                        Guardar alterações
                      </button>
                    </div>
                  </div>
                </div>
              </form>

              <?php elseif ($step === 'verify_email'): ?>
              <div class="mt-3">
                <h6 style="font-size:0.95rem;font-weight:600;color:#111827;">
                  Confirmar novo email
                </h6>
                <p style="font-size:0.9rem;color:#4b5563;">
                  Introduza o código de 6 dígitos que foi enviado para o novo endereço de email.
                </p>

                <form method="post" autocomplete="off">
                  <div class="row g-3 align-items-end">
                    <div class="col-sm-4">
                      <label for="verification_code" class="form-label mb-1">
                        Código de verificação
                      </label>
                      <input
                        type="text"
                        name="verification_code"
                        id="verification_code"
                        class="form-control"
                        required
                        maxlength="6"
                        placeholder="Ex: 123456"
                      >
                    </div>
                    <div class="col-sm-4">
                      <button
                        type="submit"
                        name="verify_email_change"
                        class="btn btn-primary mt-3 mt-sm-0"
                      >
                        Confirmar código e alterar email
                      </button>
                    </div>
                  </div>
                </form>
              </div>
              <?php endif; ?>

            </div>
          </div>

        </div>
      </div>
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
const phoneInput     = document.querySelector("#phone");
const fullPhoneInput = document.querySelector("#full_phone");
const form           = document.querySelector("#profile-form");

if (phoneInput && fullPhoneInput && form) {
  const iti = window.intlTelInput(phoneInput, {
    initialCountry: "pt",
    separateDialCode: true,
    preferredCountries: ["pt","br","fr","es","uk"],
    utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js"
  });

  <?php if (!empty($utilizador['phone'])): ?>
  iti.setNumber("<?php echo htmlspecialchars($utilizador['phone']); ?>");
  <?php endif; ?>

  form.addEventListener("submit", function (e) {
    if (phoneInput.value.trim()) {
      if (!iti.isValidNumber()) {
        e.preventDefault();
        alert("Por favor introduza um número de telefone válido.");
        return;
      }
      fullPhoneInput.value = iti.getNumber();
    } else {
      fullPhoneInput.value = "";
    }
  });
}
</script>

</body>
</html>
