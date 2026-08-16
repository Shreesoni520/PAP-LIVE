import { getIronSession, SessionOptions } from "iron-session";
import { cookies } from "next/headers";

export type PublicSessionData = {
  publicUserId?: number;
  publicUserNome?: string;
  publicUserAvatar?: string | null;
  public2faPending?: {
    userId: number;
    nome: string;
    email: string;
  };
};

export type AdminSessionData = {
  userId?: number;
  username?: string;
  isAdmin?: boolean;
  admin2faPending?: {
    userId: number;
    username: string;
    isAdmin: boolean;
  };
};

const sessionPassword =
  process.env.SESSION_SECRET || "dev-only-change-me-32chars-minimum!!";

export const publicSessionOptions: SessionOptions = {
  cookieName: "pap_public_session",
  password: sessionPassword,
  cookieOptions: {
    secure: process.env.NODE_ENV === "production",
    httpOnly: true,
    sameSite: "lax",
    path: "/",
  },
};

export const adminSessionOptions: SessionOptions = {
  cookieName: "pap_admin_session",
  password: sessionPassword,
  cookieOptions: {
    secure: process.env.NODE_ENV === "production",
    httpOnly: true,
    sameSite: "lax",
    path: "/",
  },
};

export async function getPublicSession() {
  return getIronSession<PublicSessionData>(
    await cookies(),
    publicSessionOptions,
  );
}

export async function getAdminSession() {
  return getIronSession<AdminSessionData>(
    await cookies(),
    adminSessionOptions,
  );
}
