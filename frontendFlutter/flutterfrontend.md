# Tutorial Flutter Frontend - Smart Attendance
### Panduan Lengkap: Instalasi VS Code, Flutter, hingga Menjalankan Aplikasi di HP

---

## Daftar Isi
1. [Struktur Proyek](#1-struktur-proyek)
2. [Penjelasan Setiap File](#2-penjelasan-setiap-file)
3. [Instalasi VS Code](#3-instalasi-vs-code)
4. [Instalasi Flutter SDK](#4-instalasi-flutter-sdk)
5. [Instalasi Android Studio & SDK](#5-instalasi-android-studio--sdk)
6. [Konfigurasi Flutter di VS Code](#6-konfigurasi-flutter-di-vs-code)
7. [Setup Proyek Flutter](#7-setup-proyek-flutter)
8. [Konfigurasi IP Server](#8-konfigurasi-ip-server)
9. [Konfigurasi Android](#9-konfigurasi-android)
10. [Menjalankan Aplikasi](#10-menjalankan-aplikasi)
11. [Build APK](#11-build-apk)
12. [Troubleshooting](#12-troubleshooting)
13. [Alur Kerja Aplikasi](#13-alur-kerja-aplikasi)
14. [Akun Demo](#14-akun-demo)

---

## 1. Struktur Proyek

```
frontendFlutter/
|
+-- pubspec.yaml                 <- Konfigurasi proyek & dependencies
+-- flutterfrontend.md           <- File tutorial ini
|
+-- android/
|   +-- app/src/main/
|       +-- AndroidManifest.xml  <- Izin Android (internet, GPS, kamera)
|
+-- lib/
    +-- main.dart                <- Entry point, inisialisasi app
    |
    +-- config/
    |   +-- api_config.dart      <- URL endpoint API (GANTI IP di sini)
    |
    +-- models/
    |   +-- user_model.dart      <- Model data user
    |   +-- absensi_model.dart   <- Model data absensi
    |
    +-- services/
    |   +-- auth_service.dart    <- Login & manajemen sesi
    |   +-- absensi_service.dart <- Kirim absensi & ambil riwayat
    |   +-- settings_service.dart<- Ambil koordinat kampus dari API
    |
    +-- screens/
        +-- login_screen.dart    <- Halaman login
        +-- home_screen.dart     <- Container tab utama + auto-refresh riwayat
        +-- absensi_screen.dart  <- Halaman absensi (GPS + Kamera + Peta)
        +-- riwayat_screen.dart  <- Halaman riwayat absensi
```

---

## 2. Penjelasan Setiap File

### main.dart - Entry Point
- Titik masuk aplikasi Flutter
- Mengecek apakah user sudah login via SharedPreferences
- Jika sudah login langsung ke HomeScreen, jika belum ke LoginScreen
- Mendefinisikan tema global (warna, font, style tombol)

### config/api_config.dart - Konfigurasi API
- Menyimpan semua URL endpoint API di satu tempat
- WAJIB diubah sesuai IP komputer yang menjalankan XAMPP
- Mengubah di sini otomatis berlaku untuk seluruh aplikasi

### models/user_model.dart - Model User
- Representasi data user: userId, nama, nim
- Method fromJson() untuk parse response API login
- Method toJson() untuk simpan ke SharedPreferences

### models/absensi_model.dart - Model Absensi
- Representasi satu record absensi
- Field: id, latitude, longitude, foto, waktu, status, nama, nim
- Property isHadir untuk cek apakah status hadir

### services/auth_service.dart - Service Login
- login(nim, password) -> kirim POST ke login.php, simpan sesi
- getSesi() -> ambil data user dari SharedPreferences
- logout() -> hapus semua data sesi

### services/absensi_service.dart - Service Absensi
- kirimAbsensi(...) -> encode foto ke Base64, kirim POST ke absensi.php
- getRiwayat(userId) -> GET dari riwayat.php

### services/settings_service.dart - Service Settings
- getSettings() -> ambil koordinat kampus dari API settings.php
- Sinkron otomatis dengan pengaturan di admin panel
- Jika gagal, gunakan nilai default

### screens/login_screen.dart - Halaman Login
- Form NIM + Password dengan validasi
- Tombol show/hide password
- Loading indicator saat proses login
- Navigasi ke HomeScreen setelah berhasil

### screens/home_screen.dart - Halaman Utama
- AppBar menampilkan nama & NIM user
- BottomNavigationBar dengan 2 tab: Absensi & Riwayat
- IndexedStack mempertahankan state setiap tab
- GlobalKey untuk trigger refresh RiwayatScreen dari AbsensiScreen
- Riwayat auto-refresh saat tab dibuka atau setelah absensi berhasil
- Tombol logout dengan konfirmasi dialog

### screens/absensi_screen.dart - Halaman Absensi
- Jam real-time update setiap detik
- Koordinat kampus diambil dari API settings (sinkron dengan admin panel)
- Peta OpenStreetMap dengan marker kampus & posisi user
- Lingkaran radius di sekitar kampus
- GPS dengan request izin otomatis
- Kamera/Galeri untuk foto selfie
- Callback onAbsensiSuccess untuk trigger refresh riwayat
- Semua icon menggunakan Flutter Icons (tidak ada emoji)

### screens/riwayat_screen.dart - Halaman Riwayat
- State class dibuat public (RiwayatScreenState) untuk akses via GlobalKey
- Daftar 30 absensi terakhir
- Foto thumbnail dari server
- Badge status HADIR/TERLAMBAT dengan icon Flutter
- Pull-to-refresh untuk muat ulang data
- Auto-refresh dipanggil dari HomeScreen

---
## 3. Instalasi VS Code

VS Code adalah editor kode yang ringan dan sangat cocok untuk pengembangan Flutter.

### Langkah 1 - Download VS Code

1. Buka browser dan kunjungi: https://code.visualstudio.com
2. Klik tombol biru **Download for Windows**
3. File installer akan terunduh (nama file: VSCodeUserSetup-x64-x.xx.x.exe)

### Langkah 2 - Install VS Code

1. Buka file installer yang sudah diunduh
2. Klik **Next** pada halaman License Agreement setelah membaca
3. Pilih folder instalasi (biarkan default: C:\Users\NamaAnda\AppData\Local\Programs\Microsoft VS Code)
4. Klik **Next**
5. Pada halaman Select Additional Tasks, centang semua opsi berikut:
   - Add "Open with Code" action to Windows Explorer file context menu
   - Add "Open with Code" action to Windows Explorer directory context menu
   - Register Code as an editor for supported file types
   - Add to PATH (important)
6. Klik **Next** lalu **Install**
7. Tunggu proses instalasi selesai
8. Klik **Finish** - VS Code akan terbuka otomatis

### Langkah 3 - Verifikasi VS Code

Buka Command Prompt dan ketik:
```
code --version
```
Jika muncul nomor versi, VS Code sudah terinstall dengan benar.

---

## 4. Instalasi Flutter SDK

### Langkah 1 - Download Flutter SDK

1. Buka browser dan kunjungi: https://flutter.dev/docs/get-started/install/windows
2. Klik tombol **Download Flutter SDK** (file .zip sekitar 1 GB)
3. Tunggu proses download selesai

### Langkah 2 - Ekstrak Flutter SDK

1. Buat folder baru di drive C: dengan nama **flutter**
   - Buka File Explorer
   - Klik drive C:
   - Klik kanan di area kosong -> New -> Folder
   - Beri nama: flutter
2. Ekstrak file .zip yang sudah diunduh ke folder C:\flutter
   - Klik kanan file .zip -> Extract All
   - Pilih lokasi: C:\
   - Klik Extract
3. Setelah selesai, struktur folder akan menjadi: C:\flutter\flutter\bin\flutter.bat

   PENTING: Pastikan path-nya C:\flutter\flutter\ bukan C:\flutter\flutter\flutter\

### Langkah 3 - Tambahkan Flutter ke PATH

PATH adalah daftar folder yang dicari Windows saat menjalankan perintah di terminal.

1. Klik kanan ikon **Windows** di taskbar -> **System**
2. Klik **Advanced system settings** di panel kiri
3. Klik tombol **Environment Variables** di bagian bawah
4. Di bagian **User variables** (atas), cari variabel **Path** dan klik dua kali
5. Klik tombol **New**
6. Ketik: C:\flutter\flutter\bin
7. Klik **OK** -> **OK** -> **OK**

### Langkah 4 - Verifikasi Flutter

1. Tutup semua jendela Command Prompt yang terbuka
2. Buka Command Prompt baru (tekan Windows+R, ketik cmd, Enter)
3. Ketik perintah berikut:
```
flutter --version
```
Output yang diharapkan:
```
Flutter 3.x.x channel stable
Framework revision xxxxxxxx
Engine revision xxxxxxxx
Dart 3.x.x
```

Jika muncul pesan "flutter is not recognized", berarti PATH belum benar. Ulangi Langkah 3.

### Langkah 5 - Jalankan Flutter Doctor

Flutter Doctor akan mengecek semua komponen yang diperlukan:
```
flutter doctor
```

Contoh output awal (beberapa item mungkin belum centang):
```
Doctor summary:
[v] Flutter (Channel stable, 3.x.x)
[x] Android toolchain - develop for Android devices
[x] Android Studio (not installed)
[v] VS Code (version x.x.x)
[!] Connected device
```

Jangan khawatir jika ada tanda [x], kita akan perbaiki di langkah berikutnya.

---

## 5. Instalasi Android Studio & SDK

Android Studio diperlukan untuk menyediakan Android SDK dan emulator.

### Langkah 1 - Download Android Studio

1. Buka browser dan kunjungi: https://developer.android.com/studio
2. Klik tombol **Download Android Studio**
3. Centang kotak persetujuan dan klik **Download Android Studio for Windows**
4. File installer akan terunduh (sekitar 1 GB)

### Langkah 2 - Install Android Studio

1. Buka file installer yang sudah diunduh
2. Klik **Next** pada halaman Welcome
3. Pastikan komponen berikut dicentang:
   - Android Studio
   - Android Virtual Device
4. Klik **Next**
5. Pilih folder instalasi (biarkan default)
6. Klik **Next** -> **Install**
7. Tunggu proses instalasi (bisa 5-10 menit)
8. Klik **Finish** - Android Studio akan terbuka

### Langkah 3 - Setup Android Studio Pertama Kali

1. Saat Android Studio pertama kali dibuka, pilih **Do not import settings** -> OK
2. Pada halaman Welcome, klik **Next**
3. Pilih **Standard** installation type -> **Next**
4. Pilih tema UI (Light atau Dark sesuai selera) -> **Next**
5. Klik **Next** untuk konfirmasi pengaturan
6. Klik **Finish** - Android Studio akan mengunduh komponen tambahan
7. Tunggu proses download selesai (bisa 10-20 menit tergantung koneksi)
8. Klik **Finish**

### Langkah 4 - Install Android SDK

1. Di Android Studio, klik **More Actions** -> **SDK Manager**
   (atau dari menu Tools -> SDK Manager)
2. Di tab **SDK Platforms**, centang:
   - Android 14.0 (API 34) - direkomendasikan
   - Android 10.0 (API 29) - untuk kompatibilitas HP lama
3. Di tab **SDK Tools**, pastikan tercentang:
   - Android SDK Build-Tools
   - Android SDK Command-line Tools
   - Android Emulator
   - Android SDK Platform-Tools
4. Klik **Apply** -> **OK** untuk mengunduh
5. Tunggu proses download selesai

### Langkah 5 - Terima Android Licenses

Buka Command Prompt dan jalankan:
```
flutter doctor --android-licenses
```
Ketik **y** dan tekan Enter untuk setiap pertanyaan yang muncul (biasanya 5-7 pertanyaan).

### Langkah 6 - Verifikasi

Jalankan flutter doctor lagi:
```
flutter doctor
```
Sekarang Android toolchain seharusnya sudah centang hijau:
```
[v] Flutter
[v] Android toolchain - develop for Android devices
[v] Android Studio
[v] VS Code
```

---

## 6. Konfigurasi Flutter di VS Code

### Langkah 1 - Install Extension Flutter

1. Buka VS Code
2. Klik ikon Extensions di sidebar kiri (ikon 4 kotak) atau tekan Ctrl+Shift+X
3. Di kotak pencarian, ketik: **Flutter**
4. Klik extension **Flutter** yang dibuat oleh **Dart Code** (bukan yang lain)
5. Klik tombol **Install**
6. Extension Dart akan terinstall otomatis bersamaan

### Langkah 2 - Verifikasi Extension

1. Tekan Ctrl+Shift+P untuk membuka Command Palette
2. Ketik: **Flutter: New Project**
3. Jika muncul opsi tersebut, extension sudah terinstall dengan benar

### Langkah 3 - Konfigurasi SDK Path (jika diperlukan)

Jika VS Code tidak mendeteksi Flutter otomatis:
1. Tekan Ctrl+Shift+P
2. Ketik: **Flutter: Change SDK**
3. Pilih folder: C:\flutter\flutter
4. Restart VS Code

### Langkah 4 - Install Extension Tambahan (Opsional tapi Berguna)

Cari dan install extension berikut di VS Code:
- **Dart** - syntax highlighting untuk Dart
- **Error Lens** - tampilkan error langsung di baris kode
- **Pubspec Assist** - membantu mengelola dependencies
- **Flutter Widget Snippets** - shortcut untuk widget Flutter

---

## 7. Setup Proyek Flutter

### Langkah 1 - Buat Proyek Flutter Baru

PENTING: Proyek Flutter TIDAK BISA dijalankan dari dalam folder htdocs XAMPP.
Buat proyek di lokasi terpisah.

Buka Command Prompt dan jalankan:
```
flutter create --org com.absensi --project-name absensi_app C:\Projects\absensi_flutter
```

Tunggu hingga selesai. Output yang diharapkan:
```
Creating project C:\Projects\absensi_flutter...
Got dependencies!
Wrote 131 files.
All done!
```

### Langkah 2 - Buka Proyek di VS Code

1. Buka VS Code
2. Klik **File** -> **Open Folder**
3. Navigasi ke C:\Projects\absensi_flutter
4. Klik **Select Folder**
5. VS Code akan membuka proyek Flutter

### Langkah 3 - Salin File Kode

Salin semua file dari folder frontendFlutter ke proyek baru.
Buka terminal di VS Code (Ctrl+`) dan jalankan:

```
# Buat folder yang diperlukan
mkdir lib\config
mkdir lib\models
mkdir lib\services
mkdir lib\screens

# Salin semua file (ganti path sesuai lokasi XAMPP Anda)
copy C:\xampp\htdocs\absensi\frontendFlutter\lib\main.dart lib\main.dart
copy C:\xampp\htdocs\absensi\frontendFlutter\lib\config\api_config.dart lib\config\
copy C:\xampp\htdocs\absensi\frontendFlutter\lib\models\*.dart lib\models\
copy C:\xampp\htdocs\absensi\frontendFlutter\lib\services\*.dart lib\services\
copy C:\xampp\htdocs\absensi\frontendFlutter\lib\screens\*.dart lib\screens\
```

### Langkah 4 - Update pubspec.yaml

Buka file pubspec.yaml di VS Code dan ganti seluruh isinya dengan:

```yaml
name: absensi_app
description: Aplikasi Absensi Smart Attendance
publish_to: 'none'
version: 1.0.0+1

environment:
  sdk: '>=3.0.0 <4.0.0'

dependencies:
  flutter:
    sdk: flutter
  http: ^1.2.1
  geolocator: ^12.0.0
  image_picker: ^1.1.2
  flutter_map: ^7.0.2
  latlong2: ^0.9.1
  shared_preferences: ^2.3.2
  cupertino_icons: ^1.0.8

dev_dependencies:
  flutter_test:
    sdk: flutter
  flutter_lints: ^4.0.0

flutter:
  uses-material-design: true
```

Simpan file (Ctrl+S).

### Langkah 5 - Install Dependencies

Di terminal VS Code, jalankan:
```
flutter pub get
```

Output yang diharapkan:
```
Resolving dependencies...
Got dependencies!
```

### Langkah 6 - Update minSdk Android

Buka file android\app\build.gradle.kts dan cari baris:
```
minSdk = flutter.minSdkVersion
```
Ganti menjadi:
```
minSdk = 21
```
Simpan file.

### Langkah 7 - Update AndroidManifest.xml

Buka file android\app\src\main\AndroidManifest.xml dan ganti seluruh isinya dengan isi dari file:
C:\xampp\htdocs\absensi\frontendFlutter\android\app\src\main\AndroidManifest.xml

File tersebut sudah berisi semua izin yang diperlukan (internet, GPS, kamera).

### Langkah 8 - Verifikasi Kode

Di terminal VS Code, jalankan:
```
flutter analyze
```

Output yang diharapkan:
```
No issues found!
```

---
## 8. Konfigurasi IP Server

Aplikasi Flutter perlu tahu alamat IP komputer yang menjalankan XAMPP.

### Langkah 1 - Cari IP Komputer

1. Pastikan komputer terhubung ke WiFi yang sama dengan HP
2. Buka Command Prompt
3. Ketik perintah:
```
ipconfig
```
4. Cari bagian **Wireless LAN adapter Wi-Fi** (bukan Ethernet)
5. Catat nilai **IPv4 Address**, contoh: 192.168.0.170

Contoh output ipconfig:
```
Wireless LAN adapter Wi-Fi:
   Connection-specific DNS Suffix  . :
   IPv4 Address. . . . . . . . . . . : 192.168.0.170
   Subnet Mask . . . . . . . . . . . : 255.255.255.0
   Default Gateway . . . . . . . . . : 192.168.0.1
```

### Langkah 2 - Edit File Konfigurasi

1. Di VS Code, buka file: lib\config\api_config.dart
2. Ubah IP sesuai hasil ipconfig:

```dart
class ApiConfig {
  // GANTI IP INI dengan IP komputer Anda
  static const String baseUrl = 'http://192.168.0.170/absensi/api';

  static const String login    = '$baseUrl/login.php';
  static const String absensi  = '$baseUrl/absensi.php';
  static const String riwayat  = '$baseUrl/riwayat.php';
  static const String settings = '$baseUrl/settings.php';

  // GANTI IP INI juga (untuk tampilkan foto)
  static const String fotoBase = 'http://192.168.0.170/absensi';
}
```

3. Simpan file (Ctrl+S)

### Langkah 3 - Test Koneksi dari HP

Sebelum menjalankan aplikasi, test dulu dari browser HP:
1. Pastikan HP terhubung ke WiFi yang sama dengan komputer
2. Buka browser di HP (Chrome atau Firefox)
3. Ketik di address bar: http://192.168.0.170/absensi/api/login.php
4. Jika muncul teks JSON seperti ini, server bisa diakses:
```json
{"status":"error","message":"Method tidak diizinkan"}
```
5. Jika tidak bisa dibuka, cek bagian Troubleshooting

### Langkah 4 - Izinkan Apache di Firewall Windows

Jika HP tidak bisa mengakses server, kemungkinan Windows Firewall memblokir.

1. Buka **Windows Defender Firewall** (cari di Start Menu)
2. Klik **Advanced settings** di panel kiri
3. Klik **Inbound Rules** di panel kiri
4. Klik **New Rule** di panel kanan
5. Pilih **Port** -> klik **Next**
6. Pilih **TCP**, masukkan port: **80**
7. Klik **Next**
8. Pilih **Allow the connection** -> **Next**
9. Centang semua (Domain, Private, Public) -> **Next**
10. Beri nama: **XAMPP Apache HTTP** -> **Finish**

Coba test lagi dari browser HP.

---

## 9. Konfigurasi Android

### File AndroidManifest.xml

File ini mengatur izin yang diminta aplikasi ke pengguna HP.
Lokasi: android\app\src\main\AndroidManifest.xml

Izin yang sudah dikonfigurasi:

| Izin | Fungsi |
|------|--------|
| INTERNET | HTTP request ke API XAMPP |
| ACCESS_FINE_LOCATION | GPS presisi tinggi untuk absensi |
| ACCESS_COARSE_LOCATION | GPS kasar sebagai fallback |
| CAMERA | Akses kamera untuk foto selfie |
| READ_EXTERNAL_STORAGE | Akses galeri foto (Android 12 ke bawah) |
| READ_MEDIA_IMAGES | Akses galeri foto (Android 13 ke atas) |

Pengaturan penting:
- android:usesCleartextTraffic="true" - WAJIB untuk HTTP ke server lokal
  Tanpa ini, Android 9+ akan memblokir koneksi HTTP

### Minimum SDK

File android\app\build.gradle.kts mengatur versi Android minimum:
- minSdk = 21 berarti aplikasi bisa diinstall di Android 5.0 ke atas
- HP dengan Android di bawah 5.0 tidak bisa menjalankan aplikasi ini

### Aktifkan USB Debugging di HP

Sebelum menjalankan aplikasi ke HP fisik:

1. Buka Pengaturan di HP
2. Cari menu Tentang Ponsel atau About Phone
3. Cari Nomor Build atau Build Number
4. Ketuk Nomor Build sebanyak 7 kali berturut-turut
5. Akan muncul pesan "You are now a developer"
6. Kembali ke Pengaturan utama
7. Cari menu Opsi Pengembang atau Developer Options
8. Aktifkan toggle di bagian atas
9. Cari USB Debugging dan aktifkan
10. Hubungkan HP ke komputer via kabel USB
11. Di HP akan muncul dialog "Allow USB debugging?" -> klik OK

---

## 10. Menjalankan Aplikasi

### Opsi A - Menggunakan HP Fisik (Direkomendasikan)

HP fisik memberikan pengalaman yang lebih nyata termasuk GPS dan kamera yang sesungguhnya.

**Persiapan:**
- HP sudah aktifkan USB Debugging (lihat bagian 9)
- Kabel USB terhubung ke komputer
- XAMPP Apache sudah Running

**Langkah 1 - Cek HP Terdeteksi**

Di terminal VS Code, jalankan:
```
flutter devices
```

Output yang diharapkan:
```
Found 2 connected devices:
NamaHP (mobile)  XXXXXXXX  android-arm64  Android 10
Windows (desktop)  windows  windows-x64  Microsoft Windows
```

Jika HP tidak muncul:
- Coba cabut dan pasang kembali kabel USB
- Pastikan driver USB HP sudah terinstall
- Coba kabel USB yang berbeda

**Langkah 2 - Jalankan Aplikasi**

Di terminal VS Code:
```
flutter run
```

Proses pertama kali bisa memakan waktu 3-10 menit karena Gradle perlu mengunduh dependencies.
Selanjutnya akan lebih cepat (30-60 detik).

Output saat berhasil:
```
Launching lib\main.dart on NamaHP in debug mode...
Running Gradle task 'assembleDebug'...
Built build\app\outputs\flutter-apk\app-debug.apk
Installing build\app\outputs\flutter-apk\app-debug.apk...
Syncing files to device NamaHP...
Flutter run key commands.
r Hot reload.
R Hot restart.
q Quit.
```

Aplikasi akan otomatis terbuka di HP.

**Langkah 3 - Gunakan Hot Reload**

Saat aplikasi berjalan, Anda bisa mengubah kode dan melihat hasilnya langsung:

| Tombol di Terminal | Fungsi |
|-------------------|--------|
| r | Hot Reload - update UI tanpa restart, state tetap |
| R | Hot Restart - restart aplikasi, state direset |
| q | Keluar dari mode debug |
| d | Detach - keluar terminal tapi aplikasi tetap jalan |

---

### Opsi B - Menggunakan Emulator Android

Emulator berguna jika tidak punya HP atau ingin test di versi Android tertentu.

**Langkah 1 - Buat Emulator**

1. Buka Android Studio
2. Klik More Actions -> Virtual Device Manager
   (atau dari menu Tools -> Device Manager)
3. Klik tombol + Create Device
4. Pilih kategori Phone
5. Pilih Pixel 6 -> klik Next
6. Pilih System Image: API 33 (Android 13)
   - Jika belum ada, klik Download di sebelah API 33
   - Tunggu download selesai
7. Klik Next -> Finish

**Langkah 2 - Jalankan Emulator**

1. Di Device Manager, klik tombol Play (segitiga) di sebelah emulator
2. Tunggu emulator booting (bisa 1-3 menit pertama kali)
3. Emulator siap saat muncul layar home Android

**Langkah 3 - Simulasi GPS di Emulator**

GPS di emulator perlu disimulasikan manual:
1. Di Android Studio, klik ikon titik tiga (...) di toolbar emulator
2. Pilih Location
3. Masukkan koordinat kampus:
   - Latitude: -6.561539 (sesuaikan dengan settings admin)
   - Longitude: 106.806535
4. Klik Set Location

**Langkah 4 - Jalankan ke Emulator**

Di terminal VS Code:
```
flutter run
```

Flutter akan otomatis mendeteksi emulator yang berjalan.

CATATAN: Emulator menggunakan jaringan komputer, jadi bisa mengakses
localhost. Tapi tetap gunakan IP WiFi agar konsisten dengan HP fisik.

---

### Menjalankan Ulang Setelah Perubahan Kode

Setiap kali mengubah kode:
1. Simpan file (Ctrl+S)
2. Di terminal yang sedang menjalankan flutter run, tekan r untuk Hot Reload
3. Jika perubahan tidak terlihat, tekan R untuk Hot Restart

---

## 11. Build APK

APK adalah file instalasi aplikasi Android yang bisa dibagikan ke orang lain.

### Build APK Debug (untuk testing)

APK debug berukuran lebih besar tapi mudah diinstall:
```
flutter build apk --debug
```

Lokasi file: build\app\outputs\flutter-apk\app-debug.apk

### Build APK Release (untuk distribusi)

APK release lebih kecil dan lebih cepat:
```
flutter build apk --release
```

Lokasi file: build\app\outputs\flutter-apk\app-release.apk

### Install APK ke HP via USB

Pastikan HP terhubung via USB, lalu:
```
flutter install
```

### Bagikan APK ke HP Lain

1. Salin file .apk ke HP via:
   - Kabel USB (copy ke folder Downloads HP)
   - WhatsApp (kirim sebagai dokumen)
   - Email
   - Google Drive

2. Di HP penerima, buka file .apk
3. Jika muncul peringatan "Install unknown apps":
   - Klik Settings
   - Aktifkan Allow from this source
   - Kembali dan install

---

## 12. Troubleshooting

### flutter: command not found
Penyebab: Flutter belum ditambahkan ke PATH
Solusi:
1. Pastikan folder C:\flutter\flutter\bin ada
2. Ulangi langkah menambahkan PATH di bagian 4
3. Tutup dan buka ulang Command Prompt

---

### Gradle build failed
Penyebab: Cache Gradle rusak atau dependencies bermasalah
Solusi:
```
cd android
gradlew clean
cd ..
flutter clean
flutter pub get
flutter run
```

---

### Connection refused atau SocketException
Penyebab: HP tidak bisa terhubung ke server XAMPP
Checklist:
1. Apakah XAMPP Apache sudah Running (hijau)?
2. Apakah IP di api_config.dart sudah benar?
3. Apakah HP dan komputer terhubung ke WiFi yang sama?
4. Apakah Windows Firewall memblokir port 80?
   -> Ikuti langkah di bagian 8 untuk membuka port 80

---

### No route to host (errno 113)
Penyebab: Paket jaringan tidak sampai ke server
Solusi:
1. Cek IP komputer dengan ipconfig (IP bisa berubah setiap konek WiFi)
2. Update IP di api_config.dart
3. Buka port 80 di Windows Firewall

---

### Cleartext HTTP traffic not permitted
Penyebab: Android 9+ memblokir HTTP (non-HTTPS)
Solusi: Pastikan android:usesCleartextTraffic="true" ada di AndroidManifest.xml

---

### Location permission denied
Solusi:
1. Buka Pengaturan HP
2. Cari Aplikasi -> Smart Attendance
3. Izin -> Lokasi -> Izinkan saat menggunakan aplikasi

---

### Camera permission denied
Solusi:
1. Buka Pengaturan HP
2. Cari Aplikasi -> Smart Attendance
3. Izin -> Kamera -> Izinkan

---

### Peta tidak muncul (layar putih)
Penyebab: HP tidak ada koneksi internet untuk tile OpenStreetMap
Solusi: Pastikan HP terhubung ke internet (WiFi dengan akses internet)

---

### MissingPluginException
Penyebab: Plugin belum terdaftar setelah flutter pub get
Solusi:
```
flutter clean
flutter pub get
flutter run
```

---

### Build failed: minSdkVersion too low
Penyebab: minSdk masih menggunakan nilai default Flutter yang terlalu rendah
Solusi: Buka android\app\build.gradle.kts dan ubah:
```
minSdk = 21
```

---

### HP tidak terdeteksi oleh flutter devices
Solusi:
1. Pastikan USB Debugging sudah aktif di HP
2. Cabut dan pasang kembali kabel USB
3. Di HP, pilih mode USB: File Transfer (MTP)
4. Install driver USB HP (cari di website produsen HP)
5. Coba kabel USB yang berbeda

---

## 13. Alur Kerja Aplikasi

```
ALUR LOGIN
----------
Buka App
   |
   +-- Cek SharedPreferences
         |
         +-- Ada sesi (user_id > 0) --> HomeScreen
         |
         +-- Tidak ada sesi ---------> LoginScreen
                                           |
                                       Input NIM + Password
                                           |
                                       POST ke login.php
                                           |
                                       Berhasil --> Simpan sesi --> HomeScreen
                                           |
                                       Gagal ----> Tampilkan error


ALUR ABSENSI
------------
AbsensiScreen dibuka
   |
   +-- Load settings dari API (koordinat kampus)
   |
   +-- Minta izin GPS
   |
   +-- Ambil posisi GPS
   |
   +-- Hitung jarak ke kampus (Haversine Formula)
   |
   +-- Tampilkan peta + marker kampus + marker user
   |
   +-- User ambil foto (kamera/galeri)
   |
   +-- User tekan tombol Lakukan Absensi
         |
         +-- Validasi: lokasi ada? foto ada?
         |
         +-- Encode foto ke Base64
         |
         +-- POST ke absensi.php
             {user_id, latitude, longitude, foto}
                 |
                 +-- Server cek radius kampus
                 |
                 +-- Server cek jam (hadir/terlambat)
                 |
                 +-- Simpan ke database
                 |
                 +-- Return status + jarak + waktu
                         |
                         +-- Tampilkan dialog hasil
                         |
                         +-- Trigger refresh RiwayatScreen


ALUR RIWAYAT
------------
RiwayatScreen dibuka ATAU dipanggil dari HomeScreen
   |
   +-- GET riwayat.php?user_id=xxx
   |
   +-- Parse JSON -> List AbsensiModel
   |
   +-- Tampilkan ListView:
         - Foto thumbnail dari server
         - Tanggal & jam
         - Badge HADIR/TERLAMBAT
         - Koordinat GPS
   |
   +-- Pull-to-refresh untuk muat ulang
```

---

## 14. Akun Demo

Akun berikut sudah tersedia di database untuk testing:

| NIM | Password | Nama |
|-----|----------|------|
| 2021001 | 123456 | Ahmad Fauzi |
| 2021002 | 123456 | Siti Rahayu |
| 2021003 | 123456 | Budi Santoso |
| 2021004 | 123456 | Dewi Lestari |
| 2021005 | 123456 | Rizky Pratama |

---

## Ringkasan Perintah Penting

```
# Install dependencies
flutter pub get

# Cek perangkat yang terhubung
flutter devices

# Jalankan di mode debug
flutter run

# Build APK debug (untuk testing)
flutter build apk --debug

# Build APK release (untuk distribusi)
flutter build apk --release

# Install APK ke HP via USB
flutter install

# Bersihkan cache build
flutter clean

# Cek masalah kode
flutter analyze

# Cek semua komponen Flutter
flutter doctor
```

---

## Catatan Penting

1. IP server bisa berubah setiap kali komputer konek ke WiFi berbeda.
   Selalu cek dengan ipconfig dan update api_config.dart jika perlu.

2. Koordinat kampus diambil otomatis dari admin panel.
   Ubah di http://localhost/absensi/admin/ tab Pengaturan Lokasi.

3. Untuk production (bukan localhost), ganti HTTP ke HTTPS dan
   hapus android:usesCleartextTraffic="true" dari AndroidManifest.xml.

4. APK debug berukuran lebih besar (~50-100 MB) karena menyertakan
   debug tools. APK release jauh lebih kecil (~10-20 MB).

---

*Tutorial ini dibuat untuk keperluan pembelajaran sistem absensi digital berbasis Flutter.*
*Versi terakhir diperbarui: Mei 2026*
