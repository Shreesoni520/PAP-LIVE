"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { FormEvent, useState } from "react";

export default function SignupPage() {
  const router = useRouter();
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const [loading, setLoading] = useState(false);

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    setSuccess("");
    setLoading(true);

    const form = new FormData(event.currentTarget);
    const res = await fetch("/api/auth/signup", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        nome: form.get("nome"),
        username: form.get("username"),
        email: form.get("email"),
        password: form.get("password"),
        phone: form.get("phone"),
      }),
    });

    const data = (await res.json()) as {
      ok?: boolean;
      error?: string;
      message?: string;
    };

    setLoading(false);

    if (!res.ok || !data.ok) {
      setError(data.error || "Não foi possível criar a conta.");
      return;
    }

    setSuccess(
      data.message ||
        "Conta iniciada. Verifique o email para confirmar o registo.",
    );
    router.refresh();
  }

  return (
    <div className="auth-shell">
      <h1>Criar conta</h1>
      <p>Registe-se para reportar e acompanhar ocorrências.</p>

      {error ? <p className="form-error">{error}</p> : null}
      {success ? <p className="form-success">{success}</p> : null}

      <form className="form-stack" onSubmit={onSubmit}>
        <label>
          Nome completo
          <input name="nome" type="text" required />
        </label>
        <label>
          Utilizador
          <input name="username" type="text" autoComplete="username" required />
        </label>
        <label>
          Email
          <input name="email" type="email" autoComplete="email" required />
        </label>
        <label>
          Telefone
          <input name="phone" type="tel" />
        </label>
        <label>
          Palavra-passe
          <input
            name="password"
            type="password"
            autoComplete="new-password"
            minLength={8}
            required
          />
        </label>
        <button className="btn-primary" type="submit" disabled={loading}>
          {loading ? "A criar…" : "Criar conta"}
        </button>
      </form>

      <p style={{ marginTop: "1.2rem" }}>
        Já tem conta?{" "}
        <Link className="muted-link" href="/login">
          Entrar
        </Link>
      </p>
    </div>
  );
}
