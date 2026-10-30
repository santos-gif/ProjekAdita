# Roadmap pengembangan

## MVP yang tersedia
1. Fondasi PHP Native, konfigurasi PDO, Docker.
2. Registrasi/login/logout dan role dasar.
3. Master 11 program studi.
4. Dashboard, profil dasar.
5. Pengajuan proyek dan moderasi.
6. Eksplorasi, pencarian, filter.
7. Permintaan bergabung dan keputusan pemimpin proyek.
8. Notifikasi dalam aplikasi dan audit log dasar.
9. Endpoint JSON daftar proyek.

## Tahap berikutnya
- Verifikasi email kampus dan reset password berbasis token sekali pakai.
- CV/proposal upload ke storage privat dengan validasi MIME, batas ukuran, nama acak, dan pemindaian malware.
- Portofolio, publikasi karya, HKI, lisensi dan persetujuan kontributor.
- Event dan kompetisi.
- Moderasi laporan konten, eskalasi, dan panel audit yang read-only.
- Pengelolaan program studi dan bidang fokus oleh admin khusus.
- Pembagian tugas, milestone, dan kalender proyek.
- Pusat pesan, email, dan notifikasi preferensi.
- Rekomendasi berbasis minat/keahlian dan skor trending terukur.

## Keputusan arsitektur
- PHP Native dengan pola controller/helper dan PDO; tanpa framework.
- MySQL/InnoDB untuk transaksi dan integritas relasional.
- Server-rendered HTML untuk MVP, JavaScript untuk interaksi ringan.
- JSON dipakai untuk konfigurasi dan beberapa atribut terstruktur.
- Dokumen sensitif disimpan di `storage/`, di luar web root.
