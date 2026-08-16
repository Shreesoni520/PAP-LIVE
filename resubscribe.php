<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/config.php';

/*
 * 1) Send repermission emails
 *    - Unconfirmed
 *    - Not unsubscribed
 *    - Created more than 2 months ago
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
    die('DB error (select repermission): ' . $conn->error);
}

while ($row = $result->fetch_assoc()) {
    $id    = (int)$row['id'];
    $email = $row['email'];

    // Generate a new token for this final email
    $token = bin2hex(random_bytes(16));

    // Store token on the same row
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

    // Base URL (localhost for now)
    $baseUrl    = 'http://localhost/PAP';
    $renew_link = $baseUrl . "/resubscriber.php?token=" . urlencode($token);

    // Logo URL – EdgeOne direct link
    $logoUrl = 'https://lucky-plum-7yxiihmoh3.edgeone.app/logom.png';

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

    // HEADER COM LOGO (igual ao da newsletter)
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
    $mensagem_html .= 'Queremos confirmar se ainda queres receber a newsletter do ';
    $mensagem_html .= '<strong>Reporta Évora</strong> neste endereço de email. ';
    $mensagem_html .= 'Se quiseres continuar a receber novidades sobre ocorrências urbanas e notícias, confirma abaixo.';
    $mensagem_html .= '</p>';
    $mensagem_html .= '</td></tr>';

    // BOTÃO (mesmo estilo gradient)
    $mensagem_html .= '<tr><td style="padding-top:14px;padding-bottom:6px;">';
    $mensagem_html .= '<a href="' . htmlspecialchars($renew_link, ENT_QUOTES, 'UTF-8') . '" ';
    $mensagem_html .= 'style="display:inline-block;padding:10px 18px;border-radius:999px;';
    $mensagem_html .= 'background:linear-gradient(135deg,#0ea5e9,#22c55e);color:#ffffff;';
    $mensagem_html .= 'text-decoration:none;font-size:14px;font-weight:600;">';
    $mensagem_html .= 'Continuar a receber</a>';
    $mensagem_html .= '</td></tr>';

    // LINK EM TEXTO
    $mensagem_html .= '<tr><td style="padding-top:12px;font-size:12px;color:#6b7280;">';
    $mensagem_html .= 'Se o botão não funcionar, copia e cola este link no teu navegador:<br>';
    $mensagem_html .= '<span style="word-break:break-all;">';
    $mensagem_html .= htmlspecialchars($renew_link, ENT_QUOTES, 'UTF-8');
    $mensagem_html .= '</span>';
    $mensagem_html .= '</td></tr>';

    // AVISO
    $mensagem_html .= '<tr><td style="padding-top:16px;font-size:12px;color:#6b7280;">';
    $mensagem_html .= 'Se ignorares este email, este endereço poderá ser removido da newsletter no futuro.';
    $mensagem_html .= '</td></tr>';

    // ASSINATURA
    $mensagem_html .= '<tr><td style="padding-top:24px;font-size:12px;color:#6b7280;">';
    $mensagem_html .= 'Cumprimentos,<br>Equipa Reporta Évora';
    $mensagem_html .= '</td></tr>';

    $mensagem_html .= '</table>'; // inner
    $mensagem_html .= '</td></tr></table>'; // outer
    $mensagem_html .= '</body></html>';

    // no-reply headers
    $fromEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'no-reply@localhost';

    $headers  = "From: Reporta Évora <" . $fromEmail . ">\r\n";
    $headers .= "Reply-To: " . $fromEmail . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";

    @mail($email, $subject, $mensagem_html, $headers);
}

/*
 * 2) Cleanup:
 *    Delete unconfirmed, not unsubscribed, older than 2 months
 */

$deleteSql = "
    DELETE FROM newsletter_subscribers
    WHERE is_confirmed = 0
      AND unsubscribed_at IS NULL
      AND created_at < DATE_SUB(NOW(), INTERVAL 2 MONTH)
";

if (!$conn->query($deleteSql)) {
    die('DB error (cleanup): ' . $conn->error);
}

$conn->close();

// Optional output if you open in browser
echo 'Repermission emails sent and old unconfirmed subscribers cleaned.';
