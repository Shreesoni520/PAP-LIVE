<?php
include './log.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$success = '';
$error   = '';

// remover árvores (single / multi)
if (!empty($_POST)) {
    if (isset($_POST['mode']) && $_POST['mode'] === 'multi' && !empty($_POST['delete_ids']) && is_array($_POST['delete_ids'])) {
        $ids = array_map('intval', $_POST['delete_ids']);

        if (count($ids) > 0) {
            $in    = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));

            $stmt = $conn->prepare("DELETE FROM intervencoes WHERE arvore_id IN ($in)");
            $stmt->bind_param($types, ...$ids);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM arvores WHERE id IN ($in)");
            $stmt->bind_param($types, ...$ids);

            if ($stmt->execute()) {
                $success = "Árvore(s) removida(s) com sucesso.";

                $userId = (int)$_SESSION['user_id'];
                foreach ($ids as $id) {
                    regista_log($conn, $userId, "remover", "arvore", $id, "Árvore apagada em remoção múltipla.");

                    $acao    = 'Remoção de árvore';
                    $detalhe = "Árvore ID $id removida, incluindo intervenções associadas.";

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
            } else {
                $error = "Erro ao remover árvores selecionadas!";
            }
            $stmt->close();
        }

    } elseif (isset($_POST['mode']) && $_POST['mode'] === 'single' && isset($_POST['delete_id'])) {
        $id = intval($_POST['delete_id']);

        $stmt = $conn->prepare("DELETE FROM intervencoes WHERE arvore_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM arvores WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $success = "Árvore removida com sucesso.";

            regista_log($conn, $_SESSION['user_id'], "remover", "arvore", $id, "Árvore apagada.");

            $userId  = (int)$_SESSION['user_id'];
            $acao    = 'Remoção de árvore';
            $detalhe = "Árvore ID $id removida, incluindo intervenções associadas.";

            $stmtAt = $conn->prepare("
                INSERT INTO atividade (user_id, acao, detalhe)
                VALUES (?, ?, ?)
            ");
            if ($stmtAt) {
                $stmtAt->bind_param("iss", $userId, $acao, $detalhe);
                $stmtAt->execute();
                $stmtAt->close();
            }

        } else {
            $error = "Erro ao remover árvore!";
        }
        $stmt->close();
    }

    header('Location: index.php?evora=removetree');
    exit();
}

/* filtros só para encher inputs (GET ainda usado no form) */
$f_especie = isset($_GET['especie']) ? trim($_GET['especie']) : '';
$f_tarefa  = isset($_GET['estado']) ? trim($_GET['estado']) : '';
$f_tipo    = isset($_GET['tipo']) ? trim($_GET['tipo']) : '';
$f_data    = isset($_GET['criado_em']) ? trim($_GET['criado_em']) : '';

/* carregar todas as árvores (client-side filter/pagination) */
$sqlTrees = "
    SELECT *
    FROM arvores
    ORDER BY criado_em DESC
";
$trees = $conn->query($sqlTrees);

/* valores únicos para filtros */
$tarefas_result = $conn->query("
    SELECT DISTINCT estado
    FROM arvores
    WHERE estado IS NOT NULL AND estado <> ''
    ORDER BY estado ASC
");

$tipos_result = $conn->query("
    SELECT DISTINCT tipo_intervencao
    FROM arvores
    WHERE tipo_intervencao IS NOT NULL AND tipo_intervencao <> ''
    ORDER BY tipo_intervencao ASC
");

$total_trees = $trees ? $trees->num_rows : 0;
$per_page    = 6;
$total_pages = $per_page > 0 ? (int)ceil($total_trees / $per_page) : 1;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Remover Árvores</title>
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
        #openFilterPanel,
        #toggleSelectMode {
            border-radius: 999px;
            padding: 0.4rem 1rem;
            font-size: 0.9rem;
        }
        .filter-panel {
            position: absolute;
            top: 70px;
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
        .filter-panel-actions {
            padding-top: 8px;
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
        }
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
            z-index: 1100;
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
        .tree-card-container {
            display: flex;
            margin-bottom: 16px;
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
            gap: 0.15rem;
        }
        .tree-card-footer {
            background-color: #f9fafb;
            padding: 0.45rem 1.1rem 0.55rem 1.1rem;
            font-size: 0.78rem;
            color: #6b7280;
            text-align: right;
            border-top: 1px solid #e5e7eb;
            flex-shrink: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: .75rem;
            white-space: nowrap;
        }
        .tree-card-footer span {
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .tree-title {
            font-weight: 800;
            font-size: 1rem;
            margin-bottom: 0.15rem;
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
            .ts-wrapper.single .ts-control {
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
            .page-content {
                position: relative;
                z-index: 1;
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
            <h3 class="mb-1">Remover árvores</h3>
            <p class="text-subtitle text-muted">Remova árvores do sistema.</p>
        </div>

        <div class="page-content">
            <section class="section">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php elseif ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="card card-main position-relative">
                    <div class="card-header border-0 pb-0">
                        <div class="card-header-flex">
                            <div>
                                <h4 class="mb-1">Árvores registadas</h4>
                                <small class="text-muted">Use o filtro ou ative o modo de seleção múltipla.</small>
                            </div>
                            <div class="d-flex align-items-center gap-2 ms-auto">
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
                        <form id="treeFilterForm" method="get" action="index.php">
                            <input type="hidden" name="evora" value="removetree">

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
                                <select id="filterTarefa" name="estado" class="form-select js-nice-select">
                                    <option value="">Todas</option>
                                    <?php if ($tarefas_result && $tarefas_result->num_rows > 0): ?>
                                        <?php while ($row = $tarefas_result->fetch_assoc()): ?>
                                            <?php if (!empty($row['estado'])):
                                                $val = strtolower($row['estado']);
                                                $sel = ($val === strtolower($f_tarefa)) ? 'selected' : '';
                                            ?>
                                                <option value="<?= htmlspecialchars($val) ?>" <?= $sel ?>>
                                                    <?= htmlspecialchars($row['estado']) ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="mb-2">
                                <label for="filterTipoIntervencao" class="form-label">Tipo de Intervenção</label>
                                <select id="filterTipoIntervencao" name="tipo" class="form-select js-nice-select">
                                    <option value="">Todos</option>
                                    <?php if ($tipos_result && $tipos_result->num_rows > 0): ?>
                                        <?php while ($row = $tipos_result->fetch_assoc()): ?>
                                            <?php if (!empty($row['tipo_intervencao'])):
                                                $val = strtolower($row['tipo_intervencao']);
                                                $sel = ($val === strtolower($f_tipo)) ? 'selected' : '';
                                            ?>
                                                <option value="<?= htmlspecialchars($val) ?>" <?= $sel ?>>
                                                    <?= htmlspecialchars($row['tipo_intervencao']) ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="mb-2">
                                <label for="filterCriadoEm" class="form-label">Criado em (data exata)</label>
                                <input
                                    type="date"
                                    class="form-control"
                                    id="filterCriadoEm"
                                    name="criado_em"
                                    value="<?= htmlspecialchars($f_data) ?>"
                                >
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

                    <form method="post" id="mainDeleteForm">
                        <input type="hidden" name="mode" id="deleteModeInput" value="single">
                        <input type="hidden" name="delete_id" id="singleDeleteId" value="">

                        <div class="card-body pt-3">
                            <?php if ($trees && $trees->num_rows > 0): ?>
                                <div class="container-fluid">
                                    <div class="row" id="treeList">
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
                                            <div class="col-12 col-md-6 col-xl-4 tree-card-container">
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
                                                        <div class="d-flex align-items-center gap-2 ms-auto">
                                                            <button
                                                                type="button"
                                                                class="btn btn-danger btn-sm single-delete-btn"
                                                                data-id="<?= (int)$tree['id'] ?>"
                                                            >
                                                                Remover
                                                            </button>

                                                            <div class="form-check mb-0 multi-checkbox-wrapper" style="display:none;">
                                                                <input
                                                                    class="form-check-input tree-checkbox"
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
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                </div>

                                <div class="mt-3 justify-content-end multi-actions" id="multiActions">
                                    <button type="submit" class="btn btn-danger"
                                            onclick="return confirm('Tem certeza que deseja remover as árvores selecionadas?');">
                                        Remover árvores selecionadas
                                    </button>
                                </div>

                                <?php if ($total_pages > 1): ?>
                                    <nav id="paginationNav" aria-label="Paginação de árvores" class="mt-3 mb-1">
                                        <ul class="pagination justify-content-center flex-wrap" id="paginationLinks"></ul>
                                    </nav>
                                <?php endif; ?>

                            <?php else: ?>
                                <div class="alert alert-info text-center mb-0">
                                    Nenhuma árvore registada.
                                </div>
                            <?php endif; ?>
                        </div>
                    </form>
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
const filterForm          = document.getElementById('treeFilterForm');

openFilterPanelBtn.addEventListener('click', function () {
    filterPanel.classList.toggle('d-none');
});

closeFilterPanelBtn.addEventListener('click', function () {
    filterPanel.classList.add('d-none');
});

clearFiltersBtn.addEventListener('click', function () {
    document.getElementById('filterEspecie').value  = '';
    document.getElementById('filterCriadoEm').value = '';

    const tarefaSel = document.getElementById('filterTarefa').tomselect;
    if (tarefaSel) tarefaSel.clear();
    const tipoSel   = document.getElementById('filterTipoIntervencao').tomselect;
    if (tipoSel) tipoSel.clear();

    applyFilters();
});

const especieInput = document.getElementById('filterEspecie');
const especieList  = document.getElementById('especieList');

function buildEspecieList() {
    const especies = new Set();
    document.querySelectorAll('.tree-search-especie').forEach(function (el) {
        const name = el.textContent.trim();
        if (name) especies.add(name);
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
});

const perPage         = <?= (int)$per_page ?>;
const cards           = Array.from(document.querySelectorAll('.tree-card-container'));
const filterTarefaEl  = document.getElementById('filterTarefa');
const filterTipoEl    = document.getElementById('filterTipoIntervencao');
const filterDataEl    = document.getElementById('filterCriadoEm');
const paginationNav   = document.getElementById('paginationNav');
const paginationLinks = document.getElementById('paginationLinks');

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
    const especie         = especieInput.value.trim().toLowerCase();
    const tarefa          = filterTarefaEl.value.trim().toLowerCase();
    const tipoIntervencao = filterTipoEl.value.trim().toLowerCase();
    const criadoEm        = filterDataEl.value;

    cards.forEach(function (card) {
        const textEspecie = (card.querySelector('.tree-search-especie')?.textContent || '').trim().toLowerCase();
        const textTarefa  = (card.querySelector('.tree-tarefa')?.textContent || '').trim().toLowerCase();
        const textTipo    = (card.querySelector('.tree-search-tipo')?.textContent || '').trim().toLowerCase();
        const footerText  = card.querySelector('.tree-card-footer')?.textContent || '';

        let textCriadoEm = '';
        const matchDate = footerText.match(/\d{4}-\d{2}-\d{2}/);
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

if (cards.length > 0) {
    applyFilters();
} else {
    if (paginationNav) paginationNav.style.display = 'none';
}

// modo seleção múltipla
const toggleSelectModeBtn = document.getElementById('toggleSelectMode');
const multiActions        = document.getElementById('multiActions');
const deleteModeInput     = document.getElementById('deleteModeInput');
const mainForm            = document.getElementById('mainDeleteForm');
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
        document.querySelectorAll('.tree-checkbox').forEach(chk => chk.checked = false);
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

        if (confirm('Tem certeza que deseja remover esta árvore?')) {
            deleteModeInput.value = 'single';
            singleDeleteIdInput.value = id;
            mainForm.submit();
        }
    });
});
</script>
</body>
</html>
