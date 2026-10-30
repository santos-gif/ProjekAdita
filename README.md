# Kampus Kolaborasi — PHP Native

MVP platform kolaborasi proyek dan karya kampus. Dibangun dengan PHP Native, MySQL/PDO, HTML, CSS, JavaScript, JSON, Docker, dan Apache. Tidak menggunakan framework.

## Fitur MVP
- Registrasi dan login dengan `password_hash()` / `password_verify()`.
- Role `user`, `moderator`, dan `admin`.
- Dashboard dan profil dasar.
- Daftar, pencarian, filter, dan detail proyek.
- Pengajuan proyek oleh mahasiswa dengan status moderasi.
- Admin dapat menyetujui, menolak, atau meminta revisi.
- Permintaan bergabung proyek dan keputusan pemimpin proyek.
- Notifikasi dasar.
- Audit log untuk tindakan administratif.
- CSRF token, prepared statements, validasi backend, session hardening.
- REST-style JSON endpoint sederhana pada `/api/projects`.

## Menjalankan dengan Docker
1. Install Docker Desktop.
2. Ekstrak folder proyek.
3. Jalankan dari folder ini:
   ```bash
   docker compose up -d --build
   ```
4. Buka http://localhost:8080
5. Database tersedia pada port host `3307`.

### Akun demo
- Admin: `admin@kampus.test` / `Password123!`
- User: `mahasiswa@kampus.test` / `Password123!`

Ganti password demo dan kredensial database sebelum deployment. Data seed hanya untuk development.

## Menjalankan tanpa Docker
- PHP 8.3+, ekstensi `pdo_mysql`, dan MySQL 8+.
- Buat database `kampus_kolaborasi`, lalu import `database/schema.sql` dan `database/seed.sql`.
- Atur environment variables atau ubah nilai fallback di `app/config/database.php`.
- Jalankan:
  ```bash
  php -S localhost:8000 -t public
  ```
- Buka http://localhost:8000

## Struktur
- `app/`: konfigurasi, helper, controller, model.
- `public/`: entry point, CSS, JS, upload publik non-sensitif.
- `database/`: schema dan seed.
- `storage/`: dokumen privat (jangan diekspos langsung oleh web server).
- `docs/`: rancangan endpoint dan catatan pengembangan.

## Catatan penting sebelum produksi
MVP ini belum mengimplementasikan pengiriman email sungguhan, verifikasi email, reset password berbasis token email, antivirus upload, chat realtime, kompetisi/event lengkap, publikasi HKI, serta rekomendasi machine learning. Jangan mengaktifkan pendaftaran publik tanpa verifikasi email dan kebijakan domain kampus. Terapkan HTTPS, secrets manager, backup, rate limiting di reverse proxy, pemindaian file, kebijakan privasi, dan pengujian keamanan sebelum digunakan kampus.
