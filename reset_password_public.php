<?php
session_start();
require './config.php';

$erro     = '';
$sucesso  = '';
$token    = $_GET['token'] ?? '';
$showForm = false;
$user_id  = null;
$reset_id = null;

if ($token === '') {
    $erro = "Token em falta ou inválido.";
} else {
    $stmt = $conn->prepare("
        SELECT id, user_id, expires_at, used
        FROM user_password_resets_public
        WHERE token = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->bind_result($reset_id_db, $db_user_id, $expires_at, $used);
    if ($stmt->fetch()) {
        $stmt->close();

        $now = new DateTime();
        $exp = new DateTime($expires_at);

        if ($used) {
            $erro = "Este link já foi utilizado. Faça um novo pedido de recuperação.";
        } elseif ($now > $exp) {
            $erro = "Este link expirou. Faça um novo pedido de recuperação.";
        } else {
            $user_id  = (int)$db_user_id;
            $reset_id = (int)$reset_id_db;
            $showForm = true;
        }
    } else {
        $stmt->close();
        $erro = "Link inválido. Faça um novo pedido de recuperação.";
    }
}

if ($showForm && isset($_POST['action']) && $_POST['action'] === 'skip') {
    $stmtU = $conn->prepare("UPDATE user_password_resets_public SET used = 1 WHERE id = ?");
    $stmtU->bind_param("i", $reset_id);
    $stmtU->execute();
    $stmtU->close();

    $stmtUser = $conn->prepare("SELECT id, nome FROM users_public WHERE id = ? LIMIT 1");
    $stmtUser->bind_param("i", $user_id);
    $stmtUser->execute();
    $stmtUser->bind_result($uid, $nome);
    if ($stmtUser->fetch()) {
        $_SESSION['public_user_id']   = $uid;
        $_SESSION['public_user_nome'] = $nome;
    }
    $stmtUser->close();

    $sucesso  = "Ligação validada. Abra a página inicial no botão abaixo.";
    $showForm = false;
}

if ($showForm && isset($_POST['action']) && $_POST['action'] === 'update') {
    $new_pass  = $_POST['new_password'] ?? '';
    $conf_pass = $_POST['confirm_password'] ?? '';

    if ($new_pass === '' || $conf_pass === '') {
        $erro = "Preencha todos os campos.";
    } elseif (strlen($new_pass) < 8) {
        $erro = "A nova palavra-passe deve ter pelo menos 8 caracteres.";
    } elseif ($new_pass !== $conf_pass) {
        $erro = "As novas palavras-passe não coincidem.";
    } else {
        $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmtP = $conn->prepare("UPDATE users_public SET password_hash = ? WHERE id = ?");
        $stmtP->bind_param("si", $new_hash, $user_id);
        if ($stmtP->execute()) {
            $stmtP->close();

            $stmtU = $conn->prepare("UPDATE user_password_resets_public SET used = 1 WHERE id = ?");
            $stmtU->bind_param("i", $reset_id);
            $stmtU->execute();
            $stmtU->close();

            $sucesso  = "Palavra-passe alterada com sucesso. Já pode iniciar sessão.";
            $showForm = false;
        } else {
            $stmtP->close();
            $erro = "Erro ao atualizar a palavra-passe. Tente novamente.";
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
                  Recuperar palavra-passe
                </h2>
                <p class="mb-0" style="color:#6b7280; font-size:0.9rem;">
                  Validação do link de recuperação e opções de acesso.
                </p>
              </div>

              <?php if ($erro): ?>
                <div class="alert alert-danger py-2" role="alert">
                  <?php echo htmlspecialchars($erro); ?>
                </div>
              <?php endif; ?>

              <?php if ($sucesso): ?>
                <div class="alert alert-success py-2" role="alert">
                  <?php echo htmlspecialchars($sucesso); ?>
                </div>
              <?php endif; ?>

              <?php if ($showForm && !$erro): ?>
                <p style="color:#374151; font-size:0.9rem;">
                  O seu link é válido. Escolha se quer atualizar a palavra-passe ou entrar sem alterar.
                </p>

                <form method="post" autocomplete="off">
                  <input type="hidden" name="action" value="update">

                  <div class="mb-3">
                    <label for="new_password" class="form-label mb-1" style="color:#111827; font-weight:600;">
                      Nova palavra-passe
                    </label>
                    <input type="password"
                           name="new_password"
                           id="new_password"
                           class="form-control form-control-lg"
                           style="border-color:#d1d5db; font-size:0.95rem;"
                           required>
                  </div>

                  <div class="mb-3">
                    <label for="confirm_password" class="form-label mb-1" style="color:#111827; font-weight:600;">
                      Confirmar nova palavra-passe
                    </label>
                    <input type="password"
                           name="confirm_password"
                           id="confirm_password"
                           class="form-control form-control-lg"
                           style="border-color:#d1d5db; font-size:0.95rem;"
                           required>
                  </div>

                  <div class="d-grid mb-2">
                    <button type="submit"
                            class="btn btn-lg rounded-pill"
                            style="
                              background:linear-gradient(135deg,#0d6efd,#2563eb);
                              border:none; color:#fff; font-weight:600;
                            ">
                      Atualizar palavra-passe
                    </button>
                  </div>
                </form>

                <form method="post" class="mt-2">
                  <input type="hidden" name="action" value="skip">
                  <div class="d-grid">
                    <button type="submit"
                            class="btn btn-outline-secondary btn-lg rounded-pill"
                            style="font-weight:600;">
                      Entrar sem alterar
                    </button>
                  </div>
                </form>

              <?php else: ?>
                <div class="text-center mt-3">
                  <a href="index.php?evora_p=inicio" target="_blank"
                     style="font-size:0.9rem; color:#0d6efd; font-weight:600;">
                    Ir para a página inicial
                  </a>
                </div>
              <?php endif; ?>

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
