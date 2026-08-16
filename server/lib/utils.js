const crypto = require("crypto");

function generateNumericCode(len = 6) {
  let out = "";
  for (let i = 0; i < len; i++) {
    out += String(crypto.randomInt(0, 10));
  }
  return out;
}

function generateToken(bytes = 32) {
  return crypto.randomBytes(bytes).toString("hex");
}

function getClientIp(req) {
  const xf = req.headers["x-forwarded-for"];
  if (typeof xf === "string" && xf.length) return xf.split(",")[0].trim();
  return req.socket?.remoteAddress || "unknown";
}

function formatDatePt(value) {
  if (!value) return "";
  const d = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(d.getTime())) return String(value);
  return d.toLocaleDateString("pt-PT", {
    day: "2-digit",
    month: "long",
    year: "numeric",
  });
}

function truncate(str, max = 120) {
  const s = String(str || "");
  if (s.length <= max) return s;
  return s.slice(0, max - 1) + "…";
}

function formatDisplayName(raw) {
  const nome = String(raw || "").trim();
  if (!nome) return "Utilizador";
  const parts = nome.split(/\s+/);
  const first =
    parts[0].charAt(0).toUpperCase() + parts[0].slice(1).toLowerCase();
  if (parts.length > 1) {
    const last = parts[parts.length - 1];
    return `${first} ${last.charAt(0).toUpperCase()}${last.slice(1).toLowerCase()}`;
  }
  return first;
}

function initialsFromName(raw) {
  const nome = formatDisplayName(raw);
  const parts = nome.split(/\s+/);
  if (parts.length >= 2) {
    return `${parts[0][0] || ""}${parts[1][0] || ""}`.toUpperCase();
  }
  return (parts[0]?.slice(0, 2) || "U").toUpperCase();
}

function mysqlDatetime(date = new Date()) {
  return date.toISOString().slice(0, 19).replace("T", " ");
}

module.exports = {
  generateNumericCode,
  generateToken,
  getClientIp,
  formatDatePt,
  truncate,
  formatDisplayName,
  initialsFromName,
  mysqlDatetime,
};
