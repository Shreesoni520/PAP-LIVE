<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
 
include './log.php';
 
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
 
$success = '';
$error   = '';
 
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
 
/* =========================
   CONFIG EMAIL
   ========================= */
$logoUrl   = 'https://lucky-plum-7yxiihmoh3.edgeone.app/logom.png';
$siteName  = 'Reporta Évora';
$publicUrl = 'http://localhost/PAP/index.php?evora_p=inicio';
 
function sendEmailChangeCode(string $toEmail, string $code, string &$errorOut = null): bool {
    global $logoUrl, $siteName, $publicUrl;
 
    $subject = "Código para confirmar alteração de email - {$siteName}";
 
    $body  = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    $body .= '<title>Confirmação de alteração de email</title></head>';
    $body .= '<body style="margin:0;padding:0;background-color:#f3f4f6;';
    $body .= 'font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;">';
 
    $body .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" ';
    $body .= 'style="background-color:#f3f4f6;padding:24px 0;"><tr><td align="center">';
    $body .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" ';
    $body .= 'style="max-width:640px;background-color:#ffffff;border-radius:16px;';
    $body .= 'border:1px solid #e5e7eb;padding:20px 24px;color:#111827;">';
 
    $body .= '<tr><td style="padding-bottom:18px;border-bottom:1px solid #e5e7eb;">';
    $body .= '<div style="text-align:center;">';
    $body .= '<a href="' . htmlspecialchars($publicUrl, ENT_QUOTES, 'UTF-8') . '" style="text-decoration:none;display:inline-block;">';
    $body .= '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '"';
    $body .= ' style="max-width:220px;height:auto;display:block;margin:0 auto 6px auto;border:0;">';
    $body .= '</a>';
    $body .= '<div style="font-size:12px;color:#6b7280;">Painel de administração</div>';
    $body .= '</div>';
    $body .= '</td></tr>';
 
    $body .= '<tr><td style="padding-top:18px;padding-bottom:8px;">';
    $body .= '<h2 style="margin:0;font-size:20px;line-height:1.3;color:#111827;">Confirme a alteração de email</h2>';
    $body .= '</td></tr>';
 
    $body .= '<tr><td style="padding-bottom:10px;">';
    $body .= '<p style="margin:0;color:#4b5563;font-size:14px;line-height:1.6;">';
    $body .= 'Recebemos um pedido para alterar o email associado a uma conta em ';
    $body .= '<strong>' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '</strong>. ';
    $body .= 'Introduza o código abaixo no painel de administração para confirmar que este email é válido.';
    $body .= '</p>';
    $body .= '</td></tr>';
 
    $body .= '<tr><td style="padding-top:10px;padding-bottom:6px;">';
    $body .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" ';
    $body .= 'style="background-color:#f9fafb;border-radius:12px;border:1px solid #e5e7eb;';
    $body .= 'padding:14px 16px;text-align:center;">';
    $body .= '<tr><td style="padding-bottom:8px;">';
    $body .= '<div style="font-size:12px;color:#6b7280;font-weight:600;margin-bottom:4px;">Código de verificação</div>';
    $body .= '<div style="font-size:26px;letter-spacing:.25em;font-weight:700;color:#111827;">';
    $body .= htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    $body .= '</div></td></tr>';
    $body .= '<tr><td style="padding-top:4px;">';
    $body .= '<div style="font-size:12px;color:#6b7280;">Este código é válido durante 10 minutos.</div>';
    $body .= '</td></tr></table>';
    $body .= '</td></tr>';
 
    $body .= '<tr><td style="padding-top:10px;padding-bottom:4px;">';
    $body .= '<p style="margin:0;color:#6b7280;font-size:12px;line-height:1.6;">';
    $body .= 'Se não reconhece este pedido, pode ignorar este email.';
    $body .= '</p></td></tr>';
 
    $body .= '<tr><td style="padding-top:16px;font-size:12px;color:#6b7280;border-top:1px solid #e5e7eb;padding-bottom:4px;">';
    $body .= 'Este email foi enviado automaticamente pelo sistema ';
    $body .= '<strong>' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '</strong>. Por favor, não responda diretamente a esta mensagem.';
    $body .= '</td></tr>';
 
    $body .= '</table></td></tr></table></body></html>';
 
    $headers  = "From: Reporta Evora <no-reply@example.com>\r\n";
    $headers .= "Reply-To: no-reply@example.com\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
 
    $ok = mail($toEmail, $subject, $body, $headers);
 
    if (!$ok) {
        $errorOut = "mail() devolveu false.";
    }
 
    return $ok;
}
 
/* =========================
   PARTE 1 – SELECIONAR UTILIZADOR
   CLIENT-SIDE FILTER + CLIENT-SIDE PAGINATION
   ========================= */
if ($id === 0) {
 
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
        <title>Selecionar Utilizador</title>
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
        <?php include "menu.php"; ?>
        <div id="main">
            <header class="mb-3 d-flex align-items-center">
                <a href="#" class="burger-btn d-block d-xl-none me-2">
                    <i class="bi bi-justify fs-3"></i>
                </a>
            </header>
 
            <div class="page-heading mb-3">
                <h3 class="mb-1">Selecionar Utilizador</h3>
                <p class="text-subtitle text-muted">Escolha um utilizador para editar.</p>
            </div>
 
            <div class="page-content">
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
                                                        <a href="index.php?evora=editarutilizador&id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-primary">
                                                            Editar
                                                        </a>
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
                                                        <a href="index.php?evora=editarutilizador&id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-primary">
                                                            Editar
                                                        </a>
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
    exit;
}
 
/* =========================
   PARTE 2 – CONFIRMAÇÃO PASSWORD ADMIN
   ========================= */
if (!isset($_POST['editutilizador']) && !isset($_POST['confirm_email_change'])) {
    if (!isset($_POST['confirmpass'])) {
        $showConfirmForm = true;
    } else {
        $adminId = (int)$_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $stmt->bind_result($adminHash);
        $stmt->fetch();
        $stmt->close();
 
        if (isset($_POST['adminpassword']) && password_verify($_POST['adminpassword'], $adminHash)) {
            $showConfirmForm = false;
        } else {
            $error = 'Palavra-passe incorreta!';
            $showConfirmForm = true;
        }
    }
 
    if (!empty($showConfirmForm)) {
        ?>
        <!DOCTYPE html>
        <html lang="pt">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Confirmação de Palavra-passe</title>
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
                .btn-primary { border-radius:8px; font-weight:600; }
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
                                Insira a sua palavra-passe de administrador para editar o utilizador.
                            </p>
                            <?php if (!empty($error)): ?>
                                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                            <?php endif; ?>
                            <form method="post" autocomplete="off" action="index.php?evora=editarutilizador&id=<?= $id ?>">
                                <div class="form-group mb-4">
                                    <label for="adminpassword">
                                        <i class="bi bi-lock me-1"></i> Palavra-passe
                                    </label>
                                    <input type="password" name="adminpassword" id="adminpassword" class="form-control" required minlength="8">
                                </div>
                                <input type="hidden" name="confirmpass" value="1">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-shield-check"></i> Confirmar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
 
        <script src="assets/js/bootstrap.bundle.min.js"></script>
        <script src="assets/js/main.js"></script>
        </body>
        </html>
        <?php
        exit;
    }
}
 
/* =========================
   PARTE 3 – CARREGAR DADOS DO UTILIZADOR
   ========================= */
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$result     = $stmt->get_result();
$utilizador = $result->fetch_assoc();
$stmt->close();
 
if (!$utilizador) {
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
                        Utilizador não encontrado.
                        <a href="index.php?evora=editarutilizador" class="btn btn-sm btn-primary ms-2">Selecionar Utilizador</a>
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
    exit;
}
 
/* =========================
   PARTE 4 – CONFIRMAR ALTERAÇÃO DE EMAIL
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_email_change'])) {
    $code_input = trim($_POST['email_change_code'] ?? '');
    $user_id    = $_SESSION['pending_email_change_user_id'] ?? 0;
 
    if ($code_input === '' || !$user_id || $user_id !== $id) {
        $error = 'Código ou sessão inválidos. Volte a editar o utilizador.';
    } else {
        $stmt = $conn->prepare("
            SELECT id, new_email, new_username, new_name, new_birthday, new_gender, new_phone, new_password_hash, expires_at, used
            FROM pending_user_email_changes
            WHERE user_id = ? AND code = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->bind_param("is", $user_id, $code_input);
        $stmt->execute();
        $stmt->bind_result($pend_id, $new_email, $new_username, $new_name, $new_birthday, $new_gender, $new_phone, $new_password_hash, $expires_at, $used);
 
        if ($stmt->fetch()) {
            $stmt->close();
 
            $now = new DateTime();
            $exp = DateTime::createFromFormat('Y-m-d H:i:s', $expires_at);
 
            if ($used) {
                $error = 'Este código já foi utilizado. Volte a editar o utilizador.';
            } elseif ($exp === false || $now > $exp) {
                $error = 'O código expirou. Volte a editar o utilizador.';
            } else {
                $stmt = $conn->prepare("UPDATE pending_user_email_changes SET used = 1 WHERE id = ?");
                $stmt->bind_param("i", $pend_id);
                $stmt->execute();
                $stmt->close();
 
                $current_is_admin = (int)$utilizador['is_admin'];
 
                $stmt = $conn->prepare("
                    UPDATE users
                    SET email = ?, username = ?, name = ?, birthday = ?, gender = ?, phone = ?, password = ?, is_admin = ?
                    WHERE id = ?
                ");
                $stmt->bind_param(
                    "sssssssii",
                    $new_email,
                    $new_username,
                    $new_name,
                    $new_birthday,
                    $new_gender,
                    $new_phone,
                    $new_password_hash,
                    $current_is_admin,
                    $user_id
                );
 
                if ($stmt->execute()) {
                    $success = 'Alteração de email e restantes dados confirmada com sucesso.';
                    regista_log($conn, $_SESSION['user_id'], 'editar', 'utilizador', $user_id, "Email e dados do utilizador alterados com verificação.");
                    unset($_SESSION['pending_email_change_user_id']);
 
                    $utilizador['email']    = $new_email;
                    $utilizador['username'] = $new_username;
                    $utilizador['name']     = $new_name;
                    $utilizador['birthday'] = $new_birthday;
                    $utilizador['gender']   = $new_gender;
                    $utilizador['phone']    = $new_phone;
                } else {
                    $error = 'Erro ao aplicar alterações: ' . $stmt->error;
                }
                $stmt->close();
            }
        } else {
            $stmt->close();
            $error = 'Código inválido. Verifique o código que recebeu no email.';
        }
    }
}
 
/* =========================
   PARTE 5 – PROCESSAR EDIÇÃO
   ========================= */
if (isset($_POST['editutilizador'])) {
    $email           = trim($_POST['email'] ?? '');
    $username        = trim($_POST['username'] ?? '');
    $name            = trim($_POST['name'] ?? '');
    $birthday        = $_POST['birthday'] ?? '';
    $gender          = $_POST['gender'] ?? '';
    $phone           = trim($_POST['fullphone'] ?? '');
    $password        = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirmpassword'] ?? '');
    $role            = $_POST['role'] ?? '';
 
    if (!$phone) {
        $error = 'O número de telefone é obrigatório.';
    } elseif (!preg_match('/^\+?[1-9]\d{6,14}$/', $phone)) {
        $error = 'Número de telefone inválido.';
    }
 
    if (!$error && (!$email || !$username || !$name || !$birthday || !$gender || !$phone || !$role)) {
        $error = 'Todos os campos, exceto password, são obrigatórios.';
    } elseif (!$error && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Formato de email inválido.';
    } elseif (!$error && !preg_match('/^[a-zA-Z_]{3,32}$/', $username)) {
        $error = 'Nome de utilizador inválido. Deve conter apenas letras ou underscore e ter entre 3 e 32 caracteres.';
    } elseif (!$error && !in_array($role, ['funcionario', 'admin'], true)) {
        $error = 'Tipo de utilizador inválido.';
    } elseif (!$error && $password && $password !== $confirmPassword) {
        $error = 'As palavras-passe não coincidem.';
    } elseif (!$error && $password && strlen($password) < 8) {
        $error = 'Nova palavra-passe deve ter pelo menos 8 caracteres.';
    }
 
    if (!$error && $birthday) {
        $dataNasc = DateTime::createFromFormat('Y-m-d', $birthday);
        if (!$dataNasc) {
            $error = 'Data de nascimento inválida. Use o seletor de data.';
        } else {
            $hoje = new DateTime('today');
            if ($dataNasc > $hoje) {
                $dataNasc = $hoje;
                $birthday = $hoje->format('Y-m-d');
            } else {
                $birthday = $dataNasc->format('Y-m-d');
            }
            $idade = $dataNasc->diff($hoje)->y;
            if ($idade < 18) {
                $error = 'O utilizador deve ter pelo menos 18 anos.';
            }
        }
    }
 
    if (!$error) {
        $stmt = $conn->prepare("
            SELECT id FROM users
            WHERE (email = ? OR username = ?) AND id != ?
        ");
        $stmt->bind_param('ssi', $email, $username, $id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = 'Já existe um utilizador com este email ou nome de utilizador.';
        }
        $stmt->close();
    }
 
    if (!$error) {
        $stmt = $conn->prepare("
            SELECT id FROM users
            WHERE phone = ? AND id != ?
            LIMIT 1
        ");
        $stmt->bind_param('si', $phone, $id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = 'Este número de telefone já está a ser utilizado por outro utilizador.';
        }
        $stmt->close();
    }
 
    if (!$error) {
        $email_changed    = ($email !== $utilizador['email']);
        $password_changed = !empty($password);
 
        if ($password_changed) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        } else {
            $passwordHash = $utilizador['password'];
        }
 
        $is_admin = ($role === 'admin') ? 1 : 0;
 
        if (!$email_changed) {
            $stmtUpdate = $conn->prepare("
                UPDATE users
                SET email = ?, username = ?, name = ?, birthday = ?, gender = ?, phone = ?, password = ?, is_admin = ?
                WHERE id = ?
            ");
            $stmtUpdate->bind_param(
                'sssssssii',
                $email,
                $username,
                $name,
                $birthday,
                $gender,
                $phone,
                $passwordHash,
                $is_admin,
                $id
            );
 
            if ($stmtUpdate->execute()) {
                header('Location: index.php?evora=editarutilizador');
                exit();
            } else {
                $error = 'Erro ao atualizar: ' . $stmtUpdate->error;
            }
            $stmtUpdate->close();
        } else {
            $code       = (string) random_int(100000, 999999);
            $expires_at = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');
            $created_at = date('Y-m-d H:i:s');
 
            $del = $conn->prepare("DELETE FROM pending_user_email_changes WHERE user_id = ?");
            $del->bind_param("i", $id);
            $del->execute();
            $del->close();
 
            $stmt = $conn->prepare("
                INSERT INTO pending_user_email_changes
                (user_id, new_email, new_username, new_name, new_birthday, new_gender, new_phone, new_password_hash, code, expires_at, used, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)
            ");
            $stmt->bind_param(
                "issssssssss",
                $id,
                $email,
                $username,
                $name,
                $birthday,
                $gender,
                $phone,
                $passwordHash,
                $code,
                $expires_at,
                $created_at
            );
 
            if ($stmt->execute()) {
                $stmt->close();
                $mailError = null;
                if (sendEmailChangeCode($email, $code, $mailError)) {
                    $success = 'Enviámos um código para o novo email. Introduza-o abaixo para confirmar a alteração.';
                    $_SESSION['pending_email_change_user_id'] = $id;
                } else {
                    $error = 'Não foi possível enviar o email com o código. ' . $mailError;
                }
            } else {
                $error = 'Erro ao criar registo pendente de alteração de email: ' . $stmt->error;
                $stmt->close();
            }
        }
 
        $utilizador['email']    = $email;
        $utilizador['username'] = $username;
        $utilizador['name']     = $name;
        $utilizador['birthday'] = $birthday;
        $utilizador['gender']   = $gender;
        $utilizador['phone']    = $phone;
        $utilizador['is_admin'] = $is_admin;
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Editar Utilizador</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
 
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/css/tom-select.bootstrap5.min.css">
 
    <style>
        :root {
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-soft: #6b7280;
            --border-subtle: #e5e7eb;
            --primary: #435ebe;
            --primary-dark: #2e467f;
        }
        html, body {
            height: 100%;
        }
        body {
            font-family:'Nunito',sans-serif!important;
            background: linear-gradient(135deg, #eef2ff, #f9fafb);
            color: var(--text-main);
            margin: 0;
        }
        #app, #main {
            min-height: 100%;
        }
        .adduser-shell {
            padding-bottom: 32px;
        }
        .adduser-container {
            max-width: 1100px;
            margin: 18px auto 0 auto;
            padding: 0 12px 24px 12px;
        }
        .page-heading h2 {
            font-weight: 800;
            font-size: 1.9rem;
            color: var(--text-main);
        }
        .page-heading p {
            color: var(--text-soft);
            margin-bottom: 0;
        }
        .form-card {
            background: var(--card-bg);
            border-radius: 20px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.12);
            border: 1px solid rgba(148,163,184,0.20);
            padding: 28px 24px 24px 24px;
            margin-bottom: 20px;
        }
        .form-card h5 {
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 4px;
        }
        .form-card .form-group label {
            font-weight: 600;
            color: #2a355b;
            margin-bottom: 4px;
            display: block;
        }
        .form-card .form-control,
        .form-card .form-select {
            background: #f9fafb;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            font-size: 0.95rem;
        }
        .form-card .form-control:focus,
        .form-card .form-select:focus {
            background: #f3f4ff;
            border-color:#6366f1;
            box-shadow: 0 0 0 1px rgba(99,102,241,0.16);
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
        .btn-primary {
            background: var(--primary);
            border:none;
            border-radius: 8px;
            font-weight:600;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        .btn-voltar {
            border-radius: 8px;
            font-weight: 600;
            background: #e1e6f7;
            color: #29408c;
            border: none;
        }
        .btn-voltar:hover {
            background: #c9d3fa;
            color: #1f2b5c;
        }
        .intl-tel-input,
        .intl-tel-input .form-control {
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
            .form-card {
                padding: 24px 18px 20px 18px;
            }
        }
    </style>
</head>
<body>
<div id="app">
    <?php include "menu.php"; ?>
 
    <div id="main" class="adduser-shell">
        <header class="mb-3 d-flex align-items-center">
            <a href="#" class="burger-btn d-block d-xl-none me-2">
                <i class="bi bi-justify fs-3"></i>
            </a>
        </header>
 
        <div class="adduser-container">
            <div class="page-heading mb-3 text-center text-md-start">
                <h2>Editar Utilizador</h2>
                <p>Atualize os dados do utilizador selecionado.</p>
            </div>
 
            <div class="form-card">
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php elseif ($error): ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
 
                <form method="post" autocomplete="off" id="edit-user-form" action="index.php?evora=editarutilizador&id=<?= $id ?>">
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <div class="form-group">
                                <label for="name">
                                    <i class="bi bi-card-text me-1"></i> Nome completo
                                </label>
                                <input
                                    type="text"
                                    name="name"
                                    id="name"
                                    class="form-control"
                                    required
                                    value="<?= htmlspecialchars($utilizador['name']) ?>"
                                >
                            </div>
                        </div>
 
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="username">
                                    <i class="bi bi-person me-1"></i> Nome de utilizador (login)
                                </label>
                                <input
                                    type="text"
                                    name="username"
                                    id="username"
                                    class="form-control"
                                    required
                                    pattern="[a-zA-Z_]{3,32}"
                                    title="3 a 32 letras maiúsculas ou minúsculas ou underscore (_), sem números nem outros caracteres especiais"
                                    value="<?= htmlspecialchars($utilizador['username']) ?>"
                                >
                            </div>
                        </div>
                    </div>
 
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <div class="form-group">
                                <label for="email">
                                    <i class="bi bi-envelope me-1"></i> Email
                                </label>
                                <input
                                    type="email"
                                    name="email"
                                    id="email"
                                    class="form-control"
                                    required
                                    value="<?= htmlspecialchars($utilizador['email']) ?>"
                                >
                            </div>
                        </div>
 
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="birthday">
                                    <i class="bi bi-calendar me-1"></i> Data de nascimento
                                </label>
                                <input
                                    type="date"
                                    name="birthday"
                                    id="birthday"
                                    class="form-control"
                                    required
                                    value="<?= htmlspecialchars($utilizador['birthday']) ?>"
                                    max="<?= date('Y-m-d') ?>"
                                >
                            </div>
                        </div>
                    </div>
 
                    <div class="row mb-3">
                        <div class="col-md-4 mb-3 mb-md-0">
                            <div class="form-group">
                                <label for="gender">
                                    <i class="bi bi-gender-ambiguous me-1"></i> Género
                                </label>
                                <select
                                    name="gender"
                                    id="gender"
                                    class="form-select js-nice-select"
                                    required
                                >
                                    <option value="">Selecione o género</option>
                                    <option value="male"   <?= $utilizador['gender'] === 'male'   ? 'selected' : '' ?>>Masculino</option>
                                    <option value="female" <?= $utilizador['gender'] === 'female' ? 'selected' : '' ?>>Feminino</option>
                                    <option value="other"  <?= $utilizador['gender'] === 'other'  ? 'selected' : '' ?>>Outro</option>
                                </select>
                            </div>
                        </div>
 
                        <div class="col-md-4 mb-3 mb-md-0">
                            <div class="form-group">
                                <label for="phone">
                                    <i class="bi bi-telephone me-1"></i> Telefone
                                </label>
                                <input
                                    type="tel"
                                    id="phone"
                                    name="phonedisplay"
                                    class="form-control"
                                    required
                                >
                                <input type="hidden" name="fullphone" id="fullphone">
                            </div>
                        </div>
 
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="role">
                                    <i class="bi bi-people me-1"></i> Tipo de utilizador
                                </label>
                                <select
                                    name="role"
                                    id="role"
                                    class="form-select js-nice-select"
                                    required
                                >
                                    <option value="">Selecione o tipo</option>
                                    <option value="funcionario" <?= !$utilizador['is_admin'] ? 'selected' : '' ?>>
                                        Funcionário
                                    </option>
                                    <option value="admin" <?= $utilizador['is_admin'] ? 'selected' : '' ?>>
                                        Administrador
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
 
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <div class="form-group">
                                <label for="password">
                                    <i class="bi bi-lock me-1"></i> Nova palavra-passe (opcional)
                                </label>
                                <input
                                    type="password"
                                    name="password"
                                    id="password"
                                    class="form-control"
                                    minlength="8"
                                >
                            </div>
                        </div>
 
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="confirmpassword">
                                    <i class="bi bi-lock me-1"></i> Confirmar palavra-passe
                                </label>
                                <input
                                    type="password"
                                    name="confirmpassword"
                                    id="confirmpassword"
                                    class="form-control"
                                    minlength="8"
                                >
                            </div>
                        </div>
                    </div>
 
                    <div class="d-flex flex-row flex-md-row justify-content-center justify-content-md-end align-items-center gap-2 mt-2 mb-1">
                        <button
                            type="submit"
                            name="editutilizador"
                            class="btn btn-primary w-100 w-md-auto"
                        >
                            <i class="bi bi-save me-1"></i> Guardar alterações
                        </button>
                        <a href="index.php?evora=editarutilizador" class="btn btn-voltar w-100 w-md-auto">
                            <i class="bi bi-arrow-left"></i> Voltar
                        </a>
                    </div>
                </form>
            </div>
 
            <?php if (isset($_SESSION['pending_email_change_user_id']) && $_SESSION['pending_email_change_user_id'] == $id): ?>
                <div class="form-card">
                    <h5>Confirmar alteração de email</h5>
                    <small>Introduza o código que foi enviado para o novo email do utilizador.</small>
 
                    <form method="post" autocomplete="off" class="mt-3">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="email_change_code">
                                        <i class="bi bi-shield-lock me-1"></i> Código de verificação
                                    </label>
                                    <input
                                        type="text"
                                        name="email_change_code"
                                        id="email_change_code"
                                        class="form-control"
                                        required
                                        placeholder="Ex: 123456"
                                    >
                                </div>
                            </div>
                        </div>
 
                        <div class="d-flex justify-content-end gap-2">
                            <button
                                type="submit"
                                name="confirm_email_change"
                                class="btn btn-primary"
                            >
                                Confirmar código e aplicar alterações
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
 
        </div>
    </div>
</div>
 
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const phoneInput     = document.querySelector("#phone");
    const fullPhoneInput = document.querySelector("#fullphone");
    const form           = document.querySelector("#edit-user-form");
 
    if (phoneInput && fullPhoneInput && form) {
        const iti = window.intlTelInput(phoneInput, {
            initialCountry: "pt",
            separateDialCode: true,
            preferredCountries: ["pt","br","fr","es","uk"],
            utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js"
        });
 
        <?php if (!empty($utilizador['phone'])): ?>
        iti.setNumber("<?= htmlspecialchars($utilizador['phone']) ?>");
        <?php endif; ?>
 
        form.addEventListener("submit", function (e) {
            if (phoneInput.value.trim()) {
                if (!iti.isValidNumber()) {
                    e.preventDefault();
                    alert("Por favor introduza um número de telefone válido.");
                    return;
                }
                fullPhoneInput.value = iti.getNumber();
            } else {
                fullPhoneInput.value = "";
            }
        });
    }
 
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
});
</script>
 
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>