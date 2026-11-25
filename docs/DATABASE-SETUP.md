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

Atur environment variables sesuai server:

- `CLONEFRINGER_DB_HOST`
- `CLONEFRINGER_DB_PORT`
- `CLONEFRINGER_DB_NAME`
- `CLONEFRINGER_DB_USER`
- `CLONEFRINGER_DB_PASS`

Jangan simpan password database di repository atau folder publik.

## 3. Uji koneksi

Arahkan web server ke folder `backend/public` untuk API, lalu akses `/api/health`. Respons `database: connected` menandakan PHP dapat membuka koneksi PDO dan menjalankan query sederhana.

Jika memakai PHP built-in server untuk uji lokal dari root repository:

```bash
php -S 127.0.0.1:8081 -t backend/public
```

Buka `http://127.0.0.1:8081/api.php/health` hanya jika konfigurasi routing server mendukung path tersebut; cara paling sederhana untuk uji langsung adalah `http://127.0.0.1:8081/api.php` (akan memberi 404 sampai route disesuaikan). Untuk penggunaan normal, atur rewrite `/api/health` ke `api.php`.

## 4. Prinsip data penting

- `raw_scanlogs` adalah arsip transaksi asli. Tidak disediakan endpoint edit/hapus.
- Hasil hitung disimpan terpisah pada `attendance_daily_results` dan terikat ke `processing_batches`; proses ulang menghasilkan batch baru sehingga hasil lama tetap dapat diaudit.
- `source_scan_ids` menyimpan referensi scan yang dipakai untuk hasil harian.
- Jadwal pegawai memiliki rentang berlaku. Jika tidak ada penugasan individual, aplikasi dapat memakai default unit.
- Perangkat fingerprint belum dihubungkan. Adapter harus dibuat dan diuji sesuai merek, model, firmware, serta protokol perangkat sebenarnya.

## Status

Ini baru skema dan endpoint pemeriksaan kesehatan koneksi. Belum tersedia login, CRUD API, sinkronisasi perangkat, mesin kalkulasi server, maupun migrasi otomatis. Jangan gunakan untuk absensi produksi sebelum seluruh fungsi tersebut selesai diuji.
