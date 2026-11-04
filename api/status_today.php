<?php
// ============================================
// Smart Attendance System - API Status Hari Ini
// ============================================
require_once 'koneksi.php';

$userId = (int)($_GET['user_id'] ?? 0);
if (!$userId) {
    sendResponse('error', 'Parameter user_id diperlukan');
}

$conn = getConnection();

// Auto-migrate check-out and catatan columns if not exists
$chkCols = $conn->query("SHOW COLUMNS FROM `absensi` LIKE 'waktu_pulang'");
if ($chkCols && $chkCols->num_rows === 0) {
    $conn->query("ALTER TABLE `absensi` 
        ADD COLUMN `waktu_pulang` DATETIME NULL AFTER `status`,
        ADD COLUMN `latitude_pulang` DOUBLE NULL AFTER `waktu_pulang`,
        ADD COLUMN `longitude_pulang` DOUBLE NULL AFTER `latitude_pulang`,
        ADD COLUMN `foto_pulang` TEXT NULL AFTER `longitude_pulang`,
        ADD COLUMN `status_pulang` VARCHAR(20) DEFAULT NULL AFTER `foto_pulang`");
}

$chkCatatan = $conn->query("SHOW COLUMNS FROM `absensi` LIKE 'catatan'");
if ($chkCatatan && $chkCatatan->num_rows === 0) {
    $conn->query("ALTER TABLE `absensi` ADD COLUMN `catatan` TEXT NULL AFTER `status_pulang`");
}

$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT id, waktu, status, waktu_pulang, status_pulang, foto, foto_pulang, latitude, longitude, catatan FROM absensi WHERE user_id = ? AND DATE(waktu) = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param('is', $userId, $today);
$stmt->execute();
$res = $stmt->get_result();

if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $conn->close();
    $statusLower = strtolower($row['status'] ?? '');
    $isIzin = in_array($statusLower, ['izin', 'sakit', 'dispensasi']);

    sendResponse('success', 'Status presensi hari ini ditemukan', [
        'has_record'    => true,
        'absensi_id'    => (int)$row['id'],
        'is_izin'       => $isIzin,
        'catatan'       => $row['catatan'] ?? '',
        'has_masuk'     => !$isIzin,
        'waktu_masuk'   => $row['waktu'],
        'status_masuk'  => $row['status'],
        'has_pulang'    => !empty($row['waktu_pulang']),
        'waktu_pulang'  => $row['waktu_pulang'],
        'status_pulang' => $row['status_pulang'] ?? null,
        'foto_masuk'    => $row['foto'],
        'foto_pulang'   => $row['foto_pulang']
    ]);
} else {
    $conn->close();
    sendResponse('success', 'Belum ada presensi hari ini', [
        'has_record'   => false,
        'has_masuk'    => false,
        'has_pulang'   => false,
        'waktu_masuk'  => null,
        'waktu_pulang' => null
    ]);
}
