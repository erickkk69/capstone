# Capstone PHP Backend Integration (XAMPP)

This project is wired to a PHP backend using XAMPP (Apache + MySQL/MariaDB).

## Quick start

1) Start XAMPP
- Open the XAMPP Control Panel and Start Apache and MySQL.

2) Initialize the database
- Open your browser and visit:
  http://localhost/capstone/api/setup.php
- You should see `{ ok: true, message: "Setup complete..." }`.
- This creates the database `capstone`, the table `users`, and seeds the default ABC Secretary:
  - Email: `abcsecretary@mabini.gov`
  - Password: `abcpassword`

3) Open the app
- Visit: http://localhost/capstone/
- Log in using the default credentials above.
- If login works, you should be redirected to the ABC portal.

## How it works

- Frontend pages call PHP API endpoints via `fetch()` at `api/*.php`.
- PHP uses PDO to talk to MySQL and PHP sessions to keep you logged in.

### Endpoints

- POST `api/login.php` → `{ email, password }` → `{ ok, user }`
- POST `api/logout.php` → ends the session → `{ ok }`
- GET `api/me.php` → returns current session user → `{ ok, user|null }`
- GET `api/users.php` (ABC only) → list users → `{ ok, users }`
- POST `api/users.php` (ABC only) → create user `{ email, password, barangay, role }` → `{ ok }`
- DELETE `api/users.php?email=...` (ABC only) → deletes user by email → `{ ok }`

### Files

- `api/config.php` – PDO connection + session + auth helpers
- `api/utils.php` – JSON + request helpers
- `api/setup.php` – One-time DB/table creation + seed default ABC Secretary
- `api/login.php`, `api/logout.php`, `api/me.php` – Auth endpoints
- `api/users.php` – User CRUD for ABC Secretary
- `index.html` – Login page wired to POST `api/login.php`
- `abcportal.html` – Uses `api/me.php` to protect the page and `api/users.php` for user management

## XAMPP defaults

- MySQL user: `root`, password: (empty). If you've set a password, update it in `api/config.php` and `api/setup.php`.
- Database name: `capstone` (created by setup script if missing)

## Troubleshooting

- 500 error on API calls: check `xampp/apache/logs/error.log` for PHP errors.
- Database connection failed: ensure MySQL is running and credentials in `api/config.php` are correct.
- Session issues: make sure cookies are enabled in your browser; Apache must be serving from the same origin (http://localhost/capstone/).

## Security notes

- Passwords are stored with `password_hash()`.
- This is a simple demo. For production, add CSRF protection, use HTTPS, and implement stronger validation and logging.
