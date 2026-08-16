<?php
session_start();
require './config.php';

if (empty($_SESSION['public_2fa_pending'])) {
    header("Location: index.php?evora_p=login");
    exit;
}

$user_id = (int) $_SESSION['public_2fa_pending'];
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code_input = trim($_POST['twofa_code'] ?? '');

    if ($code_input === '') {
        $erro = "Introduza o código recebido por email.";
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
            $exp = new DateTime($expires_at);

            if ($used) {
                $erro = "Este código já foi utilizado. Faça login novamente para receber um novo código.";
            } elseif ($now > $exp) {
                $erro = "O código expirou. Faça login novamente para receber um novo código.";
            } else {
                // Marcar código como usado
                $stmt2 = $conn->prepare("UPDATE user_twofa_codes_public SET used = 1 WHERE id = ?");
                $stmt2->bind_param("i", $code_id);
                $stmt2->execute();
                $stmt2->close();

                // Buscar nome do utilizador para a sessão
                $stmt3 = $conn->prepare("SELECT nome FROM users_public WHERE id = ?");
                $stmt3->bind_param("i", $user_id);
                $stmt3->execute();
                $stmt3->bind_result($nome_user);
                $stmt3->fetch();
                $stmt3->close();

                // Iniciar sessão final
                $_SESSION['public_user_id']   = $user_id;
                $_SESSION['public_user_nome'] = $nome_user ?: 'Utilizador';

                unset($_SESSION['public_2fa_pending']);

                header("Location: index.php?evora_p=inicio");
                exit;
            }
        } else {
            $stmt->close();
            $erro = "Código inválido. Verifique o código que recebeu.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>Verificar código 2FA</title>

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

          <!-- Título / subtítulo -->
          <div class="text-center mb-4">
            <h2 class="fw-semibold" style="color:#f9fafb; font-size:1.7rem;">
              Verificação em dois passos
            </h2>
            <p class="mb-0" style="color:#9ca3af; font-size:0.9rem;">
              Esta página confirma a sua identidade com um código temporário enviado por email antes de concluir o início de sessão.
            </p>
          </div>

          <!-- Card -->
          <div class="card border-0"
               style="
                 border-radius:18px;
                 box-shadow:0 20px 55px rgba(15,23,42,0.7);
                 background-color:#ffffff;
               ">
            <div class="card-body p-4 p-md-5">

              <!-- Ícone circular -->
              <div class="d-flex align-items-center justify-content-center mb-3">
                <div style="
                      width:54px;height:54px;border-radius:999px;
                      background:#0d6efd;
                      display:flex;align-items:center;justify-content:center;
                      box-shadow:0 10px 25px rgba(13,110,253,0.45);
                    ">
                  <i class="bi bi-shield-lock-fill text-white fs-3"></i>
                </div>
              </div>

              <p class="text-center mb-4" style="color:#6b7280; font-size:0.86rem;">
                Verifique o código e introduza-o exatamente como o recebeu.
              </p>

              <?php if ($erro): ?>
                <div class="alert alert-danger py-2 text-center" role="alert">
                  <?php echo htmlspecialchars($erro); ?>
                </div>
              <?php endif; ?>

              <form method="post" autocomplete="off">
                <div class="mb-3 text-center">
                  <label for="twofa_code" class="form-label mb-1" style="color:#111827; font-weight:600;">
                    Código de segurança
                  </label>
                  <input type="text"
                         name="twofa_code"
                         id="twofa_code"
                         maxlength="6"
                         class="form-control form-control-lg text-center"
                         style="border-color:#d1d5db; font-size:1.5rem; letter-spacing:0.35em; font-weight:600;"
                         placeholder="••••••"
                         autofocus
                         inputmode="numeric">
                </div>

                <div class="d-grid mb-2">
                  <button type="submit"
                          class="btn btn-lg rounded-pill"
                          style="
                            background:linear-gradient(135deg,#0d6efd,#2563eb);
                            border:none; color:#fff; font-weight:600;
                          ">
                    Confirmar código
                  </button>
                </div>
              </form>

              <p class="text-center text-muted mt-3 mb-0" style="font-size:0.85rem;">
                Se não recebeu o email, verifique a pasta de spam ou tente novamente mais tarde.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Efeitos de luz iguais ao login -->
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
