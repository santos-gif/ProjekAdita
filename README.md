# Campus Collab — Tanpa Framework

Starter MVP platform kolaborasi kampus menggunakan:
- HTML, CSS, JavaScript murni
- Node.js `http` (tanpa Express)
- PostgreSQL dengan driver `pg` (driver database, bukan framework)

## Prasyarat
- Node.js versi LTS
- PostgreSQL
- VS Code (opsional)

## Menjalankan aplikasi
1. Buat database PostgreSQL bernama `campus_collab`.
2. Jalankan `backend/database/schema.sql` pada database tersebut.
3. Salin `.env.example` menjadi `.env`, lalu sesuaikan `DATABASE_URL`.
4. Jalankan `npm install`.
5. Jalankan `npm start`.
6. Buka `http://localhost:3000`.

## API awal
- `GET /api/v1/health` — status server dan database
- `GET /api/v1/projects` — daftar proyek
- `POST /api/v1/projects` — membuat pengajuan proyek
- `GET /api/v1/departments` — daftar program studi

## Catatan
Ini adalah fondasi MVP untuk pengembangan. Autentikasi dan otorisasi belum diaktifkan,
jadi jangan dipublikasikan ke internet atau digunakan dengan data nyata sebelum fitur
login, session aman, validasi lanjutan, pembatasan akses, dan pengujian keamanan dibuat.
