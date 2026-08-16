<?php
include './config.php';

// tem de estar logado
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// tem de ser admin
if (empty($_SESSION['is_admin']) || (int)$_SESSION['is_admin'] !== 1) {
    header('Location: index.php?evora=inicio');
    exit();
}

/* ========= PDF MULTI-SELEÇÃO (FUNCIONÁRIOS + ADMIN) ========= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? 'single';

    if (isset($_POST['btn_pdf']) && $mode === 'multi'
        && !empty($_POST['ids']) && is_array($_POST['ids'])) {

        $ids    = array_map('intval', $_POST['ids']);
        $idsStr = implode(',', $ids);

        header('Location: export_pdf.php?tipo=utilizadores&ids=' . urlencode($idsStr));
        exit();
    }
}

/* ================================
   CARREGAR DADOS (SEM PAGINAÇÃO)
   PARA CLIENT-SIDE FILTER/PAGE
   ================================ */

// Funcionários
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

// Admin
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

// Público
$sql_public = "
    SELECT id, nome, email, username, birthday, gender, phone, criado_em
    FROM users_public
    ORDER BY criado_em DESC, id DESC
";
$result_public = $conn->query($sql_public);

$generos_public_result = $conn->query("
    SELECT DISTINCT gender
    FROM users_public
    WHERE gender IS NOT NULL AND gender <> ''
    ORDER BY gender ASC
");

$users_public_filter_result = $conn->query("
    SELECT id, nome, username, email
    FROM users_public
    ORDER BY nome ASC, username ASC
");

// Itens por página (igual ao editar_utilizador)
$per_page_func   = 6;
$per_page_admin  = 6;
$per_page_public = 6;

?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Lista de Utilizadores</title>
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

        .admin-actions button,
        .public-actions button {
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

        .user-card-container,
        .user-card-container-public {
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

        .section-subtitle {
            font-size: 0.85rem;
            color: #9ca3af;
        }

        .multi-checkbox-wrapper {
            display: none;
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

        <div class="page-heading-custom mb-3">
            <h3 class="mb-1">Lista de Utilizadores</h3>
            <p class="text-subtitle text-muted mb-0">
                Consulte e filtre todos os utilizadores registados na plataforma.
            </p>
        </div>

        <div class="page-content">

            <!-- ===================== FUNCIONÁRIOS ===================== -->
            <section class="section mb-4">
                <div class="card card-main position-relative">
                    <div class="card-header border-0 pb-0">
                        <div class="card-header-flex">
                            <div>
                                <h4 class="mb-1">Funcionários</h4>
                                <span class="section-subtitle">
                                    Utilizadores internos com perfil de funcionário.
                                </span>
                            </div>
                            <div class="admin-actions d-flex align-items-center gap-2">
                                <button
                                    id="toggleSelectModeFunc"
                                    class="btn btn-outline-secondary d-flex align-items-center"
                                    type="button"
                                >
                                    <i class="bi bi-check2-square me-1"></i>
                                    <span>Modo seleção</span>
                                </button>

                                <button
                                    type="submit"
                                    class="btn btn-outline-secondary"
                                    id="pdfButtonFunc"
                                    name="btn_pdf"
                                    form="usersFormFunc"
                                >
                                    Exportar PDF
                                </button>

                                <button
                                    id="openFilterPanelFunc"
                                    class="btn btn-outline-secondary d-flex align-items-center"
                                    type="button"
                                >
                                    <i class="bi bi-funnel-fill me-1"></i>
                                    <span>Filtrar</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Painel de filtros FUNC (client-side) -->
                    <div id="filterPanelFunc" class="filter-panel d-none">
                        <form id="userFilterFormFunc">
                            <div class="mb-2">
                                <label for="filterUserFunc" class="form-label">Utilizador</label>
                                <select
                                    id="filterUserFunc"
                                    class="form-select js-nice-select"
                                >
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
                                <button type="button" class="btn btn-light btn-sm" id="clearFiltersFunc">
                                    Limpar
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="closeFilterPanelFunc">
                                    Cancelar
                                </button>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    Aplicar
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="card-body pt-3">
                        <?php if ($result_func && $result_func->num_rows > 0): ?>
                            <form method="post" id="usersFormFunc">
                                <input type="hidden" name="mode" id="deleteModeInputFunc" value="single">

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
                                                        <div class="form-check mb-0 multi-checkbox-wrapper">
                                                            <input
                                                                class="form-check-input msg-checkbox-func"
                                                                type="checkbox"
                                                                name="ids[]"
                                                                value="<?= (int)$u['id'] ?>"
                                                                id="userFuncChk<?= (int)$u['id'] ?>"
                                                            >
                                                            <label class="form-check-label" for="userFuncChk<?= (int)$u['id'] ?>">
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

            <!-- ===================== ADMIN ===================== -->
            <section class="section mb-4">
                <div class="card card-main position-relative">
                    <div class="card-header border-0 pb-0">
                        <div class="card-header-flex">
                            <div>
                                <h4 class="mb-1">Utilizadores Admin</h4>
                                <span class="section-subtitle">
                                    Use os filtros para encontrar utilizadores admin.
                                </span>
                            </div>
                            <div class="admin-actions d-flex align-items-center gap-2">
                                <button id="toggleSelectMode" class="btn btn-outline-secondary d-flex align-items-center" type="button">
                                    <i class="bi bi-check2-square me-1"></i><span>Modo seleção</span>
                                </button>
                                <button type="submit" class="btn btn-outline-secondary" id="pdfButton" name="btn_pdf" form="usersForm">
                                    Exportar PDF
                                </button>
                                <button id="openFilterPanelAdmin" class="btn btn-outline-secondary d-flex align-items-center" type="button">
                                    <i class="bi bi-funnel-fill me-1"></i><span>Filtrar</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Painel de filtros ADMIN (client-side) -->
                    <div id="filterPanelAdmin" class="filter-panel d-none">
                        <form id="userFilterFormAdmin">
                            <div class="mb-2">
                                <label for="filterUserAdmin" class="form-label">Utilizador</label>
                                <select
                                    id="filterUserAdmin"
                                    class="form-select js-nice-select"
                                >
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
                            <form method="post" id="usersForm">
                                <input type="hidden" name="mode" id="deleteModeInput" value="single">
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
                                                        <span>Criado em <?= !empty($u['created_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($u['created_at']))) : '—' ?></span>
                                                        <div class="form-check mb-0 multi-checkbox-wrapper">
                                                            <input class="form-check-input msg-checkbox" type="checkbox" name="ids[]"
                                                                   value="<?= (int)$u['id'] ?>" id="userChk<?= (int)$u['id'] ?>">
                                                            <label class="form-check-label" for="userChk<?= (int)$u['id'] ?>">Selecionar</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                </div>
                            </form>

                            <nav id="paginationNavAdmin" aria-label="Paginação de admins" class="mt-3 mb-1">
                                <ul class="pagination justify-content-center flex-wrap" id="paginationLinksAdmin"></ul>
                            </nav>
                        <?php else: ?>
                            <div class="alert alert-info text-center mb-0">Nenhum utilizador admin registado.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- ===================== PÚBLICO ===================== -->
            <section class="section">
                <div class="card card-main position-relative">
                    <div class="card-header">
                        <div class="card-header-flex">
                            <div>
                                <h4 class="mb-1">Utilizadores Públicos</h4>
                                <span class="section-subtitle">Lista de utilizadores visível ao público.</span>
                            </div>
                            <div class="public-actions d-flex align-items-center gap-2">
                                <button id="openFilterPanelPublic" class="btn btn-outline-secondary d-flex align-items-center" type="button">
                                    <i class="bi bi-funnel-fill me-1"></i><span>Filtrar</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Painel de filtros PÚBLICO (client-side) -->
                    <div id="filterPanelPublic" class="filter-panel d-none">
                        <form id="userFilterFormPublic">
                            <div class="mb-2">
                                <label for="filterUserPublic" class="form-label">Utilizador (email)</label>
                                <select
                                    id="filterUserPublic"
                                    class="form-select js-nice-select"
                                >
                                    <option value="">Todos</option>
                                    <?php if ($users_public_filter_result && $users_public_filter_result->num_rows > 0): ?>
                                        <?php while ($fpu = $users_public_filter_result->fetch_assoc()): ?>
                                            <?php
                                            $fn = trim($fpu['nome'] ?? '');
                                            $un = trim($fpu['username'] ?? '');
                                            $em = trim($fpu['email'] ?? '');
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
                                <label for="filterGeneroPublic" class="form-label">Género</label>
                                <select id="filterGeneroPublic" class="form-select js-nice-select">
                                    <option value="">Todos</option>
                                    <?php if ($generos_public_result && $generos_public_result->num_rows > 0): ?>
                                        <?php while ($g = $generos_public_result->fetch_assoc()): ?>
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
                                <label for="filterDataPublic" class="form-label">Criado em</label>
                                <input type="date" class="form-control" id="filterDataPublic">
                            </div>

                            <div class="filter-panel-actions">
                                <button type="button" class="btn btn-light btn-sm" id="clearFiltersPublic">Limpar</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="closeFilterPanelPublic">Cancelar</button>
                                <button type="submit" class="btn btn-primary btn-sm">Aplicar</button>
                            </div>
                        </form>
                    </div>

                    <div class="card-body">
                        <?php if ($result_public && $result_public->num_rows > 0): ?>
                            <div class="container-fluid">
                                <div class="row" id="userListPublic">
                                    <?php while ($u = $result_public->fetch_assoc()): ?>
                                        <?php
                                        $createdDateYmd = !empty($u['criado_em']) ? date('Y-m-d', strtotime($u['criado_em'])) : '';
                                        $gender_raw   = $u['gender'] ?? '';
                                        $gender_lower = strtolower(trim($gender_raw));
                                        ?>
                                        <div class="col-12 col-md-6 col-xl-4 user-card-container-public"
                                             data-email-filter="<?= htmlspecialchars(strtolower($u['email'] ?? '')) ?>"
                                             data-genero="<?= htmlspecialchars($gender_lower) ?>"
                                             data-date-public="<?= htmlspecialchars($createdDateYmd) ?>">
                                            <div class="user-card">
                                                <div class="user-card-body">
                                                    <div class="user-title d-flex justify-content-between align-items-center">
                                                        <span><?= htmlspecialchars($u['nome'] ?: $u['username'] ?: '') ?></span>
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
                                                    <span>Criado em <?= !empty($u['criado_em']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($u['criado_em']))) : '—' ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>

                            <nav id="paginationNavPublic" aria-label="Paginação de público" class="mt-3 mb-1">
                                <ul class="pagination justify-content-center flex-wrap" id="paginationLinksPublic"></ul>
                            </nav>
                        <?php else: ?>
                            <div class="alert alert-info text-center mb-0">Nenhum utilizador público.</div>
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
    // TomSelect single (igual editar_utilizador)
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

    // Sidebar mobile
    const burgerBtn      = document.querySelector('.burger-btn');
    const sidebarWrapper = document.querySelector('.sidebar-wrapper');

    if (burgerBtn && sidebarWrapper) {
        burgerBtn.addEventListener('click', function (e) {
            e.preventDefault();
            sidebarWrapper.classList.toggle('active');
        });
    }

    /* =========================
       FUNCIONÁRIOS – FILTER + PAG.
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
       ADMIN – FILTER + PAG.
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

    /* =========================
       PÚBLICO – FILTER + PAG.
       ========================= */
    const openFilterPanelPublic  = document.getElementById('openFilterPanelPublic');
    const filterPanelPublic      = document.getElementById('filterPanelPublic');
    const closeFilterPanelPublic = document.getElementById('closeFilterPanelPublic');
    const clearFiltersPublicBtn  = document.getElementById('clearFiltersPublic');
    const filterFormPublic       = document.getElementById('userFilterFormPublic');

    const publicCards        = Array.from(document.querySelectorAll('#userListPublic .user-card-container-public'));
    const paginationNavPublic   = document.getElementById('paginationNavPublic');
    const paginationLinksPublic = document.getElementById('paginationLinksPublic');

    const filterUserPublicEl   = document.getElementById('filterUserPublic');
    const filterGeneroPublicEl = document.getElementById('filterGeneroPublic');
    const filterDataPublicEl   = document.getElementById('filterDataPublic');

    const perPagePublic = <?= (int)$per_page_public ?>;

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

    function applyPublicFilters() {
        const emailSel  = (filterUserPublicEl?.value || '').trim().toLowerCase();
        const generoSel = (filterGeneroPublicEl?.value || '').trim().toLowerCase();
        const dataSel   = (filterDataPublicEl?.value || '').trim();

        publicCards.forEach(card => {
            const cardEmail  = (card.dataset.emailFilter || '').trim().toLowerCase();
            const cardGenero = (card.dataset.genero || '').trim().toLowerCase();
            const cardData   = (card.dataset.datePublic || '').trim();

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

        showPublicPage(1);
    }

    if (openFilterPanelPublic) {
        openFilterPanelPublic.addEventListener('click', () => {
            filterPanelPublic.classList.toggle('d-none');
        });
    }

    if (closeFilterPanelPublic) {
        closeFilterPanelPublic.addEventListener('click', () => {
            filterPanelPublic.classList.add('d-none');
        });
    }

    if (clearFiltersPublicBtn) {
        clearFiltersPublicBtn.addEventListener('click', () => {
            if (filterDataPublicEl) filterDataPublicEl.value = '';

            const userSel = filterUserPublicEl?.tomselect;
            if (userSel) userSel.clear();

            const generoSel = filterGeneroPublicEl?.tomselect;
            if (generoSel) generoSel.clear();

            applyPublicFilters();
        });
    }

    if (filterFormPublic) {
        filterFormPublic.addEventListener('submit', function (e) {
            e.preventDefault();
            applyPublicFilters();
            filterPanelPublic.classList.add('d-none');
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
        applyPublicFilters();
    } else if (paginationNavPublic) {
        paginationNavPublic.style.display = 'none';
    }

    /* =========================
       MODO SELEÇÃO PDF ADMIN
       ========================= */
    const toggleSelectModeBtn = document.getElementById('toggleSelectMode');
    const deleteModeInput     = document.getElementById('deleteModeInput');
    const pdfButton           = document.getElementById('pdfButton');
    const mainForm            = document.getElementById('usersForm');
    let selectionMode         = false;

    function updateSelectionUI() {
        const checkboxWrappers = document.querySelectorAll('#usersForm .multi-checkbox-wrapper');
        if (selectionMode) {
            deleteModeInput.value = 'multi';
            checkboxWrappers.forEach(w => w.style.display = 'block');
            toggleSelectModeBtn.classList.add('active');
        } else {
            deleteModeInput.value = 'single';
            document.querySelectorAll('#usersForm .msg-checkbox').forEach(chk => chk.checked = false);
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

    if (pdfButton && mainForm) {
        pdfButton.addEventListener('click', (e) => {
            const anyChecked = Array.from(document.querySelectorAll('#usersForm .msg-checkbox')).some(chk => chk.checked);
            if (!anyChecked) {
                e.preventDefault();
                alert('Selecione pelo menos um utilizador para exportar o PDF.');
            } else {
                deleteModeInput.value = 'multi';
            }
        });
    }

    /* =========================
       MODO SELEÇÃO PDF FUNC
       ========================= */
    const toggleSelectModeFuncBtn = document.getElementById('toggleSelectModeFunc');
    const deleteModeInputFunc     = document.getElementById('deleteModeInputFunc');
    const pdfButtonFunc           = document.getElementById('pdfButtonFunc');
    const formFunc                = document.getElementById('usersFormFunc');
    let selectionModeFunc         = false;

    function updateSelectionUIFunc() {
        const checkboxWrappers = document.querySelectorAll('#usersFormFunc .multi-checkbox-wrapper');
        if (selectionModeFunc) {
            deleteModeInputFunc.value = 'multi';
            checkboxWrappers.forEach(w => w.style.display = 'block');
            toggleSelectModeFuncBtn.classList.add('active');
        } else {
            deleteModeInputFunc.value = 'single';
            document.querySelectorAll('#usersFormFunc .msg-checkbox-func').forEach(chk => chk.checked = false);
            checkboxWrappers.forEach(w => w.style.display = 'none');
            toggleSelectModeFuncBtn.classList.remove('active');
        }
    }

    if (toggleSelectModeFuncBtn) {
        toggleSelectModeFuncBtn.addEventListener('click', () => {
            selectionModeFunc = !selectionModeFunc;
            updateSelectionUIFunc();
        });
    }

    if (pdfButtonFunc && formFunc) {
        pdfButtonFunc.addEventListener('click', (e) => {
            const anyChecked = Array.from(document.querySelectorAll('#usersFormFunc .msg-checkbox-func')).some(chk => chk.checked);
            if (!anyChecked) {
                e.preventDefault();
                alert('Selecione pelo menos um funcionário para exportar o PDF.');
            } else {
                deleteModeInputFunc.value = 'multi';
            }
        });
    }
});
</script>
</body>
</html>
