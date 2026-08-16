const express = require("express");
const { query, queryOne, getPool } = require("../lib/db");
const { verifyPassword, hashPassword, validatePasswordStrength } = require("../lib/password");
const {
  generateNumericCode,
  generateToken,
  getClientIp,
  mysqlDatetime,
} = require("../lib/utils");
const { clientKey, rateLimit } = require("../lib/rate-limit");
const {
  sendTwoFaCodeEmail,
  sendSignupCodeEmail,
  sendPasswordResetEmail,
} = require("../lib/mail");

const router = express.Router();

router.get("/me", (req, res) => {
  if (!req.session.userId) {
    return res.json({ ok: true, loggedIn: false });
  }
  return res.json({
    ok: true,
    loggedIn: true,
    user: {
      id: req.session.userId,
      nome: req.session.userNome || "Utilizador",
      avatar: req.session.userAvatar || null,
    },
  });
});

router.post("/login", async (req, res) => {
  try {
    const ip = getClientIp(req);
    const limited = rateLimit(clientKey("login", ip), 10, 15 * 60 * 1000);
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
      `SELECT id, nome, email, password_hash, twofa_enabled, photo
       FROM users_public
       WHERE username = ? OR email = ?
       LIMIT 1`,
      [username, username],
    );

    if (!user || !(await verifyPassword(password, user.password_hash))) {
      return res.status(401).json({ ok: false, message: "Credenciais inválidas." });
    }

    if (Number(user.twofa_enabled) === 1) {
      const code = generateNumericCode(6);
      const expires = mysqlDatetime(new Date(Date.now() + 10 * 60 * 1000));
      await getPool().execute(
        `INSERT INTO user_twofa_codes_public (user_id, code, expires_at) VALUES (?, ?, ?)`,
        [user.id, code, expires],
      );
      await sendTwoFaCodeEmail(user.email, code, "/verify-2fa.html");
      req.session.pending2fa = {
        userId: user.id,
        nome: user.nome,
        email: user.email,
      };
      return res.json({ ok: true, redirect: "/verify-2fa.html" });
    }

    req.session.userId = user.id;
    req.session.userNome = user.nome;
    req.session.userAvatar = user.photo || null;
    delete req.session.pending2fa;
    return res.json({ ok: true, redirect: "/" });
  } catch (err) {
    console.error("[login]", err);
    return res.status(500).json({
      ok: false,
      message: "Serviço temporariamente indisponível. Verifique a base de dados.",
    });
  }
});

router.post("/verify-2fa", async (req, res) => {
  try {
    const code = String(req.body?.code || "").trim();
    const pending = req.session.pending2fa;
    if (!pending?.userId || !code) {
      return res.status(400).json({
        ok: false,
        message: "Sessão 2FA inválida. Volte a iniciar sessão.",
      });
    }

    const row = await queryOne(
      `SELECT id FROM user_twofa_codes_public
       WHERE user_id = ? AND code = ? AND expires_at >= NOW()
       ORDER BY id DESC LIMIT 1`,
      [pending.userId, code],
    );
    if (!row) {
      return res.status(401).json({ ok: false, message: "Código inválido ou expirado." });
    }

    await getPool().execute(`DELETE FROM user_twofa_codes_public WHERE user_id = ?`, [
      pending.userId,
    ]);

    const user = await queryOne(
      `SELECT id, nome, photo FROM users_public WHERE id = ? LIMIT 1`,
      [pending.userId],
    );
    if (!user) {
      return res.status(404).json({ ok: false, message: "Utilizador não encontrado." });
    }

    req.session.userId = user.id;
    req.session.userNome = user.nome;
    req.session.userAvatar = user.photo || null;
    delete req.session.pending2fa;
    return res.json({ ok: true, redirect: "/" });
  } catch (err) {
    console.error("[verify-2fa]", err);
    return res.status(500).json({ ok: false, message: "Falha na verificação." });
  }
});

router.post("/signup", async (req, res) => {
  try {
    const action = String(req.body?.action || "start");

    if (action === "verify") {
      const email = String(req.body?.email || "").trim().toLowerCase();
      const code = String(req.body?.code || "").trim();
      if (!email || !code) {
        return res.status(400).json({ ok: false, message: "Email e código obrigatórios." });
      }

      const pend = await queryOne(
        `SELECT id, username, password_hash, name, birthday, gender, phone, expires_at, used
         FROM pending_user_verifications
         WHERE email = ? AND code = ?
         ORDER BY id DESC LIMIT 1`,
        [email, code],
      );

      if (!pend || Number(pend.used) === 1) {
        return res.status(400).json({ ok: false, message: "Código inválido." });
      }
      if (new Date(pend.expires_at).getTime() < Date.now()) {
        return res.status(400).json({ ok: false, message: "Código expirado." });
      }

      await getPool().execute(
        `INSERT INTO users_public
           (nome, email, username, password_hash, phone, birthday, gender, criado_em)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())`,
        [
          pend.name,
          email,
          pend.username,
          pend.password_hash,
          pend.phone || null,
          pend.birthday || null,
          pend.gender || null,
        ],
      );
      await getPool().execute(
        `UPDATE pending_user_verifications SET used = 1 WHERE id = ?`,
        [pend.id],
      );

      return res.json({
        ok: true,
        message: "Conta criada com sucesso.",
        redirect: "/login.html?msg=verified",
      });
    }

    const nome = String(req.body?.nome || "").trim();
    const username = String(req.body?.username || "").trim();
    const email = String(req.body?.email || "").trim().toLowerCase();
    const phone = String(req.body?.phone || "").trim();
    const birthday = String(req.body?.birthday || "").trim();
    const gender = String(req.body?.gender || "").trim();
    const password = String(req.body?.password || "");

    if (!nome || !username || !email || !birthday || !gender || password.length < 8) {
      return res.status(400).json({
        ok: false,
        message:
          "Preencha todos os campos e uma palavra-passe com pelo menos 8 caracteres.",
      });
    }

    const strength = validatePasswordStrength(password);
    if (strength) {
      return res.status(400).json({ ok: false, message: strength });
    }

    const existing = await queryOne(
      `SELECT id FROM users_public WHERE username = ? OR email = ? LIMIT 1`,
      [username, email],
    );
    if (existing) {
      return res.status(409).json({ ok: false, message: "Utilizador ou email já registado." });
    }

    const passwordHash = await hashPassword(password);
    const code = generateNumericCode(6);
    const expires = mysqlDatetime(new Date(Date.now() + 10 * 60 * 1000));

    await getPool().execute(`DELETE FROM pending_user_verifications WHERE email = ?`, [
      email,
    ]);
    await getPool().execute(
      `INSERT INTO pending_user_verifications
         (email, username, password_hash, name, birthday, gender, phone, code, expires_at, used, created_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())`,
      [email, username, passwordHash, nome, birthday, gender, phone, code, expires],
    );

    const mail = await sendSignupCodeEmail(email, code);
    if (!mail.ok) {
      return res.status(500).json({
        ok: false,
        message: "Não foi possível enviar o email de verificação.",
      });
    }

    return res.json({
      ok: true,
      step: "verify",
      email,
      message:
        "Enviámos um código de verificação para o seu email. Introduza o código para concluir o registo.",
    });
  } catch (err) {
    console.error("[signup]", err);
    return res.status(500).json({
      ok: false,
      message: "Não foi possível criar a conta. Tente novamente.",
    });
  }
});

router.post("/forgot-password", async (req, res) => {
  const GENERIC =
    "Se o email existir na nossa base de dados, receberá um link de recuperação.";
  try {
    const ip = getClientIp(req);
    const limited = rateLimit(clientKey("forgot", ip), 5, 60 * 60 * 1000);
    if (!limited.ok) {
      return res.status(429).json({
        ok: false,
        message: `Demasiadas tentativas. Aguarde ${limited.retryAfterSec}s.`,
      });
    }

    const email = String(req.body?.email || "").trim().toLowerCase();
    if (!email) {
      return res.status(400).json({ ok: false, message: "Introduza o seu email." });
    }

    const user = await queryOne(
      `SELECT id FROM users_public WHERE email = ? LIMIT 1`,
      [email],
    );
    if (!user) return res.json({ ok: true, message: GENERIC });

    await getPool().execute(
      `DELETE FROM user_password_resets_public WHERE user_id = ?`,
      [user.id],
    );
    const token = generateToken(32);
    const expires = mysqlDatetime(new Date(Date.now() + 15 * 60 * 1000));
    await getPool().execute(
      `INSERT INTO user_password_resets_public (user_id, token, expires_at) VALUES (?, ?, ?)`,
      [user.id, token, expires],
    );
    await sendPasswordResetEmail(email, token);
    return res.json({ ok: true, message: GENERIC });
  } catch (err) {
    console.error("[forgot]", err);
    return res.status(500).json({ ok: false, message: "Erro ao processar pedido." });
  }
});

router.post("/reset-password", async (req, res) => {
  try {
    const token = String(req.body?.token || "").trim();
    const newPassword = String(req.body?.password || "");
    if (!token || !newPassword) {
      return res.status(400).json({ ok: false, message: "Token e palavra-passe obrigatórios." });
    }
    const strength = validatePasswordStrength(newPassword);
    if (strength) return res.status(400).json({ ok: false, message: strength });

    const row = await queryOne(
      `SELECT user_id FROM user_password_resets_public
       WHERE token = ? AND expires_at >= NOW() LIMIT 1`,
      [token],
    );
    if (!row) {
      return res.status(400).json({ ok: false, message: "Link inválido ou expirado." });
    }

    const hash = await hashPassword(newPassword);
    await getPool().execute(`UPDATE users_public SET password_hash = ? WHERE id = ?`, [
      hash,
      row.user_id,
    ]);
    await getPool().execute(`DELETE FROM user_password_resets_public WHERE user_id = ?`, [
      row.user_id,
    ]);
    return res.json({ ok: true, message: "Palavra-passe atualizada.", redirect: "/login.html" });
  } catch (err) {
    console.error("[reset]", err);
    return res.status(500).json({ ok: false, message: "Erro ao redefinir palavra-passe." });
  }
});

router.post("/logout", (req, res) => {
  req.session.destroy(() => {
    res.json({ ok: true, redirect: "/" });
  });
});

router.get("/logout", (req, res) => {
  req.session.destroy(() => {
    res.redirect("/");
  });
});

module.exports = router;
