<?php
include './log.php';

date_default_timezone_set('Europe/Lisbon');

require __DIR__ . '/../vendor/autoload.php';
use Twilio\Rest\Client;

if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Config para emails HTML
$logoUrl   = 'https://lucky-plum-7yxiihmoh3.edgeone.app/logom.png';
$siteName  = 'Reporta Évora';
$publicUrl = 'http://localhost/PAP/index.php?evora_p=inicio';
$baseUrl   = 'http://localhost/PAP';

$success = '';
$error   = '';
$intervencoes = ['Corte', 'Poda', 'Outros'];

// continua a usar a tabela states, mas tratamos como tarefas na interface
$tarefas = [];
$res_tarefa = $conn->query("SELECT name FROM states ORDER BY name");
if ($res_tarefa && $res_tarefa->num_rows > 0) {
    while ($row = $res_tarefa->fetch_assoc()) {
        if (!empty($row['name'])) {
            $tarefas[] = $row['name'];
        }
    }
}

if (isset($_POST['add_ocorrencia'])) {
    $descricao        = trim($_POST['descricao'] ?? '');
    $latitude         = trim($_POST['latitude'] ?? '');
    $longitude        = trim($_POST['longitude'] ?? '');
    $tipo_intervencao = trim($_POST['tipo_intervencao'] ?? '');
    $tarefa           = trim($_POST['estado'] ?? '');
    $place_name       = trim($_POST['place_name'] ?? '');
    $data_ocorrencia  = trim($_POST['data_ocorrencia'] ?? '');
    $imagem           = $_FILES['imagem'] ?? null;

    // obrigatórios
    if ($descricao === '' || $latitude === '' || $longitude === '' || $tarefa === '' || $tipo_intervencao === '') {
        $error = "Todos os campos obrigatórios devem ser preenchidos!";
    }

    // data
    if (!$error && $data_ocorrencia !== '') {
        $dataObj = DateTime::createFromFormat('Y-m-d', $data_ocorrencia);
        if ($dataObj === false) {
            $error = "Data da ocorrência inválida! Use o seletor de data.";
        } else {
            $hoje = new DateTime('today');
            if ($dataObj > $hoje) {
                $data_ocorrencia = $hoje->format('Y-m-d');
            } else {
                $data_ocorrencia = $dataObj->format('Y-m-d');
            }
        }
    }

    // validações extra
    if (!$error && (!is_numeric($latitude) || !is_numeric($longitude))) {
        $error = "Latitude e Longitude devem ser números válidos!";
    }

    if (!$error && $tipo_intervencao && !in_array($tipo_intervencao, $intervencoes)) {
        $error = "Tipo de intervenção inválido!";
    }

    if (!$error && !in_array($tarefa, $tarefas)) {
        $error = "Tarefa inválida!";
    }

    if (!$error) {
        $uploadDir = dirname(__DIR__) . '/uploads/ocorrencias';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $imagem_nome = null;
        $destino = null;

        if ($imagem && $imagem['error'] === UPLOAD_ERR_OK) {
            $tipos_permitidos = ['image/jpeg', 'image/png'];

            if (!in_array($imagem['type'], $tipos_permitidos)) {
                $error = "Tipo de imagem inválido! Apenas JPG e PNG são permitidos!";
            } elseif ($imagem['size'] > 5 * 1024 * 1024) {
                $error = "Imagem demasiado grande! Máximo 5MB.";
            } else {
                $extensao = strtolower(pathinfo($imagem['name'], PATHINFO_EXTENSION));
                if (!in_array($extensao, ['jpg', 'jpeg', 'png'])) {
                    $error = "Extensão de imagem inválida! Apenas JPG e PNG são permitidos.";
                } else {
                    $imagem_nome = 'ocorrencia_' . time() . '_' . $_SESSION['user_id'] . '.' . $extensao;
                    $destino     = $uploadDir . '/' . $imagem_nome;
                    if (!move_uploaded_file($imagem['tmp_name'], $destino)) {
                        $error = "Erro ao guardar imagem!";
                    }
                }
            }
        }

        if (!$error) {
            if ($data_ocorrencia === '' || $data_ocorrencia === null) {
                $data_ocorrencia_final = date('Y-m-d H:i:s');
            } else {
                $data_ocorrencia_final = $data_ocorrencia . ' 00:00:00';
            }

            $completedAt = null;
            if ($tarefa === 'Concluída') {
                $completedAt = date('Y-m-d H:i:s');
            }

            $stmt = $conn->prepare(
                "INSERT INTO ocorrencias 
                    (
                        descricao,
                        latitude,
                        longitude,
                        place_name,
                        tipo_intervencao,
                        estado,
                        imagem,
                        data_ocorrencia,
                        criado_em,
                        user_id,
                        completed_at
                    ) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)"
            );

            if ($stmt !== false) {
                $lat     = (float)$latitude;
                $lng     = (float)$longitude;
                $user_id = (int)$_SESSION['user_id'];

                $stmt->bind_param(
                    "sddsssssis",
                    $descricao,
                    $lat,
                    $lng,
                    $place_name,
                    $tipo_intervencao,
                    $tarefa,
                    $imagem_nome,
                    $data_ocorrencia_final,
                    $user_id,
                    $completedAt
                );

                if ($stmt->execute()) {
                    $novo_id = $stmt->insert_id;

                    regista_log(
                        $conn,
                        $user_id,
                        "adicionar",
                        "ocorrencia",
                        $novo_id,
                        "Descrição: $descricao"
                    );

                    $userId  = $user_id;
                    $acao    = 'Ocorrência registada';
                    $detalhe = "Local: " . ($place_name ?: 'Sem nome') .
                               " · Tipo: $tipo_intervencao · Tarefa: $tarefa";

                    $stmtAt = $conn->prepare("
                        INSERT INTO atividade (user_id, acao, detalhe)
                        VALUES (?, ?, ?)
                    ");
                    if ($stmtAt) {
                        $stmtAt->bind_param("iss", $userId, $acao, $detalhe);
                        $stmtAt->execute();
                        $stmtAt->close();
                    }

                    // EMAIL HTML PARA ADMIN
                    $to        = ADMIN_EMAIL;
                    $subject   = 'Nova ocorrência (espaço verde) registada (Admin) - ' . $siteName;
                    $adminLink = $baseUrl . '/admin/';

                    $body  = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
                    $body .= '<title>Nova ocorrência (Admin)</title></head>';
                    $body .= '<body style="margin:0;padding:0;background-color:#f3f4f6;';
                    $body .= 'font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;">';

                    $body .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" ';
                    $body .= 'style="background-color:#f3f4f6;padding:24px 0;">';
                    $body .= '<tr><td align="center">';

                    $body .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" ';
                    $body .= 'style="max-width:640px;background-color:#ffffff;border-radius:16px;';
                    $body .= 'border:1px solid #e5e7eb;padding:20px 24px;color:#111827;">';

                    // header
                    $body .= '<tr><td style="padding-bottom:18px;border-bottom:1px solid #e5e7eb;">';
                    $body .= '<div style="text-align:center;">';
                    $body .= '<a href="' . htmlspecialchars($publicUrl, ENT_QUOTES, 'UTF-8') . '" '
                          . 'style="text-decoration:none;display:inline-block;">';
                    $body .= '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '"'
                          . ' style="max-width:220px;height:auto;display:block;margin:0 auto 6px auto;border:0;">';
                    $body .= '</a>';
                    $body .= '<div style="font-size:12px;color:#6b7280;">Plataforma digital de registo de ocorrências urbanas</div>';
                    $body .= '</div>';
                    $body .= '</td></tr>';

                    // título
                    $body .= '<tr><td style="padding-top:18px;padding-bottom:8px;">';
                    $body .= '<h2 style="margin:0;font-size:20px;line-height:1.3;color:#111827;">';
                    $body .= 'Nova ocorrência registada pelo administrador</h2>';
                    $body .= '</td></tr>';

                    // intro
                    $body .= '<tr><td style="padding-bottom:10px;">';
                    $body .= '<p style="margin:0;color:#4b5563;font-size:14px;line-height:1.6;">';
                    $body .= 'Uma nova ocorrência foi registada no painel de administração por ';
                    $body .= '<strong>' . htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8') . '</strong>.';
                    $body .= '</p>';
                    $body .= '</td></tr>';

                    // card detalhes
                    $body .= '<tr><td style="padding-top:10px;padding-bottom:6px;">';
                    $body .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" ';
                    $body .= 'style="background-color:#f9fafb;border-radius:12px;border:1px solid #e5e7eb;';
                    $body .= 'padding:14px 16px;">';

                    // descrição
                    $body .= '<tr><td style="padding-bottom:8px;">';
                    $body .= '<div style="font-size:13px;color:#6b7280;font-weight:600;margin-bottom:2px;">Descrição</div>';
                    $body .= '<div style="font-size:14px;color:#111827;line-height:1.6;">';
                    $body .= nl2br(htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8'));
                    $body .= '</div>';
                    $body .= '</td></tr>';

                    // local + coords
                    $body .= '<tr><td style="padding-top:6px;padding-bottom:2px;">';
                    $body .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-size:13px;color:#111827;">';

                    $body .= '<tr>';
                    $body .= '<td style="padding:4px 0;vertical-align:top;width:50%;">';
                    $body .= '<div style="font-size:12px;color:#6b7280;font-weight:600;margin-bottom:2px;">Local</div>';
                    $body .= '<div>' . htmlspecialchars($place_name !== '' ? $place_name : 'Sem nome', ENT_QUOTES, 'UTF-8') . '</div>';
                    $body .= '</td>';

                    $body .= '<td style="padding:4px 0;vertical-align:top;width:50%;">';
                    $body .= '<div style="font-size:12px;color:#6b7280;font-weight:600;margin-bottom:2px;">Coordenadas</div>';
                    $body .= '<div>' . htmlspecialchars($latitude, ENT_QUOTES, 'UTF-8') . ', ' . htmlspecialchars($longitude, ENT_QUOTES, 'UTF-8') . '</div>';
                    $body .= '</td>';
                    $body .= '</tr>';

                    // tipo + tarefa
                    $body .= '<tr>';
                    $body .= '<td style="padding:4px 0;vertical-align:top;width:50%;">';
                    $body .= '<div style="font-size:12px;color:#6b7280;font-weight:600;margin-bottom:2px;">Tipo de intervenção</div>';
                    $body .= '<div>' . htmlspecialchars($tipo_intervencao, ENT_QUOTES, 'UTF-8') . '</div>';
                    $body .= '</td>';

                    $body .= '<td style="padding:4px 0;vertical-align:top;width:50%;">';
                    $body .= '<div style="font-size:12px;color:#6b7280;font-weight:600;margin-bottom:2px;">Tarefa</div>';
                    $body .= '<div>' . htmlspecialchars($tarefa, ENT_QUOTES, 'UTF-8') . '</div>';
                    $body .= '</td>';
                    $body .= '</tr>';

                    // data
                    $body .= '<tr>';
                    $body .= '<td style="padding:4px 0;vertical-align:top;width:50%;">';
                    $body .= '<div style="font-size:12px;color:#6b7280;font-weight:600;margin-bottom:2px;">Data da ocorrência</div>';
                    $body .= '<div>' . htmlspecialchars($data_ocorrencia_final, ENT_QUOTES, 'UTF-8') . '</div>';
                    $body .= '</td>';
                    $body .= '<td style="padding:4px 0;vertical-align:top;width:50%;"></td>';
                    $body .= '</tr>';

                    $body .= '</table>';
                    $body .= '</td></tr>';

                    $body .= '</table>';
                    $body .= '</td></tr>';

                    // botão painel
                    $body .= '<tr><td style="padding-top:14px;padding-bottom:6px;text-align:left;">';
                    $body .= '<a href="' . htmlspecialchars($adminLink, ENT_QUOTES, 'UTF-8') . '" ';
                    $body .= 'style="display:inline-block;padding:10px 18px;border-radius:999px;';
                    $body .= 'background:linear-gradient(135deg,#0ea5e9,#22c55e);color:#ffffff;';
                    $body .= 'text-decoration:none;font-size:14px;font-weight:600;">';
                    $body .= 'Abrir painel de gestão';
                    $body .= '</a>';
                    $body .= '</td></tr>';

                    // footer
                    $body .= '<tr><td style="padding-top:16px;font-size:12px;color:#6b7280;border-top:1px solid #e5e7eb;padding-bottom:4px;">';
                    $body .= 'Este email foi gerado automaticamente pelo sistema ';
                    $body .= '<strong>' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '</strong>. Por favor, não responda diretamente a esta mensagem.';
                    $body .= '</td></tr>';

                    $body .= '</table>';
                    $body .= '</td></tr></table>';
                    $body .= '</body></html>';

                    $headers  = "From: noreply@pap.local\r\n";
                    $headers .= "Reply-To: noreply@pap.local\r\n";
                    $headers .= "MIME-Version: 1.0\r\n";
                    $headers .= "Content-type: text/html; charset=UTF-8\r\n";

                    if (@mail($to, $subject, $body, $headers)) {
                        $success = "Ocorrência registada com sucesso! Email enviado.";
                    } else {
                        $success = "Ocorrência registada com sucesso, mas ocorreu um erro ao enviar o email.";
                    }

                    // SMS Twilio (usa Tarefa)
                    try {
                        $twilio = new Client(TWILIO_SID, TWILIO_TOKEN);

                        $shortDesc = trim(mb_substr($descricao, 0, 120));
                        if (mb_strlen($descricao) > 120) {
                            $shortDesc .= '...';
                        }

                        $smsBody =
                            "Nova Ocorrência Espaço Verde (Admin)\n" .
                            "Descrição: {$shortDesc}\n" .
                            "Tipo: {$tipo_intervencao}\n" .
                            "Tarefa: {$tarefa}";

                        $message = $twilio->messages->create(
                            TWILIO_TO,
                            [
                                'from' => TWILIO_FROM,
                                'body' => $smsBody
                            ]
                        );
                    } catch (Exception $e) {
                        // opcional log
                    }

                } else {
                    $error = "Erro ao registar ocorrência: " . $stmt->error;
                    if (isset($imagem_nome, $destino) && file_exists($destino)) {
                        unlink($destino);
                    }
                }

                $stmt->close();
            } else {
                $error = "Erro na preparação da query: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Registo de Ocorrências</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/vendors/simple-datatables/style.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/css/tom-select.bootstrap5.min.css">

    <style>
        html, body {
            height: 100%;
        }
        body {
            font-family: 'Nunito', sans-serif !important;
            margin: 0;
            background: linear-gradient(135deg, #eef2ff, #f9fafb);
        }
        #app, #main {
            min-height: 100%;
        }
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
        .btn-search-round i {
            font-size: 1rem;
        }
        .upload-box {
            border: 1px dashed #cbd5f5;
            border-radius: 10px;
            padding: 0.65rem 0.75rem;
            background-color: #f9fafb;
        }
        .upload-hint {
            font-size: 0.8rem;
            color: #6b7280;
        }
        .image-preview {
            max-width: 220px;
            max-height: 220px;
            border-radius: 8px;
            margin-top: 10px;
            display: none;
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
        @media (max-width: 768px) {
            .edit-card {
                padding: 16px 14px 16px 14px;
            }
            .edit-two-cols {
                grid-template-columns: 1fr;
            }
            .actions-row-desktop {
                margin-top: 10px;
            }
            #map {
                height: 280px;
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

        <div class="page-content">
            <div class="edit-container">

                <div class="page-heading-custom">
                    <h3>Registo de Ocorrências</h3>
                    <p>Comunique situações que necessitam de intervenção nos espaços verdes urbanos.</p>
                </div>

                <div class="edit-card">
                    <?php if ($success): ?>
                        <div class="alert alert-success mb-3"><?= htmlspecialchars($success) ?></div>
                    <?php elseif ($error): ?>
                        <div class="alert alert-danger mb-3"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="post" enctype="multipart/form-data" autocomplete="off">
                        <div class="edit-two-cols mb-3">
                            <div>
                                <div class="section-label">Detalhes da ocorrência</div>

                                <div class="mb-3">
                                    <label class="field-label mb-1">Descrição da Ocorrência</label>
                                    <textarea
                                        name="descricao"
                                        id="descricao"
                                        rows="4"
                                        class="form-control"
                                        required
                                        placeholder="Descreva a situação (o que aconteceu, onde, impactos...)"
                                    ></textarea>
                                </div>

                                <div class="section-label">Tipologia e tarefa</div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="field-label mb-1">
                                                Tipo de Intervenção <span class="text-danger">*</span>
                                            </label>
                                            <select
                                                name="tipo_intervencao"
                                                id="tipo_intervencao"
                                                class="form-select js-nice-select"
                                                required
                                            >
                                                <option value="">Selecione uma opção</option>
                                                <?php foreach ($intervencoes as $interv) { ?>
                                                    <option value="<?= htmlspecialchars($interv) ?>">
                                                        <?= htmlspecialchars($interv) ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="field-label mb-1">
                                                Tarefa <span class="text-danger">*</span>
                                            </label>
                                            <select
                                                name="estado"
                                                id="estado"
                                                class="form-select js-nice-select"
                                                required
                                            >
                                                <option value="">Selecione uma opção</option>
                                                <?php foreach ($tarefas as $tarefa_opt) { ?>
                                                    <option value="<?= htmlspecialchars($tarefa_opt) ?>">
                                                        <?= htmlspecialchars($tarefa_opt) ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="field-label mb-1">Data da Ocorrência</label>
                                    <input
                                        type="date"
                                        name="data_ocorrencia"
                                        id="data_ocorrencia"
                                        class="form-control"
                                        max="<?= date('Y-m-d'); ?>"
                                    >
                                    <small class="text-muted">
                                        Se não preencher, será usada a data de hoje. Não pode ser no futuro.
                                    </small>
                                </div>

                                <div class="section-label">Fotografia</div>
                                <div class="mb-3">
                                    <div class="upload-box">
                                        <label class="field-label mb-1">Fotografia da Ocorrência (opcional)</label>
                                        <input
                                            type="file"
                                            name="imagem"
                                            id="imagem"
                                            class="form-control"
                                            accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                                        >
                                        <div class="upload-hint mt-1">
                                            Máximo 5MB. Apenas JPG e PNG. A imagem pode ser usada na listagem e detalhes.
                                        </div>
                                        <div id="image-preview" class="image-preview mt-2"></div>
                                    </div>
                                </div>

                                <div class="actions-row-desktop">
                                    <button type="submit" name="add_ocorrencia" class="btn btn-primary btn-main w-100">
                                        Registar Ocorrência
                                    </button>
                                </div>
                            </div>

                            <div>
                                <div class="section-label">Localização no mapa</div>

                                <div class="mb-3">
                                    <label class="field-label mb-1">Nome do Local (opcional)</label>
                                    <div class="input-group">
                                        <input
                                            type="text"
                                            id="place_name"
                                            name="place_name"
                                            class="form-control"
                                            placeholder="Clique no mapa ou escreva nome, código postal, cidade, país..."
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
                                    <small class="text-muted">
                                        Pode pesquisar por nome, rua, código postal, cidade ou país. O marcador e coordenadas serão atualizados.
                                    </small>
                                </div>

                                <div class="row mb-2">
                                    <div class="col-6">
                                        <label class="field-label mb-1">Latitude</label>
                                        <input
                                            type="text"
                                            id="latitude"
                                            name="latitude"
                                            class="form-control"
                                            required
                                            placeholder="Clique no mapa ou use a pesquisa"
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
                                            required
                                            placeholder="Clique no mapa ou use a pesquisa"
                                            readonly
                                        >
                                    </div>
                                </div>

                                <div class="edit-map-wrapper">
                                    <div class="edit-map-top">
                                        <span class="edit-map-info">
                                            Clique no mapa ou arraste o marcador para definir o local da ocorrência.
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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
// Pré-visualização da imagem
document.getElementById('imagem').addEventListener('change', function(e) {
    const file    = e.target.files[0];
    const preview = document.getElementById('image-preview');

    if (file) {
        if (file.size > 5 * 1024 * 1024) {
            alert('Imagem demasiado grande! Máximo 5MB.');
            e.target.value    = '';
            preview.innerHTML = '';
            preview.style.display = 'none';
            return;
        }

        const reader = new FileReader();
        reader.onload = function(ev) {
            preview.innerHTML = '<img src="' + ev.target.result + '" class="img-thumbnail" alt="Pré-visualização">';
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        preview.innerHTML = '';
        preview.style.display = 'none';
    }
});

// Tom Select
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

// Leaflet mapa
const evora = [38.5667, -7.9];
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
    document.getElementById('latitude').value  = lat;
    document.getElementById('longitude').value = lng;
}

function fetchPlaceName(lat, lng) {
    fetch('reverse_proxy.php?lat=' + lat + '&lon=' + lng)
        .then(response => response.json())
        .then(data => {
            document.getElementById('place_name').value = data.display_name || '';
        })
        .catch(() => alert("Erro ao pedir ao servidor (reverse_proxy.php)!"));
}

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
    fetch('nominatim_proxy.php?q=' + encodeURIComponent(name))
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
