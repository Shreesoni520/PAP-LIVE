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
    <div className="auth-shell">
      <h1>Entrar</h1>
      <p>Aceda à sua conta de cidadão Reporta Évora.</p>

      {error ? <p className="form-error">{error}</p> : null}

      <form className="form-stack" onSubmit={onSubmit}>
        <label>
          Utilizador ou email
          <input name="username" type="text" autoComplete="username" required />
        </label>
        <label>
          Palavra-passe
          <input
            name="password"
            type="password"
            autoComplete="current-password"
            required
          />
        </label>
        <button className="btn-primary" type="submit" disabled={loading}>
          {loading ? "A entrar…" : "Entrar"}
        </button>
      </form>

      <p style={{ marginTop: "1.2rem" }}>
        <Link className="muted-link" href="/forgot-password">
          Esqueceu a palavra-passe?
        </Link>
      </p>
      <p>
        Ainda não tem conta?{" "}
        <Link className="muted-link" href="/signup">
          Criar conta
        </Link>
      </p>
    </div>
  );
}
