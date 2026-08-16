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

$intervencoes  = ['Corte', 'Poda'];
$valid_species = ['Carvalho', 'Oliveira', 'Pinheiro', 'Plátano', 'Jacarandá', 'Loureiro'];

$tarefas     = [];
$tarefas_sql = $conn->query("SELECT name FROM states");
if ($tarefas_sql && $tarefas_sql->num_rows > 0) {
    while ($row = $tarefas_sql->fetch_assoc()) {
        if (!empty($row['name'])) {
            $tarefas[] = $row['name'];
        }
    }
}

/* PARTE 1 – SELECIONAR ÁRVORE */
if ($id <= 0) {

    $sqlArvores = "
        SELECT *
        FROM arvores
        ORDER BY criado_em DESC
    ";
    $arvores = $conn->query($sqlArvores);

    $total_arvores = $arvores ? $arvores->num_rows : 0;
    $per_page      = 6;
    $total_pages   = $per_page > 0 ? (int)ceil($total_arvores / $per_page) : 1;
    ?>
    <!DOCTYPE html>
    <html lang="pt">
    <head>
        <meta charset="UTF-8">
        <title>Selecionar Árvore</title>
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
                border-top: 1px solid #e5e7eb;
                display: flex;
                align-items: center;
                gap: 0.75rem;
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

            .ts-wrapper .ts-control {
                background-color: #ffffff !important;
                border-radius: 10px;
                padding: 0.375rem 0.75rem;
                border-color: #d1d5db;
            }
            .ts-wrapper .ts-control:focus {
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
                .ts-wrapper .ts-control {
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
                <h3 class="mb-1">Selecionar árvore</h3>
                <p class="text-subtitle text-muted">Escolha uma árvore para editar.</p>
            </div>

            <div class="page-content">
                <section class="section">
                    <div class="card card-main position-relative">
                        <div class="card-header border-0 pb-0">
                            <div class="card-header-flex">
                                <div>
                                    <h4 class="mb-1">Árvores disponíveis</h4>
                                    <small class="text-muted">Use o filtro para encontrar mais rápido.</small>
                                </div>

                                <div class="d-flex justify-content-end flex-grow-1">
                                    <button id="openFilterPanel"
                                            class="btn btn-outline-secondary d-flex align-items-center ms-auto"
                                            type="button">
                                        <i class="bi bi-funnel-fill me-1"></i>
                                        <span>Filtrar</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div id="filterPanel" class="filter-panel d-none">
                            <form id="treeFilterForm">
                                <div class="mb-2 especie-wrapper">
                                    <label for="filterEspecie" class="form-label">Espécie</label>
                                    <input type="text" class="form-control" id="filterEspecie" placeholder="Nome da espécie">
                                    <ul id="especieList"></ul>
                                </div>

                                <div class="mb-2">
                                    <label for="filterTarefa" class="form-label">Tarefa</label>
                                    <select id="filterTarefa" class="form-select js-nice-select">
                                        <option value="">Todas</option>
                                        <?php foreach ($tarefas as $tarefa_opt): ?>
                                            <option value="<?= htmlspecialchars(strtolower($tarefa_opt)) ?>">
                                                <?= htmlspecialchars($tarefa_opt) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-2">
                                    <label for="filterTipoIntervencao" class="form-label">Tipo de Intervenção</label>
                                    <select id="filterTipoIntervencao" class="form-select js-nice-select">
                                        <option value="">Todos</option>
                                        <?php foreach ($intervencoes as $interv): ?>
                                            <option value="<?= htmlspecialchars(strtolower($interv)) ?>">
                                                <?= htmlspecialchars($interv) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-2">
                                    <label for="filterCriadoEm" class="form-label">Criado em (data exata)</label>
                                    <input type="date" class="form-control" id="filterCriadoEm">
                                </div>

                                <div class="filter-panel-actions">
                                    <button type="button" class="btn btn-light btn-sm" id="clearFilters">Limpar</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="closeFilterPanel">Cancelar</button>
                                    <button type="submit" class="btn btn-primary btn-sm">Aplicar</button>
                                </div>
                            </form>
                        </div>

                        <div class="card-body pt-3">
                            <?php if ($arvores && $arvores->num_rows > 0): ?>
                                <div class="container-fluid">
                                    <div class="row" id="treeList">
                                        <?php while ($tree = $arvores->fetch_assoc()): ?>
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
                                                            <span class="tree-value"><?= htmlspecialchars($tree['place_name']) ?></span>
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
                                                            <span class="tree-value tree-search-tipo"><?= htmlspecialchars($tree['tipo_intervencao']) ?></span>
                                                        </div>
                                                        <div class="tree-line">
                                                            <span class="tree-label">Tarefa:</span>
                                                            <span class="tree-value tree-tarefa"><?= htmlspecialchars($tree['estado']) ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="tree-card-footer">
                                                        <span>Criado em: <?= htmlspecialchars($criadoText) ?></span>
                                                        <a href="index.php?evora=editartree&id=<?= (int)$tree['id'] ?>"
                                                           class="btn btn-sm btn-primary ms-auto">
                                                            Editar
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
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
                    </div>
                </section>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
    });

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
        filterForm.reset();
        document.querySelectorAll('.js-nice-select').forEach(function (el) {
            if (el.tomselect) el.tomselect.clear();
        });
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

    const perPage         = <?= (int)$per_page ?>;
    const cards           = Array.from(document.querySelectorAll('.tree-card-container'));
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
        const especie         = document.getElementById('filterEspecie').value.trim().toLowerCase();
        const tarefa          = document.getElementById('filterTarefa').value.trim().toLowerCase();
        const tipoIntervencao = document.getElementById('filterTipoIntervencao').value.trim().toLowerCase();
        const criadoEm        = document.getElementById('filterCriadoEm').value;

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

    const burgerBtn = document.querySelector('.burger-btn');
    if (burgerBtn && filterPanel) {
        burgerBtn.addEventListener('click', () => {
            filterPanel.classList.add('d-none');
        });
    }
    </script>
    </body>
    </html>
    <?php
    exit();
}

/* PARTE 2 – EDITAR ÁRVORE */
$stmt = $conn->prepare("SELECT * FROM arvores WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res  = $stmt->get_result();
$tree = $res->fetch_assoc();
$stmt->close();

if (!$tree) {
    ?>
    <!DOCTYPE html>
    <html lang="pt">
    <head>
        <meta charset="UTF-8" />
        <title>Árvore não encontrada</title>
        <link rel="stylesheet" href="assets/css/bootstrap.css" />
        <link rel="stylesheet" href="assets/css/app.css" />
    </head>
    <body>
    <div class="container mt-4">
        <div class="alert alert-danger mt-5 mx-auto text-center" style="max-width:500px;">
            Árvore não encontrada.
            <a href="index.php?evora=editartree" class="btn btn-primary btn-sm ms-2">Selecionar Árvore</a>
        </div>
    </div>
    </body>
    </html>
    <?php
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_tree'])) {
    $species          = $_POST['species'] ?? null;
    $latitude         = $_POST['latitude'] ?? null;
    $longitude        = $_POST['longitude'] ?? null;
    $tipo_intervencao = $_POST['tipo_intervencao'] ?? null;
    $tarefa           = $_POST['tarefa'] ?? null;
    $place_name       = $_POST['place_name'] ?? null;

    if ($species && $latitude && $longitude && $tarefa && $place_name) {
        if (!in_array($species, $valid_species)) {
            $error = "Espécie da Árvore inválida!";
        } else {
            if (!is_numeric($latitude) || !is_numeric($longitude)) {
                $error = "Latitude e Longitude devem ser números válidos!";
            } elseif ($tipo_intervencao && !in_array($tipo_intervencao, $intervencoes)) {
                $error = "Tipo de intervenção inválido!";
            } elseif (!in_array($tarefa, $tarefas)) {
                $error = "Tarefa inválida!";
            } else {
                if (!$tipo_intervencao) {
                    $tipo_intervencao = "Nenhuma";
                }

                $sp = $species;

                $completedAt = null;
                if ($tarefa === 'Concluída') {
                    if (!empty($tree['completed_at'])) {
                        $completedAt = $tree['completed_at'];
                    } else {
                        $completedAt = date('Y-m-d H:i:s');
                    }
                }

                $stmt = $conn->prepare(
                    "UPDATE arvores
                     SET especie = ?,
                         latitude = ?,
                         longitude = ?,
                         place_name = ?,
                         tipo_intervencao = ?,
                         estado = ?,
                         completed_at = ?
                     WHERE id = ?"
                );
                $stmt->bind_param(
                    "sddssssi",
                    $sp,
                    $latitude,
                    $longitude,
                    $place_name,
                    $tipo_intervencao,
                    $tarefa,
                    $completedAt,
                    $id
                );

                if ($stmt->execute()) {
                    regista_log($conn, $_SESSION['user_id'], "editar", "arvore", $id, "Espécie: $sp");

                    $userId  = $_SESSION['user_id'];
                    $acao    = 'Árvore atualizada';
                    $detalhe = "ID: $id · Espécie: $sp · Local: $place_name · Tarefa: $tarefa · Tipo: $tipo_intervencao";

                    $stmtAt = $conn->prepare("
                        INSERT INTO atividade (user_id, acao, detalhe)
                        VALUES (?, ?, ?)
                    ");
                    if ($stmtAt) {
                        $stmtAt->bind_param("iss", $userId, $acao, $detalhe);
                        $stmtAt->execute();
                        $stmtAt->close();
                    }

                    $stmt->close();

                    header("Location: index.php?evora=editartree");
                    exit();
                } else {
                    $error = "Erro ao atualizar árvore: " . $stmt->error;
                }
            }
        }
    } else {
        $error = "Todos os campos são obrigatórios!";
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Editar Árvore</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/vendors/simple-datatables/style.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/css/tom-select.bootstrap5.min.css">

    <style>
        html, body { height: 100%; }
        body {
            font-family: 'Nunito', sans-serif !important;
            margin: 0;
            background: linear-gradient(135deg, #eef2ff, #f9fafb);
        }
        #app, #main { min-height: 100%; }
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
            font-size: 0.95rem;
            color: #6b7280;
            margin-bottom: 0;
        }
        .edit-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
            padding: 20px 20px 18px 20px;
            border: 1px solid #e5e7eb;
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
            color: #4b5563;
            font-size: 0.9rem;
        }
        .form-control,
        .form-select {
            border-radius: 10px;
        }
        .edit-two-cols {
            display: grid;
            grid-template-columns: 1.1fr 1.1fr;
            gap: 18px;
        }
        .edit-map-wrapper {
            margin-top: 8px;
            border-radius: 14px;
            background: #f9fafb;
            padding: 10px;
            border: 1px solid #e5e7eb;
        }
        #map {
            height: 330px;
            width: 100%;
            border-radius: 10px;
        }
        .edit-map-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }
        .edit-map-info {
            font-size: 0.8rem;
            color: #6b7280;
        }
        .btn-main {
            font-weight: 600;
            letter-spacing: .02em;
        }
        .actions-row-desktop {
            margin-top: 16px;
        }
        .btn-search-round {
            border-radius: 999px;
            padding-inline: 0.7rem;
            border-color: #d1d5db;
            background-color: #f9fafb;
        }
        .btn-search-round i { font-size: 1rem; }

        .ts-wrapper .ts-control {
            background-color: #ffffff !important;
            border-radius: 10px;
            padding: 0.375rem 0.75rem;
            border-color: #d1d5db;
        }
        .ts-wrapper .ts-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(37,99,235,0.25);
            border-color: #2563eb;
        }
        .ts-dropdown { background-color: #ffffff !important; }

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
        }
        @media (max-width: 768px) {
            .edit-card { padding: 16px 14px 16px 14px; }
            .edit-two-cols { grid-template-columns: 1fr; }
            .actions-row-desktop { margin-top: 10px; }
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
                    <h3>Espaço Verde</h3>
                    <p>Atualize os dados da árvore selecionada, o local e a posição no mapa.</p>
                </div>

                <div class="edit-card">
                    <?php if ($error): ?>
                        <div class="alert alert-danger mb-3"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="post" autocomplete="off">
                        <div class="edit-two-cols mb-3">
                            <div>
                                <div class="section-label">Dados principais</div>

                                <div class="mb-3">
                                    <label class="field-label mb-1">Espécie da Árvore</label>
                                    <select
                                        name="species"
                                        id="species"
                                        class="form-select js-nice-select"
                                        required
                                    >
                                        <option value="" disabled <?= $tree['especie'] ? '' : 'selected' ?>>Selecione a espécie</option>
                                        <?php foreach ($valid_species as $sp) { ?>
                                            <option
                                                value="<?= htmlspecialchars($sp) ?>"
                                                <?= ($tree['especie'] == $sp) ? 'selected' : '' ?>
                                            >
                                                <?= htmlspecialchars($sp) ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="field-label mb-1">Nome do Espaço</label>
                                    <div class="input-group">
                                        <input
                                            type="text"
                                            id="place_name"
                                            name="place_name"
                                            class="form-control"
                                            value="<?= htmlspecialchars($tree['place_name']) ?>"
                                            required
                                            placeholder="Escreva o local (rua, código postal, cidade...)"
                                        >
                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary btn-search-round"
                                            id="search-location"
                                            title="Pesquisar localização"
                                        >
                                            <i class="bi bi-search"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="section-label">Intervenção e tarefa</div>

                                <div class="mb-3">
                                    <label class="field-label mb-1">Tipo de Intervenção</label>
                                    <select name="tipo_intervencao" class="form-select js-nice-select">
                                        <option value="">Nenhuma</option>
                                        <?php foreach ($intervencoes as $interv) { ?>
                                            <option
                                                value="<?= htmlspecialchars($interv) ?>"
                                                <?= ($tree['tipo_intervencao'] == $interv ? 'selected' : '') ?>
                                            >
                                                <?= htmlspecialchars($interv) ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>

                                <div class="mb-0">
                                    <label class="field-label mb-1">Tarefa</label>
                                    <select name="tarefa" class="form-select js-nice-select" required>
                                        <option value="">Nenhuma</option>
                                        <?php foreach ($tarefas as $tarefa_opt) { ?>
                                            <option
                                                value="<?= htmlspecialchars($tarefa_opt) ?>"
                                                <?= ($tree['estado'] == $tarefa_opt ? 'selected' : '') ?>
                                            >
                                                <?= htmlspecialchars($tarefa_opt) ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>

                                <div class="actions-row-desktop">
                                    <button type="submit" name="edit_tree" class="btn btn-primary btn-main w-100">
                                        <i class="bi bi-check-lg me-1"></i> Guardar Alterações
                                    </button>
                                </div>
                            </div>

                            <div>
                                <div class="section-label">Mapa e coordenadas</div>

                                <div class="row mb-2">
                                    <div class="col-6">
                                        <label class="field-label mb-1">Latitude</label>
                                        <input
                                            type="text"
                                            id="latitude"
                                            name="latitude"
                                            class="form-control"
                                            value="<?= htmlspecialchars($tree['latitude']) ?>"
                                            required
                                            readonly
                                        >
                                    </div>
                                    <div class="col-6">
                                        <label class="field-label mb-1">Longitude</label>
                                        <input
                                            type="text"
                                            id="longitude"
                                            name="longitude"
                                            class="form-control"
                                            value="<?= htmlspecialchars($tree['longitude']) ?>"
                                            required
                                            readonly
                                        >
                                    </div>
                                </div>

                                <div class="edit-map-wrapper">
                                    <div class="edit-map-top">
                                        <span class="edit-map-info">
                                            Clique no mapa ou arraste o marcador para ajustar a posição.
                                        </span>
                                    </div>
                                    <div id="map"></div>
                                </div>
                            </div>
                        </div>
                    </form>

                </div>

            </div>
        </div>

    </div>
</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

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
});

const evora = [
    <?= $tree['latitude'] ? htmlspecialchars($tree['latitude']) : '38.5667' ?>,
    <?= $tree['longitude'] ? htmlspecialchars($tree['longitude']) : '-7.9' ?>
];

const map = L.map('map', { attributionControl: false }).setView(evora, 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

let marker;

function setMarkerPos(lat, lng) {
    if (marker) {
        marker.setLatLng([lat, lng]);
    } else {
        marker = L.marker([lat, lng], { draggable: true }).addTo(map);
        marker.on('dragend', function() {
            const pos = marker.getLatLng();
            setFormFields(pos.lat.toFixed(6), pos.lng.toFixed(6));
            fetchPlaceName(pos.lat, pos.lng);
        });
    }
    map.setView([lat, lng], 16);
}

function setFormFields(lat, lng) {
    document.getElementById('latitude').value = lat;
    document.getElementById('longitude').value = lng;
}

function fetchPlaceName(lat, lng) {
    fetch(`reverse_proxy.php?lat=${lat}&lon=${lng}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('place_name').value = data.display_name || '';
        })
        .catch(() => alert("Erro ao pedir ao servidor (reverse_proxy.php)!"));
}

setMarkerPos(evora[0], evora[1]);

map.on('click', function(e) {
    const lat = e.latlng.lat.toFixed(6);
    const lng = e.latlng.lng.toFixed(6);
    setFormFields(lat, lng);
    fetchPlaceName(lat, lng);
    setMarkerPos(lat, lng);
});

document.getElementById('search-location').addEventListener('click', function() {
    const name = document.getElementById('place_name').value;
    if (!name.trim()) {
        alert('Escreva um nome, cidade ou código postal.');
        return;
    }
    fetch(`nominatim_proxy.php?q=${encodeURIComponent(name)}`)
        .then(response => response.json())
        .then(data => {
            if (data && data.length > 0) {
                const lat = parseFloat(data[0].lat).toFixed(6);
                const lng = parseFloat(data[0].lon).toFixed(6);
                setFormFields(lat, lng);
                setMarkerPos(lat, lng);
            } else {
                alert('Localização não encontrada!');
            }
        })
        .catch(() => alert('Erro ao pesquisar localização!'));
});

document.getElementById('place_name').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        document.getElementById('search-location').click();
        e.preventDefault();
    }
});
</script>
</body>
</html>