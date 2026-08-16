<?php
session_start();
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>Informação útil e funcionalidades</title>
  <meta name="description" content="Informação útil para o cidadão e resumo das funcionalidades do sistema Reporta Évora.">
  <meta name="keywords" content="Évora, espaços verdes, mapa, ocorrências, contacto, informação útil">

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

  <style>
    html, body { max-width: 100%; overflow-x: hidden; }
    .main, #hero, #info-util { width: 100%; max-width: 100vw; overflow-x: hidden; }

    #hero {
      height: 300px;
      padding: 0;
      overflow: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      margin-bottom: 40px;
    }

    .info-shell {
      border-radius: 18px;
      border: 1px solid #e5e7eb;
      background:
        radial-gradient(circle at top left, #0d6efd15, transparent 55%),
        radial-gradient(circle at bottom right, #22c55e15, transparent 55%),
        #ffffff;
      box-shadow: 0 14px 40px rgba(15, 23, 42, 0.12);
      padding: 24px;
      margin-bottom: 32px;
    }

    .section-small-title {
      font-size: 0.9rem;
      font-weight: 600;
      color: #4b5563;
      text-transform: uppercase;
      letter-spacing: .12em;
      margin-bottom: 0.35rem;
    }

    .section-small-sub {
      font-size: 0.9rem;
      color: #6b7280;
      margin-bottom: 1rem;
    }

    .info-card {
      border-radius: 16px;
      border: 1px solid #e5e7eb;
      background-color: #ffffff;
      box-shadow: 0 8px 22px rgba(15, 23, 42, 0.08);
      padding: 18px 18px 16px 18px;
      height: 100%;
      display: flex;
      flex-direction: column;
      gap: 0.4rem;
    }

    .info-card-icon {
      width: 40px;
      height: 40px;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
      margin-bottom: 6px;
    }

    .info-card-title {
      font-size: 1rem;
      font-weight: 600;
      margin-bottom: 2px;
      color: #111827;
    }

    .info-card-text {
      font-size: 0.9rem;
      color: #4b5563;
      margin-bottom: 0;
    }

    .info-list {
      font-size: 0.9rem;
      color: #4b5563;
      padding-left: 18px;
      margin-bottom: 0;
    }

    .info-list li + li {
      margin-top: 2px;
    }

    .feature-link {
      font-size: 0.9rem;
      font-weight: 500;
      color: #2563eb;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      margin-top: 4px;
    }
    .feature-link i { font-size: 0.95rem; }
    .feature-link:hover {
      color: #1d4ed8;
      text-decoration: underline;
    }

    @media (max-width: 767.98px) {
      body.index-page {
        padding-top: 40px;
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

  <!-- Hero -->
  <section id="hero" class="section dark-background">
    <div class="container h-100 d-flex align-items-center justify-content-center">
      <div class="row w-100 gy-0 align-items-center justify-content-center">
        <div class="container section-title" data-aos="fade-up" style="margin-top: 5%;">
          <h2>Informação útil e funcionalidades</h2>
          <p>
            Saiba como utilizar o Reporta Évora, consulte funcionalidades
            e veja o que pode fazer em cada área do site.
          </p>
        </div>
      </div>
    </div>
  </section>

  <section id="info-util" class="section">
    <div class="container" data-aos="fade-up" data-aos-delay="80">

      <!-- Bloco de atalhos principais -->
      <div class="info-shell">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
          <div>
            <div class="section-small-title">Acesso rápido</div>
            <p class="section-small-sub mb-0">
              Use os atalhos para abrir o mapa, registar ocorrências, consultar listas e contactar a equipa.
            </p>
          </div>
          <div class="mt-3 mt-md-0 d-flex flex-wrap gap-2">
            <!-- Ver no mapa -->
            <a href="index.php?evora_p=mapa" class="btn btn-outline-primary btn-sm rounded-pill">
              <i class="bi bi-geo-alt me-1"></i> Mapa de ocorrências
            </a>

            <!-- Registar ocorrências -->
            <a href="index.php?evora_p=ocorrencias" class="btn btn-outline-success btn-sm rounded-pill">
              <i class="bi bi-tree me-1"></i> Registar (espaços verdes)
            </a>
            <a href="index.php?evora_p=ocorrenciasestrada" class="btn btn-outline-warning btn-sm rounded-pill">
              <i class="bi bi-signpost-split me-1"></i> Registar (estrada)
            </a>

            <!-- Consultar informação -->
            <a href="index.php?evora_p=listocorrencias" class="btn btn-outline-secondary btn-sm rounded-pill">
              <i class="bi bi-card-list me-1"></i> Ver ocorrências
            </a>
            <a href="index.php?evora_p=noticias" class="btn btn-outline-secondary btn-sm rounded-pill">
              <i class="bi bi-newspaper me-1"></i> Ver notícias
            </a>

            <!-- Contacto -->
            <a href="index.php?evora_p=contact" class="btn btn-outline-dark btn-sm rounded-pill">
              <i class="bi bi-envelope me-1"></i> Contactar equipa
            </a>
          </div>
        </div>
      </div>

      <!-- Secção 1: Informação útil para o cidadão -->
      <div class="info-shell">
        <div class="section-small-title">Informação útil</div>
        <p class="section-small-sub">
          Explicação simples de como a plataforma funciona e como pode ser utilizada no dia a dia.
        </p>

        <!-- Como usar o Reporta Évora -->
        <div class="row g-3 mt-1">
          <div class="col-12">
            <div class="info-card">
              <div class="info-card-icon" style="background:#eef2ff;color:#4338ca;">
                <i class="bi bi-map"></i>
              </div>
              <div class="info-card-title">Como usar o Reporta Évora</div>
              <p class="info-card-text mb-2">
                A plataforma digital permite consultar informação e reportar situações no espaço público.
              </p>
              <ul class="info-list">
                <li><strong>Mapa:</strong> veja árvores, ocorrências em espaços verdes e na estrada no mesmo mapa.</li>
                <li><strong>Registo de ocorrências:</strong> use os formulários de espaços verdes e de estrada para reportar problemas.</li>
                <li><strong>Lista pública:</strong> acompanhe as ocorrências já registadas na cidade.</li>
                <li><strong>Notícias:</strong> leia atualizações e comente temas relacionados com o projeto.</li>
                <li><strong>Conta pública:</strong> ao iniciar sessão pode ver “As Minhas Ocorrências” e “As Minhas Mensagens”.</li>
              </ul>
            </div>
          </div>
        </div>
      </div>

      <!-- Secção 2: Funcionalidades do sistema (resumo das páginas principais) -->
      <div class="info-shell">
        <div class="section-small-title">Funcionalidades do sistema</div>
        <p class="section-small-sub">
          Visão geral das principais páginas públicas da plataforma Reporta Évora.
        </p>

        <div class="row gy-4">
          <!-- Página Inicial -->
          <div class="col-lg-4 col-md-6">
            <div class="info-card">
              <div class="info-card-icon" style="background:#dbeafe;color:#1d4ed8;">
                <i class="bi bi-house-door"></i>
              </div>
              <div class="info-card-title">Página inicial</div>
              <p class="info-card-text">
                Ponto de entrada do site, apresenta o objetivo do projeto e liga rapidamente às restantes áreas.
              </p>
              <ul class="info-list mt-1">
                <li>Resumo do que é o Reporta Évora.</li>
                <li>Acesso rápido a mapa, ocorrências e notícias.</li>
              </ul>
              <a href="index.php?evora_p=inicio" class="feature-link">
                Ir para a página inicial <i class="bi bi-arrow-right"></i>
              </a>
            </div>
          </div>

          <!-- Mapa 2D -->
          <div class="col-lg-4 col-md-6">
            <div class="info-card">
              <div class="info-card-icon" style="background:#dcfce7;color:#15803d;">
                <i class="bi bi-map"></i>
              </div>
              <div class="info-card-title">Mapa 2D interativo</div>
              <p class="info-card-text">
                Mostra árvores, ocorrências de espaços verdes e de estrada num único mapa com filtros.
              </p>
              <ul class="info-list mt-1">
                <li>Filtrar por espécie, intervenção e tarefa.</li>
                <li>Clusters para visualizar melhor zonas com muitos registos.</li>
              </ul>
              <a href="index.php?evora_p=mapa" class="feature-link">
                Abrir mapa 2D <i class="bi bi-arrow-right"></i>
              </a>
            </div>
          </div>

          <!-- Registo de Ocorrências -->
          <div class="col-lg-4 col-md-6">
            <div class="info-card">
              <div class="info-card-icon" style="background:#fee2e2;color:#b91c1c;">
                <i class="bi bi-flag"></i>
              </div>
              <div class="info-card-title">Registo de ocorrências</div>
              <p class="info-card-text">
                Permite reportar problemas em espaços verdes e na estrada, com fotografia opcional.
              </p>
              <ul class="info-list mt-1">
                <li>Escolha o local no mapa ou por pesquisa.</li>
                <li>Indique tipo de intervenção e tarefa.</li>
                <li>Fotografia ajuda as equipas técnicas.</li>
              </ul>
              <a href="index.php?evora_p=ocorrencias" class="feature-link">
                Registar ocorrência (espaços verdes) <i class="bi bi-arrow-right"></i>
              </a>
              <a href="index.php?evora_p=ocorrenciasestrada" class="feature-link">
                Registar ocorrência de estrada <i class="bi bi-arrow-right"></i>
              </a>
            </div>
          </div>

          <!-- Lista de Ocorrências -->
          <div class="col-lg-4 col-md-6">
            <div class="info-card">
              <div class="info-card-icon" style="background:#fef3c7;color:#92400e;">
                <i class="bi bi-card-list"></i>
              </div>
              <div class="info-card-title">Lista pública de ocorrências</div>
              <p class="info-card-text">
                Mostra em cartões as ocorrências registadas no espaço público, com imagem e detalhe.
              </p>
              <ul class="info-list mt-1">
                <li>Descrição, local, coordenadas e tarefa de cada ocorrência.</li>
                <li>Visualização da fotografia em modal quando disponível.</li>
              </ul>
              <a href="index.php?evora_p=listocorrencias" class="feature-link">
                Ver todas as ocorrências <i class="bi bi-arrow-right"></i>
              </a>
              <a href="index.php?evora_p=listarocorrenciasestrada" class="feature-link">
                Ver todas as ocorrências Estrada <i class="bi bi-arrow-right"></i>
              </a>
            </div>
          </div>

          <!-- Contacto -->
          <div class="col-lg-4 col-md-6">
            <div class="info-card">
              <div class="info-card-icon" style="background:#e0f2fe;color:#0369a1;">
                <i class="bi bi-envelope-paper"></i>
              </div>
              <div class="info-card-title">Página de contacto</div>
              <p class="info-card-text">
                Formulário para enviar mensagens, dúvidas ou sugestões diretamente à equipa.
              </p>
              <ul class="info-list mt-1">
                <li>Envio simples de mensagens a partir do site.</li>
                <li>Inclui contactos principais e mapa da localização.</li>
              </ul>
              <a href="index.php?evora_p=contact" class="feature-link">
                Ir para contacto <i class="bi bi-arrow-right"></i>
              </a>
            </div>
          </div>

          <!-- Notícias -->
          <div class="col-lg-4 col-md-6">
            <div class="info-card">
              <div class="info-card-icon" style="background:#f3e8ff;color:#7c3aed;">
                <i class="bi bi-newspaper"></i>
              </div>
              <div class="info-card-title">Notícias da plataforma</div>
              <p class="info-card-text">
                Reúne notícias e destaques relacionados com o projeto e com a cidade.
              </p>
              <ul class="info-list mt-1">
                <li>Leitura das notícias em detalhe.</li>
                <li>Possibilidade de deixar comentários (com login).</li>
              </ul>
              <a href="index.php?evora_p=noticias" class="feature-link">
                Ver notícias <i class="bi bi-arrow-right"></i>
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- Área de utilizador: só aparece se estiver com login -->
      <?php if (!empty($_SESSION['public_user_id'])): ?>
      <div class="info-shell">
        <div class="section-small-title">Área de utilizador</div>
        <p class="section-small-sub">
          Funcionalidades disponíveis quando faz login com uma conta pública.
        </p>

        <div class="row gy-4">
          <!-- Conta pública -->
          <div class="col-lg-4 col-md-6">
            <div class="info-card">
              <div class="info-card-icon" style="background:#dbeafe;color:#1d4ed8;">
                <i class="bi bi-person-circle"></i>
              </div>
              <div class="info-card-title">Conta pública</div>
              <p class="info-card-text">
                Possibilidade de criar conta, iniciar sessão e gerir o seu perfil.
              </p>
              <ul class="info-list mt-1">
                <li>Registo e login com validações.</li>
                <li>Página de perfil com dados do utilizador.</li>
              </ul>
              <a href="index.php?evora_p=profile" class="feature-link">
                Ver o meu perfil <i class="bi bi-arrow-right"></i>
              </a>
            </div>
          </div>

          <!-- As Minhas Ocorrências -->
          <div class="col-lg-4 col-md-6">
            <div class="info-card">
              <div class="info-card-icon" style="background:#dcfce7;color:#15803d;">
                <i class="bi bi-flag-fill"></i>
              </div>
              <div class="info-card-title">As Minhas Ocorrências</div>
              <p class="info-card-text">
                Área onde o utilizador vê apenas as ocorrências que ele próprio registou.
              </p>
              <ul class="info-list mt-1">
                <li>Separação por “Espaços verdes” e “Estrada”.</li>
                <li>Cartões com descrição, local, data e coordenadas.</li>
              </ul>
              <a href="index.php?evora_p=myocorrencias" class="feature-link">
                Aceder às minhas ocorrências <i class="bi bi-arrow-right"></i>
              </a>
            </div>
          </div>

          <!-- As Minhas Mensagens -->
          <div class="col-lg-4 col-md-6">
            <div class="info-card">
              <div class="info-card-icon" style="background:#fef3c7;color:#92400e;">
                <i class="bi bi-chat-dots"></i>
              </div>
              <div class="info-card-title">As Minhas Mensagens</div>
              <p class="info-card-text">
                Histórico de mensagens de contacto e comentários em notícias feitos pelo utilizador.
              </p>
              <ul class="info-list mt-1">
                <li>Mensagens de contacto enviadas pelo formulário.</li>
                <li>Comentários associados a notícias.</li>
              </ul>
              <a href="index.php?evora_p=mymensagens" class="feature-link">
                Ver minhas mensagens <i class="bi bi-arrow-right"></i>
              </a>
            </div>
          </div>

          <!-- Segurança -->
          <div class="col-lg-4 col-md-6">
            <div class="info-card">
              <div class="info-card-icon" style="background:#fee2e2;color:#b91c1c;">
                <i class="bi bi-shield-lock"></i>
              </div>
              <div class="info-card-title">Segurança da conta</div>
              <p class="info-card-text">
                Opções ligadas à palavra-passe, recuperação e validação adicional (2FA).
              </p>
              <ul class="info-list mt-1">
                <li>Recuperação de password por email.</li>
                <li>Verificação de código 2FA nas operações protegidas.</li>
              </ul>
              <a href="index.php?evora_p=segurancapublic" class="feature-link">
                Ver opções de segurança <i class="bi bi-arrow-right"></i>
              </a>
            </div>
          </div>

        </div>
      </div>
      <?php endif; ?>

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

</body>
</html>
