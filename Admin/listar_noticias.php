<?php
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

/* ========= CARREGAR TODAS AS NOTÍCIAS (SEM LIMIT) ========= */
$sqlNoticias = "
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
";
$noticias = $conn->query($sqlNoticias);

/* ========= VALORES ÚNICOS PARA FILTROS ========= */
$titulos_result = $conn->query("
    SELECT DISTINCT titulo
    FROM noticias
    WHERE titulo IS NOT NULL AND titulo <> ''
    ORDER BY titulo ASC
");

$autores_result = $conn->query("
    SELECT DISTINCT autor
    FROM noticias
    WHERE autor IS NOT NULL AND autor <> ''
    ORDER BY autor ASC
");

/* ========= PAGINAÇÃO CLIENT-SIDE (VISUAL) ========= */
$per_page        = 6;
$total_news      = $noticias ? $noticias->num_rows : 0;
$total_pages_vis = $per_page > 0 ? (int)ceil($total_news / $per_page) : 1;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Lista de Notícias</title>
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
        body,
        .sidebar,
        .card,
        .btn,
        h4, h3, h2 {
            font-family: 'Nunito', sans-serif !important;
        }
        .page-content {
            background-color: #f3f4f6;
        }
        .card-main {
            border-radius: 18px;
            border: 0;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
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
        #openFilterPanel,
        #toggleEmailSelectMode,
        #sendNewsletterBtn {
            border-radius: 999px;
            padding: 0.4rem 1rem;
            font-size: 0.9rem;
        }
        .header-pill-btn i {
            font-size: 1rem;
        }
        .filter-panel {
            position: absolute;
            top: 70px;
            right: 24px;
            width: 360px;
            background: #ffffff;
            padding: 22px 18px 16px 18px;
            border-radius: 16px;
            z-index: 2000;
            border: 1px solid #e5e7eb;
            box-shadow: 0 10px 32px rgba(15, 23, 42, 0.16);
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

        /* MOBILE FILTER + HEADER + SIDEBAR (igual árvores) */
        @media (max-width: 768px) {
            .filter-panel {
                position: fixed;
                top: 88px;
                right: 12px;
                left: auto;
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
            .pagination {
                flex-wrap: wrap;
            }
        }

        /* MESMO COMPORTAMENTO DE MENU QUE NAS ÁRVORES */
        @media (max-width: 992px) {
            #main {
                margin-left: 0 !important;
            }
            .sidebar-wrapper {
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                z-index: 1500;
            }
            .page-content {
                position: relative;
                z-index: 1;
            }
            #app {
                overflow-x: hidden;
            }
            .card-main .card-body {
                padding: 1rem 0.9rem 1.1rem 0.9rem;
            }
        }

        .news-card-container {
            display: flex;
            margin-bottom: 16px;
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
            margin-bottom: 0.3rem;
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
            margin-bottom: 0.15rem;
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
    <h3 class="mb-1">Notícias registadas</h3>
    <p class="text-subtitle text-muted mb-0">
        Veja e filtre todas as notícias registadas no sistema.
    </p>
</div>

<div class="page-content">
<section class="section">
    <div class="card card-main position-relative">
        <div class="card-header">
            <div class="card-header-flex">
                <div>
                    <h4 class="mb-1">Notícias registadas</h4>
                    <span class="section-subtitle">
                        Use os filtros ou selecione uma notícia para envio da newsletter.
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <button
                        id="toggleEmailSelectMode"
                        class="btn btn-outline-secondary d-flex align-items-center header-pill-btn"
                        type="button"
                    >
                        <i class="bi bi-check2-square me-1"></i>
                        <span>Modo seleção</span>
                    </button>
                    <button
                        id="sendNewsletterBtn"
                        class="btn btn-outline-secondary d-flex align-items-center header-pill-btn"
                        type="button"
                        style="display:none;"
                    >
                        <span>Enviar newsletter selecionada</span>
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

        <!-- Painel de filtros -->
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

        <div class="card-body">
            <?php if ($noticias && $noticias->num_rows > 0): ?>
                <form id="newsEmailForm">
                    <input type="hidden" id="singleEmailId" value="">

                    <div class="container-fluid">
                        <div class="row" id="newsList">
                            <?php while ($n = $noticias->fetch_assoc()): ?>
                                <?php
                                    $dataPubRaw  = $n['data_publicacao'];
                                    $dataPubText = $dataPubRaw ? date('d/m/Y', strtotime($dataPubRaw)) : 'Sem data';

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
                                    class="col-12 col-md-6 col-xl-4 news-card-container"
                                    data-categoria="<?= htmlspecialchars($categoriaRaw) ?>"
                                >
                                    <div class="news-card">
                                        <div class="news-card-body">

                                            <div class="news-title news-titulo">
                                                <?= htmlspecialchars($n['titulo']) ?>
                                            </div>

                                            <div class="news-line">
                                                <span class="news-label">Autor:</span>
                                                <span class="news-value news-autor">
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
                                            <div class="form-check mb-0 email-checkbox-wrapper" style="display:none;">
                                                <input
                                                    class="form-check-input news-email-checkbox"
                                                    type="checkbox"
                                                    value="<?= (int)$n['id'] ?>"
                                                    id="newsEmail<?= (int)$n['id'] ?>"
                                                >
                                                <label class="form-check-label" for="newsEmail<?= (int)$n['id'] ?>">
                                                    Selecionar
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </form>

                <!-- Paginação client-side -->
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
// abrir/fechar painel filtro
const openFilterPanelBtn  = document.getElementById('openFilterPanel');
const filterPanel         = document.getElementById('filterPanel');
const closeFilterPanelBtn = document.getElementById('closeFilterPanel');
const clearFiltersBtn     = document.getElementById('clearFilters');
const filterForm          = document.getElementById('newsFilterForm');

if (openFilterPanelBtn) {
    openFilterPanelBtn.addEventListener('click', () => {
        filterPanel.classList.toggle('d-none');
    });
}

if (closeFilterPanelBtn) {
    closeFilterPanelBtn.addEventListener('click', () => {
        filterPanel.classList.add('d-none');
    });
}

if (clearFiltersBtn) {
    clearFiltersBtn.addEventListener('click', () => {
        document.getElementById('filterData').value = '';

        const tituloSel = document.getElementById('filterTitulo').tomselect;
        if (tituloSel) tituloSel.clear();

        const autorSel  = document.getElementById('filterAutor').tomselect;
        if (autorSel) autorSel.clear();

        const categoriaSel = document.getElementById('filterCategoria').tomselect;
        if (categoriaSel) categoriaSel.clear();

        applyNewsFilters();
    });
}

// fechar painel ao clicar fora
document.addEventListener('click', (e) => {
    if (!filterPanel.classList.contains('d-none')) {
        if (!filterPanel.contains(e.target) && !openFilterPanelBtn.contains(e.target)) {
            filterPanel.classList.add('d-none');
        }
    }
});

// Tom Select init
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.js-nice-select').forEach((el) => {
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
const perPage          = <?= (int)$per_page ?>;
const newsCards        = Array.from(document.querySelectorAll('.news-card-container'));
const paginationNav    = document.getElementById('paginationNav');
const paginationLinks  = document.getElementById('paginationLinks');

const filterTituloEl    = document.getElementById('filterTitulo');
const filterAutorEl     = document.getElementById('filterAutor');
const filterCategoriaEl = document.getElementById('filterCategoria');
const filterDataEl      = document.getElementById('filterData');

function getVisibleNewsCards() {
    return newsCards.filter(card => card.style.display !== 'none');
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

function showNewsPage(page) {
    const visibleCards = getVisibleNewsCards();
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

// aplica filtros lendo o texto do cartão (título, autor, tipo, data)
function applyNewsFilters() {
    const tituloSel    = (filterTituloEl.value || '').trim().toLowerCase();
    const autorSel     = (filterAutorEl.value  || '').trim().toLowerCase();
    const categoriaSel = (filterCategoriaEl.value || '').trim().toLowerCase();
    const dataSel      = (filterDataEl.value   || '').trim(); // yyyy-mm-dd

    newsCards.forEach(card => {
        const textTitulo = (card.querySelector('.news-titulo')?.textContent || '')
            .trim().toLowerCase();

        const textAutor = (card.querySelector('.news-autor')?.textContent || '')
            .trim().toLowerCase();

        const categoriaCard = (card.dataset.categoria || '')
            .trim().toLowerCase();

        const textData = (card.querySelector('.news-date')?.textContent || '')
            .trim(); // dd/mm/yyyy ou "Sem data"

        let dataCard = '';
        if (textData && textData !== 'Sem data') {
            const parts = textData.split('/');
            if (parts.length === 3) {
                dataCard = parts[2] + '-' + parts[1] + '-' + parts[0];
            }
        }

        const matchesTitulo    = !tituloSel || textTitulo === tituloSel;
        const matchesAutor     = !autorSel || textAutor === autorSel;
        const matchesCategoria = !categoriaSel || categoriaCard === categoriaSel;
        const matchesData      = !dataSel || dataCard === dataSel;

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

    showNewsPage(1);
}

// submit do painel
if (filterForm) {
    filterForm.addEventListener('submit', (e) => {
        e.preventDefault();
        applyNewsFilters();
        filterPanel.classList.add('d-none');
    });
}

// clicar paginação
if (paginationLinks) {
    paginationLinks.addEventListener('click', (e) => {
        const link = e.target.closest('.page-link');
        if (!link) return;
        e.preventDefault();

        const li = link.parentElement;
        if (li.classList.contains('disabled')) return;

        const page = parseInt(link.dataset.page, 10);
        if (!isNaN(page)) {
            showNewsPage(page);
        }
    });
}

// inicial
if (newsCards.length > 0) {
    applyNewsFilters();
} else if (paginationNav) {
    paginationNav.style.display = 'none';
}

// MODO SELEÇÃO + NEWSLETTER
const toggleEmailSelectModeBtn = document.getElementById('toggleEmailSelectMode');
const sendNewsletterBtn        = document.getElementById('sendNewsletterBtn');
const singleEmailIdInput       = document.getElementById('singleEmailId');

let emailMode = false;

function updateEmailSelectionUI() {
    const emailWrappers = document.querySelectorAll('.email-checkbox-wrapper');

    if (emailMode) {
        toggleEmailSelectModeBtn.classList.add('active');
        sendNewsletterBtn.classList.add('active');

        emailWrappers.forEach(w => w.style.display = 'block');
        sendNewsletterBtn.style.display = 'inline-flex';
    } else {
        toggleEmailSelectModeBtn.classList.remove('active');
        sendNewsletterBtn.classList.remove('active');

        emailWrappers.forEach(w => {
            w.style.display = 'none';
            const cb = w.querySelector('.news-email-checkbox');
            if (cb) cb.checked = false;
        });
        sendNewsletterBtn.style.display = 'none';
        singleEmailIdInput.value = '';
    }
}

toggleEmailSelectModeBtn.addEventListener('click', () => {
    emailMode = !emailMode;
    updateEmailSelectionUI();
});

// só 1 checkbox selecionada
document.querySelectorAll('.news-email-checkbox').forEach(cb => {
    cb.addEventListener('change', () => {
        if (cb.checked) {
            document.querySelectorAll('.news-email-checkbox').forEach(other => {
                if (other !== cb) other.checked = false;
            });
            singleEmailIdInput.value = cb.value;
        } else {
            singleEmailIdInput.value = '';
        }
    });
});

// clicar no botão: cria token one-time e abre send_newsletter.php em nova aba
sendNewsletterBtn.addEventListener('click', () => {
    const id = singleEmailIdInput.value;
    if (!id) {
        alert('Selecione uma notícia para enviar a newsletter.');
        return;
    }

    fetch('create_send_token.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'id=' + encodeURIComponent(id)
    })
    .then(r => r.json())
    .then(data => {
        if (!data || !data.token) {
            alert('Erro ao criar token de envio.');
            return;
        }
        const url = 'send_newsletter.php?token=' + encodeURIComponent(data.token);
        window.open(url, '_blank');
    })
    .catch(() => {
        alert('Erro ao comunicar com o servidor.');
    });
});

// Lightbox
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

// MENU MOBILE: mesmo comportamento que nas outras páginas
const burgerBtn = document.querySelector('.burger-btn');
const sidebarWrapper = document.querySelector('.sidebar-wrapper');

if (burgerBtn && sidebarWrapper) {
    burgerBtn.addEventListener('click', (e) => {
        e.preventDefault();
        sidebarWrapper.classList.toggle('active'); // mesmo class que usas nas outras páginas
        filterPanel.classList.add('d-none');       // fecha filtro quando abre menu
    });
}
</script>
</body>
</html>
