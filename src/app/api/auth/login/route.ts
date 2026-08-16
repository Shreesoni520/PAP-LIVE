import { NextResponse } from "next/server";
import bcrypt from "bcryptjs";
import { getDb, type RowDataPacket } from "@/lib/db";
import { sendMail } from "@/lib/mail";
import { getPublicSession } from "@/lib/session";

type PublicUser = RowDataPacket & {
  id: number;
  nome: string;
  email: string;
  password_hash: string;
  twofa_enabled: number | boolean;
  photo?: string | null;
};

function randomCode() {
  return String(Math.floor(100000 + Math.random() * 900000));
}

export async function POST(request: Request) {
  try {
    const body = (await request.json()) as {
      username?: string;
      password?: string;
    };

    const username = (body.username || "").trim();
    const password = body.password || "";

    if (!username || !password) {
      return NextResponse.json(
        { ok: false, error: "Preencha todos os campos." },
        { status: 400 },
      );
    }

    const db = getDb();
    const [rows] = await db.query<PublicUser[]>(
      `SELECT id, nome, email, password_hash, twofa_enabled, photo
       FROM users_public
       WHERE username = :username OR email = :username
       LIMIT 1`,
      { username },
    );

    const user = rows[0];
    if (!user || !(await bcrypt.compare(password, user.password_hash))) {
      return NextResponse.json(
        { ok: false, error: "Credenciais inválidas." },
        { status: 401 },
      );
    }

    const session = await getPublicSession();

    if (user.twofa_enabled) {
      const code = randomCode();
      const expires = new Date(Date.now() + 10 * 60 * 1000);

      await db.execute(
        `INSERT INTO user_twofa_codes_public (user_id, code, expires_at)
         VALUES (:userId, :code, :expires)`,
        { userId: user.id, code, expires },
      );

      const appUrl = process.env.APP_URL || "http://localhost:3000";
      await sendMail({
        to: user.email,
        subject: "Código de autenticação em dois fatores - Reporta Évora",
        html: `<p>O seu código é <strong>${code}</strong>.</p>
               <p>Válido por 10 minutos.</p>
               <p><a href="${appUrl}/verify-2fa">Introduzir código</a></p>`,
      });

      session.public2faPending = {
        userId: user.id,
        nome: user.nome,
        email: user.email,
      };
      await session.save();

      return NextResponse.json({ ok: true, require2fa: true });
    }

    session.publicUserId = user.id;
    session.publicUserNome = user.nome;
    session.publicUserAvatar = user.photo || null;
    delete session.public2faPending;
    await session.save();

    return NextResponse.json({ ok: true });
  } catch (error) {
    console.error("[login]", error);
    return NextResponse.json(
      {
        ok: false,
        error:
          "Serviço temporariamente indisponível. Verifique a ligação à base de dados.",
      },
      { status: 500 },
    );
  }
}
