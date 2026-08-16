<?php
include './log.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$success = '';
$error   = '';

// SINGLE + MULTI DELETE (mesma lógica do remover_arvore)
if (!empty($_POST)) {
    if (isset($_POST['mode']) && $_POST['mode'] === 'multi' && !empty($_POST['delete_ids']) && is_array($_POST['delete_ids'])) {
        $ids = array_map('intval', $_POST['delete_ids']);

        if (count($ids) > 0) {
            $in    = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));

            $stmt = $conn->prepare("DELETE FROM comentarios_noticias WHERE id IN ($in)");
            if ($stmt === false) {
                $error = 'Erro na preparação da query.';
            } else {
                $stmt->bind_param($types, ...$ids);
                if ($stmt->execute()) {
                    $success = 'Comentário(s) removido(s) com sucesso.';

                    $userId = (int)$_SESSION['user_id'];
                    foreach ($ids as $id) {
                        regista_log(
                            $conn,
                            $userId,
                            'remover',
                            'comentario_noticia',
                            $id,
                            'Comentário de notícia apagado (remoção múltipla).'
                        );

                        $acao    = 'Remoção de comentário de notícia';
                        $detalhe = "Comentário ID $id removido (remoção múltipla).";

                        $stmtAt = $conn->prepare("
                            INSERT INTO atividade (user_id, acao, detalhe)
                            VALUES (?, ?, ?)
                        ");
                        if ($stmtAt) {
                            $stmtAt->bind_param('iss', $userId, $acao, $detalhe);
                            $stmtAt->execute();
                            $stmtAt->close();
                        }
                    }
                } else {
                    $error = 'Erro ao remover comentários selecionados!';
                }
                $stmt->close();
            }
        }

    } elseif (isset($_POST['mode']) && $_POST['mode'] === 'single' && isset($_POST['delete_id'])) {
        $id   = (int)$_POST['delete_id'];
        $stmt = $conn->prepare('DELETE FROM comentarios_noticias WHERE id = ?');
        if ($stmt === false) {
            $error = 'Erro na preparação da query.';
        } else {
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $success = 'Comentário removido com sucesso.';

                regista_log(
                    $conn,
                    $_SESSION['user_id'],
                    'remover',
                    'comentario_noticia',
                    $id,
                    'Comentário de notícia apagado.'
                );

                $userId  = (int)$_SESSION['user_id'];
                $acao    = 'Remoção de comentário de notícia';
                $detalhe = "Comentário ID $id removido.";

                $stmtAt = $conn->prepare("
                    INSERT INTO atividade (user_id, acao, detalhe)
                    VALUES (?, ?, ?)
                ");
                if ($stmtAt) {
                    $stmtAt->bind_param('iss', $userId, $acao, $detalhe);
                    $stmtAt->execute();
                    $stmtAt->close();
                }
            } else {
                $error = 'Erro ao remover comentário!';
            }
            $stmt->close();
        }
    }

    // Como agora a listagem é client-side (sem ?page), basta recarregar a mesma página
    header('Location: index.php?evora=removercommentnoticias');
    exit();
}

/* ========= CARREGAR TODOS OS COMENTÁRIOS (SEM LIMIT/OFFSET) ========= */
$sqlComentarios = "
    SELECT 
        c.id,
        c.nome         AS nome_comentador,
        c.texto,
        c.criado_em,
        n.titulo       AS titulo_noticia,
        n.autor        AS autor_noticia
    FROM comentarios_noticias c
    JOIN noticias n ON n.id = c.noticia_id
    ORDER BY c.criado_em DESC, c.id DESC
";
$comentarios = $conn->query($sqlComentarios);

// filtros (títulos, autores, comentadores)
$titulos_result = $conn->query("
    SELECT DISTINCT n.titulo AS titulo_noticia
    FROM comentarios_noticias c
    JOIN noticias n ON n.id = c.noticia_id
    WHERE n.titulo IS NOT NULL AND n.titulo <> ''
    ORDER BY n.titulo ASC
");

$autores_result = $conn->query("
    SELECT DISTINCT n.autor AS autor_noticia
    FROM comentarios_noticias c
    JOIN noticias n ON n.id = c.noticia_id
    WHERE n.autor IS NOT NULL AND n.autor <> ''
    ORDER BY n.autor ASC
");

$nomes_result = $conn->query("
    SELECT DISTINCT c.nome AS nome_comentador
    FROM comentarios_noticias c
    WHERE c.nome IS NOT NULL AND c.nome <> ''
    ORDER BY c.nome ASC
");

// paginação visual client-side
$per_page         = 6;
$total_comments   = $comentarios ? $comentarios->num_rows : 0;
$total_pages_vis  = $per_page > 0 ? (int)ceil($total_comments / $per_page) : 1;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Remover comentários das notícias</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Fonts / tema -->
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
        .page-heading h3 {
            font-weight: 800;
        }
        .page-heading p {
            color: #6b7280;
        }
        .card-main {
            border-radius: 20px;
            border: 0;
            box-shadow: 0 18px 45px rgba(15,23,42,0.12);
        }
        .card-header {
            border-bottom: 1px solid #e5e7eb;
        }
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
            z-index: 1001;
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

        /* MOBILE FILTER PANEL */
        @media (max-width:768px) {
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
            box-shadow: 0 6px 18px rgba(15,23,42,0.08);
            overflow: hidden;
            background-color: #ffffff;
            display: flex;
            flex-direction: column;
            width: 100%;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .comment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(15,23,42,0.12);
        }
        .comment-card-body {
            padding: 0.9rem 1.1rem 0.6rem 1.1rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
        }
        .comment-card-footer {
            background-color: #f9fafb;
            padding: 0.45rem 1.1rem 0.55rem 1.1rem;
            font-size: 0.78rem;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: .75rem;
        }
        .comment-title {
            font-weight: 800;
            font-size: 1rem;
            margin-bottom: 0.15rem;
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
            font-size: 0.86rem;
            color: #4b5563;
        }
        .multi-actions {
            display:none;
        }
        .multi-actions.show {
            display:flex;
        }
    </style>
</head>
<body>
<div id="app">
    <?php include 'menu.php'; ?>

    <div id="main">
        <header class="mb-3 d-flex align-items-center">
            <a href="#" class="burger-btn d-block d-xl-none me-2">
                <i class="bi bi-justify fs-3"></i>
            </a>
        </header>

        <div class="page-heading-custom mb-3">
            <h3 class="mb-1">Remover comentários das notícias</h3>
            <p class="text-subtitle text-muted mb-0">
                Remova comentários das notícias públicas.
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
                                <h4 class="mb-1">Comentários registados</h4>
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

                    <!-- FILTER PANEL -->
                    <div id="filterPanel" class="filter-panel d-none">
                        <form id="comentarioFilterForm">
                            <div class="mb-2">
                                <label for="filterTitulo" class="form-label">Título da notícia</label>
                                <select id="filterTitulo" class="form-select js-nice-select">
                                    <option value="">Todos</option>
                                    <?php if ($titulos_result && $titulos_result->num_rows > 0): ?>
                                        <?php while ($t = $titulos_result->fetch_assoc()): ?>
                                            <?php if (!empty($t['titulo_noticia'])): ?>
                                                <option value="<?= htmlspecialchars(strtolower($t['titulo_noticia'])) ?>">
                                                    <?= htmlspecialchars($t['titulo_noticia']) ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="mb-2">
                                <label for="filterAutor" class="form-label">Autor da notícia</label>
                                <select id="filterAutor" class="form-select js-nice-select">
                                    <option value="">Todos</option>
                                    <?php if ($autores_result && $autores_result->num_rows > 0): ?>
                                        <?php while ($a = $autores_result->fetch_assoc()): ?>
                                            <?php if (!empty($a['autor_noticia'])): ?>
                                                <option value="<?= htmlspecialchars(strtolower($a['autor_noticia'])) ?>">
                                                    <?= htmlspecialchars($a['autor_noticia']) ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="mb-2">
                                <label for="filterNome" class="form-label">Nome do comentador</label>
                                <select id="filterNome" class="form-select js-nice-select">
                                    <option value="">Todos</option>
                                    <?php if ($nomes_result && $nomes_result->num_rows > 0): ?>
                                        <?php while ($n = $nomes_result->fetch_assoc()): ?>
                                            <?php if (!empty($n['nome_comentador'])): ?>
                                                <option value="<?= htmlspecialchars(strtolower($n['nome_comentador'])) ?>">
                                                    <?= htmlspecialchars($n['nome_comentador']) ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="mb-2">
                                <label for="filterData" class="form-label">Data do comentário (exata)</label>
                                <input
                                    type="date"
                                    class="form-control"
                                    id="filterData"
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

                    <!-- FORM ÚNICO -->
                    <form method="post" id="comentarioDeleteForm">
                        <input type="hidden" name="mode" id="deleteModeInput" value="single">
                        <input type="hidden" name="delete_id" id="singleDeleteId" value="">

                        <div class="card-body pt-3">
                            <?php if ($comentarios && $comentarios->num_rows > 0): ?>
                                <div class="container-fluid">
                                    <div class="row" id="comentarioList">
                                        <?php while ($c = $comentarios->fetch_assoc()): ?>
                                            <?php
                                                $dataRaw  = $c['criado_em'] ?? '';
                                                if ($dataRaw !== '' && $dataRaw !== null) {
                                                    $ts       = strtotime($dataRaw);
                                                    $dataText = $ts ? date('d/m/Y H:i', $ts) : $dataRaw;
                                                    $dataAttr = $ts ? date('Y-m-d', $ts) : '';
                                                } else {
                                                    $dataText = '—';
                                                    $dataAttr = '';
                                                }

                                                $tituloNot  = $c['titulo_noticia'] ?? 'Sem título';
                                                $autorNot   = $c['autor_noticia'] ?? '';
                                                $nomeComent = $c['nome_comentador'] ?? 'Anónimo';
                                                $texto      = $c['texto'] ?? '';
                                            ?>
                                            <div
                                                class="col-12 col-md-6 col-xl-4 comment-card-container"
                                            >
                                                <div
                                                    class="comment-card"
                                                    data-titulo-full="<?= htmlspecialchars(strtolower($tituloNot)) ?>"
                                                    data-autor-full="<?= htmlspecialchars(strtolower($autorNot)) ?>"
                                                    data-nome-full="<?= htmlspecialchars(strtolower($nomeComent)) ?>"
                                                    data-data-full="<?= htmlspecialchars($dataAttr) ?>"
                                                >
                                                    <div class="comment-card-body">
                                                        <div class="comment-title">
                                                            <?= htmlspecialchars($tituloNot) ?>
                                                        </div>

                                                        <div class="comment-line">
                                                            <span class="comment-label">Autor da notícia:</span>
                                                            <span class="comment-value comment-autor-noticia">
                                                                <?= $autorNot ? htmlspecialchars($autorNot) : 'Sem autor' ?>
                                                            </span>
                                                        </div>

                                                        <div class="comment-line">
                                                            <span class="comment-label">Comentador:</span>
                                                            <span class="comment-value comment-nome">
                                                                <?= htmlspecialchars($nomeComent) ?>
                                                            </span>
                                                        </div>

                                                        <div class="comment-line">
                                                            <span class="comment-label d-block mb-1">Comentário:</span>
                                                            <span class="comment-text">
                                                                <?= nl2br(htmlspecialchars($texto)) ?>
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <div class="comment-card-footer">
                                                        <span class="comment-data">
                                                            Data: <?= htmlspecialchars($dataText) ?>
                                                        </span>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <button
                                                                type="button"
                                                                class="btn btn-danger btn-sm single-delete-btn"
                                                                data-id="<?= (int)$c['id'] ?>"
                                                            >
                                                                Remover
                                                            </button>

                                                            <div class="form-check mb-0 multi-checkbox-wrapper" style="display:none;">
                                                                <input
                                                                    class="form-check-input comment-checkbox"
                                                                    type="checkbox"
                                                                    name="delete_ids[]"
                                                                    value="<?= (int)$c['id'] ?>"
                                                                    id="comChk<?= (int)$c['id'] ?>"
                                                                >
                                                                <label class="form-check-label" for="comChk<?= (int)$c['id'] ?>">
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
                                        onclick="return confirm('Tem a certeza que pretende remover os comentários selecionados?');"
                                    >
                                        Remover comentários selecionados
                                    </button>
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
const filterForm          = document.getElementById('comentarioFilterForm');

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
        const nomeSel   = document.getElementById('filterNome').tomselect;
        if (nomeSel) nomeSel.clear();

        applyFilters();
    });
}

if (filterForm) {
    filterForm.addEventListener('submit', (e) => {
        e.preventDefault();
        applyFilters();
        filterPanel.classList.add('d-none');
    });
}

// CLIENT-SIDE FILTER + PAGINAÇÃO
const perPage         = <?= (int)$per_page ?>;
const commentCards    = Array.from(document.querySelectorAll('.comment-card-container'));
const paginationNav   = document.getElementById('paginationNav');
const paginationLinks = document.getElementById('paginationLinks');

const filterTituloEl  = document.getElementById('filterTitulo');
const filterAutorEl   = document.getElementById('filterAutor');
const filterNomeEl    = document.getElementById('filterNome');
const filterDataEl    = document.getElementById('filterData');

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

function applyFilters() {
    const tituloSel = (filterTituloEl.value || '').trim().toLowerCase();
    const autorSel  = (filterAutorEl.value  || '').trim().toLowerCase();
    const nomeSel   = (filterNomeEl.value   || '').trim().toLowerCase();
    const dataSel   = (filterDataEl.value   || '').trim(); // yyyy-mm-dd

    commentCards.forEach(cardContainer => {
        const card = cardContainer.querySelector('.comment-card');
        if (!card) return;

        const tituloFull = (card.getAttribute('data-titulo-full') || '').trim().toLowerCase();
        const autorFull  = (card.getAttribute('data-autor-full')  || '').trim().toLowerCase();
        const nomeFull   = (card.getAttribute('data-nome-full')   || '').trim().toLowerCase();
        const dataFull   = (card.getAttribute('data-data-full')   || '').trim();

        const matchesTitulo = !tituloSel || tituloFull === tituloSel;
        const matchesAutor  = !autorSel  || autorFull  === autorSel;
        const matchesNome   = !nomeSel   || nomeFull   === nomeSel;
        const matchesData   = !dataSel   || dataFull   === dataSel;

        if (matchesTitulo && matchesAutor && matchesNome && matchesData) {
            cardContainer.style.display    = '';
            cardContainer.style.visibility = '';
            cardContainer.style.position   = '';
        } else {
            cardContainer.style.display    = 'none';
            cardContainer.style.visibility = '';
            cardContainer.style.position   = '';
        }
    });

    showCommentPage(1);
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
    applyFilters();
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

// MODO SELEÇÃO
const toggleSelectModeBtn = document.getElementById('toggleSelectMode');
const multiActions        = document.getElementById('multiActions');
const deleteModeInput     = document.getElementById('deleteModeInput');
const mainForm            = document.getElementById('comentarioDeleteForm');
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
        document.querySelectorAll('.comment-checkbox').forEach(chk => chk.checked = false);
        singleButtons.forEach(btn => btn.style.display = 'inline-block');
        checkboxWrappers.forEach(w => w.style.display = 'none');
        toggleSelectModeBtn.classList.remove('active');
    }
}

if (toggleSelectModeBtn) {
    toggleSelectModeBtn.addEventListener('click', () => {
        selectionMode = !selectionMode;
        updateSelectionUI();
    });
}

// single delete via main form
document.querySelectorAll('.single-delete-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        if (selectionMode) return;

        const id = this.getAttribute('data-id');
        if (!id) return;

        if (confirm('Tem a certeza que pretende remover este comentário?')) {
            deleteModeInput.value = 'single';
            singleDeleteIdInput.value = id;
            mainForm.submit();
        }
    });
});

// MENU MOBILE: igual às outras páginas, fecha filtro ao abrir menu
const burgerBtn      = document.querySelector('.burger-btn');
const sidebarWrapper = document.querySelector('.sidebar-wrapper');

if (burgerBtn && sidebarWrapper) {
    burgerBtn.addEventListener('click', (e) => {
        e.preventDefault();
        sidebarWrapper.classList.toggle('active');
        filterPanel.classList.add('d-none');
    });
}
</script>
</body>
</html>
