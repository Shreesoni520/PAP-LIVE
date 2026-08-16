"use client";

import Link from "next/link";
import { FormEvent, useState } from "react";

export default function AdminLoginPage() {
  const [error, setError] = useState("");

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(
      "Login admin está a ser ligado. O design do painel original será mantido.",
    );
  }

  return (
    <section
      className="section"
      style={{
        minHeight: "100vh",
        paddingTop: "7rem",
        background:
          "radial-gradient(circle at top, #e5edff 0, transparent 55%), #f3f4f6",
      }}
    >
      <div className="container" style={{ maxWidth: 480 }}>
        <div
          className="card border-0 shadow"
          style={{ borderRadius: 24, overflow: "hidden" }}
        >
          <div className="card-body p-4 p-md-5">
            <h2 className="mb-2" style={{ fontWeight: 700 }}>
              Login - Administração
            </h2>
            <p className="text-muted mb-4">
              Painel de funcionários e administradores Reporta Évora.
            </p>
            {error ? (
              <div className="alert alert-warning py-2">{error}</div>
            ) : null}
            <form onSubmit={onSubmit}>
              <div className="mb-3">
                <label className="form-label fw-semibold">Utilizador</label>
                <input
                  name="username"
                  className="form-control form-control-lg"
                  required
                />
              </div>
              <div className="mb-3">
                <label className="form-label fw-semibold">Palavra-passe</label>
                <input
                  name="password"
                  type="password"
                  className="form-control form-control-lg"
                  required
                />
              </div>
              <button
                type="submit"
                className="btn btn-primary btn-lg w-100 rounded-pill"
              >
                Entrar
              </button>
            </form>
            <div className="mt-3">
              <Link href="/">Voltar ao site público</Link>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
