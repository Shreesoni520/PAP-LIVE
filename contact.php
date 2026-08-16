<?php
ob_start();
session_start();
include './config.php';


$success = false;
$errorMsg = '';

$logged_user_id    = !empty($_SESSION['public_user_id']) ? (int) $_SESSION['public_user_id'] : 0;
$logged_user_nome  = '';
$logged_user_email = '';

if ($logged_user_id > 0) {
    if (!empty($_SESSION['public_user_nome'])) {
        $logged_user_nome = trim($_SESSION['public_user_nome']);
    }

    $stmtUser = $conn->prepare("SELECT nome, email FROM users_public WHERE id = ? LIMIT 1");
    if ($stmtUser) {
        $stmtUser->bind_param('i', $logged_user_id);
        $stmtUser->execute();
        $resUser = $stmtUser->get_result();
        if ($resUser && ($rowUser = $resUser->fetch_assoc())) {
            $logged_user_nome  = trim($rowUser['nome']);
            $logged_user_email = trim($rowUser['email']);
            $_SESSION['public_user_nome'] = $logged_user_nome;
        }
        $stmtUser->close();
    }
}

// Buscar info de contacto
$infoResult  = $conn->query("SELECT * FROM contact_info LIMIT 1");
$contactInfo = $infoResult ? $infoResult->fetch_assoc() : null;


if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $subject = trim($_POST["subject"] ?? '');
    $message = trim($_POST["message"] ?? '');

    if ($logged_user_id > 0 && $logged_user_nome !== '' && $logged_user_email !== '') {
        $name    = $logged_user_nome;
        $email   = $logged_user_email;
        $user_id = $logged_user_id;
    } else {
        $name    = trim($_POST["name"]  ?? '');
        $email   = trim($_POST["email"] ?? '');
        $user_id = 0;
    }

    if ($name === '' || $email === '' || $subject === '' || $message === '') {
        $errorMsg = "Por favor, preencha todos os campos";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMsg = "Endereço de email inválido";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO contact (name, email, subject, message, user_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        if ($stmt) {
            $stmt->bind_param("ssssi", $name, $email, $subject, $message, $user_id);
            if ($stmt->execute()) {
                $success = true;
            } else {
                $errorMsg = "Falha ao enviar mensagem: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $errorMsg = "Erro de base de dados: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Contacto</title>

  <link href="assets/img/favicon.png" rel="icon">
  <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Poppins:wght@500;600&display=swap" rel="stylesheet">

  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/aos/aos.css" rel="stylesheet">
  <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
  <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">

  <link href="assets/css/main.css" rel="stylesheet">

  <style>
    html, body { max-width: 100%; overflow-x: hidden; }
    .main, #hero, #contact { width: 100%; max-width: 100vw; overflow-x: hidden; }
    .info-wrap {
      background-color: #f8f9fa;
      border: 1px solid #e0e0e0;
      border-radius: 10px;
      padding: 30px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    }
    .info-wrap h3 {
      border-bottom: 2px solid #0d6efd;
      padding-bottom: 8px;
      margin-bottom: 20px;
      font-weight: 600;
    }
    .form-label { font-weight: 500; }

    @media (max-width: 767.98px) {
      body.index-page {
        padding-top: 48px; /* um bocadinho mais espaço para o header fixo */
        background-color: #37517e;
      }
    }
  </style>
</head>
<body class="index-page">


<header id="header" class="header d-flex align-items-center fixed-top">
    <?php include "menu.php"; ?>
</header>


<main class="main">
  <section id="hero" class="section dark-background" 
           style="height: 300px; padding: 0; overflow: hidden; display: flex; align-items: center; justify-content: center; text-align: center;">
    <div class="container h-100 d-flex align-items-center justify-content-center">
      <div class="row w-100 gy-0 align-items-center justify-content-center">
        <div class="container section-title" data-aos="fade-up" style="margin-top: 5%;">
          <h2>Contacto</h2>
          <p>Adorávamos receber a sua mensagem. Por favor, preencha o formulário abaixo e entraremos em contacto o mais breve possível.</p>
        </div>
      </div>
    </div>
  </section>

  <section id="contact" class="contact section">
    <div class="container" data-aos="fade-up" data-aos-delay="100">
      <div class="row gy-4">

        <div class="col-lg-5">
          <div class="info-wrap">
            <h3>Informações de Contacto</h3>

            <div class="info-item d-flex align-items-start mb-3">
              <i class="bi bi-geo-alt fs-3 me-3 text-primary"></i>
              <div>
                <h5>Morada</h5>
                <p><?= htmlspecialchars($contactInfo['address'] ?? 'Morada não definida') ?></p>
              </div>
            </div>

            <div class="info-item d-flex align-items-start mb-3">
              <i class="bi bi-telephone fs-3 me-3 text-primary"></i>
              <div>
                <h5>Ligue-nos</h5>
                <p><?= htmlspecialchars($contactInfo['phone'] ?? 'Telefone não definido') ?></p>
              </div>
            </div>

            <div class="info-item d-flex align-items-start mb-3">
              <i class="bi bi-envelope fs-3 me-3 text-primary"></i>
              <div>
                <h5>Envie-nos Email</h5>
                <p><?= htmlspecialchars($contactInfo['email'] ?? 'Email não definido') ?></p>
              </div>
            </div>

            <iframe class="rounded mt-3"
                    src="https://www.google.com/maps/embed?pb=!1m16!1m12!1m3!1d2823.021265895071!2d-7.910613124610994!3d38.56740771610836!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!2m1!1sevora%20epral!5e1!3m2!1spt-PT!2spt!4v1760867396066!5m2!1spt-PT!2spt"
                    frameborder="0"
                    style="border:0; width:100%; height:270px;"
                    allowfullscreen=""
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade">
            </iframe>
          </div>
        </div>

        <div class="col-lg-7">
          <div class="info-wrap">

            <?php if ($success): ?>
              <div class="alert alert-success text-center" role="alert">
                A sua mensagem foi enviada com sucesso!
              </div>
            <?php elseif ($errorMsg): ?>
              <div class="alert alert-danger text-center" role="alert">
                ⚠️ Erro: <?= htmlspecialchars($errorMsg); ?>
              </div>
            <?php endif; ?>

            <h3>Envie-nos uma Mensagem</h3>

            <form action="" method="post" class="row g-3">
              <?php if ($logged_user_id > 0 && $logged_user_nome !== '' && $logged_user_email !== ''): ?>
                <div class="col-md-6">
                  <label class="form-label">O seu Nome</label>
                  <div class="form-control bg-light text-muted" style="cursor: not-allowed;">
                    <?php echo htmlspecialchars($logged_user_nome); ?>
                  </div>
                </div>

                <div class="col-md-6">
                  <label class="form-label">O seu Email</label>
                  <div class="form-control bg-light text-muted" style="cursor: not-allowed;">
                    <?php echo htmlspecialchars($logged_user_email); ?>
                  </div>
                </div>
              <?php else: ?>
                <div class="col-md-6">
                  <label for="name-field" class="form-label">O seu Nome</label>
                  <input type="text" name="name" id="name-field" class="form-control" placeholder="Nome Completo" required>
                </div>

                <div class="col-md-6">
                  <label for="email-field" class="form-label">O seu Email</label>
                  <input type="email" name="email" id="email-field" class="form-control" placeholder="exemplo@email.com" required>
                </div>
              <?php endif; ?>

              <div class="col-12">
                <label for="subject-field" class="form-label">Assunto</label>
                <input type="text" name="subject" id="subject-field" class="form-control" placeholder="Assunto" required>
              </div>

              <div class="col-12">
                <label for="message-field" class="form-label">Mensagem</label>
                <textarea name="message" id="message-field" class="form-control" rows="6" placeholder="Escreva a sua mensagem..." required></textarea>
              </div>

              <div class="col-12 text-center">
                <button type="submit" class="btn btn-primary btn-lg">Enviar Mensagem</button>
              </div>
            </form>

          </div>
        </div>

      </div>
    </div>
  </section>

</main>

<?php include "footer.php"; ?>

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

<?php ob_end_flush(); ?>
</body>
</html>
