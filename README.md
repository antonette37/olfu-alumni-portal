# OLFU CCS Alumni Portal

Web alumni portal (`public_html/`) and mobile app (`AlumniApp/`).

## Setup after clone

1. **Database:** Copy `public_html/db_config.example.php` to `public_html/db_config.php` and set credentials.
2. **Email:** Copy `public_html/email_config.example.php` to `public_html/email_config.php` and set SMTP settings.
3. **Uploads:** Create `public_html/uploads/` on the server (not in git).
4. **Mobile app:**
   ```bash
   cd AlumniApp
   npm install
   npm run start:lan
   ```

## Deploy

Upload `public_html/` to Hostinger. Keep `db_config.php` and `email_config.php` only on the server.
