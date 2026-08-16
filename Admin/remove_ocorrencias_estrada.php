<?php
include './log.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$success = '';
$error   = '';

// REMOVER UMA OU VÁRIAS OCORRÊNCIAS DE ESTRADA
if (!empty($_POST)) {
    // MULTI
    if (isset($_POST['mode']) && $_POST['mode'] === 'multi' && !empty($_POST['delete_ids']) && is_array($_POST['delete_ids'])) {
        $ids = array_map('intval', $_POST['delete_ids']);

        if (count($ids) > 0) {
            $in    = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));

            // buscar imagens para apagar ficheiros depois
            $stmtSel = $conn->prepare("SELECT id, imagem FROM ocorrencias_estrada WHERE id IN ($in)");
            $imagensPorId = [];
            if ($stmtSel) {
                $stmtSel->bind_param($types, ...$ids);
                $stmtSel->execute();
                $resImgs = $stmtSel->get_result();
                while ($row = $resImgs->fetch_assoc()) {
                    $imagensPorId[(int)$row['id']] = $row['imagem'];
                }
                $stmtSel->close();
            }

            // apagar ocorrências
            $stmt = $conn->prepare("DELETE FROM ocorrencias_estrada WHERE id IN ($in)");
            if ($stmt === false) {
                $error = "Erro na preparação da query (delete múltiplo).";
            } else {
                $stmt->bind_param($types, ...$ids);

                if ($stmt->execute()) {
                    $success = "Ocorrências de estrada removidas com sucesso.";

                    $userId = (int)$_SESSION['user_id'];
                    foreach ($ids as $id) {
                        // log
                        regista_log(
                            $conn,
                            $userId,
                            "remover",
                            "ocorrencia_estrada",
                            $id,
                            "Ocorrência de estrada apagada em remoção múltipla."
                        );

                        // atividade
                        $acao    = 'Remoção de ocorrência de estrada';
                        $detalhe = "Ocorrência de estrada ID $id removida (remoção múltipla).";

                        $stmtAt = $conn->prepare("
                            INSERT INTO atividade (user_id, acao, detalhe)
                            VALUES (?, ?, ?)
                        ");
                        if ($stmtAt) {
                            $stmtAt->bind_param("iss", $userId, $acao, $detalhe);
                            $stmtAt->execute();
                            $stmtAt->close();
                        }

                        // apagar ficheiro de imagem se existir
                        if (!empty($imagensPorId[$id])) {
                            $ficheiro = __DIR__ . '/uploads/ocorrencias_estrada/' . $imagensPorId[$id];
                            if (file_exists($ficheiro)) {
                                @unlink($ficheiro);
                            }
                        }
                    }
                } else {
                    $error = "Erro ao remover ocorrências selecionadas!";
                }

                $stmt->close();
            }
        }

    // SINGLE
    } elseif (isset($_POST['mode']) && $_POST['mode'] === 'single' && isset($_POST['delete_id'])) {
        $id = intval($_POST['delete_id']);

        // 1) Buscar nome da imagem
        $stmtSel = $conn->prepare("SELECT imagem FROM ocorrencias_estrada WHERE id = ?");
        if ($stmtSel === false) {
            $error = "Erro na preparação da query (select).";
        } else {
            $stmtSel->bind_param("i", $id);
            $stmtSel->execute();
            $stmtSel->bind_result($imagem_nome);
            $stmtSel->fetch();
            $stmtSel->close();

            // 2) Apagar ocorrência
            $stmt = $conn->prepare("DELETE FROM ocorrencias_estrada WHERE id = ?");
            if ($stmt === false) {
                $error = "Erro na preparação da query (delete).";
            } else {
                $stmt->bind_param("i", $id);

                if ($stmt->execute()) {
                    $success = "Ocorrência de estrada removida com sucesso.";

                    // log antigo
                    regista_log(
                        $conn,
                        $_SESSION['user_id'],
                        "remover",
                        "ocorrencia_estrada",
                        $id,
                        "Ocorrência de estrada apagada."
                    );

                    // registo na tabela atividade
                    $userId  = (int)$_SESSION['user_id'];
                    $acao    = 'Remoção de ocorrência de estrada';
                    $detalhe = "Ocorrência de estrada ID $id removida.";

                    $stmtAt = $conn->prepare("
                        INSERT INTO atividade (user_id, acao, detalhe)
                        VALUES (?, ?, ?)
                    ");
                    if ($stmtAt) {
                        $stmtAt->bind_param("iss", $userId, $acao, $detalhe);
                        $stmtAt->execute();
                        $stmtAt->close();
                    }

                    // apagar ficheiro da imagem, se existir
                    if (!empty($imagem_nome)) {
                        $ficheiro = __DIR__ . '/uploads/ocorrencias_estrada/' . $imagem_nome;
                        if (file_exists($ficheiro)) {
                            @unlink($ficheiro);
                        }
                    }
                } else {
                    $error = "Erro ao remover ocorrência de estrada!";
                }

                $stmt->close();
            }
        }
    }

    // depois de remover, volta sempre para a página principal de estrada
    header('Location: index.php?evora=removeocorrencias_estrada');
    exit();
}

/* carregar TODAS as ocorrências de estrada (client-side filter/pagination) */
$sqlLista = "
    SELECT o.id,
           o.descricao,
           o.latitude,
           o.longitude,
           o.place_name,
           o.tipo_intervencao,
           o.estado,
           o.imagem,
           o.data_ocorrencia,
           o.criado_em
    FROM ocorrencias_estrada o
    ORDER BY o.criado_em DESC
";
$ocorrencias = $conn->query($sqlLista);

/* Filtros – tipos */
$tipos_result = $conn->query("
    SELECT DISTINCT tipo_intervencao
    FROM ocorrencias_estrada
    WHERE tipo_intervencao IS NOT NULL AND tipo_intervencao <> ''
    ORDER BY tipo_intervencao ASC
");

/* Filtros – estados (tarefa) */
$estados_result = $conn->query("
    SELECT DISTINCT estado
    FROM ocorrencias_estrada
    WHERE estado IS NOT NULL AND estado <> ''
    ORDER BY estado ASC
");

$total_ocorr  = $ocorrencias ? $ocorrencias->num_rows : 0;
$per_page     = 6;
$total_pages  = $per_page > 0 ? (int)ceil($total_ocorr / $per_page) : 1;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Remover Ocorrências de Estrada</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/css/tom-select.bootstrap5.min.css">

    <style>
        body,
        .sidebar,
        .card,
        .btn,
        h4, h3, h2 {
            font-family: 'Nunito', sans-serif !important;
        }
        .page-content {
            background-color: #f3f4f6;
        }
        .card-main {
            border-radius: 18px;
            border: 0;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
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
            padding: 0.35rem 0.9rem;
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
        .ocor-card-container {
            display: flex;
            margin-bottom: 16px;
        }
        .ocor-card {
            border-radius: 16px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
            overflow: hidden;
            background-color: #ffffff;
            display: flex;
            flex-direction: column;
            width: 100%;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .ocor-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12);
        }
        .ocor-card-body {
            padding: 0.85rem 1.1rem 0.6rem 1.1rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }
        .ocor-card-footer {
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
        .ocor-label {
            font-weight: 600;
            font-size: 13px;
            color: #6b7280;
        }
        .ocor-value {
            font-size: 14px;
            color: #111827;
        }
        .ocor-descricao {
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .ocor-line {
            margin-bottom: 0.1rem;
        }
        .ocor-image-line {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 0.15rem;
        }
        .ocor-image-btn {
            padding: 0 6px;
            font-size: 0.85rem;
            line-height: 1.2;
            height: 1.4rem;
        }
        .section-subtitle {
            font-size: 0.85rem;
            color: #9ca3af;
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
            .card-header-flex {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.8rem;
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
        }
        /* --- LIGHTBOX --- */
        .img-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.82);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.18s ease-out, visibility 0.18s ease-out;
        }
        .img-overlay.show {
            opacity: 1;
            visibility: visible;
        }
        .img-overlay-inner {
            position: relative;
            max-width: 92vw;
            max-height: 92vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .img-overlay img {
            max-width: 80vw;
            max-height: 80vh;
            border-radius: 12px;
            box-shadow: 0 16px 36px rgba(0,0,0,0.6);
            object-fit: contain;
        }
        .img-nav-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            border-radius: 999px;
            border: none;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0f172a;
            background: #f9fafb;
            box-shadow: 0 10px 26px rgba(15,23,42,0.6);
            cursor: pointer;
            transition: transform 0.12s ease, box-shadow 0.12s ease;
        }
        .img-nav-btn i {
            font-size: 1rem;
        }
        .img-nav-btn-left { left: -22px; }
        .img-nav-btn-right { right: -22px; }
        .img-nav-btn:hover {
            transform: translateY(-50%) translateY(-1px);
            box-shadow: 0 14px 32px rgba(15,23,42,0.8);
        }
        @media (max-width: 576px) {
            .img-overlay-inner {
                max-width: 94vw;
                max-height: 86vh;
            }
            .img-overlay img {
                max-width: 86vw;
                max-height: 70vh;
            }
            .img-nav-btn-left { left: -10px; }
            .img-nav-btn-right { right: -10px; }
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
    <h3 class="mb-1">Remover ocorrências de estrada</h3>
    <p class="text-subtitle text-muted mb-0">
        Veja e remova ocorrências de estrada registadas no sistema.
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
                    <h4 class="mb-1">Ocorrências de estrada registadas</h4>
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

        <!-- PAINEL DE FILTRO -->
        <div id="filterPanel" class="filter-panel d-none">
            <form id="ocorFilterForm">
                <div class="mb-2">
                    <label for="filterTipo" class="form-label">Tipo de intervenção</label>
                    <select id="filterTipo" class="form-select js-nice-select">
                        <option value="">Todos</option>
                        <?php if ($tipos_result && $tipos_result->num_rows > 0): ?>
                            <?php while ($row = $tipos_result->fetch_assoc()): ?>
                                <?php if (!empty($row['tipo_intervencao'])): ?>
                                    <option value="<?= htmlspecialchars(strtolower($row['tipo_intervencao'])) ?>">
                                        <?= htmlspecialchars($row['tipo_intervencao']) ?>
                                    </option>
                                <?php endif; ?>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label for="filterEstado" class="form-label">Tarefa</label>
                    <select id="filterEstado" class="form-select js-nice-select">
                        <option value="">Todas</option>
                        <?php if ($estados_result && $estados_result->num_rows > 0): ?>
                            <?php while ($row = $estados_result->fetch_assoc()): ?>
                                <?php if (!empty($row['estado'])): ?>
                                    <option value="<?= htmlspecialchars(strtolower($row['estado'])) ?>">
                                        <?= htmlspecialchars($row['estado']) ?>
                                    </option>
                                <?php endif; ?>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label for="filterData" class="form-label">Data da ocorrência</label>
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
        <!-- FIM PAINEL DE FILTRO -->

        <!-- FORM ÚNICO (single + multi) -->
        <form method="post" id="mainDeleteForm">
            <input type="hidden" name="mode" id="deleteModeInput" value="single">
            <input type="hidden" name="delete_id" id="singleDeleteId" value="">

            <div class="card-body">
                <?php if ($ocorrencias && $ocorrencias->num_rows > 0): ?>
                    <div class="container-fluid">
                        <div class="row" id="ocorList">
                            <?php while ($ocor = $ocorrencias->fetch_assoc()): ?>
                                <?php
                                    $dataOcorrenciaRaw   = $ocor['data_ocorrencia'];
                                    $textoDataOcorrencia = $dataOcorrenciaRaw
                                        ? date('d/m/Y', strtotime($dataOcorrenciaRaw))
                                        : 'Sem data';
                                    $textoCriado = date('d/m/Y H:i', strtotime($ocor['criado_em']));
                                    $imgs = [];
                                    if (!empty($ocor['imagem'])) {
                                        $imgs[] = '/PAP/uploads/ocorrencias_estrada/' . $ocor['imagem'];
                                    }
                                    $imgsJson = htmlspecialchars(json_encode($imgs), ENT_QUOTES, 'UTF-8');
                                ?>
                                <div class="col-12 col-md-4 ocor-card-container"
                                     data-tarefa="<?= htmlspecialchars(strtolower($ocor['estado'] ?: '')) ?>"
                                     data-tipo="<?= htmlspecialchars(strtolower($ocor['tipo_intervencao'] ?: '')) ?>"
                                     data-data-ocorrencia="<?= htmlspecialchars($dataOcorrenciaRaw ? date('Y-m-d', strtotime($dataOcorrenciaRaw)) : '') ?>"
                                >
                                    <div class="ocor-card">
                                        <div class="ocor-card-body">
                                            <div class="ocor-line">
                                                <span class="ocor-label">Descrição:</span>
                                                <span class="ocor-value ocor-descricao">
                                                    <?= htmlspecialchars($ocor['descricao']) ?>
                                                </span>
                                            </div>
                                            <div class="ocor-line">
                                                <span class="ocor-label">Local:</span>
                                                <span class="ocor-value">
                                                    <?= $ocor['place_name']
                                                        ? htmlspecialchars($ocor['place_name'])
                                                        : 'Sem nome' ?>
                                                </span>
                                            </div>
                                            <div class="ocor-line">
                                                <span class="ocor-label">Latitude/Longitude:</span>
                                                <span class="ocor-value">
                                                    <?= htmlspecialchars($ocor['latitude']) ?>,
                                                    <?= htmlspecialchars($ocor['longitude']) ?>
                                                </span>
                                            </div>
                                            <div class="ocor-line">
                                                <span class="ocor-label">Tipo de Intervenção:</span>
                                                <span class="ocor-value ocor-tipo">
                                                    <?= htmlspecialchars($ocor['tipo_intervencao'] ?: 'Nenhuma') ?>
                                                </span>
                                            </div>
                                            <div class="ocor-line">
                                                <span class="ocor-label">Tarefa:</span>
                                                <span class="ocor-value ocor-estado">
                                                    <?= htmlspecialchars($ocor['estado']) ?>
                                                </span>
                                            </div>
                                            <div class="ocor-line">
                                                <span class="ocor-label">Data da ocorrência:</span>
                                                <span class="ocor-value">
                                                    <?= $textoDataOcorrencia ?>
                                                </span>
                                            </div>
                                            <?php if (!empty($imgs)): ?>
                                                <div class="ocor-line ocor-image-line">
                                                    <span class="ocor-label">Imagem da ocorrência:</span>
                                                    <button
                                                        type="button"
                                                        class="btn btn-outline-secondary ocor-image-btn d-inline-flex align-items-center js-open-gallery"
                                                        data-images='<?= $imgsJson ?>'
                                                        data-start-index="0"
                                                    >
                                                        <i class="bi bi-card-image me-1"></i>
                                                        <span>Ver imagem</span>
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="ocor-card-footer">
                                            <span>
                                                Criado em:
                                                <?= $textoCriado ?>
                                            </span>
                                            <div class="d-flex align-items-center gap-2">
                                                <button
                                                    type="button"
                                                    class="btn btn-danger btn-sm single-delete-btn"
                                                    data-id="<?= (int)$ocor['id'] ?>"
                                                >
                                                    Remover
                                                </button>

                                                <div class="form-check mb-0 multi-checkbox-wrapper" style="display:none;">
                                                    <input
                                                        class="form-check-input ocor-checkbox"
                                                        type="checkbox"
                                                        name="delete_ids[]"
                                                        value="<?= (int)$ocor['id'] ?>"
                                                        id="ocorChk<?= (int)$ocor['id'] ?>"
                                                    >
                                                    <label class="form-check-label" for="ocorChk<?= (int)$ocor['id'] ?>">
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
                            onclick="return confirm('Tem certeza que deseja remover as ocorrências selecionadas?');"
                        >
                            Remover ocorrências selecionadas
                        </button>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Paginação de ocorrências de estrada" class="mt-3 mb-1" id="estradaPagination">
                            <ul class="pagination justify-content-center flex-wrap" id="paginationLinks"></ul>
                        </nav>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="alert alert-info text-center mb-0">
                        Nenhuma ocorrência de estrada registada.
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>
</section>
</div>
</div>
</div>

<div id="imgOverlay" class="img-overlay" aria-hidden="true">
    <div class="img-overlay-inner">
        <button type="button" class="img-nav-btn img-nav-btn-left" id="imgPrevBtn">
            <i class="bi bi-chevron-left"></i>
        </button>
        <img id="imgOverlayImg" src="" alt="Imagem">
        <button type="button" class="img-nav-btn img-nav-btn-right" id="imgNextBtn">
            <i class="bi bi-chevron-right"></i>
        </button>
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
const filterForm          = document.getElementById('ocorFilterForm');

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
document.addEventListener('click', function (e) {
    if (!filterPanel.classList.contains('d-none')) {
        if (!filterPanel.contains(e.target) && !openFilterPanelBtn.contains(e.target)) {
            filterPanel.classList.add('d-none');
        }
    }
});

const burgerBtn = document.querySelector('.burger-btn');
if (burgerBtn && filterPanel) {
    burgerBtn.addEventListener('click', () => {
        filterPanel.classList.add('d-none');
    });
}

if (clearFiltersBtn) {
    clearFiltersBtn.addEventListener('click', function () {
        filterForm.reset();
        document.querySelectorAll('.js-nice-select').forEach(function (el) {
            if (el.tomselect) {
                el.tomselect.clear();
            }
        });
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

// Tom Select init
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

// CLIENT-SIDE PAGINATION + FILTER (como remover_ocorrencias)
const perPage        = <?= (int)$per_page ?>;
const cards          = Array.from(document.querySelectorAll('.ocor-card-container'));
const filterTipoEl   = document.getElementById('filterTipo');
const filterEstadoEl = document.getElementById('filterEstado');
const filterDataEl   = document.getElementById('filterData');
const paginationNav  = document.getElementById('estradaPagination');
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

// filtro: filtra TODAS as cards (de todas as "páginas") e depois paginamos o resultado
function applyFilters() {
    const tipo   = (filterTipoEl ? filterTipoEl.value : '').trim().toLowerCase();
    const tarefa = (filterEstadoEl ? filterEstadoEl.value : '').trim().toLowerCase();
    const data   = (filterDataEl ? filterDataEl.value : '').trim(); // yyyy-mm-dd

    cards.forEach(function (card) {
        const cardTipo   = (card.getAttribute('data-tipo') || '').trim().toLowerCase();
        const cardTarefa = (card.getAttribute('data-tarefa') || '').trim().toLowerCase();
        const cardData   = (card.getAttribute('data-data-ocorrencia') || '').trim();

        const matchesTipo   = !tipo   || cardTipo.includes(tipo);
        const matchesTarefa = !tarefa || cardTarefa.includes(tarefa);
        const matchesData   = !data   || cardData === data;

        if (matchesTipo && matchesTarefa && matchesData) {
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

if (cards.length > 0) {
    applyFilters();
} else {
    if (paginationNav) paginationNav.style.display = 'none';
}

// MODO SELEÇÃO (single / multi)
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
        document.querySelectorAll('.ocor-checkbox').forEach(chk => chk.checked = false);
        singleButtons.forEach(btn => btn.style.display = 'inline-block');
        checkboxWrappers.forEach(w => w.style.display = 'none');
        toggleSelectModeBtn.classList.remove('active');
    }
}

if (toggleSelectModeBtn) {
    toggleSelectModeBtn.addEventListener('click', function () {
        selectionMode = !selectionMode;
        updateSelectionUI();
    });
}

// single delete
document.querySelectorAll('.single-delete-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        if (selectionMode) return;

        const id = this.getAttribute('data-id');
        if (!id) return;

        if (confirm('Tem a certeza que pretende remover esta ocorrência de estrada?')) {
            deleteModeInput.value = 'single';
            singleDeleteIdInput.value = id;
            mainForm.submit();
        }
    });
});

// LIGHTBOX
const imgOverlay    = document.getElementById('imgOverlay');
const imgOverlayImg = document.getElementById('imgOverlayImg');
const imgPrevBtn    = document.getElementById('imgPrevBtn');
const imgNextBtn    = document.getElementById('imgNextBtn');

let currentImages = [];
let currentIndex  = 0;

function openGallery(images, startIndex) {
    currentImages = images;
    currentIndex  = startIndex || 0;
    if (!currentImages.length) return;
    imgOverlayImg.src = currentImages[currentIndex];
    imgOverlay.classList.add('show');
    imgOverlay.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}

function closeGallery() {
    imgOverlay.classList.remove('show');
    imgOverlayImg.src = '';
    currentImages = [];
    currentIndex  = 0;
    imgOverlay.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

function showNext(delta) {
    if (!currentImages.length) return;
    currentIndex = (currentIndex + delta + currentImages.length) % currentImages.length;
    imgOverlayImg.src = currentImages[currentIndex];
}

document.querySelectorAll('.js-open-gallery').forEach((btn) => {
    btn.addEventListener('click', function () {
        const imagesJson = this.getAttribute('data-images');
        let images = [];
        try {
            images = JSON.parse(imagesJson);
        } catch (e) {
            images = [];
        }
        const startIndex = parseInt(this.getAttribute('data-start-index') || '0', 10) || 0;
        openGallery(images, startIndex);
    });
});

imgPrevBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    showNext(-1);
});

imgNextBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    showNext(1);
});

imgOverlay.addEventListener('click', (e) => {
    if (e.target === imgOverlay) {
        closeGallery();
    }
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && imgOverlay.classList.contains('show')) {
        closeGallery();
    }
});
</script>
</body>
</html>
