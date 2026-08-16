<?php
session_start();
require './config.php';

// Buscar as 3 notícias mais recentes para o início
$noticias_inicio = [];

$sql = "
    SELECT id, titulo, resumo, imagem_lista, data_publicacao
    FROM noticias
    ORDER BY data_publicacao DESC, id DESC
    LIMIT 3
";
if ($stmt = $conn->prepare($sql)) {
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $noticias_inicio[] = $row;
        }
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>Início</title>

  <!-- Favicons -->
  <link href="assets/img/favicon.png" rel="icon">
  <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

  <!-- Fonts -->
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&family=Poppins:wght@300;400;500;600&family=Jost:wght@300;400;500;600&display=swap"
    rel="stylesheet"
  >

  <!-- Vendor CSS Files -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/aos/aos.css" rel="stylesheet">
  <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
  <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">

  <!-- Main CSS File -->
  <link href="assets/css/main.css" rel="stylesheet">

  <style>
    html, body {
      max-width: 100%;
      overflow-x: hidden;
    }

    /* Hero */
    #hero.hero {
      min-height: 100vh;
      padding-top: 7rem;
      padding-bottom: 4rem;
      position: relative;
      overflow: hidden;
      display: flex;
      align-items: center;
    }

    #hero.hero::before {
      content: "";
      position: absolute;
      inset: 0;
      background: radial-gradient(circle at top left, rgba(59,130,246,0.25), transparent 55%),
                  radial-gradient(circle at bottom right, rgba(16,185,129,0.22), transparent 55%);
      opacity: 0.9;
      pointer-events: none;
    }

    #hero .container {
      position: relative;
      z-index: 1;
    }

    #hero h1 {
      font-family: "Poppins", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      font-weight: 600;
      font-size: clamp(1.8rem, 2.8vw + 0.8rem, 2.4rem);
      line-height: 1.2;
      margin-bottom: 0.8rem;
    }

    #hero p {
      max-width: 30rem;
      font-size: 0.95rem;
      line-height: 1.5;
      color: #e5e7eb;
      margin-bottom: 1.1rem;
    }

    .hero .btn-get-started {
      border-radius: 999px;
      padding: 0.6rem 1.5rem;
      font-size: 0.95rem;
      font-weight: 500;
      box-shadow: 0 10px 30px rgba(37,99,235,0.4);
    }

    .hero .btn-outline-light {
      border-radius: 999px;
      padding-inline: 1.1rem;
      font-size: 0.9rem;
    }

    .hero-img {
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .hero-image-card {
      width: 100%;
      max-width: 760px;
      padding: 14px;
      border-radius: 28px;
      background: linear-gradient(145deg, rgba(255,255,255,0.10), rgba(255,255,255,0.04));
      border: 1px solid rgba(255,255,255,0.12);
      box-shadow: 0 20px 45px rgba(15,23,42,0.28);
      backdrop-filter: blur(4px);
      animation: floatPulse 8s ease-in-out infinite;
    }

    .hero-image-card img {
      width: 100%;
      display: block;
      border-radius: 20px;
      box-shadow: 0 20px 40px rgba(15,23,42,0.35);
    }

    @keyframes floatPulse {
      0%, 100% { transform: translateY(0) scale(1); }
      50% { transform: translateY(-6px) scale(1.01); }
    }

    @media (max-width: 991.98px) {
      #hero.hero {
        text-align: center;
        padding-top: 6rem;
        padding-bottom: 3rem;
      }

      #hero p {
        margin-inline: auto;
      }

      .hero-img {
        margin-top: 1.5rem;
      }

      .hero-image-card {
        max-width: 100%;
        padding: 10px;
        border-radius: 22px;
      }

      .hero-image-card img {
        border-radius: 16px;
      }
    }

    /* Imagens protegidas */
    .protected-img {
      -webkit-user-drag: none;
      user-select: none;
    }

    /* ===== FIX NOTÍCIAS ===== */
    .card-news {
      border: 0;
      border-radius: 22px;
      overflow: hidden;
      background: #fff;
      box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
      position: relative;
    }

    .news-card-img-wrapper {
      width: 100%;
      height: 220px;
      overflow: hidden;
      background: #f3f4f6;
      margin: 0;
      flex-shrink: 0;
    }

    .news-card-img-wrapper img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    .card-news .card-body {
      padding: 1rem 1.1rem 1.2rem;
    }

    .card-title {
      min-height: 48px;
      margin-bottom: 0.5rem;
      line-height: 1.3;
    }

    .card-text {
      min-height: 60px;
    }
  </style>
</head>
<body class="index-page">

<header id="header" class="header d-flex align-items-center fixed-top">
  <?php include "menu.php"; ?>
</header>

<main class="main">

  <!-- Hero Section -->
  <section id="hero" class="hero section dark-background">
    <div class="container">
      <div class="row gy-4">
        <div class="col-lg-6 order-2 order-lg-1 d-flex flex-column justify-content-center" data-aos="zoom-out">
          <h1>Bem-vindo à plataforma de Évora</h1>
          <p>
            Consulte informação útil, notícias e ocorrências da cidade de Évora,
            tudo num só sítio simples e rápido de usar.
          </p>
          <div class="d-flex gap-2 flex-wrap">
            <a href="index.php?evora_p=information" class="btn-get-started">
              Informação
            </a>
            <a href="index.php?evora_p=contact"
               class="btn btn-outline-light btn-sm d-flex align-items-center gap-1">
              Contacto
            </a>
          </div>
        </div>

        <div class="col-lg-6 order-1 order-lg-2 hero-img" data-aos="zoom-out" data-aos-delay="200">
          <div class="hero-image-card">
            <img src="assets/img/hero-img.png"
                 class="img-fluid protected-img"
                 alt="Vista da cidade"
                 draggable="false"
                 oncontextmenu="return false;">
          </div>
        </div>
      </div>
    </div>
  </section>
  <!-- /Hero Section -->

  <!-- Sobre / Informação rápida -->
  <section id="about" class="about section">
    <div class="container">
      <div class="row gy-4 align-items-center">
        <div class="col-lg-6" data-aos="fade-up" data-aos-delay="100">
          <h2>Informação sobre Évora</h2>
          <p class="mb-3">
            Esta plataforma foi criada para ajudar residentes e visitantes a encontrarem
            rapidamente informação sobre a cidade de Évora, desde notícias a ocorrências
            importantes.
          </p>
          <ul class="list-unstyled">
            <li class="mb-2">
              <i class="bi bi-check2-circle text-primary me-1"></i>
              Acesso simples a notícias relevantes.
            </li>
            <li class="mb-2">
              <i class="bi bi-check2-circle text-primary me-1"></i>
              Consulta de ocorrências e pontos de interesse no mapa.
            </li>
            <li class="mb-2">
              <i class="bi bi-check2-circle text-primary me-1"></i>
              Área reservada para gerir o seu perfil e dados.
            </li>
          </ul>
        </div>

        <div class="col-lg-6" data-aos="fade-up" data-aos-delay="200">
          <div class="ratio ratio-16x9 rounded-3 overflow-hidden shadow">
            <img src="assets/img/evora.jpeg"
                 class="w-100 h-100 protected-img"
                 style="object-fit: cover;"
                 alt="Vista da cidade de Évora"
                 draggable="false"
                 oncontextmenu="return false;">
          </div>
        </div>
      </div>
    </div>
  </section>
  <!-- /Sobre -->

  <!-- Secção Mapa + Ocorrências -->
  <section class="section light-background">
    <div class="container">
      <div class="row gy-4 align-items-center">
        <div class="col-lg-6" data-aos="fade-up" data-aos-delay="100">
          <h2>Mapa de ocorrências</h2>
          <p class="mb-3">
            Veja no mapa os principais pontos e ocorrências registadas na cidade.
          </p>
          <ul class="list-unstyled mb-4">
            <li class="mb-2">
              <i class="bi bi-geo-alt-fill text-danger me-1"></i>
              Localização de ocorrências importantes.
            </li>
            <li class="mb-2">
              <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>
              Informação rápida sobre situações a ter em atenção.
            </li>
          </ul>
          <a href="index.php?evora_p=mapa" class="btn btn-primary btn-sm rounded-pill px-3">
            Abrir mapa
          </a>
          <a href="index.php?evora_p=listocorrencias" class="btn btn-outline-primary btn-sm rounded-pill px-3 ms-1">
            Ver lista de ocorrências
          </a>
        </div>

        <div class="col-lg-6" data-aos="fade-up" data-aos-delay="200">
          <div class="ratio ratio-16x9 rounded-3 overflow-hidden shadow-sm">
            <img src="assets/img/steps/evora.png"
                 class="w-100 h-100 protected-img"
                 style="object-fit: cover;"
                 alt="Mapa de ocorrências em Évora"
                 draggable="false"
                 oncontextmenu="return false;">
          </div>
        </div>
      </div>
    </div>
  </section>
  <!-- /Mapa + Ocorrências -->

  <!-- Secção Notícias destaque -->
  <section id="noticias-destaque" class="section">
    <div class="container">
      <div class="section-title text-center mb-4" data-aos="fade-up">
        <h2>Notícias em destaque</h2>
        <p>As últimas notícias sobre Évora.</p>
      </div>

      <div class="row gy-4">
        <?php if (!empty($noticias_inicio)): ?>
          <?php foreach ($noticias_inicio as $index => $noticia): ?>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="<?php echo 100 + ($index * 50); ?>">
              <div class="card card-news h-100">

                <div class="news-card-img-wrapper">
                  <?php if (!empty($noticia['imagem_lista'])): ?>
                    <img src="<?php echo htmlspecialchars($noticia['imagem_lista']); ?>" alt="Notícia">
                  <?php else: ?>
                    <img src="assets/img/blog/blog-post-1.webp" alt="Notícia">
                  <?php endif; ?>
                </div>

                <div class="card-body d-flex flex-column">
                  <small class="text-muted d-block mb-1">
                    <?php
                      if (!empty($noticia['data_publicacao'])) {
                          echo date('d/m/Y', strtotime($noticia['data_publicacao']));
                      }
                    ?>
                  </small>

                  <h5 class="card-title">
                    <?php echo htmlspecialchars($noticia['titulo']); ?>
                  </h5>

                  <p class="card-text small text-muted flex-grow-1">
                    <?php echo htmlspecialchars(mb_strimwidth($noticia['resumo'] ?? '', 0, 120, '...', 'UTF-8')); ?>
                  </p>

                  <a href="noticias.php?id=<?php echo (int)$noticia['id']; ?>" class="stretched-link small">
                    Ler notícia
                  </a>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="col-12">
            <p class="text-center text-muted">
              Ainda não existem notícias publicadas.
            </p>
          </div>
        <?php endif; ?>
      </div>

      <div class="text-center mt-3" data-aos="fade-up" data-aos-delay="250">
        <a href="index.php?evora_p=noticias" class="btn btn-outline-primary btn-sm rounded-pill px-3">
          Ver todas as notícias
        </a>
      </div>
    </div>
  </section>
  <!-- /Notícias destaque -->

  <!-- Call to action para login/conta -->
  <section class="call-to-action section dark-background">
    <img src="assets/img/bg/bg-8.webp" alt="" class="img-fluid">
    <div class="container">
      <div class="row" data-aos="zoom-in" data-aos-delay="100">
        <div class="col-xl-9 text-center text-xl-start">
          <?php if (empty($_SESSION['public_user_id'])): ?>
            <h3>Crie a sua conta</h3>
            <p>
              Com uma conta pode aceder à área reservada, gerir o seu perfil
              e ter uma experiência mais personalizada na plataforma.
            </p>
          <?php else: ?>
            <h3>Bem-vindo de volta</h3>
            <p>
              Aceda ao seu perfil para consultar e atualizar os seus dados,
              gerir as suas preferências e acompanhar a sua atividade na plataforma.
            </p>
          <?php endif; ?>
        </div>
        <div class="col-xl-3 cta-btn-container text-center">
          <?php if (empty($_SESSION['public_user_id'])): ?>
            <a class="cta-btn align-middle" href="index.php?evora_p=signup">Criar conta</a>
          <?php else: ?>
            <a class="cta-btn align-middle" href="index.php?evora_p=profile">Ver perfil</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>
  <!-- /Call to action -->

</main>

<?php include "footer.php"; ?>

<a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center">
  <i class="bi bi-arrow-up-short"></i>
</a>

<div id="preloader"></div>

<!-- Vendor JS Files -->
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/php-email-form/validate.js"></script>
<script src="assets/vendor/aos/aos.js"></script>
<script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
<script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
<script src="assets/vendor/waypoints/noframework.waypoints.js"></script>
<script src="assets/vendor/imagesloaded/imagesloaded.pkgd.min.js"></script>
<script src="assets/vendor/isotope-layout/isotope.pkgd.min.js"></script>

<!-- Main JS File -->
<script src="assets/js/main.js"></script>

<!-- JS extra para reforçar proteção das imagens -->
<script>
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.protected-img').forEach(function (img) {
      img.addEventListener('contextmenu', function (e) {
        e.preventDefault();
      });
      img.addEventListener('dragstart', function (e) {
        e.preventDefault();
      });
    });
  });
</script>

</body>
</html>