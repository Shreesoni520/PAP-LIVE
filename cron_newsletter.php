<?php
// cron / resubscricao newsletter

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/config.php';

// Detectar se está no browser ou em cron (CLI)
$isBrowser = (PHP_SAPI !== 'cli'); // true quando abres em http://localhost/...

/*
 * 1) Enviar emails de re-permissão
 *    - Não confirmados
 *    - Não anulados
 *    - Criados há mais de 2 meses
 */

$sql = "
    SELECT id, email, is_confirmed, confirm_token, created_at, confirmed_at, unsubscribed_at
    FROM newsletter_subscribers
    WHERE unsubscribed_at IS NULL
      AND is_confirmed = 0
      AND created_at < DATE_SUB(NOW(), INTERVAL 2 MONTH)
";

$result = $conn->query($sql);

if (!$result) {
    error_log('DB error (select repermission): ' . $conn->error);
    if ($isBrowser) {
        http_response_code(500);
        echo "<h1>Erro na base de dados</h1>";
    }
    exit;
}

$logoUrl   = 'https://lucky-plum-7yxiihmoh3.edgeone.app/logom.png';
$footerUrl = 'http://localhost/PAP/index.php?evora_p=inicio#footer';

$sentCount = 0;

while ($row = $result->fetch_assoc()) {
    $id    = (int)$row['id'];
    $email = $row['email'];

    $token = bin2hex(random_bytes(16));

    $updateSql = "
        UPDATE newsletter_subscribers
        SET confirm_token = ?
        WHERE id = ?
    ";
    $upd = $conn->prepare($updateSql);
    if ($upd) {
        $upd->bind_param('si', $token, $id);
        $upd->execute();
        $upd->close();
    }

    $subject = "Queres continuar a receber a newsletter do Reporta Évora?";

    $mensagem_html  = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    $mensagem_html .= '<title>Renovar subscrição - Reporta Évora</title></head>';
    $mensagem_html .= '<body style="margin:0;padding:0;background-color:#f3f4f6;';
    $mensagem_html .= 'font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;">';

    $mensagem_html .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" ';
    $mensagem_html .= 'style="background-color:#f3f4f6;padding:24px 0;">';
    $mensagem_html .= '<tr><td align="center">';

    $mensagem_html .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" ';
    $mensagem_html .= 'style="max-width:640px;background-color:#ffffff;border-radius:16px;';
    $mensagem_html .= 'border:1px solid #e5e7eb;padding:20px 24px;color:#111827;">';

    // HEADER
    $mensagem_html .= '<tr><td style="text-align:center;padding-bottom:18px;border-bottom:1px solid #e5e7eb;">';
    $mensagem_html .= '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="Reporta Évora" ';
    $mensagem_html .= 'style="max-width:220px;height:auto;display:block;margin:0 auto 6px auto;">';
    $mensagem_html .= '<div style="font-size:12px;color:#6b7280;">Plataforma digital de registo de ocorrências urbanas</div>';
    $mensagem_html .= '</td></tr>';

    // TÍTULO
    $mensagem_html .= '<tr><td style="padding-top:18px;padding-bottom:8px;">';
    $mensagem_html .= '<h2 style="margin:0;font-size:20px;line-height:1.3;color:#111827;">';
    $mensagem_html .= 'Renovar subscrição da newsletter</h2>';
    $mensagem_html .= '</td></tr>';

    // TEXTO PRINCIPAL
    $mensagem_html .= '<tr><td style="padding-bottom:10px;">';
    $mensagem_html .= '<p style="margin:0;color:#4b5563;font-size:14px;line-height:1.6;">';
    $mensagem_html .= 'Para continuares a receber a newsletter do <strong>Reporta Évora</strong>, ';
    $mensagem_html .= 'clica no botão em baixo para ires diretamente à nossa página e faz uma nova subscrição no fundo da página.';
    $mensagem_html .= '</p>';
    $mensagem_html .= '</td></tr>';

    // AVISO
    $mensagem_html .= '<tr><td style="padding-top:4px;padding-bottom:4px;">';
    $mensagem_html .= '<p style="margin:0;color:#6b7280;font-size:12px;line-height:1.6;">';
    $mensagem_html .= 'Se não fizeres nada, vamos remover este endereço da nossa lista de emails dentro de algum tempo.';
    $mensagem_html .= '</p>';
    $mensagem_html .= '</td></tr>';

    // BOTÃO
    $mensagem_html .= '<tr><td style="padding-top:14px;padding-bottom:6px;">';
    $mensagem_html .= '<a href="' . htmlspecialchars($footerUrl, ENT_QUOTES, 'UTF-8') . '" ';
    $mensagem_html .= 'style="display:inline-block;padding:10px 18px;border-radius:999px;';
    $mensagem_html .= 'background:linear-gradient(135deg,#0ea5e9,#22c55e);color:#ffffff;';
    $mensagem_html .= 'text-decoration:none;font-size:14px;font-weight:600;">';
    $mensagem_html .= 'Atualizar a minha subscrição</a>';
    $mensagem_html .= '</td></tr>';

    // LINK TEXTO
    $mensagem_html .= '<tr><td style="padding-top:12px;font-size:12px;color:#6b7280;">';
    $mensagem_html .= 'Se o botão não funcionar, copia e cola este link no teu navegador:<br>';
    $mensagem_html .= '<span style="word-break:break-all;">';
    $mensagem_html .= htmlspecialchars($footerUrl, ENT_QUOTES, 'UTF-8');
    $mensagem_html .= '</span>';
    $mensagem_html .= '</td></tr>';

    // ASSINATURA
    $mensagem_html .= '<tr><td style="padding-top:24px;font-size:12px;color:#6b7280;">';
    $mensagem_html .= 'Cumprimentos,<br>Equipa Reporta Évora';
    $mensagem_html .= '</td></tr>';

    $mensagem_html .= '</table></td></tr></table></body></html>';

    $fromEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'no-reply@localhost';

    $headers  = "From: Reporta Évora <" . $fromEmail . ">\r\n";
    $headers .= "Reply-To: " . $fromEmail . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";

    if (@mail($email, $subject, $mensagem_html, $headers)) {
        $sentCount++;
    }
}

/*
 * 2) Limpeza
 */
$deleteSql = "
    DELETE FROM newsletter_subscribers
    WHERE is_confirmed = 0
      AND unsubscribed_at IS NULL
      AND created_at < DATE_SUB(NOW(), INTERVAL 2 MONTH)
";

if (!$conn->query($deleteSql)) {
    error_log('DB error (cleanup): ' . $conn->error);
}

$conn->close();

// Se for cron/CLI: sair em silêncio
if (!$isBrowser) {
    exit;
}

// Se for browser: mostrar página simples e bonita
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Cron Newsletter - Reporta Évora</title>
    <style>
        body {
            margin: 0;
            padding: 40px 16px;
            font-family: system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
            background-color: #f3f4f6;
            color: #111827;
            display: flex;
            justify-content: center;
        }
        .card {
            width: 100%;
            max-width: 520px;
            background-color: #ffffff;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 10px 25px rgba(15,23,42,0.08);
            padding: 18px 20px 16px;
        }
        .card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 8px;
        }
        .card-logo {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg,#0ea5e9,#22c55e);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            color: #f9fafb;
        }
        .card-header h1 {
            margin: 0;
            font-size: 16px;
        }
        .card-header p {
            margin: 2px 0 0;
            font-size: 12px;
            color: #6b7280;
        }
        .card-body {
            margin-top: 8px;
            font-size: 13px;
            color: #374151;
        }
        .card-metric {
            margin-top: 10px;
            padding: 8px 10px;
            border-radius: 8px;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-metric span {
            font-size: 12px;
            color: #6b7280;
        }
        .card-metric strong {
            font-size: 15px;
            color: #1d4ed8;
        }
        .card-footer {
            margin-top: 10px;
            font-size: 11px;
            color: #6b7280;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 6px;
        }
        .card-footer a {
            color: #2563eb;
            text-decoration: none;
        }
        .card-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="card">
    <div class="card-header">
        <div class="card-logo"></div>
        <div>
            <h1>Cron da newsletter executado</h1>
            <p>Emails de renovação enviados e subscritores antigos limpos.</p>
        </div>
    </div>

    <div class="card-body">
        A tarefa automática da newsletter foi concluída com sucesso.
        <div class="card-metric">
            <div>
                Emails de renovação enviados<br>
                <span>Subscritores antigos não confirmados</span>
            </div>
            <strong><?php echo (int)$sentCount; ?></strong>
        </div>
    </div>

    <div class="card-footer">
        <span>Este script é pensado para correr em cron (sem mostrar esta página).</span>
        <a href="/PAP/index.php?evora_p=inicio">Abrir site público</a>
    </div>
</div>
</body>
</html>
