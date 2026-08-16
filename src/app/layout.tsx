import type { Metadata } from "next";
import { Jost, Poppins } from "next/font/google";
import { SiteFooter } from "@/components/SiteFooter";
import { SiteHeader } from "@/components/SiteHeader";
import { getPublicSession } from "@/lib/session";
import "./globals.css";

const poppins = Poppins({
  variable: "--font-display",
  subsets: ["latin"],
  weight: ["400", "500", "600", "700"],
});

const jost = Jost({
  variable: "--font-body",
  subsets: ["latin"],
  weight: ["300", "400", "500", "600"],
});

export const metadata: Metadata = {
  title: {
    default: "Reporta Évora",
    template: "%s · Reporta Évora",
  },
  description:
    "Plataforma digital de registo de ocorrências urbanas em Évora.",
};

export default async function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  const session = await getPublicSession();
  const user = session.publicUserId
    ? {
        id: session.publicUserId,
        nome: session.publicUserNome || "Utilizador",
        avatar: session.publicUserAvatar,
      }
    : null;

  return (
    <html lang="pt-PT">
      <body className={`${poppins.variable} ${jost.variable} antialiased`}>
        <SiteHeader user={user} />
        <main className="page-main">{children}</main>
        <SiteFooter />
      </body>
    </html>
  );
}
