Cara menjalankan sistem klinik sederhana:

1. Salin seluruh folder proyek ini ke dalam folder XAMPP htdocs, misalnya:
   C:\xampp\htdocs\klinik-sederhana

2. Pastikan XAMPP Apache dan MySQL sudah dijalankan.

3. Buka browser dan akses:
   http://localhost/klinik-sederhana/index.php

4. Halaman admin:
   http://localhost/klinik-sederhana/admin.php

5. Halaman dokter:
   http://localhost/klinik-sederhana/dokter.php

Pengaturan database:
- Koneksi database tersedia di file `config.php`.
- Nama database: `klinik_sederhana`
- User default: `root`
- Password default: kosong

Pengisian data awal:
- Aplikasi akan membuat database dan tabel otomatis jika belum ada.
- Data dokter dan jadwal awal juga akan ditambahkan secara otomatis.

Catatan penting:
- Jika ingin mengganti nomor admin WhatsApp, ubah nilai `ADMIN_PHONE` di file `config.php`.
- Jika database bermasalah, jalankan `schema.sql` di phpMyAdmin atau MySQL untuk membuat ulang struktur tabel.

File penting:
- `config.php` — konfigurasi koneksi MySQL
- `data.php` — lapisan data yang menghubungkan aplikasi ke database
- `index.php` — halaman pasien
- `admin.php` — halaman admin
- `dokter.php` — halaman dokter
- `assets/style.css` — gaya tampilan UI

ID dokter / doctor123
admin / admin123
