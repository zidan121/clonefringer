# CLONEFRINGER — Roadmap

## Tahap 1 — Fondasi UI
- [x] Identitas dan kerangka aplikasi
- [x] Dashboard awal dan alur persiapan/rutin
- [x] Navigasi menu utama
- [x] Kerangka halaman modul
- [ ] Pemeriksaan visual pada resolusi desktop pengguna

## Tahap 2 — Pegawai
- [ ] Kategori Aktif, Detail, Non Aktif, Berhenti, Segera Habis Kontrak
- [ ] Filter dan pemilih kolom tabel
- [x] Form biodata pegawai (identitas, kepegawaian, kontak, data mesin dasar)
- [ ] Import/export dengan validasi dan pratinjau
- [x] Status dan jadwal dasar; [ ] pengecualian dan registrasi perangkat
- [ ] Riwayat perubahan

## Tahap 3 — Master dan jadwal
- [ ] Departemen, kantor, jabatan
- [x] Master jam kerja, hari aktif, istirahat, dan toleransi (demo lokal)\n- [ ] Persistensi master jam kerja ke database
- [ ] Jadwal normal, berpola, auto shift
- [x] UI kalender, CRUD hari libur/kegiatan dan dampak hari kerja (browser lokal)\n- [ ] Kalender nasional terverifikasi dan database
- [x] Penugasan master jadwal per pegawai dan override jam/toleransi (demo lokal)\n- [x] Default jadwal per unit (browser lokal)\n- [x] Kalkulasi demo memakai jadwal individual, default unit, kalender, dan pengecualian disetujui\n- [ ] Uji data riil dan shift lintas hari

## Tahap 4 — Mesin dan transaksi
- [x] Inventaris perangkat, tambah/ubah/hapus, parameter koneksi, ekspor CSV
- [ ] Uji komunikasi perangkat melalui backend/adapter nyata
- [ ] Adapter komunikasi per model
- [ ] Download scanlog
- [ ] Upload/unduh user jika protokol mendukung
- [ ] Penyimpanan raw log tanpa duplikasi
- [ ] Status koneksi dan audit sinkronisasi

## Tahap 5 — Pengecualian dan kalkulasi
- [x] UI dan CRUD lokal izin, sakit, cuti, perubahan jadwal/jam, lembur\n- [ ] Integrasi pengecualian dengan kalkulasi absensi
- [x] Form tukar/ganti jadwal dan jam kerja (lokal)
- [ ] Lembur dan kerja ekstra
- [ ] Mesin perhitungan yang dapat diuji
- [ ] Koreksi manual dengan audit trail

## Tahap 6 — Laporan dan payroll
- [x] UI scanlog mentah dan rincian harian (data demo lokal)\n- [ ] Integrasi scanlog dengan mesin dan database
- [x] Proses rekap manual per pegawai/hari (aturan demo sementara)\n- [ ] Rekap periode dengan jadwal dan kalender resmi
- [ ] Terlambat, pulang cepat, tidak scan
- [ ] Lembur dan istirahat
- [ ] Komponen, formula, grup, proses gaji
- [ ] Ekspor dan cetak

## Tahap 7 — Operasional
- [ ] Login dan hak akses
- [ ] Backup/restore teruji
- [ ] Migrasi database
- [ ] Pengujian regresi dan dokumentasi instalasi

> Integrasi perangkat keras dan kompatibilitas database belum dianggap selesai sampai diuji dengan perangkat/database nyata dan tersedia sampel data yang aman.
