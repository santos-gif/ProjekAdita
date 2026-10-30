# Campus Collab

Platform kolaborasi proyek kampus. Backend PHP 8.2 (PDO, tanpa framework), database MariaDB,
frontend HTML/CSS/JavaScript murni. Tanpa Node.js dan Composer. Dijalankan dengan Docker Compose.

## Prasyarat
- Docker Desktop (Docker Compose v2)

## Menjalankan
Tiga service: `frontend` (nginx, juga mem-proxy `/api`), `api` (PHP + Apache), `db` (MariaDB).

1. Buat `.env.dev` di root proyek (di-ignore git) berisi `DB_NAME`, `DB_USER`, `DB_PASS`.
   `DB_HOST` dan `DB_PORT` diatur otomatis oleh Compose untuk jaringan Docker.
2. Development (kode PHP dan frontend langsung terbaca dari folder proyek):
   `docker compose --env-file .env.dev -f docker-compose.yml -f docker-compose.dev.yml up --build`
   - Aplikasi: `http://localhost:8080`
   - API langsung: `http://localhost:8081/api/v1/health`
   - Database dari host: `127.0.0.1:3307`
3. Produksi (`docker-compose.yml` saja, tanpa mount dan tanpa port database):
   `docker compose --env-file .env.prod up -d --build`
   Pasang reverse proxy HTTPS di depan port `8080`; cookie sesi `Secure` aktif secara default
   (`APP_COOKIE_SECURE=1`).

Skema (`backend/database/schema.sql`) diimpor otomatis hanya pada start pertama, saat volume
database masih kosong. Untuk mengulang dari nol: `docker compose ... down -v`.

## API (`/api/v1`, JSON)
Request POST/PUT/PATCH/DELETE wajib menyertakan header `X-CSRF-Token` (ambil dari `GET /csrf`);
tanpa token yang valid server membalas 403.

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
