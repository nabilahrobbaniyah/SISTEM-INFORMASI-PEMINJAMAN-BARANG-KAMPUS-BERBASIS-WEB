# Dokumen Proyek

Folder ini berisi dokumen perancangan Sistem Informasi Peminjaman Barang Kampus Berbasis Web, Kelompok 6, mata kuliah Implementasi dan Pengujian (IMPAL), semester 2025-2026-2.

## Daftar Dokumen

| Dokumen | Keterangan | File |
|---|---|---|
| SKPL | Spesifikasi Kebutuhan Perangkat Lunak | SKPL_Kelompok6.pdf |
| DPPL | Deskripsi Perancangan Perangkat Lunak | DPPL_Kelompok6.pdf |
| Rancangan Konstruksi PL | Rencana konstruksi software | Rancangan_Konstruksi_PL_kel_6.docx |

## Anggota Kelompok 6

- Nabilah Robbaniyah
- Kholifa Ayu Lestari
- Mohammad Ashrof Alfarrosy
- Afiya Nadifa Febianti

## Teknologi

Laravel (PHP), MySQL, HTML/CSS/Bootstrap, Figma.

## Catatan Operasional Aplikasi

- NIM divalidasi sebagai 12 digit angka dan NIP sebagai 8 digit angka. Pencocokan ke sumber data resmi kampus belum dapat dilakukan karena proyek ini belum terhubung ke direktori/API resmi.
- Pendaftaran dan akun baru dari admin memerlukan verifikasi email. Akun yang dibuat admin selalu dimulai sebagai peminjam; tautan pengaturan password dikirim terpisah melalui mekanisme reset password Laravel.
- Pengingat serah-terima dikirim sehari sebelum tanggal jadwal; pengingat jatuh tempo dan keterlambatan dikirim pada jadwal harian pukul 08.00 sesuai zona waktu aplikasi. Kunci unik per penerima dan peristiwa mencegah pengiriman berulang.
- Aktifkan Laravel Scheduler pada server dengan menjalankan `php artisan schedule:run` setiap menit. Pengingat juga dapat diproses manual dengan `php artisan loans:send-schedule-notifications`.
- Konfigurasikan mail transport (`MAIL_*`) agar tautan verifikasi dan pengaturan password terkirim, serta `CAMPUS_EMAIL_DOMAINS` untuk membatasi domain email pendaftar.
- Perubahan role hanya tersedia bagi admin, memerlukan password admin saat ini dan alasan, serta mencatat target, role lama/baru, pelaku, waktu, alasan, dan hasil pada audit log.
- Pengajuan peminjaman harus ditinjau dan dikonfirmasi terpisah. Konfirmasi memvalidasi ulang data dan stok serta menggunakan kunci idempoten agar klik ulang tidak membuat transaksi ganda.
- Pengembalian dicatat sebagai jumlah baik, rusak ringan, rusak berat, dan hilang secara terpisah. Barang hilang, dalam pemeriksaan, perbaikan, rusak, atau dihapuskan tidak ditawarkan sebagai stok siap; semua perpindahan tahap dicatat di riwayat inventaris.
- Barang hilang yang ditemukan masuk antrean pemeriksaan. Barang dalam perbaikan baru kembali ke stok siap setelah petugas menyatakan hasil pemeriksaan layak; pergerakan tersebut tidak menambah total unit fisik inventaris.
- Jumlah inventaris menggunakan kuantitas per status untuk barang massal: layak pinjam (tersedia di rak dan sedang dipinjam), rusak ringan, menunggu pemeriksaan, dalam perbaikan, rusak berat, hilang, dan dihapuskan. Total tercatat adalah jumlah layak pinjam ditambah semua kuantitas status tersebut; unit yang dihapuskan tetap tercatat untuk jejak audit.
- Jumlah tersedia di rak berbeda dari ketersediaan untuk suatu rentang tanggal. Detail barang menampilkan keduanya. Penambahan, pengurangan, koreksi, dan penghapusan hanya dapat dilakukan admin melalui transaksi beralasan yang menyimpan jumlah sebelum/sesudah dalam riwayat.
- Petugas dapat melihat dan menjalankan transaksi operasional inventaris, tetapi perubahan spesifikasi dan koreksi jumlah hanya tersedia bagi admin; batas ini diterapkan oleh middleware dan pemeriksaan backend, bukan hanya dengan menyembunyikan tombol.
- Inventaris saat ini dicatat sebagai kuantitas barang massal; pelacakan nomor seri per unit belum tersedia.
