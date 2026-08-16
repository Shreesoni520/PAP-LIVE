"use client";

import { FormEvent, useState } from "react";
import { useRouter } from "next/navigation";

export default function Verify2faPage() {
  const router = useRouter();
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setLoading(true);
    setError("");

    const form = new FormData(event.currentTarget);
    const res = await fetch("/api/auth/verify-2fa", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ code: form.get("code") }),
    });
    const data = (await res.json()) as { ok?: boolean; error?: string };
    setLoading(false);

    if (!res.ok || !data.ok) {
      setError(data.error || "Código inválido.");
      return;
    }

    router.push("/");
    router.refresh();
  }

  return (
    <div className="auth-shell">
      <h1>Verificação 2FA</h1>
      <p>Introduza o código enviado para o seu email.</p>
      {error ? <p className="form-error">{error}</p> : null}
      <form className="form-stack" onSubmit={onSubmit}>
        <label>
          Código
          <input name="code" inputMode="numeric" maxLength={6} required />
        </label>
        <button className="btn-primary" type="submit" disabled={loading}>
          {loading ? "A verificar…" : "Confirmar"}
        </button>
      </form>
    </div>
  );
}
