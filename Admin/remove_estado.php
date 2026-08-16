<?php
include './log.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

/* ==== REMOVER TAREFA (single + multi) ==== */
$success = '';
$error   = '';

if (!empty($_POST)) {
    $ids = [];

    if (isset($_POST['mode']) && $_POST['mode'] === 'multi'
        && !empty($_POST['delete_ids']) && is_array($_POST['delete_ids'])) {

        $ids = array_map('intval', $_POST['delete_ids']);

    } elseif (isset($_POST['mode']) && $_POST['mode'] === 'single'
              && isset($_POST['delete_id'])) {

        $ids = [intval($_POST['delete_id'])];
    }

    if (!empty($ids)) {
        $in    = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        $stmt = $conn->prepare("DELETE FROM states WHERE id IN ($in)");
        if ($stmt === false) {
            $error = "Erro na preparação da query.";
        } else {
            $stmt->bind_param($types, ...$ids);
            if ($stmt->execute()) {
                $success = (count($ids) > 1)
                    ? "Tarefa(s) removida(s) com sucesso."
                    : "Tarefa removida com sucesso.";

                $userId = (int)$_SESSION['user_id'];

                foreach ($ids as $id) {
                    $msgLog  = (count($ids) > 1)
                        ? "Tarefa apagada (remoção múltipla)."
                        : "Tarefa apagada.";
                    $detalhe = (count($ids) > 1)
                        ? "Tarefa ID $id removida (remoção múltipla)."
                        : "Tarefa ID $id removida.";

                    regista_log($conn, $userId, "remover", "tarefa", $id, $msgLog);

                    $acao = 'Remoção de tarefa';

                    $stmtAt = $conn->prepare("
                        INSERT INTO atividade (user_id, acao, detalhe)
                        VALUES (?, ?, ?)
                    ");
                    if ($stmtAt) {
                        $stmtAt->bind_param("iss", $userId, $acao, $detalhe);
                        $stmtAt->execute();
                        $stmtAt->close();
                    }
                }

                // redirect simples, paginação agora é client-side
                header('Location: index.php?evora=removeestado');
                exit();

            } else {
                $error = "Erro ao remover tarefa(s)!";
            }
            $stmt->close();
        }
    }
}
/* ==== FIM REMOVER TAREFA ==== */

/**
 * EN -> PT
 */
function traduz_cor_en_para_pt($en) {
    $en = mb_strtolower(trim($en));

    $mapa = [
        'green'        => 'verde',
        'lime'         => 'verde',
        'forestgreen'  => 'verde',
        'seagreen'     => 'verde',
        'red'          => 'vermelho',
        'crimson'      => 'vermelho',
        'firebrick'    => 'vermelho',
        'blue'         => 'azul',
        'navy'         => 'azul',
        'royalblue'    => 'azul',
        'yellow'       => 'amarelo',
        'gold'         => 'amarelo',
        'orange'       => 'laranja',
        'darkorange'   => 'laranja',
        'purple'       => 'roxo',
        'indigo'       => 'roxo',
        'violet'       => 'roxo',
        'gray'         => 'cinzento',
        'grey'         => 'cinzento',
        'darkgray'     => 'cinzento',
        'black'        => 'preto',
        'white'        => 'branco',
        'brown'        => 'castanho',
        'saddlebrown'  => 'castanho',
        'chocolate'    => 'castanho'
    ];

    return $mapa[$en] ?? null;
}

/**
 * PT -> EN
 */
function traduz_cor_pt_para_en($pt) {
    $pt = mb_strtolower(trim($pt));

    $mapa = [
        'verde'      => 'green',
        'vermelho'   => 'red',
        'azul'       => 'blue',
        'amarelo'    => 'yellow',
        'laranja'    => 'orange',
        'roxo'       => 'purple',
        'cinzento'   => 'gray',
        'cinza'      => 'gray',
        'preto'      => 'black',
        'branco'     => 'white',
        'castanho'   => 'brown'
    ];

    return $mapa[$pt] ?? null;
}

/* ========= TAREFAS (SEM LIMIT, PAGINAÇÃO CLIENT-SIDE) ========= */
// novas primeiro
$tarefas = [];
$resEstados = $conn->query("SELECT * FROM states ORDER BY id DESC");
while ($row = $resEstados->fetch_assoc()) {
    $tarefas[] = $row;
}

$total_states = count($tarefas);
$per_page     = 6;
$total_pages  = $per_page > 0 ? (int)ceil($total_states / $per_page) : 1;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Remover Tarefas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/css/tom-select.bootstrap5.min.css">

    <style>
        body, .sidebar, .card, .btn, h4, h3, h2 {
            font-family: 'Nunito', sans-serif !important;
        }
        .page-content {
            background: radial-gradient(circle at top,#e0f2fe,#eef2ff 40%,#f9fafb 80%);
        }
        .page-heading h3 { font-weight: 800; }
        .page-heading p  { color: #6b7280; }

        .card-main {
            border-radius: 20px;
            border: 0;
            box-shadow: 0 18px 45px rgba(15,23,42,0.12);
        }
        .card-header { border-bottom: 1px solid #e5e7eb; }
        .card-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .section-subtitle {
            font-size: 0.85rem;
            color: #9ca3af;
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
            box-shadow: 0 16px 40px rgba(15,23,42,0.18);
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

        .state-card-container {
            display: flex;
        }
        .state-card {
            border-radius: 16px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 6px 18px rgba(15,23,42,0.08);
            overflow: hidden;
            background-color: #ffffff;
            display: flex;
            flex-direction: column;
            width: 100%;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .state-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(15,23,42,0.12);
        }
        .state-card-body {
            padding: 1.1rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }
        .state-card-footer {
            background-color: #f9fafb;
            padding: 0.45rem 1.1rem 0.55rem 1.1rem;
            font-size: 0.78rem;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            flex-shrink: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: .75rem;
        }
        .state-title {
            font-weight: 800;
            font-size: 1rem;
            margin-bottom: 0.15rem;
            color: #111827;
        }
        .state-label {
            font-weight: 600;
            font-size: 13px;
            color: #6b7280;
        }
        .state-value {
            font-size: 14px;
            color: #111827;
        }
        .color-swatch {
            display: inline-block;
            width: 22px;
            height: 22px;
            border-radius: 4px;
            border: 1px solid #ccc;
            vertical-align: middle;
            margin-right: 8px;
        }
        .color-swatch-title {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            border: 2px solid #e5e7eb;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            transition: transform 0.2s ease;
        }
        .color-swatch-title:hover {
            transform: scale(1.1);
        }

        .multi-actions {
            display:none;
        }
        .multi-actions.show {
            display:flex;
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

        @media (max-width:768px) {
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

        @media (max-width: 992px) {
            #main { margin-left: 0 !important; }
            .sidebar-wrapper {
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                z-index: 1050;
            }
            #app { overflow-x: hidden; }
            .page-content { position: relative; z-index: 1; }
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
            <h3 class="mb-1">Remover Tarefas</h3>
            <p class="text-subtitle text-muted mb-0">Lista de tarefas. Pode filtrar e remover.</p>
        </div>

        <div class="page-content">
            <section class="section">
                <div class="card card-main position-relative">
                    <div class="card-header">
                        <div class="card-header-flex">
                            <div>
                                <h4 class="mb-1">Tarefas disponíveis</h4>
                                <span class="section-subtitle">
                                    Use o filtro por nome para encontrar mais rápido.
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

                    <div id="filterPanel" class="filter-panel d-none">
                        <form id="stateFilterForm">
                            <div class="mb-2">
                                <label for="filterTarefa" class="form-label">Tarefa</label>
                                <select id="filterTarefa" class="form-select js-ts-select">
                                    <option value="">Todas</option>
                                    <?php if (!empty($tarefas)): ?>
                                        <?php
                                        $allStates = $conn->query("SELECT DISTINCT name FROM states ORDER BY id DESC");
                                        while ($e = $allStates->fetch_assoc()):
                                        ?>
                                            <option value="<?= htmlspecialchars(strtolower($e['name'])) ?>">
                                                <?= htmlspecialchars($e['name']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="filter-panel-actions">
                                <button type="button" class="btn btn-light btn-sm" id="clearFilters">
                                    Limpar
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="closeFilterPanel">
                                    Cancelar
                                </button>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    Aplicar
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="card-body">
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                        <?php elseif (!empty($error)): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <?php if (!empty($tarefas)): ?>
                            <form method="post" id="stateDeleteForm">
                                <input type="hidden" name="mode" id="deleteModeInput" value="single">
                                <input type="hidden" name="delete_id" id="singleDeleteId" value="">

                                <div class="container-fluid">
                                    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3" id="stateList">
                                        <?php foreach ($tarefas as $tarefa): ?>
                                            <?php
                                                $storedColor = $tarefa['color_name'] ?? '';
                                                $ptNameCard  = traduz_cor_en_para_pt($storedColor) ?? $storedColor;
                                                $cssColor    = traduz_cor_pt_para_en($ptNameCard) ?? $storedColor;
                                            ?>
                                            <div class="col state-card-container">
                                                <div class="state-card">
                                                    <div class="state-card-body">
                                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                                            <div class="state-title state-search-nome">
                                                                <?= htmlspecialchars($tarefa['name']) ?>
                                                            </div>
                                                            <?php if (!empty($storedColor)): ?>
                                                                <div class="color-swatch-title"
                                                                     style="background: <?= htmlspecialchars($cssColor) ?>;"
                                                                     title="<?= htmlspecialchars($storedColor) ?>">
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div>
                                                            <span class="state-label">Cor:</span>
                                                            <span class="state-value state-color">
                                                                <span
                                                                    class="color-swatch"
                                                                    style="background: <?= htmlspecialchars($cssColor) ?>;"
                                                                ></span>
                                                                <?= htmlspecialchars($storedColor) ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="state-card-footer">
                                                        <span>Tarefa</span>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <button
                                                                type="button"
                                                                class="btn btn-danger btn-sm single-delete-btn"
                                                                data-id="<?= (int)$tarefa['id'] ?>"
                                                            >
                                                                Remover
                                                            </button>

                                                            <div class="form-check mb-0 multi-checkbox-wrapper" style="display:none;">
                                                                <input
                                                                    class="form-check-input state-checkbox"
                                                                    type="checkbox"
                                                                    name="delete_ids[]"
                                                                    value="<?= (int)$tarefa['id'] ?>"
                                                                    id="stateChk<?= (int)$tarefa['id'] ?>"
                                                                >
                                                                <label class="form-check-label" for="stateChk<?= (int)$tarefa['id'] ?>">
                                                                    Selecionar
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="mt-3 justify-content-end multi-actions" id="multiActions">
                                    <button type="submit" class="btn btn-danger"
                                            onclick="return confirm('Tem a certeza que deseja remover as tarefas selecionadas?');">
                                        Remover tarefas selecionadas
                                    </button>
                                </div>

                                <?php if ($total_pages > 1): ?>
                                    <nav id="paginationNav" aria-label="Paginação de tarefas" class="mt-3 mb-1">
                                        <ul class="pagination justify-content-center flex-wrap" id="paginationLinks"></ul>
                                    </nav>
                                <?php endif; ?>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-info text-center mb-0">
                                Nenhuma tarefa registada.
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
// FILTRO: abrir/fechar painel
const openFilterPanelBtn  = document.getElementById('openFilterPanel');
const filterPanel         = document.getElementById('filterPanel');
const closeFilterPanelBtn = document.getElementById('closeFilterPanel');
const clearFiltersBtn     = document.getElementById('clearFilters');
const filterForm          = document.getElementById('stateFilterForm');

openFilterPanelBtn.addEventListener('click', function () {
    filterPanel.classList.toggle('d-none');
});

closeFilterPanelBtn.addEventListener('click', function () {
    filterPanel.classList.add('d-none');
});

clearFiltersBtn.addEventListener('click', function () {
    const sel = document.getElementById('filterTarefa');
    sel.value = '';
    if (sel.tomselect) sel.tomselect.clear();
    applyFilters();
});

// CLIENT-SIDE FILTER + PAGINAÇÃO VISUAL
const perPage        = <?= (int)$per_page ?>;
const cards          = Array.from(document.querySelectorAll('.state-card-container'));
const filterTarefaEl = document.getElementById('filterTarefa');
const paginationNav  = document.getElementById('paginationNav');
const paginationLinks= document.getElementById('paginationLinks');

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

if (paginationLinks) {
    paginationLinks.addEventListener('click', function(e) {
        const link = e.target.closest('.page-link');
        if (!link) return;
        e.preventDefault();

        const li = link.parentElement;
        if (li.classList.contains('disabled')) return;

        const page = parseInt(link.dataset.page, 10);
        if (!isNaN(page)) showPage(page);
    });
}

function applyFilters() {
    const tarefa = filterTarefaEl.value.trim().toLowerCase();

    cards.forEach(card => {
        const textNome = (card.querySelector('.state-search-nome')?.textContent || '')
            .trim().toLowerCase();

        const matches = !tarefa || textNome === tarefa;

        if (matches) {
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

// inicial
if (cards.length > 0) {
    applyFilters();
} else {
    if (paginationNav) paginationNav.style.display = 'none';
}

// clicar fora fecha painel
document.addEventListener('click', function (e) {
    if (!filterPanel.classList.contains('d-none')) {
        if (!filterPanel.contains(e.target) && !openFilterPanelBtn.contains(e.target)) {
            filterPanel.classList.add('d-none');
        }
    }
});

// Tom Select init
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-ts-select').forEach(function (el) {
        new TomSelect(el, {
            maxItems: 1,
            allowEmptyOption: true,
            create: false,
            plugins: {
                clear_button: {
                    title: 'Limpar'
                }
            }
        });
    });
});

// MODO SELEÇÃO
const toggleSelectModeBtn = document.getElementById('toggleSelectMode');
const multiActions        = document.getElementById('multiActions');
const deleteModeInput     = document.getElementById('deleteModeInput');
const mainForm            = document.getElementById('stateDeleteForm');
const singleDeleteIdInput = document.getElementById('singleDeleteId');

let selectionMode = false;

function updateSelectionUI() {
    const singleButtons    = document.querySelectorAll('.single-delete-btn');
    const checkboxWrappers = document.querySelectorAll('.multi-checkbox-wrapper');

    if (selectionMode) {
        deleteModeInput.value = 'multi';
        multiActions.classList.add('show');
        singleButtons.forEach(btn => btn.style.display = 'none');
        checkboxWrappers.forEach(w => w.style.display = 'block');
        toggleSelectModeBtn.classList.add('active');
    } else {
        deleteModeInput.value = 'single';
        multiActions.classList.remove('show');
        document.querySelectorAll('.state-checkbox').forEach(chk => chk.checked = false);
        singleButtons.forEach(btn => btn.style.display = 'inline-block');
        checkboxWrappers.forEach(w => w.style.display = 'none');
        toggleSelectModeBtn.classList.remove('active');
    }
}

toggleSelectModeBtn.addEventListener('click', function () {
    selectionMode = !selectionMode;
    updateSelectionUI();
});

document.querySelectorAll('.single-delete-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        if (selectionMode) return;

        const id = this.getAttribute('data-id');
        if (!id) return;

        if (confirm('Tem a certeza que deseja remover esta tarefa?')) {
            deleteModeInput.value = 'single';
            singleDeleteIdInput.value = id;
            mainForm.submit();
        }
    });
});

// EXTRA: tradução de cor
document.addEventListener('DOMContentLoaded', function () {
    const enToPt = {
        green:'verde', lime:'verde', red:'vermelho', blue:'azul',
        yellow:'amarelo', orange:'laranja', purple:'roxo',
        gray:'cinzento', grey:'cinzento', black:'preto', white:'branco',
        brown:'castanho', pink:'rosa', deeppink:'rosa', crimson:'vermelho',
        cyan:'ciano', teal:'verde-azulado', navy:'azul-escuro',
        gold:'dourado', silver:'prateado', skyblue:'azul-céu',
        lightgreen:'verde-claro', darkred:'vermelho-escuro',
        darkgreen:'verde-escuro'
    };
    const ptToEn = {
        verde:'green', vermelho:'red', azul:'blue', amarelo:'yellow',
        laranja:'orange', roxo:'purple', cinzento:'gray', cinza:'gray',
        preto:'black', branco:'white', castanho:'brown', rosa:'pink',
        dourado:'gold', prateado:'silver', ciano:'cyan'
    };
    const ptList = Object.keys(ptToEn);

    const cssColors = {
        black:"#000000", white:"#ffffff", red:"#ff0000", green:"#008000", blue:"#0000ff",
        lime:"#00ff00", yellow:"#ffff00", orange:"#ffa500", purple:"#800080", gray:"#808080",
        brown:"#8b4513", pink:"#ffc0cb", deeppink:"#ff1493", crimson:"#dc143c", cyan:"#00ffff",
        teal:"#008080", navy:"#000080", gold:"#ffd700", silver:"#c0c0c0", skyblue:"#87ceeb",
        lightgreen:"#90ee90", darkred:"#8b0000", darkgreen:"#006400"
    };

    function hexToRgb(hex) {
        hex = hex.replace('#','');
        if (hex.length === 3) {
            hex = hex.split('').map(c => c + c).join('');
        }
        const num = parseInt(hex, 16);
        return {
            r: (num >> 16) & 255,
            g: (num >> 8) & 255,
            b: num & 255
        };
    }

    function colorDistance(c1, c2) {
        const dr = c1.r - c2.r;
        const dg = c1.g - c2.g;
        const db = c1.b - c2.b;
        return dr*dr + dg*dg + db*db;
    }

    function closestCssColorName(hex) {
        const target = hexToRgb(hex);
        let bestName = null;
        let bestDist = Infinity;

        for (const [name, hexVal] of Object.entries(cssColors)) {
            const rgb = hexToRgb(hexVal);
            const d   = colorDistance(target, rgb);
            if (d < bestDist) {
                bestDist = d;
                bestName = name;
            }
        }
        return bestName;
    }

    function guessPtName(val) {
        const lower = val.trim().toLowerCase();
        if (!lower) return '';

        if (ptList.includes(lower)) return lower;
        if (enToPt[lower]) return enToPt[lower];

        if (/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(lower)) {
            const name = closestCssColorName(lower);
            if (name && enToPt[name]) return enToPt[name];
        }
        return val;
    }

    document.querySelectorAll('.state-card .state-color').forEach(function (span) {
        const rawText = span.textContent.trim();
        const ptName  = guessPtName(rawText);

        if (ptName && ptName !== rawText) {
            span.textContent = ptName;
        }
    });
});
</script>
</body>
</html>
