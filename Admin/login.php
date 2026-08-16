<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    header('Location: index.php?evora=inicio');
    exit();
}

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$step         = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$hasPending2F = isset($_SESSION['pending_2fa_user_id']);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Painel de Administração</title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap">
    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/app.css">

    <style>
        :root {
            --bg-page: #f3f4f6;
            --card-bg: #ffffff;
            --card-border: #e5e7eb;
            --primary: #2563eb;
            --primary-soft: #dbeafe;
            --text-main: #0f172a;
            --text-soft: #6b7280;
            --accent: #22c55e;
            --danger: #dc2626;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Nunito', sans-serif!important;
            background: radial-gradient(circle at top, #e5edff 0, transparent 55%), var(--bg-page);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .shell {
            width: 100%;
            max-width: 900px;
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(260px, 0.9fr);
            gap: 0;
            border-radius: 24px;
            background: rgba(255,255,255,0.9);
            box-shadow: 0 20px 45px rgba(15,23,42,0.10);
            border: 1px solid rgba(148,163,184,0.40);
            overflow: hidden;
            backdrop-filter: blur(12px);
        }

        /* LEFT: formulário */
        .left {
            padding: 22px 22px 20px 22px;
            background: linear-gradient(135deg, #f9fafb, #ffffff);
        }
        .card-inner {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 20px 18px 18px 18px;
            border: 1px solid var(--card-border);
            box-shadow: 0 10px 26px rgba(15,23,42,0.06);
            height: 100%;
        }
        .logo-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }
        .logo-row img {
            height: 30px;
        }
        .logo-row span {
            font-weight: 700;
            font-size: 1rem;
            color: var(--text-main);
        }

        .title {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 4px;
        }
        .subtitle {
            font-size: 0.9rem;
            color: var(--text-soft);
            margin-bottom: 18px;
        }

        .form-label {
            font-size: 0.84rem;
            font-weight: 600;
            color: #4b5563;
        }
        .form-control-xl {
            height: 44px;
            border-radius: 12px;
            border: 1px solid var(--card-border);
            font-size: 0.95rem;
            background: #f9fafb;
        }
        .form-control-xl:focus {
            border-color: var(--primary);
            background: #eff6ff;
            box-shadow: 0 0 0 1px rgba(37,99,235,0.20);
            outline: none;
        }

        .btn-primary {
            border-radius: 999px;
            font-weight: 700;
            border: none;
            height: 44px;
            font-size: 0.92rem;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            box-shadow: 0 8px 22px rgba(37,99,235,0.30);
            transition: transform 0.12s ease-out, box-shadow 0.14s ease-out;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 30px rgba(37,99,235,0.40);
        }
        .btn-primary:active {
            transform: translateY(1px) scale(0.99);
            box-shadow: 0 6px 16px rgba(15,23,42,0.30);
        }

        .text-danger {
            color: var(--danger)!important;
            font-size: 0.86rem;
            margin-top: 8px;
        }

        .helper {
            font-size: 0.8rem;
            color: var(--text-soft);
            margin-top: 6px;
        }

        /* RIGHT: painel passo 1 / 2 */
        .right {
            padding: 22px 20px 18px 20px;
            background: #f9fafb;
            border-left: 1px solid var(--card-border);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .step-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 11px;
            border-radius: 999px;
            background: var(--primary-soft);
            color: #1d4ed8;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .step-pill i {
            font-size: 0.9rem;
            color: var(--accent);
        }
        .step-title {
            margin-top: 10px;
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-main);
        }
        .step-text {
            font-size: 0.86rem;
            color: var(--text-soft);
            margin-top: 4px;
        }
        .bullet {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-size: 0.8rem;
            color: var(--text-main);
            margin-top: 8px;
        }
        .bullet i {
            margin-top: 2px;
            color: #60a5fa;
        }
        .side-footer {
            font-size: 0.74rem;
            color: #9ca3af;
            margin-top: 10px;
        }

        @media (max-width: 900px) {
            .shell {
                grid-template-columns: minmax(0, 1fr);
                max-width: 420px;
            }
            .right {
                display: none;
            }
        }
    </style>
</head>
<body>
<div class="shell">
    <!-- LEFT: FORM -->
    <div class="left">
        <div class="card-inner">
            <div class="logo-row">
                <img src="assets/images/logo/logo.png" alt="Logo">
                <span>Painel de Administração</span>
            </div>

            <?php if ($step === 2 && $hasPending2F): ?>
                <div class="title">Confirmar acesso</div>
                <p class="subtitle">
                    Introduza o código de 6 dígitos enviado para o email associado à sua conta.
                </p>

                <form method="POST" action="login_funcao.php" autocomplete="off">
                    <input type="hidden" name="action" value="verify_2fa">

                    <div class="mb-3">
                        <label for="twofa_code" class="form-label">Código 2FA</label>
                        <input
                            type="text"
                            name="twofa_code"
                            id="twofa_code"
                            class="form-control form-control-xl"
                            placeholder="Ex: 123456"
                            required
                        >
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mt-1">
                        <i class="bi bi-shield-lock"></i> Entrar
                    </button>
                </form>

                <?php if (isset($_GET['error']) && $_GET['error'] === '2fa'): ?>
                    <p class="text-danger">
                        Código inválido ou expirado. Faça login novamente para gerar novo código.
                    </p>
                <?php endif; ?>

                <p class="helper">
                    Não é você? <a href="login.php">Voltar ao ecrã de login</a>
                </p>

            <?php else: ?>
                <div class="title">Iniciar sessão</div>
                <p class="subtitle">
                    Use o seu nome de utilizador e palavra‑passe. No passo seguinte pedimos um código enviado por email.
                </p>

                <form method="POST" action="login_funcao.php" autocomplete="off">
                    <input type="hidden" name="action" value="login">

                    <div class="mb-3">
                        <label for="username" class="form-label">Nome de utilizador</label>
                        <input
                            type="text"
                            name="username"
                            id="username"
                            class="form-control form-control-xl"
                            required
                        >
                    </div>

                    <div class="mb-2">
                        <label for="password" class="form-label">Palavra-passe</label>
                        <input
                            type="password"
                            name="password"
                            id="password"
                            class="form-control form-control-xl"
                            required
                        >
                    </div>

                    <p class="helper">
                        Depois deste passo enviaremos um código 2FA para o email da sua conta.
                    </p>

                    <button type="submit" class="btn btn-primary w-100 mt-1">
                        <i class="bi bi-arrow-right-circle"></i> Continuar
                    </button>
                </form>

                <?php if (isset($_GET['error']) && $_GET['error'] === '1'): ?>
                    <p class="text-danger">
                        Nome de utilizador ou palavra‑passe inválidos.
                    </p>
                <?php elseif (isset($_GET['error']) && $_GET['error'] === 'email'): ?>
                    <p class="text-danger">
                        Não foi possível enviar o código 2FA. Tente novamente.
                    </p>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>

    <!-- RIGHT: PASSO 1 / 2 -->
    <div class="right">
        <?php if ($step === 2 && $hasPending2F): ?>
            <div>
                <div class="step-pill">
                    <i class="bi bi-shield-lock"></i> Passo 2 de 2 · Código
                </div>
                <div class="step-title">Verificação em dois passos</div>
                <p class="step-text">
                    Confirme o código único para concluir o login em segurança.
                </p>
                <div class="bullet">
                    <i class="bi bi-check-circle-fill"></i>
                    <span>Palavra‑passe já validada com sucesso.</span>
                </div>
                <div class="bullet">
                    <i class="bi bi-envelope-fill"></i>
                    <span>Verifique a caixa de entrada e SPAM do seu email.</span>
                </div>
            </div>
        <?php else: ?>
            <div>
                <div class="step-pill">
                    <i class="bi bi-box-arrow-in-right"></i> Passo 1 de 2 · Login
                </div>
                <div class="step-title">Aceder ao painel</div>
                <p class="step-text">
                    Primeiro faz o login com credenciais. Depois confirmas com um código 2FA.
                </p>
                <div class="bullet">
                    <i class="bi bi-lock-fill"></i>
                    <span>Credenciais encriptadas e ligação segura.</span>
                </div>
                <div class="bullet">
                    <i class="bi bi-shield-check"></i>
                    <span>Autenticação em dois fatores ativa por defeito.</span>
                </div>
            </div>
        <?php endif; ?>

        <div class="side-footer">
            © <?= date('Y') ?> · Krishna Soni · Município de Évora
        </div>
    </div>
</div>
</body>
</html>
