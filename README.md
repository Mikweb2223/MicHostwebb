MicHost (BloodLink) — Local run & smoke-test

Quick start (PHP built-in server)

Prerequisites:
- PHP 8+ with PDO MySQL extension
- MySQL or MariaDB running and accessible
- Configure DB in `php/config/database.php`

Google sign-in setup:
- Create a Google OAuth 2.0 Web application client in Google Cloud Console.
- Add `http://localhost:8000` to the authorized JavaScript origins.
- Replace `YOUR_CLIENT_ID.apps.googleusercontent.com` in `login.html` with your Web application client ID.
- Set the same value as the `GOOGLE_CLIENT_ID` environment variable for PHP.
- Add each deployed site origin (including scheme and port) to the OAuth client's authorized JavaScript origins.
- Do not put a Google client secret in this project. PHP must be able to reach `oauth2.googleapis.com` over HTTPS.

Start the app (from project root):

Windows PowerShell:

```powershell
php -S localhost:8000 -t .
```

Then open http://localhost:8000 in your browser.

Important: open the pages through this PHP URL, such as `http://localhost:8000/signup.html`. Do not use VS Code Live Server for the HTML pages, because Live Server cannot execute the PHP registration API and will return `405 Method Not Allowed` when Create Account is pressed.

Notes:
- `php/config/bootstrap.php` contains a development helper `ensureDatabaseSchema()` which creates required tables on first request. Remove or secure before production.
- Sessions are used for auth; ensure cookies are enabled.

Smoke-test curl commands

Replace the example values with real credentials that exist in your `users` table.

1) Login (save cookies)

```bash
curl -c cookies.txt -X POST "http://localhost:8000/php/auth/login.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "email=you@example.com&password=yourpassword"
```

2) Fetch profile (uses saved cookie)

```bash
curl -b cookies.txt "http://localhost:8000/php/profile/get-profile.php"
```

3) Fetch reports data

```bash
curl -b cookies.txt "http://localhost:8000/php/reports/data.php"
```

4) List notifications

```bash
curl -b cookies.txt "http://localhost:8000/php/notifications/list.php"
```

5) List blood requests

```bash
curl -b cookies.txt "http://localhost:8000/php/requests/list.php"
```

Google Authenticator setup is available from Settings after login. Scan the displayed QR code, enter the six-digit code, and future password logins will require the code. The QR image uses the QR Server endpoint, so the browser needs internet access during setup.

6) Create an appointment (example)

```bash
curl -b cookies.txt -X POST "http://localhost:8000/php/appointments/create.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "donor=John+Doe&blood=A%2B&date=2026-10-15&time=09:00&hospital=City+Hospital"
```

Testing notes and troubleshooting

- If you get 401 responses, ensure you logged in and cookies are preserved with `-c` and `-b` in curl.
- If `php` isn't available on Windows, install PHP or use XAMPP/WAMP and point the document root to the project folder.
- For database connection issues, open `php/config/database.php` and verify `host`, `dbname`, `user`, and `pass`.

Next steps you can ask me to do:
- Run a scripted smoke-test (I can create a .sh/.ps1 that runs the curl commands).
- Add client-side loading indicators and toast notifications across pages.
- Harden database bootstrapping for production.
