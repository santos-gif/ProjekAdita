# ProjectAdita

Platform kolaborasi proyek kampus. Backend PHP 8.2 (PDO, tanpa framework), database MariaDB,
frontend HTML/CSS/JavaScript murni. Tanpa Node.js dan Composer. Dijalankan dengan Docker Compose.

## Prasyarat
- Docker Desktop (Docker Compose v2)

## Menjalankan
Tiga service: `frontend` (nginx, juga mem-proxy `/api`), `api` (PHP + Apache), `db` (MariaDB).
Ada dua file compose, dan masing-masing berdiri sendiri.

**Siapa saja** (`docker-compose.yml`, semuanya di-build ke image):
1. Salin `.env.example` menjadi `.env`, lalu isi `DB_NAME`, `DB_USER` (jangan `root`), dan `DB_PASS`.
2. `docker compose up --build`, lalu buka `http://localhost:8080`.
3. Berhenti: `docker compose down` (data tetap ada; `down -v` juga menghapus database).

Cookie sesi `Secure` aktif secara default. Jika dibuka lewat http di host selain localhost,
tambahkan `APP_COOKIE_SECURE=0` di `.env`. Untuk produksi, pakai `.env.prod` dan reverse proxy HTTPS:
`docker compose --env-file .env.prod up -d --build`.

**Development** (`docker-compose.dev.yml`, kode PHP dan frontend langsung terbaca dari folder proyek):
1. Buat `.env.dev` di root proyek (di-ignore git): `DB_NAME`, `DB_USER`, `DB_PASS`,
   `DB_HOST_PORT` (port database di komputer Anda, pilih yang belum dipakai), dan `API_PORT`.
2. `docker compose -f docker-compose.dev.yml --env-file .env.dev up --build`
   (berhenti dengan perintah yang sama memakai `down`).
   - Aplikasi: `http://localhost:8080`
   - API langsung: `http://localhost:<API_PORT>/api/v1/health`
   - Database dari komputer Anda: `127.0.0.1:<DB_HOST_PORT>`
3. Setelah mengubah `frontend/nginx.conf`, jalankan perintah di atas dengan `restart frontend`.
   Perubahan `Dockerfile` atau `php.ini` butuh `--build`.

Melihat isi database (nama container dev `projekadita-dev-db-1`; untuk file standar `projekadita-db-1`):
`docker exec -it projekadita-dev-db-1 sh -c 'mariadb -u$MARIADB_USER -p$MARIADB_PASSWORD $MARIADB_DATABASE'`

Skema (`backend/database/schema.sql`) diimpor otomatis hanya pada start pertama, saat volume
database masih kosong. Untuk mengulang dari nol: `down -v`.

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
