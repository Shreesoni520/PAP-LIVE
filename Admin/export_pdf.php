<?php
session_start();
date_default_timezone_set('Europe/Lisbon'); // fuso horário Portugal

include './config.php';

require __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Bloquear não autenticados
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Validar parâmetros
if (empty($_GET['ids']) || empty($_GET['tipo'])) {
    die('Nada selecionado.');
}

$tipo   = $_GET['tipo'];
$idList = explode(',', $_GET['ids']);
$ids    = array_filter(array_map('intval', $idList));

if (count($ids) === 0) {
    die('Nenhum ID válido.');
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types        = str_repeat('i', count($ids));

$htmlTitulo = '';
$records    = [];

// 1) Buscar dados consoante o tipo
switch ($tipo) {
    case 'contact':
        $sql = "SELECT id, name, email, subject, message, created_at
                FROM contact
                WHERE id IN ($placeholders)
                ORDER BY created_at DESC";
        break;

    case 'arvores':
        $sql = "SELECT id, especie, place_name, latitude, longitude,
                       tipo_intervencao, estado, criado_em
                FROM arvores
                WHERE id IN ($placeholders)
                ORDER BY criado_em DESC";
        break;

    case 'ocorrencias':
        $sql = "SELECT id, descricao, latitude, longitude, place_name,
                       tipo_intervencao, estado, data_ocorrencia, criado_em, imagem
                FROM ocorrencias
                WHERE id IN ($placeholders)
                ORDER BY id DESC";
        break;

    case 'ocorrencias_estrada':
        $sql = "SELECT id, descricao, latitude, longitude, place_name,
                       tipo_intervencao, estado, data_ocorrencia, criado_em, imagem
                FROM ocorrencias_estrada
                WHERE id IN ($placeholders)
                ORDER BY id DESC";
        break;

    case 'utilizadores':
        // usa is_admin em vez de role
        $sql = "SELECT id, username, email, is_admin, created_at
                FROM users
                WHERE id IN ($placeholders)
                ORDER BY created_at DESC";
        break;

    default:
        die('Tipo inválido.');
}

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('Erro ao preparar a query.');
}
$stmt->bind_param($types, ...$ids);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $records[] = $row;
}
$stmt->close();

if (empty($records)) {
    die('Nenhum registo encontrado para exportar.');
}

// 2) Título do PDF
switch ($tipo) {
    case 'contact':
        $htmlTitulo = 'Mensagens de Contacto';
        break;
    case 'arvores':
        $htmlTitulo = 'Árvores selecionadas';
        break;
    case 'ocorrencias':
        $htmlTitulo = 'Ocorrências selecionadas';
        break;
    case 'ocorrencias_estrada':
        $htmlTitulo = 'Ocorrências de estrada selecionadas';
        break;
    case 'utilizadores':
        $htmlTitulo = 'Utilizadores selecionados';
        break;
}

// 2.1) Ler logo e converter para base64 (PNG)
$logoFile = __DIR__ . '/assets/images/logo/logo.png';
$logoBase64 = '';
if (file_exists($logoFile)) {
    $logoData   = file_get_contents($logoFile);
    $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
}

// 3) HTML base
$html = '
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>'.htmlspecialchars($htmlTitulo).'</title>
<style>
    * { box-sizing: border-box; }
    body {
        font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
        font-size: 11px;
        margin: 0;
        padding: 24px 28px;
        background-color: #f3f4f6;
        color: #111827;
    }
    .header {
        display: flex;
        align-items: center;
        justify-content: flex-start;
        margin-bottom: 10px;
    }
    .header-logo {
        width: 120px;
        height: auto;
        margin-right: 10px;
    }
    .page-title {
        text-align: center;
        margin-bottom: 4px;
        font-size: 20px;
        font-weight: bold;
        letter-spacing: 0.03em;
        color: #111827;
    }
    .page-subtitle {
        text-align: center;
        font-size: 10px;
        color: #6b7280;
        margin-bottom: 18px;
    }
    .meta-bar {
        font-size: 9px;
        color: #6b7280;
        text-align: right;
        margin-bottom: 12px;
    }
    .cards-wrapper { display: block; }
    .card {
        background: #ffffff;
        border-radius: 10px;
        border: 1px solid #e5e7eb;
        padding: 10px 12px;
        margin-bottom: 8px;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.08);
        page-break-inside: avoid;
    }
    .line { margin-bottom: 3px; font-size: 10px; }
    .label { font-weight: bold; color: #374151; }
    .value { color: #111827; }
    .message-value { white-space: pre-wrap; }
</style>
</head>
<body>
    <div class="header">';

if ($logoBase64 !== '') {
    $html .= '
        <img class="header-logo" src="'.$logoBase64.'" alt="Logo">';
}

$html .= '
    </div>
    <div class="page-title">'.htmlspecialchars($htmlTitulo).'</div>
    <div class="page-subtitle">Exportação dos registos selecionados.</div>
    <div class="meta-bar">
        Exportado em '.date('d/m/Y H:i').' · Total: '.count($records).' registos
    </div>
    <div class="cards-wrapper">
';

// 4) Conteúdo por tipo
foreach ($records as $r) {
    if ($tipo === 'contact') {
        $createdRaw  = $r['created_at'] ?? null;
        $createdText = $createdRaw ? date('d/m/Y H:i', strtotime($createdRaw)) : 'Sem data';

        $html .= '
        <div class="card">
            <div class="line">
                <span class="label">Nome: </span>
                <span class="value">'.htmlspecialchars($r['name'] ?? '').'</span>
            </div>
            <div class="line">
                <span class="label">Email: </span>
                <span class="value">'.htmlspecialchars($r['email'] ?? '').'</span>
            </div>
            <div class="line">
                <span class="label">Assunto: </span>
                <span class="value">'.htmlspecialchars($r['subject'] ?? '').'</span>
            </div>
            <div class="line">
                <span class="label">Mensagem: </span>
                <span class="value message-value">'.nl2br(htmlspecialchars($r['message'] ?? '')).'</span>
            </div>
            <div class="line">
                <span class="label">Criado em: </span>
                <span class="value">'.$createdText.'</span>
            </div>
        </div>';
    } elseif ($tipo === 'arvores') {
        $createdRaw  = $r['criado_em'] ?? null;
        $createdText = $createdRaw ? date('d/m/Y H:i', strtotime($createdRaw)) : 'Sem data';

        $html .= '
        <div class="card">
            <div class="line">
                <span class="label">Espécie: </span>
                <span class="value">'.htmlspecialchars($r['especie'] ?? '').'</span>
            </div>
            <div class="line">
                <span class="label">Nome do Espaço: </span>
                <span class="value">'.htmlspecialchars($r['place_name'] ?? '').'</span>
            </div>
            <div class="line">
                <span class="label">Latitude/Longitude: </span>
                <span class="value">'.htmlspecialchars($r['latitude'] ?? '').', '.htmlspecialchars($r['longitude'] ?? '').'</span>
            </div>
            <div class="line">
                <span class="label">Tipo de Intervenção: </span>
                <span class="value">'.htmlspecialchars($r['tipo_intervencao'] ?? '').'</span>
            </div>
            <div class="line">
                <span class="label">Tarefa: </span>
                <span class="value">'.htmlspecialchars($r['estado'] ?? '').'</span>
            </div>
            <div class="line">
                <span class="label">Criado em: </span>
                <span class="value">'.$createdText.'</span>
            </div>
        </div>';
    } elseif ($tipo === 'ocorrencias' || $tipo === 'ocorrencias_estrada') {
        $dataOcRaw  = $r['data_ocorrencia'] ?? null;
        $dataOcText = $dataOcRaw ? date('d/m/Y', strtotime($dataOcRaw)) : 'Sem data';

        $createdRaw  = $r['criado_em'] ?? null;
        $createdText = $createdRaw ? date('d/m/Y H:i', strtotime($createdRaw)) : 'Sem data';

        // mantém lógica: estrada mostra "Tarefa", restantes "Estado"
        $labelEstado = ($tipo === 'ocorrencias_estrada') ? 'Tarefa' : 'Estado';

        $html .= '
        <div class="card">
            <div class="line">
                <span class="label">Descrição: </span>
                <span class="value">'.htmlspecialchars($r['descricao'] ?? '').'</span>
            </div>
            <div class="line">
                <span class="label">Local: </span>
                <span class="value">'.htmlspecialchars($r['place_name'] ?? 'Sem nome').'</span>
            </div>
            <div class="line">
                <span class="label">Latitude/Longitude: </span>
                <span class="value">'.htmlspecialchars($r['latitude'] ?? '').', '.htmlspecialchars($r['longitude'] ?? '').'</span>
            </div>
            <div class="line">
                <span class="label">Tipo de Intervenção: </span>
                <span class="value">'.htmlspecialchars($r['tipo_intervencao'] ?? 'Nenhuma').'</span>
            </div>
            <div class="line">
                <span class="label">'.$labelEstado.': </span>
                <span class="value">'.htmlspecialchars($r['estado'] ?? '').'</span>
            </div>
            <div class="line">
                <span class="label">Data da ocorrência: </span>
                <span class="value">'.$dataOcText.'</span>
            </div>
            <div class="line">
                <span class="label">Criado em: </span>
                <span class="value">'.$createdText.'</span>
            </div>
        </div>';
    } elseif ($tipo === 'utilizadores') {
        $createdRaw  = $r['created_at'] ?? null;
        $createdText = $createdRaw ? date('d/m/Y H:i', strtotime($createdRaw)) : 'Sem data';

        // transforma is_admin em texto
        $funcao = (!empty($r['is_admin']) && (int)$r['is_admin'] === 1) ? 'Admin' : 'Funcionário';

        $html .= '
        <div class="card">
            <div class="line">
                <span class="label">Username: </span>
                <span class="value">'.htmlspecialchars($r['username'] ?? '').'</span>
            </div>
            <div class="line">
                <span class="label">Email: </span>
                <span class="value">'.htmlspecialchars($r['email'] ?? '').'</span>
            </div>
            <div class="line">
                <span class="label">Posição: </span>
                <span class="value">'.htmlspecialchars($funcao).'</span>
            </div>
            <div class="line">
                <span class="label">Criado em: </span>
                <span class="value">'.$createdText.'</span>
            </div>
        </div>';
    }
}

$html .= '
    </div>
</body>
</html>
';

// 5) Dompdf
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('chroot', __DIR__);

$dompdf = new Dompdf($options);

$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = preg_replace('/[^a-z0-9_-]+/i', '_', strtolower($tipo)) . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;
