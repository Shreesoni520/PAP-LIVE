<?php
session_start();
require './config.php';

// ----------------------------------------------------
// VER SE ESTÁ EM DETALHE OU LISTA
// ----------------------------------------------------
$detalhe            = false;
$noticiaSelecionada = null;

// Se tiver ?id= na URL, tenta buscar uma notícia específica
if (isset($_GET['id']) && ctype_digit($_GET['id'])) {
    $id = (int) $_GET['id'];

    $stmt = $conn->prepare("
        SELECT id, titulo, resumo, conteudo, imagem_lista, imagem_detalhe,
               autor, data_publicacao, categoria
        FROM noticias
        WHERE id = ?
    ");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows === 1) {
            $noticiaSelecionada = $res->fetch_assoc();
            $detalhe = true;
        }
        $stmt->close();
    }
}

// ----------------------------------------------------
// PROCESSAR COMENTÁRIOS (SE ESTIVER EM DETALHE)
// ----------------------------------------------------

// ID e nome do utilizador autenticado
$logged_user_id   = !empty($_SESSION['public_user_id']) ? (int) $_SESSION['public_user_id'] : 0;
$logged_user_nome = '';

if ($logged_user_id > 0) {
    if (!empty($_SESSION['public_user_nome'])) {
        $logged_user_nome = trim($_SESSION['public_user_nome']);
    } else {
        $stmtUser = $conn->prepare("SELECT nome FROM users_public WHERE id = ? LIMIT 1");
        if ($stmtUser) {
            $stmtUser->bind_param('i', $logged_user_id);
            $stmtUser->execute();
            $resUser = $stmtUser->get_result();
            if ($resUser && ($rowUser = $resUser->fetch_assoc())) {
                $logged_user_nome = trim($rowUser['nome']);
                $_SESSION['public_user_nome'] = $logged_user_nome;
            }
            $stmtUser->close();
        }
    }
}

if (
    $detalhe &&
    $noticiaSelecionada &&
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["comentario_submit"])
) {
    $texto = trim($_POST["comentario"] ?? '');

    if ($logged_user_id <= 0 || $logged_user_nome === '') {
        header('Location: index.php?evora_p=login');
        exit;
    }

    if ($texto !== '') {
        $nome    = $logged_user_nome;
        $user_id = $logged_user_id;

        $stmtCom = $conn->prepare("
            INSERT INTO comentarios_noticias (noticia_id, nome, texto, user_id)
            VALUES (?, ?, ?, ?)
        ");
        if ($stmtCom) {
            $nid = (int) $noticiaSelecionada['id'];
            $stmtCom->bind_param("issi", $nid, $nome, $texto, $user_id);
            $stmtCom->execute();
            $stmtCom->close();

            header("Location: noticias.php?id=" . $noticiaSelecionada['id'] . "#comentarios");
            exit;
        }
    }
}

// ----------------------------------------------------
// PESQUISA + LISTA + PAGINAÇÃO (SE NÃO ESTIVER EM DETALHE)
// ----------------------------------------------------
$termoPesquisa     = '';
$noticiasFiltradas = [];

$sqlBase = "
    SELECT id, titulo, resumo, conteudo, imagem_lista, imagem_detalhe,
           autor, data_publicacao, categoria
    FROM noticias
";

$primeiraPaginaLimite = 2;
$limitePadrao         = 6;

$paginaAtual = isset($_GET['page']) && is_numeric($_GET['page'])
    ? (int) $_GET['page']
    : 1;
if ($paginaAtual < 1) {
    $paginaAtual = 1;
}

$whereSql = '';
$params   = [];
$types    = '';

if (!$detalhe && isset($_GET['q'])) {
    $termoPesquisa = trim($_GET['q']);
    if ($termoPesquisa !== '') {
        $like = '%' . $termoPesquisa . '%';
        $whereSql = " WHERE titulo LIKE ? OR resumo LIKE ? ";
        $params[] = $like;
        $params[] = $like;
        $types   .= 'ss';
    }
}

$sqlCount = "SELECT COUNT(*) AS total FROM noticias " . $whereSql;

$stmtCount = $conn->prepare($sqlCount);
if ($stmtCount && $whereSql !== '') {
    $stmtCount->bind_param($types, ...$params);
}

$totalRegistos = 0;
if ($stmtCount) {
    $stmtCount->execute();
    $resCount = $stmtCount->get_result();
    if ($resCount && ($rowC = $resCount->fetch_assoc())) {
        $totalRegistos = (int) $rowC['total'];
    }
    $stmtCount->close();
}

if ($totalRegistos <= $primeiraPaginaLimite) {
    $totalPaginas = 1;
} else {
    $restantes    = $totalRegistos - $primeiraPaginaLimite;
    $paginasExtra = (int) ceil($restantes / $limitePadrao);
    $totalPaginas = 1 + $paginasExtra;
}
if ($totalPaginas < 1) {
    $totalPaginas = 1;
}
if ($paginaAtual > $totalPaginas) {
    $paginaAtual = $totalPaginas;
}

if ($paginaAtual === 1) {
    $registosPorPagina = $primeiraPaginaLimite;
    $offset            = 0;
} else {
    $registosPorPagina = $limitePadrao;
    $offset            = $primeiraPaginaLimite + ($paginaAtual - 2) * $limitePadrao;
}

if (!$detalhe) {
    $sqlLista = $sqlBase . $whereSql . " ORDER BY id DESC LIMIT ? OFFSET ? ";

    $stmt = $conn->prepare($sqlLista);
    if ($stmt) {
        if ($whereSql !== '') {
            $typesFull  = $types . 'ii';
            $paramsFull = $params;
            $paramsFull[] = $registosPorPagina;
            $paramsFull[] = $offset;

            $stmt->bind_param($typesFull, ...$paramsFull);
        } else {
            $stmt->bind_param("ii", $registosPorPagina, $offset);
        }

        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $noticiasFiltradas[$row['id']] = $row;
        }
        $stmt->close();
    }
}

// ----------------------------------------------------
// RECENTES
// ----------------------------------------------------
$recentes = [];
$stmtRec = $conn->prepare($sqlBase . " ORDER BY id DESC LIMIT 4");
if ($stmtRec) {
    $stmtRec->execute();
    $resRec = $stmtRec->get_result();
    while ($row = $resRec->fetch_assoc()) {
        $recentes[$row['id']] = $row;
    }
    $stmtRec->close();
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <?php if ($detalhe && $noticiaSelecionada): ?>
    <title><?php echo htmlspecialchars($noticiaSelecionada['titulo']); ?> - Arsha</title>
  <?php else: ?>
    <title>Notícias - Arsha</title>
  <?php endif; ?>

  <meta name="description" content="Notícias sobre a plataforma tridimensional de gestão urbana de espaços verdes em Évora.">
  <meta name="keywords" content="Évora, espaços verdes, Leaflet, Three.js, gestão urbana, poda, corte, árvores">

  <link href="assets/img/favicon.png" rel="icon">
  <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800;900&family=Jost:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/aos/aos.css" rel="stylesheet">
  <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
  <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">

  <link href="assets/css/main.css" rel="stylesheet">

  <style>
    html, body {
      max-width: 100%;
      overflow-x: hidden;
    }

    #hero-noticias,
    #noticias,
    .service-details,
    .section,
    .main {
      width: 100%;
      max-width: 100vw;
      overflow-x: hidden;
    }

    .services-img {
      max-height: 400px;
      width: 100%;
      object-fit: cover;
    }

    .post-img img {
      max-height: 250px;
      width: 100%;
      object-fit: cover;
    }

    .noticias-hero {
      height: 300px;
      padding: 0;
      overflow: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
    }

    .read-more a {
      display: inline-block;
      border-radius: 999px;
      padding: 0.35rem 0.9rem;
      font-size: 0.85rem;
      border: 1px solid #e5e7eb;
      color: #ffffff;
      background: linear-gradient(135deg, #0d6efd, #2563eb);
      box-shadow: 0 3px 8px rgba(37, 99, 235, 0.4);
      text-decoration: none;
      transition: all 0.15s ease-in-out;
    }

    .read-more a:hover {
      transform: translateY(-1px);
      box-shadow: 0 5px 14px rgba(37, 99, 235, 0.5);
      text-decoration: none;
      color: #ffffff;
    }

    .blog-posts article {
      border-radius: 14px;
      border: 1px solid #e5e7eb;
      background: #ffffff;
      box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
      overflow: hidden;
      height: 100%;
      display: flex;
      flex-direction: column;
      transition: transform 0.12s ease, box-shadow 0.12s ease;
    }

    .blog-posts article:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 20px rgba(15, 23, 42, 0.12);
    }

    .blog-posts article .post-img img {
      max-height: 220px;
      width: 100%;
      object-fit: cover;
    }

    .blog-posts article .card-body-simple {
      padding: 0.8rem 1rem 0.8rem 1rem;
      display: flex;
      flex-direction: column;
      gap: 0.35rem;
      flex: 1;
    }

    .blog-posts article .news-title {
      font-size: 1rem;
      font-weight: 600;
      margin: 0;
    }

    .blog-posts article .news-title a {
      color: #111827;
      text-decoration: none;
    }

    .blog-posts article .news-title a:hover {
      color: #2563eb;
    }

    .blog-posts article .news-meta {
      font-size: 0.82rem;
      color: #6b7280;
      display: flex;
      flex-direction: column;
      gap: 0.1rem;
    }

    .blog-posts article .news-meta span {
      display: flex;
      align-items: center;
      gap: 0.3rem;
    }

    .blog-posts article .news-excerpt {
      font-size: 0.9rem;
      color: #4b5563;
      margin: 0.2rem 0 0.3rem 0;
      display: -webkit-box;
      -webkit-line-clamp: 3;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .blog-posts article .card-footer-simple {
      padding: 0.6rem 1rem 0.7rem 1rem;
      border-top: 1px solid #e5e7eb;
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 0.8rem;
      color: #6b7280;
      background-color: #f9fafb;
    }

    .news-category-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
      padding: 0.15rem 0.55rem;
      border-radius: 999px;
      font-size: 0.75rem;
      font-weight: 500;
    }

    .news-category-plataforma {
      background-color: #ecfdf3;
      color: #15803d;
    }

    .news-category-estrada {
      background-color: #eff6ff;
      color: #1d4ed8;
    }

    .news-category-default {
      background-color: #f3f4f6;
      color: #4b5563;
    }

    .noticias-pagination .page-item .page-link {
      border-radius: 999px;
      margin: 0 3px;
      padding: 0.35rem 0.75rem;
      border: 1px solid #e5e7eb;
      color: #4b5563;
      background-color: #ffffff;
      box-shadow: 0 3px 8px rgba(15, 23, 42, 0.08);
      font-size: 0.9rem;
      transition: all 0.15s ease-in-out;
    }

    .noticias-pagination .page-item .page-link:hover {
      color: #0f172a;
      border-color: #0d6efd;
      background-color: #eff6ff;
      text-decoration: none;
      transform: translateY(-1px);
    }

    .noticias-pagination .page-item.active .page-link {
      background: linear-gradient(135deg, #0d6efd, #2563eb);
      border-color: #0d6efd;
      color: #ffffff;
      box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
      cursor: default;
    }

    .noticias-pagination {
      gap: 2px;
    }

    .widget-card {
      border-radius: 16px;
      border: 1px solid #e5e7eb;
      background-color: #ffffff;
      box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
      overflow: hidden;
      margin-bottom: 1.4rem;
    }

    .widget-card-header {
      padding: 0.75rem 1rem;
      border-bottom: 1px solid #e5e7eb;
      background: linear-gradient(135deg, #f9fafb, #eef2ff);
    }

    .widget-card-title {
      margin: 0;
      font-size: 0.95rem;
      font-weight: 600;
      letter-spacing: .06em;
      text-transform: uppercase;
      color: #4b5563;
    }

    .widget-card-body {
      padding: 0.85rem 1rem 1rem 1rem;
    }

    .news-search-card {
      border-radius: 16px;
      border: 1px solid #e5e7eb;
      background-color: #ffffff;
      box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
      overflow: hidden;
      margin-bottom: 1.4rem;
    }

    .news-search-card-header {
      padding: 0.75rem 1rem;
      border-bottom: 1px solid #e5e7eb;
      background: linear-gradient(135deg, #f9fafb, #eef2ff);
    }

    .news-search-title {
      margin: 0;
      font-size: 0.95rem;
      font-weight: 600;
      letter-spacing: .06em;
      text-transform: uppercase;
      color: #4b5563;
    }

    .news-search-subtitle {
      margin: 0.15rem 0 0 0;
      font-size: 0.8rem;
      color: #6b7280;
    }

    .news-search-card-body {
      padding: 0.85rem 1rem 1rem 1rem;
    }

    .news-search-shell {
      border-radius: 999px;
      border: 1px solid #e5e7eb;
      background-color: #f9fafb;
      padding: 0.15rem 0.3rem;
    }

    .news-search-inner {
      display: flex;
      align-items: center;
      gap: 0.3rem;
    }

    .news-search-icon {
      color: #6b7280;
      font-size: 0.95rem;
      padding-left: 0.15rem;
    }

    .news-search-input {
      flex: 1;
      border: none;
      background: transparent;
      font-size: 0.9rem;
      padding: 0.3rem 0.2rem;
      color: #111827;
      outline: none;
    }

    .news-search-input::placeholder {
      color: #9ca3af;
    }

    .news-search-clear {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 26px;
      height: 26px;
      border-radius: 999px;
      color: #9ca3af;
      text-decoration: none;
      transition: background-color 0.15s, color 0.15s, transform 0.1s;
    }

    .news-search-clear:hover {
      background-color: #e5e7eb;
      color: #4b5563;
      transform: translateY(-1px);
    }

    .news-search-btn {
      border: none;
      border-radius: 999px;
      padding: 0.3rem 0.85rem;
      font-size: 0.8rem;
      font-weight: 500;
      letter-spacing: .04em;
      text-transform: uppercase;
      color: #ffffff;
      background: linear-gradient(135deg, #0d6efd, #2563eb);
      box-shadow: 0 3px 8px rgba(37, 99, 235, 0.35);
      white-space: nowrap;
      cursor: pointer;
      transition: transform 0.12s, box-shadow 0.12s;
    }

    .news-search-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 5px 14px rgba(37, 99, 235, 0.5);
    }

    .news-recent-list {
      display: flex;
      flex-direction: column;
      gap: 0.7rem;
    }

    .news-recent-item {
      display: flex;
      align-items: flex-start;
      gap: 0.65rem;
      padding: 0.4rem 0.15rem;
      border-radius: 10px;
      transition: background-color 0.15s, transform 0.15s;
    }

    .news-recent-item:hover {
      background-color: #f9fafb;
      transform: translateY(-1px);
    }

    .news-recent-thumb img {
      width: 60px;
      height: 60px;
      object-fit: cover;
      border-radius: 12px;
    }

    .news-recent-content {
      flex: 1;
      min-width: 0;
    }

    .news-recent-title {
      display: block;
      font-size: 0.9rem;
      font-weight: 600;
      color: #111827;
      text-decoration: none;
      margin-bottom: 0.50rem;
    }

    .news-recent-title:hover {
      color: #2563eb;
      text-decoration: none;
    }

    .news-recent-meta-line {
      display: flex;
      flex-wrap: wrap;
      gap: 0.25rem;
    }

    .news-recent-meta-chip {
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
      padding: 0.05rem 0.45rem;
      border-radius: 999px;
      background-color: #f3f4f6;
      font-size: 0.75rem;
      color: #4b5563;
    }

    @media (max-width: 767.98px) {
      body.index-page {
        padding-top: 40px;
        background-color: #37517e;
      }

      .noticias-hero .section-title h2 {
        font-size: 1.6rem;
        line-height: 1.2;
      }

      .noticias-hero .section-title p {
        font-size: 0.92rem;
      }
    }
  </style>
</head>

<body class="index-page">

<header id="header" class="header d-flex align-items-center fixed-top">
    <?php include "menu.php"; ?>
</header>

<main class="main">

  <section id="hero-noticias" class="section dark-background noticias-hero">
    <div class="container h-100 d-flex align-items-center justify-content-center">
      <div class="row w-100 gy-0 align-items-center justify-content-center">
        <div class="container section-title" data-aos="fade-up" style="margin-top: 5%;">
          <?php if ($detalhe && $noticiaSelecionada): ?>
            <h2><?php echo htmlspecialchars($noticiaSelecionada['titulo']); ?></h2>
            <p>Detalhe da notícia integrada na plataforma tridimensional de gestão urbana.</p>
          <?php else: ?>
            <h2>Notícias da Plataforma</h2>
            <p>Explore as notícias relacionadas com a Plataforma Tridimensional para Gestão de Espaços Verdes em Évora.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <?php if (!$detalhe): ?>
    <section id="noticias" class="section">
      <div class="container">
        <div class="row">

          <?php if ($paginaAtual === 1): ?>
            <div class="col-lg-4 sidebar order-0 order-lg-2">
              <div class="widgets-container" data-aos="fade-up" data-aos-delay="200">

                <div class="news-search-card">
                  <div class="news-search-card-header">
                    <h3 class="news-search-title">Pesquisar notícias</h3>
                    <p class="news-search-subtitle">Procure por assuntos, termos técnicos ou intervenções</p>
                  </div>
                  <div class="news-search-card-body">
                    <form action="noticias.php" method="get" class="news-search-form">
                      <div class="news-search-shell">
                        <div class="news-search-inner">
                          <i class="bi bi-search news-search-icon"></i>
                          <input
                            type="text"
                            name="q"
                            class="news-search-input"
                            placeholder="Pesquisar"
                            value="<?php echo htmlspecialchars($termoPesquisa); ?>">
                          <?php if ($termoPesquisa !== ''): ?>
                            <a href="noticias.php" class="news-search-clear" title="Limpar pesquisa">
                              <i class="bi bi-x-lg"></i>
                            </a>
                          <?php endif; ?>
                          <button type="submit" class="news-search-btn">
                            Procurar
                          </button>
                        </div>
                      </div>
                    </form>
                  </div>
                </div>

                <div class="widget-card">
                  <div class="widget-card-header">
                    <h3 class="widget-card-title">Notícias recentes</h3>
                  </div>
                  <div class="widget-card-body">
                    <?php if (empty($recentes)): ?>
                      <p class="mb-0 text-muted" style="font-size: 0.9rem;">
                        Ainda não existem notícias recentes.
                      </p>
                    <?php else: ?>
                      <ul class="list-unstyled mb-0 news-recent-list">
                        <?php foreach ($recentes as $r): ?>
                          <?php
                          $comentariosQtd = 0;
                          $stmtCnt = $conn->prepare("
                              SELECT COUNT(*) AS total
                              FROM comentarios_noticias
                              WHERE noticia_id = ?
                          ");
                          if ($stmtCnt) {
                              $rid = $r['id'];
                              $stmtCnt->bind_param("i", $rid);
                              $stmtCnt->execute();
                              $resCnt = $stmtCnt->get_result();
                              if ($rowCnt = $resCnt->fetch_assoc()) {
                                  $comentariosQtd = (int)$rowCnt['total'];
                              }
                              $stmtCnt->close();
                          }
                          ?>
                          <li class="news-recent-item">
                            <a href="noticias.php?id=<?php echo $r['id']; ?>" class="news-recent-thumb">
                              <?php if (!empty($r['imagem_lista'])): ?>
                                <img src="<?php echo htmlspecialchars($r['imagem_lista']); ?>" alt="">
                              <?php else: ?>
                                <img src="assets/img/blog/blog-post-1.webp" alt="">
                              <?php endif; ?>
                            </a>

                            <div class="news-recent-content">
                              <a href="noticias.php?id=<?php echo $r['id']; ?>" class="news-recent-title">
                                <?php echo htmlspecialchars($r['titulo']); ?>
                              </a>

                              <div class="news-recent-meta-line">
                                <span class="news-recent-meta-chip">
                                  <i class="bi bi-person"></i>
                                  <?php echo htmlspecialchars($r['autor'] ?? ''); ?>
                                </span>

                                <?php if (!empty($r['data_publicacao'])): ?>
                                  <span class="news-recent-meta-chip">
                                    <i class="bi bi-calendar-event"></i>
                                    <?php echo date('d/m/Y', strtotime($r['data_publicacao'])); ?>
                                  </span>
                                <?php endif; ?>

                                <?php if ($comentariosQtd > 0): ?>
                                  <span class="news-recent-meta-chip">
                                    <i class="bi bi-chat-dots"></i>
                                    <?php echo $comentariosQtd === 1 ? '1 comentário' : $comentariosQtd . ' comentários'; ?>
                                  </span>
                                <?php else: ?>
                                  <span class="news-recent-meta-chip">
                                    <i class="bi bi-chat-dots"></i>
                                    Sem comentários
                                  </span>
                                <?php endif; ?>
                              </div>
                            </div>
                          </li>
                        <?php endforeach; ?>
                      </ul>
                    <?php endif; ?>
                  </div>
                </div>

              </div>
            </div>

            <div class="col-lg-8 order-1 order-lg-1">
          <?php else: ?>
            <div class="col-12">
          <?php endif; ?>

              <section id="blog-posts" class="blog-posts section">
                <div class="container" data-aos="fade-up" data-aos-delay="100">
                  <div class="row gy-4">

                    <?php if (empty($noticiasFiltradas)): ?>
                      <div class="col-12">
                        <?php if ($termoPesquisa !== ''): ?>
                          <p>Não foram encontradas notícias para "<?php echo htmlspecialchars($termoPesquisa); ?>".</p>
                        <?php else: ?>
                          <p>Não existem notícias registadas.</p>
                        <?php endif; ?>
                      </div>
                    <?php else: ?>
                      <?php foreach ($noticiasFiltradas as $n): ?>
                        <?php if ($paginaAtual === 1): ?>
                          <div class="col-lg-6">
                        <?php else: ?>
                          <div class="col-lg-4 col-md-6">
                        <?php endif; ?>

                            <article>
                              <div class="post-img">
                                <?php if (!empty($n['imagem_lista'])): ?>
                                  <img src="<?php echo htmlspecialchars($n['imagem_lista']); ?>" alt="" class="img-fluid">
                                <?php else: ?>
                                  <img src="assets/img/blog/blog-post-1.webp" alt="" class="img-fluid">
                                <?php endif; ?>
                              </div>

                              <div class="card-body-simple">
                                <h2 class="news-title">
                                  <a href="noticias.php?id=<?php echo $n['id']; ?>">
                                    <?php echo htmlspecialchars($n['titulo']); ?>
                                  </a>
                                </h2>

                                <div class="news-meta">
                                  <span>
                                    <i class="bi bi-person"></i>
                                    <strong>Autor:</strong>
                                    <span><?php echo htmlspecialchars($n['autor'] ?? ''); ?></span>
                                  </span>
                                  <span>
                                    <i class="bi bi-calendar-event"></i>
                                    <strong>Publicado em:</strong>
                                    <span>
                                      <?php
                                      if (!empty($n['data_publicacao'])) {
                                          echo date('d/m/Y', strtotime($n['data_publicacao']));
                                      }
                                      ?>
                                    </span>
                                  </span>
                                </div>

                                <p class="news-excerpt">
                                  <?php echo nl2br(htmlspecialchars($n['resumo'])); ?>
                                </p>
                              </div>

                              <div class="card-footer-simple">
                                <span class="news-category-badge
                                  <?php
                                    $cat = $n['categoria'] ?? '';
                                    if ($cat === 'plataforma') {
                                        echo 'news-category-plataforma';
                                    } elseif ($cat === 'estrada') {
                                        echo 'news-category-estrada';
                                    } else {
                                        echo 'news-category-default';
                                    }
                                  ?>
                                ">
                                  <i class="bi bi-tag"></i>
                                  <?php
                                    if ($cat === 'plataforma') {
                                        echo 'Espaços Verdes';
                                    } elseif ($cat === 'estrada') {
                                        echo 'Estrada';
                                    } else {
                                        echo 'Geral';
                                    }
                                  ?>
                                </span>

                                <div class="read-more mb-0">
                                  <a href="noticias.php?id=<?php echo $n['id']; ?>">Ler mais</a>
                                </div>
                              </div>
                            </article>
                          </div>
                      <?php endforeach; ?>
                    <?php endif; ?>

                  </div>

                  <?php if ($totalPaginas > 1): ?>
                    <nav aria-label="Navegação de páginas" class="mt-4">
                      <ul class="pagination justify-content-center noticias-pagination">
                        <?php
                        $qParam = $termoPesquisa !== '' ? '&q=' . urlencode($termoPesquisa) : '';
                        ?>

                        <?php if ($paginaAtual > 1): ?>
                          <li class="page-item">
                            <a class="page-link"
                               href="noticias.php?page=<?php echo $paginaAtual - 1; ?><?php echo $qParam; ?>#noticias"
                               aria-label="Página anterior">
                              <span aria-hidden="true">&laquo;</span>
                            </a>
                          </li>
                        <?php endif; ?>

                        <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
                          <li class="page-item <?php echo $p == $paginaAtual ? 'active' : ''; ?>">
                            <a class="page-link"
                               href="noticias.php?page=<?php echo $p; ?><?php echo $qParam; ?>#noticias">
                              <?php echo $p; ?>
                            </a>
                          </li>
                        <?php endfor; ?>

                        <?php if ($paginaAtual < $totalPaginas): ?>
                          <li class="page-item">
                            <a class="page-link"
                               href="noticias.php?page=<?php echo $paginaAtual + 1; ?><?php echo $qParam; ?>#noticias"
                               aria-label="Próxima página">
                              <span aria-hidden="true">&raquo;</span>
                            </a>
                          </li>
                        <?php endif; ?>
                      </ul>
                    </nav>
                  <?php endif; ?>

                </div>
              </section>
            </div>

        </div>
      </div>
    </section>

  <?php elseif ($detalhe && $noticiaSelecionada): ?>
    <section id="noticias" class="service-details section">
      <div class="container">
        <div class="row gy-4">

          <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
            <div class="services-list mb-3">
              <a href="noticias.php" class="active">Voltar às notícias</a>
            </div>

            <div class="card border-0 shadow-sm rounded-4 mb-3">
              <div class="card-body">
                <h5 class="card-title mb-3">Informação</h5>
                <p class="mb-1">
                  <i class="bi bi-person me-1 text-primary"></i>
                  <strong>Autor:</strong>
                  <?php echo htmlspecialchars($noticiaSelecionada['autor'] ?? ''); ?>
                </p>
                <p class="mb-3">
                  <i class="bi bi-calendar-event me-1 text-primary"></i>
                  <strong>Data:</strong>
                  <?php
                  if (!empty($noticiaSelecionada['data_publicacao'])) {
                      echo date('d/m/Y', strtotime($noticiaSelecionada['data_publicacao']));
                  }
                  ?>
                </p>
                <p class="small text-muted mb-1">
                  <strong>Tipo de notícia:</strong>
                  <?php
                    $cat = $noticiaSelecionada['categoria'] ?? '';
                    if ($cat === 'plataforma') {
                        echo 'Plataforma (espaços verdes)';
                    } elseif ($cat === 'estrada') {
                        echo 'Estrada';
                    } else {
                        echo 'Geral';
                    }
                  ?>
                </p>
                <p class="small text-muted mb-0">
                  Esta notícia está integrada na Plataforma Tridimensional para Gestão de Espaços Verdes em Évora, ligando intervenções reais aos módulos 2D e 3D.
                </p>
              </div>
            </div>

            <?php
            $listaComentarios = [];
            $stmtLC = $conn->prepare("
                SELECT nome, texto, criado_em
                FROM comentarios_noticias
                WHERE noticia_id = ?
                ORDER BY criado_em DESC
            ");
            if ($stmtLC) {
                $nid = $noticiaSelecionada['id'];
                $stmtLC->bind_param("i", $nid);
                $stmtLC->execute();
                $resLC = $stmtLC->get_result();
                while ($rowC = $resLC->fetch_assoc()) {
                    $listaComentarios[] = $rowC;
                }
                $stmtLC->close();
            }
            $totalComentarios = count($listaComentarios);
            ?>

            <div class="card border-0 shadow-sm rounded-4">
              <div class="card-header bg-white border-0 pb-0 d-flex align-items-center justify-content-between">
                <div>
                  <h5 class="card-title mb-0">Comentários</h5>
                  <small class="text-muted">
                    <?php echo $totalComentarios === 0 ? 'Ainda não há comentários' :
                        ($totalComentarios === 1 ? '1 comentário' : $totalComentarios . ' comentários'); ?>
                  </small>
                </div>

                <button
                  class="btn btn-light btn-sm rounded-pill d-flex align-items-center gap-1"
                  type="button"
                  data-bs-toggle="collapse"
                  data-bs-target="#commentsPanel"
                  aria-expanded="false"
                  aria-controls="commentsPanel"
                >
                  <i class="bi bi-chat-dots"></i>
                  <span>Ver comentários</span>
                </button>
              </div>

              <div class="card-body pt-3">

                <div id="comentarios">
                  <?php if ($logged_user_id > 0 && $logged_user_nome !== ''): ?>
                    <form
                      action="noticias.php?id=<?php echo $noticiaSelecionada['id']; ?>#comentarios"
                      method="post"
                      class="comment-form mb-3"
                    >
                      <div class="mb-2">
                        <label class="form-label mb-1" style="font-size: 0.85rem;">A comentar como</label>
                        <div
                          class="form-control form-control-sm rounded-3 bg-light text-muted"
                          style="cursor: not-allowed;"
                        >
                          <?php echo htmlspecialchars($logged_user_nome); ?>
                        </div>
                      </div>
                      <div class="mb-2">
                        <label for="comentario" class="form-label mb-1" style="font-size: 0.85rem;">Comentário</label>
                        <textarea
                          name="comentario"
                          id="comentario"
                          rows="2"
                          class="form-control form-control-sm rounded-3"
                          placeholder="Partilhe a sua opinião sobre esta notícia..."
                          required
                        ></textarea>
                      </div>
                      <div class="d-flex justify-content-end">
                        <button
                          type="submit"
                          name="comentario_submit"
                          class="btn btn-primary btn-sm rounded-pill d-flex align-items-center gap-1"
                        >
                          <i class="bi bi-send-fill"></i>
                          <span>Publicar</span>
                        </button>
                      </div>
                    </form>
                  <?php else: ?>
                    <div class="alert alert-light border rounded-3 small mb-3">
                      <i class="bi bi-person-lock me-1"></i>
                      <a href="index.php?evora_p=login" class="fw-semibold">Inicie sessão</a>
                      para publicar um comentário com o seu nome de conta.
                    </div>
                  <?php endif; ?>
                </div>

                <div class="collapse" id="commentsPanel">
                  <?php if (empty($listaComentarios)): ?>
                    <p class="text-muted small mb-3">
                      Ainda não existem comentários para esta notícia.
                    </p>
                  <?php else: ?>
                    <div
                      class="border rounded-4 px-3 py-2 mb-1"
                      style="max-height: 260px; overflow-y: auto; background-color: #f9fafb;"
                    >
                      <?php foreach ($listaComentarios as $c): ?>
                        <div class="d-flex mb-3 pb-2 border-bottom">
                          <div
                            class="flex-shrink-0 rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2"
                            style="width: 34px; height: 34px; font-size: 0.9rem; font-weight: 600;"
                          >
                            <?php
                            $nomeC = $c['nome'] ?? '';
                            $iniciais = '';
                            $partes = preg_split('/\s+/', trim($nomeC));
                            if (!empty($partes[0])) $iniciais .= mb_strtoupper(mb_substr($partes[0], 0, 1));
                            if (!empty($partes[1])) $iniciais .= mb_strtoupper(mb_substr($partes[1], 0, 1));
                            echo htmlspecialchars($iniciais ?: 'C');
                            ?>
                          </div>

                          <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center">
                              <span class="fw-semibold" style="font-size: 0.9rem;">
                                <?php echo htmlspecialchars($c['nome']); ?>
                              </span>
                              <small class="text-muted" style="font-size: 0.75rem;">
                                <?php echo date('d/m H:i', strtotime($c['criado_em'])); ?>
                              </small>
                            </div>
                            <p class="mb-1" style="font-size: 0.88rem;">
                              <?php echo nl2br(htmlspecialchars($c['texto'])); ?>
                            </p>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </div>

              </div>
            </div>
          </div>

          <div class="col-lg-8" data-aos="fade-up" data-aos-delay="200">

            <?php
            $detString = $noticiaSelecionada['imagem_detalhe'] ?? '';
            $detImages = $detString !== '' ? explode('|', $detString) : [];
            $galleryId = 'noticia-' . $noticiaSelecionada['id'];
            ?>

            <?php if (!empty($detImages)): ?>
            <div id="noticiaCarousel" class="carousel slide mb-3" data-bs-ride="carousel">
              <div class="carousel-inner">
                <?php foreach ($detImages as $idx => $path): ?>
                  <div class="carousel-item <?php echo $idx === 0 ? 'active' : ''; ?>">
                    <a href="<?php echo htmlspecialchars($path); ?>"
                       class="glightbox"
                       data-gallery="<?php echo $galleryId; ?>">
                      <img src="<?php echo htmlspecialchars($path); ?>" class="d-block w-100 services-img" alt="">
                    </a>
                  </div>
                <?php endforeach; ?>
              </div>
              <button class="carousel-control-prev" type="button" data-bs-target="#noticiaCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Anterior</span>
              </button>
              <button class="carousel-control-next" type="button" data-bs-target="#noticiaCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Seguinte</span>
              </button>
            </div>
            <?php endif; ?>

            <h3><?php echo htmlspecialchars($noticiaSelecionada['titulo']); ?></h3>

            <p><?php echo nl2br(htmlspecialchars($noticiaSelecionada['resumo'])); ?></p>

            <ul>
              <li><i class="bi bi-check-circle"></i> <span>Relacionada com gestão urbana de espaços verdes.</span></li>
              <li><i class="bi bi-check-circle"></i> <span>Integrada com o mapa 2D Leaflet.</span></li>
              <li><i class="bi bi-check-circle"></i> <span>Apoia o planeamento e a comunicação com cidadãos e técnicos.</span></li>
            </ul>

            <p><?php echo nl2br(htmlspecialchars($noticiaSelecionada['conteudo'])); ?></p>

          </div>

        </div>
      </div>
    </section>
  <?php endif; ?>

</main>

<?php include "footer.php"; ?>

<a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center">
  <i class="bi bi-arrow-up-short"></i>
</a>

<div id="preloader"></div>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/php-email-form/validate.js"></script>
<script src="assets/vendor/aos/aos.js"></script>
<script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
<script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
<script src="assets/vendor/waypoints/noframework.waypoints.js"></script>
<script src="assets/vendor/imagesloaded/imagesloaded.pkgd.min.js"></script>
<script src="assets/vendor/isotope-layout/isotope.pkgd.min.js"></script>

<script>
  const lightbox = GLightbox({
    selector: '.glightbox'
  });
</script>

<script src="assets/js/main.js"></script>

</body>
</html>
