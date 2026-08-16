<?php

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$sucesso = '';
$erro    = '';
$step    = 'form';

$logoUrl   = 'https://lucky-plum-7yxiihmoh3.edgeone.app/logom.png';
$siteName  = 'Reporta Évora';
$publicUrl = 'http://localhost/PAP/index.php?evora_p=inicio';

function email_valido_com_mx(string $email): bool {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $partes  = explode('@', $email);
    $dominio = array_pop($partes);

    if (checkdnsrr($dominio, 'MX') ||
        checkdnsrr($dominio, 'A')  ||
        checkdnsrr($dominio, 'AAAA')) {
        return true;
    }

    return false;
}

function sendProfileChangeCode(string $toEmail, string $code, string &$errorOut = null): bool {
    global $logoUrl, $siteName, $publicUrl;

    $subject = "Código de verificação de alteração de email - {$siteName}";

    $body  = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    $body .= '<title>Código de verificação</title></head>';
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
    $body .= '<div style="font-size:12px;color:#6b7280;">Painel de utilizador</div>';
    $body .= '</div>';
    $body .= '</td></tr>';

    $body .= '<tr><td style="padding-top:18px;padding-bottom:8px;">';
    $body .= '<h2 style="margin:0;font-size:20px;line-height:1.3;color:#111827;">';
    $body .= 'Confirme a alteração do seu email</h2>';
    $body .= '</td></tr>';

    $body .= '<tr><td style="padding-bottom:10px;">';
    $body .= '<p style="margin:0;color:#4b5563;font-size:14px;line-height:1.6;">';
    $body .= 'Recebemos um pedido para alterar o email da sua conta em ';
    $body .= '<strong>' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '</strong>. ';
    $body .= 'Introduza o código abaixo na página de perfil para confirmar que este email é seu.';
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

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$utilizador = $stmt->get_result()->fetch_assoc();
$stmt->close();

$avatarPath = !empty($utilizador['photo']) ? $utilizador['photo'] : '';

$nomeBase = $utilizador['name'] ?: $utilizador['username'];
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_perfil'])) {
    $nome       = trim($_POST['name'] ?? '');
    $emailNovo  = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['full_phone'] ?? '');
    $nascimento = $_POST['birthday'] ?? null;
    $genero     = $_POST['gender'] ?? null;
    $foto_name  = $utilizador['photo'];

    $quer_remover_foto = isset($_POST['remover_foto']) && $_POST['remover_foto'] === '1';

    if ($quer_remover_foto) {
        if (!empty($foto_name) && file_exists($foto_name)) {
            @unlink($foto_name);
        }
        $foto_name = null;
    }

    if (!$quer_remover_foto && !empty($_FILES['photo']['name'])) {
        $target_dir = 'uploads/fotos/';
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $ext     = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png'];
        $newname = 'user_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;

        if (!in_array($ext, $allowed)) {
            $erro = "Formato de imagem inválido. Só são permitidos JPG, JPEG e PNG.";
        } elseif ($_FILES['photo']['size'] > 2 * 1024 * 1024) {
            $erro = "Tamanho máximo: 2MB.";
        } elseif (move_uploaded_file($_FILES['photo']['tmp_name'], $target_dir . $newname)) {
            if (!empty($utilizador['photo']) && file_exists($utilizador['photo'])) {
                @unlink($utilizador['photo']);
            }
            $foto_name = $target_dir . $newname;
        } else {
            $erro = "Falha ao guardar a foto.";
        }
    }

    if (empty($erro)) {
        if ($nome === '' || $emailNovo === '' || $genero === '') {
            $erro = "Nome, Email e Género são obrigatórios.";
        } elseif (!email_valido_com_mx($emailNovo)) {
            $erro = "O email não é válido ou o domínio não aceita emails.";
        } elseif ($phone === '') {
            $erro = "O número de telefone é obrigatório.";
        } elseif (!preg_match('/^\+[1-9][0-9]{6,14}$/', $phone)) {
            $erro = "Número de telefone inválido.";
        }
    }

    if (empty($erro) && !empty($nascimento)) {
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

    if (empty($erro)) {
        $stmt = $conn->prepare("
            SELECT id
            FROM users
            WHERE phone = ?
              AND id <> ?
            LIMIT 1
        ");
        $stmt->bind_param("si", $phone, $_SESSION['user_id']);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $erro = "Este número de telefone já está a ser utilizado por outro utilizador.";
        }

        $stmt->close();
    }

    if (empty($erro)) {
        $emailAtual = $utilizador['email'];
        $emailMudou = (strcasecmp($emailAtual, $emailNovo) !== 0);

        $stmt = $conn->prepare("
            UPDATE users
            SET name = ?, phone = ?, birthday = ?, gender = ?, photo = ?
            WHERE id = ?
        ");
        $stmt->bind_param(
            "sssssi",
            $nome,
            $phone,
            $nascimento,
            $genero,
            $foto_name,
            $_SESSION['user_id']
        );

        if (!$stmt->execute()) {
            $erro = "Erro ao atualizar perfil: " . $stmt->error;
            $stmt->close();
        } else {
            $stmt->close();

            $utilizador['name']     = $nome;
            $utilizador['phone']    = $phone;
            $utilizador['birthday'] = $nascimento;
            $utilizador['gender']   = $genero;
            $utilizador['photo']    = $foto_name;

            if (!$emailMudou) {
                $sucesso = "Perfil atualizado com sucesso.";
            } else {
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
                $stmt->bind_param("si", $emailNovo, $_SESSION['user_id']);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $erro = "Este email já está a ser utilizado por outro utilizador.";
                    $stmt->close();
                } else {
                    $stmt->close();

                    $code       = (string) random_int(100000, 999999);
                    $expires_at = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');
                    $created_at = date('Y-m-d H:i:s');

                    $del = $conn->prepare("DELETE FROM pending_user_email_changes WHERE user_id = ?");
                    $del->bind_param("i", $_SESSION['user_id']);
                    $del->execute();
                    $del->close();

                    $usernameAtual   = $utilizador['username'];
                    $nameAtual       = $nome;
                    $birthAtual      = $nascimento;
                    $genderAtual     = $genero;
                    $phoneAtual      = $phone;
                    $newPasswordHash = null;

                    $stmt = $conn->prepare("
                        INSERT INTO pending_user_email_changes
                        (user_id, new_email, new_username, new_name, new_birthday, new_gender, new_phone, new_password_hash, code, expires_at, used, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)
                    ");
                    $stmt->bind_param(
                        "issssssssss",
                        $_SESSION['user_id'],
                        $emailNovo,
                        $usernameAtual,
                        $nameAtual,
                        $birthAtual,
                        $genderAtual,
                        $phoneAtual,
                        $newPasswordHash,
                        $code,
                        $expires_at,
                        $created_at
                    );

                    if ($stmt->execute()) {
                        $stmt->close();

                        $mailError = null;
                        if (sendProfileChangeCode($emailNovo, $code, $mailError)) {
                            $sucesso = "Atualizámos os restantes dados. Enviámos um código de verificação para o novo email. Introduza o código abaixo para concluir a alteração de email.";
                            $step    = 'verify_email_change';

                            $_SESSION['pending_profile_change_user']  = $_SESSION['user_id'];
                            $_SESSION['pending_profile_change_email'] = $emailNovo;
                        } else {
                            $erro = "Não foi possível enviar o email com o código de verificação: " . $mailError;
                        }
                    } else {
                        $erro = "Erro ao criar registo pendente de alteração de email: " . $stmt->error;
                        $stmt->close();
                    }
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_email_code'])) {
    $code_input = trim($_POST['verification_code'] ?? '');

    if ($code_input === '') {
        $erro = "Tem de introduzir o código recebido no novo email.";
        $step = 'verify_email_change';
    } elseif (empty($_SESSION['pending_profile_change_user']) || empty($_SESSION['pending_profile_change_email'])) {
        $erro = "Sessão de verificação expirada. Por favor volte a atualizar o email no perfil.";
        $step = 'form';
    } else {
        $userId   = (int) $_SESSION['pending_profile_change_user'];
        $newEmail = $_SESSION['pending_profile_change_email'];

        $stmt = $conn->prepare("
            SELECT id, new_email, new_username, new_name, new_birthday, new_gender, new_phone, new_password_hash, expires_at, used
            FROM pending_user_email_changes
            WHERE user_id = ? AND new_email = ? AND code = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->bind_param("iss", $userId, $newEmail, $code_input);
        $stmt->execute();
        $stmt->bind_result(
            $pend_id,
            $pend_email,
            $pend_username,
            $pend_name,
            $pend_birthday,
            $pend_gender,
            $pend_phone,
            $pend_password_hash,
            $expires_at,
            $used
        );

        if ($stmt->fetch()) {
            $stmt->close();

            $now = new DateTime();
            $exp = DateTime::createFromFormat('Y-m-d H:i:s', $expires_at);

            if ($used) {
                $erro = "Este código já foi utilizado. Volte a atualizar o email no perfil.";
                $step = 'form';
            } elseif ($exp === false || $now > $exp) {
                $erro = "O código expirou. Volte a atualizar o email no perfil.";
                $step = 'form';
            } else {
                $stmt = $conn->prepare("UPDATE pending_user_email_changes SET used = 1 WHERE id = ?");
                $stmt->bind_param("i", $pend_id);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
                $stmt->bind_param("si", $pend_email, $userId);

                if ($stmt->execute()) {
                    $stmt->close();

                    $utilizador['email'] = $pend_email;

                    unset($_SESSION['pending_profile_change_user']);
                    unset($_SESSION['pending_profile_change_email']);

                    $sucesso = "Email alterado e verificado com sucesso.";
                    $step    = 'form';
                } else {
                    $erro = "Erro ao atualizar email: " . $stmt->error;
                    $stmt->close();
                    $step = 'form';
                }
            }
        } else {
            $stmt->close();
            $erro = "Código inválido. Verifique o código que recebeu no novo email.";
            $step = 'verify_email_change';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Perfil</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link
        href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.min.css"
    >
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/css/tom-select.bootstrap5.min.css">
    <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon">

    <style>
        :root {
            --bg-page: #f3f4f6;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-soft: #6b7280;
            --border-subtle: #e5e7eb;
            --primary: #435ebe;
            --primary-dark: #2e467f;
            --accent: #22c1c3;
        }
        html, body {
            height: 100%;
        }
        body {
            font-family: 'Nunito', sans-serif !important;
            background: linear-gradient(135deg, #eef2ff, #f9fafb);
            color: var(--text-main);
            margin: 0;
            display: flex;
            flex-direction: column;
        }
        #app {
            flex: 1;
            display: flex;
        }
        #main {
            flex: 1;
            min-height: 100%;
        }
        .profile-shell {
            padding-top: 16px;
            padding-bottom: 32px;
        }
        .profile-container-outer {
            max-width: 1120px;
            margin: 0 auto;
            padding: 0 12px 24px 12px;
        }
        .page-heading {
            margin-bottom: 12px;
        }
        .page-heading h2 {
            font-size: 1.9rem;
            font-weight: 800;
            color: var(--text-main);
        }
        .page-heading p {
            margin-bottom: 0;
            color: var(--text-soft);
        }
        .profile-container {
            display: grid;
            grid-template-columns: minmax(280px, 320px) minmax(0, 1fr);
            gap: 24px;
            align-items: flex-start;
        }
        .card-elevated {
            background: var(--card-bg);
            border-radius: 20px;
            box-shadow: 0 18px 45px rgba(15,23,42,0.10);
            border: 1px solid rgba(148,163,184,0.18);
        }
        .profile-left {
            padding: 22px 22px 20px 22px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .avatar-ring {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            padding: 3px;
            background: conic-gradient(from 220deg,#4f46e5,#22c1c3,#4f46e5);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
        }
        .avatar {
            width: 134px;
            height: 134px;
            border-radius: 50%;
            background: #f3f4ff;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            display: block;
        }
        .avatar-initials {
            font-weight: 700;
            font-size: 2.4rem;
            letter-spacing: 0.08em;
            color: #1d4ed8;
        }
        .profile-left-text {
            text-align: center;
        }
        .profile-name {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--text-main);
        }
        .profile-email {
            font-size: 0.9rem;
            color: var(--text-soft);
        }
        .profile-tagline {
            margin-top: 4px;
            font-size: 0.8rem;
            color: #9ca3af;
        }
        .profile-form-card {
            padding: 22px 24px 20px 24px;
        }
        .profile-form-header {
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
            gap:12px;
            margin-bottom:14px;
        }
        .profile-form-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-main);
        }
        .profile-form-sub {
            font-size: 0.86rem;
            color: var(--text-soft);
        }
        .profile-form .form-group {
            margin-bottom: 16px;
        }
        .profile-form label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 4px;
            font-size: 0.9rem;
        }
        .profile-form .form-control,
        .profile-form .iti input[type="tel"] {
            box-sizing: border-box;
            width: 100%;
            height: 44px;
            background: #f9fafb;
            border: 1px solid var(--border-subtle);
            border-radius: 10px;
            font-size: 0.93rem;
            color: var(--text-main);
            padding: 0 12px;
        }
        .profile-form .iti {
            width: 100% !important;
        }
        .photo-input-row {
            margin-bottom: 6px;
        }
        #photo {
            display:none;
        }
        .custom-file-btn {
            display:inline-flex;
            align-items:center;
            gap:6px;
            padding:6px 13px;
            border-radius:999px;
            background:#eef2ff;
            border:1px solid #c7d2fe;
            color:#4338ca;
            font-size:0.8rem;
            font-weight:600;
            cursor:pointer;
        }
        .photo-hint {
            font-size:0.78rem;
            color:var(--text-soft);
            margin-top:4px;
        }
        .btn-primary {
            background: var(--primary);
            border-radius: 999px;
            border: none;
            font-weight: 600;
            font-size: 0.94rem;
            padding-inline: 22px;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        .js-nice-select {
            padding: 0 !important;
        }
        .ts-wrapper.single .ts-control,
        .ts-wrapper.single.input-active .ts-control {
            background-color: #f9fafb !important;
            border-radius: 10px !important;
            border-color: var(--border-subtle) !important;
            min-height: 44px;
            padding: 0.375rem 0.75rem;
        }
        .ts-wrapper.single .ts-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(67,94,190,0.25);
            border-color: #435ebe;
        }
        .ts-dropdown {
            background-color: #ffffff !important;
        }
        a, a:hover, a:focus, a:active,
        .btn, h1, h2, h3, h4, h5, h6,
        th, td, label, .sidebar, .menu, .navbar {
            text-decoration: none !important;
            border-bottom: none !important;
        }
        @media (max-width: 576px) {
            .profile-container-outer {
                padding: 0 10px 24px 10px;
            }
            .profile-container {
                grid-template-columns: minmax(0,1fr);
            }
            .profile-left {
                flex-direction: column;
                text-align: center;
            }
            .profile-left-text {
                text-align: center;
            }
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
    <div id="main" class="profile-shell">
        <header class="mb-3 d-flex align-items-center">
            <a href="#" class="burger-btn d-block d-xl-none me-2">
                <i class="bi bi-justify fs-3"></i>
            </a>
        </header>

        <div class="profile-container-outer">
            <div class="page-heading">
                <h2>Perfil</h2>
                <p>Revise a sua informação pessoal e mantenha os dados sempre atualizados.</p>
            </div>

            <div class="profile-container">
                <div class="card-elevated profile-left">
                    <div class="avatar-ring">
                        <div class="avatar">
                            <?php if (!empty($avatarPath)): ?>
                                <img src="<?= htmlspecialchars($avatarPath) ?>" alt="Avatar do utilizador">
                            <?php else: ?>
                                <span class="avatar-initials"><?= htmlspecialchars($iniciais) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="profile-left-text">
                        <div class="profile-name">
                            <?= htmlspecialchars($utilizador['name'] ?: $utilizador['username']) ?>
                        </div>
                        <div class="profile-email">
                            <?= htmlspecialchars($utilizador['email']) ?>
                        </div>
                        <div class="profile-tagline">
                            Utilizador desde <?= htmlspecialchars(date('Y', strtotime($utilizador['created_at'] ?? 'now'))) ?>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-column" style="min-height: 100%;">
                    <div class="card-elevated profile-form-card mb-3">
                        <?php if ($sucesso): ?>
                            <div class="alert alert-success py-2 mb-3">
                                <?= htmlspecialchars($sucesso) ?>
                            </div>
                        <?php elseif ($erro): ?>
                            <div class="alert alert-danger py-2 mb-3">
                                <?= htmlspecialchars($erro) ?>
                            </div>
                        <?php endif; ?>

                        <div class="profile-form-header">
                            <div>
                                <div class="profile-form-title">Detalhes do perfil</div>
                                <div class="profile-form-sub">
                                    Esta informação é usada para contacto e autenticação.
                                </div>
                            </div>
                        </div>

                        <form
                            class="profile-form"
                            action=""
                            method="post"
                            enctype="multipart/form-data"
                            autocomplete="off"
                            id="profile-form"
                        >
                            <div class="photo-input-row mb-3">
                                <label class="mb-1 d-block">
                                    <i class="bi bi-image me-1"></i>
                                    Fotografia
                                </label>

                                <div class="d-flex align-items-center flex-wrap gap-2">
                                    <label for="photo" class="custom-file-btn mb-0">
                                        <i class="bi bi-upload"></i>
                                        <span>Carregar nova foto</span>
                                    </label>

                                    <?php if (!empty($utilizador['photo'])): ?>
                                        <div class="form-check mb-0">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                value="1"
                                                id="remover_foto"
                                                name="remover_foto"
                                            >
                                            <label class="form-check-label" for="remover_foto" style="font-size:0.83rem;">
                                                Remover fotografia atual
                                            </label>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <input
                                    type="file"
                                    name="photo"
                                    id="photo"
                                    accept="image/png, image/jpeg, image/jpg"
                                >

                                <div class="photo-hint">
                                    JPG ou PNG, até 2MB. A foto será recortada em formato circular.
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="name">Nome completo</label>
                                        <input
                                            type="text"
                                            name="name"
                                            id="name"
                                            class="form-control"
                                            required
                                            value="<?= htmlspecialchars($utilizador['name'] ?? '') ?>"
                                        >
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="email">Email</label>
                                        <input
                                            type="email"
                                            name="email"
                                            id="email"
                                            class="form-control"
                                            required
                                            value="<?= htmlspecialchars($utilizador['email']) ?>"
                                        >
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="phone">Telefone</label>
                                        <input
                                            type="tel"
                                            id="phone"
                                            name="phone_display"
                                            class="form-control"
                                            required
                                        >
                                        <input type="hidden" name="full_phone" id="full_phone">
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="birthday">Data de nascimento</label>
                                        <input
                                            type="date"
                                            name="birthday"
                                            id="birthday"
                                            class="form-control"
                                            value="<?= htmlspecialchars($utilizador['birthday'] ?? '') ?>"
                                            min="1950-01-01"
                                            max="<?= date('Y-m-d') ?>"
                                        >
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="gender">Género</label>
                                        <select
                                            name="gender"
                                            id="gender"
                                            class="form-control js-nice-select"
                                            required
                                        >
                                            <option value="">Selecione</option>
                                            <option value="male"   <?= $utilizador['gender'] === 'male'   ? 'selected' : '' ?>>Masculino</option>
                                            <option value="female" <?= $utilizador['gender'] === 'female' ? 'selected' : '' ?>>Feminino</option>
                                            <option value="other"  <?= $utilizador['gender'] === 'other'  ? 'selected' : '' ?>>Outro</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3 d-flex justify-content-end">
                                <button
                                    type="submit"
                                    name="atualizar_perfil"
                                    class="btn btn-primary"
                                >
                                    Guardar alterações
                                </button>
                            </div>
                        </form>
                    </div>

                    <?php if ($step === 'verify_email_change'): ?>
                        <div class="card-elevated profile-form-card flex-grow-1 d-flex flex-column">
                            <h6>Verificar novo email</h6>
                            <p style="font-size:0.9rem;color:#6b7280;">
                                Introduza o código que foi enviado para o novo endereço de email para concluir a alteração.
                            </p>
                            <form method="post" autocomplete="off" class="mt-2">
                                <div class="row g-3 align-items-center">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="verification_code">
                                                <i class="bi bi-shield-lock me-1"></i> Código de verificação
                                            </label>
                                            <input
                                                type="text"
                                                name="verification_code"
                                                id="verification_code"
                                                class="form-control"
                                                required
                                                placeholder="Ex: 123456"
                                            >
                                        </div>
                                    </div>
                                    <div class="col-md-4 d-flex align-items-end">
                                        <button
                                            type="submit"
                                            name="verify_email_code"
                                            class="btn btn-primary"
                                        >
                                            Confirmar código
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

<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js"></script>
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
    iti.setNumber("<?= htmlspecialchars($utilizador['phone']) ?>");
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

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-nice-select').forEach(function (el) {
        new TomSelect(el, {
            maxItems: 1,
            allowEmptyOption: true,
            create: false,
            plugins: {
                clear_button: { title: 'Limpar seleção' }
            }
        });
    });
});
</script>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
