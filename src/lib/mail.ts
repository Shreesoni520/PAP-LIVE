import nodemailer from "nodemailer";

export async function sendMail(options: {
  to: string;
  subject: string;
  html: string;
}): Promise<{ ok: boolean; error?: string }> {
  const host = process.env.SMTP_HOST;
  const from =
    process.env.SMTP_FROM || "Reporta Évora <no-reply@example.com>";

  if (!host) {
    console.info("[mail:dev]", {
      to: options.to,
      subject: options.subject,
    });
    return { ok: true };
  }

  try {
    const transporter = nodemailer.createTransport({
      host,
      port: Number(process.env.SMTP_PORT || 587),
      secure: Number(process.env.SMTP_PORT || 587) === 465,
      auth:
        process.env.SMTP_USER && process.env.SMTP_PASS
          ? {
              user: process.env.SMTP_USER,
              pass: process.env.SMTP_PASS,
            }
          : undefined,
    });

    await transporter.sendMail({
      from,
      to: options.to,
      subject: options.subject,
      html: options.html,
    });

    return { ok: true };
  } catch (error) {
    const message = error instanceof Error ? error.message : "mail failed";
    console.error("[mail]", message);
    return { ok: false, error: message };
  }
}
