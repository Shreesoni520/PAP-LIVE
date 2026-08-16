<?php
session_start();
include './config.php';


// Bloquear acesso se não tiver login
if (empty($_SESSION['public_user_id'])) {
    header('Location: index.php?evora_p=login');
    exit;
}

$user_id = (int) $_SESSION['public_user_id'];

// Buscar ocorrências de espaços verdes
$ocorrencias = [];
$stmt = $conn->prepare("
    SELECT id, descricao, place_name, tipo_intervencao, estado, imagem, data_ocorrencia, criado_em, latitude, longitude
    FROM ocorrencias
    WHERE user_id = ?
    ORDER BY criado_em DESC
");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $ocorrencias[] = $row;
    }
    $stmt->close();
}

// Buscar ocorrências de estrada
$ocorrencias_estrada = [];
$stmt2 = $conn->prepare("
    SELECT id, descricao, place_name, tipo_intervencao, estado, imagem, data_ocorrencia, criado_em, latitude, longitude
    FROM ocorrencias_estrada
    WHERE user_id = ?
    ORDER BY criado_em DESC
");
if ($stmt2) {
    $stmt2->bind_param("i", $user_id);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    while ($row = $res2->fetch_assoc()) {
        $ocorrencias_estrada[] = $row;
    }
    $stmt2->close();
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>As Minhas Ocorrências</title>

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
        .main, #hero, #my-ocorrencias { width: 100%; max-width: 100vw; overflow-x: hidden; }
        .profile-shell {
            border-radius: 18px;
            background: radial-gradient(circle at top left, #0d6efd15, transparent 55%),
                        radial-gradient(circle at bottom right, #22c55e15, transparent 55%),
                        #ffffff;
            border: 1px solid #e5e7eb;
            box-shadow: 0 14px 40px rgba(15, 23, 42, 0.12);
            padding: 24px;
        }
        .pill-nav .nav-link {
            border-radius: 999px;
            padding: 0.45rem 1rem;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .pill-nav .nav-link.active {
            background: linear-gradient(135deg, #0d6efd, #2563eb);
            color: #fff;
            box-shadow: 0 6px 18px rgba(37, 99, 235, 0.4);
        }
        .occ-card {
            border-radius: 16px;
            border: 1px solid #e5e7eb;
            background: #ffffff;
            box-shadow: 0 8px 22px rgba(15, 23, 42, 0.08);
            transition: transform 0.12s ease, box-shadow 0.12s ease;
            height: 100%;
        }
        .occ-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.16);
        }
        .occ-badge {
            font-size: 0.75rem;
            border-radius: 999px;
            padding: 0.15rem 0.6rem;
        }
        .occ-meta { font-size: 0.8rem; color: #6b7280; }
        .empty-state {
            border-radius: 18px;
            border: 1px dashed #cbd5f5;
            background: #f9fafb;
            padding: 24px;
            text-align: center;
        }
        .empty-icon {
            width: 46px;
            height: 46px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #eff6ff;
            color: #2563eb;
            margin-bottom: 10px;
        }

        @media (max-width: 767.98px) {
          body.index-page {
            padding-top: 60px; /* pequeno espaço extra para o header fixo */
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
             style="height: 260px; padding: 0; overflow: hidden; display: flex; align-items: center; justify-content: center; text-align: center;">
        <div class="container h-100 d-flex align-items-center justify-content-center">
            <div class="row w-100 gy-0 align-items-center justify-content-center">
                <div class="container section-title" data-aos="fade-up" style="margin-top: 5%;">
                    <h2>As Minhas Ocorrências</h2>
                    <p>Veja todas as ocorrências que registou na plataforma, em espaços verdes e na estrada.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="my-ocorrencias" class="section">
        <div class="container" data-aos="fade-up" data-aos-delay="100">

            <div class="profile-shell">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="mb-1">Histórico de ocorrências</h5>
                        <p class="text-muted mb-0" style="font-size: 0.9rem;">
                            Visualize e acompanhe os registos que efetuou no sistema.
                        </p>
                    </div>
                    <div class="mt-3 mt-md-0 d-flex gap-2">
                        <a href="ocorrencias.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-tree me-1"></i> Nova ocorrência (espaços verdes)
                        </a>
                        <a href="ocorrencias_estrada.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-signpost-split me-1"></i> Nova ocorrência de estrada
                        </a>
                    </div>
                </div>

                <ul class="nav nav-pills pill-nav mb-3" id="pills-tab" role="tablist">
                    <li class="nav-item me-1" role="presentation">
                        <button class="nav-link active" id="pill-verdes-tab" data-bs-toggle="pill"
                                data-bs-target="#pill-verdes" type="button" role="tab"
                                aria-controls="pill-verdes" aria-selected="true">
                            <i class="bi bi-tree me-1"></i> Espaços verdes
                            <span class="badge bg-light text-dark ms-1">
                                <?php echo count($ocorrencias); ?>
                            </span>
                        </button>
                    </li>
                    <li class="nav-item me-1" role="presentation">
                        <button class="nav-link" id="pill-estrada-tab" data-bs-toggle="pill"
                                data-bs-target="#pill-estrada" type="button" role="tab"
                                aria-controls="pill-estrada" aria-selected="false">
                            <i class="bi bi-signpost-split me-1"></i> Estrada
                            <span class="badge bg-light text-dark ms-1">
                                <?php echo count($ocorrencias_estrada); ?>
                            </span>
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="pills-tabContent">
                    <!-- Espaços verdes -->
                    <div class="tab-pane fade show active" id="pill-verdes" role="tabpanel" aria-labelledby="pill-verdes-tab">
                        <?php if (empty($ocorrencias)): ?>
                            <div class="empty-state mt-2">
                                <div class="empty-icon">
                                    <i class="bi bi-tree"></i>
                                </div>
                                <h6 class="mb-1">Ainda não tem ocorrências de espaços verdes.</h6>
                                <p class="text-muted mb-2" style="font-size: 0.9rem;">
                                    Quando registar uma nova ocorrência, ela aparecerá aqui.
                                </p>
                                <a href="ocorrencias.php" class="btn btn-primary btn-sm">
                                    Registar primeira ocorrência
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="row g-3 mt-1">
                                <?php foreach ($ocorrencias as $o): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="occ-card p-3 d-flex flex-column h-100">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <span class="occ-badge bg-success-subtle text-success">
                                                    <i class="bi bi-tree me-1"></i>
                                                    Espaços verdes
                                                </span>
                                                <?php if (!empty($o['estado'])): ?>
                                                    <span class="occ-badge bg-light text-muted">
                                                        <i class="bi bi-circle-half me-1"></i>
                                                        <?php echo htmlspecialchars($o['estado']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="mb-2" style="font-size: 0.9rem;">
                                                <?php echo nl2br(htmlspecialchars(mb_strimwidth($o['descricao'], 0, 160, '...'))); ?>
                                            </p>
                                            <div class="occ-meta mt-auto">
                                                <?php if (!empty($o['place_name'])): ?>
                                                    <div>
                                                        <i class="bi bi-geo-alt me-1"></i>
                                                        <?php echo htmlspecialchars($o['place_name']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <i class="bi bi-calendar-event me-1"></i>
                                                    <?php
                                                    $data = !empty($o['data_ocorrencia']) ? $o['data_ocorrencia'] : $o['criado_em'];
                                                    echo date('d/m/Y H:i', strtotime($data));
                                                    ?>
                                                </div>
                                                <div>
                                                    <i class="bi bi-geo me-1"></i>
                                                    <?php echo htmlspecialchars($o['latitude']); ?>,
                                                    <?php echo htmlspecialchars($o['longitude']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Estrada -->
                    <div class="tab-pane fade" id="pill-estrada" role="tabpanel" aria-labelledby="pill-estrada-tab">
                        <?php if (empty($ocorrencias_estrada)): ?>
                            <div class="empty-state mt-2">
                                <div class="empty-icon" style="background:#fef3c7;color:#d97706;">
                                    <i class="bi bi-signpost-split"></i>
                                </div>
                                <h6 class="mb-1">Ainda não tem ocorrências de estrada.</h6>
                                <p class="text-muted mb-2" style="font-size: 0.9rem;">
                                    Registe problemas na via pública para acompanhar a sua tarefa.
                                </p>
                                <a href="ocorrencias_estrada.php" class="btn btn-primary btn-sm">
                                    Registar ocorrência de estrada
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="row g-3 mt-1">
                                <?php foreach ($ocorrencias_estrada as $o): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="occ-card p-3 d-flex flex-column h-100">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <span class="occ-badge bg-warning-subtle text-warning">
                                                    <i class="bi bi-signpost-split me-1"></i>
                                                    Estrada
                                                </span>
                                                <?php if (!empty($o['estado'])): ?>
                                                    <span class="occ-badge bg-light text-muted">
                                                        <i class="bi bi-list-check me-1"></i>
                                                        <?php echo htmlspecialchars($o['estado']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="mb-2" style="font-size: 0.9rem;">
                                                <?php echo nl2br(htmlspecialchars(mb_strimwidth($o['descricao'], 0, 160, '...'))); ?>
                                            </p>
                                            <div class="occ-meta mt-auto">
                                                <?php if (!empty($o['place_name'])): ?>
                                                    <div>
                                                        <i class="bi bi-geo-alt me-1"></i>
                                                        <?php echo htmlspecialchars($o['place_name']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <i class="bi bi-calendar-event me-1"></i>
                                                    <?php
                                                    $data = !empty($o['data_ocorrencia']) ? $o['data_ocorrencia'] : $o['criado_em'];
                                                    echo date('d/m/Y H:i', strtotime($data));
                                                    ?>
                                                </div>
                                                <div>
                                                    <i class="bi bi-geo me-1"></i>
                                                    <?php echo htmlspecialchars($o['latitude']); ?>,
                                                    <?php echo htmlspecialchars($o['longitude']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
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

</body>
</html>
