<?php
session_start();
include '../config.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 0) validar token one-time
$token = isset($_GET['token']) ? $_GET['token'] : '';
if ($token === '') {
    die('Token em falta.');
}

$stmt = $conn->prepare("
    SELECT noticia_id, used
    FROM newsletter_send_tokens
    WHERE token = ?
    LIMIT 1
");
if (!$stmt) {
    die('Erro ao preparar token: ' . $conn->error);
}
$stmt->bind_param('s', $token);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    die('Token inválido.');
}
if ((int)$row['used'] === 1) {
    die('Este link já foi usado.');
}

$noticia_id = (int)$row['noticia_id'];

// marcar token como usado
$stmt = $conn->prepare("
    UPDATE newsletter_send_tokens
    SET used = 1
    WHERE token = ?
");
if ($stmt) {
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $stmt->close();
}

if ($noticia_id <= 0) {
    die('Notícia inválida.');
}

// 1) Buscar a notícia (sem imagens)
$sql = "SELECT titulo, resumo, conteudo FROM noticias WHERE id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('Erro ao preparar query da notícia: ' . $conn->error);
}
$stmt->bind_param('i', $noticia_id);
$stmt->execute();
$result = $stmt->get_result();
$noticia = $result->fetch_assoc();
$stmt->close();

if (!$noticia) {
    die('Notícia não encontrada.');
}

// 2) Buscar apenas emails confirmados
$sql = "
    SELECT email
    FROM newsletter_subscribers
    WHERE is_confirmed = 1
";
$result = $conn->query($sql);
if (!$result) {
    die('Erro ao buscar subscritores: ' . $conn->error);
}

$total_subscritores = $result->num_rows;

// 3) Preparar email base (HTML)
$subject = 'Nova notícia em Reporta Évora: ' . $noticia['titulo'];

// BASE URL DA APLICAÇÃO (para links e unsubscribe)
$baseUrl = 'http://localhost/PAP';

// Logo URL - EdgeOne direct link
$logoUrl = 'https://lucky-plum-7yxiihmoh3.edgeone.app/logom.png';

// Corpo HTML base
$mensagem_html  = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
$mensagem_html .= '<title>Nova notícia - Reporta Évora</title></head>';
$mensagem_html .= '<body style="margin:0;padding:0;background-color:#f3f4f6;';
$mensagem_html .= 'font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;">';

$mensagem_html .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"';
$mensagem_html .= ' style="background-color:#f3f4f6;padding:24px 0;">';
$mensagem_html .= '<tr><td align="center">';

$mensagem_html .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"';
$mensagem_html .= ' style="max-width:640px;background-color:#ffffff;border-radius:16px;';
$mensagem_html .= 'border:1px solid #e5e7eb;padding:20px 24px;color:#111827;">';

// HEADER COM LOGO
$mensagem_html .= '<tr><td style="text-align:center;padding-bottom:18px;border-bottom:1px solid #e5e7eb;">'
    . '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="Reporta Évora"'
    . ' style="max-width:220px;height:auto;display:block;margin:0 auto 6px auto;">'
    . '<div style="font-size:12px;color:#6b7280;">Plataforma digital de registo de ocorrências urbanas</div>'
    . '</td></tr>';

// TÍTULO
$mensagem_html .= '<tr><td style="padding-top:18px;padding-bottom:8px;">';
$mensagem_html .= '<h2 style="margin:0;font-size:20px;line-height:1.3;color:#111827;">'
    . htmlspecialchars($noticia['titulo'], ENT_QUOTES, 'UTF-8')
    . '</h2>';
$mensagem_html .= '</td></tr>';

// RESUMO
$mensagem_html .= '<tr><td style="padding-bottom:10px;">';
$mensagem_html .= '<p style="margin:0;font-size:14px;line-height:1.6;color:#4b5563;">'
    . nl2br(htmlspecialchars($noticia['resumo'], ENT_QUOTES, 'UTF-8'))
    . '</p>';
$mensagem_html .= '</td></tr>';

// BOTÃO VER NOTÍCIA
$mensagem_html .= '<tr><td style="padding-top:14px;padding-bottom:6px;">';
$mensagem_html .= '<a href="' . htmlspecialchars($baseUrl . '/index.php?evora_p=noticias', ENT_QUOTES, 'UTF-8') . '"';
$mensagem_html .= ' style="display:inline-block;padding:10px 18px;border-radius:999px;';
$mensagem_html .= 'background:linear-gradient(135deg,#0ea5e9,#22c55e);color:#ffffff;';
$mensagem_html .= 'text-decoration:none;font-size:14px;font-weight:600;">Ver notícia completa</a>';
$mensagem_html .= '</td></tr>';

// RODAPÉ
$mensagem_html .= '<tr><td style="padding-top:18px;font-size:12px;color:#6b7280;">';
$mensagem_html .= 'Esta mensagem foi enviada pelo Reporta Évora. Por favor, não responds a este email (endereço não monitorizado).';
$mensagem_html .= '<br><br>Cumprimentos,<br>Equipa Reporta Évora';
$mensagem_html .= '</td></tr>';

// SLOT PARA UNSUBSCRIBE
$mensagem_html .= '<tr><td id="evora-unsub-slot" style="padding-top:18px;"></td></tr>';

$mensagem_html .= '</table>'; // inner card
$mensagem_html .= '</td></tr></table>'; // outer wrapper
$mensagem_html .= '</body></html>';

// headers para HTML (no-reply)
$headers  = "From: Reporta Évora <no-reply@localhost>\r\n";
$headers .= "Reply-To: no-reply@localhost\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-type: text/html; charset=UTF-8\r\n";

// 4) Enviar para todos os subscritores confirmados
while ($row = $result->fetch_assoc()) {
    $to = $row['email'];

    $unsubscribe_link = rtrim($baseUrl, '/') . "/index.php?evora_p=unsubscribe&email=" . urlencode($to);

    $mensagem_html_com_unsub = str_replace(
        '<td id="evora-unsub-slot" style="padding-top:18px;"></td>',
        '<td style="padding-top:18px;border-top:1px solid #e5e7eb;font-size:12px;color:#9ca3af;">'
        . 'Se já não quiseres receber esta newsletter, clica aqui para cancelar: '
        . '<a href="' . htmlspecialchars($unsubscribe_link, ENT_QUOTES, 'UTF-8') . '"'
        . ' style="color:#2563eb;text-decoration:underline;">Cancelar Subscrição</a>'
        . '</td>',
        $mensagem_html
    );

    file_put_contents(__DIR__ . '/last_newsletter.html', $mensagem_html_com_unsub);

    @mail($to, $subject, $mensagem_html_com_unsub, $headers);
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Newsletter enviada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            min-height: 100vh;
            background: radial-gradient(circle at top, #0f172a 0, #020617 45%, #000 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .status-card {
            max-width: 520px;
            width: 100%;
            border-radius: 18px;
            background: rgba(15,23,42,0.96);
            border: 1px solid rgba(148,163,184,0.4);
            box-shadow: 0 22px 45px rgba(0,0,0,0.75);
            color: #e5e7eb;
        }
        .status-card .icon-circle {
            width: 52px;
            height: 52px;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at 30% 0, #22c55e, #16a34a);
            box-shadow: 0 14px 30px rgba(34,197,94,0.55);
        }
        .status-card .icon-circle i {
            font-size: 1.7rem;
            color: #ecfdf5;
        }
        .status-card h1 {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        .status-card p {
            margin-bottom: 0.4rem;
        }
        .badge-count {
            border-radius: 999px;
            background: rgba(34,197,94,0.1);
            color: #bbf7d0;
            border: 1px solid rgba(34,197,94,0.5);
            font-size: 0.78rem;
            padding: 0.25rem 0.7rem;
        }
        .hint {
            font-size: 0.8rem;
            color: #9ca3af;
        }
        .btn-outline-light {
            border-radius: 999px;
            font-size: 0.85rem;
            padding: 0.4rem 1.1rem;
        }
        .btn-primary {
            border-radius: 999px;
            font-size: 0.85rem;
            padding: 0.4rem 1.2rem;
            background: linear-gradient(135deg,#0ea5e9,#22c55e);
            border: none;
            box-shadow: 0 10px 24px rgba(34,197,94,0.45);
        }
    </style>
</head>
<body>
<div class="container px-3">
    <div class="status-card mx-auto p-4 p-md-4">
        <div class="d-flex align-items-start gap-3">
            <div class="icon-circle">
                <i class="bi bi-send-check-fill"></i>
            </div>
            <div class="flex-grow-1">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <h1 class="mb-0">Newsletter enviada</h1>
                    <span class="badge-count">
                        <?= (int)$total_subscritores ?> subscritor(es)
                    </span>
                </div>
                <p class="mb-1">
                    Foram encontrados <?= (int)$total_subscritores ?> subscritores confirmados e o envio desta notícia foi iniciado com sucesso.
                </p>
                <p class="hint mb-3">
                    Se estiveres em localhost, o servidor de SMTP pode demorar alguns segundos ou enviar para a pasta de spam.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="../index.php?evora=addnoticias" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i> Adicionar outra notícia
                    </a>
                    <a href="../index.php?evora=listarnoticias" class="btn btn-outline-light">
                        <i class="bi bi-newspaper me-1"></i> Ver lista de notícias
                    </a>
                    <button type="button" class="btn btn-outline-light ms-auto" onclick="window.close();">
                        Fechar janela
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
