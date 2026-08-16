require("dotenv").config();

const path = require("path");
const express = require("express");
const session = require("express-session");
const cookieParser = require("cookie-parser");

const authRoutes = require("./routes/auth");
const publicApi = require("./routes/public-api");
const adminApi = require("./routes/admin-api");

const app = express();
const PORT = Number(process.env.PORT || 3000);
const root = path.join(__dirname, "..");
const pub = path.join(root, "public");

app.use(cookieParser());
app.use(express.json({ limit: "2mb" }));
app.use(express.urlencoded({ extended: true }));

app.use(
  session({
    name: "reporta.sid",
    secret: process.env.SESSION_SECRET || "dev-secret-change-me-please-32chars",
    resave: false,
    saveUninitialized: false,
    cookie: {
      httpOnly: true,
      sameSite: "lax",
      secure: false,
      maxAge: 1000 * 60 * 60 * 24 * 7,
    },
  }),
);

// Static files (everything the browser needs lives under /public)
app.use("/assets", express.static(path.join(pub, "assets")));
app.use("/Admin/assets", express.static(path.join(pub, "admin", "assets")));
app.use("/admin/assets", express.static(path.join(pub, "admin", "assets")));
// Fallback to restored Admin/assets folder if present
app.use("/Admin/assets", express.static(path.join(root, "Admin", "assets")));
app.use("/uploads", express.static(path.join(pub, "uploads")));

app.use("/api/auth", authRoutes);
app.use("/api", publicApi);
app.use("/api/admin", adminApi);

app.get("/", (_req, res) => {
  res.sendFile(path.join(pub, "index.html"));
});

app.get("/admin", (_req, res) => {
  res.redirect("/admin/");
});

app.get("/admin/", (req, res) => {
  if (!req.session.adminUserId) {
    return res.redirect("/admin/login.html");
  }
  return res.sendFile(path.join(pub, "admin", "index.html"));
});

app.use("/admin", express.static(path.join(pub, "admin")));
app.use(express.static(pub));

app.use((err, _req, res, _next) => {
  console.error("[express]", err);
  res.status(500).json({ ok: false, message: "Erro interno." });
});

app.listen(PORT, () => {
  console.log(`Reporta Évora (HTML + Node) em http://localhost:${PORT}`);
});
