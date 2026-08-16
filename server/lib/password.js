const bcrypt = require("bcryptjs");

async function hashPassword(plain) {
  return bcrypt.hash(plain, 12);
}

async function verifyPassword(plain, hash) {
  if (!plain || !hash) return false;
  try {
    return await bcrypt.compare(plain, hash);
  } catch {
    return false;
  }
}

function validatePasswordStrength(password) {
  if (!password || password.length < 8) {
    return "A palavra-passe deve ter pelo menos 8 caracteres.";
  }
  if (!/[A-Z]/.test(password)) {
    return "A palavra-passe deve incluir pelo menos uma letra maiúscula.";
  }
  if (!/[a-z]/.test(password)) {
    return "A palavra-passe deve incluir pelo menos uma letra minúscula.";
  }
  if (!/[0-9]/.test(password)) {
    return "A palavra-passe deve incluir pelo menos um número.";
  }
  return null;
}

module.exports = { hashPassword, verifyPassword, validatePasswordStrength };
