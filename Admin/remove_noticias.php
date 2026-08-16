<?php
include './log.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$success = null;
$error   = null;

/* ========= MULTI OR SINGLE DELETE ========= */
if (!empty($_POST)) {
    if (isset($_POST['mode']) && $_POST['mode'] === 'multi' && !empty($_POST['delete_ids']) && is_array($_POST['delete_ids'])) {
        // MULTI DELETE (checkboxes)
        $ids = array_map('intval', $_POST['delete_ids']);

        if (count($ids) > 0) {
            $in    = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));

            $sqlSel = "SELECT id, imagem_lista, imagem_detalhe, titulo FROM noticias WHERE id IN ($in)";
            $stmtSel = $conn->prepare($sqlSel);

            if ($stmtSel === false) {
                $error = "Erro na preparação da query (select múltiplo).";
            } else {
                $stmtSel->bind_param($types, ...$ids);
                $stmtSel->execute();
                $resultSel = $stmtSel->get_result();
                $rowsToDelete = [];

                while ($r = $resultSel->fetch_assoc()) {
                    $rowsToDelete[] = $r;
                }

                $stmtSel->close();

                $stmtDel = $conn->prepare("DELETE FROM noticias WHERE id IN ($in)");
                if ($stmtDel === false) {
                    $error = "Erro na preparação da query (delete múltiplo).";
                } else {
                    $stmtDel->bind_param($types, ...$ids);

                    if ($stmtDel->execute()) {
                        $success = "Notícias removidas com sucesso.";

                        $baseDir = dirname(__DIR__) . '/uploads/noticias/';
                        $userId  = (int)$_SESSION['user_id'];

                        foreach ($rowsToDelete as $rowN) {
                            $nid            = (int)$rowN['id'];
                            $imagem_lista   = $rowN['imagem_lista'] ?? '';
                            $imagem_detalhe = $rowN['imagem_detalhe'] ?? '';
                            $tituloNoticia  = $rowN['titulo'] ?? 'Sem título';

                            regista_log(
                                $conn,
                                $userId,
                                "remover",
                                "noticias",
                                $nid,
                                "Notícia apagada em remoção múltipla. Título: " . ($tituloNoticia ?: 'Sem título')
                            );

                            $acao    = 'Remoção de notícia';
                            $detalhe = "Notícia ID $nid removida em remoção múltipla. Título: " . ($tituloNoticia ?: 'Sem título');

                            if ($stmtAt = $conn->prepare("
                                INSERT INTO atividade (user_id, acao, detalhe)
                                VALUES (?, ?, ?)
                            ")) {
                                $stmtAt->bind_param("iss", $userId, $acao, $detalhe);
                                $stmtAt->execute();
                                $stmtAt->close();
                            }

                            if (!empty($imagem_lista)) {
                                $listaParts = explode('|', $imagem_lista);
                                foreach ($listaParts as $imgPath) {
                                    $imgPath = trim($imgPath);
                                    if ($imgPath === '') continue;
                                    $nomeLista     = basename($imgPath);
                                    $ficheiroLista = $baseDir . $nomeLista;
                                    if (file_exists($ficheiroLista)) {
                                        @unlink($ficheiroLista);
                                    }
                                }
                            }

                            if (!empty($imagem_detalhe)) {
                                $imagensDet = explode('|', $imagem_detalhe);
                                foreach ($imagensDet as $imgPath) {
                                    $imgPath = trim($imgPath);
                                    if ($imgPath === '') continue;
                                    $nomeDet     = basename($imgPath);
                                    $ficheiroDet = $baseDir . $nomeDet;
                                    if (file_exists($ficheiroDet)) {
                                        @unlink($ficheiroDet);
                                    }
                                }
                            }
                        }
                    } else {
                        $error = "Erro ao remover notícias selecionadas!";
                    }

                    $stmtDel->close();
                }
            }
        }
    } elseif (isset($_POST['mode']) && $_POST['mode'] === 'single' && isset($_POST['delete_id'])) {
        // SINGLE DELETE
        $id = (int)$_POST['delete_id'];

        $stmtSel = $conn->prepare("
            SELECT imagem_lista, imagem_detalhe, titulo
            FROM noticias
            WHERE id = ?
        ");
        if ($stmtSel === false) {
            $error = "Erro na preparação da query (select).";
        } else {
            $stmtSel->bind_param("i", $id);
            $stmtSel->execute();
            $stmtSel->bind_result($imagem_lista, $imagem_detalhe, $tituloNoticia);
            $stmtSel->fetch();
            $stmtSel->close();

            $stmt = $conn->prepare("DELETE FROM noticias WHERE id = ?");
            if ($stmt === false) {
                $error = "Erro na preparação da query (delete).";
            } else {
                $stmt->bind_param("i", $id);

                if ($stmt->execute()) {
                    $success = "Notícia removida com sucesso.";

                    regista_log(
                        $conn,
                        $_SESSION['user_id'],
                        "remover",
                        "noticias",
                        $id,
                        "Notícia apagada. Título: " . ($tituloNoticia ?: 'Sem título')
                    );

                    $userId  = (int) $_SESSION['user_id'];
                    $acao    = 'Remoção de notícia';
                    $detalhe = "Notícia ID $id removida. Título: " . ($tituloNoticia ?: 'Sem título');

                    if ($stmtAt = $conn->prepare("
                        INSERT INTO atividade (user_id, acao, detalhe)
                        VALUES (?, ?, ?)
                    ")) {
                        $stmtAt->bind_param("iss", $userId, $acao, $detalhe);
                        $stmtAt->execute();
                        $stmtAt->close();
                    }

                    $baseDir = dirname(__DIR__) . '/uploads/noticias/';

                    if (!empty($imagem_lista)) {
                        $listaParts = explode('|', $imagem_lista);
                        foreach ($listaParts as $imgPath) {
                            $imgPath = trim($imgPath);
                            if ($imgPath === '') continue;
                            $nomeLista     = basename($imgPath);
                            $ficheiroLista = $baseDir . $nomeLista;
                            if (file_exists($ficheiroLista)) {
                                @unlink($ficheiroLista);
                            }
                        }
                    }

                    if (!empty($imagem_detalhe)) {
                        $imagensDet = explode('|', $imagem_detalhe);
                        foreach ($imagensDet as $imgPath) {
                            $imgPath = trim($imgPath);
                            if ($imgPath === '') continue;
                            $nomeDet     = basename($imgPath);
                            $ficheiroDet = $baseDir . $nomeDet;
                            if (file_exists($ficheiroDet)) {
                                @unlink($ficheiroDet);
                            }
                        }
                    }
                } else {
                    $error = "Erro ao remover notícia!";
                }

                $stmt->close();
            }
        }
    }

    header('Location: index.php?evora=removernoticias');
    exit();
}

/* ========= CARREGAR TODAS AS NOTÍCIAS (SEM LIMIT/OFFSET) ========= */
$stmtList = $conn->prepare("
    SELECT id,
           titulo,
           resumo,
           conteudo,
           imagem_lista,
           imagem_detalhe,
           autor,
           categoria,
           data_publicacao,
           criado_em
    FROM noticias
    ORDER BY id DESC
");
$stmtList->execute();
$noticias = $stmtList->get_result();

/* ========= FILTROS: AUTORES / TÍTULOS ========= */
$autores_result = $conn->query("
    SELECT DISTINCT autor
    FROM noticias
    WHERE autor IS NOT NULL AND autor <> ''
    ORDER BY autor ASC
");

$titulos_result = $conn->query("
    SELECT DISTINCT titulo
    FROM noticias
    WHERE titulo IS NOT NULL AND titulo <> ''
    ORDER BY titulo ASC
");

/* ========= PAGINAÇÃO CLIENT-SIDE (VISUAL) ========= */
$per_page       = 6;
$total_news     = $noticias ? $noticias->num_rows : 0;
$total_pages_js = $per_page > 0 ? (int)ceil($total_news / $per_page) : 1;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Remover Notícias</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link
        href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/css/tom-select.bootstrap5.min.css">

    <style>
        body, .sidebar, .card, .btn, h4, h3, h2 {
            font-family: 'Nunito', sans-serif !important;
        }
        .page-content {
            background: radial-gradient(circle at top, #e0f2fe, #eef2ff 40%, #f9fafb 80%);
        }
        .card-main {
            border-radius: 20px;
            border: 0;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.12);
        }
        .card-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .card-header {
            border-bottom: 1px solid #e5e7eb;
        }
        .page-heading-custom h3 {
            font-weight: 800;
        }
        .page-heading-custom p {
            color: #6b7280;
        }
        #openFilterPanel,
        #toggleSelectMode {
            border-radius: 999px;
            padding: 0.4rem 1rem;
            font-size: 0.9rem;
        }
        .filter-panel {
            position: absolute;
            top: 72px;
            right: 24px;
            width: 320px;
            background: #ffffff;
            padding: 22px 18px 16px 18px;
            border-radius: 18px;
            z-index: 2000;
            border: 1px solid #e5e7eb;
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.18);
        }
        .filter-panel .form-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #6b7280;
        }
        .filter-panel .form-control,
        .filter-panel .form-select {
            font-size: 0.85rem;
            border-radius: 10px;
        }
        .filter-panel-actions {
            padding-top: 8px;
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
        }
        .ts-wrapper.single .ts-control,
        .ts-wrapper.single.input-active .ts-control {
            background-color: #ffffff !important;
            border-radius: 10px;
            padding: 0.375rem 0.75rem;
            border-color: #d1d5db;
        }
        .ts-wrapper.single .ts-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(37,99,235,0.25);
            border-color: #2563eb;
        }
        .ts-dropdown {
            background-color: #ffffff !important;
        }
        .news-card-container {
            display: flex;
        }
        .news-card {
            border-radius: 16px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
            overflow: hidden;
            background-color: #ffffff;
            display: flex;
            flex-direction: column;
            width: 100%;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .news-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12);
        }
        .news-card-body {
            padding: 0.9rem 1.1rem 0.6rem 1.1rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .news-card-footer {
            background-color: #f9fafb;
            padding: 0.45rem 1.1rem 0.55rem 1.1rem;
            font-size: 0.78rem;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.5rem;
        }
        .news-title {
            font-weight: 800;
            font-size: 1rem;
            margin-bottom: 0.1rem;
            color: #111827;
        }
        .news-label {
            font-weight: 600;
            font-size: 13px;
            color: #6b7280;
        }
        .news-value {
            font-size: 14px;
            color: #111827;
        }
        .news-line {
            margin-bottom: 0.1rem;
        }
        .news-resumo {
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
            font-size: 0.86rem;
            color: #4b5563;
            margin-top: 0.15rem;
        }
        .news-image-line {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
            margin-top: 0.15rem;
        }
        .news-image-btn {
            padding: 0 6px;
            font-size: 0.85rem;
            line-height: 1.2;
            height: 1.4rem;
        }
        .section-subtitle {
            font-size: 0.85rem;
            color: #9ca3af;
        }
        .multi-actions {
            display: none;
        }
        .multi-actions.show {
            display: flex;
        }
        .img-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.82);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.18s ease-out, visibility 0.18s ease-out;
        }
        .img-overlay.show {
            opacity: 1;
            visibility: visible;
        }
        .img-overlay-inner {
            position: relative;
            max-width: 92vw;
            max-height: 92vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .img-overlay img {
            max-width: 80vw;
            max-height: 80vh;
            border-radius: 12px;
            box-shadow: 0 16px 36px rgba(0,0,0,0.6);
            object-fit: contain;
        }
        .img-nav-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            border-radius: 999px;
            border: none;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0f172a;
            background: #f9fafb;
            box-shadow: 0 10px 26px rgba(15,23,42,0.6);
            cursor: pointer;
            transition: transform 0.12s ease, box-shadow 0.12s ease;
        }
        .img-nav-btn i {
            font-size: 1rem;
        }
        .img-nav-btn-left { left: -22px; }
        .img-nav-btn-right { right: -22px; }
        .img-nav-btn:hover {
            transform: translateY(-50%) translateY(-1px);
            box-shadow: 0 14px 32px rgba(15,23,42,0.8);
        }
        @media (max-width: 768px) {
            .filter-panel {
                position: fixed;
                top: 88px;
                right: 12px;
                width: 260px;
                max-width: 80%;
                padding: 14px 12px 10px 12px;
                border-radius: 14px;
                box-shadow: 0 10px 28px rgba(15, 23, 42, 0.18);
            }
            .filter-panel .form-label {
                font-size: 0.75rem;
            }
            .filter-panel .form-control,
            .filter-panel .form-select,
            .ts-wrapper.single .ts-control,
            .ts-wrapper.single.input-active .ts-control {
                font-size: 0.8rem;
                padding: 0.25rem 0.6rem;
            }
            .filter-panel-actions {
                gap: 0.35rem;
            }
            .filter-panel-actions .btn {
                font-size: 0.75rem;
                padding: 0.3rem 0.5rem;
                border-radius: 999px;
            }
            .card-header-flex {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.8rem;
            }
            .news-card-footer {
                flex-direction: column;
                align-items: flex-start;
            }
        }
        @media (max-width: 576px) {
            .img-overlay-inner {
                max-width: 94vw;
                max-height: 86vh;
            }
            .img-overlay img {
                max-width: 86vw;
                max-height: 70vh;
            }
            .img-nav-btn-left { left: -10px; }
            .img-nav-btn-right { right: -10px; }
        }
        @media (max-width: 992px) {
            #main { margin-left: 0 !important; }
            .sidebar-wrapper {
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                z-index: 1500;
            }
            .page-content { position: relative; z-index: 1; }
            #app { overflow-x: hidden; }
            .card-main .card-body {
                padding: 1rem 0.9rem 1.1rem 0.9rem;
            }
            .page-heading-custom h3 {
                font-size: 1.4rem;
            }
            .page-heading-custom p {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
<div id="app">
<?php include "menu.php"; ?>
<div id="main">
<header class="mb-3 d-flex align-items-center">
    <a href="#" class="burger-btn d-block d-xl-none me-2">
        <i class="bi bi-justify fs-3"></i>
    </a>
</header>

<div class="page-heading-custom mb-3">
    <h3 class="mb-1">Remover notícias</h3>
    <p class="text-subtitle text-muted mb-0">
        Veja e remova notícias registadas no sistema.
    </p>
</div>

<div class="page-content">
<section class="section">
    <?php if ($success): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="card card-main position-relative">
        <div class="card-header">
            <div class="card-header-flex">
                <div>
                    <h4 class="mb-1">Notícias registadas</h4>
                    <span class="section-subtitle">
                        Use os filtros ou ative o modo de seleção múltipla.
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button
                        id="toggleSelectMode"
                        class="btn btn-outline-secondary d-flex align-items-center"
                        type="button"
                    >
                        <i class="bi bi-check2-square me-1"></i>
                        <span>Modo seleção</span>
                    </button>
                    <button
                        id="openFilterPanel"
                        class="btn btn-outline-secondary d-flex align-items-center"
                        type="button"
                    >
                        <i class="bi bi-funnel-fill me-1"></i>
                        <span>Filtrar</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- PAINEL DE FILTROS -->
        <div id="filterPanel" class="filter-panel d-none">
            <form id="newsFilterForm">
                <div class="mb-2">
                    <label for="filterTitulo" class="form-label">Título</label>
                    <select id="filterTitulo" class="form-select js-nice-select">
                        <option value="">Todos</option>
                        <?php if ($titulos_result && $titulos_result->num_rows > 0): ?>
                            <?php while ($t = $titulos_result->fetch_assoc()): ?>
                                <?php if (!empty($t['titulo'])): ?>
                                    <option value="<?= htmlspecialchars(strtolower($t['titulo'])) ?>">
                                        <?= htmlspecialchars($t['titulo']) ?>
                                    </option>
                                <?php endif; ?>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label for="filterAutor" class="form-label">Autor</label>
                    <select id="filterAutor" class="form-select js-nice-select">
                        <option value="">Todos</option>
                        <?php if ($autores_result && $autores_result->num_rows > 0): ?>
                            <?php while ($a = $autores_result->fetch_assoc()): ?>
                                <?php if (!empty($a['autor'])): ?>
                                    <option value="<?= htmlspecialchars(strtolower($a['autor'])) ?>">
                                        <?= htmlspecialchars($a['autor']) ?>
                                    </option>
                                <?php endif; ?>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="mb-2">
                    <label for="filterCategoria" class="form-label">Tipo de notícia</label>
                    <select id="filterCategoria" class="form-select js-nice-select">
                        <option value="">Todos</option>
                        <option value="plataforma">Plataforma (espaços verdes)</option>
                        <option value="estrada">Estrada</option>
                        <option value="outros">Outros</option>
                    </select>
                </div>

                <div class="mb-2">
                    <label for="filterData" class="form-label">Data de publicação</label>
                    <input type="date" class="form-control" id="filterData">
                </div>
                <div class="filter-panel-actions">
                    <button type="button" class="btn btn-light btn-sm" id="clearFilters">Limpar</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="closeFilterPanel">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm">Aplicar</button>
                </div>
            </form>
        </div>
        <!-- FIM PAINEL DE FILTROS -->

        <div class="card-body">
            <?php if ($noticias && $noticias->num_rows > 0): ?>
                <form method="post" id="newsDeleteForm">
                    <input type="hidden" name="mode" id="deleteModeInput" value="single">
                    <input type="hidden" name="delete_id" id="singleDeleteId" value="">

                    <div class="container-fluid">
                        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3" id="newsList">
                            <?php while ($n = $noticias->fetch_assoc()): ?>
                                <?php
                                    $dataPubRaw  = $n['data_publicacao'];
                                    $dataPubText = $dataPubRaw ? date('d/m/Y', strtotime($dataPubRaw)) : 'Sem data';
                                    $dataPubAttr = $dataPubRaw ? date('Y-m-d', strtotime($dataPubRaw)) : '';

                                    $criadoRaw = $n['criado_em'] ?? '';
                                    if ($criadoRaw !== '' && $criadoRaw !== null) {
                                        $ts = strtotime($criadoRaw);
                                        $criadoText = $ts ? date('Y-m-d H:i', $ts) : $criadoRaw;
                                    } else {
                                        $criadoText = 'Sem data';
                                    }

                                    $imgsLista   = [];
                                    $imgsDetalhe = [];
                                    $imgs        = [];

                                    if (!empty($n['imagem_lista'])) {
                                        $listaParts = explode('|', $n['imagem_lista']);
                                        foreach ($listaParts as $p) {
                                            $p = trim($p);
                                            if ($p !== '') {
                                                $imgsLista[] = '/PAP/' . $p;
                                            }
                                        }
                                    }

                                    if (!empty($n['imagem_detalhe'])) {
                                        $detParts = explode('|', $n['imagem_detalhe']);
                                        foreach ($detParts as $p) {
                                            $p = trim($p);
                                            if ($p !== '') {
                                                $imgsDetalhe[] = '/PAP/' . $p;
                                            }
                                        }
                                    }

                                    $imgs           = array_merge($imgsLista, $imgsDetalhe);
                                    $imgsJson       = htmlspecialchars(json_encode($imgs), ENT_QUOTES, 'UTF-8');
                                    $hasLista       = count($imgsLista) > 0;
                                    $hasDetalhe     = count($imgsDetalhe) > 0;
                                    $startDetalheIx = $hasDetalhe ? count($imgsLista) : 0;

                                    $categoriaRaw = strtolower(trim($n['categoria'] ?? ''));

                                    $tipoTexto = 'Sem tipo';
                                    if ($categoriaRaw === 'plataforma') {
                                        $tipoTexto = 'Plataforma (espaços verdes)';
                                    } elseif ($categoriaRaw === 'estrada') {
                                        $tipoTexto = 'Estrada';
                                    } elseif ($categoriaRaw === 'outros') {
                                        $tipoTexto = 'Outros';
                                    }
                                ?>
                                <div
                                    class="col news-card-container"
                                    data-titulo="<?= htmlspecialchars(strtolower($n['titulo'])) ?>"
                                    data-autor="<?= htmlspecialchars(strtolower($n['autor'] ?? '')) ?>"
                                    data-categoria="<?= htmlspecialchars($categoriaRaw) ?>"
                                    data-data-publicacao="<?= htmlspecialchars($dataPubAttr) ?>"
                                >
                                    <div class="news-card">
                                        <div class="news-card-body">
                                            <div class="news-title news-search-titulo">
                                                <?= htmlspecialchars($n['titulo']) ?>
                                            </div>

                                            <div class="news-line">
                                                <span class="news-label">Autor:</span>
                                                <span class="news-value news-search-autor">
                                                    <?= $n['autor'] ? htmlspecialchars($n['autor']) : 'Sem autor' ?>
                                                </span>
                                            </div>

                                            <div class="news-resumo">
                                                <span class="news-label d-block mb-1">Resumo:</span>
                                                <span class="news-value">
                                                    <?= htmlspecialchars(mb_strimwidth($n['resumo'], 0, 160, '...')) ?>
                                                </span>
                                            </div>

                                            <div class="news-line">
                                                <span class="news-label">Tipo de notícia:</span>
                                                <span class="news-value">
                                                    <?= htmlspecialchars($tipoTexto) ?>
                                                </span>
                                            </div>

                                            <?php if (!empty($imgs)): ?>
                                                <div class="news-line news-image-line">
                                                    <span class="news-label">Imagens:</span>

                                                    <?php if ($hasLista): ?>
                                                        <button
                                                            type="button"
                                                            class="btn btn-outline-secondary news-image-btn d-inline-flex align-items-center js-open-gallery"
                                                            data-images='<?= $imgsJson ?>'
                                                            data-start-index="0"
                                                        >
                                                            <i class="bi bi-card-image me-1"></i>
                                                            <span>Ver imagem da lista</span>
                                                        </button>
                                                    <?php endif; ?>

                                                    <?php if ($hasDetalhe): ?>
                                                        <button
                                                            type="button"
                                                            class="btn btn-outline-secondary news-image-btn d-inline-flex align-items-center js-open-gallery"
                                                            data-images='<?= $imgsJson ?>'
                                                            data-start-index="<?= (int)$startDetalheIx ?>"
                                                        >
                                                            <i class="bi bi-card-image me-1"></i>
                                                            <span>Ver imagem de detalhe</span>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>

                                            <div class="news-line">
                                                <span class="news-label">Data de publicação:</span>
                                                <span class="news-value news-date">
                                                    <?= $dataPubText ?>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="news-card-footer">
                                            <span>
                                                Criado em <?= htmlspecialchars($criadoText) ?>
                                            </span>
                                            <div class="d-flex align-items-center gap-2">
                                                <button
                                                    type="button"
                                                    class="btn btn-danger btn-sm single-delete-btn"
                                                    data-id="<?= (int)$n['id'] ?>"
                                                >
                                                    Remover
                                                </button>
                                                <div class="form-check mb-0 multi-checkbox-wrapper" style="display:none;">
                                                    <input
                                                        class="form-check-input news-checkbox"
                                                        type="checkbox"
                                                        name="delete_ids[]"
                                                        value="<?= (int)$n['id'] ?>"
                                                        id="newsChk<?= (int)$n['id'] ?>"
                                                    >
                                                    <label class="form-check-label" for="newsChk<?= (int)$n['id'] ?>">
                                                        Selecionar
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>

                    <div class="mt-3 justify-content-end multi-actions" id="multiActions">
                        <button
                            type="submit"
                            class="btn btn-danger"
                            onclick="return confirm('Tem a certeza que pretende remover as notícias selecionadas?');"
                        >
                            Remover notícias selecionadas
                        </button>
                    </div>
                </form>

                <!-- PAGINAÇÃO CLIENT-SIDE -->
                <nav id="paginationNav" aria-label="Paginação de notícias" class="mt-3 mb-1">
                    <ul class="pagination justify-content-center flex-wrap" id="paginationLinks"></ul>
                </nav>
            <?php else: ?>
                <div class="alert alert-info text-center mb-0">
                    Nenhuma notícia registada.
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
</div>
</div>
</div>

<div id="imgOverlay" class="img-overlay" aria-hidden="true">
    <div class="img-overlay-inner">
        <button type="button" class="img-nav-btn img-nav-btn-left" id="imgPrevBtn">
            <i class="bi bi-chevron-left"></i>
        </button>
        <img id="imgOverlayImg" src="" alt="Imagem">
        <button type="button" class="img-nav-btn img-nav-btn-right" id="imgNextBtn">
            <i class="bi bi-chevron-right"></i>
        </button>
    </div>
</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js"></script>

<script>
// PAINEL DE FILTROS
const openFilterPanelBtn  = document.getElementById('openFilterPanel');
const filterPanel         = document.getElementById('filterPanel');
const closeFilterPanelBtn = document.getElementById('closeFilterPanel');
const clearFiltersBtn     = document.getElementById('clearFilters');
const filterForm          = document.getElementById('newsFilterForm');

openFilterPanelBtn.addEventListener('click', () => {
    filterPanel.classList.toggle('d-none');
});

closeFilterPanelBtn.addEventListener('click', () => {
    filterPanel.classList.add('d-none');
});

clearFiltersBtn.addEventListener('click', () => {
    const tituloSel = document.getElementById('filterTitulo').tomselect;
    if (tituloSel) tituloSel.clear();

    const autorSel = document.getElementById('filterAutor').tomselect;
    if (autorSel) autorSel.clear();

    const categoriaSel = document.getElementById('filterCategoria').tomselect;
    if (categoriaSel) categoriaSel.clear();

    document.getElementById('filterData').value = '';

    applyNewsFilters();
});

filterForm.addEventListener('submit', (e) => {
    e.preventDefault();
    applyNewsFilters();
    filterPanel.classList.add('d-none');
});

document.addEventListener('click', (e) => {
    if (!filterPanel.classList.contains('d-none')) {
        if (!filterPanel.contains(e.target) && !openFilterPanelBtn.contains(e.target)) {
            filterPanel.classList.add('d-none');
        }
    }
});

// Tom Select
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-nice-select').forEach(function (el) {
        new TomSelect(el, {
            maxItems: 1,
            allowEmptyOption: true,
            create: false,
            plugins: {
                clear_button: {
                    title: 'Limpar seleção'
                }
            }
        });
    });
});

// CLIENT-SIDE FILTER + PAGINAÇÃO
const perPage         = <?= (int)$per_page ?>;
const cards           = Array.from(document.querySelectorAll('.news-card-container'));
const paginationNav   = document.getElementById('paginationNav');
const paginationLinks = document.getElementById('paginationLinks');

const filterTituloEl    = document.getElementById('filterTitulo');
const filterAutorEl     = document.getElementById('filterAutor');
const filterCategoriaEl = document.getElementById('filterCategoria');
const filterDataEl      = document.getElementById('filterData');

function getVisibleCards() {
    return cards.filter(card => card.style.display !== 'none');
}

function renderPagination(totalPages, currentPage) {
    if (!paginationNav || !paginationLinks) return;

    if (totalPages <= 1) {
        paginationNav.style.display = 'none';
        paginationLinks.innerHTML = '';
        return;
    }

    paginationNav.style.display = '';
    let html = '';

    html += `
        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <a href="#" class="page-link" data-page="${currentPage - 1}">« Anterior</a>
        </li>
    `;

    for (let p = 1; p <= totalPages; p++) {
        html += `
            <li class="page-item ${p === currentPage ? 'active' : ''}">
                <a href="#" class="page-link" data-page="${p}">${p}</a>
            </li>
        `;
    }

    html += `
        <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
            <a href="#" class="page-link" data-page="${currentPage + 1}">Próxima »</a>
        </li>
    `;

    paginationLinks.innerHTML = html;
}

function showPage(page) {
    const visibleCards = getVisibleCards();
    const totalVisible = visibleCards.length;
    const totalPages   = Math.ceil(totalVisible / perPage) || 1;

    if (page < 1) page = 1;
    if (page > totalPages) page = totalPages;

    const start = (page - 1) * perPage;
    const end   = start + perPage;

    visibleCards.forEach((card, index) => {
        if (index >= start && index < end) {
            card.style.visibility = '';
            card.style.position   = '';
        } else {
            card.style.visibility = 'hidden';
            card.style.position   = 'absolute';
        }
    });

    renderPagination(totalPages, page);
}

function applyNewsFilters() {
    const titulo    = filterTituloEl.value.trim().toLowerCase();
    const autor     = filterAutorEl.value.trim().toLowerCase();
    const categoria = filterCategoriaEl.value.trim().toLowerCase();
    const data      = filterDataEl.value;

    cards.forEach((card) => {
        const cardTitulo    = (card.getAttribute('data-titulo') || '').trim().toLowerCase();
        const cardAutor     = (card.getAttribute('data-autor') || '').trim().toLowerCase();
        const cardCategoria = (card.getAttribute('data-categoria') || '').trim().toLowerCase();
        const cardData      = (card.getAttribute('data-data-publicacao') || '').trim();

        const matchesTitulo    = !titulo || cardTitulo === titulo;
        const matchesAutor     = !autor || cardAutor === autor;
        const matchesCategoria = !categoria || cardCategoria === categoria;
        const matchesData      = !data || cardData === data;

        if (matchesTitulo && matchesAutor && matchesCategoria && matchesData) {
            card.style.display    = '';
            card.style.visibility = '';
            card.style.position   = '';
        } else {
            card.style.display    = 'none';
            card.style.visibility = '';
            card.style.position   = '';
        }
    });

    showPage(1);
}

if (paginationLinks) {
    paginationLinks.addEventListener('click', function(e) {
        const link = e.target.closest('.page-link');
        if (!link) return;
        e.preventDefault();

        const li = link.parentElement;
        if (li.classList.contains('disabled')) return;

        const page = parseInt(link.dataset.page, 10);
        if (!isNaN(page)) {
            showPage(page);
        }
    });
}

if (cards.length > 0) {
    applyNewsFilters();
} else {
    if (paginationNav) {
        paginationNav.style.display = 'none';
    }
}

// MODO SELEÇÃO + DELETE
const toggleSelectModeBtn = document.getElementById('toggleSelectMode');
const deleteModeInput     = document.getElementById('deleteModeInput');
const singleDeleteIdInput = document.getElementById('singleDeleteId');
const multiActions        = document.getElementById('multiActions');

let multiMode = false;

toggleSelectModeBtn.addEventListener('click', () => {
    multiMode = !multiMode;
    const checkboxes = document.querySelectorAll('.multi-checkbox-wrapper');
    const singleBtns = document.querySelectorAll('.single-delete-btn');

    if (multiMode) {
        deleteModeInput.value = 'multi';
        multiActions.classList.add('show');
        toggleSelectModeBtn.classList.add('btn-primary');
        toggleSelectModeBtn.classList.remove('btn-outline-secondary');
        checkboxes.forEach(cb => cb.style.display = 'block');
        singleBtns.forEach(btn => btn.style.display = 'none');
    } else {
        deleteModeInput.value = 'single';
        multiActions.classList.remove('show');
        toggleSelectModeBtn.classList.remove('btn-primary');
        toggleSelectModeBtn.classList.add('btn-outline-secondary');
        checkboxes.forEach(cb => cb.style.display = 'none');
        singleBtns.forEach(btn => btn.style.display = 'inline-block');
        document.querySelectorAll('.news-checkbox').forEach(chk => chk.checked = false);
    }
});

// single delete
document.querySelectorAll('.single-delete-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        if (!id) return;
        if (!confirm('Tem a certeza que pretende remover esta notícia?')) return;
        deleteModeInput.value = 'single';
        singleDeleteIdInput.value = id;
        document.getElementById('newsDeleteForm').submit();
    });
});

// lightbox
const imgOverlay    = document.getElementById('imgOverlay');
const imgOverlayImg = document.getElementById('imgOverlayImg');
const imgPrevBtn    = document.getElementById('imgPrevBtn');
const imgNextBtn    = document.getElementById('imgNextBtn');

let currentImages = [];
let currentIndex  = 0;

function openGallery(images, startIndex) {
    currentImages = images;
    currentIndex  = startIndex || 0;
    if (!currentImages.length) return;
    imgOverlayImg.src = currentImages[currentIndex];
    imgOverlay.classList.add('show');
    imgOverlay.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}

function closeGallery() {
    imgOverlay.classList.remove('show');
    imgOverlayImg.src = '';
    currentImages = [];
    currentIndex  = 0;
    imgOverlay.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

function showNext(delta) {
    if (!currentImages.length) return;
    currentIndex = (currentIndex + delta + currentImages.length) % currentImages.length;
    imgOverlayImg.src = currentImages[currentIndex];
}

document.querySelectorAll('.js-open-gallery').forEach((btn) => {
    btn.addEventListener('click', function () {
        const imagesJson = this.getAttribute('data-images');
        let images = [];
        try {
            images = JSON.parse(imagesJson);
        } catch (e) {
            images = [];
        }
        const startIndex = parseInt(this.getAttribute('data-start-index') || '0', 10) || 0;
        openGallery(images, startIndex);
    });
});

imgPrevBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    showNext(-1);
});

imgNextBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    showNext(1);
});

imgOverlay.addEventListener('click', (e) => {
    if (e.target === imgOverlay) {
        closeGallery();
    }
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && imgOverlay.classList.contains('show')) {
        closeGallery();
    }
});

// fechar painel ao abrir menu mobile
const burgerBtn = document.querySelector('.burger-btn');
if (burgerBtn && filterPanel) {
    burgerBtn.addEventListener('click', () => {
        filterPanel.classList.add('d-none');
    });
}
</script>
</body>
</html>
