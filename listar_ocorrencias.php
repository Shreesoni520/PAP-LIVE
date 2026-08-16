<?php
ob_start();
session_start();
include './config.php';

$registosPorPagina = 6;

$paginaAtual = isset($_GET['page']) && is_numeric($_GET['page'])
    ? (int)$_GET['page']
    : 1;
if ($paginaAtual < 1) {
    $paginaAtual = 1;
}

$sqlTotal = "
    SELECT COUNT(*) AS total
    FROM ocorrencias
";
$resultTotal   = $conn->query($sqlTotal);
$totalRegistos = 0;
if ($resultTotal && $rowTotal = $resultTotal->fetch_assoc()) {
    $totalRegistos = (int)$rowTotal['total'];
}

$totalPaginas = $totalRegistos > 0
    ? (int)ceil($totalRegistos / $registosPorPagina)
    : 1;

if ($paginaAtual > $totalPaginas) {
    $paginaAtual = $totalPaginas;
}

$offset = ($paginaAtual - 1) * $registosPorPagina;

$ocorrencias = $conn->query("
    SELECT o.id,
           o.descricao,
           o.latitude,
           o.longitude,
           o.place_name,
           o.tipo_intervencao,
           o.estado,
           o.imagem,
           o.data_ocorrencia,
           o.criado_em
    FROM ocorrencias o
    ORDER BY o.criado_em DESC, o.id DESC
    LIMIT $registosPorPagina OFFSET $offset
");
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Ocorrências</title>

  <link href="assets/img/favicon.png" rel="icon">
  <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&family=Poppins:wght@300;400;500;600;700&family=Jost:wght@300;400;500;600;700&display=swap" rel="stylesheet">

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
    .main,
    #hero,
    #ocorrencias {
      width: 100%;
      max-width: 100vw;
      overflow-x: hidden;
    }
    #hero {
      margin-bottom: 40px;
    }
    .ocor-list-section {
      padding-top: 80px;
      padding-bottom: 100px;
    }
    .ocor-card {
      border-radius: 18px;
      border: 1px solid #e5e7eb;
      background-color: #ffffff;
      box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
      overflow: hidden;
      display: flex;
      flex-direction: column;
      height: 100%;
    }
    .ocor-card-img-wrapper img {
      width: 100%;
      height: 210px;
      object-fit: cover;
      cursor: pointer;
      display: block;
    }
    .ocor-card-body {
      padding: 0.9rem 1.1rem 0.6rem 1.1rem;
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 0.15rem;
    }
    .ocor-footer {
      border-top: 1px solid #e5e7eb;
      padding: 0.4rem 1.1rem 0.5rem 1.1rem;
      font-size: 0.78rem;
      color: #6b7280;
      background-color: #f9fafb;
      text-align: right;
    }
    .ocor-line {
      font-size: 0.9rem;
    }
    .ocor-label {
      font-weight: 600;
      color: #6b7280;
      margin-right: 4px;
    }
    .ocor-value {
      color: #111827;
    }
    .ocor-descricao {
      white-space: normal;
      word-wrap: break-word;
      overflow-wrap: break-word;
    }
    .ocor-pagination .page-item .page-link {
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
    .ocor-pagination .page-item .page-link:hover {
      color: #0f172a;
      border-color: #0d6efd;
      background-color: #eff6ff;
      text-decoration: none;
      transform: translateY(-1px);
    }
    .ocor-pagination .page-item.active .page-link {
      background: linear-gradient(135deg, #0d6efd, #2563eb);
      border-color: #0d6efd;
      color: #ffffff;
      box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
      cursor: default;
    }
    .ocor-pagination .page-item.disabled .page-link,
    .ocor-pagination .page-item .page-link[aria-disabled="true"] {
      color: #9ca3af;
      border-color: #e5e7eb;
      background-color: #f9fafb;
      box-shadow: none;
    }
    .ocor-pagination {
      gap: 2px;
    }
    .ocor-empty {
      padding: 3rem 1rem;
    }
    .ocor-empty-icon {
      font-size: 2.2rem;
      margin-bottom: 0.75rem;
      color: #9ca3af;
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

  <section id="hero" class="section dark-background"
           style="height: 300px; padding: 0; overflow: hidden; display: flex; align-items: center; justify-content: center; text-align: center;">
    <div class="container h-100 d-flex align-items-center justify-content-center">
      <div class="row w-100 gy-0 align-items-center justify-content-center">
        <div class="container section-title" data-aos="fade-up" style="margin-top: 5%;">
          <h2>Ocorrências registadas</h2>
          <p>Consulte as ocorrências registadas no sistema e visualize a sua localização e tarefa.</p>
        </div>
      </div>
    </div>
  </section>

  <section id="ocorrencias" class="ocor-list-section">
    <div class="container" data-aos="fade-up" data-aos-delay="100">

      <?php if ($ocorrencias && $ocorrencias->num_rows > 0): ?>
        <div class="row gy-4">
          <?php while ($ocor = $ocorrencias->fetch_assoc()):
            $dataOcorrenciaRaw   = $ocor['data_ocorrencia'];
            $textoDataOcorrencia = $dataOcorrenciaRaw ? date('d/m/Y', strtotime($dataOcorrenciaRaw)) : 'Sem data';
            $textoCriadoEm       = date('d/m/Y H:i', strtotime($ocor['criado_em']));
            $descricao       = $ocor['descricao'] ?? '';
            $placeName       = $ocor['place_name'] ?: 'Sem nome';
            $tipoIntervencao = $ocor['tipo_intervencao'] ?: 'Nenhuma';
            $tarefa          = $ocor['estado'] ?: 'Sem tarefa';
            $imagemPath = !empty($ocor['imagem'])
              ? "/PAP/uploads/ocorrencias/" . htmlspecialchars($ocor['imagem'], ENT_QUOTES, 'UTF-8')
              : "assets/img/blog/blog-post-1.webp";
          ?>
            <div class="col-12 col-md-4">
              <div class="ocor-card">

                <div class="ocor-card-img-wrapper">
                  <img
                    src="<?= $imagemPath ?>"
                    alt="<?= htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8') ?>"
                    data-bs-toggle="modal"
                    data-bs-target="#imagemModal<?= (int)$ocor['id'] ?>"
                  >
                </div>

                <div class="modal fade" id="imagemModal<?= (int)$ocor['id'] ?>" tabindex="-1" aria-hidden="true">
                  <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title">Imagem da ocorrência</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body text-center">
                        <img
                          src="<?= $imagemPath ?>"
                          alt="Imagem da ocorrência"
                          class="img-fluid"
                          style="max-height: 70vh; object-fit: contain;"
                        >
                      </div>
                    </div>
                  </div>
                </div>

                <div class="ocor-card-body">
                  <div class="ocor-line">
                    <span class="ocor-label">Descrição:</span>
                    <span class="ocor-value ocor-descricao">
                      <?= htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                  </div>
                  <div class="ocor-line">
                    <span class="ocor-label">Local:</span>
                    <span class="ocor-value">
                      <?= htmlspecialchars($placeName, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                  </div>
                  <div class="ocor-line">
                    <span class="ocor-label">Latitude/Longitude:</span>
                    <span class="ocor-value">
                      <?= htmlspecialchars($ocor['latitude'], ENT_QUOTES, 'UTF-8') ?>,
                      <?= htmlspecialchars($ocor['longitude'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                  </div>
                  <div class="ocor-line">
                    <span class="ocor-label">Tipo de intervenção:</span>
                    <span class="ocor-value">
                      <?= htmlspecialchars($tipoIntervencao, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                  </div>
                  <div class="ocor-line">
                    <span class="ocor-label">Tarefa:</span>
                    <span class="ocor-value">
                      <?= htmlspecialchars($tarefa, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                  </div>
                  <div class="ocor-line">
                    <span class="ocor-label">Data da ocorrência:</span>
                    <span class="ocor-value">
                      <?= $textoDataOcorrencia ?>
                    </span>
                  </div>
                </div>

                <div class="ocor-footer">
                  Criado em: <?= $textoCriadoEm ?>
                </div>

              </div>
            </div>
          <?php endwhile; ?>
        </div>

        <?php if ($totalPaginas > 1): ?>
          <nav aria-label="Navegação de páginas" class="mt-4">
            <ul class="pagination justify-content-center ocor-pagination">
              <?php if ($paginaAtual > 1): ?>
                <li class="page-item">
                  <a class="page-link"
                     href="index.php?evora_p=listocorrencias&page=<?= $paginaAtual - 1 ?>#ocorrencias"
                     aria-label="Página anterior">
                    <span aria-hidden="true">&laquo;</span>
                  </a>
                </li>
              <?php endif; ?>

              <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
                <li class="page-item <?= $p == $paginaAtual ? 'active' : '' ?>">
                  <a class="page-link"
                     href="index.php?evora_p=listocorrencias&page=<?= $p ?>#ocorrencias">
                    <?= $p ?>
                  </a>
                </li>
              <?php endfor; ?>

              <?php if ($paginaAtual < $totalPaginas): ?>
                <li class="page-item">
                  <a class="page-link"
                     href="index.php?evora_p=listocorrencias&page=<?= $paginaAtual + 1 ?>#ocorrencias"
                     aria-label="Próxima página">
                    <span aria-hidden="true">&raquo;</span>
                  </a>
                </li>
              <?php endif; ?>
            </ul>
          </nav>
        <?php endif; ?>

      <?php else: ?>
        <div class="ocor-empty text-center">
          <div class="ocor-empty-icon">
            <i class="bi bi-geo-alt"></i>
          </div>
          <h5 class="mb-1">Ainda não existem ocorrências</h5>
          <p class="text-muted mb-0">
            Quando forem registadas ocorrências no espaço público, aparecerão aqui com a respetiva localização e tarefa.
          </p>
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

<?php
ob_end_flush();
?>
</body>
</html>
