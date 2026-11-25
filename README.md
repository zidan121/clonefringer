# CLONEFRINGER

Aplikasi manajemen personalia dan absensi fingerprint mandiri. Proyek ini membangun ulang alur kerja yang setara secara fungsional dengan sistem manajemen absensi desktop, menggunakan implementasi, kode, dan aset orisinal.

## Status
**Tahap 1 selesai sebagai preview UI; fondasi database dan API PHP mulai dibangun.**

## Menjalankan
Preview UI: buka `index.html` di browser modern.\n\nBackend awal: lihat `docs/DATABASE-SETUP.md` untuk menyiapkan MySQL dan endpoint pemeriksaan koneksi.

## Modul target
- Dashboard
- Pegawai
- Pengecualian
- Mesin
- Laporan
- Pengaturan

## Prinsip implementasi
- Data asli tidak diubah tanpa tindakan eksplisit pengguna.
- Semua operasi impor, sinkronisasi, dan penghapusan harus menampilkan pratinjau serta meminta konfirmasi.
- Backup sebelum operasi tulis atau migrasi.
- Log transaksi fingerprint dipertahankan sebagai data mentah; perhitungan hadir/pulang berada pada lapisan terpisah.
- Integrasi perangkat dibuat melalui adapter per model/protokol; kompatibilitas harus diuji, bukan diasumsikan.

## Catatan
Tahap awal ini merupakan kerangka UI dengan data contoh. UI masih memakai data demo/localStorage dan belum membaca database. Backend memiliki skema MySQL, login berbasis sesi, endpoint pegawai dasar, dan health-check. UI belum terhubung ke API; kalkulasi server dan adapter mesin belum tersedia. Tidak ada koneksi ke database proprietary Fingerspot.
