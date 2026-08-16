<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include './log.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$success = '';
$error   = '';
$id      = isset($_GET['id']) ? intval($_GET['id']) : 0;

function categoriaTextoNoticia($categoria) {
    if ($categoria === 'plataforma') {
        return 'Plataforma (espaços verdes)';
    } elseif ($categoria === 'estrada') {
        return 'Estrada';
    } elseif ($categoria === 'outros') {
        return 'Outros';
    }
    return 'Sem tipo';
}

/* =========================
   PARTE 1 – SELECIONAR NOTÍCIA
   ========================= */
if ($id <= 0) {

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

    $autores = [];
    $sqlAutores = "SELECT DISTINCT autor FROM noticias WHERE autor IS NOT NULL AND autor <> '' ORDER BY autor ASC";
    if ($resAut = $conn->query($sqlAutores)) {
        while ($rowA = $resAut->fetch_assoc()) {
            $autores[] = $rowA['autor'];
        }
    }

    $titulos_result = $conn->query("
        SELECT DISTINCT titulo
        FROM noticias
        WHERE titulo IS NOT NULL AND titulo <> ''
        ORDER BY titulo ASC
    ");

    $per_page        = 6;
    $total_news      = $noticias ? $noticias->num_rows : 0;
    $total_pages_vis = $per_page > 0 ? (int)ceil($total_news / $per_page) : 1;
    ?>
    <!DOCTYPE html>
    <html lang="pt">
    <head>
        <meta charset="UTF-8">
        <title>Selecionar Notícia</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
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
                background: linear-gradient(135deg, #eef2ff, #f9fafb);
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
            #openFilterPanel {
                border-radius: 999px;
                padding: 0.4rem 1rem;
                font-size: 0.9rem;
            }

            .filter-panel {
                position: absolute;
                top: 70px;
                right: 24px;
                width: 360px;
                background: #ffffff;
                padding: 22px 18px 16px 18px;
                border-radius: 16px;
                z-index: 1001;
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
                gap: 0.15rem;
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
            }
            .news-title {
                font-weight: 800;
                font-size: 1rem;
                margin-bottom: 0.15rem;
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
                #app {
                    overflow-x: hidden;
                }
                .page-content {
                    position: relative;
                    z-index: 1;
                }
                .card-main .card-body {
                    padding: 1rem 0.9rem 1.1rem 0.9rem;
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

            <div class="page-heading mb-3">
                <h3 class="mb-1">Selecionar Notícia</h3>
                <p class="text-subtitle text-muted">Escolha uma notícia para editar.</p>
            </div>

            <div class="page-content">
                <section class="section">
                    <div class="card card-main position-relative">
                        <div class="card-header border-0 pb-0">
                            <div class="card-header-flex">
                                <div>
                                    <h4 class="mb-1">Notícias disponíveis</h4>
                                    <small class="text-muted">Use o filtro para encontrar mais rápido.</small>
                                </div>
                                <button id="openFilterPanel" class="btn btn-outline-secondary d-flex align-items-center" type="button">
                                    <i class="bi bi-funnel-fill me-1"></i>
                                    <span>Filtrar</span>
                                </button>
                            </div>
                        </div>

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
                                        <?php foreach ($autores as $autorOpt): ?>
                                            <?php if (!empty($autorOpt)): ?>
                                                <option value="<?= htmlspecialchars(strtolower($autorOpt)) ?>">
                                                    <?= htmlspecialchars($autorOpt) ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
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

                        <div class="card-body pt-3">
                            <?php if ($noticias && $noticias->num_rows > 0): ?>
                                <div class="container-fluid">
                                    <div class="row" id="newsList">
                                        <?php while ($n = $noticias->fetch_assoc()): ?>
                                            <?php
                                                $dataPubRaw  = $n['data_publicacao'];
                                                $dataPubText = $dataPubRaw ? date('d/m/Y', strtotime($dataPubRaw)) : 'Sem data';

                                                $criadoRaw = $n['criado_em'] ?? '';
                                                if ($criadoRaw !== '' && $criadoRaw !== null) {
                                                    $tsCriado   = strtotime($criadoRaw);
                                                    $criadoText = $tsCriado ? date('Y-m-d H:i', $tsCriado) : $criadoRaw;
                                                } else {
                                                    $criadoText = 'Sem data';
                                                }

                                                $categoriaRaw = $n['categoria'] ?? '';
                                                $tipoTexto = categoriaTextoNoticia($categoriaRaw);
                                            ?>
                                            <div
                                                class="col-12 col-md-6 col-xl-4 news-card-container"
                                                data-categoria="<?= htmlspecialchars(strtolower($categoriaRaw)) ?>"
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
                                                            <span class="news-value news-categoria-text">
                                                                <?= htmlspecialchars($tipoTexto) ?>
                                                            </span>
                                                        </div>

                                                        <div class="news-line mt-1">
                                                            <span class="news-label">Data de publicação:</span>
                                                            <span class="news-value news-date">
                                                                <?= $dataPubText ?>
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <div class="news-card-footer">
                                                        <span>Criado em <?= htmlspecialchars($criadoText) ?></span>
                                                        <a href="index.php?evora=editarnoticias&id=<?= (int)$n['id'] ?>" class="btn btn-sm btn-primary">
                                                            Editar
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                </div>

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

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js"></script>

    <script>
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

    function applyNewsFilters() {
        const tituloSel    = (filterTituloEl.value    || '').trim().toLowerCase();
        const autorSel     = (filterAutorEl.value     || '').trim().toLowerCase();
        const categoriaSel = (filterCategoriaEl.value || '').trim().toLowerCase();
        const dataSel      = (filterDataEl.value      || '').trim();

        newsCards.forEach(card => {
            const textTitulo = (card.querySelector('.news-titulo')?.textContent || '')
                .trim().toLowerCase();

            const textAutor  = (card.querySelector('.news-autor')?.textContent  || '')
                .trim().toLowerCase();

            const categoriaCard = (card.dataset.categoria || '').trim().toLowerCase();

            const textData = (card.querySelector('.news-date')?.textContent || '').trim();

            let dataCard = '';
            if (textData && textData !== 'Sem data') {
                const parts = textData.split('/');
                if (parts.length === 3) {
                    dataCard = parts[2] + '-' + parts[1] + '-' + parts[0];
                }
            }

            const matchesTitulo    = !tituloSel    || textTitulo === tituloSel;
            const matchesAutor     = !autorSel     || textAutor === autorSel;
            const matchesCategoria = !categoriaSel || categoriaCard === categoriaSel;
            const matchesData      = !dataSel      || dataCard === dataSel;

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

    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', () => {
            filterDataEl.value = '';

            const tituloSel = filterTituloEl.tomselect;
            if (tituloSel) tituloSel.clear();

            const autorSel = filterAutorEl.tomselect;
            if (autorSel) autorSel.clear();

            const categoriaSel = filterCategoriaEl.tomselect;
            if (categoriaSel) categoriaSel.clear();

            applyNewsFilters();
        });
    }

    if (filterForm) {
        filterForm.addEventListener('submit', (e) => {
            e.preventDefault();
            applyNewsFilters();
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
                showNewsPage(page);
            }
        });
    }

    document.addEventListener('click', (e) => {
        if (!filterPanel.classList.contains('d-none')) {
            if (!filterPanel.contains(e.target) && !openFilterPanelBtn.contains(e.target)) {
                filterPanel.classList.add('d-none');
            }
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.js-nice-select').forEach(function (el) {
            new TomSelect(el, {
                maxItems: 1,
                allowEmptyOption: true,
                create: false,
                plugins: {
                    clear_button: { title: 'Limpar seleção' }
                }
            });
        });

        if (newsCards.length > 0) {
            applyNewsFilters();
        } else if (paginationNav) {
            paginationNav.style.display = 'none';
        }

        const burgerBtn      = document.querySelector('.burger-btn');
        const sidebarWrapper = document.querySelector('.sidebar-wrapper');

        if (burgerBtn && sidebarWrapper) {
            burgerBtn.addEventListener('click', (e) => {
                e.preventDefault();
                sidebarWrapper.classList.toggle('active');
                filterPanel.classList.add('d-none');
            });
        }
    });
    </script>
    </body>
    </html>
    <?php
    exit;
}

/* =========================
   PARTE 2 – EDITAR NOTÍCIA
   ========================= */

$stmt = $conn->prepare("
    SELECT id,
           titulo,
           resumo,
           conteudo,
           imagem_lista,
           imagem_detalhe,
           autor,
           categoria,
           data_publicacao
    FROM noticias
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Erro na preparação da query.");
}

$stmt->bind_param('i', $id);
$stmt->execute();
$result  = $stmt->get_result();
$noticia = $result->fetch_assoc();
$stmt->close();

if (!$noticia) {
    ?>
    <!DOCTYPE html>
    <html lang="pt">
    <head>
        <meta charset="UTF-8">
        <title>Notícia não encontrada</title>
        <link rel="stylesheet" href="assets/css/bootstrap.css">
        <link rel="stylesheet" href="assets/css/app.css">
    </head>
    <body>
    <div class="container mt-4">
        <div class="alert alert-danger mt-5 mx-auto text-center" style="max-width:500px">
            Notícia não encontrada.
            <a href="index.php?evora=editarnoticias" class="btn btn-primary btn-sm ms-2">Selecionar notícia</a>
        </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

$uploadDirRel = 'uploads/noticias/';
$uploadDirAbs = dirname(__DIR__) . '/' . $uploadDirRel;

if (!is_dir($uploadDirAbs)) {
    mkdir($uploadDirAbs, 0775, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editnews'])) {
    $titulo    = trim($_POST['titulo'] ?? '');
    $resumo    = trim($_POST['resumo'] ?? '');
    $conteudo  = trim($_POST['conteudo'] ?? '');
    $autor     = trim($_POST['autor'] ?? '');
    $dataPub   = trim($_POST['datapublicacao'] ?? '');
    $categoria = isset($_POST['categoria']) ? trim($_POST['categoria']) : '';

    $categoriasPermitidas = ['plataforma', 'estrada', 'outros'];

    if ($titulo === '') {
        $error = "O título é obrigatório!";
    } elseif ($categoria === '' || !in_array($categoria, $categoriasPermitidas, true)) {
        $error = "Tipo de notícia inválido.";
    } elseif ($dataPub !== '') {
        $dt = DateTime::createFromFormat('Y-m-d', $dataPub);
        if ($dt === false) {
            $error = "Data de publicação inválida. Use o formato AAAA-MM-DD.";
        } else {
            $hoje = new DateTime('today');
            if ($dt > $hoje) {
                $dataPub = $hoje->format('Y-m-d');
            } else {
                $dataPub = $dt->format('Y-m-d');
            }
        }
    }

    $oldLista       = $noticia['imagem_lista'] ?? '';
    $oldDetalhe     = $noticia['imagem_detalhe'] ?? '';
    $newListaPath   = $oldLista;
    $newDetalhePath = $oldDetalhe;

    if (empty($error) && isset($_FILES['imagemlista']) && $_FILES['imagemlista']['error'] === UPLOAD_ERR_OK) {
        if (!empty($oldLista)) {
            $listaPartsOld = explode('|', $oldLista);
            foreach ($listaPartsOld as $imgPath) {
                $imgPath = trim($imgPath);
                if (!$imgPath) continue;

                $full = $uploadDirAbs . basename($imgPath);
                if (file_exists($full)) {
                    unlink($full);
                }
            }
        }

        $ext = strtolower(pathinfo($_FILES['imagemlista']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $error = "Formato de imagem da lista inválido. Use JPG, JPEG, PNG ou WEBP.";
        } else {
            $nomeFile = 'noticia_' . $id . '_lista_' . time() . '.' . $ext;
            $dest     = $uploadDirAbs . $nomeFile;

            if (move_uploaded_file($_FILES['imagemlista']['tmp_name'], $dest)) {
                $newListaPath = $uploadDirRel . $nomeFile;
            } else {
                $error = "Erro ao guardar a nova imagem da lista.";
            }
        }
    }

    if (empty($error) && isset($_FILES['imagemdetalhe']) && !empty($_FILES['imagemdetalhe']['name'][0])) {
        if (!empty($oldDetalhe)) {
            $detPartsOld = explode('|', $oldDetalhe);
            foreach ($detPartsOld as $imgPath) {
                $imgPath = trim($imgPath);
                if (!$imgPath) continue;

                $full = $uploadDirAbs . basename($imgPath);
                if (file_exists($full)) {
                    unlink($full);
                }
            }
        }

        $files = $_FILES['imagemdetalhe'];

        $total = 0;
        foreach ($files['name'] as $idx => $name) {
            if ($files['error'][$idx] === UPLOAD_ERR_OK && $name !== '') {
                $total++;
            }
        }

        if ($total > 3) {
            $error = "Só pode enviar no máximo 3 imagens de detalhe.";
        } else {
            $detalhePaths = [];

            foreach ($files['name'] as $idx => $name) {
                if ($files['error'][$idx] === UPLOAD_ERR_OK && $name !== '') {
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                        $error = "Formato de imagem de detalhe inválido. Use JPG, JPEG, PNG ou WEBP.";
                        break;
                    }

                    $nomeFile = 'noticia_' . $id . '_det_' . $idx . '_' . time() . '.' . $ext;
                    $dest     = $uploadDirAbs . $nomeFile;

                    if (move_uploaded_file($files['tmp_name'][$idx], $dest)) {
                        $detalhePaths[] = $uploadDirRel . $nomeFile;
                    } else {
                        $error = "Erro ao guardar ficheiro de imagem (detalhe).";
                        break;
                    }
                }
            }

            if (empty($error)) {
                $newDetalhePath = !empty($detalhePaths) ? implode('|', $detalhePaths) : '';
            }
        }
    }

    if (empty($error)) {
        $stmtUp = $conn->prepare("
            UPDATE noticias
            SET titulo = ?,
                resumo = ?,
                conteudo = ?,
                imagem_lista = ?,
                imagem_detalhe = ?,
                autor = ?,
                categoria = ?,
                data_publicacao = ?
            WHERE id = ?
        ");

        if ($stmtUp) {
            $stmtUp->bind_param(
                'ssssssssi',
                $titulo,
                $resumo,
                $conteudo,
                $newListaPath,
                $newDetalhePath,
                $autor,
                $categoria,
                $dataPub,
                $id
            );

            if ($stmtUp->execute()) {
                regista_log(
                    $conn,
                    $_SESSION['user_id'],
                    "editar",
                    "noticias",
                    $id,
                    "Notícia editada. Título: " . ($titulo ?: "Sem título") . " (categoria: " . $categoria . ")"
                );

                $userId = (int)$_SESSION['user_id'];
                $acao   = 'Edição de notícia';
                $det    = "Notícia ID {$id} editada. Título: " . ($titulo ?: "Sem título") . " (categoria: " . $categoria . ")";

                if ($stmtAt = $conn->prepare("INSERT INTO atividade (user_id, acao, detalhe) VALUES (?, ?, ?)")) {
                    $stmtAt->bind_param("iss", $userId, $acao, $det);
                    $stmtAt->execute();
                    $stmtAt->close();
                }

                header("Location: index.php?evora=editarnoticias");
                exit();
            } else {
                $error = "Erro ao atualizar a notícia.";
            }

            $stmtUp->close();
        } else {
            $error = "Erro na preparação da query de atualização.";
        }
    }

    $noticia['titulo']          = $titulo;
    $noticia['resumo']          = $resumo;
    $noticia['conteudo']        = $conteudo;
    $noticia['autor']           = $autor;
    $noticia['categoria']       = $categoria;
    $noticia['data_publicacao'] = $dataPub;
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Editar Notícia</title>
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
        html, body {
            height: 100%;
        }
        body {
            background: linear-gradient(135deg, #eef2ff, #f9fafb);
            font-family: 'Nunito', sans-serif !important;
            margin: 0;
        }
        #app, #main {
            min-height: 100%;
        }
        .page-content {
            min-height: calc(100vh - 56px);
            padding: 24px 12px 32px 12px;
        }
        .edit-container {
            width: 100%;
            max-width: 1180px;
            margin: 0 auto;
        }
        .page-heading-custom {
            margin-bottom: 16px;
        }
        .page-heading-custom h3 {
            font-weight: 700;
            font-size: 1.6rem;
            margin-bottom: 4px;
        }
        .page-heading-custom p {
            color: #6b7280;
            font-size: 0.95rem;
            margin-bottom: 0;
        }
        .edit-card {
            background-color: #ffffff;
            border-radius: 16px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.09);
            padding: 20px 20px 18px 20px;
        }
        .section-label {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #9ca3af;
            margin-top: 4px;
            margin-bottom: 6px;
        }
        .field-label {
            font-weight: 600;
            color: #374151;
            font-size: 0.9rem;
        }
        .form-control,
        .form-select {
            border-radius: 10px;
        }
        .edit-two-cols {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 18px;
        }
        .btn-main {
            font-weight: 600;
            letter-spacing: .02em;
        }
        .actions-row-desktop {
            margin-top: 16px;
        }
        .alert {
            border-radius: 12px;
        }
        .upload-hint {
            font-size: 0.8rem;
            color: #6b7280;
        }
        .upload-box {
            border: 1px dashed #cbd5f5;
            border-radius: 10px;
            padding: 0.65rem 0.75rem;
            background-color: #f9fafb;
        }
        .upload-box label {
            margin-bottom: 0.25rem;
        }
        .current-img-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 6px;
        }
        .current-img-list img {
            max-height: 90px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            background-color: #f9fafb;
            object-fit: cover;
        }
        .current-img-list-detail img {
            max-height: 80px;
        }
        .current-img-caption {
            font-size: 0.78rem;
            color: #6b7280;
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
            #app {
                overflow-x: hidden;
            }
        }
        @media (max-width: 768px) {
            .edit-card {
                padding: 16px 14px 16px 14px;
            }
            .edit-two-cols {
                grid-template-columns: 1fr;
            }
            .actions-row-desktop {
                margin-top: 10px;
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

<div class="page-content">
    <div class="edit-container">

        <div class="page-heading-custom">
            <h3>Editar Notícias</h3>
            <p>Atualize o conteúdo, metadados e imagens da notícia selecionada.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger mb-3"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="edit-card">
            <form method="post" enctype="multipart/form-data" autocomplete="off">
                <div class="edit-two-cols mb-3">

                    <div>
                        <div class="section-label">Dados principais</div>

                        <div class="row mb-3">
                            <div class="col-12 col-md-6 mb-3 mb-md-0">
                                <label class="field-label mb-1">Título da Notícia</label>
                                <input
                                    type="text"
                                    name="titulo"
                                    class="form-control"
                                    required
                                    value="<?= htmlspecialchars($noticia['titulo'] ?? '') ?>"
                                >
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="field-label mb-1">Autor</label>
                                <input
                                    type="text"
                                    name="autor"
                                    class="form-control"
                                    value="<?= htmlspecialchars($noticia['autor'] ?? '') ?>"
                                >
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="field-label mb-1">Resumo</label>
                            <textarea
                                name="resumo"
                                class="form-control"
                                rows="3"
                                maxlength="500"
                            ><?= htmlspecialchars($noticia['resumo'] ?? '') ?></textarea>
                            <div class="upload-hint mt-1">
                                Máximo de 500 caracteres. Este texto aparecerá em listagens e pré-visualizações.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="field-label mb-1">Conteúdo completo</label>
                            <textarea
                                name="conteudo"
                                class="form-control"
                                rows="8"
                            ><?= htmlspecialchars($noticia['conteudo'] ?? '') ?></textarea>
                        </div>

                        <div class="row mb-3">
                            <div class="col-12 col-md-6 mb-3 mb-md-0">
                                <label class="field-label mb-1">Tipo de notícia</label>
                                <select
                                    name="categoria"
                                    id="categoria"
                                    class="form-select js-nice-select"
                                    required
                                >
                                    <option value="" disabled <?= empty($noticia['categoria']) ? 'selected' : '' ?>>
                                        Selecione uma opção
                                    </option>

                                    <option value="plataforma" <?= ($noticia['categoria'] ?? '') === 'plataforma' ? 'selected' : '' ?>>
                                        Plataforma (espaços verdes)
                                    </option>

                                    <option value="estrada" <?= ($noticia['categoria'] ?? '') === 'estrada' ? 'selected' : '' ?>>
                                        Estrada
                                    </option>

                                    <option value="outros" <?= ($noticia['categoria'] ?? '') === 'outros' ? 'selected' : '' ?>>
                                        Outros
                                    </option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="field-label mb-1">Data de publicação</label>
                                <input
                                    type="date"
                                    name="datapublicacao"
                                    class="form-control"
                                    value="<?= htmlspecialchars($noticia['data_publicacao'] ?? '') ?>"
                                >
                                <div class="upload-hint mt-1">
                                    Se deixar vazio, a notícia não terá data específica de publicação.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="section-label">Imagens</div>

                        <div class="mb-3">
                            <label class="field-label mb-1">Imagem da lista</label>
                            <div class="upload-box">
                                <label class="mb-1">Substituir imagem da lista opcional</label>
                                <input
                                    type="file"
                                    name="imagemlista"
                                    class="form-control"
                                    accept=".jpg,.jpeg,.png,.webp"
                                >
                                <div class="upload-hint mt-1">
                                    Formatos permitidos: JPG, JPEG, PNG, WEBP.
                                </div>
                            </div>
                            <?php if (!empty($noticia['imagem_lista'])): ?>
                                <div class="current-img-list mt-2">
                                    <?php
                                    $listaParts = explode('|', $noticia['imagem_lista']);
                                    foreach ($listaParts as $imgPath):
                                        $imgPath = trim($imgPath);
                                        if (!$imgPath) continue;
                                    ?>
                                        <div>
                                            <img src="/PAP/<?= htmlspecialchars($imgPath) ?>" alt="Imagem lista">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="current-img-caption mt-1">
                                    Imagem atualmente usada na lista.
                                </div>
                            <?php else: ?>
                                <div class="current-img-caption mt-1">
                                    Nenhuma imagem de lista definida.
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="field-label mb-1">Imagens de detalhe</label>
                            <div class="upload-box">
                                <label class="mb-1">Substituir imagens de detalhe máx. 3</label>
                                <input
                                    type="file"
                                    name="imagemdetalhe[]"
                                    class="form-control"
                                    accept=".jpg,.jpeg,.png,.webp"
                                    multiple
                                >
                                <div class="upload-hint mt-1">
                                    Pode carregar até 3 imagens. Formatos permitidos: JPG, JPEG, PNG, WEBP.
                                </div>
                            </div>

                            <?php if (!empty($noticia['imagem_detalhe'])): ?>
                                <div class="current-img-list current-img-list-detail mt-2">
                                    <?php
                                    $detParts = explode('|', $noticia['imagem_detalhe']);
                                    foreach ($detParts as $imgPath):
                                        $imgPath = trim($imgPath);
                                        if (!$imgPath) continue;
                                    ?>
                                        <div>
                                            <img src="/PAP/<?= htmlspecialchars($imgPath) ?>" alt="Imagem detalhe">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="current-img-caption mt-1">
                                    Imagens atualmente usadas na página de detalhe.
                                </div>
                            <?php else: ?>
                                <div class="current-img-caption mt-1">
                                    Nenhuma imagem de detalhe definida.
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>

                <div class="d-flex justify-content-between flex-wrap gap-2 actions-row-desktop">
                    <a href="index.php?evora=editarnoticias" class="btn btn-outline-secondary">
                        Voltar à seleção
                    </a>
                    <button type="submit" name="editnews" class="btn btn-primary btn-main">
                        Guardar alterações
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>
</div>
</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-nice-select').forEach(function (el) {
        new TomSelect(el, {
            maxItems: 1,
            allowEmptyOption: true,
            create: false,
            plugins: {
                clear_button: { title: 'Limpar seleção' }
            }
        });
    });

    const burgerBtn      = document.querySelector('.burger-btn');
    const sidebarWrapper = document.querySelector('.sidebar-wrapper');

    if (burgerBtn && sidebarWrapper) {
        burgerBtn.addEventListener('click', function (e) {
            e.preventDefault();
            sidebarWrapper.classList.toggle('active');
        });
    }
});
</script>
</body>
</html>
