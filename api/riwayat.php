<?php
// ============================================
// Smart Attendance System - API Riwayat Absensi
// ============================================
require_once 'koneksi.php';

$userId = (int)($_GET['user_id'] ?? 0);
if (!$userId) {
    sendResponse('error', 'Parameter user_id diperlukan');
}

$conn = getConnection();

// Auto-migrate catatan column if not exists
$chkCatatan = $conn->query("SHOW COLUMNS FROM `absensi` LIKE 'catatan'");
if ($chkCatatan && $chkCatatan->num_rows === 0) {
    $conn->query("ALTER TABLE `absensi` ADD COLUMN `catatan` TEXT NULL AFTER `status_pulang`");
}

$stmt = $conn->prepare("
    SELECT
        a.id,
        a.latitude,
        a.longitude,
        a.foto,
        a.waktu,
        a.status,
        a.waktu_pulang,
        a.foto_pulang,
        a.status_pulang,
        a.catatan,
        u.nama,
        u.nim
    FROM absensi a
    JOIN users u ON a.user_id = u.id
    WHERE a.user_id = ?
    ORDER BY a.waktu DESC
    LIMIT 30
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
$conn->close();

sendResponse('success', 'Data riwayat absensi', $data);
?>
