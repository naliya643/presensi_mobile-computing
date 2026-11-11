# Frontend Presensi – Smart Attendance System

Modul antarmuka web (mobile-first) untuk sistem presensi online berbasis Geofencing GPS dan Swafoto Kamera.

## Akses Cepat

- `http://localhost/absensi/frontend/index.html` : Halaman Presensi Mahasiswa / Karyawan (Login, Verifikasi Lokasi, Kamera Selfie, Presensi Masuk & Pulang, Form Izin, dan Riwayat).
- `http://localhost/absensi/frontend/admin.html` : Halaman Rekapitulasi dan Audit Data Presensi.

## Fitur Utama

- **Autentikasi Pengguna**: Login akun dengan NIM dan kata sandi.
- **Deteksi Geofence GPS**: Peta interaktif Leaflet untuk memvalidasi posisi pengguna terhadap radius kampus.
- **Kamera Swafoto**: Pengambilan foto verifikasi wajah langsung melalui kamera perangkat.
- **Dua Sesi Presensi**: Mendukung presensi masuk dan presensi pulang bertahap.
- **Pengajuan Izin**: Formulir permohonan izin (Sakit, Dispensasi, Keperluan) dengan catatan.
- **Riwayat Real-Time**: Daftar riwayat kehadiran lengkap dengan status waktu dan foto bukti.
