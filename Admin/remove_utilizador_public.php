<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
 
include './config.php';
include './log.php';
 
// 1) Tem de estar logado
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
 
// 2) Tem de ser admin
if (empty($_SESSION['is_admin']) || (int) $_SESSION['is_admin'] !== 1) {
    header('Location: index.php?evora=inicio');
    exit();
}
 
$success = '';
$error   = '';
$id      = isset($_GET['id']) ? (int) $_GET['id'] : 0;
 
/* =========================================================
   PARTE 1 – LISTA DE UTILIZADORES PÚBLICOS
   CLIENT-SIDE FILTER + CLIENT-SIDE PAGINATION
   ========================================================= */
if ($id <= 0) {
 
    $stmt = $conn->prepare("
        SELECT id, nome, email, username, phone, gender, birthday, criado_em
        FROM users_public
        ORDER BY criado_em DESC, id DESC
    ");
    $stmt->execute();
    $utilizadores_public = $stmt->get_result();
    $stmt->close();
 
    $emails_result = $conn->query("
        SELECT DISTINCT email
        FROM users_public
        WHERE email IS NOT NULL AND email <> ''
        ORDER BY email ASC
    ");
 
    $usernames_result = $conn->query("
        SELECT DISTINCT username
        FROM users_public
        WHERE username IS NOT NULL AND username <> ''
        ORDER BY username ASC
    ");
 
    $names_result = $conn->query("
        SELECT DISTINCT nome
        FROM users_public
        WHERE nome IS NOT NULL AND nome <> ''
        ORDER BY nome ASC
    ");
 
    $per_page_pub = 6;
    ?>
    <!DOCTYPE html>
    <html lang="pt">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Remover Utilizador Público</title>
 
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
 
            .user-card-container {
                display: flex;
                margin-bottom: 16px;
            }
            .user-card {
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
            .user-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12);
            }
            .user-card-body {
                padding: 0.9rem 1.1rem 0.6rem 1.1rem;
                flex-grow: 1;
                display: flex;
                flex-direction: column;
                gap: 0.15rem;
            }
            .user-card-footer {
                background-color: #f9fafb;
                padding: 0.45rem 1.1rem 0.55rem 1.1rem;
                font-size: 0.78rem;
                color: #6b7280;
                border-top: 1px solid #e5e7eb;
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: .75rem;
                flex-wrap: wrap;
            }
            .user-title {
                font-weight: 800;
                font-size: 1rem;
                margin-bottom: 0.15rem;
                color: #111827;
            }
            .user-label {
                font-weight: 600;
                font-size: 13px;
                color: #6b7280;
            }
            .user-value {
                font-size: 14px;
                color: #111827;
            }
            .user-line {
                margin-bottom: 0.15rem;
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
        <?php include 'menu.php'; ?>
 
        <div id="main">
            <header class="mb-3 d-flex align-items-center">
                <a href="#" class="burger-btn d-block d-xl-none me-2">
                    <i class="bi bi-justify fs-3"></i>
                </a>
            </header>
 
            <div class="page-heading mb-3">
                <h3 class="mb-1">Remover Utilizador Público</h3>
                <p class="text-subtitle text-muted">Escolha um utilizador público que pretende remover.</p>
            </div>
 
            <div class="page-content">
                <section class="section">
                    <div class="card card-main position-relative">
                        <div class="card-header border-0 pb-0">
                            <div class="card-header-flex">
                                <div>
                                    <h4 class="mb-1">Utilizadores públicos</h4>
                                    <small class="text-muted">Use o filtro para encontrar mais rápido.</small>
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
 
                        <div id="filterPanel" class="filter-panel d-none">
                            <form id="userFilterForm">
                                <div class="mb-2">
                                    <label for="filterEmail" class="form-label">Email</label>
                                    <select id="filterEmail" class="form-select js-nice-select">
                                        <option value="">Todos</option>
                                        <?php if ($emails_result && $emails_result->num_rows > 0): ?>
                                            <?php while ($e = $emails_result->fetch_assoc()): ?>
                                                <?php if (!empty($e['email'])): ?>
                                                    <option value="<?= htmlspecialchars(strtolower($e['email'])) ?>">
                                                        <?= htmlspecialchars($e['email']) ?>
                                                    </option>
                                                <?php endif; ?>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
 
                                <div class="mb-2">
                                    <label for="filterUsername" class="form-label">Nome de Utilizador</label>
                                    <select id="filterUsername" class="form-select js-nice-select">
                                        <option value="">Todos</option>
                                        <?php if ($usernames_result && $usernames_result->num_rows > 0): ?>
                                            <?php while ($u = $usernames_result->fetch_assoc()): ?>
                                                <?php if (!empty($u['username'])): ?>
                                                    <option value="<?= htmlspecialchars(strtolower($u['username'])) ?>">
                                                        <?= htmlspecialchars($u['username']) ?>
                                                    </option>
                                                <?php endif; ?>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
 
                                <div class="mb-2">
                                    <label for="filterName" class="form-label">Nome</label>
                                    <select id="filterName" class="form-select js-nice-select">
                                        <option value="">Todos</option>
                                        <?php if ($names_result && $names_result->num_rows > 0): ?>
                                            <?php while ($n = $names_result->fetch_assoc()): ?>
                                                <?php if (!empty($n['nome'])): ?>
                                                    <option value="<?= htmlspecialchars(strtolower($n['nome'])) ?>">
                                                        <?= htmlspecialchars($n['nome']) ?>
                                                    </option>
                                                <?php endif; ?>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
 
                                <div class="filter-panel-actions">
                                    <button type="button" class="btn btn-light btn-sm" id="clearFilters">Limpar</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="closeFilterPanel">Cancelar</button>
                                    <button type="submit" class="btn btn-primary btn-sm">Aplicar</button>
                                </div>
                            </form>
                        </div>
 
                        <div class="card-body pt-3">
                            <?php if ($utilizadores_public && $utilizadores_public->num_rows > 0): ?>
                                <div class="container-fluid">
                                    <div class="row" id="userListPublic">
                                        <?php while ($p = $utilizadores_public->fetch_assoc()): ?>
                                            <div
                                                class="col-12 col-md-6 col-xl-4 user-card-container"
                                                data-email="<?= htmlspecialchars(strtolower($p['email'] ?? '')) ?>"
                                                data-username="<?= htmlspecialchars(strtolower($p['username'] ?? '')) ?>"
                                                data-name="<?= htmlspecialchars(strtolower($p['nome'] ?? '')) ?>"
                                            >
                                                <div class="user-card">
                                                    <div class="user-card-body">
                                                        <div class="user-title d-flex justify-content-between align-items-center">
                                                            <span><?= htmlspecialchars(($p['nome'] ?: $p['username']) ?? '') ?></span>
                                                            <span class="badge rounded-pill bg-secondary"
                                                                  style="font-size:0.70rem;font-weight:700;padding:0.15rem 0.45rem;">
                                                                Público
                                                            </span>
                                                        </div>
 
                                                        <div class="user-line">
                                                            <span class="user-label">Email:</span>
                                                            <span class="user-value"><?= htmlspecialchars($p['email'] ?? '—') ?></span>
                                                        </div>
 
                                                        <div class="user-line">
                                                            <span class="user-label">Utilizador:</span>
                                                            <span class="user-value"><?= htmlspecialchars($p['username'] ?? '—') ?></span>
                                                        </div>
 
                                                        <div class="user-line">
                                                            <span class="user-label">Telefone:</span>
                                                            <span class="user-value"><?= htmlspecialchars($p['phone'] ?: '—') ?></span>
                                                        </div>
 
                                                        <div class="user-line">
                                                            <span class="user-label">Género:</span>
                                                            <span class="user-value"><?= htmlspecialchars($p['gender'] ?: '—') ?></span>
                                                        </div>
 
                                                        <div class="user-line">
                                                            <span class="user-label">Data Nasc.:</span>
                                                            <span class="user-value">
                                                                <?= !empty($p['birthday']) ? htmlspecialchars(date('d/m/Y', strtotime($p['birthday']))) : '—' ?>
                                                            </span>
                                                        </div>
                                                    </div>
 
                                                    <div class="user-card-footer">
                                                        <span>
                                                            Criado em:
                                                            <?= !empty($p['criado_em']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($p['criado_em']))) : '—' ?>
                                                        </span>
 
                                                        <a
                                                            href="index.php?evora=removerutilizadorpublic&id=<?= (int) $p['id'] ?>"
                                                            class="btn btn-danger btn-sm"
                                                        >
                                                            Remover
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                </div>
 
                                <nav id="paginationNavPublic" aria-label="Paginação de utilizadores públicos" class="mt-3 mb-1">
                                    <ul class="pagination justify-content-center flex-wrap" id="paginationLinksPublic"></ul>
                                </nav>
                            <?php else: ?>
                                <div class="alert alert-info text-center mb-0">
                                    Nenhum utilizador público.
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
 
        const openFilterPanelBtn  = document.getElementById('openFilterPanel');
        const filterPanel         = document.getElementById('filterPanel');
        const closeFilterPanelBtn = document.getElementById('closeFilterPanel');
        const clearFiltersBtn     = document.getElementById('clearFilters');
        const filterForm          = document.getElementById('userFilterForm');
 
        const publicCards         = Array.from(document.querySelectorAll('#userListPublic .user-card-container'));
        const paginationNavPublic = document.getElementById('paginationNavPublic');
        const paginationLinksPublic = document.getElementById('paginationLinksPublic');
 
        const filterEmailEl       = document.getElementById('filterEmail');
        const filterUsernameEl    = document.getElementById('filterUsername');
        const filterNameEl        = document.getElementById('filterName');
 
        const perPagePublic = <?= (int)$per_page_pub ?>;
 
        function getVisiblePublicCards() {
            return publicCards.filter(card => card.style.display !== 'none');
        }
 
        function renderPaginationPublic(totalPages, currentPage) {
            if (!paginationNavPublic || !paginationLinksPublic) return;
 
            if (totalPages <= 1) {
                paginationNavPublic.style.display = 'none';
                paginationLinksPublic.innerHTML = '';
                return;
            }
 
            paginationNavPublic.style.display = '';
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
 
            paginationLinksPublic.innerHTML = html;
        }
 
        function showPublicPage(page) {
            const visibleCards = getVisiblePublicCards();
            const totalVisible = visibleCards.length;
            const totalPages   = Math.ceil(totalVisible / perPagePublic) || 1;
 
            if (page < 1) page = 1;
            if (page > totalPages) page = totalPages;
 
            const start = (page - 1) * perPagePublic;
            const end   = start + perPagePublic;
 
            visibleCards.forEach((card, index) => {
                if (index >= start && index < end) {
                    card.style.visibility = '';
                    card.style.position   = '';
                } else {
                    card.style.visibility = 'hidden';
                    card.style.position   = 'absolute';
                }
            });
 
            renderPaginationPublic(totalPages, page);
        }
 
        function applyFilters() {
            const emailVal    = (filterEmailEl?.value || '').trim().toLowerCase();
            const usernameVal = (filterUsernameEl?.value || '').trim().toLowerCase();
            const nameVal     = (filterNameEl?.value || '').trim().toLowerCase();
 
            publicCards.forEach(function (card) {
                const cardEmail    = (card.dataset.email || '').trim().toLowerCase();
                const cardUsername = (card.dataset.username || '').trim().toLowerCase();
                const cardName     = (card.dataset.name || '').trim().toLowerCase();
 
                const matchesEmail    = !emailVal || cardEmail === emailVal;
                const matchesUsername = !usernameVal || cardUsername === usernameVal;
                const matchesName     = !nameVal || cardName === nameVal;
 
                if (matchesEmail && matchesUsername && matchesName) {
                    card.style.display    = '';
                    card.style.visibility = '';
                    card.style.position   = '';
                } else {
                    card.style.display    = 'none';
                    card.style.visibility = '';
                    card.style.position   = '';
                }
            });
 
            showPublicPage(1);
        }
 
        if (openFilterPanelBtn) {
            openFilterPanelBtn.addEventListener('click', function () {
                filterPanel.classList.toggle('d-none');
            });
        }
 
        if (closeFilterPanelBtn) {
            closeFilterPanelBtn.addEventListener('click', function () {
                filterPanel.classList.add('d-none');
            });
        }
 
        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', function () {
                const emailSel = filterEmailEl?.tomselect;
                if (emailSel) emailSel.clear();
 
                const usernameSel = filterUsernameEl?.tomselect;
                if (usernameSel) usernameSel.clear();
 
                const nameSel = filterNameEl?.tomselect;
                if (nameSel) nameSel.clear();
 
                applyFilters();
            });
        }
 
        if (filterForm) {
            filterForm.addEventListener('submit', function (e) {
                e.preventDefault();
                applyFilters();
                filterPanel.classList.add('d-none');
            });
        }
 
        if (paginationLinksPublic) {
            paginationLinksPublic.addEventListener('click', function (e) {
                const link = e.target.closest('.page-link');
                if (!link) return;
                e.preventDefault();
 
                const li = link.parentElement;
                if (li.classList.contains('disabled')) return;
 
                const page = parseInt(link.dataset.page, 10);
                if (!isNaN(page)) {
                    showPublicPage(page);
                }
            });
        }
 
        if (publicCards.length > 0) {
            applyFilters();
        } else if (paginationNavPublic) {
            paginationNavPublic.style.display = 'none';
        }
 
        document.addEventListener('click', function (e) {
            if (filterPanel && openFilterPanelBtn && !filterPanel.classList.contains('d-none')) {
                if (!filterPanel.contains(e.target) && !openFilterPanelBtn.contains(e.target)) {
                    filterPanel.classList.add('d-none');
                }
            }
        });
    });
    </script>
    </body>
    </html>
    <?php
    exit();
}
 
/* =========================================================
   PARTE 2 – PASSO 1: CONFIRMAR PASSWORD DO ADMIN
   ========================================================= */
if (!isset($_SESSION['remove_public_verified']) || (int) $_SESSION['remove_public_verified'] !== (int) $id) {
    if (isset($_POST['confirm_pass'])) {
        $adminId = (int) $_SESSION['user_id'];
 
        $stmt = $conn->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $stmt->bind_result($adminHash);
        $stmt->fetch();
        $stmt->close();
 
        if (isset($_POST['admin_password']) && password_verify($_POST['admin_password'], $adminHash)) {
            $_SESSION['remove_public_verified'] = (int) $id;
            header('Location: index.php?evora=removerutilizadorpublic&id=' . $id);
            exit();
        }
 
        $error = 'Palavra-passe incorreta!';
    }
    ?>
    <!DOCTYPE html>
    <html lang="pt">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Confirmar Remoção (Público)</title>
 
        <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="assets/css/bootstrap.css">
        <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
        <link rel="stylesheet" href="assets/css/app.css">
 
        <style>
            html, body { height:100%; overflow:hidden; }
            body { background-color:#f8f9fa !important; font-family:'Nunito',sans-serif!important; margin:0; }
            #app, #main { height:100%; }
            .page-content { background-color:transparent; height:100%; padding:0; }
            .form-wrapper { height:100%; display:flex; align-items:center; justify-content:center; padding:0 12px; }
            .form-card {
                background:#ffffff; border-radius:18px; box-shadow:0 2px 32px rgba(35,58,140,0.09);
                padding:38px 38px 27px 38px; max-width:400px; width:100%;
            }
            .form-card h3 { font-weight:700; color:#111827; }
            .btn-danger { border-radius:8px; font-weight:600; }
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
 
            <div class="page-content">
                <div class="form-wrapper">
                    <div class="form-card shadow">
                        <h3 class="mb-3 text-center">Confirmação de Segurança</h3>
 
                        <p class="text-subtitle text-muted text-center mb-4">
                            Insira a sua palavra-passe de administrador para remover o utilizador público.
                        </p>
 
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger">
                                <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
 
                        <form method="post" autocomplete="off" action="index.php?evora=removerutilizadorpublic&id=<?= $id ?>">
                            <div class="form-group mb-4">
                                <label for="admin_password">
                                    <i class="bi bi-lock me-1"></i> Palavra-passe
                                </label>
 
                                <input
                                    type="password"
                                    name="admin_password"
                                    id="admin_password"
                                    class="form-control"
                                    required
                                    minlength="8"
                                >
                            </div>
 
                            <input type="hidden" name="confirm_pass" value="1">
 
                            <button type="submit" class="btn btn-danger w-100">
                                <i class="bi bi-shield-check"></i> Confirmar
                            </button>
                        </form>
 
                        <a href="index.php?evora=removerutilizadorpublic" class="btn btn-secondary w-100 mt-2">
                            Cancelar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
 
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
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
    <?php
    exit();
}
 
/* =========================================================
   PARTE 3 – CARREGAR UTILIZADOR PÚBLICO PELO ID
   ========================================================= */
$stmt = $conn->prepare('SELECT * FROM users_public WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$result      = $stmt->get_result();
$user_public = $result->fetch_assoc();
$stmt->close();
 
if (!$user_public) {
    ?>
    <!DOCTYPE html>
    <html lang="pt">
    <head>
        <meta charset="UTF-8">
        <title>Utilizador público não encontrado</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
 
        <link
            href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap"
            rel="stylesheet"
        >
        <link rel="stylesheet" href="assets/css/bootstrap.css">
        <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
        <link rel="stylesheet" href="assets/css/app.css">
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
 
            <div class="page-content">
                <section class="section">
                    <div class="alert alert-danger shadow mt-5 mx-auto" style="max-width: 500px;">
                        Utilizador público não encontrado.
 
                        <a href="index.php?evora=removerutilizadorpublic" class="btn btn-sm btn-primary ms-2">
                            Selecionar Outro
                        </a>
                    </div>
                </section>
            </div>
        </div>
    </div>
 
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
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
    <?php
    exit();
}
 
/* =========================================================
   PARTE 4 – DELETE DEFINITIVO
   ========================================================= */
if (isset($_POST['remover_definitivo'])) {
    $stmt = $conn->prepare('DELETE FROM users_public WHERE id = ?');
    $stmt->bind_param('i', $id);
 
    if ($stmt->execute()) {
        regista_log(
            $conn,
            (int) $_SESSION['user_id'],
            'remover',
            'utilizador_public',
            $id,
            'Utilizador público apagado.'
        );
 
        $adminId = (int) $_SESSION['user_id'];
        $acao    = 'Remoção de utilizador público';
        $detalhe = 'Utilizador público ID ' . $id . ' removido pelo administrador ID ' . $adminId . '.';
 
        $stmtAt = $conn->prepare("
            INSERT INTO atividade (user_id, acao, detalhe)
            VALUES (?, ?, ?)
        ");
        if ($stmtAt) {
            $stmtAt->bind_param('iss', $adminId, $acao, $detalhe);
            $stmtAt->execute();
            $stmtAt->close();
        }
 
        unset($_SESSION['remove_public_verified']);
        $success = 'Utilizador público removido com sucesso!';
    } else {
        $error = 'Erro ao remover o utilizador público: ' . $stmt->error;
    }
 
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Confirmar Remoção (Público)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
 
    <link
        href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/app.css">
 
    <style>
        body {
            font-family: 'Nunito', sans-serif !important;
            background: #f6f8fc;
        }
 
        .center-form-container {
            min-height: 90vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 0 12px;
        }
 
        .form-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 2px 32px rgba(35, 58, 140, 0.09);
            padding: 38px 38px 27px 38px;
            max-width: 500px;
            width: 100%;
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
 
        <div class="center-form-container">
            <div class="form-card shadow">
                <h3 class="mb-3 text-center">Remoção de Utilizador Público</h3>
 
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($success) ?>
                    </div>
 
                    <a href="index.php?evora=removerutilizadorpublic" class="btn btn-primary w-100 mt-2">
                        <i class="bi bi-arrow-left"></i>
                        Voltar à lista pública
                    </a>
 
                <?php elseif (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars($error) ?>
                    </div>
 
                    <a href="index.php?evora=removerutilizadorpublic" class="btn btn-secondary w-100 mt-2">
                        <i class="bi bi-arrow-left"></i>
                        Voltar
                    </a>
 
                <?php else: ?>
                    <p class="mb-3">
                        Tem a certeza que pretende remover o utilizador público seguinte?
                    </p>
 
                    <ul class="mb-3">
                        <li><strong>Nome:</strong> <?= htmlspecialchars($user_public['nome']) ?></li>
                        <li><strong>Username:</strong> <?= htmlspecialchars($user_public['username']) ?></li>
                        <li><strong>Email:</strong> <?= htmlspecialchars($user_public['email']) ?></li>
                    </ul>
 
                    <form method="post" action="index.php?evora=removerutilizadorpublic&id=<?= $id ?>">
                        <button
                            type="submit"
                            name="remover_definitivo"
                            class="btn btn-danger w-100"
                        >
                            Sim, remover definitivamente
                        </button>
 
                        <a href="index.php?evora=removerutilizadorpublic" class="btn btn-secondary w-100 mt-2">
                            Cancelar
                        </a>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
 
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
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
