import Link from "next/link";
import { formatDisplayName, initialsFromName } from "@/lib/format";

type NavUser = {
  id: number;
  nome: string;
  avatar?: string | null;
};

const links = [
  { href: "/", label: "Início" },
  { href: "/information", label: "Informação" },
  { href: "/mapa", label: "Mapa" },
  { href: "/ocorrencias", label: "Ocorrências" },
  { href: "/ocorrencias-estrada", label: "Estrada" },
  { href: "/noticias", label: "Notícias" },
  { href: "/contact", label: "Contacto" },
];

export function SiteHeader({ user }: { user: NavUser | null }) {
  return (
    <header className="site-header">
      <div className="site-header-inner">
        <Link href="/" className="brand">
          <span className="brand-mark">RE</span>
          <span className="brand-text">
            Reporta <em>Évora</em>
          </span>
        </Link>

        <nav className="site-nav" aria-label="Principal">
          {links.map((link) => (
            <Link key={link.href} href={link.href} className="nav-link">
              {link.label}
            </Link>
          ))}
        </nav>

        <div className="header-actions">
          {user ? (
            <div className="user-menu">
              <Link href="/profile" className="nav-avatar-toggle">
                <span className="nav-avatar" aria-hidden>
                  {user.avatar ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img
                      src={user.avatar}
                      alt=""
                      className="nav-avatar-img"
                    />
                  ) : (
                    <span className="nav-avatar-initials">
                      {initialsFromName(user.nome)}
                    </span>
                  )}
                </span>
                <span className="user-name">
                  {formatDisplayName(user.nome)}
                </span>
              </Link>
              <form action="/api/auth/logout" method="post">
                <button type="submit" className="btn-ghost">
                  Sair
                </button>
              </form>
            </div>
          ) : (
            <>
              <Link href="/login" className="btn-ghost">
                Entrar
              </Link>
              <Link href="/signup" className="btn-primary">
                Criar conta
              </Link>
            </>
          )}
        </div>
      </div>
    </header>
  );
}
