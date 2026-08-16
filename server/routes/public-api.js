const express = require("express");
const { query, queryOne, getPool } = require("../lib/db");
const { formatDatePt, truncate, getClientIp, generateToken, mysqlDatetime } = require("../lib/utils");
const { clientKey, rateLimit } = require("../lib/rate-limit");
const {
  sendNewsletterConfirmEmail,
  sendOcorrenciaAdminEmail,
} = require("../lib/mail");
const multer = require("multer");
const path = require("path");
const fs = require("fs");

const router = express.Router();

const uploadDir = path.join(__dirname, "..", "..", "public", "uploads");
const storage = multer.diskStorage({
  destination(req, file, cb) {
    const folder = req.uploadFolder || "ocorrencias";
    const dir = path.join(uploadDir, folder);
    fs.mkdirSync(dir, { recursive: true });
    cb(null, dir);
  },
  filename(req, file, cb) {
    const ext = path.extname(file.originalname).toLowerCase();
    const userId = req.session?.userId || 0;
    cb(null, `${req.uploadPrefix || "occ"}_${Date.now()}_${userId}${ext}`);
  },
});

function fileFilter(req, file, cb) {
  if (!["image/jpeg", "image/png"].includes(file.mimetype)) {
    return cb(new Error("Apenas JPG e PNG."));
  }
  cb(null, true);
}

const upload = multer({
  storage,
  fileFilter,
  limits: { fileSize: 5 * 1024 * 1024 },
});

router.get("/noticias", async (req, res) => {
  const limit = Math.min(Number(req.query.limit) || 50, 100);
  const id = req.query.id ? Number(req.query.id) : null;
  try {
    if (id) {
      const noticia = await queryOne(
        `SELECT id, titulo, resumo, conteudo, imagem_lista, imagem_detalhe, data_publicacao
         FROM noticias WHERE id = ? LIMIT 1`,
        [id],
      );
      if (!noticia) {
        return res.status(404).json({ ok: false, message: "Notícia não encontrada." });
      }
      const comments = await query(
        `SELECT id, nome, texto, criado_em FROM comentarios_noticias
         WHERE noticia_id = ? ORDER BY criado_em DESC`,
        [id],
      );
      return res.json({
        ok: true,
        noticia: {
          ...noticia,
          data_formatada: formatDatePt(noticia.data_publicacao),
        },
        comments,
      });
    }

    const rows = await query(
      `SELECT id, titulo, resumo, imagem_lista, data_publicacao
       FROM noticias
       ORDER BY data_publicacao DESC, id DESC
       LIMIT ${limit}`,
    );
    return res.json({
      ok: true,
      noticias: rows.map((n) => ({
        ...n,
        resumo_curto: truncate(n.resumo || "", 140),
        data_formatada: formatDatePt(n.data_publicacao),
      })),
    });
  } catch (err) {
    console.error("[noticias]", err);
    if (id) {
      return res.status(503).json({
        ok: false,
        message: "Serviço temporariamente indisponível. Tente novamente mais tarde.",
      });
    }
    return res.json({ ok: true, noticias: [] });
  }
});

router.post("/noticias/comments", async (req, res) => {
  try {
    if (!req.session.userId) {
      return res.status(401).json({ ok: false, message: "Precisa de iniciar sessão." });
    }
    const noticiaId = Number(req.body?.noticia_id);
    const texto = String(req.body?.texto || "").trim();
    if (!noticiaId || !texto) {
      return res.status(400).json({ ok: false, message: "Comentário inválido." });
    }
    const user = await queryOne(`SELECT nome FROM users_public WHERE id = ?`, [
      req.session.userId,
    ]);
    if (!user) {
      return res.status(401).json({ ok: false, message: "Utilizador inválido." });
    }
    await getPool().execute(
      `INSERT INTO comentarios_noticias (noticia_id, nome, texto, user_id, criado_em)
       VALUES (?, ?, ?, ?, NOW())`,
      [noticiaId, user.nome, texto, req.session.userId],
    );
    return res.json({ ok: true, message: "Comentário publicado." });
  } catch (err) {
    console.error("[comments]", err);
    return res.status(500).json({ ok: false, message: "Erro ao comentar." });
  }
});

router.get("/map/arvores", async (_req, res) => {
  try {
    const rows = await query(
      `SELECT id, nome, especie, latitude, longitude, estado FROM arvores
       WHERE latitude IS NOT NULL AND longitude IS NOT NULL`,
    );
    res.json({ ok: true, data: rows });
  } catch (err) {
    console.error(err);
    res.status(500).json({ ok: false, data: [] });
  }
});

router.get("/map/ocorrencias", async (_req, res) => {
  try {
    const rows = await query(
      `SELECT id, descricao, latitude, longitude, place_name, data_ocorrencia, imagem
       FROM ocorrencias
       WHERE latitude IS NOT NULL AND longitude IS NOT NULL`,
    );
    res.json({ ok: true, data: rows });
  } catch (err) {
    console.error(err);
    res.status(500).json({ ok: false, data: [] });
  }
});

router.get("/map/ocorrencias-estrada", async (_req, res) => {
  try {
    const rows = await query(
      `SELECT id, descricao, latitude, longitude, place_name, data_ocorrencia, imagem
       FROM ocorrencias_estrada
       WHERE latitude IS NOT NULL AND longitude IS NOT NULL`,
    );
    res.json({ ok: true, data: rows });
  } catch (err) {
    console.error(err);
    res.status(500).json({ ok: false, data: [] });
  }
});

router.get("/ocorrencias", async (req, res) => {
  try {
    const tipo = req.query.tipo === "estrada" ? "estrada" : "verde";
    const table = tipo === "estrada" ? "ocorrencias_estrada" : "ocorrencias";
    const mine = req.query.mine === "1";
    if (mine && !req.session.userId) {
      return res.status(401).json({ ok: false, message: "Não autenticado." });
    }
    let sql = `SELECT * FROM ${table}`;
    const params = [];
    if (mine) {
      sql += ` WHERE user_id = ?`;
      params.push(req.session.userId);
    }
    sql += ` ORDER BY id DESC LIMIT 200`;
    const rows = await query(sql, params);
    res.json({ ok: true, data: rows });
  } catch (err) {
    console.error(err);
    res.status(500).json({ ok: false, message: "Erro ao listar." });
  }
});

async function handleOcorrenciaCreate(req, res, kind) {
  try {
    const descricao = String(req.body?.descricao || "").trim();
    const place_name = String(req.body?.place_name || "").trim();
    const latitude = String(req.body?.latitude || "").trim();
    const longitude = String(req.body?.longitude || "").trim();
    const data_ocorrencia = String(req.body?.data_ocorrencia || "").trim();
    const tipo_intervencao = String(req.body?.tipo_intervencao || "").trim();
    const tarefa = String(req.body?.tarefa || "").trim();

    if (!descricao || !latitude || !longitude) {
      return res.status(400).json({
        ok: false,
        message: "Descrição e localização são obrigatórias.",
      });
    }

    const userId = req.session.userId || 0;
    const imagem = req.file ? `/uploads/${kind === "estrada" ? "ocorrencias_estrada" : "ocorrencias"}/${req.file.filename}` : null;
    const table = kind === "estrada" ? "ocorrencias_estrada" : "ocorrencias";

    await getPool().execute(
      `INSERT INTO ${table}
         (descricao, place_name, latitude, longitude, data_ocorrencia, tipo_intervencao, tarefa, imagem, user_id, criado_em)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())`,
      [
        descricao,
        place_name,
        latitude,
        longitude,
        data_ocorrencia || null,
        tipo_intervencao || null,
        tarefa || null,
        imagem,
        userId || null,
      ],
    );

    await sendOcorrenciaAdminEmail({
      kind,
      descricao,
      placeName: place_name,
      latitude,
      longitude,
    });

    return res.json({ ok: true, message: "Ocorrência registada com sucesso!" });
  } catch (err) {
    console.error("[ocorrencias]", err);
    return res.status(500).json({ ok: false, message: "Erro ao registar ocorrência." });
  }
}

router.post("/ocorrencias", (req, res, next) => {
  req.uploadFolder = "ocorrencias";
  req.uploadPrefix = "verde";
  upload.single("imagem")(req, res, (err) => {
    if (err) {
      return res.status(400).json({ ok: false, message: err.message });
    }
    return handleOcorrenciaCreate(req, res, "verde");
  });
});

router.post("/ocorrencias-estrada", (req, res, next) => {
  req.uploadFolder = "ocorrencias_estrada";
  req.uploadPrefix = "estrada";
  upload.single("imagem")(req, res, (err) => {
    if (err) {
      return res.status(400).json({ ok: false, message: err.message });
    }
    return handleOcorrenciaCreate(req, res, "estrada");
  });
});

router.get("/contact-info", async (_req, res) => {
  try {
    const info = await queryOne(`SELECT * FROM contact_info ORDER BY id ASC LIMIT 1`);
    res.json({ ok: true, info });
  } catch {
    res.json({ ok: true, info: null });
  }
});

router.post("/contact", async (req, res) => {
  try {
    const ip = getClientIp(req);
    const limited = rateLimit(clientKey("contact", ip), 8, 60 * 60 * 1000);
    if (!limited.ok) {
      return res.status(429).json({
        ok: false,
        message: `Demasiadas tentativas. Aguarde ${limited.retryAfterSec}s.`,
      });
    }

    let nome = String(req.body?.nome || "").trim();
    let email = String(req.body?.email || "").trim();
    const assunto = String(req.body?.assunto || "").trim();
    const mensagem = String(req.body?.mensagem || "").trim();
    let userId = null;

    if (req.session.userId) {
      const user = await queryOne(
        `SELECT id, nome, email FROM users_public WHERE id = ?`,
        [req.session.userId],
      );
      if (user) {
        nome = user.nome;
        email = user.email;
        userId = user.id;
      }
    }

    if (!nome || !email || !mensagem) {
      return res.status(400).json({ ok: false, message: "Preencha os campos obrigatórios." });
    }

    await getPool().execute(
      `INSERT INTO contact (nome, email, assunto, mensagem, user_id, criado_em)
       VALUES (?, ?, ?, ?, ?, NOW())`,
      [nome, email, assunto, mensagem, userId],
    );
    return res.json({ ok: true, message: "Mensagem enviada com sucesso." });
  } catch (err) {
    console.error("[contact]", err);
    return res.status(500).json({ ok: false, message: "Erro ao enviar mensagem." });
  }
});

router.get("/mymensagens", async (req, res) => {
  if (!req.session.userId) {
    return res.status(401).json({ ok: false, message: "Não autenticado." });
  }
  try {
    const rows = await query(
      `SELECT id, assunto, mensagem, criado_em FROM contact WHERE user_id = ? ORDER BY id DESC`,
      [req.session.userId],
    );
    res.json({ ok: true, data: rows });
  } catch (err) {
    res.status(500).json({ ok: false, message: "Erro." });
  }
});

router.post("/newsletter", async (req, res) => {
  try {
    if (!req.session.userId) {
      return res.status(401).json({
        ok: false,
        message: "Precisas de iniciar sessão para subscrever a newsletter.",
        redirect: "/login.html",
      });
    }
    const email = String(req.body?.email || "").trim().toLowerCase();
    if (!email) {
      return res.status(400).json({ ok: false, message: "Introduza um email." });
    }
    const token = generateToken(24);
    await getPool().execute(
      `INSERT INTO newsletter_subscribers (email, token, confirmed, created_at)
       VALUES (?, ?, 0, NOW())
       ON DUPLICATE KEY UPDATE token = VALUES(token), confirmed = 0`,
      [email, token],
    );
    await sendNewsletterConfirmEmail(email, token);
    return res.json({
      ok: true,
      message: "Enviámos um email de confirmação. Verifique a sua caixa de entrada.",
    });
  } catch (err) {
    console.error("[newsletter]", err);
    return res.status(500).json({ ok: false, message: "Erro na newsletter." });
  }
});

router.get("/newsletter/confirm", async (req, res) => {
  try {
    const token = String(req.query.token || "");
    if (!token) return res.status(400).json({ ok: false, message: "Token em falta." });
    const row = await queryOne(
      `SELECT id FROM newsletter_subscribers WHERE token = ? LIMIT 1`,
      [token],
    );
    if (!row) return res.status(404).json({ ok: false, message: "Token inválido." });
    await getPool().execute(
      `UPDATE newsletter_subscribers SET confirmed = 1 WHERE id = ?`,
      [row.id],
    );
    return res.json({ ok: true, message: "Newsletter confirmada." });
  } catch (err) {
    return res.status(500).json({ ok: false, message: "Erro." });
  }
});

router.get("/geo/nominatim", async (req, res) => {
  try {
    const q = String(req.query.q || "").trim();
    if (!q) return res.json([]);
    const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q)}&limit=5&countrycodes=pt`;
    const r = await fetch(url, {
      headers: { "User-Agent": "ReportaEvora/1.0" },
    });
    const data = await r.json();
    res.json(data);
  } catch {
    res.json([]);
  }
});

router.get("/geo/reverse", async (req, res) => {
  try {
    const lat = req.query.lat;
    const lon = req.query.lon;
    if (!lat || !lon) return res.json({ display_name: "" });
    const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}`;
    const r = await fetch(url, {
      headers: { "User-Agent": "ReportaEvora/1.0" },
    });
    const data = await r.json();
    res.json(data);
  } catch {
    res.json({ display_name: "" });
  }
});

module.exports = router;
