<?php
// ============================================
// Smart Attendance System - Database Config
// ============================================

// Deteksi otomatis environment (Localhost vs Hosting Online)
$isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1'])
           || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost:') === 0;

if ($isLocal) {
    // Konfigurasi Lokal (XAMPP)
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'absensi_db');
} else {
    // ============================================
    // Konfigurasi Hosting InfinityFree (Live Server)
    // Silakan isi sesuai info dari Control Panel InfinityFree:
    // ============================================
    define('DB_HOST', 'sql102.infinityfree.com');
    define('DB_USER', 'if0_43299804');
    define('DB_PASS', 'UbsswIAaeUu');
    define('DB_NAME', 'if0_43299804_absensi_db');
}

// Timezone WIB – wajib diset agar date() menggunakan waktu Indonesia
date_default_timezone_set('Asia/Jakarta');

// Header CORS untuk akses dari mobile app
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Koneksi database gagal: ' . $conn->connect_error
        ]);
        exit();
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

function sendResponse($status, $message, $data = null) {
    $response = ['status' => $status, 'message' => $message];
    if ($data !== null) $response['data'] = $data;
    echo json_encode($response);
    exit();
}
?>
