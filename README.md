# Reporta Évora

Urban occurrence reporting platform for Évora, Portugal. A full-stack web application that lets citizens report public-space and road issues, while municipal staff manage and resolve them through an admin dashboard.

## What is it for?

Évora residents often encounter problems in public spaces and roads — broken pavements, damaged greenery, road hazards — with no simple way to report them to the city. Reporta Évora solves this by giving citizens a direct channel to report issues with photos and location, and giving municipal staff the tools to track, assign, and resolve those reports.

## What does it do?

- Lets citizens report urban and road occurrences with photos and map location
- Shows all occurrences and green spaces on an interactive 2D map
- Provides a personal account area for profile, security, and viewing past reports
- Publishes news articles with comments from logged-in users
- Offers a contact form and newsletter subscription
- Gives staff an admin dashboard to manage occurrences, trees, interventions, news, users, and newsletters
- Sends email and SMS alerts when new occurrences are submitted
- Exports data to PDF and shows real-time notifications in the admin panel

## How does it work?

1. A citizen visits the public website, creates an account, and submits an occurrence (urban or road) with a description, photo, and location.
2. The report is saved to the MySQL database and the administrator is notified by email (and SMS via Twilio, if configured).
3. Staff log into the admin panel, review the occurrence on the dashboard or map, and assign an intervention with a status.
4. Administrators can also manage trees, publish news, moderate comments, respond to contact messages, and send newsletters.
5. Citizens can track their own reports in their personal area and stay informed through news and the newsletter.

## Author

Krishna Soni

---
## Table of Contents

- [About the Project](#about-the-project)
- [Public Website](#public-website)
- [Admin Panel](#admin-panel)
- [Technologies](#technologies)
- [Requirements](#requirements)
- [Installation](#installation)
- [Secrets Configuration](#secrets-configuration)
- [Database](#database)
- [Main URLs](#main-urls)
- [Project Structure](#project-structure)
- [PHP Dependencies](#php-dependencies)
- [Moving to Another PC](#moving-to-another-pc)
- [GitHub](#github)

---

## About the Project

**Reporta Évora** is a full-stack web application developed as a school project (PAP). It connects citizens with municipal services by allowing them to:

- Report problems in public spaces and roads
- View occurrences on an interactive map
- Read news and leave comments
- Contact the organization and subscribe to newsletters
- Create a personal account with profile and security settings

On the administration side, the team can manage trees, intervention statuses, occurrences, news, users, contact messages, newsletters, PDF exports, and real-time notifications.

---

## Public Website

Accessible at `http://localhost/PAP/`

### Features

| Area | Description |
|------|-------------|
| **Home** | Main landing page with highlights and quick access |
| **Useful Information** | Information for citizens about the system and services |
| **2D Map** | Geographic view of occurrences and green spaces |
| **Urban Occurrences** | Report public space issues with location and photo |
| **Road Occurrences** | Report road issues with location and photo |
| **Public Listings** | Browse urban and road occurrences |
| **News** | Read news articles; comments require login and use the account name |
| **Contact** | Message form; logged-in users use their account name and email |
| **Newsletter** | Email subscription with confirmation (login required) |
| **Public Account** | Sign up, login, profile, security, and password recovery |
| **Personal Area** | My occurrences and my contact messages |

### What citizens can do

- Create an account and log in
- Manage profile (name, email, phone, photo, etc.)
- Enable two-factor authentication (2FA) via email
- Recover password
- View their own occurrences and contact messages
- Comment on news using only their registered account name

### Public routes

The public site uses the `evora_p` parameter in `index.php`:

```
index.php?evora_p=inicio
index.php?evora_p=login
index.php?evora_p=signup
index.php?evora_p=profile
index.php?evora_p=noticias
index.php?evora_p=mapa
index.php?evora_p=ocorrencias
index.php?evora_p=ocorrenciasestrada
index.php?evora_p=contact
index.php?evora_p=information
```

---

## Admin Panel

Accessible at `http://localhost/PAP/Admin/`

### User roles

| Role | Access |
|------|--------|
| **Staff (Funcionário)** | Dashboard, maps, occurrences, interventions, profile, and security |
| **Administrator** | Everything staff has + trees, statuses, news, users, contacts, and newsletter |

### Modules

| Module | Description |
|--------|-------------|
| **Dashboard** | Statistics and charts of platform activity |
| **Unified 2D Map** | Admin view of occurrences and trees |
| **Trees** | Add, list, edit, and remove trees |
| **Statuses** | Manage intervention statuses |
| **Urban Occurrences** | Create, list, edit interventions, and remove records |
| **Road Occurrences** | Create, list, edit interventions, and remove records |
| **Interventions** | Assign and track tasks per staff member |
| **News** | Create, edit, list, and remove news articles |
| **Comments** | Moderate comments on news articles |
| **Contact** | Manage messages from the public contact form |
| **Newsletter** | Send newsletters to subscribers |
| **Internal Users** | Manage staff and administrator accounts |
| **Public Users** | Manage citizen accounts |
| **Profile & Security** | Update data, password, and 2FA |
| **PDF Export** | Export selected data to PDF |
| **Notifications** | Alerts for new occurrences and activity |

### Automatic alerts

When an occurrence is submitted, the system can:

- Send an **email** to the administrator
- Send an **SMS** via Twilio (if configured)

### Admin routes

The panel uses the `evora` parameter in `Admin/index.php`:

```
Admin/index.php?evora=inicio
Admin/index.php?evora=mapa2d
Admin/index.php?evora=addocorrencias
Admin/index.php?evora=listocorrencias
Admin/index.php?evora=addnoticias
Admin/index.php?evora=listarnoticias
Admin/index.php?evora=addutilizador
Admin/index.php?evora=profile
Admin/index.php?evora=security
```

---

## Technologies

| Layer | Technology |
|-------|------------|
| Backend | PHP 8.0+ |
| Database | MySQL / MariaDB |
| Local server | XAMPP (Apache + MySQL) |
| Public frontend | HTML, CSS, Bootstrap, JavaScript |
| Admin panel | Mazer Admin Template, Bootstrap, ApexCharts |
| Email | PHP `mail()` |
| SMS | Twilio SDK |
| PDF | Dompdf |
| Dependency management | Composer |

---

## Requirements

- **XAMPP** with PHP **8.0 or higher**
- **MySQL / MariaDB**
- **Composer** (only if you need to reinstall the `vendor` folder)

---

## Installation

### 1. Copy the project

Place the project folder at:

```
C:\xampp\htdocs\PAP
```

### 2. Configure secrets

See [Secrets Configuration](#secrets-configuration).

### 3. Import the database

1. Open **phpMyAdmin**
2. Create or import the **`pap`** database
3. Import the project SQL file (if available)

### 4. Install PHP dependencies

If the `vendor` folder does not exist:

```bash
cd C:\xampp\htdocs\PAP
php composer.phar install
```

### 5. Start the server

1. Open **XAMPP Control Panel**
2. Start **Apache** and **MySQL**
3. Visit:
   - Public: `http://localhost/PAP/`
   - Admin: `http://localhost/PAP/Admin/`

---

## Secrets Configuration

**Never put passwords, Twilio tokens, or credentials inside the project code.**

### Real secrets file (outside the project)

```
C:\xampp\secrets\pap-secrets.php
```

### How to create it

1. Create the folder `C:\xampp\secrets\`
2. Copy `pap-secrets.example.php` to `C:\xampp\secrets\pap-secrets.php`
3. Fill in your real values:

```php
return [
    'DB_HOST' => 'localhost',
    'DB_USER' => 'root',
    'DB_PASS' => '',
    'DB_NAME' => 'pap',
    'ADMIN_EMAIL' => 'your-email@example.com',
    'TWILIO_SID'   => 'YOUR_TWILIO_SID',
    'TWILIO_TOKEN' => 'YOUR_TWILIO_TOKEN',
    'TWILIO_FROM'  => '+1XXXXXXXXXX',
    'TWILIO_TO'    => '+351XXXXXXXXX',
];
```

### Changing credentials

To update Twilio, email, database, etc., edit **only**:

```
C:\xampp\secrets\pap-secrets.php
```

---

## Database

Database name: **`pap`**

### Main tables

| Table | Purpose |
|-------|---------|
| `users` | Admin panel users |
| `users_public` | Public website users |
| `ocorrencias` | Urban occurrences |
| `ocorrencias_estrada` | Road occurrences |
| `arvores` | Trees / green spaces |
| `states` | Intervention statuses |
| `intervencoes` | Assigned interventions |
| `noticias` | News articles |
| `comentarios_noticias` | News comments |
| `contact` | Contact messages |
| `contact_info` | Site contact information |
| `newsletter_subscribers` | Newsletter subscribers |
| `notificacoes` | Admin notifications |
| `atividade` | Admin user activity log |
| `log` | System logs |

---

## Main URLs

| Page | URL |
|------|-----|
| Public site | `http://localhost/PAP/` |
| Public login | `http://localhost/PAP/index.php?evora_p=login` |
| News | `http://localhost/PAP/index.php?evora_p=noticias` |
| Public map | `http://localhost/PAP/index.php?evora_p=mapa` |
| Contact | `http://localhost/PAP/index.php?evora_p=contact` |
| Admin | `http://localhost/PAP/Admin/` |
| Admin login | `http://localhost/PAP/Admin/login.php` |

---

## Project Structure

```
PAP/
├── index.php              # Public site router
├── config.php             # DB connection and secrets loader
├── inicio.php             # Public home page
├── login.php              # Public login
├── signup.php             # Public registration
├── profile.php            # Citizen profile
├── noticias.php           # News and comments
├── contact.php            # Contact form
├── ocorrencias.php        # Report urban occurrence
├── ocorrencias_estrada.php
├── map2d.php              # Public map
├── forms/                 # Newsletter handlers
├── assets/                # Public CSS, JS, and images
├── uploads/               # User uploads (not on GitHub)
├── vendor/                # Composer dependencies (Twilio, Dompdf)
├── pap-secrets.example.php
├── composer.json
├── README.md
└── Admin/
    ├── index.php          # Admin panel router
    ├── config.php         # Loads root config.php
    ├── login.php          # Admin login
    ├── inicio.php         # Dashboard
    ├── menu.php           # Sidebar and notifications
    ├── add_*.php          # Create pages
    ├── listar_*.php       # List pages
    ├── editar_*.php       # Edit pages
    ├── remove_*.php       # Delete pages
    ├── export_pdf.php     # PDF export
    └── assets/            # Admin CSS, JS, and resources
```

### Secrets file (outside project)

```
C:\xampp\secrets\pap-secrets.php
```

---

## PHP Dependencies

| Package | Purpose |
|---------|---------|
| `twilio/sdk` | SMS alerts when occurrences are reported |
| `dompdf/dompdf` | PDF generation in the admin panel |

Install:

```bash
php composer.phar install
```

---

## Moving to Another PC

Copy these 3 items:

1. **Project folder**
   ```
   C:\xampp\htdocs\PAP
   ```

2. **Secrets file**
   ```
   C:\xampp\secrets\pap-secrets.php
   ```

3. **Database** `pap` (export from phpMyAdmin and import on the new PC)

On the new PC:

- Install XAMPP with PHP 8.0+
- If `vendor` is missing, run `php composer.phar install`
- Start Apache and MySQL

---

## GitHub

### What goes on GitHub

- Project source code
- `pap-secrets.example.php` (template without real secrets)
- `README.md`

### What should NOT go on GitHub

- `C:\xampp\secrets\pap-secrets.php` (real secrets)
- `uploads/` folder (user photos)
- `vendor/` folder (reinstall with Composer)

### After cloning the repository

```bash
cd PAP
php composer.phar install
```

Also create `C:\xampp\secrets\pap-secrets.php` with your real values.

---

## Security Notes

- News comments require login and use the account name
- Contact form uses account name/email when the user is logged in
- Secrets (Twilio, DB, email) are stored outside the project folder
- Logout clears all session data correctly

---

## Author

Krishna Soni — developed as a school project (PAP).
