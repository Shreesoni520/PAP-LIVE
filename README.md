# Reporta Évora (HTML + Node.js)

No PHP. Frontend is HTML/CSS/JS. Backend is Node.js + Express + MySQL.

## Folder layout

```
PAP-LIVE/
├── public/                 ← everything the browser opens
│   ├── index.html          ← home
│   ├── login.html
│   ├── signup.html
│   ├── …                   ← other public pages
│   ├── admin/              ← admin pages + admin assets
│   │   ├── index.html
│   │   ├── login.html
│   │   └── assets/
│   ├── assets/             ← site CSS / JS / images
│   └── uploads/            ← user photos
├── server/                 ← Node backend
│   ├── index.js            ← starts the app
│   ├── lib/                ← db, mail, password, utils
│   └── routes/             ← /api/... endpoints
├── package.json
├── .env                    ← DB settings (not committed)
└── README.md
```

## Run

1. Start **MySQL** in XAMPP.
2. Check `.env` (database `pap`).
3. Then:

```bash
npm install
npm start
```

- Site: http://localhost:3000/
- Admin: http://localhost:3000/admin/login.html

## Author

Krishna Soni
