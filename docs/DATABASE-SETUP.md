# Database dan backend awal

Fondasi database memakai MySQL 8.0+ atau MariaDB 10.6+. API memakai PHP 8.1+ dan PDO MySQL.

## 1. Backup dan buat database

Jangan jalankan skema ini di database aplikasi lain. Buat database baru bernama `clonefringer`, lalu backup sebelum perubahan skema berikutnya.

Contoh SQL:

```sql
CREATE DATABASE clonefringer
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER 'clonefringer_app'@'localhost' IDENTIFIED BY 'GANTI_PASSWORD_KUAT';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES
  ON clonefringer.* TO 'clonefringer_app'@'localhost';
FLUSH PRIVILEGES;
```

Import `database/schema.sql` ke database tersebut. Sesuaikan port MySQL dengan instalasi lokal.

## 2. Konfigurasi PHP

Atur environment variables di konfigurasi Apache/PHP sesuai server (jangan commit nilainya):

- `CLONEFRINGER_DB_HOST`
- `CLONEFRINGER_DB_PORT`
- `CLONEFRINGER_DB_NAME`
- `CLONEFRINGER_DB_USER`
- `CLONEFRINGER_DB_PASS`

Jangan simpan password database di repository atau folder publik.

## 3. Uji koneksi

Untuk XAMPP, letakkan repository di `C:\\xampp\\htdocs\\clonefringer`, aktifkan Apache dan MySQL, lalu akses `http://localhost/clonefringer/`. File `.htaccess` di root akan meneruskan `/api/*` ke PHP API dan memblokir akses langsung ke file konfigurasi/database. Aktifkan modul Apache `mod_rewrite` dan `AllowOverride All` pada konfigurasi direktori. Pastikan PHP 8.1+ dan ekstensi `pdo_mysql` aktif. Respons `database: connected` menandakan PHP dapat membuka koneksi PDO dan menjalankan query sederhana.

Jika memakai PHP built-in server untuk uji lokal dari root repository:

```bash
php -S 127.0.0.1:8081 -t backend/public backend/public/router.php
```

Buka `http://127.0.0.1:8081/api/health`. Router development akan meneruskan seluruh path API. Untuk Apache/XAMPP, root `.htaccess` meneruskan endpoint API ke front controller.\n\n## 3a. Buat akun admin pertama\n\nSetelah schema diimpor dan environment database tersedia, jalankan dari root repository:\n\n```bash\nCLONEFRINGER_INITIAL_ADMIN_PASSWORD='PASSWORD_PANJANG_MINIMAL_12_KARAKTER' php backend/bin/create-admin.php\n```\n\nMasukkan username dan nama admin saat diminta. Password dibaca dari environment, bukan diketik ke prompt. Script hanya mengizinkan pembuatan admin bila tabel pengguna masih kosong.\n\nPowerShell Windows (jalankan dari root repository; ganti password dengan milik Anda dan jangan bagikan):\n\n```powershell\n$env:CLONEFRINGER_INITIAL_ADMIN_PASSWORD = 'PASSWORD_PANJANG_MINIMAL_12_KARAKTER'\nphp backend/bin/create-admin.php\nRemove-Item Env:CLONEFRINGER_INITIAL_ADMIN_PASSWORD\n```\n\n## 3b. Endpoint awal\n\n- `POST /api/auth/login` — menerima JSON `username` dan `password`.\n- `GET /api/auth/me` — memeriksa sesi aktif dan mengembalikan token CSRF.\n- `POST /api/auth/logout` — wajib mengirim header `X-CSRF-Token`.\n- `GET /api/employees?q=...` — daftar pegawai (maksimal 500).\n- `POST /api/employees` — tambah pegawai, role admin/operator.\n- `PUT /api/employees/{id}` — ubah pegawai, role admin/operator.\n- `DELETE /api/employees/{id}` — menonaktifkan pegawai (admin saja), bukan menghapus riwayat.\n\nSemua request tulis setelah login wajib mengirim header `X-CSRF-Token` dari respons login atau `/auth/me`. Sesi menggunakan cookie HttpOnly dan SameSite Strict. Gunakan HTTPS di server yang diakses melalui jaringan.

## 4. Prinsip data penting

- `raw_scanlogs` adalah arsip transaksi asli. Tidak disediakan endpoint edit/hapus.
- Hasil hitung disimpan terpisah pada `attendance_daily_results` dan terikat ke `processing_batches`; proses ulang menghasilkan batch baru sehingga hasil lama tetap dapat diaudit.
- `source_scan_ids` menyimpan referensi scan yang dipakai untuk hasil harian.
- Jadwal pegawai memiliki rentang berlaku. Jika tidak ada penugasan individual, aplikasi dapat memakai default unit.
- Perangkat fingerprint belum dihubungkan. Adapter harus dibuat dan diuji sesuai merek, model, firmware, serta protokol perangkat sebenarnya.

## Hubungkan UI ke API\n\nUI memanggil API pada origin yang sama dengan prefix `/api`. Karena itu, jalankan frontend dan API pada host yang sama. Jangan membuka `index.html` langsung dengan skema `file://`; gunakan Apache melalui alamat localhost.\n\n## Status dan batasan

Fondasi login, CRUD pegawai, master jadwal/kalender/pengecualian/mesin, impor scanlog, pemetaan PIN, dan proses rekap server sudah tersedia pada branch pengembangan. Belum tersedia koneksi langsung ke mesin fingerprint, pengujian runtime menyeluruh, serta seluruh fitur CRUD untuk semua master. Jangan gunakan untuk absensi produksi sebelum uji penerimaan dan backup terverifikasi.

## Endpoint tambahan yang tersedia

Semua endpoint di bawah memerlukan sesi login untuk membaca data. Semua operasi tulis memerlukan role admin/operator (kecuali keputusan pengecualian yang khusus admin) dan header `X-CSRF-Token`.

- `GET /api/schedules`, `POST /api/schedules`
- `GET /api/calendar`, `POST /api/calendar`
- `GET /api/exceptions`, `POST /api/exceptions`
- `POST /api/exceptions/{id}/decision` — admin menyetujui/menolak pengajuan pending.
- `GET /api/machines`, `POST /api/machines` — inventaris saja, belum menguji koneksi perangkat.
- `GET /api/schedule-assignments`, `POST /api/schedule-assignments`
- `GET /api/unit-schedule-defaults`, `POST /api/unit-schedule-defaults`

Endpoint create untuk master belum menyediakan update/delete.
\n\n## Menjalankan aplikasi di XAMPP (Windows)\n\n1. Salin/clone repository ke `C:\\xampp\\htdocs\\clonefringer`.\n2. Pastikan Apache dan MySQL aktif dari XAMPP Control Panel.\n3. Buat database `clonefringer` dan import `database/schema.sql` melalui phpMyAdmin.\n4. Buat user database khusus aplikasi dan berikan hak akses hanya pada database tersebut.\n5. Atur environment variables database pada konfigurasi Apache/PHP, lalu restart Apache.\n6. Buat admin pertama melalui perintah PowerShell di atas.\n7. Buka `http://localhost/clonefringer/` dan login.\n\nJika API mengembalikan 404, periksa `mod_rewrite`, `AllowOverride All`, dan bahwa file root `.htaccess` ikut tersalin. Jika muncul pesan database belum terhubung, periksa environment variables dan ekstensi `pdo_mysql`.\n

## Scanlog dan proses rekap

- `GET /api/scanlogs?from=YYYY-MM-DD&to=YYYY-MM-DD` — membaca log mentah (maksimal 31 hari per permintaan).
- `GET /api/scanlogs/unmatched` — daftar PIN yang belum dipetakan ke pegawai.
- `POST /api/scanlogs/import` — impor JSON `{"rows":[...]}`, maksimal 1.000 baris. Setiap baris minimal memiliki `pin` dan `scanned_at` dengan format `YYYY-MM-DD HH:MM:SS`. Kolom opsional: `verify_method`, `device_event_code`, `device_transaction_id`.
- `POST /api/scanlogs/map-pin` — pemetaan PIN terpisah menggunakan `pin` dan `employee_id`; tidak mengubah arsip scanlog.
- `POST /api/attendance/process` — proses manual dengan JSON `{"from":"YYYY-MM-DD","to":"YYYY-MM-DD"}`. Setiap proses menghasilkan batch baru.
- `GET /api/attendance/batches` — riwayat batch.
- `GET /api/attendance/results?batch_id=ID` — hasil batch.

Jika database sudah dibuat sebelum fitur pemetaan PIN ditambahkan, backup dahulu lalu jalankan satu kali file `database/migrations/001_scanlog_pin_mappings.sql`. Untuk instalasi database baru, cukup impor `database/schema.sql` versi terbaru.

Aturan kalkulasi saat ini: scan pertama menjadi waktu masuk, scan terakhir menjadi waktu pulang, scan perantara tetap direferensikan; jadwal pegawai didahulukan lalu default unit; hari libur nonkerja dan pengecualian yang disetujui dipertimbangkan; hari kerja terjadwal tanpa scan diberi status tidak hadir. Shift lintas tengah malam diberi status pemeriksaan manual, bukan dihitung otomatis. Periode proses maksimal 31 hari.
