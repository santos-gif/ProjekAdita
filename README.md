# Campus Collab

Platform kolaborasi proyek kampus. Backend PHP 8.2 (PDO MySQL, tanpa framework),
frontend HTML/CSS/JavaScript murni. Tanpa Node.js dan Composer.

## Prasyarat
- XAMPP (Apache dengan `mod_rewrite`, PHP 8.2, ekstensi `pdo_mysql`)
- MySQL 8 (atau MariaDB dari XAMPP)

## Menjalankan
1. Buat database `projekadita_db` (utf8mb4), lalu impor skema:
   `mysql -u <user> -p projekadita_db < backend/database/schema.sql`
2. Buat `.env.dev` di root proyek berisi `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`.
   Dengan variabel environment `APP_ENV=production` aplikasi memakai `.env.prod`; selain itu `.env.dev`.
   Kedua file di-ignore git (`.env*`).
3. Salin `backend/apache.conf` ke konfigurasi Apache (mis. `httpd-vhosts.conf`), ganti `/path/to/ProjekAdita`
   dengan lokasi proyek. DocumentRoot menunjuk ke `frontend/`, dan `/api` di-alias ke `backend/`.
4. Restart Apache, lalu buka `http://campus-collab.local` (tambahkan ke file hosts) atau `http://localhost`.

## API (`/api/v1`, JSON)
Request POST/PUT/PATCH/DELETE wajib menyertakan header `X-CSRF-Token` (ambil dari `GET /csrf`).

- `GET /csrf` — token CSRF
- `GET /health` — status server dan database
- `POST /auth/register` — daftar (`name`, `email`, `password`, `confirmPassword`)
- `POST /auth/login` — masuk (`email`, `password`, `remember`)
- `POST /auth/logout` — keluar
- `GET /auth/me` — pengguna saat ini atau `null`
- `POST /auth/reset` — belum tersedia (501)

## Catatan
Login dan session sudah ada (password di-hash, CSRF, cookie HttpOnly/SameSite), tetapi aplikasi
belum siap produksi: belum ada pembatasan percobaan login, reset kata sandi belum tersedia,
dan belum ada otorisasi per fitur atau pengujian keamanan menyeluruh. Jangan dipublikasikan
ke internet atau dipakai dengan data nyata sebelum itu dilengkapi.
