const nodemailer = require("nodemailer");

const LOGO_URL = "https://lucky-plum-7yxiihmoh3.edgeone.app/logom.png";
const SITE_NAME = "Reporta Évora";

async function sendMail({ to, subject, html }) {
  const host = process.env.SMTP_HOST;
  const from = process.env.SMTP_FROM || `${SITE_NAME} <no-reply@example.com>`;

  if (!host) {
    console.info("[mail:dev]", { to, subject });
    return { ok: true };
  }

  try {
    const transporter = nodemailer.createTransport({
      host,
      port: Number(process.env.SMTP_PORT || 587),
      secure: Number(process.env.SMTP_PORT || 587) === 465,
      auth:
        process.env.SMTP_USER && process.env.SMTP_PASS
          ? { user: process.env.SMTP_USER, pass: process.env.SMTP_PASS }
          : undefined,
    });

    await transporter.sendMail({ from, to, subject, html });
    return { ok: true };
  } catch (error) {
    const message = error instanceof Error ? error.message : "mail failed";
    console.error("[mail]", message);
    return { ok: false, error: message };
  }
}

function codeEmailHtml(title, intro, code, linkPath) {
  const appUrl = process.env.APP_URL || "http://localhost:3000";
  const link = linkPath ? `${appUrl}${linkPath}` : null;
  return `<!DOCTYPE html><html><body style="font-family:system-ui,sans-serif;background:#f3f4f6;padding:24px;">
  <div style="max-width:640px;margin:0 auto;background:#fff;border-radius:16px;padding:24px;border:1px solid #e5e7eb;">
    <div style="text-align:center;margin-bottom:16px;">
      <img src="${LOGO_URL}" alt="${SITE_NAME}" style="max-width:220px;height:auto;">
    </div>
    <h2 style="margin:0 0 12px;color:#111827;">${title}</h2>
    <p style="color:#4b5563;font-size:14px;line-height:1.6;">${intro}</p>
    <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:16px;text-align:center;margin:16px 0;">
      <div style="font-size:12px;color:#6b7280;font-weight:600;">Código</div>
      <div style="font-size:26px;letter-spacing:.25em;font-weight:700;color:#111827;">${code}</div>
      <div style="font-size:12px;color:#6b7280;margin-top:6px;">Válido durante 10 minutos.</div>
    </div>
    ${link ? `<p><a href="${link}">Continuar</a></p>` : ""}
  </div></body></html>`;
}

async function sendTwoFaCodeEmail(to, code, path = "/verify-2fa.html") {
  return sendMail({
    to,
    subject: `Código de autenticação em dois fatores - ${SITE_NAME}`,
    html: codeEmailHtml(
      "Código de autenticação em dois fatores",
      `Utilize o código abaixo para concluir a sua sessão em <strong>${SITE_NAME}</strong>.`,
      code,
      path,
    ),
  });
}

async function sendSignupCodeEmail(to, code) {
  return sendMail({
    to,
    subject: `Código de verificação de email - ${SITE_NAME}`,
    html: codeEmailHtml(
      "Verifique o seu endereço de email",
      `Introduza o código abaixo na página de registo para confirmar o email.`,
      code,
      "/signup.html",
    ),
  });
}

async function sendPasswordResetEmail(to, token) {
  const appUrl = process.env.APP_URL || "http://localhost:3000";
  const link = `${appUrl}/reset-password.html?token=${encodeURIComponent(token)}`;
  return sendMail({
    to,
    subject: `Recuperação de palavra-passe - ${SITE_NAME}`,
    html: `<p>Clique para redefinir a palavra-passe (válido 15 minutos):</p><p><a href="${link}">${link}</a></p>`,
  });
}

async function sendNewsletterConfirmEmail(to, token) {
  const appUrl = process.env.APP_URL || "http://localhost:3000";
  const link = `${appUrl}/newsletter-confirm.html?token=${encodeURIComponent(token)}`;
  return sendMail({
    to,
    subject: `Confirme a newsletter - ${SITE_NAME}`,
    html: `<p>Confirme a sua inscrição:</p><p><a href="${link}">${link}</a></p>`,
  });
}

async function sendEmailChangeCodeEmail(to, code) {
  return sendMail({
    to,
    subject: `Confirmar novo email - ${SITE_NAME}`,
    html: codeEmailHtml("Confirmar novo email", "Código para confirmar o novo email:", code, "/profile.html"),
  });
}

async function sendOcorrenciaAdminEmail(data) {
  const to = process.env.ADMIN_EMAIL;
  if (!to) return { ok: true };
  return sendMail({
    to,
    subject: `Nova ocorrência (${data.kind}) - ${SITE_NAME}`,
    html: `<p><strong>Tipo:</strong> ${data.kind}</p>
      <p><strong>Descrição:</strong> ${data.descricao || ""}</p>
      <p><strong>Local:</strong> ${data.placeName || ""}</p>
      <p><strong>Coords:</strong> ${data.latitude}, ${data.longitude}</p>`,
  });
}

module.exports = {
  sendMail,
  sendTwoFaCodeEmail,
  sendSignupCodeEmail,
  sendPasswordResetEmail,
  sendNewsletterConfirmEmail,
  sendEmailChangeCodeEmail,
  sendOcorrenciaAdminEmail,
};
