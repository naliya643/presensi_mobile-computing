<?php
// ============================================
// Smart Attendance System - API Registrasi Mahasiswa
// ============================================
require_once 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Method tidak diizinkan');
}

// Ambil raw body
$rawBody = file_get_contents('php://input');

// Coba parse sebagai JSON
$jsonInput = json_decode($rawBody, true);

// Coba parse sebagai query string
$queryInput = [];
parse_str(ltrim($rawBody, '?'), $queryInput);

// Prioritas: JSON → $_POST → query string
$nama     = trim($jsonInput['nama']     ?? $_POST['nama']     ?? $queryInput['nama']     ?? '');
$nim      = trim($jsonInput['nim']      ?? $_POST['nim']      ?? $queryInput['nim']      ?? '');
$password = trim($jsonInput['password'] ?? $_POST['password'] ?? $queryInput['password'] ?? '');

if (empty($nama) || empty($nim) || empty($password)) {
    sendResponse('error', 'Nama lengkap, NIM, dan kata sandi wajib diisi');
}

if (strlen($nim) < 3) {
    sendResponse('error', 'NIM minimal terdiri dari 3 karakter');
}

if (strlen($password) < 4) {
    sendResponse('error', 'Kata sandi minimal terdiri dari 4 karakter');
}

$conn = getConnection();

// Cek apakah NIM sudah terdaftar
$cek = $conn->prepare("SELECT id FROM users WHERE nim = ? LIMIT 1");
$cek->bind_param('s', $nim);
$cek->execute();
$resCek = $cek->get_result();

if ($resCek && $resCek->num_rows > 0) {
    $cek->close();
    $conn->close();
    sendResponse('error', "NIM '$nim' sudah terdaftar dalam sistem. Silakan langsung masuk.");
}
$cek->close();

// Simpan password dengan hash modern bcrypt
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users (nama, nim, password) VALUES (?, ?, ?)");
$stmt->bind_param('sss', $nama, $nim, $hashedPassword);

if ($stmt->execute()) {
    $newId = $stmt->insert_id;
    $stmt->close();
    $conn->close();

    sendResponse('success', 'Pendaftaran akun mahasiswa berhasil!', [
        'user_id' => $newId,
        'nama'    => $nama,
        'nim'     => $nim
    ]);
} else {
    $err = $stmt->error;
    $stmt->close();
    $conn->close();
    sendResponse('error', 'Gagal mendaftarkan akun: ' . $err);
}
?>

