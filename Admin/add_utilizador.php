<?php
include './log.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (empty($_SESSION['is_admin']) || (int) $_SESSION['is_admin'] !== 1) {
    header('Location: index.php?evora=inicio');
    exit();
}

$success = '';
$error   = '';
$step    = 'form'; // form | verify

// Config do email HTML (igual estilo security.php)
$logoUrl   = 'https://lucky-plum-7yxiihmoh3.edgeone.app/logom.png';
$siteName  = 'Reporta Évora';
$publicUrl = 'http://localhost/PAP/index.php?evora_p=inicio';

// Função de envio de email com código para validar email do novo utilizador
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

    // HEADER LOGO
    $body .= '<tr><td style="padding-bottom:18px;border-bottom:1px solid #e5e7eb;">';
    $body .= '<div style="text-align:center;">';
    $body .= '<a href="' . htmlspecialchars($publicUrl, ENT_QUOTES, 'UTF-8') . '" style="text-decoration:none;display:inline-block;">';
    $body .= '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '"'
          . ' style="max-width:220px;height:auto;display:block;margin:0 auto 6px auto;border:0;">';
    $body .= '</a>';
    $body .= '<div style="font-size:12px;color:#6b7280;">Painel de administração</div>';
    $body .= '</div>';
    $body .= '</td></tr>';

    // TÍTULO
    $body .= '<tr><td style="padding-top:18px;padding-bottom:8px;">';
    $body .= '<h2 style="margin:0;font-size:20px;line-height:1.3;color:#111827;">';
    $body .= 'Verifique o endereço de email do novo utilizador</h2>';
    $body .= '</td></tr>';

    // TEXTO INTRO
    $body .= '<tr><td style="padding-bottom:10px;">';
    $body .= '<p style="margin:0;color:#4b5563;font-size:14px;line-height:1.6;">';
    $body .= 'Foi solicitado o registo de um novo utilizador no sistema ';
    $body .= '<strong>' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '</strong>. ';
    $body .= 'Introduza o código abaixo no painel de administração para confirmar que este email é válido.';
    $body .= '</p>';
    $body .= '</td></tr>';

    // CARD COM CÓDIGO
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

    // AVISO
    $body .= '<tr><td style="padding-top:10px;padding-bottom:4px;">';
    $body .= '<p style="margin:0;color:#6b7280;font-size:12px;line-height:1.6;">';
    $body .= 'Se não reconhece este pedido, pode simplesmente ignorar este email.';
    $body .= '</p>';
    $body .= '</td></tr>';

    // FOOTER
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

// 1) ADMIN SUBMETE FORM PARA CRIAR UTILIZADOR (PASSO 1 - ENVIAR CÓDIGO)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_utilizador'])) {
    $email            = trim($_POST['email'] ?? '');
    $username         = trim($_POST['username'] ?? '');
    $name             = trim($_POST['name'] ?? '');
    $birthday         = $_POST['birthday'] ?? '';
    $gender           = $_POST['gender'] ?? '';
    $phone_full       = trim($_POST['phone_full'] ?? '');
    $password         = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    // Tipo de utilizador (funcionario / admin) vindo do dropdown
    $role             = $_POST['role'] ?? '';

    // VALIDAÇÕES
    if (
        $email === '' || $username === '' || $name === '' || $birthday === '' ||
        $gender === '' || $password === '' || $confirm_password === '' ||
        $phone_full === '' || $role === ''
    ) {
        $error = 'Todos os campos são obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email inválido.';
    // ALTERADO: permite letras + underscore, 3–32 caracteres
    } elseif (!preg_match('/^[a-zA-Z_]{3,32}$/', $username)) {
        $error = "Nome de utilizador inválido. Deve conter apenas letras ou underscore e ter entre 3 e 32 caracteres.";
    } elseif (strlen($password) < 8) {
        $error = "A palavra-passe deve ter pelo menos 8 caracteres.";
    } elseif ($password !== $confirm_password) {
        $error = "As palavras-passe não coincidem.";
    } elseif (!preg_match('/^\+[1-9][0-9]{6,14}$/', $phone_full)) {
        $error = "Número de telefone inválido.";
    } elseif (!in_array($role, ['funcionario', 'admin'], true)) {
        $error = "Tipo de utilizador inválido.";
    }

    // VERIFICA IDADE
    if (!$error && $birthday !== '') {
        $dataNasc = DateTime::createFromFormat('Y-m-d', $birthday);
        if (!$dataNasc) {
            $error = "Data de nascimento inválida.";
        } else {
            $hoje  = new DateTime('today');
            $idade = $dataNasc->diff($hoje)->y;
            if ($idade < 18) {
                $error = "O utilizador deve ter pelo menos 18 anos.";
            }
        }
    }

    // VERIFICA SE JÁ EXISTE UTILIZADOR COM MESMO EMAIL/USERNAME/TELEFONE
    if (!$error) {
        $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? OR username = ? OR phone = ?');
        $stmt->bind_param('sss', $email, $username, $phone_full);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = 'Já existe um utilizador com este email, nome de utilizador ou telefone.';
        }
        $stmt->close();
    }

    if (!$error) {
        // GERAR CÓDIGO E GUARDAR EM pending_user_verifications
        $code         = (string) random_int(100000, 999999);
        $expires_at   = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');
        $created_at   = date('Y-m-d H:i:s');
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // limpar registos pendentes antigos para este email (opcional)
        $del = $conn->prepare("DELETE FROM pending_user_verifications WHERE email = ?");
        $del->bind_param("s", $email);
        $del->execute();
        $del->close();

        $stmt = $conn->prepare('
            INSERT INTO pending_user_verifications
            (email, username, password_hash, name, birthday, gender, phone, code, expires_at, used, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)
        ');
        $stmt->bind_param(
            'ssssssssss',
            $email,
            $username,
            $passwordHash,
            $name,
            $birthday,
            $gender,
            $phone_full,
            $code,
            $expires_at,
            $created_at
        );

        if ($stmt->execute()) {
            $stmt->close();

            $mailError = null;
            if (sendNewUserVerificationCode($email, $code, $mailError)) {
                $success = "Enviámos um código de verificação para o email do novo utilizador. Introduza o código abaixo para concluir o registo.";
                $step    = 'verify';

                // guardar email e role em sessão para usar no PASSO 2
                $_SESSION['pending_new_user_email'] = $email;
                $_SESSION['pending_new_user_role']  = $role;
            } else {
                $error = "Não foi possível enviar o email com o código. " . $mailError;
            }
        } else {
            $error = 'Erro ao criar registo pendente: ' . $stmt->error;
            $stmt->close();
        }
    }
}

// 2) ADMIN INTRODUZ CÓDIGO RECEBIDO PELO UTILIZADOR (PASSO 2 - CRIAR UTILIZADOR)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_code'])) {
    $code_input = trim($_POST['verification_code'] ?? '');

    if ($code_input === '') {
        $error = "Tem de introduzir o código recebido por email.";
        $step  = 'verify';
    } elseif (empty($_SESSION['pending_new_user_email'])) {
        $error = "Sessão de verificação expirada. Volte a adicionar o utilizador.";
        $step  = 'form';
    } else {
        $email_pending = $_SESSION['pending_new_user_email'];

        $stmt = $conn->prepare("
            SELECT id, username, password_hash, name, birthday, gender, phone, expires_at, used
            FROM pending_user_verifications
            WHERE email = ? AND code = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->bind_param("ss", $email_pending, $code_input);
        $stmt->execute();
        $stmt->bind_result(
            $pend_id,
            $pend_username,
            $pend_password_hash,
            $pend_name,
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
                $error = "Este código já foi utilizado. Volte a adicionar o utilizador.";
                $step  = 'form';
            } elseif ($exp === false || $now > $exp) {
                $error = "O código expirou. Volte a adicionar o utilizador.";
                $step  = 'form';
            } else {
                // marcar pendente como usado
                $stmt = $conn->prepare("UPDATE pending_user_verifications SET used = 1 WHERE id = ?");
                $stmt->bind_param("i", $pend_id);
                $stmt->execute();
                $stmt->close();

                // LER o role da sessão (funcionario / admin)
                $pend_role = $_SESSION['pending_new_user_role'] ?? 'funcionario';
                // admin => is_admin = 1; funcionario => is_admin = 0
                $is_admin = ($pend_role === 'admin') ? 1 : 0;

                // inserir finalmente em users
                $created_at = date('Y-m-d H:i:s');
                $insert = $conn->prepare('
                    INSERT INTO users (email, username, password, created_at, name, birthday, gender, phone, is_admin)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $insert->bind_param(
                    'ssssssssi',
                    $email_pending,
                    $pend_username,
                    $pend_password_hash,
                    $created_at,
                    $pend_name,
                    $pend_birthday,
                    $pend_gender,
                    $pend_phone,
                    $is_admin
                );

                if ($insert->execute()) {
                    $success = 'Utilizador adicionado com sucesso após verificação de email!';
                    $novo_id = $insert->insert_id;
                    $insert->close();

                    // limpar sessão
                    unset($_SESSION['pending_new_user_email'], $_SESSION['pending_new_user_role']);

                    // registar log
                    regista_log(
                        $conn,
                        $_SESSION['user_id'],
                        "adicionar",
                        "utilizador",
                        $novo_id,
                        "Criado {$pend_username} <{$email_pending}> com email verificado."
                    );

                    $userId  = $_SESSION['user_id'];
                    $acao    = 'Novo utilizador criado';
                    $detalhe = "Utilizador: {$pend_username} · Email: {$email_pending}";

                    $stmtAt = $conn->prepare("
                        INSERT INTO atividade (user_id, acao, detalhe)
                        VALUES (?, ?, ?)
                    ");
                    if ($stmtAt) {
                        $stmtAt->bind_param("iss", $userId, $acao, $detalhe);
                        $stmtAt->execute();
                        $stmtAt->close();
                    }

                    $step = 'form';
                } else {
                    $error = 'Erro ao adicionar utilizador: ' . $insert->error;
                    $insert->close();
                    $step = 'form';
                }
            }
        } else {
            $stmt->close();
            $error = "Código inválido. Verifique o código que recebeu no email.";
            $step  = 'verify';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Adicionar Utilizador</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Fonte / tema -->
    <link
        href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    >
    <link rel="stylesheet" href="assets/css/app.css">

    <!-- intl-tel-input -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.min.css"
    >

    <!-- TomSelect CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/css/tom-select.bootstrap5.min.css">

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
        .adduser-shell {
            padding-bottom: 32px;
        }
        .adduser-container {
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
        .form-card {
            background: var(--card-bg);
            border-radius: 20px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.12);
            border: 1px solid rgba(148,163,184,0.20);
            padding: 28px 24px 24px 24px;
            margin-bottom: 20px;
        }
        .form-card h5 {
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 4px;
        }
        .form-card small {
            color: var(--text-soft);
        }
        .form-card .form-group label {
            font-weight: 600;
            color: #2a355b;
            margin-bottom: 4px;
            display: block;
        }
        .form-card .form-control,
        .form-card .form-select {
            background: #f9fafb;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            font-size: 0.95rem;
        }
        .form-card .form-control:focus,
        .form-card .form-select:focus {
            background: #f3f4ff;
            border-color:#6366f1;
            box-shadow: 0 0 0 1px rgba(99,102,241,0.16);
        }
        .ts-wrapper .ts-control {
            background-color: #ffffff !important;
            border-radius: 10px;
            padding: 0.375rem 0.75rem;
            border-color: #d1d5db;
        }
        .ts-wrapper .ts-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(37,99,235,0.25);
            border-color: #2563eb;
        }
        .ts-dropdown {
            background-color: #ffffff !important;
        }
        .btn-primary {
            background: var(--primary);
            border:none;
            border-radius: 8px;
            font-weight:600;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        .btn-voltar {
            border-radius: 8px;
            font-weight: 600;
            background: #e1e6f7;
            color: #29408c;
            border: none;
        }
        .btn-voltar:hover {
            background: #c9d3fa;
            color: #1f2b5c;
        }
        .intl-tel-input,
        .intl-tel-input .form-control {
            width: 100%;
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
            .form-card {
                padding: 24px 18px 20px 18px;
            }
        }
    </style>
</head>
<body>
<div id="app">
    <?php include "menu.php"; ?>

    <div id="main" class="adduser-shell">
        <header class="mb-3 d-flex align-items-center">
            <a href="#" class="burger-btn d-block d-xl-none me-2">
                <i class="bi bi-justify fs-3"></i>
            </a>
        </header>

        <div class="adduser-container">
            <div class="page-heading mb-3 text-center text-md-start">
                <h2>Adicionar Utilizador</h2>
                <p>Preencha todos os dados para criar um novo utilizador.</p>
            </div>

            <div class="form-card">
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php elseif ($error): ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <!-- FORMULÁRIO PRINCIPAL (PASSO 1) -->
                <form method="post" autocomplete="off" id="add-user-form">
                    <!-- 1ª linha: Nome completo | Nome de utilizador -->
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <div class="form-group">
                                <label for="name">
                                    <i class="bi bi-card-text me-1"></i> Nome completo
                                </label>
                                <input
                                    type="text"
                                    name="name"
                                    id="name"
                                    class="form-control"
                                    required
                                    value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>"
                                >
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="username">
                                    <i class="bi bi-person me-1"></i> Nome de utilizador (login)
                                </label>
                                <input
                                    type="text"
                                    name="username"
                                    id="username"
                                    class="form-control"
                                    required
                                    pattern="[a-zA-Z_]{3,32}"
                                    title="3 a 32 letras maiúsculas ou minúsculas ou underscore (_), sem números nem outros caracteres especiais"
                                    value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- 2ª linha: Email | Data de nascimento -->
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <div class="form-group">
                                <label for="email">
                                    <i class="bi bi-envelope me-1"></i> Email
                                </label>
                                <input
                                    type="email"
                                    name="email"
                                    id="email"
                                    class="form-control"
                                    required
                                    value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                                >
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="birthday">
                                    <i class="bi bi-calendar me-1"></i> Data de nascimento
                                </label>
                                <input
                                    type="date"
                                    name="birthday"
                                    id="birthday"
                                    class="form-control"
                                    required
                                    value="<?= isset($_POST['birthday']) ? htmlspecialchars($_POST['birthday']) : '' ?>"
                                    min="1950-01-01"
                                    max="<?= date('Y-m-d') ?>"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- 3ª linha: Género | Telefone | Tipo de utilizador -->
                    <div class="row mb-3">
                        <!-- Género -->
                        <div class="col-md-4 mb-3 mb-md-0">
                            <div class="form-group">
                                <label for="gender">
                                    <i class="bi bi-gender-ambiguous me-1"></i> Género
                                </label>
                                <select
                                    name="gender"
                                    id="gender"
                                    class="form-select js-nice-select"
                                    required
                                >
                                    <option value="">Selecione o género</option>
                                    <option value="male"   <?= (isset($_POST['gender']) && $_POST['gender'] === 'male')   ? 'selected' : '' ?>>Masculino</option>
                                    <option value="female" <?= (isset($_POST['gender']) && $_POST['gender'] === 'female') ? 'selected' : '' ?>>Feminino</option>
                                    <option value="other"  <?= (isset($_POST['gender']) && $_POST['gender'] === 'other')  ? 'selected' : '' ?>>Outro</option>
                                </select>
                            </div>
                        </div>

                        <!-- Telefone -->
                        <div class="col-md-4 mb-3 mb-md-0">
                            <div class="form-group">
                                <label for="phone">
                                    <i class="bi bi-telephone me-1"></i> Telefone
                                </label>
                                <input
                                    type="tel"
                                    id="phone"
                                    name="phone"
                                    class="form-control"
                                    required
                                >
                                <input type="hidden" name="phone_full" id="phone_full">
                            </div>
                        </div>

                        <!-- Tipo de utilizador -->
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="role">
                                    <i class="bi bi-people me-1"></i> Tipo de utilizador
                                </label>
                                <select
                                    name="role"
                                    id="role"
                                    class="form-select js-nice-select"
                                    required
                                >
                                    <option value="">Selecione o tipo</option>
                                    <option value="funcionario" <?= (isset($_POST['role']) && $_POST['role'] === 'funcionario') ? 'selected' : '' ?>>
                                        Funcionário
                                    </option>
                                    <option value="admin" <?= (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : '' ?>>
                                        Administrador
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- 4ª linha: Palavra-passe | Confirmar palavra-passe -->
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <div class="form-group">
                                <label for="password">
                                    <i class="bi bi-lock me-1"></i> Palavra-passe
                                </label>
                                <input
                                    type="password"
                                    name="password"
                                    id="password"
                                    class="form-control"
                                    required
                                    minlength="8"
                                    title="Mínimo 8 caracteres"
                                >
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="confirm_password">
                                    <i class="bi bi-lock me-1"></i> Confirmar palavra-passe
                                </label>
                                <input
                                    type="password"
                                    name="confirm_password"
                                    id="confirm_password"
                                    class="form-control"
                                    required
                                    minlength="8"
                                    title="Confirme a palavra-passe"
                                >
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-row flex-md-row justify-content-center justify-content-md-end align-items-center gap-2 mt-2 mb-1">
                        <button
                            type="submit"
                            name="add_utilizador"
                            class="btn btn-primary w-100 w-md-auto"
                        >
                            <i class="bi bi-plus-circle me-1"></i> Adicionar utilizador (enviar código)
                        </button>
                        <a href="index.php" class="btn btn-voltar w-100 w-md-auto">
                            <i class="bi bi-arrow-left"></i> Voltar
                        </a>
                    </div>
                </form>
            </div>

            <!-- PASSO 2: FORMULÁRIO PARA INTRODUZIR O CÓDIGO DE VERIFICAÇÃO -->
            <?php if ($step === 'verify'): ?>
                <div class="form-card">
                    <h5>Verificar email do novo utilizador</h5>
                    <small>Introduza o código que foi enviado para o email do novo utilizador.</small>

                    <form method="post" autocomplete="off" class="mt-3">
                        <div class="row mb-3">
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
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <button
                                type="submit"
                                name="verify_code"
                                class="btn btn-primary"
                            >
                                Confirmar código e criar utilizador
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js"></script>
<script>
const phoneInput   = document.querySelector("#phone");
const fullPhoneInp = document.querySelector("#phone_full");
const form         = document.querySelector("#add-user-form");

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

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>

<!-- TomSelect JS -->
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js"></script>
<script>
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
</body>
</html>
