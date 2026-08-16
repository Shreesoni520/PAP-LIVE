const express = require("express");
const { query, queryOne, getPool } = require("../lib/db");
const { verifyPassword } = require("../lib/password");
const { generateNumericCode, getClientIp, mysqlDatetime } = require("../lib/utils");
const { clientKey, rateLimit } = require("../lib/rate-limit");
const { sendTwoFaCodeEmail } = require("../lib/mail");

const router = express.Router();

function requireAdmin(req, res, next) {
  if (!req.session.adminUserId) {
    return res.status(401).json({ ok: false, message: "Não autenticado." });
  }
  next();
}

function requireIsAdmin(req, res, next) {
  if (!req.session.adminUserId || !req.session.adminIsAdmin) {
    return res.status(403).json({ ok: false, message: "Sem permissão." });
  }
  next();
}

router.get("/me", (req, res) => {
  if (!req.session.adminUserId) {
    return res.json({ ok: true, loggedIn: false });
  }
  return res.json({
    ok: true,
    loggedIn: true,
    user: {
      id: req.session.adminUserId,
      username: req.session.adminUsername,
      isAdmin: Boolean(req.session.adminIsAdmin),
    },
  });
});

router.post("/login", async (req, res) => {
  try {
    const ip = getClientIp(req);
    const limited = rateLimit(clientKey("admin-login", ip), 8, 15 * 60 * 1000);
    if (!limited.ok) {
      return res.status(429).json({
        ok: false,
        message: `Demasiadas tentativas. Aguarde ${limited.retryAfterSec}s.`,
      });
    }

    const username = String(req.body?.username || "").trim();
    const password = String(req.body?.password || "");
    if (!username || !password) {
      return res.status(400).json({ ok: false, message: "Preencha todos os campos." });
    }

    const user = await queryOne(
      `SELECT id, username, email, password, is_admin, twofa_enabled, is_active, name
       FROM users WHERE username = ? LIMIT 1`,
      [username],
    );

    if (!user || Number(user.is_active) === 0) {
      return res.status(401).json({
        ok: false,
        message: "Nome de utilizador ou palavra‑passe inválidos.",
      });
    }

    const ok = await verifyPassword(password, user.password);
    if (!ok) {
      return res.status(401).json({
        ok: false,
        message: "Nome de utilizador ou palavra‑passe inválidos.",
      });
    }

    const isAdmin = Number(user.is_admin) === 1;

    if (Number(user.twofa_enabled) === 1) {
      const code = generateNumericCode(6);
      const expires = mysqlDatetime(new Date(Date.now() + 10 * 60 * 1000));
      await getPool().execute(
        `INSERT INTO user_twofa_codes (user_id, code, expires_at) VALUES (?, ?, ?)`,
        [user.id, code, expires],
      );
      await sendTwoFaCodeEmail(user.email, code, "/admin/login.html?step=2");
      req.session.adminPending2fa = {
        userId: user.id,
        username: user.username,
        isAdmin,
      };
      return res.json({ ok: true, require2fa: true });
    }

    req.session.adminUserId = user.id;
    req.session.adminUsername = user.username;
    req.session.adminIsAdmin = isAdmin;
    delete req.session.adminPending2fa;

    try {
      await getPool().execute(
        `INSERT INTO atividade (user_id, acao, detalhe) VALUES (?, ?, ?)`,
        [user.id, "login", "Login admin"],
      );
    } catch {
      /* optional table */
    }

    return res.json({ ok: true, redirect: "/admin/" });
  } catch (err) {
    console.error("[admin-login]", err);
    const msg =
      err && (err.code === "ECONNREFUSED" || String(err.message || "").includes("ECONNREFUSED"))
        ? "Serviço temporariamente indisponível. Tente novamente mais tarde."
        : "Não foi possível iniciar sessão. Tente novamente.";
    return res.status(500).json({ ok: false, message: msg });
  }
});

router.post("/verify-2fa", async (req, res) => {
  try {
    const code = String(req.body?.code || "").trim();
    const pending = req.session.adminPending2fa;
    if (!pending?.userId || !code) {
      return res.status(400).json({ ok: false, message: "Sessão 2FA inválida." });
    }
    const row = await queryOne(
      `SELECT id FROM user_twofa_codes
       WHERE user_id = ? AND code = ? AND expires_at >= NOW()
       ORDER BY id DESC LIMIT 1`,
      [pending.userId, code],
    );
    if (!row) {
      return res.status(401).json({ ok: false, message: "Código inválido ou expirado." });
    }
    await getPool().execute(`DELETE FROM user_twofa_codes WHERE user_id = ?`, [
      pending.userId,
    ]);
    req.session.adminUserId = pending.userId;
    req.session.adminUsername = pending.username;
    req.session.adminIsAdmin = pending.isAdmin;
    delete req.session.adminPending2fa;
    return res.json({ ok: true, redirect: "/admin/" });
  } catch (err) {
    console.error(err);
    return res.status(500).json({ ok: false, message: "Falha 2FA." });
  }
});

router.post("/logout", (req, res) => {
  req.session.adminUserId = undefined;
  req.session.adminUsername = undefined;
  req.session.adminIsAdmin = undefined;
  req.session.adminPending2fa = undefined;
  res.json({ ok: true, redirect: "/admin/login.html" });
});

router.get("/dashboard", requireAdmin, async (req, res) => {
  try {
    const count = async (sql) => {
      const row = await queryOne(sql);
      return Number(row?.c || 0);
    };
    const stats = {
      ocorrencias: await count(`SELECT COUNT(*) AS c FROM ocorrencias`),
      ocorrenciasEstrada: await count(`SELECT COUNT(*) AS c FROM ocorrencias_estrada`),
      arvores: await count(`SELECT COUNT(*) AS c FROM arvores`),
      noticias: await count(`SELECT COUNT(*) AS c FROM noticias`),
      usersPublic: await count(`SELECT COUNT(*) AS c FROM users_public`),
      contact: await count(`SELECT COUNT(*) AS c FROM contact`),
    };
    res.json({
      ok: true,
      stats,
      user: {
        username: req.session.adminUsername,
        isAdmin: Boolean(req.session.adminIsAdmin),
      },
    });
  } catch (err) {
    console.error(err);
    res.status(500).json({ ok: false, message: "Erro no dashboard." });
  }
});

router.get("/list/:entity", requireAdmin, async (req, res) => {
  const map = {
    arvores: "SELECT * FROM arvores ORDER BY id DESC LIMIT 500",
    ocorrencias: "SELECT * FROM ocorrencias ORDER BY id DESC LIMIT 500",
    "ocorrencias-estrada": "SELECT * FROM ocorrencias_estrada ORDER BY id DESC LIMIT 500",
    noticias: "SELECT * FROM noticias ORDER BY id DESC LIMIT 500",
    contact: "SELECT * FROM contact ORDER BY id DESC LIMIT 500",
    users: "SELECT id, username, name, email, is_admin, is_active FROM users ORDER BY id DESC",
    "users-public":
      "SELECT id, nome, email, username, phone, criado_em FROM users_public ORDER BY id DESC LIMIT 500",
    states: "SELECT * FROM states ORDER BY id DESC",
  };
  const sql = map[req.params.entity];
  if (!sql) return res.status(404).json({ ok: false, message: "Entidade desconhecida." });
  if (
    ["users", "users-public", "noticias", "states", "contact"].includes(req.params.entity) &&
    !req.session.adminIsAdmin
  ) {
    return res.status(403).json({ ok: false, message: "Sem permissão." });
  }
  try {
    const data = await query(sql);
    res.json({ ok: true, data });
  } catch (err) {
    console.error(err);
    res.status(500).json({ ok: false, message: "Erro ao listar." });
  }
});

router.delete("/item/:entity/:id", requireAdmin, requireIsAdmin, async (req, res) => {
  const tables = {
    arvores: "arvores",
    ocorrencias: "ocorrencias",
    "ocorrencias-estrada": "ocorrencias_estrada",
    noticias: "noticias",
    contact: "contact",
    users: "users",
    "users-public": "users_public",
    states: "states",
  };
  const table = tables[req.params.entity];
  if (!table) return res.status(404).json({ ok: false, message: "Entidade desconhecida." });
  try {
    await getPool().execute(`DELETE FROM ${table} WHERE id = ?`, [Number(req.params.id)]);
    res.json({ ok: true });
  } catch (err) {
    console.error(err);
    res.status(500).json({ ok: false, message: "Erro ao apagar." });
  }
});

module.exports = router;
