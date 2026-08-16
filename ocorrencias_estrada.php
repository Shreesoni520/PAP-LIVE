<?php
session_start();
include './config.php';

date_default_timezone_set('Europe/Lisbon');

require __DIR__ . '/vendor/autoload.php';
use Twilio\Rest\Client;

$logoUrl   = 'https://lucky-plum-7yxiihmoh3.edgeone.app/logom.png';
$siteName  = 'Reporta Évora';
$publicUrl = 'http://localhost/PAP/index.php?evora_p=inicio';
$baseUrl   = 'http://localhost/PAP';

$intervencoes = [
    'Buraco na estrada',
    'Sinalização danificada',
    'Pavimento degradado',
    'Obstáculo na estrada',
    'Outros'
];

$success  = false;
$errorMsg = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $descricao        = trim($_POST["descricao"]        ?? '');
    $latitude         = trim($_POST["latitude"]         ?? '');
    $longitude        = trim($_POST["longitude"]        ?? '');
    $place_name       = trim($_POST["place_name"]       ?? '');
    $tipo_intervencao = trim($_POST["tipo_intervencao"] ?? '');
    $data_ocorrencia  = trim($_POST["data_ocorrencia"]  ?? '');

    $user_id = !empty($_SESSION['public_user_id'])
        ? intval($_SESSION['public_user_id'])
        : 0;

    $imagem      = $_FILES['imagem'] ?? null;
    $imagem_nome = null;

    if ($data_ocorrencia !== '') {
        $dataObj = DateTime::createFromFormat('Y-m-d', $data_ocorrencia);

        if ($dataObj === false) {
            $errorMsg = "Data da ocorrência inválida! Use o seletor de data.";
        } else {
            $hoje = new DateTime('today');

            if ($dataObj > $hoje) {
                $data_ocorrencia = $hoje->format('Y-m-d');
            } else {
                $data_ocorrencia = $dataObj->format('Y-m-d');
            }
        }
    }

    $tarefa_para_bd = 'Por tratar';

    if ($errorMsg !== '') {
        // já há erro de data
    } elseif ($descricao === '' || $latitude === '' || $longitude === '') {
        $errorMsg = "Preencha todos os campos obrigatórios.";
    } elseif (!is_numeric($latitude) || !is_numeric($longitude)) {
        $errorMsg = "Coordenadas inválidas!";
    } elseif ($tipo_intervencao && !in_array($tipo_intervencao, $intervencoes)) {
        $errorMsg = "Tipo de intervenção inválido!";
    } else {
        if (!$tipo_intervencao) {
            $tipo_intervencao = "Nenhuma";
        }

        $pasta_uploads = 'C:/xampp/htdocs/PAP/uploads/ocorrencias_estrada';

        if (!is_dir($pasta_uploads)) {
            mkdir($pasta_uploads, 0777, true);
        }

        if ($imagem && $imagem['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($imagem['error'] !== UPLOAD_ERR_OK) {
                $errorMsg = "Erro no upload da imagem (código " . $imagem['error'] . ").";
            } else {
                $tipos_permitidos = ['image/jpeg', 'image/png'];

                if (!in_array($imagem['type'], $tipos_permitidos)) {
                    $errorMsg = "Tipo de imagem inválido! Apenas JPG e PNG são permitidos.";
                } elseif ($imagem['size'] > 5 * 1024 * 1024) {
                    $errorMsg = "Imagem demasiado grande! Máximo 5MB.";
                } else {
                    $extensao = strtolower(pathinfo($imagem['name'], PATHINFO_EXTENSION));

                    if (!in_array($extensao, ['jpg', 'jpeg', 'png'])) {
                        $errorMsg = "Extensão de imagem inválida! Apenas JPG e PNG são permitidos.";
                    } else {
                        $imagem_nome = 'ocorrencia_estrada_' . time() . '_' . $user_id . '.' . $extensao;
                        $destino     = $pasta_uploads . '/' . $imagem_nome;

                        if (!move_uploaded_file($imagem['tmp_name'], $destino)) {
                            $errorMsg = "Erro ao guardar imagem.";
                        }
                    }
                }
            }
        }

        if ($errorMsg === '') {
            if ($data_ocorrencia === '' || $data_ocorrencia === null) {
                $data_ocorrencia_final = date('Y-m-d H:i:s');
            } else {
                $data_ocorrencia_final = $data_ocorrencia . ' 00:00:00';
            }

            $stmt = $conn->prepare(
                "INSERT INTO ocorrencias_estrada
                 (descricao, latitude, longitude, place_name, tipo_intervencao, estado, imagem, data_ocorrencia, criado_em, user_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)"
            );

            if ($stmt) {
                $lat = (float)$latitude;
                $lng = (float)$longitude;

                $stmt->bind_param(
                    "sddsssssi",
                    $descricao,
                    $lat,
                    $lng,
                    $place_name,
                    $tipo_intervencao,
                    $tarefa_para_bd,
                    $imagem_nome,
                    $data_ocorrencia_final,
                    $user_id
                );

                if ($stmt->execute()) {
                    $success = true;

                    $_SESSION['ocorrencia_msg'] = [
                        'type' => 'success',
                        'text' => 'Ocorrência de estrada registada com sucesso!'
                    ];

                    $to        = ADMIN_EMAIL;
                    $subject   = 'Nova ocorrência de estrada registada no ' . $siteName;
                    $adminLink = $baseUrl . '/admin/';

                    $body  = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
                    $body .= '<title>Nova ocorrência de estrada</title></head>';
                    $body .= '<body style="margin:0;padding:0;background-color:#f3f4f6;';
                    $body .= 'font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;">';

                    $body .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" ';
                    $body .= 'style="background-color:#f3f4f6;padding:24px 0;">';
                    $body .= '<tr><td align="center">';

                    $body .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" ';
                    $body .= 'style="max-width:640px;background-color:#ffffff;border-radius:16px;';
                    $body .= 'border:1px solid #e5e7eb;padding:20px 24px;color:#111827;">';

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

                    $body .= '<tr><td style="padding-top:18px;padding-bottom:8px;">';
                    $body .= '<h2 style="margin:0;font-size:20px;line-height:1.3;color:#111827;">';
                    $body .= 'Nova ocorrência de estrada registada</h2>';
                    $body .= '</td></tr>';

                    $body .= '<tr><td style="padding-bottom:10px;">';
                    $body .= '<p style="margin:0;color:#4b5563;font-size:14px;line-height:1.6;">';
                    $body .= 'Foi registada uma nova ocorrência de estrada através do site público ';
                    $body .= '<strong>' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '</strong>.';
                    $body .= '</p>';
                    $body .= '</td></tr>';

                    $body .= '<tr><td style="padding-top:10px;padding-bottom:6px;">';
                    $body .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" ';
                    $body .= 'style="background-color:#f9fafb;border-radius:12px;border:1px solid #e5e7eb;';
                    $body .= 'padding:14px 16px;">';

                    $body .= '<tr><td style="padding-bottom:8px;">';
                    $body .= '<div style="font-size:13px;color:#6b7280;font-weight:600;margin-bottom:2px;">Descrição do problema</div>';
                    $body .= '<div style="font-size:14px;color:#111827;line-height:1.6;">';
                    $body .= nl2br(htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8'));
                    $body .= '</div>';
                    $body .= '</td></tr>';

                    $body .= '<tr><td style="padding-top:6px;padding-bottom:2px;">';
                    $body .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-size:13px;color:#111827;">';

                    $body .= '<tr>';
                    $body .= '<td style="padding:4px 0;vertical-align:top;width:50%;">';
                    $body .= '<div style="font-size:12px;color:#6b7280;font-weight:600;margin-bottom:2px;">Local (estrada)</div>';
                    $body .= '<div>' . htmlspecialchars($place_name !== '' ? $place_name : 'Sem nome', ENT_QUOTES, 'UTF-8') . '</div>';
                    $body .= '</td>';

                    $body .= '<td style="padding:4px 0;vertical-align:top;width:50%;">';
                    $body .= '<div style="font-size:12px;color:#6b7280;font-weight:600;margin-bottom:2px;">Coordenadas</div>';
                    $body .= '<div>' . htmlspecialchars($latitude, ENT_QUOTES, 'UTF-8') . ', ' . htmlspecialchars($longitude, ENT_QUOTES, 'UTF-8') . '</div>';
                    $body .= '</td>';
                    $body .= '</tr>';

                    $body .= '<tr>';
                    $body .= '<td style="padding:4px 0;vertical-align:top;width:50%;">';
                    $body .= '<div style="font-size:12px;color:#6b7280;font-weight:600;margin-bottom:2px;">Tipo de intervenção</div>';
                    $body .= '<div>' . htmlspecialchars($tipo_intervencao, ENT_QUOTES, 'UTF-8') . '</div>';
                    $body .= '</td>';

                    $body .= '<td style="padding:4px 0;vertical-align:top;width:50%;">';
                    $body .= '<div style="font-size:12px;color:#6b7280;font-weight:600;margin-bottom:2px;">Tarefa</div>';
                    $body .= '<div>' . htmlspecialchars($tarefa_para_bd, ENT_QUOTES, 'UTF-8') . '</div>';
                    $body .= '</td>';
                    $body .= '</tr>';

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

                    $body .= '<tr><td style="padding-top:14px;padding-bottom:6px;text-align:left;">';
                    $body .= '<a href="' . htmlspecialchars($adminLink, ENT_QUOTES, 'UTF-8') . '" ';
                    $body .= 'style="display:inline-block;padding:10px 18px;border-radius:999px;';
                    $body .= 'background:linear-gradient(135deg,#0ea5e9,#22c55e);color:#ffffff;';
                    $body .= 'text-decoration:none;font-size:14px;font-weight:600;">';
                    $body .= 'Abrir painel de gestão';
                    $body .= '</a>';
                    $body .= '</td></tr>';

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

                    @mail($to, $subject, $body, $headers);

                    try {
                        $twilio = new Client(TWILIO_SID, TWILIO_TOKEN);

                        $shortDesc = trim(mb_substr($descricao, 0, 120));
                        if (mb_strlen($descricao) > 120) {
                            $shortDesc .= '...';
                        }

                        $smsBody =
                            "Nova Ocorrência Estrada (Pública)\n" .
                            "\n" .
                            "Descrição: {$shortDesc}\n" .
                            "Tipo: {$tipo_intervencao}\n" .
                            "Tarefa: {$tarefa_para_bd}";

                        $message = $twilio->messages->create(
                            TWILIO_TO,
                            [
                                'from' => TWILIO_FROM,
                                'body' => $smsBody
                            ]
                        );
                    } catch (Exception $e) {
                        // opcional: logar erro se quiseres
                    }

                } else {
                    $errorMsg = "Erro ao registar ocorrência: " . $stmt->error;

                    $_SESSION['ocorrencia_msg'] = [
                        'type' => 'error',
                        'text' => $errorMsg
                    ];

                    if ($imagem_nome && isset($destino) && file_exists($destino)) {
                        unlink($destino);
                    }
                }

                $stmt->close();

            } else {
                $errorMsg = "Erro de base de dados: " . $conn->error;

                $_SESSION['ocorrencia_msg'] = [
                    'type' => 'error',
                    'text' => $errorMsg
                ];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">

    <title>Registo de Ocorrências de Estrada</title>

    <link href="assets/img/favicon.png" rel="icon">
    <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Poppins:wght@500;600&display=swap" rel="stylesheet">

    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/aos/aos.css" rel="stylesheet">
    <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
    <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">

    <link href="assets/css/main.css" rel="stylesheet">

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/css/tom-select.bootstrap5.min.css">

    <style>
        html, body {
            max-width: 100%;
            overflow-x: hidden;
        }
        .main,
        #hero,
        #registo-ocorrencias-estrada,
        #map {
            width: 100%;
            max-width: 100vw;
            overflow-x: hidden;
        }
        #hero {
            margin-bottom: 40px;
        }
        .info-wrap {
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }
        .info-wrap h3 {
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .form-label {
            font-weight: 500;
        }
        #map {
            height: 300px;
            border-radius: 10px;
            margin-top: 8px;
            position: relative;
            z-index: 1;
        }
        .header {
            z-index: 1030;
        }
        .leaflet-top,
        .leaflet-bottom,
        .leaflet-control {
            z-index: 400;
        }
        @media (max-width: 767.98px) {
          body.index-page {
            padding-top: 70px;
            background-color: #37517e;
          }
        }
    </style>
</head>
<body class="index-page">

<header id="header" class="header d-flex align-items-center fixed-top">
    <?php include "menu.php"; ?>
</header>

<main class="main">

    <section id="hero" class="section dark-background"
             style="height: 300px; padding: 0; overflow: hidden; display: flex; align-items: center; justify-content: center; text-align: center;">
        <div class="container h-100 d-flex align-items-center justify-content-center">
            <div class="row w-100 gy-0 align-items-center justify-content-center">
                <div class="container section-title" data-aos="fade-up" style="margin-top: 5%;">
                    <h2>Registo de Ocorrências de Estrada</h2>
                    <p>Registe problemas na via pública (estradas, cruzamentos, rotundas) que necessitam de intervenção técnica.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="registo-ocorrencias-estrada" class="contact section">
        <div class="container" data-aos="fade-up" data-aos-delay="100">

            <div class="row gy-4">

                <div class="col-lg-5">
                    <div class="info-wrap">
                        <h3>Informações sobre as ocorrências de estrada</h3>

                        <div class="info-item d-flex align-items-start mb-3">
                            <i class="bi bi-info-circle fs-3 me-3 text-primary"></i>
                            <div>
                                <h5>O que são ocorrências de estrada?</h5>
                                <p>
                                    São situações que afetam a segurança ou o bom estado das estradas, como buracos,
                                    sinalização em falta ou danificada, pavimento degradado, obstáculos na via, entre outros.
                                </p>
                            </div>
                        </div>

                        <div class="info-item d-flex align-items-start mb-3">
                            <i class="bi bi-question-circle fs-3 me-3 text-primary"></i>
                            <div>
                                <h5>Como preencher o formulário</h5>
                                <ul>
                                    <li>Descreva o problema de forma clara (ex.: buraco profundo na faixa direita).</li>
                                    <li>Use o mapa para selecionar o ponto exato na estrada.</li>
                                    <li>Se possível, anexe uma fotografia para ajudar a identificação.</li>
                                </ul>
                            </div>
                        </div>

                        <div class="mt-4">
                            <h5>Mapa da ocorrência de estrada</h5>
                            <p class="text-muted mb-1">
                                Utilize o mapa para marcar o ponto exato onde o problema na estrada se encontra.
                            </p>
                            <ul class="text-muted mb-2" style="padding-left:18px;">
                                <li>Clique no mapa para definir o local do problema.</li>
                                <li>Pode arrastar o marcador para ajustar a posição.</li>
                                <li>A latitude, longitude e o nome do local são preenchidos automaticamente.</li>
                            </ul>
                            <div id="map"></div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="info-wrap">

                        <?php if ($success): ?>
                            <div class="alert alert-success text-center" role="alert">
                                Ocorrência de estrada registada com sucesso!
                            </div>
                        <?php elseif ($errorMsg): ?>
                            <div class="alert alert-danger text-center" role="alert">
                                <?= htmlspecialchars($errorMsg); ?>
                            </div>
                        <?php endif; ?>

                        <h3>Registar Ocorrência de Estrada</h3>

                        <form action="" method="post" class="row g-3" enctype="multipart/form-data">

                            <div class="col-12">
                                <label for="descricao-field" class="form-label">
                                    Descrição do problema na estrada <span class="text-danger">*</span>
                                </label>
                                <textarea
                                    name="descricao"
                                    id="descricao-field"
                                    class="form-control"
                                    rows="4"
                                    placeholder="Ex.: Buraco profundo na faixa direita, perto da rotunda X..."
                                    required
                                ></textarea>
                            </div>

                            <div class="col-12">
                                <label for="imagem-field" class="form-label">
                                    Fotografia da ocorrência (opcional)
                                </label>
                                <input
                                    type="file"
                                    name="imagem"
                                    id="imagem-field"
                                    class="form-control"
                                    accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                                >
                                <small class="text-muted">
                                    Máximo 5MB. Apenas JPG e PNG.
                                </small>
                                <div
                                    id="image-preview"
                                    class="mt-2"
                                    style="max-width:200px;max-height:200px;display:none;"
                                ></div>
                            </div>

                            <div class="col-12">
                                <label for="place-name-field" class="form-label">Nome ou referência do local</label>
                                <div class="input-group">
                                    <input
                                        type="text"
                                        name="place_name"
                                        id="place-name-field"
                                        class="form-control"
                                        placeholder="Clique no mapa ou escreva nome, código postal, cidade, país..."
                                    >
                                    <button type="button" class="btn btn-secondary" id="search-location">
                                        Pesquisar
                                    </button>
                                </div>
                                <small class="text-muted">
                                    Pode escrever o nome da estrada, rua, código postal, cidade ou país.
                                </small>
                            </div>

                            <div class="col-md-6">
                                <label for="latitude-field" class="form-label">
                                    Latitude <span class="text-danger">*</span>
                                </label>
                                <input
                                    type="text"
                                    name="latitude"
                                    id="latitude-field"
                                    class="form-control"
                                    placeholder="Clique no mapa ou use a pesquisa"
                                    required
                                    readonly
                                >
                            </div>

                            <div class="col-md-6">
                                <label for="longitude-field" class="form-label">
                                    Longitude <span class="text-danger">*</span>
                                </label>
                                <input
                                    type="text"
                                    name="longitude"
                                    id="longitude-field"
                                    class="form-control"
                                    placeholder="Clique no mapa ou use a pesquisa"
                                    required
                                    readonly
                                >
                                <small class="text-muted d-block mt-1">
                                    Estes campos são preenchidos automaticamente ao escolher o ponto no mapa.
                                </small>
                            </div>

                            <div class="col-md-6">
                                <label for="tipo-intervencao-field" class="form-label">
                                    Tipo de intervenção <span class="text-danger">*</span>
                                </label>
                                <select
                                    name="tipo_intervencao"
                                    id="tipo-intervencao-field"
                                    class="js-nice-select"
                                    required
                                >
                                    <option value="">Selecione...</option>
                                    <?php foreach ($intervencoes as $interv): ?>
                                        <option value="<?= htmlspecialchars($interv) ?>">
                                            <?= htmlspecialchars($interv) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="data-ocorrencia-field" class="form-label">
                                    Data da ocorrência (opcional, até hoje)
                                </label>
                                <input
                                    type="date"
                                    name="data_ocorrencia"
                                    id="data-ocorrencia-field"
                                    class="form-control"
                                    max="<?= date('Y-m-d'); ?>"
                                >
                                <small class="text-muted">
                                    Se não preencher, será usada a data de hoje.
                                </small>
                            </div>

                            <div class="col-12 text-center">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    Registar ocorrência de estrada
                                </button>
                            </div>

                        </form>
                    </div>
                </div>

            </div>

        </div>
    </section>

</main>

<?php include "footer.php"; ?>

<a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center">
  <i class="bi bi-arrow-up-short"></i>
</a>

<div id="preloader"></div>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/aos/aos.js"></script>
<script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
<script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
<script src="assets/vendor/waypoints/noframework.waypoints.js"></script>
<script src="assets/vendor/imagesloaded/imagesloaded.pkgd.min.js"></script>
<script src="assets/vendor/isotope-layout/isotope.pkgd.min.js"></script>
<script src="assets/js/main.js"></script>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const evora = [38.5667, -7.9];

    const map = L.map('map', {
        attributionControl: false
    }).setView(evora, 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19
    }).addTo(map);

    let marker;

    function setMarkerPos(lat, lng) {
        if (marker) {
            marker.setLatLng([lat, lng]);
        } else {
            marker = L.marker([lat, lng], { draggable: true }).addTo(map);

            marker.on('dragend', function () {
                const pos = marker.getLatLng();
                setFormFields(pos.lat.toFixed(6), pos.lng.toFixed(6));
                fetchPlaceName(pos.lat, pos.lng);
            });
        }
        map.setView([lat, lng], 16);
    }

    function setFormFields(lat, lng) {
        document.getElementById('latitude-field').value  = lat;
        document.getElementById('longitude-field').value = lng;
    }

    function fetchPlaceName(lat, lng) {
        fetch(`reverse_proxy.php?lat=${lat}&lon=${lng}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('place-name-field').value = data.display_name || '';
            });
    }

    map.on('click', function (e) {
        const lat = e.latlng.lat.toFixed(6);
        const lng = e.latlng.lng.toFixed(6);

        setFormFields(lat, lng);
        fetchPlaceName(lat, lng);
        setMarkerPos(lat, lng);
    });

    document.getElementById('search-location').addEventListener('click', function () {
        const name = document.getElementById('place-name-field').value;

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
            });
    });

    document.getElementById('place-name-field').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            document.getElementById('search-location').click();
            e.preventDefault();
        }
    });

    const inputImagem = document.getElementById('imagem-field');
    const preview     = document.getElementById('image-preview');

    if (inputImagem && preview) {
        inputImagem.addEventListener('change', function (e) {
            const file = e.target.files[0];

            if (file) {
                if (file.size > 5 * 1024 * 1024) {
                    alert('Imagem demasiado grande! Máximo 5MB.');
                    e.target.value        = '';
                    preview.innerHTML     = '';
                    preview.style.display = 'none';
                    return;
                }

                const reader = new FileReader();

                reader.onload = function (ev) {
                    preview.innerHTML =
                        '<img src="' + ev.target.result + '" class="img-thumbnail" alt="Pré-visualização">';
                    preview.style.display = 'block';
                };

                reader.readAsDataURL(file);

            } else {
                preview.innerHTML     = '';
                preview.style.display = 'none';
            }
        });
    }

    document.querySelectorAll('.js-nice-select').forEach(function (el) {
        el.classList.remove('form-select');
        el.classList.remove('form-control');

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
</script>

</body>
</html>
