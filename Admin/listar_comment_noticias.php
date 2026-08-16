<?php
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

/* ========= CARREGAR TODOS OS COMENTÁRIOS (SEM LIMIT/OFFSET) ========= */
$sql = "
    SELECT 
        c.id,
        c.noticia_id,
        c.nome          AS nome_comentador,
        c.texto,
        c.criado_em,
        n.titulo        AS titulo_noticia,
        n.autor         AS autor_noticia
    FROM comentarios_noticias c
    JOIN noticias n ON n.id = c.noticia_id
    ORDER BY c.criado_em DESC
";
$comentarios = $conn->query($sql);

/* Valores únicos para filtros (título, autor, comentador) */
$titulos_result = $conn->query("
    SELECT DISTINCT n.titulo
    FROM comentarios_noticias c
    JOIN noticias n ON n.id = c.noticia_id
    WHERE n.titulo IS NOT NULL AND n.titulo <> ''
    ORDER BY n.titulo ASC
");

$autores_result = $conn->query("
    SELECT DISTINCT n.autor
    FROM comentarios_noticias c
    JOIN noticias n ON n.id = c.noticia_id
    WHERE n.autor IS NOT NULL AND n.autor <> ''
    ORDER BY n.autor ASC
");

$nomes_result = $conn->query("
    SELECT DISTINCT c.nome
    FROM comentarios_noticias c
    WHERE c.nome IS NOT NULL AND c.nome <> ''
    ORDER BY c.nome ASC
");

/* Paginação visual client-side */
$per_page          = 6;
$total_comments    = $comentarios ? $comentarios->num_rows : 0;
$total_pages_vis   = $per_page > 0 ? (int)ceil($total_comments / $per_page) : 1;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Comentários das notícias</title>
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

        .page-heading h3 {
            font-weight: 800;
        }

        .page-heading p {
            color: #6b7280;
        }

        #openFilterPanel {
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
            z-index: 1001;
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

        .filter-panel-actions {
            padding-top: 8px;
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
        }

        /* MOBILE FILTER PANEL + HEADER STACK */
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

        /* MESMO MENU MOBILE DAS OUTRAS PÁGINAS */
        @media (max-width: 992px) {
            #main {
                margin-left: 0 !important;
            }
            .sidebar-wrapper {
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                z-index: 1050;
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
            .page-heading h3 {
                font-size: 1.4rem;
            }
            .page-heading p {
                font-size: 0.9rem;
            }
        }

        .comment-card-container {
            display: flex;
            margin-bottom: 16px;
        }

        .comment-card {
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

        .comment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12);
        }

        .comment-card-body {
            padding: 0.9rem 1.1rem 0.6rem 1.1rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .comment-card-footer {
            background-color: #f9fafb;
            padding: 0.45rem 1.1rem 0.55rem 1.1rem;
            font-size: 0.78rem;
            color: #6b7280;
            text-align: right;
            border-top: 1px solid #e5e7eb;
            flex-shrink: 0;
        }

        .comment-news-title {
            font-weight: 800;
            font-size: 1rem;
            margin-bottom: 0.3rem;
            color: #111827;
        }

        .comment-label {
            font-weight: 600;
            font-size: 13px;
            color: #6b7280;
        }

        .comment-value {
            font-size: 14px;
            color: #111827;
        }

        .comment-line {
            margin-bottom: 0.15rem;
        }

        .comment-text {
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
            font-size: 0.86rem;
            color: #4b5563;
            margin-top: 0.15rem;
        }

        .section-subtitle {
            font-size: 0.85rem;
            color: #9ca3af;
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
            <h3 class="mb-1">Comentários das notícias</h3>
            <p class="text-subtitle text-muted mb-0">
                Veja e filtre todos os comentários registados nas notícias públicas.
            </p>
        </div>

        <div class="page-content">
            <section class="section">
                <div class="card card-main position-relative">
                    <div class="card-header">
                        <div class="card-header-flex">
                            <div>
                                <h4 class="mb-1">Comentários registados</h4>
                                <span class="section-subtitle">
                                    Use os filtros para encontrar rapidamente comentários por título, autor, nome ou data.
                                </span>
                            </div>

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

                    <!-- CAIXA DE FILTROS -->
                    <div id="filterPanel" class="filter-panel d-none">
                        <form id="commentFilterForm">
                            <div class="mb-2">
                                <label for="filterTitulo" class="form-label">Título da notícia</label>
                                <select
                                    id="filterTitulo"
                                    class="form-select js-nice-select"
                                >
                                    <option value="">Todos</option>
                                    <?php if ($titulos_result && $titulos_result->num_rows > 0): ?>
                                        <?php while ($row = $titulos_result->fetch_assoc()): ?>
                                            <?php if (!empty($row['titulo'])): ?>
                                                <option value="<?= htmlspecialchars(strtolower($row['titulo'])) ?>">
                                                    <?= htmlspecialchars($row['titulo']) ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="mb-2">
                                <label for="filterAutor" class="form-label">Autor da notícia</label>
                                <select
                                    id="filterAutor"
                                    class="form-select js-nice-select"
                                >
                                    <option value="">Todos</option>
                                    <?php if ($autores_result && $autores_result->num_rows > 0): ?>
                                        <?php while ($row = $autores_result->fetch_assoc()): ?>
                                            <?php if (!empty($row['autor'])): ?>
                                                <option value="<?= htmlspecialchars(strtolower($row['autor'])) ?>">
                                                    <?= htmlspecialchars($row['autor']) ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="mb-2">
                                <label for="filterNome" class="form-label">Comentador</label>
                                <select
                                    id="filterNome"
                                    class="form-select js-nice-select"
                                >
                                    <option value="">Todos</option>
                                    <?php if ($nomes_result && $nomes_result->num_rows > 0): ?>
                                        <?php while ($row = $nomes_result->fetch_assoc()): ?>
                                            <?php if (!empty($row['nome'])): ?>
                                                <option value="<?= htmlspecialchars(strtolower($row['nome'])) ?>">
                                                    <?= htmlspecialchars($row['nome']) ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="mb-2">
                                <label for="filterData" class="form-label">Data do comentário</label>
                                <input
                                    type="date"
                                    class="form-control"
                                    id="filterData"
                                >
                            </div>

                            <div class="filter-panel-actions">
                                <button
                                    type="button"
                                    class="btn btn-light btn-sm"
                                    id="clearFilters"
                                >
                                    Limpar
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-outline-secondary btn-sm"
                                    id="closeFilterPanel"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    class="btn btn-primary btn-sm"
                                >
                                    Aplicar
                                </button>
                            </div>
                        </form>
                    </div>
                    <!-- FIM CAIXA DE FILTROS -->

                    <div class="card-body">
                        <?php if ($comentarios && $comentarios->num_rows > 0): ?>
                            <div class="container-fluid">
                                <div class="row" id="commentList">
                                    <?php while ($c = $comentarios->fetch_assoc()): ?>
                                        <?php
                                            $dataRaw  = $c['criado_em'];
                                            $dataText = $dataRaw ? date('d/m/Y H:i', strtotime($dataRaw)) : 'Sem data';
                                            $dataAttr = $dataRaw ? date('Y-m-d', strtotime($dataRaw)) : '';
                                            $textoShort = mb_strimwidth($c['texto'], 0, 220, '...');
                                            $autorNoticia = $c['autor_noticia'] ?? '';
                                        ?>
                                        <div
                                            class="col-12 col-md-6 col-xl-4 comment-card-container"
                                        >
                                            <div class="comment-card">
                                                <div class="comment-card-body">
                                                    <div class="comment-news-title comment-titulo">
                                                        <?= htmlspecialchars($c['titulo_noticia'] ?? 'Sem título') ?>
                                                    </div>

                                                    <div class="comment-line">
                                                        <span class="comment-label">Autor da notícia:</span>
                                                        <span class="comment-value comment-autor-noticia">
                                                            <?= $autorNoticia ? htmlspecialchars($autorNoticia) : 'Sem autor' ?>
                                                        </span>
                                                    </div>

                                                    <div class="comment-line">
                                                        <span class="comment-label">Comentador:</span>
                                                        <span class="comment-value comment-nome">
                                                            <?= htmlspecialchars($c['nome_comentador'] ?? 'Anónimo') ?>
                                                        </span>
                                                    </div>

                                                    <div class="comment-text">
                                                        <span class="comment-label d-block mb-1">Comentário:</span>
                                                        <span class="comment-value">
                                                            <?= nl2br(htmlspecialchars($textoShort)) ?>
                                                        </span>
                                                    </div>
                                                </div>

                                                <div class="comment-card-footer">
                                                    <span class="comment-data">
                                                        Data do comentário: <?= htmlspecialchars($dataText) ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>

                            <nav id="paginationNav" aria-label="Paginação de comentários" class="mt-3 mb-1">
                                <ul class="pagination justify-content-center flex-wrap" id="paginationLinks"></ul>
                            </nav>

                        <?php else: ?>
                            <div class="alert alert-info text-center mb-0">
                                Ainda não existem comentários registados.
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </section>
        </div>
    </div>
</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js"></script>

<script>
const openFilterPanelBtn  = document.getElementById('openFilterPanel');
const filterPanel         = document.getElementById('filterPanel');
const closeFilterPanelBtn = document.getElementById('closeFilterPanel');
const clearFiltersBtn     = document.getElementById('clearFilters');
const filterForm          = document.getElementById('commentFilterForm');

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

        const tituloSelect = document.getElementById('filterTitulo').tomselect;
        if (tituloSelect) tituloSelect.clear();
        const autorSelect  = document.getElementById('filterAutor').tomselect;
        if (autorSelect) autorSelect.clear();
        const nomeSelect   = document.getElementById('filterNome').tomselect;
        if (nomeSelect) nomeSelect.clear();

        applyCommentFilters();
    });
}

// CLIENT-SIDE FILTER + PAGINAÇÃO (igual estilo notícias/árvores)
const perPage          = <?= (int)$per_page ?>;
const commentCards     = Array.from(document.querySelectorAll('.comment-card-container'));
const paginationNav    = document.getElementById('paginationNav');
const paginationLinks  = document.getElementById('paginationLinks');

const filterTituloEl   = document.getElementById('filterTitulo');
const filterAutorEl    = document.getElementById('filterAutor');
const filterNomeEl     = document.getElementById('filterNome');
const filterDataEl     = document.getElementById('filterData');

function getVisibleCommentCards() {
    return commentCards.filter(card => card.style.display !== 'none');
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

function showCommentPage(page) {
    const visibleCards = getVisibleCommentCards();
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

function applyCommentFilters() {
    const tituloSel = (filterTituloEl.value || '').trim().toLowerCase();
    const autorSel  = (filterAutorEl.value  || '').trim().toLowerCase();
    const nomeSel   = (filterNomeEl.value   || '').trim().toLowerCase();
    const dataSel   = (filterDataEl.value   || '').trim(); // yyyy-mm-dd

    commentCards.forEach(card => {
        const textTitulo = (card.querySelector('.comment-titulo')?.textContent || '')
            .trim().toLowerCase();
        const textAutor  = (card.querySelector('.comment-autor-noticia')?.textContent || '')
            .trim().toLowerCase();
        const textNome   = (card.querySelector('.comment-nome')?.textContent || '')
            .trim().toLowerCase();
        const textData   = (card.querySelector('.comment-data')?.textContent || '')
            .trim(); // "Data do comentário: dd/mm/yyyy HH:ii"

        let dataCard = '';
        const matchDate = textData.match(/(\d{2})\/(\d{2})\/(\d{4})/);
        if (matchDate) {
            const d = matchDate[1];
            const m = matchDate[2];
            const y = matchDate[3];
            dataCard = `${y}-${m}-${d}`;
        }

        const matchesTitulo = !tituloSel || textTitulo === tituloSel;
        const matchesAutor  = !autorSel  || textAutor  === autorSel;
        const matchesNome   = !nomeSel   || textNome   === nomeSel;
        const matchesData   = !dataSel   || dataCard   === dataSel;

        if (matchesTitulo && matchesAutor && matchesNome && matchesData) {
            card.style.display    = '';
            card.style.visibility = '';
            card.style.position   = '';
        } else {
            card.style.display    = 'none';
            card.style.visibility = '';
            card.style.position   = '';
        }
    });

    showCommentPage(1);
}

if (filterForm) {
    filterForm.addEventListener('submit', (e) => {
        e.preventDefault();
        applyCommentFilters();
        filterPanel.classList.add('d-none');
    });
}

if (paginationLinks) {
    paginationLinks.addEventListener('click', (e) => {
        const link = e.target.closest('.page-link');
        if (!link) return;
        e.preventDefault();

        const li = link.parentElement;
        if (li.classList.contains('disabled')) return;

        const page = parseInt(link.dataset.page, 10);
        if (!isNaN(page)) {
            showCommentPage(page);
        }
    });
}

if (commentCards.length > 0) {
    applyCommentFilters();
} else if (paginationNav) {
    paginationNav.style.display = 'none';
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

// MENU MOBILE: igual às outras páginas
const burgerBtn      = document.querySelector('.burger-btn');
const sidebarWrapper = document.querySelector('.sidebar-wrapper');

if (burgerBtn && sidebarWrapper) {
    burgerBtn.addEventListener('click', (e) => {
        e.preventDefault();
        sidebarWrapper.classList.toggle('active'); // usa a mesma classe do resto do projeto
        filterPanel.classList.add('d-none');
    });
}
</script>
</body>
</html>
