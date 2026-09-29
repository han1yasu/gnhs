# GNHS Guidance System

## Run on Windows, macOS, or Linux

Install Docker Desktop (Windows or macOS) or Docker Engine with the Compose plugin (Linux). Docker runs the PHP web server and MariaDB in containers, so the host computer does not need XAMPP, PHP, or MySQL installed.

1. Copy `.env.example` to `.env`.
2. Edit `.env`. Set unique values for `DB_PASSWORD`, `MARIADB_ROOT_PASSWORD`, and `BOOTSTRAP_ADMIN_PASSWORD`. The owner password must be at least 14 characters. Set the owner's email and ID too.
3. From this project folder, run:

   ```sh
   docker compose up --build -d
   ```

4. Open <http://127.0.0.1:8080/> and sign in with the owner email and password from `.env`. The first login asks you to add an authenticator key.

Stop the app with `docker compose down`. Your database and avatar uploads stay in Docker volumes. To remove the database and uploaded files too, run `docker compose down --volumes`.

The app listens on this computer only by default. Set `APP_PORT` in `.env` to change its local port. Set `GEMINI_API_KEY` if you want AI summaries; submissions still work when it is blank. Email password resets need a mail service configured in PHP.

## Run with an existing local PHP and MySQL install

Create an empty `gnhs_guidance` database and import `database/schema.sql`. Set `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, and `DB_NAME` for that machine, then run `php database/migrate.php`. Create the first counselor account with `php database/bootstrap.php` after setting the `BOOTSTRAP_ADMIN_*` variables listed in `.env.example`. Start PHP's local server from this directory with `php -S 127.0.0.1:8000 -t . router.php` and open <http://127.0.0.1:8000/>.

## Project folders

- `assets/`: browser styles, scripts, and images
- `api/`, `pages/`, `includes/`, `src/`: application code
- `database/`: schema, migrations, and first admin bootstrap
- `tools/`: local diagnostic and legacy utilities

The `gnhs-guidance/` folder is the older copy. The root app is the maintained version.
