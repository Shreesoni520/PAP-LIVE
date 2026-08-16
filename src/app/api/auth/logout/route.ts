import { NextResponse } from "next/server";
import { getPublicSession } from "@/lib/session";

async function logoutAndRedirect(request: Request) {
  const session = await getPublicSession();
  session.destroy();
  return NextResponse.redirect(new URL("/", request.url), { status: 303 });
}

export async function GET(request: Request) {
  return logoutAndRedirect(request);
}

export async function POST(request: Request) {
  return logoutAndRedirect(request);
}
