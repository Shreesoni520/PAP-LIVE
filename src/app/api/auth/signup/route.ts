import { NextResponse } from "next/server";
import bcrypt from "bcryptjs";
import { getDb, type RowDataPacket } from "@/lib/db";
import { sendMail } from "@/lib/mail";

function randomCode() {
  return String(Math.floor(100000 + Math.random() * 900000));
}

export async function POST(request: Request) {
  try {
    const body = (await request.json()) as {
      nome?: string;
      username?: string;
      email?: string;
      password?: string;
      phone?: string;
    };

    const nome = (body.nome || "").trim();
    const username = (body.username || "").trim();
    const email = (body.email || "").trim().toLowerCase();
    const phone = (body.phone || "").trim();
    const password = body.password || "";

    if (!nome || !username || !email || password.length < 8) {
      return NextResponse.json(
        {
          ok: false,
          error:
            "Preencha nome, utilizador, email e uma palavra-passe com pelo menos 8 caracteres.",
        },
        { status: 400 },
      );
    }

    const db = getDb();

    const [existing] = await db.query<RowDataPacket[]>(
      `SELECT id FROM users_public
       WHERE username = :username OR email = :email
       LIMIT 1`,
      { username, email },
    );

    if (existing.length > 0) {
      return NextResponse.json(
        { ok: false, error: "Utilizador ou email já registado." },
        { status: 409 },
      );
    }

    const passwordHash = await bcrypt.hash(password, 12);
    const code = randomCode();
    const expires = new Date(Date.now() + 30 * 60 * 1000);

    await db.execute(
      `INSERT INTO pending_user_verifications
         (nome, username, email, phone, password_hash, code, expires_at)
       VALUES
         (:nome, :username, :email, :phone, :passwordHash, :code, :expires)
       ON DUPLICATE KEY UPDATE
         nome = VALUES(nome),
         username = VALUES(username),
         phone = VALUES(phone),
         password_hash = VALUES(password_hash),
         code = VALUES(code),
         expires_at = VALUES(expires_at)`,
      { nome, username, email, phone, passwordHash, code, expires },
    );

    const appUrl = process.env.APP_URL || "http://localhost:3000";
    await sendMail({
      to: email,
      subject: "Confirme o seu registo - Reporta Évora",
      html: `<p>Olá ${nome},</p>
             <p>O seu código de verificação é <strong>${code}</strong>.</p>
             <p><a href="${appUrl}/signup/verify">Confirmar registo</a></p>`,
    });

    return NextResponse.json({
      ok: true,
      message:
        "Enviámos um código de verificação para o seu email. Confirme para ativar a conta.",
    });
  } catch (error) {
    console.error("[signup]", error);
    return NextResponse.json(
      {
        ok: false,
        error:
          "Não foi possível criar a conta. Confirme a base de dados e tente novamente.",
      },
      { status: 500 },
    );
  }
}
