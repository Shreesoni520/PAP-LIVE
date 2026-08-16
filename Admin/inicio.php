<?php
// get is_admin from session (same logic as menu)
$isAdmin = !empty($_SESSION['is_admin']) && (int) $_SESSION['is_admin'] === 1;
$currentUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

if (!function_exists('buildMonthlySeries')) {
    function buildMonthlySeries(mysqli $conn, string $table, string $dateColumn = 'completed_at'): array
    {
        $labelsMap = [];
        $seriesMap = [];

        $now = new DateTime('first day of this month');
        for ($i = 11; $i >= 0; $i--) {
            $clone = clone $now;
            $clone->modify("-{$i} month");
            $key = $clone->format('Y-m');
            $labelsMap[$key] = $clone->format('M Y');
            $seriesMap[$key] = 0;
        }

        // add noticias here
        $allowedTables = ['arvores', 'ocorrencias', 'ocorrencias_estrada', 'noticias'];
        if (!in_array($table, $allowedTables, true)) {
            return [
                'labels' => array_values($labelsMap),
                'series' => array_values($seriesMap),
            ];
        }

        $sql = "
            SELECT DATE_FORMAT({$dateColumn}, '%Y-%m') AS ym, COUNT(*) AS total
            FROM {$table}
            WHERE {$dateColumn} IS NOT NULL
              AND {$dateColumn} >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY ym
            ORDER BY ym ASC
        ";

        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $ym = $row['ym'];
                if (isset($seriesMap[$ym])) {
                    $seriesMap[$ym] = (int)$row['total'];
                }
            }
        }

        return [
            'labels' => array_values($labelsMap),
            'series' => array_values($seriesMap),
        ];
    }
}

if (!function_exists('buildTaskDistribution')) {
    function buildTaskDistribution(mysqli $conn, string $table): array
    {
        $labels = [];
        $series = [];

        $allowedTables = ['arvores', 'ocorrencias', 'ocorrencias_estrada'];
        if (!in_array($table, $allowedTables, true)) {
            return ['labels' => [], 'series' => []];
        }

        $sql = "
            SELECT estado, COUNT(*) AS total
            FROM {$table}
            WHERE estado IS NOT NULL AND TRIM(estado) <> ''
            GROUP BY estado
            ORDER BY total DESC, estado ASC
        ";

        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $labels[] = $row['estado'];
                $series[] = (int)$row['total'];
            }
        }

        return ['labels' => $labels, 'series' => $series];
    }
}

// Total de árvores
$res = $conn->query("SELECT COUNT(*) AS total FROM arvores");
$row = $res ? $res->fetch_assoc() : ['total' => 0];
$totalArvores = (int)($row['total'] ?? 0);

// Total de intervenções (continua disponível se precisa noutro lado)
$res = $conn->query("SELECT COUNT(*) AS total FROM intervencoes");
$row = $res ? $res->fetch_assoc() : ['total' => 0];
$totalIntervencoes = (int)($row['total'] ?? 0);

// Total de intervenções árvore
if ($isAdmin || $currentUserId <= 0) {
    $res = $conn->query("SELECT COUNT(*) AS total FROM arvores");
} else {
    $stmtTreeCount = $conn->prepare("SELECT COUNT(*) AS total FROM arvores WHERE assigned_to_user_id = ?");
    $stmtTreeCount->bind_param("i", $currentUserId);
    $stmtTreeCount->execute();
    $res = $stmtTreeCount->get_result();
}
$row = $res ? $res->fetch_assoc() : ['total' => 0];
$totalIntervencoesArvore = (int)($row['total'] ?? 0);
if (isset($stmtTreeCount) && $stmtTreeCount instanceof mysqli_stmt) {
    $stmtTreeCount->close();
}

// Total de ocorrências (espaços verdes)
$res = $conn->query("SELECT COUNT(*) AS total FROM ocorrencias");
$row = $res ? $res->fetch_assoc() : ['total' => 0];
$totalOcorrencias = (int)($row['total'] ?? 0);

// Total de ocorrências de estrada
$res = $conn->query("SELECT COUNT(*) AS total FROM ocorrencias_estrada");
$row = $res ? $res->fetch_assoc() : ['total' => 0];
$totalOcorrenciasEstrada = (int)($row['total'] ?? 0);

// Total de utilizadores
$res = $conn->query("SELECT COUNT(*) AS total FROM users");
$row = $res ? $res->fetch_assoc() : ['total' => 0];
$totalUtilizadores = (int)($row['total'] ?? 0);

// Total de notícias
$res = $conn->query("SELECT COUNT(*) AS total FROM noticias");
$row = $res ? $res->fetch_assoc() : ['total' => 0];
$totalNoticias = (int)($row['total'] ?? 0);

/**
 * DADOS DOS GRÁFICOS – agora com NOTÍCIAS
 */
$chartData = [
    'noticias' => [
        'title'    => 'Notícias publicadas nos últimos 12 meses',
        'subtitle' => 'Baseado em noticias.data_publicacao.',
        'monthly'  => buildMonthlySeries($conn, 'noticias', 'data_publicacao'),
        'tasks'    => ['labels' => [], 'series' => []], // sem donut para notícias
    ],
    'espaco_verde' => [
        'title'    => 'Intervenções de Espaço Verde nos últimos 12 meses',
        'subtitle' => 'Baseado em arvores.completed_at.',
        'monthly'  => buildMonthlySeries($conn, 'arvores', 'completed_at'),
        'tasks'    => buildTaskDistribution($conn, 'arvores'),
    ],
    'ocorrencias' => [
        'title'    => 'Intervenções de Ocorrências nos últimos 12 meses',
        'subtitle' => 'Baseado em ocorrencias.completed_at.',
        'monthly'  => buildMonthlySeries($conn, 'ocorrencias', 'completed_at'),
        'tasks'    => buildTaskDistribution($conn, 'ocorrencias'),
    ],
    'ocorrencias_estrada' => [
        'title'    => 'Intervenções de Ocorrências Estrada nos últimos 12 meses',
        'subtitle' => 'Baseado em ocorrencias_estrada.completed_at.',
        'monthly'  => buildMonthlySeries($conn, 'ocorrencias_estrada', 'completed_at'),
        'tasks'    => buildTaskDistribution($conn, 'ocorrencias_estrada'),
    ],
];

/**
 * ATIVIDADE – só admin
 */
$atividade = [];

if ($isAdmin) {
    $res = $conn->query("
        SELECT a.*, u.username
        FROM atividade a
        LEFT JOIN users u ON a.user_id = u.id
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $atividade[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Controlo</title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/css/tom-select.bootstrap5.min.css">

    <style>
        :root {
            --primary: #435ebe;
            --primary-soft: #eef2ff;
            --bg-page: #f3f4f6;
            --card-radius: 18px;
        }
        html, body { height: 100%; overflow-x: hidden; }
        body {
            font-family: 'Nunito', sans-serif !important;
            margin: 0;
            background: radial-gradient(circle at top left, #e0ecff 0, #f3f4f6 42%, #edf2ff 100%);
        }
        #app, #main { min-height: 100%; }
        .page-heading h3 { font-weight: 800; color: #111827; margin-bottom: 6px; }
        .page-heading p { color: #6b7280; margin-bottom: 0; }
        .dashboard-shell { padding-bottom: 32px; }
        .card-elevated {
            border-radius: var(--card-radius);
            border: 1px solid rgba(148,163,184,0.25);
            box-shadow: 0 16px 40px rgba(15,23,42,0.12);
            background: #ffffff;
            overflow: hidden;
        }
        .stat-card {
            padding: 18px;
            display: flex;
            align-items: center;
            gap: 14px;
            min-height: 92px;
        }
        .stat-icon {
            width: 46px;
            height: 46px;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            box-shadow: 0 12px 24px rgba(15,23,42,0.18);
            flex-shrink: 0;
        }
        .stat-icon.trees  { background: linear-gradient(135deg,#16a34a,#22c55e); }
        .stat-icon.inter  { background: linear-gradient(135deg,#0ea5e9,#2563eb); }
        .stat-icon.occur  { background: linear-gradient(135deg,#f97316,#ea580c); }
        .stat-icon.users  { background: linear-gradient(135deg,#6366f1,#4f46e5); }

        .stat-meta {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
        }
        .stat-label {
            font-size: 0.85rem;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .04em;
            line-height: 1.2;
            word-break: break-word;
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: #111827;
            line-height: 1.1;
        }
        .stat-sub {
            font-size: 0.8rem;
            color: #9ca3af;
            line-height: 1.3;
            word-break: break-word;
        }

        .card-section-title {
            font-weight: 700;
            color: #111827;
            margin-bottom: 4px;
            line-height: 1.3;
        }
        .card-section-sub {
            font-size: 0.85rem;
            color: #9ca3af;
            line-height: 1.45;
        }

        .chart-card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        .chart-filter-wrap {
            min-width: 220px;
            max-width: 280px;
        }
        .chart-summary {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 6px;
        }
        .summary-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            color: #475569;
            font-size: 0.78rem;
            font-weight: 700;
        }
        .summary-chip i { color: #4f46e5; }

        .activity-list { margin: 0; padding: 0; list-style: none; }
        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .activity-item:last-child { border-bottom: none; }
        .activity-dot {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            margin-top: 6px;
            flex-shrink: 0;
        }
        .activity-dot-green  { background: #22c55e; }
        .activity-dot-blue   { background: #3b82f6; }
        .activity-dot-orange { background: #f97316; }
        .activity-dot-red    { background: #ef4444; }
        .activity-text-main {
            font-size: 0.9rem;
            color: #111827;
            font-weight: 600;
            line-height: 1.4;
            word-break: break-word;
        }
        .activity-text-sub {
            font-size: 0.8rem;
            color: #6b7280;
            line-height: 1.45;
            word-break: break-word;
        }

        .quick-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            margin-bottom: 8px;
            text-decoration: none;
            color: #111827;
            transition: all .15s ease;
            min-height: 70px;
        }
        .quick-link:hover {
            background: #eef2ff;
            border-color: #4f46e5;
            text-decoration: none;
        }
        .quick-link > div { min-width: 0; }
        .quick-link span {
            font-size: 0.9rem;
            font-weight: 600;
            line-height: 1.3;
            word-break: break-word;
        }
        .quick-link small {
            font-size: 0.78rem;
            color: #6b7280;
            line-height: 1.35;
            display: inline-block;
            margin-top: 2px;
        }
        .quick-link i { color: #4f46e5; flex-shrink: 0; }

        #chart-intervencoes,
        #chart-tarefas {
            width: 100%;
            min-height: 220px;
        }

        .ts-wrapper { width: 100%; }
        .ts-wrapper .ts-control {
            background-color: #ffffff !important;
            border-radius: 10px !important;
            border: 1px solid #d1d5db !important;
            padding: 0.375rem 0.75rem !important;
            min-height: 38px !important;
            box-shadow: none !important;
            transform: none !important;
            transition: border-color .15s ease, box-shadow .15s ease !important;
        }
        .ts-wrapper.focus .ts-control,
        .ts-wrapper .ts-control:focus,
        .ts-wrapper.input-active .ts-control {
            border-color: #2563eb !important;
            box-shadow: 0 0 0 0.25rem rgba(37,99,235,0.25) !important;
            transform: none !important;
        }
        .ts-dropdown {
            background-color: #ffffff !important;
            border: 1px solid #d1d5db !important;
            border-radius: 10px !important;
            margin-top: 6px !important;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08) !important;
            overflow: hidden !important;
            transform: none !important;
            animation: none !important;
            transition: none !important;
        }
        .ts-dropdown .ts-dropdown-content {
            padding: 4px 0 !important;
            transform: none !important;
            animation: none !important;
            transition: none !important;
        }
        .ts-dropdown .option {
            padding: 10px 12px !important;
            font-size: 0.95rem !important;
            color: #111827 !important;
            background: #ffffff !important;
        }
        .ts-dropdown .option:hover,
        .ts-dropdown .active {
            background: #f3f4f6 !important;
            color: #111827 !important;
        }
        .ts-dropdown .selected {
            background: #eef2ff !important;
            color: #111827 !important;
        }
        .ts-wrapper .clear-button,
        .ts-control .clear-button { display: none !important; }
        .ts-wrapper *, .ts-dropdown * {
            animation: none !important;
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
        @media (max-width: 768px) {
            .dashboard-shell { padding-bottom: 16px; }
            .container-fluid {
                padding-left: 12px !important;
                padding-right: 12px !important;
            }
            .page-heading { margin-bottom: 14px !important; }
            .card-elevated { border-radius: 16px; }
            .stat-card {
                padding: 14px;
                gap: 10px;
                min-height: 84px;
            }
            .stat-icon {
                width: 40px;
                height: 40px;
            }
            .stat-label { font-size: 0.75rem; }
            .stat-value { font-size: 1.2rem; }
            .stat-sub { font-size: 0.74rem; }
            .card-body { padding: 14px !important; }
            .card-section-title { font-size: 0.98rem; }
            .card-section-sub { font-size: 0.8rem; }
            .activity-text-main { font-size: 0.86rem; }
            .activity-text-sub { font-size: 0.76rem; }
            .quick-link {
                padding: 12px;
                min-height: 66px;
            }
            .quick-link span { font-size: 0.86rem; }
            .quick-link small { font-size: 0.74rem; }
            #chart-intervencoes,
            #chart-tarefas { min-height: 200px; }
            .chart-filter-wrap {
                min-width: 100%;
                max-width: 100%;
            }
        }
        @media (max-width: 480px) {
            .page-heading h3 { font-size: 1.2rem; }
            .page-heading p { font-size: 0.86rem; }
            .stat-card {
                padding: 12px;
                min-height: 78px;
            }
            .stat-icon {
                width: 38px;
                height: 38px;
            }
            .stat-value { font-size: 1.08rem; }
            .stat-label { font-size: 0.68rem; }
            .stat-sub { font-size: 0.7rem; }
            .card-body { padding: 12px !important; }
            .quick-link {
                padding: 10px 12px;
                border-radius: 12px;
            }
            .quick-link span { font-size: 0.84rem; }
            .quick-link small { font-size: 0.72rem; }
            .quick-link i { font-size: 1.1rem !important; }
        }
    </style>
</head>
<body>
<div id="app">
    <?php include "menu.php"; ?>

    <div id="main" class="dashboard-shell">
        <header class="mb-2 mb-md-3 d-flex align-items-center">
            <a href="#" class="burger-btn d-block d-xl-none me-2">
                <i class="bi bi-justify fs-3"></i>
            </a>
        </header>

        <div class="container-fluid px-3 px-lg-4">
            <div class="page-heading mb-3">
                <h3>Painel de controlo</h3>
                <p>Resumo rápido das árvores, notícias, ocorrências e utilizadores.</p>
            </div>

            <div class="page-content">
                <section class="row">

                    <?php if ($isAdmin): ?>
                        <div class="col-12 mb-3">
                            <div class="row g-3">
                                <div class="col-12 col-sm-6 col-lg-3">
                                    <div class="card card-elevated">
                                        <div class="stat-card">
                                            <div class="stat-icon trees">
                                                <i class="bi bi-tree-fill"></i>
                                            </div>
                                            <div class="stat-meta">
                                                <div class="stat-label">Árvores</div>
                                                <div class="stat-value"><?= $totalArvores ?></div>
                                                <div class="stat-sub">Registadas no sistema</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- KPI Notícias em vez de Intervenções -->
                                <div class="col-12 col-sm-6 col-lg-3">
                                    <div class="card card-elevated">
                                        <div class="stat-card">
                                            <div class="stat-icon inter">
                                                <i class="bi bi-newspaper"></i>
                                            </div>
                                            <div class="stat-meta">
                                                <div class="stat-label">Notícias</div>
                                                <div class="stat-value"><?= $totalNoticias ?></div>
                                                <div class="stat-sub">Total de notícias publicadas</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-sm-6 col-lg-3">
                                    <div class="card card-elevated">
                                        <div class="stat-card">
                                            <div class="stat-icon occur">
                                                <i class="bi bi-exclamation-triangle-fill"></i>
                                            </div>
                                            <div class="stat-meta">
                                                <div class="stat-label">Ocorrências</div>
                                                <div class="stat-value"><?= $totalOcorrencias ?></div>
                                                <div class="stat-sub">Espaços Verdes</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-sm-6 col-lg-3">
                                    <div class="card card-elevated">
                                        <div class="stat-card">
                                            <div class="stat-icon users">
                                                <i class="bi bi-people-fill"></i>
                                            </div>
                                            <div class="stat-meta">
                                                <div class="stat-label">Utilizadores</div>
                                                <div class="stat-value"><?= $totalUtilizadores ?></div>
                                                <div class="stat-sub">Com acesso ao painel</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-lg-8">
                            <div class="card card-elevated mb-3">
                                <div class="card-body">
                                    <div class="chart-card-header">
                                        <div>
                                            <div class="card-section-title" id="chart-main-title">
                                                Notícias publicadas nos últimos 12 meses
                                            </div>
                                            <div class="card-section-sub" id="chart-main-subtitle">
                                                Baseado em noticias.data_publicacao.
                                            </div>
                                        </div>

                                        <div class="chart-filter-wrap">
                                            <select id="chartSourceSelect" class="form-select js-chart-select">
                                                <option value="noticias">Notícias</option>
                                                <option value="espaco_verde">Espaço Verde</option>
                                                <option value="ocorrencias">Ocorrências</option>
                                                <option value="ocorrencias_estrada">Ocorrências Estrada</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="chart-summary" id="chartSummary"></div>

                                    <div id="chart-intervencoes"></div>
                                </div>
                            </div>

                            <div class="card card-elevated">
                                <div class="card-body">
                                    <div class="card-section-title mb-2">Atividade recente</div>

                                    <?php if (!empty($atividade)): ?>
                                        <ul class="activity-list">
                                            <?php
                                            $colors = ['green','orange','blue','red'];
                                            foreach ($atividade as $idx => $log):
                                                $dot = 'activity-dot-' . $colors[$idx % count($colors)];
                                            ?>
                                                <li class="activity-item">
                                                    <span class="activity-dot <?= $dot ?>"></span>
                                                    <div>
                                                        <div class="activity-text-main">
                                                            <?= htmlspecialchars($log['acao']) ?>
                                                        </div>
                                                        <div class="activity-text-sub">
                                                            <?= htmlspecialchars($log['detalhe']) ?>
                                                            · por <?= htmlspecialchars($log['username'] ?? 'Utilizador') ?>
                                                            · <?= htmlspecialchars($log['created_at']) ?>
                                                        </div>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p class="activity-text-sub mb-0">
                                            Ainda não existem registos de atividade.
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-lg-4">
                            <div class="card card-elevated mb-3">
                                <div class="card-body">
                                    <div class="card-section-title mb-2">Atalhos principais</div>

                                    <a href="index.php?evora=addtree" class="quick-link">
                                        <div>
                                            <span>Adicionar Árvore</span><br>
                                            <small>Registar uma nova árvore no sistema</small>
                                        </div>
                                        <i class="bi bi-arrow-right-short fs-5"></i>
                                    </a>

                                    <a href="index.php?evora=addnoticia" class="quick-link">
                                        <div>
                                            <span>Nova Notícia</span><br>
                                            <small>Adicionar notícia à plataforma</small>
                                        </div>
                                        <i class="bi bi-arrow-right-short fs-5"></i>
                                    </a>

                                    <a href="index.php?evora=listocorrencias" class="quick-link">
                                        <div>
                                            <span>Ver Ocorrências</span><br>
                                            <small>Analisar ocorrências reportadas</small>
                                        </div>
                                        <i class="bi bi-arrow-right-short fs-5"></i>
                                    </a>

                                    <a href="index.php?evora=listarnoticias" class="quick-link">
                                        <div>
                                            <span>Ver Notícias</span><br>
                                            <small>Ver notícias publicadas</small>
                                        </div>
                                        <i class="bi bi-arrow-right-short fs-5"></i>
                                    </a>

                                    <a href="index.php?evora=editarintervencaoarvore" class="quick-link">
                                        <div>
                                            <span>Intervenções Árvore</span><br>
                                            <small>Gerir intervenções de árvores</small>
                                        </div>
                                        <i class="bi bi-arrow-right-short fs-5"></i>
                                    </a>
                                </div>
                            </div>

                            <div class="card card-elevated">
                                <div class="card-body">
                                    <div class="card-section-title mb-2" id="chart-task-title">Distribuição de tarefas</div>
                                    <div class="card-section-sub mb-2" id="chart-task-subtitle">
                                        Contagem por estado da origem selecionada.
                                    </div>
                                    <div id="chart-tarefas"></div>
                                </div>
                            </div>
                        </div>

                    <?php else: ?>
                        <div class="col-12 mb-3">
                            <div class="row g-3">
                                <!-- KPI para notícias também no modo não-admin -->
                                <div class="col-12 col-sm-6 col-lg-4">
                                    <div class="card card-elevated">
                                        <div class="stat-card">
                                            <div class="stat-icon trees">
                                                <i class="bi bi-tree-fill"></i>
                                            </div>
                                            <div class="stat-meta">
                                                <div class="stat-label">Intervenções Árvore</div>
                                                <div class="stat-value"><?= $totalIntervencoesArvore ?></div>
                                                <div class="stat-sub">Atribuídas ao funcionário</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-sm-6 col-lg-4">
                                    <div class="card card-elevated">
                                        <div class="stat-card">
                                            <div class="stat-icon occur">
                                                <i class="bi bi-exclamation-triangle-fill"></i>
                                            </div>
                                            <div class="stat-meta">
                                                <div class="stat-label">Espaços Verdes</div>
                                                <div class="stat-value"><?= $totalOcorrencias ?></div>
                                                <div class="stat-sub">Ocorrências reportadas</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-sm-6 col-lg-4">
                                    <div class="card card-elevated">
                                        <div class="stat-card">
                                            <div class="stat-icon occur">
                                                <i class="bi bi-cone-striped"></i>
                                            </div>
                                            <div class="stat-meta">
                                                <div class="stat-label">Estrada</div>
                                                <div class="stat-value"><?= $totalOcorrenciasEstrada ?></div>
                                                <div class="stat-sub">Ocorrências na via pública</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-lg-8">
                            <div class="card card-elevated mb-3">
                                <div class="card-body">
                                    <div class="chart-card-header">
                                        <div>
                                            <div class="card-section-title" id="chart-main-title">
                                                Notícias publicadas nos últimos 12 meses
                                            </div>
                                            <div class="card-section-sub" id="chart-main-subtitle">
                                                Baseado em noticias.data_publicacao.
                                            </div>
                                        </div>

                                        <div class="chart-filter-wrap">
                                            <select id="chartSourceSelect" class="form-select js-chart-select">
                                                <option value="noticias">Notícias</option>
                                                <option value="espaco_verde">Espaço Verde</option>
                                                <option value="ocorrencias">Ocorrências</option>
                                                <option value="ocorrencias_estrada">Ocorrências Estrada</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="chart-summary" id="chartSummary"></div>

                                    <div id="chart-intervencoes"></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-lg-4">
                            <div class="card card-elevated mb-3">
                                <div class="card-body">
                                    <div class="card-section-title mb-2">Atalhos principais</div>

                                    <a href="index.php?evora=editarintervencaoarvore" class="quick-link">
                                        <div>
                                            <span>Intervenções Árvore</span><br>
                                            <small>Ver intervenções atribuídas</small>
                                        </div>
                                        <i class="bi bi-arrow-right-short fs-5"></i>
                                    </a>

                                    <a href="index.php?evora=addnoticia" class="quick-link">
                                        <div>
                                            <span>Nova Notícia</span><br>
                                            <small>Adicionar notícia à plataforma</small>
                                        </div>
                                        <i class="bi bi-arrow-right-short fs-5"></i>
                                    </a>

                                    <a href="index.php?evora=listocorrencias" class="quick-link">
                                        <div>
                                            <span>Ver Ocorrências</span><br>
                                            <small>Analisar ocorrências de Espaços Verdes</small>
                                        </div>
                                        <i class="bi bi-arrow-right-short fs-5"></i>
                                    </a>

                                    <a href="index.php?evora=listocorrencias_estrada" class="quick-link">
                                        <div>
                                            <span>Ver Ocorrências Estrada</span><br>
                                            <small>Ocorrências registadas na via pública</small>
                                        </div>
                                        <i class="bi bi-arrow-right-short fs-5"></i>
                                    </a>

                                    <a href="index.php?evora=mapa2d" class="quick-link">
                                        <div>
                                            <span>Ver Mapa 2D</span><br>
                                            <small>Mapa de Espaços Verdes / Ocorrências</small>
                                        </div>
                                        <i class="bi bi-arrow-right-short fs-5"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                </section>
            </div>
        </div>
    </div>
</div>

<script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendors/apexcharts/apexcharts.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js"></script>

<script>
const chartData = <?= json_encode($chartData, JSON_UNESCAPED_UNICODE) ?>;
const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-chart-select').forEach(function (el) {
        if (!el.tomselect) {
            new TomSelect(el, {
                maxItems: 1,
                allowEmptyOption: false,
                create: false,
                controlInput: null,
                plugins: {}
            });
        }
    });

    const sourceSelect = document.getElementById('chartSourceSelect');
    const chartTitle = document.getElementById('chart-main-title');
    const chartSubtitle = document.getElementById('chart-main-subtitle');
    const chartSummary = document.getElementById('chartSummary');

    const taskTitle = document.getElementById('chart-task-title');
    const taskSubtitle = document.getElementById('chart-task-subtitle');

    const defaultSource = sourceSelect ? sourceSelect.value : 'noticias';

    function buildSummaryHtml(sourceKey) {
        const dataset = chartData[sourceKey];
        if (!dataset || !dataset.monthly || !dataset.monthly.series) return '';

        const total12Meses = dataset.monthly.series.reduce((sum, val) => sum + Number(val || 0), 0);
        const mesesComDados = dataset.monthly.series.filter(val => Number(val || 0) > 0).length;
        const ultimoValor = dataset.monthly.series.length ? dataset.monthly.series[dataset.monthly.series.length - 1] : 0;

        return `
            <span class="summary-chip"><i class="bi bi-bar-chart-line-fill"></i> Total 12 meses: ${total12Meses}</span>
            <span class="summary-chip"><i class="bi bi-calendar3"></i> Meses com atividade: ${mesesComDados}</span>
            <span class="summary-chip"><i class="bi bi-check2-circle"></i> Mês atual: ${ultimoValor}</span>
        `;
    }

    const initialLabels = chartData[defaultSource].monthly.labels;
    const initialSeries = chartData[defaultSource].monthly.series;

    const intervencoesOptions = {
        chart: {
            type: 'area',
            height: 300,
            toolbar: { show: false },
            fontFamily: 'Nunito, sans-serif'
        },
        series: [{
            name: 'Notícias publicadas',
            data: initialSeries
        }],
        xaxis: {
            categories: initialLabels,
            labels: {
                style: { colors: '#6b7280' },
                rotate: -45,
                hideOverlappingLabels: true
            }
        },
        yaxis: {
            min: 0,
            forceNiceScale: true,
            labels: { style: { colors: '#6b7280' } }
        },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 3 },
        colors: ['#4f46e5'],
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.35,
                opacityTo: 0.05,
                stops: [0, 90, 100]
            }
        },
        grid: {
            borderColor: '#e5e7eb',
            strokeDashArray: 4
        },
        tooltip: { theme: 'light' },
        noData: {
            text: 'Sem dados disponíveis'
        },
        responsive: [
            {
                breakpoint: 768,
                options: {
                    chart: { height: 240 },
                    stroke: { width: 2.5 },
                    xaxis: {
                        labels: {
                            rotate: -45,
                            style: { fontSize: '10px' }
                        }
                    }
                }
            },
            {
                breakpoint: 480,
                options: {
                    chart: { height: 210 },
                    xaxis: {
                        labels: {
                            rotate: -45,
                            style: { fontSize: '9px' }
                        }
                    }
                }
            }
        ]
    };

    const intervencoesChart = new ApexCharts(document.querySelector("#chart-intervencoes"), intervencoesOptions);
    intervencoesChart.render();

    let tarefasChart = null;

    if (isAdmin && document.querySelector("#chart-tarefas")) {
        const taskLabels = chartData[defaultSource].tasks.labels;
        const taskSeries = chartData[defaultSource].tasks.series;

        const tarefasOptions = {
            chart: {
                type: 'donut',
                height: 280,
                fontFamily: 'Nunito, sans-serif'
            },
            labels: taskLabels,
            series: taskSeries,
            colors: ['#22c55e','#eab308','#ef4444','#0ea5e9','#6366f1','#a855f7','#14b8a6','#f97316'],
            legend: {
                position: 'bottom',
                labels: { colors: '#6b7280' },
                fontSize: '12px'
            },
            dataLabels: { enabled: false },
            stroke: { width: 1, colors: ['#ffffff'] },
            noData: {
                text: 'Sem tarefas disponíveis'
            },
            responsive: [
                {
                    breakpoint: 768,
                    options: {
                        chart: { height: 240 },
                        legend: { fontSize: '11px' }
                    }
                },
                {
                    breakpoint: 480,
                    options: {
                        chart: { height: 220 },
                        legend: {
                            position: 'bottom',
                            fontSize: '10px'
                        }
                    }
                }
            ]
        };

        tarefasChart = new ApexCharts(document.querySelector("#chart-tarefas"), tarefasOptions);
        tarefasChart.render();
    }

    function updateDashboardCharts(sourceKey) {
        const dataset = chartData[sourceKey];
        if (!dataset) return;

        if (chartTitle) chartTitle.textContent = dataset.title;
        if (chartSubtitle) chartSubtitle.textContent = dataset.subtitle;
        if (chartSummary) chartSummary.innerHTML = buildSummaryHtml(sourceKey);

        intervencoesChart.updateOptions({
            xaxis: {
                categories: dataset.monthly.labels,
                labels: {
                    style: { colors: '#6b7280' },
                    rotate: -45,
                    hideOverlappingLabels: true
                }
            }
        });

        // série muda conforme origem; nome fixo para notícias, mas mantém genérico
        const seriesName = (sourceKey === 'noticias') ? 'Notícias publicadas' : 'Intervenções concluídas';

        intervencoesChart.updateSeries([{
            name: seriesName,
            data: dataset.monthly.series
        }]);

        if (isAdmin && tarefasChart) {
            if (sourceKey === 'noticias') {
                // sem donut para notícias
                if (taskTitle) taskTitle.textContent = 'Distribuição de tarefas';
                if (taskSubtitle) taskSubtitle.textContent = 'Sem distribuição para notícias.';
                tarefasChart.updateOptions({ labels: [] });
                tarefasChart.updateSeries([]);
                return;
            }

            if (taskTitle) taskTitle.textContent = 'Distribuição de tarefas';
            if (taskSubtitle) taskSubtitle.textContent = 'Contagem por estado da origem selecionada.';

            tarefasChart.updateOptions({
                labels: dataset.tasks.labels
            });

            tarefasChart.updateSeries(dataset.tasks.series);
        }
    }

    updateDashboardCharts(defaultSource);

    sourceSelect?.addEventListener('change', function () {
        updateDashboardCharts(this.value);
    });
});
</script>
<script src="assets/js/main.js"></script>
</body>
</html>
