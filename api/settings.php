<?php
// ============================================
// Smart Attendance System - API Settings
// GET  → ambil semua setting
// POST → simpan setting (dari admin panel)
// ============================================
require_once 'koneksi.php';

$conn = getConnection();

// ---- GET: kembalikan semua setting ----
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $res  = $conn->query("SELECT setting_key, setting_val, label FROM settings ORDER BY id");
    $data = [];
    while ($row = $res->fetch_assoc()) {
        $data[$row['setting_key']] = [
            'value' => $row['setting_val'],
            'label' => $row['label']
        ];
    }
    $conn->close();
    sendResponse('success', 'Data settings', $data);
}

// ---- POST: simpan setting ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $allowed = ['kampus_lat', 'kampus_lng', 'max_radius', 'batas_jam', 'batas_menit'];
    $updated = 0;

    foreach ($allowed as $key) {
        if (!isset($input[$key])) continue;

        $val = trim($input[$key]);

        // Validasi numerik
        if (!is_numeric($val)) {
            $conn->close();
            sendResponse('error', "Nilai '$key' harus berupa angka");
        }

        // Validasi range
        if ($key === 'kampus_lat'  && ($val < -90  || $val > 90))  { $conn->close(); sendResponse('error', 'Latitude harus antara -90 dan 90'); }
        if ($key === 'kampus_lng'  && ($val < -180 || $val > 180)) { $conn->close(); sendResponse('error', 'Longitude harus antara -180 dan 180'); }
        if ($key === 'max_radius'  && $val < 10)                   { $conn->close(); sendResponse('error', 'Radius minimal 10 meter'); }
        if ($key === 'batas_jam'   && ($val < 0 || $val > 23))     { $conn->close(); sendResponse('error', 'Jam harus antara 0-23'); }
        if ($key === 'batas_menit' && ($val < 0 || $val > 59))     { $conn->close(); sendResponse('error', 'Menit harus antara 0-59'); }

        $stmt = $conn->prepare(
            "UPDATE settings SET setting_val = ? WHERE setting_key = ?"
        );
        $stmt->bind_param('ss', $val, $key);
        $stmt->execute();
        $updated++;
    }

    $conn->close();

    if ($updated === 0) {
        sendResponse('error', 'Tidak ada data yang diperbarui');
    }
    sendResponse('success', "$updated pengaturan berhasil disimpan");
}

sendResponse('error', 'Method tidak diizinkan');
?>
