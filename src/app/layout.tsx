import type { Metadata } from "next";
import { SiteFooter } from "@/components/SiteFooter";
import { SiteHeader } from "@/components/SiteHeader";
import { SiteScripts } from "@/components/SiteScripts";
import { getPublicSession } from "@/lib/session";
import "./globals.css";
import "./legacy-extra.css";

export const metadata: Metadata = {
  title: {
    default: "Reporta Évora",
    template: "%s · Reporta Évora",
  },
  description:
    "Plataforma digital de registo de ocorrências urbanas em Évora.",
  icons: {
    icon: "/assets/img/favicon.png",
    apple: "/assets/img/apple-touch-icon.png",
  },
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
      <head>
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link
          rel="preconnect"
          href="https://fonts.gstatic.com"
          crossOrigin="anonymous"
        />
        <link
          href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&family=Poppins:wght@300;400;500;600&family=Jost:wght@300;400;500;600&display=swap"
          rel="stylesheet"
        />
        <link
          href="/assets/vendor/bootstrap/css/bootstrap.min.css"
          rel="stylesheet"
        />
        <link
          href="/assets/vendor/bootstrap-icons/bootstrap-icons.css"
          rel="stylesheet"
        />
        <link href="/assets/vendor/aos/aos.css" rel="stylesheet" />
        <link
          href="/assets/vendor/glightbox/css/glightbox.min.css"
          rel="stylesheet"
        />
        <link
          href="/assets/vendor/swiper/swiper-bundle.min.css"
          rel="stylesheet"
        />
        <link href="/assets/css/main.css" rel="stylesheet" />
      </head>
      <body className="index-page">
        <header
          id="header"
          className="header d-flex align-items-center fixed-top"
        >
          <SiteHeader user={user} />
        </header>

        <main className="main">{children}</main>

        <SiteFooter isLoggedIn={Boolean(user)} />

        <a
          href="#"
          id="scroll-top"
          className="scroll-top d-flex align-items-center justify-content-center"
        >
          <i className="bi bi-arrow-up-short" />
        </a>

        <div id="preloader" />
        <SiteScripts />
      </body>
    </html>
  );
}
