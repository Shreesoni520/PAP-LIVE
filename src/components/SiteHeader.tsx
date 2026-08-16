"use client";

import Link from "next/link";
import { formatDisplayName, initialsFromName } from "@/lib/format";

type NavUser = {
  id: number;
  nome: string;
  avatar?: string | null;
} | null;

export function SiteHeader({ user }: { user: NavUser }) {
  return (
    <div className="container-fluid container-xl position-relative d-flex align-items-center">
      <Link href="/" className="logo d-flex align-items-center me-auto">
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img src="/admin/assets/images/logo/logo.png" alt="Logo" />
      </Link>

      <nav id="navmenu" className="navmenu">
        <ul>
          <li>
            <Link href="/">Início</Link>
          </li>
          <li>
            <Link href="/information">Informação</Link>
          </li>
          <li>
            <Link href="/noticias">Notícias</Link>
          </li>
          <li>
            <Link href="/mapa">Mapa</Link>
          </li>

          <li className="dropdown">
            <a href="#">
              <span>Ocorrências</span>{" "}
              <i className="bi bi-chevron-down toggle-dropdown" />
            </a>
            <ul>
              <li className="dropdown">
                <Link href="/ocorrencias">
                  <span>Registar Ocorrências</span>{" "}
                  <i className="bi bi-chevron-down toggle-dropdown" />
                </Link>
                <ul>
                  <li>
                    <Link href="/ocorrencias">Espaço verde</Link>
                  </li>
                  <li>
                    <Link href="/ocorrencias-estrada">Estrada</Link>
                  </li>
                </ul>
              </li>
              <li className="dropdown">
                <Link href="/listar-ocorrencias">
                  <span>Lista ocorrências</span>{" "}
                  <i className="bi bi-chevron-down toggle-dropdown" />
                </Link>
                <ul>
                  <li>
                    <Link href="/listar-ocorrencias">Espaço verde</Link>
                  </li>
                  <li>
                    <Link href="/listar-ocorrencias-estrada">Estrada</Link>
                  </li>
                </ul>
              </li>
            </ul>
          </li>

          <li>
            <Link href="/contact">Contacto</Link>
          </li>

          {user ? (
            <li className="dropdown ms-2">
              <a
                href="#"
                className="d-flex align-items-center nav-avatar-toggle"
              >
                <div className="nav-avatar">
                  {user.avatar ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img
                      src={user.avatar}
                      alt="Avatar"
                      className="nav-avatar-img"
                    />
                  ) : (
                    <span className="nav-avatar-initials">
                      {initialsFromName(user.nome)}
                    </span>
                  )}
                </div>
                <span className="nav-avatar-name ms-2 d-none d-lg-inline">
                  {formatDisplayName(user.nome)}
                </span>
                <i className="bi bi-chevron-down toggle-dropdown ms-1" />
              </a>
              <ul>
                <li>
                  <hr className="dropdown-divider" />
                </li>
                <li>
                  <Link href="/profile">Perfil</Link>
                </li>
                <li>
                  <Link href="/myocorrencias">Minhas Ocorrências</Link>
                </li>
                <li>
                  <Link href="/mymensagens">Minhas Mensagens</Link>
                </li>
                <li>
                  <Link href="/seguranca">Segurança</Link>
                </li>
                <li>
                  <Link href="/api/auth/logout">Terminar sessão</Link>
                </li>
              </ul>
            </li>
          ) : (
            <li className="ms-2">
              <Link
                href="/login"
                className="btn-getstarted btn-sm text-white px-3 rounded-pill"
              >
                Iniciar sessão
              </Link>
            </li>
          )}
        </ul>
        <i className="mobile-nav-toggle d-xl-none bi bi-list" />
      </nav>

      <style jsx global>{`
        .nav-avatar-toggle {
          text-decoration: none;
          color: #f9fafb;
        }
        .nav-avatar {
          width: 34px;
          height: 34px;
          border-radius: 999px;
          position: relative;
          overflow: hidden;
          box-shadow: 0 4px 10px rgba(15, 23, 42, 0.45);
          flex-shrink: 0;
          background: linear-gradient(135deg, #2563eb, #1d4ed8);
          display: flex;
          align-items: center;
          justify-content: center;
        }
        .nav-avatar-img {
          width: 100%;
          height: 100%;
          object-fit: cover;
          display: block;
        }
        .nav-avatar-initials {
          font-weight: 600;
          font-size: 0.85rem;
          letter-spacing: 0.04em;
          color: #e5e7eb;
          position: relative;
          z-index: 1;
        }
        .nav-avatar-name {
          font-size: 0.8rem;
          color: #e5e7eb;
          max-width: 120px;
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
          opacity: 0.9;
        }
      `}</style>
    </div>
  );
}
