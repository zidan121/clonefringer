# Impor backup Fingerspot unit SD

Importer ini membaca database Fingerspot yang **sudah dipulihkan ke database staging terpisah**. Jangan jalankan dump SQL langsung terhadap database CLONEFRINGER.

## 1. Siapkan database staging
1. Buat database kosong, misalnya `fingerspot_sd_staging`.
2. Impor `backup SD.sql` ke database staging tersebut melalui phpMyAdmin atau perintah MySQL.
3. Pastikan database tujuan CLONEFRINGER sudah dibuat dan `database/schema.sql` sudah dijalankan.
4. Pastikan unit `SDIT` tersedia.

## 2. Atur environment (PowerShell)
Isi variabel berikut sesuai database lokal. Jangan menyimpan password di repository.

```powershell
$env:LEGACY_DB_HOST="127.0.0.1"
$env:LEGACY_DB_PORT="3307"
$env:LEGACY_DB_NAME="fingerspot_sd_staging"
$env:LEGACY_DB_USER="root"
$env:LEGACY_DB_PASS=""

$env:CLONEFRINGER_DB_HOST="127.0.0.1"
$env:CLONEFRINGER_DB_PORT="3307"
$env:CLONEFRINGER_DB_NAME="clonefringer"
$env:CLONEFRINGER_DB_USER="root"
$env:CLONEFRINGER_DB_PASS=""
```

## 3. Simulasi (default, tidak menulis data)
Dari folder proyek:

```powershell
php backend/bin/import-fingerspot-sd.php
```

Baca ringkasan jumlah tabel, scanlog valid, dan tanggal placeholder. Mode ini tidak mengubah database.

## 4. Impor sebenarnya
1. Backup database CLONEFRINGER dan pastikan file backup dapat dibaca.
2. Jalankan simulasi dan periksa hasilnya.
3. Jika sudah disetujui, jalankan:

```powershell
php backend/bin/import-fingerspot-sd.php --apply
```

Seluruh penulisan dilakukan dalam satu transaksi database. Jika terjadi kegagalan, transaksi dibatalkan.

## Data yang dipindahkan
- Pegawai: PIN, nama, unit SDIT, status, tanggal mulai kerja, telepon, jenis kelamin, tanggal lahir, dan tempat lahir sebagai catatan.
- Mesin: nomor seri, nama, model indikatif, IP/port dan status unknown. Activation code dan communication key sengaja tidak dibaca.
- Scanlog: `att_log` dengan tanggal valid mulai 2001, PIN asli, waktu scan, kode verifikasi dan payload sumber. ID transaksi dibuat deterministik dari serial mesin + waktu + PIN, sehingga proses ulang tidak menggandakan transaksi.

## Batasan penting
- Baris scan dengan tanggal placeholder `2000-01-01` atau zero-date tidak diimpor sebagai kehadiran. Jumlahnya ditampilkan dalam laporan.
- Dua pola jadwal sumber (Guru dan Yayasan) dipetakan jika semua hari kerja aktif pada pola tersebut memiliki jam masuk, pulang, dan toleransi yang sama. Penugasan jadwal per pegawai ikut dipindahkan dengan tanggal efektif sumber. Pola dengan jam berbeda antarhari dilewati dan dilaporkan untuk peninjauan manual.
- Backup ini memiliki scanlog bertanggal 2020–2021, sedangkan sebagian tanggal mulai kerja pegawai berada pada 2022. Importer menampilkan jumlah scan yang mendahului tanggal mulai kerja. Scan tersebut tetap dipertahankan sebagai data mentah, tetapi jangan dianggap sebagai kehadiran yang sah sebelum diverifikasi.
- `shift_result` tidak dipindahkan sebagai scanlog karena merupakan hasil kalkulasi aplikasi lama.
- Setelah impor, buka modul Pegawai dan Laporan, cocokkan PIN belum cocok, lalu atur jadwal. Jangan proses rekap sebelum jadwal SD diverifikasi.
- Rekap tidak membuat ketidakhadiran sebelum tanggal mulai kerja atau setelah akhir kontrak. Scan di luar masa aktif pegawai ditandai `perlu_review_status_pegawai`, bukan dianggap hadir/absen normal.
- Jika hanya ada satu scan pada hari kerja, hasil ditandai `perlu_review_scan_tunggal` karena jam pulang belum dapat dipastikan. Periksa rincian scan sebelum menetapkan status akhir.
- Importer ini tidak menghubungkan mesin secara langsung; protokol perangkat perlu diuji terpisah.
