const store = new Map();

function clientKey(...parts) {
  return parts.filter(Boolean).join(":");
}

function rateLimit(key, max, windowMs) {
  const now = Date.now();
  let entry = store.get(key);
  if (!entry || now > entry.resetAt) {
    entry = { count: 0, resetAt: now + windowMs };
    store.set(key, entry);
  }
  entry.count += 1;
  if (entry.count > max) {
    return {
      ok: false,
      retryAfterSec: Math.max(1, Math.ceil((entry.resetAt - now) / 1000)),
    };
  }
  return { ok: true };
}

module.exports = { clientKey, rateLimit };
