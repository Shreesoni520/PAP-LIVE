<?php
include './log.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$success = '';
$error   = '';

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

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

/* 1) LISTA DE TAREFAS (SELECIONAR PARA EDITAR) */
if ($id <= 0) {

    /* SEM LIMIT: para paginação client-side */
    $tarefas = [];
    $res = $conn->query("SELECT * FROM states ORDER BY id");
    while ($row = $res->fetch_assoc()) {
        $tarefas[] = $row;
    }

    /* PAGINAÇÃO VISUAL (client-side) */
    $total_states = count($tarefas);
    $per_page     = 6;
    $total_pages  = $per_page > 0 ? (int)ceil($total_states / $per_page) : 1;
    $page         = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    ?>
    <!DOCTYPE html>
    <html lang="pt">
    <head>
        <meta charset="UTF-8">
        <title>Selecionar Tarefa</title>
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
            .page-heading p { color: #6b7280; }

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

            /* Cards */
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
                <h3 class="mb-1">Selecionar Tarefa</h3>
                <p class="text-subtitle text-muted mb-0">Escolha uma tarefa para editar.</p>
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

                        <!-- Painel de filtros -->
                        <div id="filterPanel" class="filter-panel d-none">
                            <form id="stateFilterForm" method="get" action="index.php">
                                <input type="hidden" name="evora" value="editarestado">

                                <div class="mb-2">
                                    <label for="filterEstado" class="form-label">Tarefa</label>
                                    <select id="filterEstado" name="estado" class="form-select js-nice-select">
                                        <option value="">Todas</option>
                                        <?php if ($total_states > 0): ?>
                                            <?php
                                            $allStates = $conn->query("SELECT name FROM states ORDER BY name");
                                            while ($e = $allStates->fetch_assoc()):
                                            ?>
                                                <option value="<?= htmlspecialchars($e['name']) ?>">
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
                        <!-- Fim painel filtros -->

                        <div class="card-body">
                            <?php if (!empty($tarefas)): ?>
                                <div class="container-fluid">
                                    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3" id="stateList">
                                        <?php foreach ($tarefas as $estado): ?>
                                            <?php
                                                $storedColor = $estado['color_name'] ?? '';
                                                $ptNameCard  = traduz_cor_en_para_pt($storedColor) ?? $storedColor;
                                                $cssColor    = traduz_cor_pt_para_en($ptNameCard) ?? $storedColor;
                                            ?>
                                            <div class="col state-card-container">
                                                <div class="state-card">
                                                    <div class="state-card-body">
                                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                                            <div class="state-title state-search-nome">
                                                                <?= htmlspecialchars($estado['name']) ?>
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
                                                                <?= htmlspecialchars($storedColor) ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="state-card-footer">
                                                        <span>Tarefa</span>
                                                        <a
                                                            href="index.php?evora=editarestado&id=<?= (int)$estado['id'] ?>"
                                                            class="btn btn-primary btn-sm"
                                                        >
                                                            Editar
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Paginação visual (server + client side helper) -->
                                <?php if ($total_pages > 1): ?>
                                    <nav id="paginationNav" aria-label="Paginação de tarefas" class="mt-3 mb-1">
                                        <ul class="pagination justify-content-center flex-wrap">
                                            <?php
                                            $baseQuery = $_GET;
                                            $baseQuery['evora'] = 'editarestado';
                                            ?>
                                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                                <?php
                                                $baseQuery['page'] = max(1, $page - 1);
                                                $prevQs = http_build_query($baseQuery);
                                                ?>
                                                <a class="page-link"
                                                   href="index.php?<?= $prevQs ?>">
                                                    « Anterior
                                                </a>
                                            </li>

                                            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                                                <?php
                                                $baseQuery['page'] = $p;
                                                $pQs = http_build_query($baseQuery);
                                                ?>
                                                <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                                                    <a class="page-link"
                                                       href="index.php?<?= $pQs ?>">
                                                        <?= $p ?>
                                                    </a>
                                                </li>
                                            <?php endfor; ?>

                                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                                <?php
                                                $baseQuery['page'] = min($total_pages, $page + 1);
                                                $nextQs = http_build_query($baseQuery);
                                                ?>
                                                <a class="page-link"
                                                   href="index.php?<?= $nextQs ?>">
                                                    Próxima »
                                                </a>
                                            </li>
                                        </ul>
                                    </nav>
                                <?php endif; ?>

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
    // === FILTRO: abrir/fechar painel ===
    const openFilterPanelBtn = document.getElementById('openFilterPanel');
    const filterPanel        = document.getElementById('filterPanel');
    const closeFilterPanelBtn= document.getElementById('closeFilterPanel');
    const clearFiltersBtn    = document.getElementById('clearFilters');
    const filterForm         = document.getElementById('stateFilterForm');

    openFilterPanelBtn.addEventListener('click', function () {
        filterPanel.classList.toggle('d-none');
    });

    closeFilterPanelBtn.addEventListener('click', function () {
        filterPanel.classList.add('d-none');
    });

    document.addEventListener('click', function (e) {
        if (!filterPanel.classList.contains('d-none')) {
            if (!filterPanel.contains(e.target) && !openFilterPanelBtn.contains(e.target)) {
                filterPanel.classList.add('d-none');
            }
        }
    });

    clearFiltersBtn.addEventListener('click', function () {
        document.getElementById('filterEstado').value = '';
        if (document.getElementById('filterEstado').tomselect) {
            document.getElementById('filterEstado').tomselect.clear();
        }
        filterForm.querySelectorAll('input[name="page"]').forEach(i => i.remove());
        applyFilters();
    });

    // === CLIENT-SIDE FILTER + PAGINAÇÃO VISUAL ===
    const paginationNav = document.getElementById('paginationNav');
    const perPage       = <?= (int)$per_page ?>;
    const cards         = Array.from(document.querySelectorAll('.state-card-container'));
    const filterEstadoEl= document.getElementById('filterEstado');

    function applyFilters() {
        const estadoSel = filterEstadoEl.value.trim().toLowerCase();
        const anyFilterActive = !!estadoSel;

        cards.forEach(card => {
            const textNome = (card.querySelector('.state-search-nome')?.textContent || '')
                .trim().toLowerCase();

            const matchesEstado = !estadoSel || textNome === estadoSel;

            card.style.display   = matchesEstado ? '' : 'none';
            card.style.visibility= '';
            card.style.position  = '';
        });

        if (paginationNav) {
            paginationNav.style.display = anyFilterActive ? 'none' : '';
        }

        if (!anyFilterActive) {
            showPage(1);
        }
    }

    function showPage(page) {
        let visibleIndex = 0;
        const start = (page - 1) * perPage;
        const end   = start + perPage;

        cards.forEach(card => {
            if (card.style.display === 'none') {
                card.style.visibility = 'hidden';
                card.style.position   = 'absolute';
                return;
            }

            if (visibleIndex >= start && visibleIndex < end) {
                card.style.visibility = '';
                card.style.position   = '';
            } else {
                card.style.visibility = 'hidden';
                card.style.position   = 'absolute';
            }
            visibleIndex++;
        });
    }

    // submit do filtro
    filterForm.addEventListener('submit', function (e) {
        e.preventDefault();
        applyFilters();
        filterPanel.classList.add('d-none');
    });

    // páginas clicadas
    if (paginationNav) {
        document.querySelectorAll('#paginationNav .page-link').forEach(a => {
            const url   = new URL(a.href, window.location.href);
            const pageQ = parseInt(url.searchParams.get('page') || '1', 10);
            a.dataset.page = pageQ;
            a.addEventListener('click', function (e) {
                e.preventDefault();
                if (paginationNav && paginationNav.style.display === 'none') return;
                showPage(pageQ);
            });
        });
    }

    // inicial
    applyFilters();

    // EXTRA: fechar filtro quando abrir o menu (mobile)
    const burgerBtn = document.querySelector('.burger-btn');
    if (burgerBtn && filterPanel) {
        burgerBtn.addEventListener('click', () => {
            filterPanel.classList.add('d-none');
        });
    }

    // === Tom Select + tratamento de cores ===
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
                const d = colorDistance(target, rgb);
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
    <?php
    exit();
}

/* 2) PROCESSAR EDIÇÃO */
if (isset($_POST['edit_estado'])) {
    $name        = trim($_POST['name'] ?? '');
    $color_input = trim($_POST['color_name'] ?? '');

    if ($name !== '') {

        $color_lower = mb_strtolower($color_input);
        $pt_from_en  = traduz_cor_en_para_pt($color_lower);
        if ($pt_from_en !== null) {
            $color_css = $pt_from_en;
        } else {
            $color_css = $color_input;
        }

        $stmt = $conn->prepare("UPDATE states SET name = ?, color_name = ? WHERE id = ?");
        if ($stmt === false) {
            $error = "Erro na preparação da query: " . $conn->error;
        } else {
            $stmt->bind_param("ssi", $name, $color_css, $id);
            if ($stmt->execute()) {
                $success = "Tarefa atualizada!";

                regista_log(
                    $conn,
                    $_SESSION['user_id'],
                    "editar",
                    "estado",
                    $id,
                    "Novo nome: $name, cor digitada: $color_input, guardada: $color_css"
                );

                $userId  = $_SESSION['user_id'];
                $acao    = 'Tarefa atualizada';
                $detalhe = "ID: $id · Nome: $name · Cor digitada: $color_input · Guardada: $color_css";

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

                header("Location: index.php?evora=editarestado");
                exit();
            } else {
                $error = "Erro: " . $stmt->error;
                $stmt->close();
            }
        }
    } else {
        $error = "O nome da tarefa é obrigatório.";
    }
}

/* 3) CARREGAR TAREFA A EDITAR */
$stmt = $conn->prepare("SELECT * FROM states WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$estado = $result->fetch_assoc();
$stmt->close();

if (!$estado) {
    ?>
    <!DOCTYPE html>
    <html lang="pt">
    <head>
        <meta charset="UTF-8">
        <title>Tarefa não encontrada</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="assets/css/bootstrap.css">
        <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
        <link rel="stylesheet" href="assets/css/app.css">
        <style>
            body, .sidebar, .card, .btn, h4, h3, h2 {
                font-family: 'Nunito', sans-serif !important;
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
                <section class="section">
                    <div class="alert alert-danger shadow mt-5 mx-auto" style="max-width: 500px;">
                        Tarefa não encontrada.
                        <a href="index.php?evora=editarestado" class="btn btn-sm btn-primary ms-2">
                            Selecionar Tarefa
                        </a>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    </body>
    </html>
    <?php
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Editar Tarefa</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon">

    <style>
        body, .sidebar, .card, .btn, h4, h3, h2 {
            font-family: 'Nunito', sans-serif !important;
        }
        .page-content {
            background-color: transparent;
        }
        .page-heading { margin-bottom: 20px; }
        .edit-estado-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
            border: 1px solid #e5e7eb;
        }
        .field-label {
            font-weight: 600;
            color: #4b5563;
            font-size: 0.9rem;
        }
        .btn-main {
            font-weight: 600;
            letter-spacing: .02em;
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
        }
        .hc-color-card {
            display: flex;
            align-items: center;
            width: 100%;
            padding: 0.55rem 0.85rem;
            border-radius: 14px;
            border: 1px solid #e5e7eb;
            background: #ffffff;
            box-shadow: 0 10px 24px rgba(15,23,42,0.08);
            cursor: pointer;
            transition: box-shadow 0.16s ease, transform 0.16s ease, border-color 0.16s ease, background-color 0.16s ease;
        }
        .hc-color-card:hover {
            box-shadow: 0 18px 36px rgba(15,23,42,0.14);
            transform: translateY(-1px);
            border-color: #d1d5db;
            background-color: #fff5f1;
        }
        .hc-color-preview {
            width: 40px;
            height: 32px;
            border-radius: 999px;
            border: 1px solid rgba(15,23,42,0.12);
            box-shadow: 0 4px 10px rgba(15,23,42,0.25);
            background: #F54927;
            flex-shrink: 0;
        }
        .hc-color-main {
            display: flex;
            align-items: baseline;
            gap: 0.4rem;
            margin-left: 0.75rem;
        }
        .hc-color-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #9ca3af;
        }
        .hc-color-value {
            font-size: 0.95rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            color: #111827;
        }
        .hc-color-meta {
            margin-left: auto;
            font-size: 0.85rem;
            font-weight: 600;
            color: #4b5563;
        }
        .hc-color-input {
            max-width: 0;
            max-height: 0;
            opacity: 0;
            padding: 0;
            border: 0;
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

        <div class="page-content d-flex justify-content-center">
            <div style="width: 100%; max-width: 520px;">

                <div class="page-heading">
                    <h3>Editar Tarefa</h3>
                    <p class="text-subtitle text-muted mb-0">
                        Altere o nome e a cor da tarefa selecionada.
                    </p>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php elseif ($error): ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <div class="card edit-estado-card shadow-sm">
                    <div class="card-body">
                        <form method="post" autocomplete="off">
                            <div class="mb-3">
                                <label for="nome" class="field-label mb-1">
                                    <i class="bi bi-card-text me-1"></i> Nome da Tarefa
                                </label>
                                <input
                                    type="text"
                                    name="name"
                                    id="nome"
                                    class="form-control"
                                    required
                                    value="<?= htmlspecialchars($estado['name']) ?>"
                                >
                            </div>

                            <div class="mb-3">
                                <label class="field-label mb-1">
                                    <i class="bi bi-palette me-1"></i> Cor da Tarefa
                                </label>
                                <?php
                                    $storedColor         = $estado['color_name'] ?? '';
                                    $hexDefault          = '#F54927';
                                    $cssColorForPicker   = $hexDefault;

                                    $enFromPt = traduz_cor_pt_para_en($storedColor);
                                    if ($enFromPt) {
                                        $cssColorForPicker = $enFromPt;
                                    } elseif (preg_match('/^[0-9a-f]{3,6}$/i', $storedColor)) {
                                        $cssColorForPicker = '#' . (strlen($storedColor) === 3 ? $storedColor . $storedColor : $storedColor);
                                    } elseif ($storedColor) {
                                        $cssColorForPicker = $storedColor;
                                    }

                                    $ptInitLabel = traduz_cor_en_para_pt($storedColor) ?? $storedColor;
                                ?>

                                <div class="d-flex flex-column gap-2">
                                    <div id="colorBoxTrigger" class="hc-color-card">
                                        <div class="hc-color-preview" id="colorBoxPreview"></div>

                                        <div class="hc-color-main">
                                            <span class="hc-color-label">HEX</span>
                                            <span class="hc-color-value" id="colorBoxHex">
                                                <?= strtoupper(htmlspecialchars($cssColorForPicker)) ?>
                                            </span>
                                        </div>

                                        <div class="hc-color-meta" id="corlabel">
                                            <?= htmlspecialchars($ptInitLabel ?: 'Sem nome') ?>
                                        </div>
                                    </div>

                                    <input
                                        type="color"
                                        id="corpicker"
                                        name="color_name"
                                        value="<?= htmlspecialchars($cssColorForPicker) ?>"
                                        class="hc-color-input"
                                        title="Escolher cor"
                                    >
                                </div>
                            </div>

                            <button type="submit" name="edit_estado" class="btn btn-primary btn-main w-100">
                                Guardar alterações
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const inputPicker = document.getElementById('corpicker');
    const labelSpan   = document.getElementById('corlabel');
    const boxTrigger  = document.getElementById('colorBoxTrigger');
    const boxPreview  = document.getElementById('colorBoxPreview');
    const boxHex      = document.getElementById('colorBoxHex');

    const cssColors = {
        black: '000000', white: 'ffffff', red: 'ff0000', green: '008000', blue: '0000ff',
        lime: '00ff00', yellow: 'ffff00', orange: 'ffa500', purple: '800080', gray: '808080',
        brown: '8b4513', pink: 'ffc0cb', deeppink: 'ff1493', crimson: 'dc143c', cyan: '00ffff',
        teal: '008080', navy: '000080', gold: 'ffd700', silver: 'c0c0c0', skyblue: '87ceeb',
        lightgreen: '90ee90', darkred: '8b0000', darkgreen: '006400'
    };

    const enToPt = {
        green: 'verde', lime: 'verde', red: 'vermelho', blue: 'azul',
        yellow: 'amarelo', orange: 'laranja', purple: 'roxo',
        gray: 'cinzento', grey: 'cinzento', black: 'preto', white: 'branco',
        brown: 'castanho', pink: 'rosa', deeppink: 'rosa', crimson: 'vermelho',
        cyan: 'ciano', teal: 'verde-azulado', navy: 'azul-escuro',
        gold: 'dourado', silver: 'prateado', skyblue: 'azul-claro',
        lightgreen: 'verde-claro', darkred: 'vermelho-escuro',
        darkgreen: 'verde-escuro'
    };

    function hexToRgb(hex) {
        hex = hex.replace('#', '');
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
        const dr = c1.r - c2.r, dg = c1.g - c2.g, db = c1.b - c2.b;
        return dr*dr + dg*dg + db*db;
    }

    function closestCssColorName(hex) {
        const target = hexToRgb(hex);
        let bestName = null, bestDist = Infinity;
        Object.entries(cssColors).forEach(([name, hexVal]) => {
            const rgb = hexToRgb(hexVal);
            const d   = colorDistance(target, rgb);
            if (d < bestDist) {
                bestDist = d;
                bestName = name;
            }
        });
        return bestName;
    }

    function guessPtName(val) {
        const lower = val.trim().toLowerCase();
        if (!lower) return 'Sem nome';

        const hexRegex = /^#?[0-9a-f]{3,6}$/i;
        if (hexRegex.test(lower)) {
            const normalized = lower.startsWith('#') ? lower : '#' + lower;
            const name = closestCssColorName(normalized);
            if (name && enToPt[name]) return enToPt[name];
            return normalized.toUpperCase();
        }

        return lower;
    }

    function updateFromPicker() {
        if (!inputPicker) return;
        const val = inputPicker.value || '#F54927';
        boxPreview.style.backgroundColor = val;
        boxHex.textContent = val.toUpperCase();
        labelSpan.textContent = guessPtName(val);
    }

    if (boxTrigger && inputPicker) {
        boxTrigger.addEventListener('click', function () {
            inputPicker.click();
        });
        inputPicker.addEventListener('input', updateFromPicker);
        updateFromPicker();
    }
});
</script>
</body>
</html>
