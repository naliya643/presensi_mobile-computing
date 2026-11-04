# Frontend Simulasi – Smart Attendance

Folder ini adalah simulasi web (mobile-first) untuk menguji sistem absensi
sebelum diimplementasikan di Kodular.

## Cara Akses

Pastikan XAMPP berjalan, lalu buka di browser:

```
http://localhost/absensi/frontend/index.html   ← Halaman utama (login + absensi)
http://localhost/absensi/frontend/admin.html   ← Rekap data absensi
```

## Fitur

| Fitur | Keterangan |
|---|---|
| Login | NIM + password (MD5), sesuai API login.php |
| Deteksi GPS | Menggunakan Geolocation API browser |
| Peta Leaflet | Menampilkan posisi Anda & kampus + radius 100m |
| Kamera Selfie | Buka kamera langsung dari browser (getUserMedia) |
| Upload Foto | Alternatif pilih foto dari galeri |
| Absensi | POST ke absensi.php dengan koordinat + foto base64 |
| Riwayat | GET dari riwayat.php, tampil 30 data terakhir |
| Jam Real-time | Update setiap detik |

## Catatan Penting

1. **Koordinat kampus** – Sesuaikan `KAMPUS_LAT` dan `KAMPUS_LNG` di `js/app.js`
   agar sama dengan nilai di `api/absensi.php`.

2. **HTTPS untuk kamera & GPS** – Browser modern memerlukan HTTPS atau `localhost`
   untuk mengakses kamera dan GPS. Di XAMPP lokal (`localhost`) sudah aman.

3. **Izin browser** – Saat pertama kali, browser akan meminta izin lokasi dan kamera.
   Klik "Izinkan".

4. **Simulasi lokasi** – Jika ingin menguji tanpa berada di kampus, gunakan
   DevTools Chrome → tab Sensors → Override geolocation, masukkan koordinat kampus.

## Struktur File

```
frontend/
├── index.html      ← Halaman utama (login + absensi + riwayat)
├── admin.html      ← Halaman rekap admin
├── css/
│   └── style.css   ← Semua styling (mobile-first)
└── js/
    └── app.js      ← Logika: login, GPS, kamera, absensi, riwayat
```
