<?php
// ============================================
// Smart Attendance System - API Absensi
// ============================================
require_once 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Method tidak diizinkan');
}

$input     = json_decode(file_get_contents('php://input'), true);
$userId    = (int)($input['user_id']   ?? $_POST['user_id']   ?? 0);
$latitude  = (float)($input['latitude']  ?? $_POST['latitude']  ?? 0);
$longitude = (float)($input['longitude'] ?? $_POST['longitude'] ?? 0);
$foto      = $input['foto'] ?? $_POST['foto'] ?? '';

$tipe      = strtolower(trim($input['tipe'] ?? $input['aksi'] ?? $_POST['tipe'] ?? $_POST['aksi'] ?? ''));
$kategori  = strtolower(trim($input['kategori'] ?? $_POST['kategori'] ?? 'izin'));
$catatan   = trim($input['catatan'] ?? $_POST['catatan'] ?? '');

if (!$userId) {
    sendResponse('error', 'Parameter user_id diperlukan');
}

$conn = getConnection();

// Auto-migrate kolom presensi pulang dan catatan jika belum ada
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
$cek   = $conn->prepare(
    "SELECT id, waktu, status, waktu_pulang, status_pulang, catatan FROM absensi WHERE user_id = ? AND DATE(waktu) = ? ORDER BY id DESC LIMIT 1"
);
$cek->bind_param('is', $userId, $today);
$cek->execute();
$resCek = $cek->get_result();
$rowToday = ($resCek && $resCek->num_rows > 0) ? $resCek->fetch_assoc() : null;
$cek->close();

// ============================================
// PENGAJUAN IZIN TIDAK HADIR (SAKIT / IZIN / DISPENSASI)
// ============================================
if ($tipe === 'izin') {
    if ($rowToday) {
        $st = strtoupper($rowToday['status']);
        $conn->close();
        sendResponse('error', "Anda sudah memiliki catatan presensi/izin ($st) untuk hari ini.");
    }

    if (!in_array($kategori, ['sakit', 'izin', 'dispensasi'])) {
        $kategori = 'izin';
    }

    if (empty($catatan)) {
        $conn->close();
        sendResponse('error', 'Catatan / keterangan alasan izin wajib diisi.');
    }

    $waktuIzin = date('Y-m-d H:i:s');
    $latIzin   = $latitude ? $latitude : 0.0;
    $lngIzin   = $longitude ? $longitude : 0.0;
    $fotoIzin  = '';

    $stmt = $conn->prepare(
        "INSERT INTO absensi (user_id, latitude, longitude, foto, status, catatan) VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('iddsss', $userId, $latIzin, $lngIzin, $fotoIzin, $kategori, $catatan);
    $stmt->execute();
    $insertId = $conn->insert_id;
    $stmt->close();
    $conn->close();

    $labelKategori = ($kategori === 'sakit') ? 'Sakit' : (($kategori === 'dispensasi') ? 'Dispensasi' : 'Izin Keperluan');
    sendResponse('success', "Permohonan izin tidak hadir ($labelKategori) berhasil dicatat.", [
        'tipe'       => 'izin',
        'kategori'   => $kategori,
        'absensi_id' => $insertId,
        'status'     => $kategori,
        'catatan'    => $catatan,
        'waktu'      => $waktuIzin
    ]);
}

// Untuk presensi fisik (masuk / pulang), koordinat wajib terisi
if (!$latitude || !$longitude) {
    $conn->close();
    sendResponse('error', 'Data tidak lengkap (latitude dan longitude wajib untuk presensi fisik)');
}

// ============================================
// Koordinat kampus – diambil dari tabel settings
// ============================================
$cfg = [];
$tblCheck = $conn->query("SHOW TABLES LIKE 'settings'");
if ($tblCheck && $tblCheck->num_rows > 0) {
    $resCfg = $conn->query(
        "SELECT setting_key, setting_val FROM settings
         WHERE setting_key IN ('kampus_lat','kampus_lng','max_radius','batas_jam','batas_menit')"
    );
    if ($resCfg) {
        while ($r = $resCfg->fetch_assoc()) {
            $cfg[$r['setting_key']] = $r['setting_val'];
        }
    }
}

// Gunakan nilai dari DB, fallback ke default jika tidak ada
$kampusLat  = isset($cfg['kampus_lat'])  ? (float)$cfg['kampus_lat']  : -6.2088;
$kampusLng  = isset($cfg['kampus_lng'])  ? (float)$cfg['kampus_lng']  : 106.8456;
$maxRadius  = isset($cfg['max_radius'])  ? (int)$cfg['max_radius']    : 100;
$batasJam   = isset($cfg['batas_jam'])   ? (int)$cfg['batas_jam']     : 8;
$batasMenit = isset($cfg['batas_menit']) ? (int)$cfg['batas_menit']   : 0;

/**
 * Hitung jarak dua koordinat menggunakan Haversine Formula
 */
function hitungJarak($lat1, $lng1, $lat2, $lng2) {
    $R    = 6371000; // radius bumi dalam meter
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a    = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);
    $c    = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $R * $c;
}

$jarak = hitungJarak($latitude, $longitude, $kampusLat, $kampusLng);

if ($jarak > $maxRadius) {
    $conn->close();
    sendResponse('error', 'Lokasi di luar radius kampus. Jarak: ' . round($jarak) . 'm (maks ' . $maxRadius . 'm)');
}

// Tentukan status hadir / terlambat berdasarkan settings
$jam    = (int)date('H');
$menit  = (int)date('i');
$waktuSekarang = $jam * 60 + $menit;
$waktuBatas    = $batasJam * 60 + $batasMenit;
$status = ($waktuSekarang <= $waktuBatas) ? 'hadir' : 'terlambat';

// Simpan foto Base64 jika ada
$fotoPath = '';
if (!empty($foto)) {
    $fotoData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $foto));
    if ($fotoData !== false) {
        $fotoSuffix = ($tipe === 'pulang') ? '_pulang_' : '_masuk_';
        $fotoName   = 'foto_' . $userId . $fotoSuffix . time() . '.jpg';
        $fotoDir    = __DIR__ . '/../uploads/';
        if (!is_dir($fotoDir)) mkdir($fotoDir, 0755, true);
        file_put_contents($fotoDir . $fotoName, $fotoData);
        $fotoPath = 'uploads/' . $fotoName;
    }
}

// Otomatis tentukan aksi jika tipe tidak dikirim secara eksplisit
if (empty($tipe)) {
    if (!$rowToday) {
        $tipe = 'masuk';
    } elseif (empty($rowToday['waktu_pulang'])) {
        $tipe = 'pulang';
    } else {
        $conn->close();
        sendResponse('error', 'Presensi hari ini (masuk dan pulang) telah lengkap tercatat.');
    }
}

if ($tipe === 'pulang') {
    // ============================================
    // PRESENSI PULANG
    // ============================================
    if (!$rowToday) {
        $conn->close();
        sendResponse('error', 'Anda belum melakukan presensi masuk hari ini. Silakan presensi masuk terlebih dahulu.');
    }

    if (!empty($rowToday['waktu_pulang'])) {
        $jamPulang = date('H:i', strtotime($rowToday['waktu_pulang']));
        $conn->close();
        sendResponse('error', 'Anda sudah melakukan presensi pulang hari ini pada ' . $jamPulang . ' WIB.');
    }

    $absensiId   = (int)$rowToday['id'];
    $waktuPulang = date('Y-m-d H:i:s');
    $statusPulang= 'pulang';

    $upd = $conn->prepare("
        UPDATE absensi 
        SET waktu_pulang = ?, latitude_pulang = ?, longitude_pulang = ?, foto_pulang = ?, status_pulang = ?
        WHERE id = ?
    ");
    $upd->bind_param('sddssi', $waktuPulang, $latitude, $longitude, $fotoPath, $statusPulang, $absensiId);
    $upd->execute();
    $upd->close();
    $conn->close();

    sendResponse('success', 'Presensi pulang berhasil dicatat. Terima kasih atas kehadirannya hari ini!', [
        'tipe'         => 'pulang',
        'absensi_id'   => $absensiId,
        'status'       => $statusPulang,
        'jarak'        => round($jarak) . 'm',
        'waktu'        => $waktuPulang,
        'waktu_masuk'  => $rowToday['waktu'],
        'waktu_pulang' => $waktuPulang,
        'foto_pulang'  => $fotoPath
    ]);

} else {
    // ============================================
    // PRESENSI MASUK
    // ============================================
    if ($rowToday) {
        $jamMasuk = date('H:i', strtotime($rowToday['waktu']));
        $conn->close();
        if (empty($rowToday['waktu_pulang'])) {
            sendResponse('error', 'Anda sudah melakukan presensi masuk pada ' . $jamMasuk . ' WIB. Silakan lakukan Presensi Pulang.');
        } else {
            sendResponse('error', 'Anda telah menyelesaikan presensi masuk dan pulang untuk hari ini.');
        }
    }

    $stmt = $conn->prepare(
        "INSERT INTO absensi (user_id, latitude, longitude, foto, status) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('iddss', $userId, $latitude, $longitude, $fotoPath, $status);
    $stmt->execute();
    $insertId = $conn->insert_id;
    $stmt->close();
    $conn->close();

    sendResponse('success', 'Presensi masuk berhasil dicatat', [
        'tipe'        => 'masuk',
        'absensi_id'  => $insertId,
        'status'      => $status,
        'jarak'       => round($jarak) . 'm',
        'waktu'       => date('Y-m-d H:i:s'),
        'waktu_masuk' => date('Y-m-d H:i:s'),
        'foto'        => $fotoPath
    ]);
}
?>
