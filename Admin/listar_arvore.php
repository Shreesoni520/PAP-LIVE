<?php
// NUNCA chamar session_start() aqui, já é chamado em index.php
// NUNCA incluir config/db_conn aqui, já vem de index.php -> config.php

if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

/* ========= APENAS PDF MULTI-SELEÇÃO ========= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? 'single';

    // Exportar PDF (multi-seleção)
    if (isset($_POST['btn_pdf']) && $mode === 'multi'
        && !empty($_POST['delete_ids']) && is_array($_POST['delete_ids'])) {

        $ids    = array_map('intval', $_POST['delete_ids']);
        $idsStr = implode(',', $ids);

        header('Location: export_pdf.php?tipo=arvores&ids=' . urlencode($idsStr));
        exit();
    }
}

/* ========= CARREGAR TODAS AS ÁRVORES (SEM LIMIT/OFFSET) ========= */
$sqlTrees = "
    SELECT *
    FROM arvores
    ORDER BY criado_em DESC
";
$trees = $conn->query($sqlTrees);

/* ========= VALORES ÚNICOS PARA OS FILTROS (SEM FILTRO, SEM LIMIT) ========= */
$estados_result = $conn->query("
    SELECT DISTINCT estado
    FROM arvores
    WHERE estado IS NOT NULL
      AND estado <> ''
    ORDER BY estado ASC
");

$tipos_result = $conn->query("
    SELECT DISTINCT tipo_intervencao
    FROM arvores
    WHERE tipo_intervencao IS NOT NULL
      AND tipo_intervencao <> ''
    ORDER BY tipo_intervencao ASC
");

/* ========= PAGINAÇÃO CLIENT-SIDE (VISUAL) ========= */
$per_page     = 6;
$total_trees  = $trees ? $trees->num_rows : 0;
$total_pages  = $per_page > 0 ? (int)ceil($total_trees / $per_page) : 1;

// filtros atuais (apenas para preencher inputs do painel)
$f_especie = isset($_GET['especie']) ? trim($_GET['especie']) : '';
$f_tarefa  = isset($_GET['tarefa'])  ? trim($_GET['tarefa'])  : '';
$f_tipo    = isset($_GET['tipo'])    ? trim($_GET['tipo'])    : '';
$f_data    = isset($_GET['criado_em']) ? trim($_GET['criado_em']) : '';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Árvores registadas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link
        href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <!-- Tom Select -->
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

        /* BOTÕES NO TOPO */
        #openFilterPanel,
        #toggleSelectMode,
        #pdfButton {
            border-radius: 999px;
            padding: 0.4rem 1rem;
            font-size: 0.9rem;
        }

        .top-action-btn {
            line-height: 1.2;
        }

        /* PAINEL DE FILTRO – desktop */
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

        /* Tom Select */
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

        /* LISTA DE ESPÉCIES */
        .especie-wrapper {
            position: relative;
        }

        #especieList {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 180px;
            overflow-y: auto;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            margin-top: 4px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.16);
            list-style: none;
            padding: 4px 0;
            z-index: 2100;
            display: none;
        }

        #especieList li {
            padding: 6px 10px;
            font-size: 0.85rem;
            cursor: pointer;
        }

        #especieList li:hover {
            background: #f3f4f6;
        }

        /* CARTÕES ÁRVORES */
        .tree-card-container {
            display: flex;
        }

        .tree-card {
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

        .tree-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12);
        }

        .tree-card-body {
            padding: 0.9rem 1.1rem 0.6rem 1.1rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .tree-card-footer {
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

        .tree-title {
            font-weight: 800;
            font-size: 1rem;
            margin-bottom: 0.3rem;
            color: #111827;
        }

        .tree-label {
            font-weight: 600;
            font-size: 13px;
            color: #6b7280;
        }

        .tree-value {
            font-size: 14px;
            color: #111827;
        }

        .tree-line {
            margin-bottom: 0.15rem;
        }

        .section-subtitle {
            font-size: 0.85rem;
            color: #9ca3af;
        }

        /* MOBILE AJUSTES */
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

            #especieList {
                max-height: 150px;
                font-size: 0.8rem;
            }

            #especieList li {
                padding: 4px 8px;
            }

            .card-header-flex {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.6rem;
            }

            .tree-card-footer {
                flex-direction: column;
                align-items: flex-start;
            }

            .top-actions {
                width: 100%;
            }
            .top-actions .top-action-btn {
                flex: 1 1 100%;
                justify-content: center;
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
            <h3 class="mb-1">Árvores registadas</h3>
            <p class="text-subtitle text-muted mb-0">
                Veja e filtre todas as árvores registadas no sistema.
            </p>
        </div>

        <div class="page-content">
            <section class="section">
                <div class="card card-main position-relative">
                    <div class="card-header">
                        <div class="card-header-flex">
                            <div>
                                <h4 class="mb-1">Árvores registadas</h4>
                                <span class="section-subtitle">
                                    Use os filtros para encontrar rapidamente espécies ou tarefas específicas.
                                </span>
                            </div>

                            <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end top-actions">
                                <button
                                    id="toggleSelectMode"
                                    class="btn btn-outline-secondary d-flex align-items-center top-action-btn"
                                    type="button"
                                >
                                    <i class="bi bi-check2-square me-1"></i>
                                    <span>Modo seleção</span>
                                </button>

                                <button
                                    type="submit"
                                    class="btn btn-outline-secondary d-flex align-items-center top-action-btn"
                                    id="pdfButton"
                                    name="btn_pdf"
                                    form="treesForm"
                                >
                                    <span>Exportar PDF</span>
                                </button>

                                <button
                                    id="openFilterPanel"
                                    class="btn btn-outline-secondary d-flex align-items-center top-action-btn"
                                    type="button"
                                >
                                    <i class="bi bi-funnel-fill me-1"></i>
                                    <span>Filtrar</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- CAIXA DE FILTROS -->
                    <div id="filterPanel" class="filter-panel d-none">
                        <form id="treeFilterForm" method="get" action="index.php">
                            <input type="hidden" name="evora" value="listtree">

                            <div class="mb-2 especie-wrapper">
                                <label for="filterEspecie" class="form-label">Espécie</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="filterEspecie"
                                    name="especie"
                                    placeholder="Nome da espécie"
                                    value="<?= htmlspecialchars($f_especie) ?>"
                                >
                                <ul id="especieList"></ul>
                            </div>

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
                                <label for="filterCriadoEm" class="form-label">
                                    Criado em (data exata)
                                </label>
                                <input
                                    type="date"
                                    class="form-control"
                                    id="filterCriadoEm"
                                    name="criado_em"
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
                    <!-- FIM CAIXA DE FILTROS -->

                    <div class="card-body">
                        <?php if ($trees && $trees->num_rows > 0): ?>
                            <form method="post" id="treesForm">
                                <input type="hidden" name="mode" id="deleteModeInput" value="single">

                                <div class="container-fluid">
                                    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3" id="treeList">
                                        <?php while ($tree = $trees->fetch_assoc()): ?>
                                            <?php
                                                $criadoRaw = $tree['criado_em'] ?? '';
                                                if ($criadoRaw !== '' && $criadoRaw !== null) {
                                                    $tsCriado   = strtotime($criadoRaw);
                                                    $criadoText = $tsCriado ? date('Y-m-d H:i', $tsCriado) : $criadoRaw;
                                                } else {
                                                    $criadoText = '—';
                                                }
                                            ?>
                                            <div class="col tree-card-container">
                                                <div class="tree-card">
                                                    <div class="tree-card-body">
                                                        <div class="tree-title tree-search-especie">
                                                            <?= htmlspecialchars($tree['especie']) ?>
                                                        </div>

                                                        <div class="tree-line">
                                                            <span class="tree-label">Nome do Espaço:</span>
                                                            <span class="tree-value">
                                                                <?= htmlspecialchars($tree['place_name']) ?>
                                                            </span>
                                                        </div>

                                                        <div class="tree-line">
                                                            <span class="tree-label">Latitude/Longitude:</span>
                                                            <span class="tree-value">
                                                                <?= htmlspecialchars($tree['latitude']) ?>,
                                                                <?= htmlspecialchars($tree['longitude']) ?>
                                                            </span>
                                                        </div>

                                                        <div class="tree-line">
                                                            <span class="tree-label">Tipo de Intervenção:</span>
                                                            <span class="tree-value tree-search-tipo">
                                                                <?= htmlspecialchars($tree['tipo_intervencao']) ?>
                                                            </span>
                                                        </div>

                                                        <div class="tree-line">
                                                            <span class="tree-label">Tarefa:</span>
                                                            <span class="tree-value tree-tarefa">
                                                                <?= htmlspecialchars($tree['estado']) ?>
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <div class="tree-card-footer">
                                                        <span>
                                                            Criado em: <?= htmlspecialchars($criadoText) ?>
                                                        </span>

                                                        <div class="form-check mb-0 multi-checkbox-wrapper" style="display:none;">
                                                            <input
                                                                class="form-check-input msg-checkbox"
                                                                type="checkbox"
                                                                name="delete_ids[]"
                                                                value="<?= (int)$tree['id'] ?>"
                                                                id="treeChk<?= (int)$tree['id'] ?>"
                                                            >
                                                            <label class="form-check-label" for="treeChk<?= (int)$tree['id'] ?>">
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
                            <nav id="paginationNav" aria-label="Paginação de árvores" class="mt-3 mb-1">
                                <ul class="pagination justify-content-center flex-wrap" id="paginationLinks"></ul>
                            </nav>
                        <?php else: ?>
                            <div class="alert alert-info text-center mb-0">
                                Nenhuma árvore registada.
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
// === FILTRO: abrir/fechar painel ===
const openFilterPanelBtn  = document.getElementById('openFilterPanel');
const filterPanel         = document.getElementById('filterPanel');
const closeFilterPanelBtn = document.getElementById('closeFilterPanel');
const clearFiltersBtn     = document.getElementById('clearFilters');
const filterForm          = document.getElementById('treeFilterForm');

openFilterPanelBtn.addEventListener('click', function () {
    filterPanel.classList.toggle('d-none');
});

closeFilterPanelBtn.addEventListener('click', function () {
    filterPanel.classList.add('d-none');
});

// limpar filtros -> GET sem filtros
clearFiltersBtn.addEventListener('click', function () {
    document.getElementById('filterEspecie').value  = '';
    document.getElementById('filterCriadoEm').value = '';
    const tarefaSelect = document.getElementById('filterTarefa').tomselect;
    if (tarefaSelect) tarefaSelect.clear();
    const tipoSelect   = document.getElementById('filterTipoIntervencao').tomselect;
    if (tipoSelect) tipoSelect.clear();

    filterForm.querySelectorAll('input[name="page"]').forEach(i => i.remove());
    filterForm.submit();
});

/* LISTA DE ESPÉCIES A PARTIR DOS CARTÕES */
const especieInput = document.getElementById('filterEspecie');
const especieList  = document.getElementById('especieList');

function buildEspecieList() {
    const especies = new Set();
    document.querySelectorAll('.tree-search-especie').forEach(function (el) {
        const name = el.textContent.trim();
        if (name) {
            especies.add(name);
        }
    });

    especieList.innerHTML = '';
    Array.from(especies).sort().forEach(function (nome) {
        const li = document.createElement('li');
        li.textContent = nome;
        li.addEventListener('click', function () {
            especieInput.value = nome;
            especieList.style.display = 'none';
            applyFilters();
        });
        especieList.appendChild(li);
    });
}

especieInput.addEventListener('focus', function () {
    buildEspecieList();
    especieList.style.display = 'block';
});

especieInput.addEventListener('input', function () {
    const term = this.value.trim().toLowerCase();
    Array.from(especieList.children).forEach(function (li) {
        const text = li.textContent.toLowerCase();
        li.style.display = !term || text.includes(term) ? '' : 'none';
    });
});

document.addEventListener('click', function (e) {
    if (!especieList.contains(e.target) && e.target !== especieInput) {
        especieList.style.display = 'none';
    }
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

// === CLIENT-SIDE FILTER + PAGINATION VISUAL ===
const perPage         = <?= (int)$per_page ?>;
const cards           = Array.from(document.querySelectorAll('.tree-card-container'));
const paginationNav   = document.getElementById('paginationNav');
const paginationLinks = document.getElementById('paginationLinks');

const filterTarefaEl = document.getElementById('filterTarefa');
const filterTipoEl   = document.getElementById('filterTipoIntervencao');
const filterDataEl   = document.getElementById('filterCriadoEm');

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

// ligar filtros do painel ao applyFilters
function applyFilters() {
    const especie         = especieInput.value.trim().toLowerCase();
    const tarefa          = filterTarefaEl.value.trim().toLowerCase();
    const tipoIntervencao = filterTipoEl.value.trim().toLowerCase();
    const criadoEm        = filterDataEl.value;

    cards.forEach(card => {
        const textEspecie = (card.querySelector('.tree-search-especie')?.textContent || '')
            .trim().toLowerCase();
        const textTarefa  = (card.querySelector('.tree-tarefa')?.textContent || '')
            .trim().toLowerCase();
        const textTipo    = (card.querySelector('.tree-search-tipo')?.textContent || '')
            .trim().toLowerCase();
        const footerText  = card.querySelector('.tree-card-footer')?.textContent || '';

        let textCriadoEm = '';
        const matchDate  = footerText.match(/\d{4}-\d{2}-\d{2}/);
        if (matchDate) textCriadoEm = matchDate[0];

        const matchesEspecie = !especie || textEspecie.includes(especie);
        const matchesTarefa  = !tarefa || textTarefa === tarefa;
        const matchesTipo    = !tipoIntervencao || textTipo === tipoIntervencao;
        const matchesCriado  = !criadoEm || textCriadoEm === criadoEm;

        if (matchesEspecie && matchesTarefa && matchesTipo && matchesCriado) {
            card.style.display   = '';
            card.style.visibility = '';
            card.style.position   = '';
        } else {
            card.style.display   = 'none';
            card.style.visibility = '';
            card.style.position   = '';
        }
    });

    showPage(1);
}

filterForm.addEventListener('submit', function (e) {
    e.preventDefault();
    applyFilters();
    filterPanel.classList.add('d-none');
});

// clique nos botões de paginação
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

// inicial
if (cards.length > 0) {
    applyFilters();
} else {
    if (paginationNav) {
        paginationNav.style.display = 'none';
    }
}

// === MODO SELEÇÃO + PDF ===
const toggleSelectModeBtn = document.getElementById('toggleSelectMode');
const deleteModeInput     = document.getElementById('deleteModeInput');
const pdfButton           = document.getElementById('pdfButton');
const mainForm            = document.getElementById('treesForm');

let selectionMode = false;

function updateSelectionUI() {
    const checkboxWrappers = document.querySelectorAll('.multi-checkbox-wrapper');

    if (selectionMode) {
        deleteModeInput.value = 'multi';
        checkboxWrappers.forEach(w => w.style.display = 'block');
        toggleSelectModeBtn.classList.add('active');
    } else {
        deleteModeInput.value = 'single';
        document.querySelectorAll('.msg-checkbox').forEach(chk => chk.checked = false);
        checkboxWrappers.forEach(w => w.style.display = 'none');
        toggleSelectModeBtn.classList.remove('active');
    }
}

if (toggleSelectModeBtn) {
    toggleSelectModeBtn.addEventListener('click', function () {
        selectionMode = !selectionMode;
        updateSelectionUI();
    });
}

// PDF button: exige pelo menos uma árvore selecionada
if (pdfButton && mainForm) {
    pdfButton.addEventListener('click', function (e) {
        const anyChecked = Array.from(document.querySelectorAll('.msg-checkbox'))
            .some(chk => chk.checked);
        if (!anyChecked) {
            e.preventDefault();
            alert('Selecione pelo menos uma árvore para exportar o PDF.');
        } else {
            deleteModeInput.value = 'multi';
        }
    });
}

// EXTRA: fechar filtro quando abrir o menu (mobile)
const burgerBtn = document.querySelector('.burger-btn');
if (burgerBtn && filterPanel) {
    burgerBtn.addEventListener('click', () => {
        filterPanel.classList.add('d-none');
    });
}
</script>
</body>
</html>
