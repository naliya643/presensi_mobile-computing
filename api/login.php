<?php
// ============================================
// Smart Attendance System - API Login
// ============================================
require_once 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Method tidak diizinkan');
}

// Ambil raw body
$rawBody = file_get_contents('php://input');

// Coba parse sebagai JSON
$jsonInput = json_decode($rawBody, true);

// Coba parse sebagai query string (format Kodular: ?nim=xxx&password=yyy atau nim=xxx&password=yyy)
$queryInput = [];
parse_str(ltrim($rawBody, '?'), $queryInput);

// Prioritas: JSON → $_POST (form-data) → query string di body
$nim      = trim($jsonInput['nim']      ?? $_POST['nim']      ?? $queryInput['nim']      ?? '');
$password = trim($jsonInput['password'] ?? $_POST['password'] ?? $queryInput['password'] ?? '');

if (empty($nim) || empty($password)) {
    sendResponse('error', 'NIM dan password wajib diisi');
}

$conn = getConnection();
$stmt = $conn->prepare(
    "SELECT id, nama, nim, password FROM users WHERE nim = ? LIMIT 1"
);
$stmt->bind_param('s', $nim);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    sendResponse('error', 'NIM atau password salah');
}

$user = $result->fetch_assoc();
$isPasswordValid = false;

if (password_verify($password, $user['password'])) {
    $isPasswordValid = true;
} elseif (md5($password) === $user['password']) {
    $isPasswordValid = true;
    // Upgrade otomatis hash MD5 lama ke modern BCRYPT
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $upd->bind_param('si', $newHash, $user['id']);
    $upd->execute();
    $upd->close();
}

if (!$isPasswordValid) {
    $conn->close();
    sendResponse('error', 'NIM atau password salah');
}

$conn->close();

sendResponse('success', 'Login berhasil', [
    'user_id' => (int)$user['id'],
    'nama'    => $user['nama'],
    'nim'     => $user['nim']
]);
?>
