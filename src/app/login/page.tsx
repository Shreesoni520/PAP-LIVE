"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { FormEvent, useState } from "react";

export default function LoginPage() {
  const router = useRouter();
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    setLoading(true);

    const form = new FormData(event.currentTarget);
    const res = await fetch("/api/auth/login", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        username: form.get("username"),
        password: form.get("password"),
      }),
    });

    const data = (await res.json()) as {
      ok?: boolean;
      error?: string;
      require2fa?: boolean;
    };

    setLoading(false);

    if (!res.ok || !data.ok) {
      setError(data.error || "Não foi possível iniciar sessão.");
      return;
    }

    if (data.require2fa) {
      router.push("/verify-2fa");
      return;
    }

    router.push("/");
    router.refresh();
  }

  return (
    <section
      className="section"
      style={{
        minHeight: "100vh",
        paddingTop: "7rem",
        paddingBottom: "3rem",
        background:
          "radial-gradient(circle at top, #111827 0%, #020617 55%, #000 100%)",
      }}
    >
      <div className="container" style={{ maxWidth: 520 }}>
        <div className="text-center mb-4">
          <h2
            className="fw-semibold"
            style={{ color: "#f9fafb", fontSize: "1.7rem" }}
          >
            Iniciar sessão
          </h2>
          <p className="mb-0" style={{ color: "#9ca3af", fontSize: "0.9rem" }}>
            Entre na área de gestão de espaços verdes de Évora.
          </p>
        </div>

        <div
          className="card border-0"
          style={{
            borderRadius: 18,
            boxShadow: "0 20px 55px rgba(15,23,42,0.7)",
            backgroundColor: "#ffffff",
          }}
        >
          <div className="card-body p-4 p-md-5">
            <div className="d-flex align-items-center justify-content-center mb-3">
              <div
                style={{
                  width: 54,
                  height: 54,
                  borderRadius: 999,
                  background: "#0d6efd",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  boxShadow: "0 10px 25px rgba(13,110,253,0.45)",
                }}
              >
                <i className="bi bi-person-fill-lock text-white fs-3" />
              </div>
            </div>

            <p
              className="text-center mb-4"
              style={{ color: "#6b7280", fontSize: "0.86rem" }}
            >
              Preencha os campos de forma clara para evitar erros de
              autenticação.
            </p>

            {error ? (
              <div className="alert alert-danger py-2" role="alert">
                {error}
              </div>
            ) : null}

            <form onSubmit={onSubmit} autoComplete="off">
              <div className="mb-3">
                <label
                  htmlFor="username"
                  className="form-label mb-1"
                  style={{ color: "#111827", fontWeight: 600 }}
                >
                  Utilizador
                </label>
                <input
                  type="text"
                  name="username"
                  id="username"
                  autoComplete="off"
                  className="form-control form-control-lg"
                  style={{ borderColor: "#d1d5db", fontSize: "0.95rem" }}
                  placeholder="Introduza o seu nome de utilizador ou email"
                  required
                />
              </div>

              <div className="mb-3">
                <label
                  htmlFor="password"
                  className="form-label mb-1"
                  style={{ color: "#111827", fontWeight: 600 }}
                >
                  Palavra-passe
                </label>
                <input
                  type="password"
                  name="password"
                  id="password"
                  autoComplete="current-password"
                  className="form-control form-control-lg"
                  style={{ borderColor: "#d1d5db", fontSize: "0.95rem" }}
                  placeholder="Introduza a sua palavra-passe"
                  required
                />
              </div>

              <div className="d-grid mt-4">
                <button
                  type="submit"
                  className="btn btn-primary btn-lg rounded-pill"
                  disabled={loading}
                >
                  {loading ? "A entrar…" : "Entrar"}
                </button>
              </div>
            </form>

            <div className="text-center mt-4" style={{ fontSize: "0.9rem" }}>
              <Link href="/forgot-password">Esqueceu a palavra-passe?</Link>
              <div className="mt-2">
                Ainda não tem conta? <Link href="/signup">Criar conta</Link>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
