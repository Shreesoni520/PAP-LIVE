import { NextResponse } from "next/server";
import { getDb, type RowDataPacket } from "@/lib/db";
import { getPublicSession } from "@/lib/session";

export async function POST(request: Request) {
  try {
    const body = (await request.json()) as { code?: string };
    const code = (body.code || "").trim();
    const session = await getPublicSession();
    const pending = session.public2faPending;

    if (!pending?.userId || !code) {
      return NextResponse.json(
        { ok: false, error: "Sessão 2FA inválida. Volte a iniciar sessão." },
        { status: 400 },
      );
    }

    const db = getDb();
    const [rows] = await db.query<RowDataPacket[]>(
      `SELECT id FROM user_twofa_codes_public
       WHERE user_id = :userId AND code = :code AND expires_at >= NOW()
       ORDER BY id DESC LIMIT 1`,
      { userId: pending.userId, code },
    );

    if (rows.length === 0) {
      return NextResponse.json(
        { ok: false, error: "Código inválido ou expirado." },
        { status: 401 },
      );
    }

    await db.execute(
      `DELETE FROM user_twofa_codes_public WHERE user_id = :userId`,
      { userId: pending.userId },
    );

    const [users] = await db.query<RowDataPacket[]>(
      `SELECT id, nome, photo FROM users_public WHERE id = :id LIMIT 1`,
      { id: pending.userId },
    );
    const user = users[0];
    if (!user) {
      return NextResponse.json(
        { ok: false, error: "Utilizador não encontrado." },
        { status: 404 },
      );
    }

    session.publicUserId = Number(user.id);
    session.publicUserNome = String(user.nome);
    session.publicUserAvatar = (user.photo as string | null) || null;
    delete session.public2faPending;
    await session.save();

    return NextResponse.json({ ok: true });
  } catch (error) {
    console.error("[verify-2fa]", error);
    return NextResponse.json(
      { ok: false, error: "Falha na verificação." },
      { status: 500 },
    );
  }
}
