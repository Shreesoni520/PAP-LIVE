import Link from "next/link";

export function SiteFooter() {
  return (
    <footer className="site-footer">
      <div className="site-footer-inner">
        <div>
          <p className="footer-brand">Reporta Évora</p>
          <p className="footer-copy">
            Plataforma digital de registo de ocorrências urbanas.
          </p>
        </div>
        <div className="footer-links">
          <Link href="/information">Informação útil</Link>
          <Link href="/contact">Contacto</Link>
          <Link href="/noticias">Notícias</Link>
          <Link href="/admin">Área administrativa</Link>
        </div>
      </div>
      <p className="footer-legal">
        © {new Date().getFullYear()} Reporta Évora. Todos os direitos
        reservados.
      </p>
    </footer>
  );
}
