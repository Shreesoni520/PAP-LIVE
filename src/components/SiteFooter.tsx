"use client";

import Link from "next/link";

export function SiteFooter({
  isLoggedIn,
  newsletterError,
  newsletterSuccess,
}: {
  isLoggedIn: boolean;
  newsletterError?: string;
  newsletterSuccess?: string;
}) {
  const year = new Date().getFullYear();

  return (
    <>
      <footer
        id="footer"
        className="footer text-white pt-5"
        style={{
          background:
            "radial-gradient(circle at top, #111827 0%, #020617 45%, #000000 100%)",
          borderTop: "1px solid rgba(255,255,255,0.08)",
        }}
      >
        <div className="container">
          <div className="row justify-content-center text-center pb-5">
            <div className="col-lg-8">
              <span
                style={{
                  letterSpacing: ".12em",
                  color: "#6b7280",
                  fontSize: ".75rem",
                  textTransform: "uppercase",
                }}
              >
                Fica a par das novidades
              </span>

              <h4 className="mt-2 mb-2" style={{ fontWeight: 700 }}>
                Subscreve a nossa newsletter
              </h4>

              <p
                className="mb-4"
                style={{ color: "#9ca3af", fontSize: "0.95rem" }}
              >
                Recebe notícias, alertas e informação útil sobre Évora
                diretamente no teu email.
              </p>

              <form action="/api/newsletter" method="post">
                <div
                  className="d-flex flex-column flex-sm-row align-items-stretch gap-2 mx-auto"
                  style={{ maxWidth: 560 }}
                >
                  <div
                    className="d-flex align-items-center flex-grow-1 px-3 py-2"
                    style={{
                      background: "rgba(15,23,42,0.95)",
                      borderRadius: 999,
                      border: "1px solid rgba(148,163,184,0.35)",
                      boxShadow: "0 18px 35px rgba(0,0,0,0.45)",
                    }}
                  >
                    <span
                      className="me-2"
                      style={{ color: "#9ca3af", fontSize: "1rem" }}
                    >
                      <i className="bi bi-envelope-fill" />
                    </span>
                    <input
                      type="email"
                      name="email"
                      className="form-control border-0 bg-transparent text-white"
                      placeholder="O teu email"
                      required
                      style={{ fontSize: "0.95rem", boxShadow: "none" }}
                    />
                  </div>
                  <button
                    type="submit"
                    className="btn"
                    style={{
                      borderRadius: 999,
                      padding: "10px 24px",
                      fontSize: "0.95rem",
                      fontWeight: 600,
                      background: "linear-gradient(135deg,#0ea5e9,#22c55e)",
                      border: "none",
                      color: "#fff",
                      whiteSpace: "nowrap",
                      boxShadow: "0 12px 30px rgba(34,197,94,0.25)",
                    }}
                  >
                    Subscrever
                  </button>
                </div>

                {newsletterError ? (
                  <div
                    className="mt-3"
                    style={{ fontSize: "0.85rem", color: "#fca5a5" }}
                  >
                    {newsletterError}
                  </div>
                ) : null}
                {newsletterSuccess ? (
                  <div
                    className="mt-3"
                    style={{ fontSize: "0.85rem", color: "#bbf7d0" }}
                  >
                    {newsletterSuccess}
                  </div>
                ) : null}
              </form>
            </div>
          </div>

          <div className="row gy-4 pb-4">
            <div className="col-lg-4 col-md-6">
              <Link
                href="/"
                className="text-decoration-none d-inline-block mb-3"
              >
                <span
                  style={{
                    fontWeight: 700,
                    fontSize: "1.5rem",
                    color: "#ffffff",
                  }}
                >
                  Reporta Évora
                </span>
              </Link>
              <p
                style={{
                  color: "#9ca3af",
                  fontSize: "0.92rem",
                  lineHeight: 1.7,
                }}
              >
                Plataforma digital para consulta de informação útil e registo
                de ocorrências urbanas em Évora. Um único portal com notícias,
                mapas, contactos e serviços importantes para a cidade.
              </p>
              <div className="mt-3" style={{ fontSize: "0.9rem" }}>
                <p className="mb-1" style={{ color: "#e5e7eb" }}>
                  Av. Dinis Miranda
                </p>
                <p className="mb-2" style={{ color: "#e5e7eb" }}>
                  Évora, 7005-140
                </p>
                <p className="mb-1" style={{ color: "#9ca3af" }}>
                  <strong>Telefone:</strong> +351 920 263 262
                </p>
                <p className="mb-0" style={{ color: "#9ca3af" }}>
                  <strong>Email:</strong> shreesoni520@gmail.com
                </p>
              </div>
            </div>

            <div className="col-lg-2 col-md-6">
              <h5
                className="mb-3"
                style={{
                  fontSize: "0.95rem",
                  textTransform: "uppercase",
                  letterSpacing: ".08em",
                  color: "#d1d5db",
                }}
              >
                Navegação
              </h5>
              <ul className="list-unstyled mb-0">
                <li className="mb-2">
                  <Link href="/" className="text-decoration-none footer-link">
                    Início
                  </Link>
                </li>
                <li className="mb-2">
                  <Link
                    href="/information"
                    className="text-decoration-none footer-link"
                  >
                    Informação útil
                  </Link>
                </li>
                <li className="mb-2">
                  <Link
                    href="/noticias"
                    className="text-decoration-none footer-link"
                  >
                    Notícias
                  </Link>
                </li>
                <li className="mb-2">
                  <Link
                    href="/mapa"
                    className="text-decoration-none footer-link"
                  >
                    Mapa
                  </Link>
                </li>
                <li className="mb-2">
                  <Link
                    href="/contact"
                    className="text-decoration-none footer-link"
                  >
                    Contactos
                  </Link>
                </li>
              </ul>
            </div>

            <div className="col-lg-2 col-md-6">
              <h5
                className="mb-3"
                style={{
                  fontSize: "0.95rem",
                  textTransform: "uppercase",
                  letterSpacing: ".08em",
                  color: "#d1d5db",
                }}
              >
                Ocorrências
              </h5>
              <ul className="list-unstyled mb-0">
                <li className="mb-2">
                  <Link
                    href="/ocorrencias"
                    className="text-decoration-none footer-link"
                  >
                    Ocorrências urbanas
                  </Link>
                </li>
                <li className="mb-2">
                  <Link
                    href="/ocorrencias-estrada"
                    className="text-decoration-none footer-link"
                  >
                    Ocorrências estrada
                  </Link>
                </li>
                <li className="mb-2">
                  <Link
                    href="/listar-ocorrencias"
                    className="text-decoration-none footer-link"
                  >
                    Listar ocorrências
                  </Link>
                </li>
                <li className="mb-2">
                  <Link
                    href="/listar-ocorrencias-estrada"
                    className="text-decoration-none footer-link"
                  >
                    Listar ocorrências estrada
                  </Link>
                </li>
              </ul>
            </div>

            <div className="col-lg-2 col-md-6">
              <h5
                className="mb-3"
                style={{
                  fontSize: "0.95rem",
                  textTransform: "uppercase",
                  letterSpacing: ".08em",
                  color: "#d1d5db",
                }}
              >
                Conta
              </h5>
              <ul className="list-unstyled mb-0">
                {!isLoggedIn ? (
                  <>
                    <li className="mb-2">
                      <Link
                        href="/login"
                        className="text-decoration-none footer-link"
                      >
                        Iniciar sessão
                      </Link>
                    </li>
                    <li className="mb-2">
                      <Link
                        href="/signup"
                        className="text-decoration-none footer-link"
                      >
                        Criar conta
                      </Link>
                    </li>
                    <li className="mb-2">
                      <Link
                        href="/forgot-password"
                        className="text-decoration-none footer-link"
                      >
                        Recuperar password
                      </Link>
                    </li>
                  </>
                ) : (
                  <>
                    <li className="mb-2">
                      <Link
                        href="/profile"
                        className="text-decoration-none footer-link"
                      >
                        Perfil
                      </Link>
                    </li>
                    <li className="mb-2">
                      <Link
                        href="/myocorrencias"
                        className="text-decoration-none footer-link"
                      >
                        Minhas ocorrências
                      </Link>
                    </li>
                    <li className="mb-2">
                      <Link
                        href="/mymensagens"
                        className="text-decoration-none footer-link"
                      >
                        Minhas mensagens
                      </Link>
                    </li>
                    <li className="mb-2">
                      <Link
                        href="/api/auth/logout"
                        className="text-decoration-none footer-link"
                      >
                        Terminar sessão
                      </Link>
                    </li>
                  </>
                )}
              </ul>
            </div>

            <div className="col-lg-2 col-md-6">
              <h5
                className="mb-3"
                style={{
                  fontSize: "0.95rem",
                  textTransform: "uppercase",
                  letterSpacing: ".08em",
                  color: "#d1d5db",
                }}
              >
                Mais
              </h5>
              <ul className="list-unstyled mb-0">
                <li className="mb-2">
                  <Link
                    href="/seguranca"
                    className="text-decoration-none footer-link"
                  >
                    Segurança pública
                  </Link>
                </li>
                <li className="mb-2">
                  <Link
                    href="/unsubscribe"
                    className="text-decoration-none footer-link"
                  >
                    Cancelar newsletter
                  </Link>
                </li>
              </ul>
            </div>
          </div>

          <div
            className="d-flex flex-column flex-md-row justify-content-between align-items-center pt-4 pb-4"
            style={{ borderTop: "1px solid rgba(255,255,255,0.08)" }}
          >
            <div
              className="mb-3 mb-md-0"
              style={{ color: "#6b7280", fontSize: "0.88rem" }}
            >
              © {year} Reporta Évora. Todos os direitos reservados.
            </div>
            <div className="d-flex align-items-center gap-2">
              <a
                href="https://github.com/Shreesoni520"
                className="footer-social"
                target="_blank"
                rel="noopener noreferrer"
              >
                <i className="bi bi-github" />
              </a>
              <a
                href="https://www.instagram.com/krishna_soni.52"
                className="footer-social"
                target="_blank"
                rel="noopener noreferrer"
              >
                <i className="bi bi-instagram" />
              </a>
              <a
                href="https://x.com/@Shreessoni520"
                className="footer-social"
                target="_blank"
                rel="noopener noreferrer"
              >
                <i className="bi bi-twitter-x" />
              </a>
              <a
                href="https://www.linkedin.com/in/shree-soni-7751782b1"
                className="footer-social"
                target="_blank"
                rel="noopener noreferrer"
              >
                <i className="bi bi-linkedin" />
              </a>
            </div>
          </div>
        </div>
      </footer>

      <style jsx global>{`
        .footer-link {
          color: #9ca3af;
          font-size: 0.9rem;
          transition: 0.25s ease;
        }
        .footer-link:hover {
          color: #ffffff;
          padding-left: 4px;
        }
        .footer-social {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          width: 38px;
          height: 38px;
          border-radius: 50%;
          background: rgba(15, 23, 42, 0.9);
          color: #e5e7eb;
          border: 1px solid rgba(55, 65, 81, 0.8);
          text-decoration: none;
          transition: 0.25s ease;
        }
        .footer-social:hover {
          color: #ffffff;
          transform: translateY(-2px);
          border-color: rgba(14, 165, 233, 0.6);
        }
        #footer input::placeholder {
          color: #9ca3af;
        }
        #footer input:focus {
          outline: none;
          box-shadow: none;
        }
      `}</style>
    </>
  );
}
