<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include './config.php';
include './log.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (empty($_SESSION['is_admin']) || (int) $_SESSION['is_admin'] !== 1) {
    header('Location: index.php?evora=inicio');
    exit();
}

$success = '';
$error   = '';
$id      = isset($_GET['id']) ? (int) $_GET['id'] : 0;

/* =========================================================
   PARTE 1 – LISTA DE UTILIZADORES
   CLIENT-SIDE FILTER + CLIENT-SIDE PAGINATION
   ========================================================= */
if ($id <= 0) {

    $sql_func = "
        SELECT id, email, username, name, birthday, gender, phone, created_at, is_active, last_activity
        FROM users
        WHERE is_admin = 0 AND is_active = 1
        ORDER BY created_at DESC, id DESC
    ";
    $result_func = $conn->query($sql_func);

    $generos_func_result = $conn->query("
        SELECT DISTINCT gender
        FROM users
        WHERE gender IS NOT NULL AND gender <> '' AND is_admin = 0 AND is_active = 1
        ORDER BY gender ASC
    ");

    $users_func_filter_result = $conn->query("
        SELECT id, name, username, email
        FROM users
        WHERE is_admin = 0 AND is_active = 1
        ORDER BY name ASC, username ASC
    ");

    $sql_admin = "
        SELECT id, email, username, name, birthday, gender, phone, created_at, is_active, last_activity
        FROM users
        WHERE is_admin = 1 AND is_active = 1
        ORDER BY created_at DESC, id DESC
    ";
    $result_admin = $conn->query($sql_admin);

    $generos_admin_result = $conn->query("
        SELECT DISTINCT gender
        FROM users
        WHERE gender IS NOT NULL AND gender <> '' AND is_admin = 1 AND is_active = 1
        ORDER BY gender ASC
    ");

    $users_admin_filter_result = $conn->query("
        SELECT id, name, username, email
        FROM users
        WHERE is_admin = 1 AND is_active = 1
        ORDER BY name ASC, username ASC
    ");

    $per_page_func  = 6;
    $per_page_admin = 6;
    ?>
    <!DOCTYPE html>
    <html lang="pt">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Remover Utilizadores</title>

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
            #openFilterPanelFunc,
            #openFilterPanelAdmin {
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
            .ts-wrapper.single.input-active .ts-control,
            .ts-wrapper.multi .ts-control {
                background-color: #ffffff !important;
                border-radius: 10px;
                padding: 0.375rem 0.75rem;
                border-color: #d1d5db;
            }
            .ts-wrapper.single .ts-control:focus,
            .ts-wrapper.multi .ts-control:focus {
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
                .ts-wrapper.single.input-active .ts-control,
                .ts-wrapper.multi .ts-control {
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
                <h3 class="mb-1">Remover Utilizadores</h3>
                <p class="text-subtitle text-muted">Selecione o utilizador que pretende remover.</p>
            </div>

            <div class="page-content">
                <?php if ($success): ?>
                    <div class="alert alert-success mx-3">
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php elseif ($error): ?>
                    <div class="alert alert-danger mx-3">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <!-- FUNCIONÁRIOS -->
                <section class="section mb-4">
                    <div class="card card-main position-relative">
                        <div class="card-header border-0 pb-0">
                            <div class="card-header-flex">
                                <div>
                                    <h4 class="mb-1">Funcionários</h4>
                                    <small class="text-muted">Use o filtro para encontrar mais rápido.</small>
                                </div>
                                <button id="openFilterPanelFunc" class="btn btn-outline-secondary d-flex align-items-center" type="button">
                                    <i class="bi bi-funnel-fill me-1"></i>
                                    <span>Filtrar</span>
                                </button>
                            </div>
                        </div>

                        <div id="filterPanelFunc" class="filter-panel d-none">
                            <form id="userFilterFormFunc">
                                <div class="mb-2">
                                    <label for="filterUserFunc" class="form-label">Utilizador</label>
                                    <select id="filterUserFunc" class="form-select js-nice-select">
                                        <option value="">Todos</option>
                                        <?php if ($users_func_filter_result && $users_func_filter_result->num_rows > 0): ?>
                                            <?php while ($fu = $users_func_filter_result->fetch_assoc()): ?>
                                                <?php
                                                $fn = trim($fu['name'] ?? '');
                                                $un = trim($fu['username'] ?? '');
                                                $em = trim($fu['email'] ?? '');
                                                $labelParts = [];
                                                if ($fn !== '') $labelParts[] = $fn;
                                                if ($un !== '') $labelParts[] = $un;
                                                if ($em !== '') $labelParts[] = $em;
                                                $label = implode(' – ', $labelParts);
                                                if ($label === '' || $em === '') continue;
                                                ?>
                                                <option value="<?= htmlspecialchars(strtolower($em)) ?>">
                                                    <?= htmlspecialchars($label) ?>
                                                </option>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <div class="mb-2">
                                    <label for="filterGeneroFunc" class="form-label">Género</label>
                                    <select id="filterGeneroFunc" class="form-select js-nice-select">
                                        <option value="">Todos</option>
                                        <?php if ($generos_func_result && $generos_func_result->num_rows > 0): ?>
                                            <?php while ($g = $generos_func_result->fetch_assoc()): ?>
                                                <?php if (!empty($g['gender'])): ?>
                                                    <option value="<?= htmlspecialchars(strtolower($g['gender'])) ?>">
                                                        <?= htmlspecialchars($g['gender']) ?>
                                                    </option>
                                                <?php endif; ?>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <div class="mb-2">
                                    <label for="filterDataFunc" class="form-label">Criado em</label>
                                    <input type="date" class="form-control" id="filterDataFunc">
                                </div>

                                <div class="filter-panel-actions">
                                    <button type="button" class="btn btn-light btn-sm" id="clearFiltersFunc">Limpar</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="closeFilterPanelFunc">Cancelar</button>
                                    <button type="submit" class="btn btn-primary btn-sm">Aplicar</button>
                                </div>
                            </form>
                        </div>

                        <div class="card-body pt-3">
                            <?php if ($result_func && $result_func->num_rows > 0): ?>
                                <div class="container-fluid">
                                    <div class="row" id="userListFunc">
                                        <?php while ($u = $result_func->fetch_assoc()): ?>
                                            <?php
                                            $temUltimaAtividade = !empty($u['last_activity']);
                                            $pillBg     = $temUltimaAtividade ? '#dcfce7' : '#fee2e2';
                                            $pillText   = $temUltimaAtividade ? '#166534' : '#b91c1c';
                                            $pillBorder = $temUltimaAtividade ? '#bbf7d0' : '#fecaca';
                                            $pillIcon   = $temUltimaAtividade ? 'bi-person-check' : 'bi-person-x';
                                            $pillLabel  = $temUltimaAtividade ? 'Ativo' : 'Inativo';
                                            $createdDateYmd = !empty($u['created_at']) ? date('Y-m-d', strtotime($u['created_at'])) : '';
                                            $gender_raw   = $u['gender'] ?? '';
                                            $gender_lower = strtolower(trim($gender_raw));
                                            ?>
                                            <div class="col-12 col-md-6 col-xl-4 user-card-container"
                                                 data-email-filter="<?= htmlspecialchars(strtolower($u['email'] ?? '')) ?>"
                                                 data-genero="<?= htmlspecialchars($gender_lower) ?>"
                                                 data-date-func="<?= htmlspecialchars($createdDateYmd) ?>">
                                                <div class="user-card">
                                                    <div class="user-card-body">
                                                        <div class="user-title d-flex justify-content-between align-items-center">
                                                            <span><?= htmlspecialchars(($u['name'] ?: $u['username']) ?? '') ?></span>
                                                            <span class="badge rounded-pill"
                                                                  style="background:<?= $pillBg ?>;color:<?= $pillText ?>;border:1px solid <?= $pillBorder ?>;font-size:0.70rem;font-weight:700;padding:0.15rem 0.45rem;">
                                                                <i class="bi <?= $pillIcon ?> me-1"></i><?= $pillLabel ?>
                                                            </span>
                                                        </div>

                                                        <div class="user-line">
                                                            <span class="user-label">Username:</span>
                                                            <span class="user-value"><?= htmlspecialchars($u['username'] ?? '—') ?></span>
                                                        </div>

                                                        <div class="user-line">
                                                            <span class="user-label">Email:</span>
                                                            <span class="user-value"><?= htmlspecialchars($u['email'] ?? '—') ?></span>
                                                        </div>

                                                        <div class="user-line">
                                                            <span class="user-label">Telefone:</span>
                                                            <span class="user-value"><?= htmlspecialchars($u['phone'] ?: '—') ?></span>
                                                        </div>

                                                        <div class="user-line">
                                                            <span class="user-label">Género:</span>
                                                            <span class="user-value"><?= htmlspecialchars($u['gender'] ?: '—') ?></span>
                                                        </div>

                                                        <div class="user-line">
                                                            <span class="user-label">Data Nasc.:</span>
                                                            <span class="user-value">
                                                                <?= !empty($u['birthday']) ? htmlspecialchars(date('d/m/Y', strtotime($u['birthday']))) : '—' ?>
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <div class="user-card-footer">
                                                        <span>
                                                            Criado em:
                                                            <?= !empty($u['created_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($u['created_at']))) : '—' ?>
                                                        </span>
                                                        <div>
                                                            <?php if ($u['id'] == $_SESSION['user_id']): ?>
                                                                <span class="text-muted small">A sua conta</span>
                                                            <?php else: ?>
                                                                <a href="index.php?evora=removerutilizador&id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-danger">
                                                                    Remover
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                </div>

                                <nav id="paginationNavFunc" aria-label="Paginação de funcionários" class="mt-3 mb-1">
                                    <ul class="pagination justify-content-center flex-wrap" id="paginationLinksFunc"></ul>
                                </nav>
                            <?php else: ?>
                                <div class="alert alert-info text-center mb-0">
                                    Nenhum funcionário registado.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <!-- ADMINS -->
                <section class="section mb-4">
                    <div class="card card-main position-relative">
                        <div class="card-header border-0 pb-0">
                            <div class="card-header-flex">
                                <div>
                                    <h4 class="mb-1">Utilizadores Admin</h4>
                                    <small class="text-muted">Use o filtro para encontrar mais rápido.</small>
                                </div>
                                <button id="openFilterPanelAdmin" class="btn btn-outline-secondary d-flex align-items-center" type="button">
                                    <i class="bi bi-funnel-fill me-1"></i>
                                    <span>Filtrar</span>
                                </button>
                            </div>
                        </div>

                        <div id="filterPanelAdmin" class="filter-panel d-none">
                            <form id="userFilterFormAdmin">
                                <div class="mb-2">
                                    <label for="filterUserAdmin" class="form-label">Utilizador</label>
                                    <select id="filterUserAdmin" class="form-select js-nice-select">
                                        <option value="">Todos</option>
                                        <?php if ($users_admin_filter_result && $users_admin_filter_result->num_rows > 0): ?>
                                            <?php while ($fu = $users_admin_filter_result->fetch_assoc()): ?>
                                                <?php
                                                $fn = trim($fu['name'] ?? '');
                                                $un = trim($fu['username'] ?? '');
                                                $em = trim($fu['email'] ?? '');
                                                $labelParts = [];
                                                if ($fn !== '') $labelParts[] = $fn;
                                                if ($un !== '') $labelParts[] = $un;
                                                if ($em !== '') $labelParts[] = $em;
                                                $label = implode(' – ', $labelParts);
                                                if ($label === '' || $em === '') continue;
                                                ?>
                                                <option value="<?= htmlspecialchars(strtolower($em)) ?>">
                                                    <?= htmlspecialchars($label) ?>
                                                </option>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <div class="mb-2">
                                    <label for="filterGeneroAdmin" class="form-label">Género</label>
                                    <select id="filterGeneroAdmin" class="form-select js-nice-select">
                                        <option value="">Todos</option>
                                        <?php if ($generos_admin_result && $generos_admin_result->num_rows > 0): ?>
                                            <?php while ($g = $generos_admin_result->fetch_assoc()): ?>
                                                <?php if (!empty($g['gender'])): ?>
                                                    <option value="<?= htmlspecialchars(strtolower($g['gender'])) ?>">
                                                        <?= htmlspecialchars($g['gender']) ?>
                                                    </option>
                                                <?php endif; ?>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <div class="mb-2">
                                    <label for="filterAtivoAdmin" class="form-label">Ativos / Inativos</label>
                                    <select id="filterAtivoAdmin" class="form-select js-nice-select">
                                        <option value="">Todos</option>
                                        <option value="1">Só ativos</option>
                                        <option value="0">Só inativos</option>
                                    </select>
                                </div>

                                <div class="mb-2">
                                    <label for="filterDataAdmin" class="form-label">Criado em</label>
                                    <input type="date" class="form-control" id="filterDataAdmin">
                                </div>

                                <div class="filter-panel-actions">
                                    <button type="button" class="btn btn-light btn-sm" id="clearFiltersAdmin">Limpar</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="closeFilterPanelAdmin">Cancelar</button>
                                    <button type="submit" class="btn btn-primary btn-sm">Aplicar</button>
                                </div>
                            </form>
                        </div>

                        <div class="card-body pt-3">
                            <?php if ($result_admin && $result_admin->num_rows > 0): ?>
                                <div class="container-fluid">
                                    <div class="row" id="userListAdmin">
                                        <?php while ($u = $result_admin->fetch_assoc()): ?>
                                            <?php
                                            $temUltimaAtividade = !empty($u['last_activity']);
                                            $pillBg     = $temUltimaAtividade ? '#dcfce7' : '#fee2e2';
                                            $pillText   = $temUltimaAtividade ? '#166534' : '#b91c1c';
                                            $pillBorder = $temUltimaAtividade ? '#bbf7d0' : '#fecaca';
                                            $pillIcon   = $temUltimaAtividade ? 'bi-person-check' : 'bi-person-x';
                                            $pillLabel  = $temUltimaAtividade ? 'Ativo' : 'Inativo';
                                            $createdDateYmd = !empty($u['created_at']) ? date('Y-m-d', strtotime($u['created_at'])) : '';
                                            $gender_raw   = $u['gender'] ?? '';
                                            $gender_lower = strtolower(trim($gender_raw));
                                            ?>
                                            <div class="col-12 col-md-6 col-xl-4 user-card-container"
                                                 data-email-filter="<?= htmlspecialchars(strtolower($u['email'] ?? '')) ?>"
                                                 data-genero="<?= htmlspecialchars($gender_lower) ?>"
                                                 data-date-admin="<?= htmlspecialchars($createdDateYmd) ?>"
                                                 data-ativo="<?= $temUltimaAtividade ? '1' : '0' ?>">
                                                <div class="user-card">
                                                    <div class="user-card-body">
                                                        <div class="user-title d-flex justify-content-between align-items-center">
                                                            <span><?= htmlspecialchars(($u['name'] ?: $u['username']) ?? '') ?></span>
                                                            <span class="badge rounded-pill"
                                                                  style="background:<?= $pillBg ?>;color:<?= $pillText ?>;border:1px solid <?= $pillBorder ?>;font-size:0.70rem;font-weight:700;padding:0.15rem 0.45rem;">
                                                                <i class="bi <?= $pillIcon ?> me-1"></i><?= $pillLabel ?>
                                                            </span>
                                                        </div>

                                                        <div class="user-line">
                                                            <span class="user-label">Username:</span>
                                                            <span class="user-value"><?= htmlspecialchars($u['username'] ?? '—') ?></span>
                                                        </div>

                                                        <div class="user-line">
                                                            <span class="user-label">Email:</span>
                                                            <span class="user-value"><?= htmlspecialchars($u['email'] ?? '—') ?></span>
                                                        </div>

                                                        <div class="user-line">
                                                            <span class="user-label">Telefone:</span>
                                                            <span class="user-value"><?= htmlspecialchars($u['phone'] ?: '—') ?></span>
                                                        </div>

                                                        <div class="user-line">
                                                            <span class="user-label">Género:</span>
                                                            <span class="user-value"><?= htmlspecialchars($u['gender'] ?: '—') ?></span>
                                                        </div>

                                                        <div class="user-line">
                                                            <span class="user-label">Data Nasc.:</span>
                                                            <span class="user-value">
                                                                <?= !empty($u['birthday']) ? htmlspecialchars(date('d/m/Y', strtotime($u['birthday']))) : '—' ?>
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <div class="user-card-footer">
                                                        <span>
                                                            Criado em:
                                                            <?= !empty($u['created_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($u['created_at']))) : '—' ?>
                                                        </span>
                                                        <div>
                                                            <?php if ($u['id'] == $_SESSION['user_id']): ?>
                                                                <span class="text-muted small">A sua conta</span>
                                                            <?php else: ?>
                                                                <a href="index.php?evora=removerutilizador&id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-danger">
                                                                    Remover
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                </div>

                                <nav id="paginationNavAdmin" aria-label="Paginação de admins" class="mt-3 mb-1">
                                    <ul class="pagination justify-content-center flex-wrap" id="paginationLinksAdmin"></ul>
                                </nav>
                            <?php else: ?>
                                <div class="alert alert-info text-center mb-0">
                                    Nenhum admin registado.
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

        /* =========================
           FUNCIONÁRIOS
           ========================= */
        const openFilterPanelFunc  = document.getElementById('openFilterPanelFunc');
        const filterPanelFunc      = document.getElementById('filterPanelFunc');
        const closeFilterPanelFunc = document.getElementById('closeFilterPanelFunc');
        const clearFiltersFuncBtn  = document.getElementById('clearFiltersFunc');
        const filterFormFunc       = document.getElementById('userFilterFormFunc');

        const funcCards            = Array.from(document.querySelectorAll('#userListFunc .user-card-container'));
        const paginationNavFunc    = document.getElementById('paginationNavFunc');
        const paginationLinksFunc  = document.getElementById('paginationLinksFunc');

        const filterUserFuncEl     = document.getElementById('filterUserFunc');
        const filterGeneroFuncEl   = document.getElementById('filterGeneroFunc');
        const filterDataFuncEl     = document.getElementById('filterDataFunc');

        const perPageFunc = <?= (int)$per_page_func ?>;

        function getVisibleFuncCards() {
            return funcCards.filter(card => card.style.display !== 'none');
        }

        function renderPaginationFunc(totalPages, currentPage) {
            if (!paginationNavFunc || !paginationLinksFunc) return;

            if (totalPages <= 1) {
                paginationNavFunc.style.display = 'none';
                paginationLinksFunc.innerHTML = '';
                return;
            }

            paginationNavFunc.style.display = '';
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

            paginationLinksFunc.innerHTML = html;
        }

        function showFuncPage(page) {
            const visibleCards = getVisibleFuncCards();
            const totalVisible = visibleCards.length;
            const totalPages   = Math.ceil(totalVisible / perPageFunc) || 1;

            if (page < 1) page = 1;
            if (page > totalPages) page = totalPages;

            const start = (page - 1) * perPageFunc;
            const end   = start + perPageFunc;

            visibleCards.forEach((card, index) => {
                if (index >= start && index < end) {
                    card.style.visibility = '';
                    card.style.position   = '';
                } else {
                    card.style.visibility = 'hidden';
                    card.style.position   = 'absolute';
                }
            });

            renderPaginationFunc(totalPages, page);
        }

        function applyFuncFilters() {
            const emailSel  = (filterUserFuncEl?.value || '').trim().toLowerCase();
            const generoSel = (filterGeneroFuncEl?.value || '').trim().toLowerCase();
            const dataSel   = (filterDataFuncEl?.value || '').trim();

            funcCards.forEach(card => {
                const cardEmail  = (card.dataset.emailFilter || '').trim().toLowerCase();
                const cardGenero = (card.dataset.genero || '').trim().toLowerCase();
                const cardData   = (card.dataset.dateFunc || '').trim();

                const matchesEmail  = !emailSel || cardEmail === emailSel;
                const matchesGenero = !generoSel || cardGenero === generoSel;
                const matchesData   = !dataSel || cardData === dataSel;

                if (matchesEmail && matchesGenero && matchesData) {
                    card.style.display    = '';
                    card.style.visibility = '';
                    card.style.position   = '';
                } else {
                    card.style.display    = 'none';
                    card.style.visibility = '';
                    card.style.position   = '';
                }
            });

            showFuncPage(1);
        }

        if (openFilterPanelFunc) {
            openFilterPanelFunc.addEventListener('click', () => {
                filterPanelFunc.classList.toggle('d-none');
            });
        }

        if (closeFilterPanelFunc) {
            closeFilterPanelFunc.addEventListener('click', () => {
                filterPanelFunc.classList.add('d-none');
            });
        }

        if (clearFiltersFuncBtn) {
            clearFiltersFuncBtn.addEventListener('click', () => {
                if (filterDataFuncEl) filterDataFuncEl.value = '';

                const userSel = filterUserFuncEl?.tomselect;
                if (userSel) userSel.clear();

                const generoSel = filterGeneroFuncEl?.tomselect;
                if (generoSel) generoSel.clear();

                applyFuncFilters();
            });
        }

        if (filterFormFunc) {
            filterFormFunc.addEventListener('submit', function (e) {
                e.preventDefault();
                applyFuncFilters();
                filterPanelFunc.classList.add('d-none');
            });
        }

        if (paginationLinksFunc) {
            paginationLinksFunc.addEventListener('click', function (e) {
                const link = e.target.closest('.page-link');
                if (!link) return;
                e.preventDefault();

                const li = link.parentElement;
                if (li.classList.contains('disabled')) return;

                const page = parseInt(link.dataset.page, 10);
                if (!isNaN(page)) {
                    showFuncPage(page);
                }
            });
        }

        if (funcCards.length > 0) {
            applyFuncFilters();
        } else if (paginationNavFunc) {
            paginationNavFunc.style.display = 'none';
        }

        /* =========================
           ADMIN
           ========================= */
        const openFilterPanelAdmin  = document.getElementById('openFilterPanelAdmin');
        const filterPanelAdmin      = document.getElementById('filterPanelAdmin');
        const closeFilterPanelAdmin = document.getElementById('closeFilterPanelAdmin');
        const clearFiltersAdminBtn  = document.getElementById('clearFiltersAdmin');
        const filterFormAdmin       = document.getElementById('userFilterFormAdmin');

        const adminCards            = Array.from(document.querySelectorAll('#userListAdmin .user-card-container'));
        const paginationNavAdmin    = document.getElementById('paginationNavAdmin');
        const paginationLinksAdmin  = document.getElementById('paginationLinksAdmin');

        const filterUserAdminEl     = document.getElementById('filterUserAdmin');
        const filterGeneroAdminEl   = document.getElementById('filterGeneroAdmin');
        const filterAtivoAdminEl    = document.getElementById('filterAtivoAdmin');
        const filterDataAdminEl     = document.getElementById('filterDataAdmin');

        const perPageAdmin = <?= (int)$per_page_admin ?>;

        function getVisibleAdminCards() {
            return adminCards.filter(card => card.style.display !== 'none');
        }

        function renderPaginationAdmin(totalPages, currentPage) {
            if (!paginationNavAdmin || !paginationLinksAdmin) return;

            if (totalPages <= 1) {
                paginationNavAdmin.style.display = 'none';
                paginationLinksAdmin.innerHTML = '';
                return;
            }

            paginationNavAdmin.style.display = '';
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

            paginationLinksAdmin.innerHTML = html;
        }

        function showAdminPage(page) {
            const visibleCards = getVisibleAdminCards();
            const totalVisible = visibleCards.length;
            const totalPages   = Math.ceil(totalVisible / perPageAdmin) || 1;

            if (page < 1) page = 1;
            if (page > totalPages) page = totalPages;

            const start = (page - 1) * perPageAdmin;
            const end   = start + perPageAdmin;

            visibleCards.forEach((card, index) => {
                if (index >= start && index < end) {
                    card.style.visibility = '';
                    card.style.position   = '';
                } else {
                    card.style.visibility = 'hidden';
                    card.style.position   = 'absolute';
                }
            });

            renderPaginationAdmin(totalPages, page);
        }

        function applyAdminFilters() {
            const emailSel  = (filterUserAdminEl?.value || '').trim().toLowerCase();
            const generoSel = (filterGeneroAdminEl?.value || '').trim().toLowerCase();
            const ativoSel  = (filterAtivoAdminEl?.value || '').trim();
            const dataSel   = (filterDataAdminEl?.value || '').trim();

            adminCards.forEach(card => {
                const cardEmail  = (card.dataset.emailFilter || '').trim().toLowerCase();
                const cardGenero = (card.dataset.genero || '').trim().toLowerCase();
                const cardAtivo  = (card.dataset.ativo || '').trim();
                const cardData   = (card.dataset.dateAdmin || '').trim();

                const matchesEmail  = !emailSel || cardEmail === emailSel;
                const matchesGenero = !generoSel || cardGenero === generoSel;
                const matchesAtivo  = !ativoSel || cardAtivo === ativoSel;
                const matchesData   = !dataSel || cardData === dataSel;

                if (matchesEmail && matchesGenero && matchesAtivo && matchesData) {
                    card.style.display    = '';
                    card.style.visibility = '';
                    card.style.position   = '';
                } else {
                    card.style.display    = 'none';
                    card.style.visibility = '';
                    card.style.position   = '';
                }
            });

            showAdminPage(1);
        }

        if (openFilterPanelAdmin) {
            openFilterPanelAdmin.addEventListener('click', () => {
                filterPanelAdmin.classList.toggle('d-none');
            });
        }

        if (closeFilterPanelAdmin) {
            closeFilterPanelAdmin.addEventListener('click', () => {
                filterPanelAdmin.classList.add('d-none');
            });
        }

        if (clearFiltersAdminBtn) {
            clearFiltersAdminBtn.addEventListener('click', () => {
                if (filterDataAdminEl) filterDataAdminEl.value = '';

                const userSel = filterUserAdminEl?.tomselect;
                if (userSel) userSel.clear();

                const generoSel = filterGeneroAdminEl?.tomselect;
                if (generoSel) generoSel.clear();

                const ativoSel = filterAtivoAdminEl?.tomselect;
                if (ativoSel) ativoSel.clear();

                applyAdminFilters();
            });
        }

        if (filterFormAdmin) {
            filterFormAdmin.addEventListener('submit', function (e) {
                e.preventDefault();
                applyAdminFilters();
                filterPanelAdmin.classList.add('d-none');
            });
        }

        if (paginationLinksAdmin) {
            paginationLinksAdmin.addEventListener('click', function (e) {
                const link = e.target.closest('.page-link');
                if (!link) return;
                e.preventDefault();

                const li = link.parentElement;
                if (li.classList.contains('disabled')) return;

                const page = parseInt(link.dataset.page, 10);
                if (!isNaN(page)) {
                    showAdminPage(page);
                }
            });
        }

        if (adminCards.length > 0) {
            applyAdminFilters();
        } else if (paginationNavAdmin) {
            paginationNavAdmin.style.display = 'none';
        }

        document.addEventListener('click', function (e) {
            if (filterPanelFunc && openFilterPanelFunc && !filterPanelFunc.classList.contains('d-none')) {
                if (!filterPanelFunc.contains(e.target) && !openFilterPanelFunc.contains(e.target)) {
                    filterPanelFunc.classList.add('d-none');
                }
            }

            if (filterPanelAdmin && openFilterPanelAdmin && !filterPanelAdmin.classList.contains('d-none')) {
                if (!filterPanelAdmin.contains(e.target) && !openFilterPanelAdmin.contains(e.target)) {
                    filterPanelAdmin.classList.add('d-none');
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
   PARTE 2 – BLOQUEAR AUTO-DELETE
   ========================================================= */
if ($id == $_SESSION['user_id']) {
    header('Location: index.php?evora=removerutilizador');
    exit();
}

/* =========================================================
   PARTE 3 – CONFIRMAR PASSWORD DO ADMIN
   ========================================================= */
if (!isset($_SESSION['remove_verified']) || (int)$_SESSION['remove_verified'] !== (int)$id) {
    if (isset($_POST['confirm_pass'])) {
        $adminId = (int) $_SESSION['user_id'];

        $stmt = $conn->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $stmt->bind_result($adminHash);
        $stmt->fetch();
        $stmt->close();

        if (isset($_POST['admin_password']) && password_verify($_POST['admin_password'], $adminHash)) {
            $_SESSION['remove_verified'] = (int)$id;
            header('Location: index.php?evora=removerutilizador&id=' . $id);
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
        <title>Confirmar Remoção</title>
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
        <?php include "menu.php"; ?>
        <div id="main">
            <header class="mb-3 d-flex align-items-center">
                <a href="#" class="burger-btn d-block d-xl-none me-2">
                    <i class="bi bi-justify fs-3"></i>
                </a>
            </header>
            <div class="page-content">
                <div class="form-wrapper">
                    <div class="form-card">
                        <h3 class="mb-3 text-center">Confirmação de Segurança</h3>
                        <p class="text-subtitle text-muted text-center mb-4">
                            Insira a sua palavra-passe de administrador para remover o utilizador.
                        </p>
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        <form method="post" autocomplete="off" action="index.php?evora=removerutilizador&id=<?= $id ?>">
                            <div class="form-group mb-4">
                                <label for="admin_password">
                                    <i class="bi bi-lock me-1"></i> Palavra-passe
                                </label>
                                <input type="password" name="admin_password" id="admin_password" class="form-control" required minlength="8">
                            </div>
                            <input type="hidden" name="confirm_pass" value="1">
                            <button type="submit" class="btn btn-danger w-100">
                                <i class="bi bi-shield-check"></i> Confirmar
                            </button>
                        </form>
                        <a href="index.php?evora=removerutilizador" class="btn btn-secondary w-100 mt-2">
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
   PARTE 4 – CARREGAR UTILIZADOR PELO ID
   ========================================================= */
$stmt = $conn->prepare('SELECT * FROM users WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    ?>
    <!DOCTYPE html>
    <html lang="pt">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Utilizador não encontrado</title>
        <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
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
                        Utilizador não encontrado.
                        <a href="index.php?evora=removerutilizador" class="btn btn-sm btn-primary ms-2">Selecionar Outro</a>
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
   PARTE 5 – DELETE DEFINITIVO
   ========================================================= */
if (isset($_POST['remover_definitivo'])) {
    $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        regista_log(
            $conn,
            (int) $_SESSION['user_id'],
            'remover',
            'utilizador',
            $id,
            'Utilizador apagado.'
        );

        $adminId = (int) $_SESSION['user_id'];
        $acao    = 'Remoção de utilizador';
        $detalhe = 'Utilizador ID ' . $id . ' removido pelo administrador ID ' . $adminId . '.';

        $stmtAt = $conn->prepare("
            INSERT INTO atividade (user_id, acao, detalhe)
            VALUES (?, ?, ?)
        ");
        if ($stmtAt) {
            $stmtAt->bind_param('iss', $adminId, $acao, $detalhe);
            $stmtAt->execute();
            $stmtAt->close();
        }

        unset($_SESSION['remove_verified']);
        $success = 'Utilizador removido com sucesso!';
    } else {
        $error = 'Erro ao remover o utilizador: ' . $stmt->error;
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Confirmar Remoção</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
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
                <h3 class="mb-3 text-center">Remoção de Utilizador</h3>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($success) ?>
                    </div>

                    <a href="index.php?evora=removerutilizador" class="btn btn-primary w-100 mt-2">
                        <i class="bi bi-arrow-left"></i>
                        Voltar à lista
                    </a>

                <?php elseif ($error): ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars($error) ?>
                    </div>

                    <a href="index.php?evora=removerutilizador" class="btn btn-secondary w-100 mt-2">
                        <i class="bi bi-arrow-left"></i>
                        Voltar
                    </a>

                <?php else: ?>
                    <p class="mb-3">
                        Tem a certeza que pretende remover o utilizador seguinte?
                    </p>

                    <ul class="mb-3">
                        <li><strong>Nome:</strong> <?= htmlspecialchars($user['name']) ?></li>
                        <li><strong>Username:</strong> <?= htmlspecialchars($user['username']) ?></li>
                        <li><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></li>
                    </ul>

                    <form method="post" action="index.php?evora=removerutilizador&id=<?= $id ?>">
                        <button type="submit" name="remover_definitivo" class="btn btn-danger w-100">
                            Sim, remover definitivamente
                        </button>

                        <a href="index.php?evora=removerutilizador" class="btn btn-secondary w-100 mt-2">
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
