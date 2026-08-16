import { NextResponse } from "next/server";
import { getPublicSession } from "@/lib/session";

export async function POST(request: Request) {
  const session = await getPublicSession();
  session.destroy();
  return NextResponse.redirect(new URL("/", request.url), { status: 303 });
}
