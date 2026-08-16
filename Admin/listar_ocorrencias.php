<?php
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

/* ========= APENAS PDF MULTI-SELEÇÃO ========= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? 'single';

    if (isset($_POST['btn_pdf']) && $mode === 'multi'
        && !empty($_POST['ids']) && is_array($_POST['ids'])) {

        $ids    = array_map('intval', $_POST['ids']);
        $idsStr = implode(',', $ids);

        header('Location: export_pdf.php?tipo=ocorrencias&ids=' . urlencode($idsStr));
        exit();
    }
}

/* ========= CARREGAR TODAS AS OCORRÊNCIAS (SEM LIMIT/OFFSET) ========= */
$sqlOcorrencias = "
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
";
$ocorrencias = $conn->query($sqlOcorrencias);

/* ========= VALORES ÚNICOS PARA OS FILTROS ========= */
$tipos_result = $conn->query("
    SELECT DISTINCT tipo_intervencao
    FROM ocorrencias
    WHERE tipo_intervencao IS NOT NULL AND tipo_intervencao <> ''
    ORDER BY tipo_intervencao ASC
");
$estados_result = $conn->query("
    SELECT DISTINCT estado
    FROM ocorrencias
    WHERE estado IS NOT NULL AND estado <> ''
    ORDER BY estado ASC
");

/* ========= PAGINAÇÃO CLIENT-SIDE (VISUAL) ========= */
$per_page        = 6;
$total_ocorr     = $ocorrencias ? $ocorrencias->num_rows : 0;
$total_pages_vis = $per_page > 0 ? (int)ceil($total_ocorr / $per_page) : 1;

// filtros atuais só para pré-preencher select/date se quiseres usar GET depois
$f_tarefa = isset($_GET['tarefa']) ? trim($_GET['tarefa']) : '';
$f_tipo   = isset($_GET['tipo'])   ? trim($_GET['tipo'])   : '';
$f_data   = isset($_GET['data'])   ? trim($_GET['data'])   : '';

/* ========= CAMINHO BASE DAS IMAGENS =========
   Se o teu projeto estiver em /PAP/, deixa assim.
   Se não estiver, muda só esta linha.
*/
$uploadBaseUrl = '/PAP/uploads/ocorrencias/';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Ocorrências registadas</title>
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

        #openFilterPanel,
        #toggleSelectMode,
        #pdfButton {
            border-radius: 999px;
            padding: 0.35rem 0.9rem;
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
        }

        .ocor-card-container {
            display: flex;
        }

        .ocor-card {
            border-radius: 16px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
            overflow: hidden;
            background-color: #ffffff;
            display: flex;
            flex-direction: column;
            width: 100%;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .ocor-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12);
        }

        .ocor-card-body {
            padding: 0.85rem 1.1rem 0.6rem 1.1rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }

        .ocor-card-footer {
            background-color: #f9fafb;
            padding: 0.45rem 1.1rem 0.55rem 1.1rem;
            font-size: 0.78rem;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:.75rem;
        }

        .ocor-label {
            font-weight: 600;
            font-size: 13px;
            color: #6b7280;
        }

        .ocor-value {
            font-size: 14px;
            color: #111827;
        }

        .ocor-descricao {
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .ocor-line {
            margin-bottom: 0.1rem;
        }

        .ocor-image-line{
            display:flex;
            align-items:center;
            gap:6px;
            margin-top: 0.15rem;
        }

        .ocor-image-btn{
            padding:0 6px;
            font-size:0.85rem;
            line-height:1.2;
            height:1.4rem;
        }

        .section-subtitle {
            font-size: 0.85rem;
            color: #9ca3af;
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
        }

        /* LIGHTBOX */
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
    <h3 class="mb-1">Ocorrências registadas</h3>
    <p class="text-subtitle text-muted mb-0">
        Veja e filtre todas as ocorrências registadas no sistema.
    </p>
</div>

<div class="page-content">
<section class="section">
    <div class="card card-main position-relative">
        <div class="card-header">
            <div class="card-header-flex">
                <div>
                    <h4 class="mb-1">Ocorrências registadas</h4>
                    <span class="section-subtitle">
                        Use os filtros para encontrar rapidamente tipos ou tarefas específicas.
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
                        type="submit"
                        class="btn btn-outline-secondary"
                        id="pdfButton"
                        name="btn_pdf"
                        form="ocorrenciasForm"
                    >
                        Exportar PDF
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

        <!-- PAINEL DE FILTROS (igual padrão árvores) -->
        <div id="filterPanel" class="filter-panel d-none">
            <form id="ocorFilterForm" method="get" action="index.php">
                <input type="hidden" name="evora" value="listocorrencias">

                <div class="mb-2">
                    <label for="filterTarefa" class="form-label">Tarefa</label>
                    <select id="filterTarefa" name="tarefa" class="form-select js-nice-select">
                        <option value="">Todas</option>
                        <?php if ($estados_result && $estados_result->num_rows > 0): ?>
                            <?php while ($rowE = $estados_result->fetch_assoc()): ?>
                                <?php if (!empty($rowE['estado'])):
                                    $val = strtolower($rowE['estado']);
                                    $sel = ($val === strtolower($f_tarefa)) ? 'selected' : '';
                                ?>
                                    <option value="<?= htmlspecialchars($val) ?>" <?= $sel ?>>
                                        <?= htmlspecialchars($rowE['estado']) ?>
                                    </option>
                                <?php endif; ?>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="mb-2">
                    <label for="filterTipoIntervencao" class="form-label">
                        Tipo de Intervenção
                    </label>
                    <select id="filterTipoIntervencao" name="tipo" class="form-select js-nice-select">
                        <option value="">Todos</option>
                        <?php if ($tipos_result && $tipos_result->num_rows > 0): ?>
                            <?php while ($rowT = $tipos_result->fetch_assoc()): ?>
                                <?php if (!empty($rowT['tipo_intervencao'])):
                                    $val = strtolower($rowT['tipo_intervencao']);
                                    $sel = ($val === strtolower($f_tipo)) ? 'selected' : '';
                                ?>
                                    <option value="<?= htmlspecialchars($val) ?>" <?= $sel ?>>
                                        <?= htmlspecialchars($rowT['tipo_intervencao']) ?>
                                    </option>
                                <?php endif; ?>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="mb-2">
                    <label for="filterData" class="form-label">
                        Data da ocorrência (exata)
                    </label>
                    <input
                        type="date"
                        class="form-control"
                        id="filterData"
                        name="data"
                        value="<?= htmlspecialchars($f_data) ?>"
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
        <!-- FIM PAINEL FILTROS -->

        <div class="card-body">
            <?php if ($ocorrencias && $ocorrencias->num_rows > 0): ?>
                <form method="post" id="ocorrenciasForm">
                    <input type="hidden" name="mode" id="deleteModeInput" value="single">

                    <div class="container-fluid">
                        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3" id="ocorList">
                            <?php while ($ocor = $ocorrencias->fetch_assoc()): ?>
                                <?php
                                    $dataOcorrenciaRaw = $ocor['data_ocorrencia'];
                                    $textoDataOcorrencia = $dataOcorrenciaRaw
                                        ? date('d/m/Y', strtotime($dataOcorrenciaRaw))
                                        : 'Sem data';
                                    $dataOcorrenciaAttr = $dataOcorrenciaRaw
                                        ? date('Y-m-d', strtotime($dataOcorrenciaRaw))
                                        : '';
                                    $textoCriadoEm = date('d/m/Y H:i', strtotime($ocor['criado_em']));

                                    $imgs = [];
                                    if (!empty($ocor['imagem'])) {
                                        $imgs[] = $uploadBaseUrl . rawurlencode($ocor['imagem']);
                                    }
                                    $imgsJson = htmlspecialchars(json_encode($imgs), ENT_QUOTES, 'UTF-8');
                                ?>
                                <div class="col ocor-card-container">
                                    <div class="ocor-card">
                                        <div class="ocor-card-body">

                                            <div class="ocor-line">
                                                <span class="ocor-label">Descrição:</span>
                                                <span class="ocor-value ocor-descricao">
                                                    <?= htmlspecialchars($ocor['descricao']) ?>
                                                </span>
                                            </div>

                                            <div class="ocor-line">
                                                <span class="ocor-label">Local:</span>
                                                <span class="ocor-value">
                                                    <?= $ocor['place_name']
                                                        ? htmlspecialchars($ocor['place_name'])
                                                        : 'Sem nome' ?>
                                                </span>
                                            </div>

                                            <div class="ocor-line">
                                                <span class="ocor-label">Latitude/Longitude:</span>
                                                <span class="ocor-value">
                                                    <?= htmlspecialchars($ocor['latitude']) ?>,
                                                    <?= htmlspecialchars($ocor['longitude']) ?>
                                                </span>
                                            </div>

                                            <div class="ocor-line">
                                                <span class="ocor-label">Tipo de Intervenção:</span>
                                                <span class="ocor-value ocor-tipo">
                                                    <?= htmlspecialchars($ocor['tipo_intervencao'] ?: 'Nenhuma') ?>
                                                </span>
                                            </div>

                                            <div class="ocor-line">
                                                <span class="ocor-label">Tarefa:</span>
                                                <span class="ocor-value ocor-tarefa">
                                                    <?= htmlspecialchars($ocor['estado']) ?>
                                                </span>
                                            </div>

                                            <div class="ocor-line">
                                                <span class="ocor-label">Data da ocorrência:</span>
                                                <span class="ocor-value ocor-data">
                                                    <?= $textoDataOcorrencia ?>
                                                </span>
                                            </div>

                                            <?php if (!empty($imgs)): ?>
                                                <div class="ocor-line ocor-image-line">
                                                    <span class="ocor-label">Imagem da ocorrência:</span>
                                                    <button
                                                        type="button"
                                                        class="btn btn-outline-secondary ocor-image-btn d-inline-flex align-items-center js-open-gallery"
                                                        data-images='<?= $imgsJson ?>'
                                                        data-start-index="0"
                                                    >
                                                        <i class="bi bi-card-image me-1"></i>
                                                        <span>Ver imagem</span>
                                                    </button>
                                                </div>
                                            <?php endif; ?>

                                        </div>

                                        <div class="ocor-card-footer">
                                            <span>
                                                Criado em: <?= htmlspecialchars($textoCriadoEm) ?>
                                            </span>

                                            <div class="form-check mb-0 multi-checkbox-wrapper" style="display:none;">
                                                <input
                                                    class="form-check-input msg-checkbox"
                                                    type="checkbox"
                                                    name="ids[]"
                                                    value="<?= (int)$ocor['id'] ?>"
                                                    id="ocorChk<?= (int)$ocor['id'] ?>"
                                                >
                                                <label class="form-check-label" for="ocorChk<?= (int)$ocor['id'] ?>">
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

                <!-- PAGINAÇÃO CLIENT-SIDE -->
                <nav id="paginationNav" aria-label="Paginação de ocorrências" class="mt-3 mb-1">
                    <ul class="pagination justify-content-center flex-wrap" id="paginationLinks"></ul>
                </nav>
            <?php else: ?>
                <div class="alert alert-info text-center mb-0">
                    Nenhuma ocorrência registada.
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
</div>
</div>
</div>

<!-- OVERLAY DE IMAGEM -->
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
const filterForm          = document.getElementById('ocorFilterForm');

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

        const tarefaSelect = document.getElementById('filterTarefa').tomselect;
        if (tarefaSelect) tarefaSelect.clear();

        const tipoSelect = document.getElementById('filterTipoIntervencao').tomselect;
        if (tipoSelect) tipoSelect.clear();

        filterForm.querySelectorAll('input[name="page"]').forEach(i => i.remove());
        applyFilters();
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

// CLIENT-SIDE FILTER + PAGINAÇÃO (igual padrão árvores)
const perPage         = <?= (int)$per_page ?>;
const cards           = Array.from(document.querySelectorAll('.ocor-card-container'));
const paginationNav   = document.getElementById('paginationNav');
const paginationLinks = document.getElementById('paginationLinks');

const filterTarefaEl = document.getElementById('filterTarefa');
const filterTipoEl   = document.getElementById('filterTipoIntervencao');
const filterDataEl   = document.getElementById('filterData');

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

// aplica filtros lendo texto do cartão (tipo, tarefa, data), igual árvores
function applyFilters() {
    const tarefa  = (filterTarefaEl.value || '').trim().toLowerCase();
    const tipo    = (filterTipoEl.value || '').trim().toLowerCase();
    const dataSel = (filterDataEl.value || '').trim();  // yyyy-mm-dd

    cards.forEach(card => {
        const textTarefa = (card.querySelector('.ocor-tarefa')?.textContent || '')
            .trim().toLowerCase();
        const textTipo   = (card.querySelector('.ocor-tipo')?.textContent || '')
            .trim().toLowerCase();
        const textData   = (card.querySelector('.ocor-data')?.textContent || '')
            .trim(); // dd/mm/yyyy ou "Sem data"

        let dataCard = '';
        if (textData && textData !== 'Sem data') {
            const parts = textData.split('/');
            if (parts.length === 3) {
                dataCard = parts[2] + '-' + parts[1] + '-' + parts[0];
            }
        }

        const matchesTarefa = !tarefa || textTarefa === tarefa;
        const matchesTipo   = !tipo   || textTipo === tipo;
        const matchesData   = !dataSel || dataCard === dataSel;

        if (matchesTarefa && matchesTipo && matchesData) {
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

// ligar submit do painel
if (filterForm) {
    filterForm.addEventListener('submit', (e) => {
        e.preventDefault();
        applyFilters();
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
            showPage(page);
        }
    });
}

// inicial
if (cards.length > 0) {
    applyFilters();
} else if (paginationNav) {
    paginationNav.style.display = 'none';
}

// MODO SELEÇÃO + PDF (igual antes)
const toggleSelectModeBtn2 = document.getElementById('toggleSelectMode');
const deleteModeInput2     = document.getElementById('deleteModeInput');
const pdfButton2           = document.getElementById('pdfButton');
const mainForm2            = document.getElementById('ocorrenciasForm');

let selectionMode = false;

function updateSelectionUI() {
    const checkboxWrappers = document.querySelectorAll('.multi-checkbox-wrapper');

    if (selectionMode) {
        deleteModeInput2.value = 'multi';
        checkboxWrappers.forEach(w => w.style.display = 'block');
        toggleSelectModeBtn2.classList.add('active');
    } else {
        deleteModeInput2.value = 'single';
        document.querySelectorAll('.msg-checkbox').forEach(chk => chk.checked = false);
        checkboxWrappers.forEach(w => w.style.display = 'none');
        toggleSelectModeBtn2.classList.remove('active');
    }
}

if (toggleSelectModeBtn2) {
    toggleSelectModeBtn2.addEventListener('click', () => {
        selectionMode = !selectionMode;
        updateSelectionUI();
    });
}

if (pdfButton2 && mainForm2) {
    pdfButton2.addEventListener('click', (e) => {
        const anyChecked = Array.from(document.querySelectorAll('.msg-checkbox'))
            .some(chk => chk.checked);
        if (!anyChecked) {
            e.preventDefault();
            alert('Selecione pelo menos uma ocorrência para exportar o PDF.');
        } else {
            deleteModeInput2.value = 'multi';
        }
    });
}

// fechar filtro quando abre menu mobile
const burgerBtn = document.querySelector('.burger-btn');
if (burgerBtn && filterPanel) {
    burgerBtn.addEventListener('click', () => {
        filterPanel.classList.add('d-none');
    });
}

/* ========= GALERIA / LIGHTBOX ========= */
const overlay      = document.getElementById('imgOverlay');
const overlayImg   = document.getElementById('imgOverlayImg');
const prevBtn      = document.getElementById('imgPrevBtn');
const nextBtn      = document.getElementById('imgNextBtn');
const galleryBtns  = document.querySelectorAll('.js-open-gallery');

let currentImages = [];
let currentIndex  = 0;

function updateOverlayImage() {
    if (!currentImages.length) return;
    overlayImg.src = currentImages[currentIndex];
}

function openGallery(images, startIndex = 0) {
    if (!images || !images.length) return;

    currentImages = images;
    currentIndex  = startIndex >= 0 ? startIndex : 0;

    updateOverlayImage();
    overlay.classList.add('show');
    overlay.setAttribute('aria-hidden', 'false');
}

function closeGallery() {
    overlay.classList.remove('show');
    overlay.setAttribute('aria-hidden', 'true');
    overlayImg.src = '';
    currentImages = [];
    currentIndex = 0;
}

function showPrevImage() {
    if (!currentImages.length) return;
    currentIndex = (currentIndex - 1 + currentImages.length) % currentImages.length;
    updateOverlayImage();
}

function showNextImage() {
    if (!currentImages.length) return;
    currentIndex = (currentIndex + 1) % currentImages.length;
    updateOverlayImage();
}

galleryBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        let images = [];
        try {
            images = JSON.parse(btn.getAttribute('data-images') || '[]');
        } catch (e) {
            images = [];
        }

        const startIndex = parseInt(btn.getAttribute('data-start-index') || '0', 10);
        openGallery(images, startIndex);
    });
});

if (prevBtn) {
    prevBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        showPrevImage();
    });
}

if (nextBtn) {
    nextBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        showNextImage();
    });
}

if (overlay) {
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
            closeGallery();
        }
    });
}

document.addEventListener('keydown', (e) => {
    if (!overlay.classList.contains('show')) return;

    if (e.key === 'Escape') {
        closeGallery();
    } else if (e.key === 'ArrowLeft') {
        showPrevImage();
    } else if (e.key === 'ArrowRight') {
        showNextImage();
    }
});
</script>
</body>
</html>