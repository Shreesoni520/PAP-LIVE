# Reporta Évora (Next.js)

Urban occurrence reporting platform for Évora — migrated from PHP to **Next.js + TypeScript + React + Tailwind**.

## Stack

- Next.js 15 (App Router)
- TypeScript
- React
- Tailwind CSS
- MySQL (`pap` database)
- iron-session, bcryptjs, nodemailer

## Local development

1. Install [Node.js 20+](https://nodejs.org/)
2. Keep MySQL running (XAMPP MySQL is fine)
3. Copy env file:

```bash
cp .env.example .env.local
```

4. Fill DB credentials and a long `SESSION_SECRET` (32+ chars)
5. Install and run:

```bash
npm install
npm run dev
```

Open [http://localhost:3000](http://localhost:3000)

## Vercel deploy

This repo is connected to GitHub → Vercel. Every push to `main` rebuilds the site.

### Required Vercel environment variables

Set these in **Vercel → Project → Settings → Environment Variables**:

- `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME`
- `APP_URL` (your Vercel URL, e.g. `https://your-app.vercel.app`)
- `SESSION_SECRET` (long random string)
- `ADMIN_EMAIL`
- Optional: `SMTP_*`, `TWILIO_*`

**Important:** Vercel cannot reach `localhost` MySQL on your PC. Use a cloud MySQL (PlanetScale, Railway, Aiven, etc.) or a tunnel for production.

## Legacy PHP

Old PHP files remain in the repo for reference during migration and will be removed once feature parity is complete. The live app is Next.js only.
