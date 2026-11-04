<?php
// ============================================
// Smart Attendance System - Upload Foto
// ============================================
require_once 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Method tidak diizinkan');
}

$input  = json_decode(file_get_contents('php://input'), true);
$base64 = $input['foto'] ?? '';

if (empty($base64)) {
    sendResponse('error', 'Data foto kosong');
}

// Bersihkan header base64 data URI jika ada
$imageData = preg_replace('#^data:image/\w+;base64,#i', '', $base64);
$decoded   = base64_decode($imageData);

if ($decoded === false) {
    sendResponse('error', 'Format base64 tidak valid');
}

// Validasi ukuran (maks 5MB)
if (strlen($decoded) > 5 * 1024 * 1024) {
    sendResponse('error', 'Ukuran foto terlalu besar (maks 5MB)');
}

$uploadDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$filename = 'selfie_' . uniqid() . '.jpg';
$filepath = $uploadDir . $filename;

if (file_put_contents($filepath, $decoded) === false) {
    sendResponse('error', 'Gagal menyimpan foto ke server');
}

sendResponse('success', 'Foto berhasil diupload', [
    'path'     => 'uploads/' . $filename,
    'filename' => $filename,
    'size'     => strlen($decoded)
]);
?>
