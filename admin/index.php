<?php
// ============================================
// Smart Attendance System - Executive Admin Dashboard
// Professional, Clean Look & Corporate Luxury
// ============================================
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

require_once '../api/koneksi.php';

header_remove('Content-Type');
header('Content-Type: text/html; charset=utf-8');

$adminNama = htmlspecialchars($_SESSION['admin_nama'] ?? 'Administrator');
$adminUsername = htmlspecialchars($_SESSION['admin_username'] ?? 'admin');

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

// Statistik utama
$total     = (int)($conn->query("SELECT COUNT(*) as c FROM absensi")->fetch_assoc()['c'] ?? 0);
$hadir     = (int)($conn->query("SELECT COUNT(*) as c FROM absensi WHERE status='hadir'")->fetch_assoc()['c'] ?? 0);
$terlambat = (int)($conn->query("SELECT COUNT(*) as c FROM absensi WHERE status='terlambat'")->fetch_assoc()['c'] ?? 0);
$pulang    = (int)($conn->query("SELECT COUNT(*) as c FROM absensi WHERE waktu_pulang IS NOT NULL")->fetch_assoc()['c'] ?? 0);
$izinCount = (int)($conn->query("SELECT COUNT(*) as c FROM absensi WHERE status IN ('izin','sakit','dispensasi')")->fetch_assoc()['c'] ?? 0);
$mahasiswa = (int)($conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'] ?? 0);

// Presensi terbaru
$result  = $conn->query("
    SELECT a.id, a.latitude, a.longitude, a.foto, a.waktu, a.status,
           a.waktu_pulang, a.latitude_pulang, a.longitude_pulang, a.foto_pulang, a.status_pulang,
           a.catatan,
           u.nama, u.nim
    FROM absensi a
    JOIN users u ON a.user_id = u.id
    ORDER BY a.waktu DESC
    LIMIT 50
");
$absensi = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Settings
$resCfg = $conn->query("SELECT setting_key, setting_val, label FROM settings ORDER BY id");
$settings = [];
if ($resCfg) {
    while ($r = $resCfg->fetch_assoc()) {
        $settings[$r['setting_key']] = ['value' => $r['setting_val'], 'label' => $r['label']];
    }
}

// ============================================
// AGREGASI DATA GRAFIK KEHADIRAN (CHART.JS)
// ============================================
$trendLabels = [];
$trendHadirData = [];
$trendTerlambatData = [];
$trendIzinData = [];

// 7 Hari Terakhir
for ($i = 6; $i >= 0; $i--) {
    $tglKey = date('Y-m-d', strtotime("-$i days"));
    $labelKey = date('d M', strtotime("-$i days"));
    $trendLabels[$tglKey] = $labelKey;
    $trendHadirData[$tglKey] = 0;
    $trendTerlambatData[$tglKey] = 0;
    $trendIzinData[$tglKey] = 0;
}

$resTrend = $conn->query("
    SELECT DATE(waktu) as tgl, status, COUNT(*) as jml
    FROM absensi
    WHERE waktu >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(waktu), status
");
if ($resTrend) {
    while ($tr = $resTrend->fetch_assoc()) {
        $t = $tr['tgl'];
        $st = strtolower($tr['status']);
        $jml = (int)$tr['jml'];
        if (isset($trendLabels[$t])) {
            if ($st === 'hadir') {
                $trendHadirData[$t] += $jml;
            } elseif ($st === 'terlambat') {
                $trendTerlambatData[$t] += $jml;
            } else {
                $trendIzinData[$t] += $jml;
            }
        }
    }
}

// Fallback jika data berada di luar 7 hari terakhir tapi $total > 0
$sumTrend = array_sum($trendHadirData) + array_sum($trendTerlambatData) + array_sum($trendIzinData);
if ($sumTrend === 0 && $total > 0) {
    $resDistinctDays = $conn->query("
        SELECT DISTINCT DATE(waktu) as tgl 
        FROM absensi 
        ORDER BY tgl DESC 
        LIMIT 7
    ");
    if ($resDistinctDays && $resDistinctDays->num_rows > 0) {
        $foundDays = [];
        while ($r = $resDistinctDays->fetch_assoc()) {
            $foundDays[] = $r['tgl'];
        }
        $foundDays = array_reverse($foundDays);
        
        $trendLabels = [];
        $trendHadirData = [];
        $trendTerlambatData = [];
        $trendIzinData = [];
        foreach ($foundDays as $fd) {
            $trendLabels[$fd] = date('d M', strtotime($fd));
            $trendHadirData[$fd] = 0;
            $trendTerlambatData[$fd] = 0;
            $trendIzinData[$fd] = 0;
        }
        
        $inClause = "'" . implode("','", $foundDays) . "'";
        $resTrend2 = $conn->query("
            SELECT DATE(waktu) as tgl, status, COUNT(*) as jml
            FROM absensi
            WHERE DATE(waktu) IN ($inClause)
            GROUP BY DATE(waktu), status
        ");
        if ($resTrend2) {
            while ($tr = $resTrend2->fetch_assoc()) {
                $t = $tr['tgl'];
                $st = strtolower($tr['status']);
                $jml = (int)$tr['jml'];
                if (isset($trendLabels[$t])) {
                    if ($st === 'hadir') {
                        $trendHadirData[$t] += $jml;
                    } elseif ($st === 'terlambat') {
                        $trendTerlambatData[$t] += $jml;
                    } else {
                        $trendIzinData[$t] += $jml;
                    }
                }
            }
        }
    }
}

$chartLabelsJson = json_encode(array_values($trendLabels));
$chartHadirJson = json_encode(array_values($trendHadirData));
$chartTerlambatJson = json_encode(array_values($trendTerlambatData));
$chartIzinJson = json_encode(array_values($trendIzinData));

$conn->close();

$kampusLat = (float)($settings['kampus_lat']['value'] ?? -6.2088);
$kampusLng = (float)($settings['kampus_lng']['value'] ?? 106.8456);
$maxRadius = (int)($settings['max_radius']['value'] ?? 100);
$batasJam  = (int)($settings['batas_jam']['value'] ?? 8);
$batasMenit = (int)($settings['batas_menit']['value'] ?? 0);

$rateHadir = $total > 0 ? round(($hadir / $total) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Executive Portal – Smart Attendance System</title>
  
  <!-- Google Fonts: Cinzel, Playfair Display & Plus Jakarta Sans -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800;900&family=Playfair+Display:ital,wght@0,600;0,700;1,500;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Font Awesome 6 CDN -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  
  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

  <!-- Chart.js CDN (Clean Modern Charts) -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <style>
    *, *::before, *::after {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    :root {
      /* Base: Broken White / Pure White (Non-flat, High Contrast Luxury System) */
      --bg-base: #faf8f5;
      --bg-surface: #f4f0e8;
      --card-bg: #ffffff;
      --card-border: rgba(197, 155, 86, 0.24);
      --border-subtle: rgba(22, 19, 17, 0.08);
      --border-gold: rgba(197, 155, 86, 0.35);

      /* Deep Obsidian Noir Typography (Ultra Sharp Contrast, Tidak Mati) */
      --text-main: #141210;
      --text-muted: #5e5750;
      --text-soft: #8e8479;

      /* Warm Champagne Gold & Polished Amber (Inspired by 'JOIN NOW' button) */
      --gold-primary: #d4a362;
      --gold-hover: #c4914d;
      --gold-dark: #9a6b28;
      --gold-light-bg: #fcf5eb;
      --gold-border: rgba(212, 163, 98, 0.4);
      --gold-glow: 0 4px 18px rgba(197, 155, 86, 0.35);

      /* Deep Obsidian Noir for Sidebar Anchor (Dramatic Non-flat Contrast) */
      --sidebar-bg: #141210;
      --sidebar-border: rgba(197, 155, 86, 0.22);
      --sidebar-text: #cfc7be;
      --sidebar-text-muted: #8a8176;

      /* Alive Vibrant Status Accents */
      --sage-corp: #1e5a40;
      --sage-soft-bg: #edf6f1;
      --sage-border: #bfe0cf;

      --berry-corp: #9c2436;
      --berry-soft-bg: #fdf0f2;
      --berry-border: #f2c7ce;

      --taupe-corp: #67554b;
      --taupe-soft-bg: #f6f1ec;

      --radius-xl: 18px;
      --radius-lg: 14px;
      --radius-md: 10px;
      --radius-sm: 8px;

      --shadow-sm: 0 2px 6px rgba(22, 19, 17, 0.04);
      --shadow-md: 0 8px 24px rgba(22, 19, 17, 0.05), 0 2px 6px rgba(22, 19, 17, 0.02);
      --shadow-lg: 0 16px 40px rgba(22, 19, 17, 0.08), 0 4px 12px rgba(22, 19, 17, 0.03);
    }

    body {
      font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background-color: var(--bg-base);
      color: var(--text-main);
      min-height: 100vh;
      display: flex;
      letter-spacing: -0.01em;
      -webkit-font-smoothing: antialiased;
      overflow-x: hidden;
    }

    /* ---- SIDEBAR NAVIGATION ---- */
    /* ---- SIDEBAR NAVIGATION (DEEP OBSIDIAN NOIR WITH GOLD ACCENTS) ---- */
    .sidebar {
      width: 270px;
      background: var(--sidebar-bg);
      border-right: 1px solid var(--sidebar-border);
      display: flex;
      flex-direction: column;
      position: fixed;
      top: 0;
      bottom: 0;
      left: 0;
      z-index: 150;
      transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
      box-shadow: 4px 0 28px rgba(0, 0, 0, 0.14);
    }

    .sidebar-brand {
      padding: 24px 22px 20px;
      border-bottom: 1px solid rgba(197, 155, 86, 0.2);
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .brand-icon {
      width: 42px;
      height: 42px;
      border-radius: var(--radius-md);
      background: linear-gradient(135deg, #e5b675, #c4914d);
      color: #141210;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.15rem;
      box-shadow: 0 4px 16px rgba(212, 163, 98, 0.4);
    }

    .brand-text h2 {
      font-family: 'Cinzel', serif;
      font-size: 0.95rem;
      font-weight: 700;
      letter-spacing: 0.06em;
      color: #fdfaf5;
      line-height: 1.2;
      text-transform: uppercase;
    }

    .brand-text span {
      font-size: 0.65rem;
      font-weight: 700;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      color: var(--gold-primary);
    }

    .sidebar-menu {
      padding: 20px 14px;
      flex: 1;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 5px;
    }

    .menu-heading {
      font-size: 0.65rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.15em;
      color: var(--sidebar-text-muted);
      padding: 12px 12px 6px;
    }

    .sidebar-link {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 11px 14px;
      border-radius: var(--radius-md);
      color: var(--sidebar-text);
      text-decoration: none;
      font-size: 0.84rem;
      font-weight: 600;
      border: 1px solid transparent;
      background: transparent;
      cursor: pointer;
      width: 100%;
      text-align: left;
      font-family: inherit;
      transition: all 0.2s ease;
    }

    .sidebar-link i {
      font-size: 0.95rem;
      width: 20px;
      text-align: center;
      color: var(--sidebar-text-muted);
      transition: color 0.2s ease;
    }

    .sidebar-link:hover {
      background: rgba(212, 163, 98, 0.08);
      color: var(--gold-primary);
    }

    .sidebar-link:hover i {
      color: var(--gold-primary);
    }

    .sidebar-link.active {
      background: rgba(212, 163, 98, 0.14);
      color: #ffffff;
      border-color: rgba(212, 163, 98, 0.4);
      font-weight: 700;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    }

    .sidebar-link.active i {
      color: var(--gold-primary);
    }

    .sidebar-link .menu-badge {
      margin-left: auto;
      background: rgba(212, 163, 98, 0.18);
      border: 1px solid rgba(212, 163, 98, 0.35);
      font-size: 0.7rem;
      font-weight: 700;
      color: var(--gold-primary);
      padding: 2px 8px;
      border-radius: 999px;
    }

    .sidebar-footer {
      padding: 18px;
      border-top: 1px solid rgba(197, 155, 86, 0.18);
      background: rgba(0, 0, 0, 0.25);
    }

    .status-widget {
      background: rgba(255, 255, 255, 0.04);
      border: 1px solid rgba(197, 155, 86, 0.25);
      border-radius: var(--radius-md);
      padding: 12px 14px;
    }

    .status-indicator {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.74rem;
      font-weight: 700;
      color: #5ec292;
      margin-bottom: 4px;
    }

    .pulse-dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: #4ade80;
      position: relative;
    }

    .pulse-dot::after {
      content: '';
      position: absolute;
      top: -3px;
      left: -3px;
      width: 13px;
      height: 13px;
      border-radius: 50%;
      background: rgba(74, 222, 128, 0.3);
      animation: pulseAnim 2s infinite;
    }

    @keyframes pulseAnim {
      0% { transform: scale(0.9); opacity: 0.8; }
      70% { transform: scale(1.6); opacity: 0; }
      100% { transform: scale(0.9); opacity: 0; }
    }

    .status-info {
      font-size: 0.72rem;
      color: var(--sidebar-text-muted);
      line-height: 1.4;
    }

    /* ---- MAIN CONTENT WRAPPER ---- */
    .main-wrap {
      margin-left: 270px;
      flex: 1;
      display: flex;
      flex-direction: column;
      min-width: 0;
      background-color: var(--bg-base);
    }

    /* ---- TOP EXECUTIVE HEADER BAR ---- */
    .topbar {
      height: 72px;
      background: rgba(255, 255, 255, 0.94);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border-bottom: 1px solid var(--border-gold);
      position: sticky;
      top: 0;
      z-index: 100;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 36px;
      box-shadow: 0 2px 14px rgba(22, 19, 17, 0.03);
    }

    .topbar-left {
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .mobile-menu-toggle {
      display: none;
      background: #ffffff;
      border: 1px solid var(--border-gold);
      width: 38px;
      height: 38px;
      border-radius: var(--radius-sm);
      color: var(--text-main);
      cursor: pointer;
      font-size: 1rem;
    }

    .breadcrumbs {
      font-size: 0.78rem;
      font-weight: 600;
      color: var(--text-soft);
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .breadcrumbs span {
      color: var(--text-main);
      font-weight: 700;
    }

    .topbar-right {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .clock-pill {
      background: var(--gold-light-bg);
      border: 1px solid var(--gold-border);
      padding: 7px 16px;
      border-radius: 999px;
      font-size: 0.78rem;
      font-weight: 700;
      color: var(--gold-dark);
      display: flex;
      align-items: center;
      gap: 8px;
      font-variant-numeric: tabular-nums;
      box-shadow: var(--shadow-sm);
    }

    .clock-pill i {
      color: var(--gold-primary);
      font-size: 0.85rem;
    }

    /* ---- BUTTONS (LUXURY GOLD & DEEP BRONZE) ---- */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 9px 18px;
      border-radius: var(--radius-md);
      font-size: 0.82rem;
      font-weight: 700;
      font-family: inherit;
      cursor: pointer;
      text-decoration: none;
      border: 1px solid transparent;
      transition: all 0.22s ease;
      letter-spacing: 0.02em;
      white-space: nowrap;
    }

    .btn:active:not(:disabled) {
      transform: scale(0.98);
    }

    .btn:disabled {
      opacity: 0.55;
      cursor: not-allowed;
    }

    /* Primary Gold Button (Inspired by 'JOIN NOW') */
    .btn-luxury {
      background: linear-gradient(135deg, #dfa86a, #c7924d);
      color: #14110e;
      font-weight: 700;
      letter-spacing: 0.04em;
      box-shadow: 0 4px 16px rgba(199, 146, 77, 0.35);
      border: none;
    }

    .btn-luxury:hover:not(:disabled) {
      background: linear-gradient(135deg, #e6b175, #cf9c58);
      color: #14110e;
      transform: translateY(-2px);
      box-shadow: 0 6px 22px rgba(199, 146, 77, 0.45);
    }

    .btn-outline {
      background: #ffffff;
      border: 1.5px solid var(--border-gold);
      color: var(--text-main);
      box-shadow: var(--shadow-sm);
      font-weight: 600;
    }

    .btn-outline:hover:not(:disabled) {
      background: var(--gold-light-bg);
      border-color: var(--gold-primary);
      color: var(--gold-dark);
      transform: translateY(-1px);
    }

    .btn-sage {
      background: var(--sage-corp);
      color: #ffffff;
      box-shadow: 0 2px 8px rgba(30, 90, 64, 0.25);
    }

    .btn-sage:hover:not(:disabled) {
      background: #143e2c;
      color: #ffffff;
    }

    .btn-sm {
      padding: 6px 12px;
      font-size: 0.76rem;
      border-radius: var(--radius-sm);
    }

    /* ---- MAIN STAGE / CONTENT ---- */
    .content-stage {
      padding: 32px 36px 64px;
      max-width: 1400px;
      width: 100%;
      margin: 0 auto;
    }

    .tab-panel {
      display: none;
    }

    .tab-panel.active {
      display: block;
      animation: fadeIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(6px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* ---- EXECUTIVE STATS GRID ---- */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 18px;
      margin-bottom: 28px;
    }

    .stat-card {
      background: #ffffff;
      border: 1px solid var(--card-border);
      border-radius: var(--radius-lg);
      padding: 20px 22px;
      box-shadow: 0 4px 16px rgba(22, 19, 17, 0.04);
      transition: all 0.22s ease;
      position: relative;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 28px rgba(22, 19, 17, 0.08);
      border-color: var(--gold-primary);
    }

    .stat-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 10px;
    }

    .stat-label {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 0.76rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.03em;
      color: var(--text-muted);
    }

    .stat-icon {
      width: 38px;
      height: 38px;
      border-radius: var(--radius-md);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.95rem;
    }

    .stat-icon.rose { background: var(--gold-light-bg); color: var(--gold-dark); border: 1px solid var(--gold-border); }
    .stat-icon.sage { background: var(--sage-soft-bg); color: var(--sage-corp); border: 1px solid var(--sage-border); }
    .stat-icon.berry { background: var(--berry-soft-bg); color: var(--berry-corp); border: 1px solid var(--berry-border); }
    .stat-icon.taupe { background: var(--taupe-soft-bg); color: var(--taupe-corp); border: 1px solid rgba(197, 155, 86, 0.25); }

    .stat-value {
      font-size: 2.2rem;
      font-weight: 800;
      letter-spacing: -0.03em;
      line-height: 1;
      color: var(--text-main);
      font-variant-numeric: tabular-nums;
      margin-bottom: 0;
    }

    .stat-meta {
      font-size: 0.74rem;
      color: var(--text-muted);
      display: flex;
      align-items: center;
      gap: 6px;
      font-weight: 500;
    }

    .trend-pill {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 2px 8px;
      border-radius: 999px;
      font-size: 0.68rem;
      font-weight: 700;
    }

    .trend-pill.positive { background: var(--sage-soft-bg); color: var(--sage-corp); border: 1px solid var(--sage-border); }
    .trend-pill.neutral { background: var(--gold-light-bg); color: var(--gold-dark); border: 1px solid var(--gold-border); }

    /* ---- TABLE CARD & CONTROLS ---- */
    .table-container {
      background: #ffffff;
      border: 1px solid var(--card-border);
      border-radius: var(--radius-lg);
      box-shadow: 0 8px 24px rgba(22, 19, 17, 0.05);
      overflow: hidden;
    }

    .table-toolbar {
      padding: 18px 24px;
      border-bottom: 1px solid var(--border-gold);
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      background: #faf8f5;
    }

    .toolbar-left {
      display: flex;
      align-items: center;
      gap: 14px;
      flex-wrap: wrap;
      flex: 1;
    }

    .search-box {
      position: relative;
      min-width: 260px;
      max-width: 360px;
      width: 100%;
    }

    .search-box i {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-soft);
      font-size: 0.85rem;
    }

    .search-input {
      width: 100%;
      padding: 10px 14px 10px 38px;
      border-radius: var(--radius-md);
      border: 1.5px solid var(--border-gold);
      background: #ffffff;
      font-size: 0.82rem;
      font-family: inherit;
      color: var(--text-main);
      outline: none;
      transition: all 0.2s ease;
    }

    .search-input:focus {
      border-color: var(--gold-primary);
      box-shadow: 0 0 0 3px rgba(212, 163, 98, 0.2);
    }

    .filter-tabs {
      display: flex;
      background: #f0ece3;
      padding: 3px;
      border-radius: var(--radius-md);
      border: 1px solid var(--border-gold);
      gap: 2px;
    }

    .filter-btn {
      padding: 7px 14px;
      border: none;
      background: transparent;
      border-radius: var(--radius-sm);
      font-size: 0.76rem;
      font-weight: 600;
      color: var(--text-muted);
      cursor: pointer;
      font-family: inherit;
      transition: all 0.18s ease;
    }

    .filter-btn:hover {
      color: var(--text-main);
    }

    .filter-btn.active {
      background: #ffffff;
      color: #141210;
      font-weight: 700;
      box-shadow: 0 2px 6px rgba(22, 19, 17, 0.08);
      border: 1px solid var(--gold-border);
    }

    .toolbar-right {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    /* ---- DATA TABLE ---- */
    .table-responsive {
      overflow-x: auto;
      scrollbar-width: thin;
      scrollbar-color: rgba(212, 163, 98, 0.3) transparent;
      -webkit-overflow-scrolling: touch;
    }

    .table-responsive::-webkit-scrollbar {
      height: 6px;
      width: 6px;
    }

    .table-responsive::-webkit-scrollbar-track {
      background: transparent;
    }

    .table-responsive::-webkit-scrollbar-thumb {
      background: rgba(212, 163, 98, 0.3);
      border-radius: 999px;
    }

    .table-responsive::-webkit-scrollbar-thumb:hover {
      background: var(--gold-primary);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
    }

    th {
      background: #f5f1e8;
      padding: 14px 18px;
      font-family: 'Cinzel', serif;
      font-size: 0.72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.09em;
      color: #5e5750;
      border-bottom: 1px solid var(--border-gold);
      white-space: nowrap;
    }

    td {
      padding: 15px 18px;
      font-size: 0.84rem;
      color: var(--text-main);
      border-bottom: 1px solid rgba(22, 19, 17, 0.06);
      vertical-align: middle;
      transition: background-color 0.15s ease;
    }

    tr:last-child td {
      border-bottom: none;
    }

    tbody tr:hover td {
      background-color: #fbf7ef;
    }

    /* Personel column */
    .person-cell {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .avatar-wrapper {
      position: relative;
      width: 42px;
      height: 42px;
      flex-shrink: 0;
      cursor: pointer;
    }

    .avatar-img {
      width: 42px;
      height: 42px;
      border-radius: var(--radius-md);
      object-fit: cover;
      border: 1px solid var(--border-gold);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .avatar-wrapper:hover .avatar-img {
      transform: scale(1.05);
      box-shadow: 0 4px 14px rgba(212, 163, 98, 0.35);
    }

    .avatar-placeholder {
      width: 42px;
      height: 42px;
      border-radius: var(--radius-md);
      background: var(--gold-light-bg);
      border: 1px solid var(--gold-border);
      color: var(--gold-dark);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.95rem;
    }

    .person-details {
      display: flex;
      flex-direction: column;
      gap: 2px;
    }

    .person-name {
      font-weight: 700;
      color: var(--text-main);
      font-size: 0.86rem;
    }

    .person-nim {
      font-size: 0.72rem;
      color: var(--text-muted);
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
      background: #f4efe6;
      padding: 1px 6px;
      border-radius: 4px;
      display: inline-block;
      width: fit-content;
      border: 1px solid rgba(197, 155, 86, 0.3);
    }

    .time-cell {
      font-variant-numeric: tabular-nums;
      font-weight: 600;
      color: var(--text-main);
      font-size: 0.82rem;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .time-cell i {
      color: var(--gold-primary);
      font-size: 0.76rem;
    }

    .gps-cell {
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
      font-size: 0.74rem;
      color: var(--text-muted);
      line-height: 1.4;
    }

    .gps-dist {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      color: var(--sage-corp);
      font-weight: 700;
      font-size: 0.7rem;
    }

    /* Status badges */
    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 12px;
      border-radius: 999px;
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
    }

    .status-badge.hadir {
      background: var(--sage-soft-bg);
      color: var(--sage-corp);
      border: 1px solid var(--sage-border);
    }

    .status-badge.terlambat {
      background: var(--berry-soft-bg);
      color: var(--berry-corp);
      border: 1px solid var(--berry-border);
    }

    .status-badge.izin,
    .status-badge.sakit,
    .status-badge.dispensasi {
      background: #fef7ea;
      color: #b8853b;
      border: 1px solid rgba(212, 163, 98, 0.45);
    }

    .status-badge .dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
    }

    .status-badge.hadir .dot { background: var(--sage-corp); }
    .status-badge.terlambat .dot { background: var(--berry-corp); }
    .status-badge.izin .dot,
    .status-badge.sakit .dot,
    .status-badge.dispensasi .dot { background: #b8853b; }

    .table-footer {
      padding: 14px 24px;
      border-top: 1px solid var(--border-gold);
      background: #faf8f5;
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 0.76rem;
      color: var(--text-muted);
    }

    /* Empty state styling */
    .empty-state-box {
      max-width: 440px;
      margin: 0 auto;
      padding: 24px;
      text-align: center;
    }

    .empty-state-icon {
      width: 58px;
      height: 58px;
      border-radius: var(--radius-md);
      background: var(--gold-light-bg);
      border: 1.5px solid var(--gold-border);
      color: var(--gold-dark);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      margin-bottom: 16px;
      box-shadow: 0 6px 18px rgba(197, 155, 86, 0.2);
    }

    .empty-state-title {
      font-family: 'Cinzel', serif;
      font-size: 1.05rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      color: var(--text-main);
      margin-bottom: 8px;
    }

    .empty-state-desc {
      font-size: 0.8rem;
      color: var(--text-muted);
      line-height: 1.55;
      margin-bottom: 20px;
    }

    .empty-state-actions {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      flex-wrap: wrap;
    }

    /* ---- TAB SETTINGS (GEOFENCE & PARAMETERS) ---- */
    .settings-layout {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 24px;
    }

    .card {
      background: #ffffff;
      border: 1px solid var(--card-border);
      border-radius: var(--radius-lg);
      padding: 26px;
      box-shadow: var(--shadow-md);
    }

    .card-title {
      font-family: 'Cinzel', serif;
      font-size: 1rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      color: var(--text-main);
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 20px;
      padding-bottom: 14px;
      border-bottom: 1px solid var(--border-gold);
    }

    .card-title i {
      color: var(--gold-primary);
      font-size: 1rem;
    }

    .form-group {
      margin-bottom: 18px;
    }

    .form-label {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 0.78rem;
      font-weight: 700;
      color: var(--text-main);
      margin-bottom: 8px;
    }

    .form-label span {
      font-size: 0.72rem;
      font-weight: 500;
      color: var(--text-soft);
    }

    .input-wrapper {
      position: relative;
    }

    .input-wrapper i {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-soft);
      font-size: 0.85rem;
    }

    .form-input {
      width: 100%;
      padding: 10px 14px 10px 38px;
      background: #ffffff;
      border: 1.5px solid var(--border-gold);
      border-radius: var(--radius-md);
      color: var(--text-main);
      font-size: 0.86rem;
      font-family: inherit;
      outline: none;
      transition: all 0.2s ease;
    }

    .form-input:focus {
      border-color: var(--gold-primary);
      box-shadow: 0 0 0 3px rgba(212, 163, 98, 0.2);
    }

    .input-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 14px;
    }

    /* Range slider */
    .range-box {
      background: #faf8f5;
      padding: 16px;
      border-radius: var(--radius-md);
      border: 1px solid var(--border-gold);
      margin-top: 4px;
    }

    .range-header {
      display: flex;
      justify-content: space-between;
      font-size: 0.78rem;
      font-weight: 600;
      color: var(--text-main);
      margin-bottom: 8px;
    }

    .range-val {
      color: var(--gold-dark);
      font-weight: 700;
    }

    input[type=range] {
      width: 100%;
      accent-color: var(--gold-primary);
      cursor: pointer;
    }

    .cutoff-preview {
      background: var(--gold-light-bg);
      border: 1px solid var(--gold-border);
      border-radius: var(--radius-md);
      padding: 14px 16px;
      font-size: 0.78rem;
      color: var(--text-main);
      line-height: 1.55;
      margin-top: 14px;
      margin-bottom: 22px;
    }

    .cutoff-preview b {
      color: var(--gold-dark);
    }

    /* Map container */
    #settings-map {
      height: 320px;
      border-radius: var(--radius-md);
      border: 1.5px solid var(--border-gold);
      overflow: hidden;
      margin-top: 14px;
      box-shadow: var(--shadow-sm);
    }

    .gps-hud {
      background: #faf8f5;
      border: 1px solid var(--border-gold);
      border-radius: var(--radius-md);
      padding: 14px;
      margin-bottom: 14px;
    }

    .hud-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 5px 0;
      font-size: 0.76rem;
      border-bottom: 1px dashed rgba(197, 155, 86, 0.3);
    }

    .hud-row:last-child {
      border-bottom: none;
    }

    .hud-label {
      color: var(--text-muted);
    }

    .hud-val {
      font-weight: 700;
      color: var(--text-main);
      font-family: ui-monospace, monospace;
    }

    /* Toast Alert */
    .toast-alert {
      padding: 12px 16px;
      border-radius: var(--radius-md);
      font-size: 0.82rem;
      font-weight: 600;
      margin-bottom: 20px;
      display: none;
      align-items: center;
      gap: 10px;
    }

    .toast-alert.show {
      display: flex;
      animation: fadeIn 0.25s ease;
    }

    .toast-success {
      background: var(--sage-soft-bg);
      color: var(--sage-corp);
      border: 1px solid var(--sage-border);
    }

    .toast-error {
      background: var(--berry-soft-bg);
      color: var(--berry-corp);
      border: 1px solid var(--berry-border);
    }

    /* ---- INSPECTION MODAL (BUKTI PRESENSI) ---- */
    .modal-backdrop {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(35, 25, 30, 0.45);
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
      z-index: 200;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .modal-backdrop.open {
      display: flex;
      animation: fadeIn 0.2s ease;
    }

    .modal-card {
      background: #ffffff;
      border-radius: var(--radius-xl);
      border: 1px solid var(--card-border);
      box-shadow: var(--shadow-lg);
      max-width: 480px;
      width: 100%;
      max-height: calc(100vh - 32px);
      display: flex;
      flex-direction: column;
      overflow: hidden;
      animation: modalSlide 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes modalSlide {
      from { transform: translateY(16px) scale(0.97); opacity: 0; }
      to { transform: translateY(0) scale(1); opacity: 1; }
    }

    .modal-header {
      padding: 14px 20px;
      border-bottom: 1px solid var(--border-subtle);
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: #fdfcfb;
      flex-shrink: 0;
    }

    .modal-header h3 {
      font-size: 0.92rem;
      font-weight: 700;
      color: var(--text-main);
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .modal-close {
      background: transparent;
      border: none;
      width: 32px;
      height: 32px;
      border-radius: 50%;
      color: var(--text-soft);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.15s ease;
    }

    .modal-close:hover {
      background: var(--gold-light-bg);
      color: var(--gold-dark);
    }

    .modal-body {
      padding: 16px 20px;
      text-align: center;
      overflow-y: auto;
      flex: 1;
      -webkit-overflow-scrolling: touch;
    }

    .modal-footer {
      padding: 12px 20px;
      border-top: 1px solid var(--border-subtle);
      background: #fdfcfb;
      flex-shrink: 0;
    }

    .modal-foto-switch {
      display: flex;
      gap: 8px;
      margin-bottom: 12px;
      justify-content: center;
    }

    .btn-foto-switch {
      padding: 5px 12px;
      border-radius: 6px;
      font-size: 0.72rem;
      font-weight: 700;
      border: 1.5px solid var(--border-gold);
      background: #ffffff;
      color: var(--text-muted);
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      transition: all 0.2s ease;
    }

    .btn-foto-switch.active {
      background: linear-gradient(135deg, #dfa86a, #c7924d);
      color: #14110e;
      border-color: #dfa86a;
      box-shadow: 0 2px 8px rgba(199, 146, 77, 0.3);
    }

    .modal-foto-frame {
      width: 100%;
      height: 190px;
      max-height: 190px;
      border-radius: var(--radius-md);
      overflow: hidden;
      background: #141210;
      border: 1px solid var(--border-subtle);
      margin-bottom: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
    }

    .modal-foto-frame img {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }

    .modal-foto-badge {
      position: absolute;
      top: 8px;
      left: 8px;
      background: rgba(20, 18, 16, 0.75);
      backdrop-filter: blur(4px);
      color: #dfa86a;
      border: 1px solid rgba(212, 163, 98, 0.35);
      padding: 3px 8px;
      border-radius: 6px;
      font-size: 0.68rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }

    .modal-info-grid {
      text-align: left;
      background: #faf7f5;
      border: 1px solid var(--border-subtle);
      border-radius: var(--radius-md);
      padding: 14px;
      display: flex;
      flex-direction: column;
      gap: 8px;
      font-size: 0.78rem;
    }

    .modal-info-row {
      display: flex;
      justify-content: space-between;
      border-bottom: 1px dashed rgba(225, 215, 218, 0.4);
      padding-bottom: 6px;
    }

    .modal-info-row:last-child {
      border-bottom: none;
      padding-bottom: 0;
    }

    .modal-info-label {
      color: var(--text-muted);
    }

    .modal-info-value {
      font-weight: 700;
      color: var(--text-main);
    }

    /* Spinner */
    .spinner {
      display: inline-block;
      width: 14px;
      height: 14px;
      border: 2px solid rgba(255, 255, 255, 0.4);
      border-top-color: #ffffff;
      border-radius: 50%;
      animation: spin 0.6s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ---- DASHBOARD BANNER ---- */
    .dashboard-banner {
      background: linear-gradient(135deg, #1f1b18 0%, #29241f 100%);
      border: 1px solid rgba(212, 163, 98, 0.22);
      border-radius: var(--radius-lg);
      padding: 20px 24px;
      margin-bottom: 22px;
      color: #ffffff;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      box-shadow: 0 4px 18px rgba(20, 18, 16, 0.07);
    }
    .banner-text h2 {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 1.15rem;
      font-weight: 700;
      letter-spacing: -0.01em;
      color: #ffffff;
      margin-bottom: 4px;
    }
    .banner-text p {
      font-size: 0.82rem;
      color: #b8afa4;
      max-width: 580px;
      line-height: 1.45;
      margin: 0;
    }
    .banner-actions {
      display: flex;
      gap: 10px;
      align-items: center;
      flex-wrap: wrap;
      flex-shrink: 0;
    }

    /* ---- CHARTS GRID ---- */
    .charts-grid {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 20px;
      margin-bottom: 28px;
    }
    .chart-card {
      background: #ffffff;
      border: 1px solid var(--card-border);
      border-radius: var(--radius-lg);
      padding: 22px 24px;
      box-shadow: 0 8px 24px rgba(22, 19, 17, 0.05);
      display: flex;
      flex-direction: column;
    }
    .chart-card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 18px;
      padding-bottom: 12px;
      border-bottom: 1px solid #f4f0e8;
    }
    .chart-card-title {
      font-size: 0.95rem;
      font-weight: 700;
      color: var(--text-main);
      display: flex;
      align-items: center;
      gap: 8px;
      letter-spacing: -0.01em;
    }
    .chart-card-title i {
      color: var(--gold-dark);
      font-size: 1rem;
    }
    .chart-card-meta {
      font-size: 0.76rem;
      color: var(--text-muted);
      font-weight: 600;
    }
    .chart-canvas-wrap {
      position: relative;
      flex: 1;
      width: 100%;
      min-height: 250px;
      max-height: 290px;
    }

    /* Donut Legend List */
    .donut-legend-wrap {
      display: flex;
      flex-direction: column;
      gap: 10px;
      margin-top: 14px;
      padding-top: 14px;
      border-top: 1px dashed #ede8df;
    }
    .donut-legend-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 0.78rem;
    }
    .donut-legend-label {
      display: flex;
      align-items: center;
      gap: 8px;
      color: var(--text-muted);
      font-weight: 600;
    }
    .legend-bullet {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      flex-shrink: 0;
    }
    .donut-legend-val {
      font-weight: 700;
      color: var(--text-main);
      font-variant-numeric: tabular-nums;
    }

    /* ---- DASHBOARD SPLIT GRID ---- */
    .dashboard-split-grid {
      display: grid;
      grid-template-columns: 1.8fr 1.2fr;
      gap: 20px;
      margin-bottom: 28px;
    }

    /* Mini Table */
    .mini-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.8rem;
    }
    .mini-table th {
      text-align: left;
      font-size: 0.7rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--text-muted);
      padding: 10px 12px;
      border-bottom: 1.5px solid #f0eae1;
      background: #faf8f5;
    }
    .mini-table td {
      padding: 11px 12px;
      border-bottom: 1px solid #f4f0e8;
      vertical-align: middle;
    }
    .mini-table tr:hover td {
      background: #faf8f5;
    }

    /* ---- RESPONSIVE ---- */
    @media (max-width: 1080px) {
      .stats-grid { grid-template-columns: repeat(2, 1fr); }
      .settings-layout { grid-template-columns: 1fr; }
      .charts-grid { grid-template-columns: 1fr; }
      .dashboard-split-grid { grid-template-columns: 1fr; }
      .dashboard-banner { flex-direction: column; align-items: flex-start; }
    }

    @media (max-width: 768px) {
      .sidebar {
        transform: translateX(-100%);
      }
      .sidebar.open {
        transform: translateX(0);
        box-shadow: var(--shadow-lg);
      }
      .main-wrap { margin-left: 0; }
      .topbar { padding: 0 18px; }
      .mobile-menu-toggle { display: inline-flex; align-items: center; justify-content: center; }
      .content-stage { padding: 20px 16px 48px; }
      .stats-grid { grid-template-columns: 1fr; }
      .table-toolbar { flex-direction: column; align-items: stretch; }
      .search-box { max-width: 100%; }
      .filter-tabs { overflow-x: auto; }
      .input-row { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

  <!-- SIDEBAR NAVIGATION -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="brand-icon">
        <i class="fa-solid fa-layer-group"></i>
      </div>
      <div class="brand-text">
        <h2>Smart Attendance</h2>
        <span>Admin Dashboard</span>
      </div>
    </div>

    <div class="sidebar-menu">
      <div class="menu-heading">Menu Utama</div>
      
      <button class="sidebar-link active" id="nav-btn-dashboard" onclick="switchNav('dashboard')">
        <i class="fa-solid fa-chart-pie"></i>
        <span>Dashboard Utama</span>
      </button>

      <button class="sidebar-link" id="nav-btn-riwayat" onclick="switchNav('riwayat')">
        <i class="fa-solid fa-clock-rotate-left"></i>
        <span>Riwayat Presensi</span>
        <span class="menu-badge" id="sidebar-total-badge"><?= $total ?></span>
      </button>

      <button class="sidebar-link" id="nav-btn-settings" onclick="switchNav('settings')">
        <i class="fa-solid fa-sliders"></i>
        <span>Konfigurasi Geofence</span>
      </button>

      <div class="menu-heading" style="margin-top:16px;">Navigasi Sistem</div>

      <a class="sidebar-link" href="../frontend/index.html" target="_blank">
        <i class="fa-solid fa-mobile-screen"></i>
        <span>Presensi Mahasiswa</span>
      </a>

      <a class="sidebar-link" href="../index.html#petunjuk" target="_blank">
        <i class="fa-solid fa-circle-question"></i>
        <span>Petunjuk Penggunaan</span>
      </a>

      <a class="sidebar-link" href="../index.html#tentang" target="_blank">
        <i class="fa-solid fa-circle-info"></i>
        <span>Tentang Kami</span>
      </a>

      <a class="sidebar-link" href="../index.html">
        <i class="fa-solid fa-house"></i>
        <span>Halaman Utama</span>
      </a>
    </div>

    <div class="sidebar-footer">
      <div style="padding:12px;margin-bottom:12px;background:rgba(255,255,255,0.04);border:1px solid rgba(197,155,86,0.22);border-radius:var(--radius-md);display:flex;align-items:center;justify-content:space-between;gap:10px;">
        <div style="display:flex;align-items:center;gap:10px;min-width:0;">
          <div style="width:34px;height:34px;border-radius:8px;background:linear-gradient(135deg,#dfa86a,#c7924d);display:flex;align-items:center;justify-content:center;color:#14110e;font-size:0.9rem;flex-shrink:0;">
            <i class="fa-solid fa-user-shield"></i>
          </div>
          <div style="min-width:0;">
            <div style="color:#ffffff;font-size:0.8rem;font-weight:700;font-family:'Cinzel',serif;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= $adminNama ?></div>
          </div>
        </div>
        <a href="logout.php" style="color:#dfa86a;background:rgba(212,163,98,0.12);border:1px solid rgba(212,163,98,0.3);text-decoration:none;padding:6px 12px;border-radius:8px;font-size:0.75rem;font-weight:700;display:inline-flex;align-items:center;gap:6px;transition:all 0.2s;" onmouseover="this.style.background='rgba(224,108,117,0.2)';this.style.color='#f08080';this.style.borderColor='rgba(224,108,117,0.4)'" onmouseout="this.style.background='rgba(212,163,98,0.12)';this.style.color='#dfa86a';this.style.borderColor='rgba(212,163,98,0.3)'" title="Keluar dari Sesi Admin">
          <i class="fa-solid fa-arrow-right-from-bracket"></i>
          <span>Logout</span>
        </a>
      </div>

      <div class="status-widget">
        <div class="status-indicator">
          <div class="pulse-dot"></div>
          <span>Sistem Berjalan Aktif</span>
        </div>
        <div class="status-info">
          Radius: <b><?= $maxRadius ?>m</b><br>
          Batas: <b><?= str_pad($batasJam, 2, '0', STR_PAD_LEFT) ?>:<?= str_pad($batasMenit, 2, '0', STR_PAD_LEFT) ?> WIB</b>
        </div>
      </div>
    </div>
  </aside>

  <!-- MAIN VIEWPORT WRAPPER -->
  <div class="main-wrap">
    
    <!-- TOP EXECUTIVE HEADER BAR -->
    <header class="topbar">
      <div class="topbar-left">
        <button class="mobile-menu-toggle" onclick="toggleSidebar()" aria-label="Toggle menu">
          <i class="fa-solid fa-bars"></i>
        </button>
        <div class="breadcrumbs">
          <span>Portal Administrasi</span>
          <i class="fa-solid fa-chevron-right" style="font-size:0.65rem;"></i>
          <span id="page-title-crumb" style="color:var(--rose-luxury);">Dashboard Utama</span>
        </div>
      </div>

      <div class="topbar-right">
        <div class="clock-pill">
          <i class="fa-regular fa-clock"></i>
          <span id="live-clock"><?= date('d M Y, H:i:s') ?></span>
        </div>
        
        <button class="btn btn-outline btn-sm" onclick="location.reload()" title="Segarkan Data">
          <i class="fa-solid fa-arrows-rotate"></i>
          <span>Perbarui</span>
        </button>

        <button class="btn btn-luxury btn-sm" onclick="exportCSV()" title="Ekspor Data ke Format CSV">
          <i class="fa-solid fa-file-arrow-down"></i>
          <span>Ekspor CSV</span>
        </button>

      </div>
    </header>

    <!-- CONTENT STAGE -->
    <main class="content-stage">

      <!-- ===================== TAB DASHBOARD (CLEAN LOOK MODERN) ===================== -->
      <section id="tab-dashboard" class="tab-panel active">
        
        <!-- DASHBOARD BANNER -->
        <div class="dashboard-banner">
          <div class="banner-text">
            <h2>Selamat Datang, <?= $adminNama ?></h2>
            <p>Pantau rekapan kilas kehadiran mahasiswa, grafik mingguan, dan parameter geofencing hari ini.</p>
          </div>
          <div class="banner-actions">
            <button class="btn btn-luxury btn-sm" onclick="switchNav('riwayat')">
              <i class="fa-solid fa-clock-rotate-left"></i>
              <span>Riwayat Presensi</span>
            </button>
            <button class="btn btn-luxury btn-sm" onclick="switchNav('settings')">
              <i class="fa-solid fa-sliders"></i>
              <span>Atur Geofence</span>
            </button>
          </div>
        </div>

        <!-- KPI STATS CARDS (REKAPAN KILAS KEHADIRAN) -->
        <div class="stats-grid">
          
          <div class="stat-card">
            <div class="stat-header">
              <span class="stat-label">Total Log Presensi</span>
              <div class="stat-icon rose"><i class="fa-solid fa-clipboard-check"></i></div>
            </div>
            <div class="stat-value"><?= number_format($total) ?></div>
          </div>

          <div class="stat-card">
            <div class="stat-header">
              <span class="stat-label">Tepat Waktu</span>
              <div class="stat-icon sage"><i class="fa-solid fa-circle-check"></i></div>
            </div>
            <div class="stat-value" style="color:var(--sage-corp);"><?= number_format($hadir) ?></div>
          </div>

          <div class="stat-card">
            <div class="stat-header">
              <span class="stat-label">Terlambat</span>
              <div class="stat-icon berry"><i class="fa-solid fa-clock-rotate-left"></i></div>
            </div>
            <div class="stat-value" style="color:var(--berry-corp);"><?= number_format($terlambat) ?></div>
          </div>

          <div class="stat-card">
            <div class="stat-header">
              <span class="stat-label">Izin & Sakit</span>
              <div class="stat-icon taupe"><i class="fa-solid fa-file-signature"></i></div>
            </div>
            <div class="stat-value" style="color:var(--gold-dark);"><?= number_format($izinCount) ?></div>
          </div>

        </div>

        <!-- CHARTS GRID (GRAFIK KEHADIRAN CLEAN LOOK & MODERN) -->
        <div class="charts-grid">
          
          <!-- Card Kiri: Tren Kehadiran Mingguan -->
          <div class="chart-card">
            <div class="chart-card-header">
              <div class="chart-card-title">
                <i class="fa-solid fa-chart-line"></i>
                <span>Tren Kehadiran (7 Hari Terakhir)</span>
              </div>
              <span class="chart-card-meta">Data Akumulatif Harian</span>
            </div>
            <div class="chart-canvas-wrap">
              <canvas id="chart-trend-canvas"></canvas>
            </div>
          </div>

          <!-- Card Kanan: Distribusi Status Kehadiran (Donut) -->
          <div class="chart-card">
            <div class="chart-card-header">
              <div class="chart-card-title">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Distribusi Status</span>
              </div>
              <span class="chart-card-meta"><?= $total ?> Total Log</span>
            </div>
            <div class="chart-canvas-wrap" style="display:flex;align-items:center;justify-content:center;">
              <canvas id="chart-donut-canvas" style="max-height:185px;"></canvas>
            </div>
            <div class="donut-legend-wrap">
              <div class="donut-legend-item">
                <div class="donut-legend-label">
                  <span class="legend-bullet" style="background:#1e5a40;"></span>
                  <span>Tepat Waktu (Hadir)</span>
                </div>
                <span class="donut-legend-val"><?= $hadir ?> (<?= $total > 0 ? round(($hadir/$total)*100, 1) : 0 ?>%)</span>
              </div>
              <div class="donut-legend-item">
                <div class="donut-legend-label">
                  <span class="legend-bullet" style="background:#9c2436;"></span>
                  <span>Terlambat</span>
                </div>
                <span class="donut-legend-val"><?= $terlambat ?> (<?= $total > 0 ? round(($terlambat/$total)*100, 1) : 0 ?>%)</span>
              </div>
              <div class="donut-legend-item">
                <div class="donut-legend-label">
                  <span class="legend-bullet" style="background:#d4a362;"></span>
                  <span>Izin / Sakit / Dispensasi</span>
                </div>
                <span class="donut-legend-val"><?= $izinCount ?> (<?= $total > 0 ? round(($izinCount/$total)*100, 1) : 0 ?>%)</span>
              </div>
            </div>
          </div>

        </div>

        <!-- DASHBOARD SPLIT GRID: AKTIVITAS TERKINI & STATUS GEOFENCE -->
        <div class="dashboard-split-grid">
          
          <!-- Aktivitas Presensi Terkini (5 Entri Terakhir) -->
          <div class="card" style="padding:22px 24px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #f4f0e8;">
              <div class="chart-card-title">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <span>Aktivitas Presensi Terkini</span>
              </div>
              <button type="button" class="btn btn-outline btn-sm" onclick="switchNav('riwayat')" style="font-size:0.75rem;">
                <span>Buka Riwayat Lengkap</span>
                <i class="fa-solid fa-arrow-right"></i>
              </button>
            </div>

            <div class="table-responsive" style="border:none;box-shadow:none;">
              <table class="mini-table">
                <thead>
                  <tr>
                    <th>Personel</th>
                    <th>Waktu Presensi</th>
                    <th>Status</th>
                    <th style="text-align:right;">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                  $recent5 = array_slice($absensi, 0, 5);
                  if (!empty($recent5)): 
                    foreach ($recent5 as $r):
                      $isIzinR = in_array(strtolower($r['status']), ['izin', 'sakit', 'dispensasi']);
                      $rCat = !empty($r['catatan']) ? htmlspecialchars($r['catatan']) : '';
                      $lat1 = deg2rad($r['latitude']);
                      $lng1 = deg2rad($r['longitude']);
                      $lat2 = deg2rad($kampusLat);
                      $lng2 = deg2rad($kampusLng);
                      $dLat = $lat2 - $lat1;
                      $dLng = $lng2 - $lng1;
                      $a = sin($dLat/2) * sin($dLat/2) + cos($lat1) * cos($lat2) * sin($dLng/2) * sin($dLng/2);
                      $c = 2 * atan2(sqrt($a), sqrt(1-$a));
                      $dMeter = round(6371000 * $c);
                      $hFoto = !empty($r['foto']);
                      $fPath = $hFoto ? '../' . htmlspecialchars($r['foto']) : '';
                      $hFotoP = !empty($r['foto_pulang']);
                      $fPPath = $hFotoP ? '../' . htmlspecialchars($r['foto_pulang']) : '';
                      $wPlg = !empty($r['waktu_pulang']) ? htmlspecialchars($r['waktu_pulang']) : '';
                  ?>
                  <tr>
                    <td>
                      <div class="person-cell">
                        <div class="avatar-wrapper" style="width:34px;height:34px;" onclick="openInspectionModal('<?= htmlspecialchars(addslashes($r['nama'])) ?>', '<?= htmlspecialchars($r['nim']) ?>', '<?= $fPath ?>', '<?= $fPPath ?>', '<?= $r['waktu'] ?>', '<?= $wPlg ?>', '<?= round($r['latitude'], 6) ?>', '<?= round($r['longitude'], 6) ?>', '<?= strtoupper($r['status']) ?>', '<?= $dMeter ?>', '<?= addslashes($rCat) ?>')">
                          <?php if ($hFoto): ?>
                            <img src="<?= $fPath ?>" class="avatar-img" alt="Selfie" onerror="this.onerror=null;this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'34\' height=\'34\'%3E%3Crect width=\'34\' height=\'34\' fill=\'%23f7eff1\'/%3E%3C/svg%3E';">
                          <?php elseif ($isIzinR): ?>
                            <div class="avatar-placeholder" style="background:#fef7ea;color:#b8853b;width:34px;height:34px;font-size:0.85rem;"><i class="fa-solid fa-file-signature"></i></div>
                          <?php else: ?>
                            <div class="avatar-placeholder" style="width:34px;height:34px;font-size:0.85rem;"><i class="fa-solid fa-user"></i></div>
                          <?php endif; ?>
                        </div>
                        <div class="person-details">
                          <span class="person-name" style="font-size:0.82rem;"><?= htmlspecialchars($r['nama']) ?></span>
                          <span class="person-nim" style="font-size:0.7rem;"><?= htmlspecialchars($r['nim']) ?></span>
                        </div>
                      </div>
                    </td>
                    <td>
                      <div style="font-size:0.78rem;font-weight:600;color:var(--text-main);font-variant-numeric:tabular-nums;">
                        <?= htmlspecialchars(substr($r['waktu'], 11, 8)) ?>
                      </div>
                      <div style="font-size:0.7rem;color:var(--text-soft);">
                        <?= htmlspecialchars(substr($r['waktu'], 0, 10)) ?>
                      </div>
                    </td>
                    <td>
                      <span class="status-badge <?= $isIzinR ? 'izin' : strtolower($r['status']) ?>" style="font-size:0.7rem;padding:3px 8px;">
                        <span class="dot"></span>
                        <span><?= strtoupper($r['status']) ?></span>
                      </span>
                    </td>
                    <td style="text-align:right;">
                      <button class="btn btn-outline btn-sm" style="padding:4px 8px;font-size:0.72rem;" onclick="openInspectionModal('<?= htmlspecialchars(addslashes($r['nama'])) ?>', '<?= htmlspecialchars($r['nim']) ?>', '<?= $fPath ?>', '<?= $fPPath ?>', '<?= $r['waktu'] ?>', '<?= $wPlg ?>', '<?= round($r['latitude'], 6) ?>', '<?= round($r['longitude'], 6) ?>', '<?= strtoupper($r['status']) ?>', '<?= $dMeter ?>', '<?= addslashes($rCat) ?>')">
                        <i class="fa-regular fa-eye"></i>
                      </button>
                    </td>
                  </tr>
                  <?php endforeach; else: ?>
                  <tr>
                    <td colspan="4" style="text-align:center;padding:24px;color:var(--text-soft);font-size:0.8rem;">
                      Belum ada data presensi yang masuk.
                    </td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Parameter Geofence & Status Sistem Ringkas -->
          <div class="card" style="padding:22px 24px;display:flex;flex-direction:column;justify-content:space-between;">
            <div>
              <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #f4f0e8;">
                <div class="chart-card-title">
                  <i class="fa-solid fa-shield-halved"></i>
                  <span>Status Geofence & Aturan</span>
                </div>
                <span class="badge" style="background:#edf6f1;color:#1e5a40;border:1px solid #bfe0cf;font-size:0.72rem;padding:3px 8px;border-radius:999px;font-weight:700;">
                  <i class="fa-solid fa-circle-check" style="font-size:0.65rem;"></i> AKTIF
                </span>
              </div>

              <div style="display:flex;flex-direction:column;gap:12px;font-size:0.8rem;">
                <div style="display:flex;justify-content:space-between;padding-bottom:8px;border-bottom:1px solid #f4f0e8;">
                  <span style="color:var(--text-muted);">Radius Maksimal:</span>
                  <span style="font-weight:700;color:var(--text-main);font-variant-numeric:tabular-nums;"><?= $maxRadius ?> Meter</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding-bottom:8px;border-bottom:1px solid #f4f0e8;">
                  <span style="color:var(--text-muted);">Batas Jam Masuk:</span>
                  <span style="font-weight:700;color:var(--text-main);"><?= str_pad($batasJam, 2, '0', STR_PAD_LEFT) ?>:<?= str_pad($batasMenit, 2, '0', STR_PAD_LEFT) ?> WIB</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding-bottom:8px;border-bottom:1px solid #f4f0e8;">
                  <span style="color:var(--text-muted);">Koordinat Pusat:</span>
                  <span style="font-weight:700;color:var(--text-main);font-family:monospace;"><?= round($kampusLat, 4) ?>, <?= round($kampusLng, 4) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;padding-bottom:8px;">
                  <span style="color:var(--text-muted);">Total Mahasiswa:</span>
                  <span style="font-weight:700;color:var(--text-main);"><?= $mahasiswa ?> Personel</span>
                </div>
              </div>
            </div>

            <div style="margin-top:20px;">
              <button type="button" class="btn btn-outline" style="width:100%;font-size:0.8rem;gap:6px;" onclick="switchNav('settings')">
                <i class="fa-solid fa-sliders" style="color:var(--gold-dark);"></i>
                <span>Buka Pengaturan Geofence Lengkap</span>
              </button>
            </div>
          </div>

        </div>

      </section>

      <!-- ===================== TAB RIWAYAT PRESENSI ===================== -->
      <section id="tab-riwayat" class="tab-panel">
        
        <!-- LOG PRESENSI TABLE CONTAINER -->
        <div class="table-container">
          
          <div class="table-toolbar">
            <div class="toolbar-left">
              <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="table-search" class="search-input" placeholder="Cari nama atau NIM personel…" oninput="filterTable()" />
              </div>

              <div class="filter-tabs">
                <button class="filter-btn active" data-status="all" onclick="setStatusFilter('all', this)">Semua (<?= $total ?>)</button>
                <button class="filter-btn" data-status="hadir" onclick="setStatusFilter('hadir', this)">Tepat Waktu (<?= $hadir ?>)</button>
                <button class="filter-btn" data-status="terlambat" onclick="setStatusFilter('terlambat', this)">Terlambat (<?= $terlambat ?>)</button>
                <button class="filter-btn" data-status="izin" onclick="setStatusFilter('izin', this)">Izin / Sakit (<?= $izinCount ?>)</button>
              </div>
            </div>

            <div class="toolbar-right">
              <span style="font-size:0.76rem;color:var(--text-muted);font-weight:600;" id="table-count-text">
                Menampilkan <?= count($absensi) ?> data
              </span>
            </div>
          </div>

          <div class="table-responsive">
            <table id="attendance-table">
              <thead>
                <tr>
                  <th style="width:60px;">No</th>
                  <th>Personel Mahasiswa</th>
                  <th>Jam Masuk & Pulang</th>
                  <th>Koordinat Presensi</th>
                  <th>Jarak ke Pusat</th>
                  <th>Status Verifikasi</th>
                  <th style="text-align:right;">Aksi</th>
                </tr>
              </thead>
              <tbody id="table-tbody">
                <?php if (!empty($absensi)): ?>
                  <?php foreach ($absensi as $idx => $row): 
                    $isIzinRow = in_array(strtolower($row['status']), ['izin', 'sakit', 'dispensasi']);
                    $rowCatatan = !empty($row['catatan']) ? htmlspecialchars($row['catatan']) : '';
                    // Hitung jarak perkiraan Haversine
                    $lat1 = deg2rad($row['latitude']);
                    $lng1 = deg2rad($row['longitude']);
                    $lat2 = deg2rad($kampusLat);
                    $lng2 = deg2rad($kampusLng);
                    $dLat = $lat2 - $lat1;
                    $dLng = $lng2 - $lng1;
                    $a = sin($dLat/2) * sin($dLat/2) + cos($lat1) * cos($lat2) * sin($dLng/2) * sin($dLng/2);
                    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
                    $distMeter = round(6371000 * $c);
                    $hasFoto = !empty($row['foto']);
                    $fotoPath = $hasFoto ? '../' . htmlspecialchars($row['foto']) : '';
                    $hasFotoPulang = !empty($row['foto_pulang']);
                    $fotoPulangPath = $hasFotoPulang ? '../' . htmlspecialchars($row['foto_pulang']) : '';
                    $waktuPulang = !empty($row['waktu_pulang']) ? htmlspecialchars($row['waktu_pulang']) : '';
                    $rowFilterStatus = $isIzinRow ? 'izin' : strtolower($row['status']);
                  ?>
                  <tr class="table-row" data-status="<?= $rowFilterStatus ?>" data-search="<?= strtolower(htmlspecialchars($row['nama'] . ' ' . $row['nim'])) ?>">
                    <td style="color:var(--text-soft);font-variant-numeric:tabular-nums;"><?= $idx + 1 ?></td>
                    <td>
                      <div class="person-cell">
                        <div class="avatar-wrapper" onclick="openInspectionModal('<?= htmlspecialchars(addslashes($row['nama'])) ?>', '<?= htmlspecialchars($row['nim']) ?>', '<?= $fotoPath ?>', '<?= $fotoPulangPath ?>', '<?= $row['waktu'] ?>', '<?= $waktuPulang ?>', '<?= round($row['latitude'], 6) ?>', '<?= round($row['longitude'], 6) ?>', '<?= strtoupper($row['status']) ?>', '<?= $distMeter ?>', '<?= addslashes($rowCatatan) ?>')">
                          <?php if ($hasFoto): ?>
                            <img src="<?= $fotoPath ?>" class="avatar-img" alt="Selfie" onerror="this.onerror=null;this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'40\' height=\'40\'%3E%3Crect width=\'40\' height=\'40\' fill=\'%23f7eff1\'/%3E%3C/svg%3E';">
                          <?php elseif ($isIzinRow): ?>
                            <div class="avatar-placeholder" style="background:#fef7ea;color:#b8853b;border-color:rgba(212,163,98,0.4);"><i class="fa-solid fa-file-signature"></i></div>
                          <?php else: ?>
                            <div class="avatar-placeholder"><i class="fa-solid fa-user"></i></div>
                          <?php endif; ?>
                        </div>
                        <div class="person-details">
                          <span class="person-name"><?= htmlspecialchars($row['nama']) ?></span>
                          <span class="person-nim"><?= htmlspecialchars($row['nim']) ?></span>
                        </div>
                      </div>
                    </td>
                    <td>
                      <?php if ($isIzinRow): ?>
                        <div style="display:flex;flex-direction:column;gap:3px;">
                          <div class="time-cell">
                            <i class="fa-solid fa-file-pen" style="color:var(--gold-dark);font-size:0.75rem;"></i>
                            <span><b>Izin:</b> <?= htmlspecialchars($row['waktu']) ?></span>
                          </div>
                          <span style="font-size:0.72rem;color:var(--text-soft);font-style:italic;">Tidak Perlu Presensi Pulang</span>
                        </div>
                      <?php else: ?>
                        <div style="display:flex;flex-direction:column;gap:4px;">
                          <div class="time-cell">
                            <i class="fa-solid fa-arrow-right-to-bracket" style="color:var(--gold-dark);font-size:0.75rem;"></i>
                            <span><b>Masuk:</b> <?= htmlspecialchars($row['waktu']) ?></span>
                          </div>
                          <div class="time-cell">
                            <i class="fa-solid fa-arrow-right-from-bracket" style="color:<?= $waktuPulang ? '#1e5a40' : 'var(--text-soft)' ?>;font-size:0.75rem;"></i>
                            <span>
                              <?php if ($waktuPulang): ?>
                                <b style="color:#1e5a40;">Pulang:</b> <?= $waktuPulang ?>
                              <?php else: ?>
                                <span style="color:var(--text-soft);font-style:italic;font-size:0.75rem;">Belum Pulang</span>
                              <?php endif; ?>
                            </span>
                          </div>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($isIzinRow): ?>
                        <span style="color:var(--text-soft);font-style:italic;font-size:0.76rem;">Tidak Hadir (Izin)</span>
                      <?php else: ?>
                        <div class="gps-cell">
                          <span><?= round($row['latitude'], 5) ?></span>, 
                          <span><?= round($row['longitude'], 5) ?></span>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($isIzinRow): ?>
                        <span style="color:var(--text-soft);font-size:0.76rem;">-</span>
                      <?php else: ?>
                        <span class="gps-dist">
                          <i class="fa-solid fa-location-crosshairs"></i>
                          <span><?= $distMeter ?> m</span>
                        </span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div style="display:flex;flex-direction:column;gap:4px;">
                        <span class="status-badge <?= $isIzinRow ? 'izin' : strtolower($row['status']) ?>">
                          <span class="dot"></span>
                          <span><?= strtoupper($row['status']) ?></span>
                        </span>
                        <?php if ($rowCatatan): ?>
                          <div style="font-size:0.71rem;color:var(--text-muted);font-style:italic;max-width:170px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= $rowCatatan ?>">
                            <i class="fa-solid fa-message" style="margin-right:3px;font-size:0.65rem;color:var(--gold-dark);"></i><?= $rowCatatan ?>
                          </div>
                        <?php endif; ?>
                        <?php if ($waktuPulang): ?>
                          <span class="badge" style="background:#eef6f2;color:#1e5a40;border:1px solid #c2e2d1;font-size:0.68rem;padding:2px 6px;width:fit-content;border-radius:4px;font-weight:700;">
                            <i class="fa-solid fa-check" style="font-size:0.65rem;margin-right:2px;"></i> PULANG LENGKAP
                          </span>
                        <?php endif; ?>
                      </div>
                    </td>
                    <td style="text-align:right;">
                      <button class="btn btn-outline btn-sm" onclick="openInspectionModal('<?= htmlspecialchars(addslashes($row['nama'])) ?>', '<?= htmlspecialchars($row['nim']) ?>', '<?= $fotoPath ?>', '<?= $fotoPulangPath ?>', '<?= $row['waktu'] ?>', '<?= $waktuPulang ?>', '<?= round($row['latitude'], 6) ?>', '<?= round($row['longitude'], 6) ?>', '<?= strtoupper($row['status']) ?>', '<?= $distMeter ?>', '<?= addslashes($rowCatatan) ?>')">
                        <i class="fa-regular fa-eye"></i>
                        <span>Detail</span>
                      </button>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr id="no-data-row">
                    <td colspan="7" style="text-align:center;padding:48px 20px;">
                      <div class="empty-state-box">
                        <div class="empty-state-icon">
                          <i class="fa-solid fa-clipboard-check"></i>
                        </div>
                        <h4 class="empty-state-title">Belum Ada Presensi Masuk Hari Ini</h4>
                        <p class="empty-state-desc">
                          Data kehadiran personel yang diverifikasi melalui sensor GPS geofence dan snapshot selfie kamera akan otomatis ditampilkan secara langsung di sini.
                        </p>
                        <div class="empty-state-actions">
                          <a href="../frontend/index.html" target="_blank" class="btn btn-outline btn-sm">
                            <i class="fa-solid fa-mobile-screen"></i> Buka Portal Presensi Mobile
                          </a>
                          <button type="button" class="btn btn-luxury btn-sm" onclick="location.reload()">
                            <i class="fa-solid fa-arrows-rotate"></i> Muat Ulang Data
                          </button>
                        </div>
                      </div>
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <div class="table-footer">
            <span>Catatan terverifikasi aman dengan geofencing & selfie snapshot</span>
            <span>Maksimal 50 entri data terakhir</span>
          </div>

        </div>

      </section>

      <!-- ===================== TAB SETTINGS ===================== -->
      <section id="tab-settings" class="tab-panel">
        
        <div id="toast-alert" class="toast-alert">
          <i class="fa-solid fa-circle-info" id="toast-icon"></i>
          <span id="toast-msg"></span>
        </div>

        <div class="settings-layout">
          
          <!-- KIRI: FORM PENGATURAN -->
          <div class="card">
            <div class="card-title">
              <i class="fa-solid fa-sliders"></i>
              <span>Parameter Geofencing & Jam Masuk</span>
            </div>

            <div class="input-row">
              <div class="form-group">
                <label class="form-label">
                  <span>Titik Latitude Pusat</span>
                  <span>-90 s/d 90</span>
                </label>
                <div class="input-wrapper">
                  <i class="fa-solid fa-compass"></i>
                  <input type="number" id="f-lat" class="form-input" step="0.000001" min="-90" max="90"
                         value="<?= htmlspecialchars((string)$kampusLat) ?>"
                         oninput="updateMapFromForm()" />
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">
                  <span>Titik Longitude Pusat</span>
                  <span>-180 s/d 180</span>
                </label>
                <div class="input-wrapper">
                  <i class="fa-solid fa-compass"></i>
                  <input type="number" id="f-lng" class="form-input" step="0.000001" min="-180" max="180"
                         value="<?= htmlspecialchars((string)$kampusLng) ?>"
                         oninput="updateMapFromForm()" />
                </div>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">
                <span>Radius Maksimal Zona Presensi</span>
                <span>Satuan Meter</span>
              </label>
              <div class="range-box">
                <div class="range-header">
                  <span>Geser Jangkauan Radius</span>
                  <span class="range-val" id="radius-val-label"><?= $maxRadius ?> meter</span>
                </div>
                <input type="range" id="f-radius" min="20" max="2000" step="10"
                       value="<?= $maxRadius ?>"
                       oninput="onRadiusChange(this.value)" />
              </div>
            </div>

            <div class="input-row">
              <div class="form-group">
                <label class="form-label">
                  <span>Batas Jam Presensi</span>
                  <span>0 s/d 23 WIB</span>
                </label>
                <div class="input-wrapper">
                  <i class="fa-regular fa-clock"></i>
                  <input type="number" id="f-jam" class="form-input" min="0" max="23" step="1"
                         value="<?= $batasJam ?>"
                         oninput="updateCutoffLabel()" />
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">
                  <span>Batas Menit Presensi</span>
                  <span>0 s/d 59 Menit</span>
                </label>
                <div class="input-wrapper">
                  <i class="fa-regular fa-clock"></i>
                  <input type="number" id="f-menit" class="form-input" min="0" max="59" step="1"
                         value="<?= $batasMenit ?>"
                         oninput="updateCutoffLabel()" />
                </div>
              </div>
            </div>

            <div class="cutoff-preview">
              <i class="fa-solid fa-shield-halved" style="color:var(--gold-dark);margin-right:6px;"></i>
              Ketentuan Aturan: Mahasiswa yang melakukan absensi sebelum atau tepat pukul <b id="cutoff-preview-time"><?= str_pad($batasJam, 2, '0', STR_PAD_LEFT) ?>:<?= str_pad($batasMenit, 2, '0', STR_PAD_LEFT) ?> WIB</b> akan diverifikasi berstatus <b>HADIR</b>. Presensi setelah jam tersebut secara otomatis dikategorikan <b>TERLAMBAT</b>.
            </div>

            <button class="btn btn-luxury" id="btn-simpan" style="width:100%;padding:12px;" onclick="simpanSettings()">
              <i class="fa-solid fa-floppy-disk"></i>
              <span>Simpan Seluruh Perubahan Konfigurasi</span>
            </button>
          </div>

          <!-- KANAN: PETA INTERAKTIF & SENSOR GPS -->
          <div class="card">
            <div class="card-title">
              <i class="fa-solid fa-map-location-dot"></i>
              <span>Visualisasi Geofence & Sensor Lokasi</span>
            </div>

            <div class="gps-hud">
              <div class="hud-row">
                <span class="hud-label">Status Sensor GPS Perangkat:</span>
                <span class="hud-val" id="gps-status" style="color:var(--sage-corp);">Mendeteksi…</span>
              </div>
              <div class="hud-row">
                <span class="hud-label">Koordinat Terdeteksi:</span>
                <span class="hud-val" id="gps-coord">–</span>
              </div>
              <div class="hud-row">
                <span class="hud-label">Tingkat Akurasi Sensor:</span>
                <span class="hud-val" id="gps-akurasi">–</span>
              </div>
            </div>

            <button class="btn btn-outline" style="width:100%;margin-bottom:12px;" onclick="gunakanLokasiSaatIni()">
              <i class="fa-solid fa-location-crosshairs" style="color:var(--gold-dark);"></i>
              <span>Gunakan Lokasi GPS Saya Saat Ini Sebagai Titik Pusat</span>
            </button>

            <div id="settings-map"></div>
            
            <p style="font-size:0.75rem;color:var(--text-soft);margin-top:10px;line-height:1.4;">
              <i class="fa-solid fa-circle-info" style="margin-right:4px;"></i>
              Klik atau geser pin di atas bidang peta untuk menyetel koordinat lokasi kampus secara visual.
            </p>
          </div>

        </div>

      </section>

    </main>
  </div>

  <!-- INSPECTION DETAIL MODAL -->
  <div class="modal-backdrop" id="inspectionModal" onclick="closeModalOnBackdrop(event)">
    <div class="modal-card">
      <div class="modal-header">
        <h3><i class="fa-solid fa-id-card" style="color:var(--gold-primary);"></i> Bukti Presensi Personel</h3>
        <button class="modal-close" onclick="closeInspectionModal()"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="modal-body">
        
        <!-- Foto Switch Tabs jika ada Masuk dan Pulang -->
        <div id="modal-foto-switch-wrap" class="modal-foto-switch" style="display:none;">
          <button type="button" class="btn-foto-switch active" id="btn-foto-masuk" onclick="switchModalPhoto('masuk')">
            <i class="fa-solid fa-arrow-right-to-bracket"></i> Foto Masuk
          </button>
          <button type="button" class="btn-foto-switch" id="btn-foto-pulang" onclick="switchModalPhoto('pulang')">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> Foto Pulang
          </button>
        </div>

        <div class="modal-foto-frame" id="modal-foto-container">
          <div class="modal-foto-badge" id="modal-foto-badge">
            <i class="fa-solid fa-camera"></i> <span id="modal-foto-type">Foto Masuk</span>
          </div>
          <img id="modal-img" src="" alt="Bukti Selfie">
        </div>

        <div class="modal-info-grid">
          <div class="modal-info-row">
            <span class="modal-info-label">Nama Mahasiswa:</span>
            <span class="modal-info-value" id="modal-nama">-</span>
          </div>
          <div class="modal-info-row">
            <span class="modal-info-label">Nomor Induk Mahasiswa (NIM):</span>
            <span class="modal-info-value" id="modal-nim" style="font-family:monospace;">-</span>
          </div>
          <div class="modal-info-row">
            <span class="modal-info-label">Waktu Presensi Masuk:</span>
            <span class="modal-info-value" id="modal-waktu">-</span>
          </div>
          <div class="modal-info-row">
            <span class="modal-info-label">Waktu Presensi Pulang:</span>
            <span class="modal-info-value" id="modal-waktu-pulang" style="font-weight:700;">-</span>
          </div>
          <div class="modal-info-row">
            <span class="modal-info-label">Koordinat Presensi:</span>
            <span class="modal-info-value" id="modal-coord" style="font-family:monospace;">-</span>
          </div>
          <div class="modal-info-row">
            <span class="modal-info-label">Jarak ke Pusat Radius:</span>
            <span class="modal-info-value" id="modal-dist">-</span>
          </div>
          <div class="modal-info-row">
            <span class="modal-info-label">Status Verifikasi:</span>
            <span class="modal-info-value" id="modal-status">-</span>
          </div>
          <div class="modal-info-row" id="modal-row-catatan" style="display:none;">
            <span class="modal-info-label">Catatan / Alasan:</span>
            <span class="modal-info-value" id="modal-catatan" style="font-weight:700;color:var(--text-main);">-</span>
          </div>
        </div>
      </div>

      <!-- STICKY FOOTER (SELALU KELIHATAN DI BAWAH) -->
      <div class="modal-footer">
        <button class="btn btn-outline" style="width:100%;display:flex;align-items:center;justify-content:center;gap:8px;" onclick="closeInspectionModal()">
          <i class="fa-solid fa-xmark"></i>
          <span>Tutup Detail Bukti</span>
        </button>
      </div>
    </div>
  </div>

  <!-- LEAFLET & LOGIC SCRIPTS -->
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    // ============================================
    // LIVE CLOCK
    // ============================================
    setInterval(() => {
      const now = new Date();
      document.getElementById('live-clock').textContent = now.toLocaleString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit', second: '2-digit'
      });
    }, 1000);

    // ============================================
    // NAVIGATION SWITCHING
    // ============================================
    function switchNav(tabName) {
      document.getElementById('nav-btn-dashboard').classList.toggle('active', tabName === 'dashboard');
      document.getElementById('nav-btn-riwayat').classList.toggle('active', tabName === 'riwayat');
      document.getElementById('nav-btn-settings').classList.toggle('active', tabName === 'settings');

      document.getElementById('tab-dashboard').classList.toggle('active', tabName === 'dashboard');
      document.getElementById('tab-riwayat').classList.toggle('active', tabName === 'riwayat');
      document.getElementById('tab-settings').classList.toggle('active', tabName === 'settings');

      let crumb = 'Dashboard Utama';
      if (tabName === 'riwayat') crumb = 'Riwayat Presensi';
      else if (tabName === 'settings') crumb = 'Konfigurasi Geofence';
      document.getElementById('page-title-crumb').textContent = crumb;

      if (tabName === 'dashboard') {
        setTimeout(initDashboardCharts, 80);
      } else if (tabName === 'settings') {
        setTimeout(initSettingsMap, 100);
      }

      // Close mobile sidebar if open
      document.getElementById('sidebar').classList.remove('open');
    }

    function toggleSidebar() {
      document.getElementById('sidebar').classList.toggle('open');
    }

    // ============================================
    // GRAFIK KEHADIRAN (CHART.JS CLEAN LOOK)
    // ============================================
    let trendChartInstance = null;
    let donutChartInstance = null;

    function initDashboardCharts() {
      // Data dari agregasi PHP
      const labels = <?= $chartLabelsJson ?>;
      const dataHadir = <?= $chartHadirJson ?>;
      const dataTerlambat = <?= $chartTerlambatJson ?>;
      const dataIzin = <?= $chartIzinJson ?>;

      // 1. Line/Area Trend Chart
      const trendCtx = document.getElementById('chart-trend-canvas');
      if (trendCtx) {
        if (trendChartInstance) {
          trendChartInstance.destroy();
        }
        
        trendChartInstance = new Chart(trendCtx, {
          type: 'line',
          data: {
            labels: labels,
            datasets: [
              {
                label: 'Tepat Waktu',
                data: dataHadir,
                borderColor: '#1e5a40',
                backgroundColor: 'rgba(30, 90, 64, 0.12)',
                borderWidth: 2.5,
                fill: true,
                tension: 0.35,
                pointBackgroundColor: '#1e5a40',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 4.5,
                pointHoverRadius: 6.5
              },
              {
                label: 'Terlambat',
                data: dataTerlambat,
                borderColor: '#9c2436',
                backgroundColor: 'rgba(156, 36, 54, 0.08)',
                borderWidth: 2.5,
                fill: true,
                tension: 0.35,
                pointBackgroundColor: '#9c2436',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 4.5,
                pointHoverRadius: 6.5
              },
              {
                label: 'Izin / Sakit',
                data: dataIzin,
                borderColor: '#d4a362',
                backgroundColor: 'rgba(212, 163, 98, 0.08)',
                borderWidth: 2,
                borderDash: [5, 4],
                fill: false,
                tension: 0.35,
                pointBackgroundColor: '#d4a362',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
              }
            ]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
              intersect: false,
              mode: 'index'
            },
            plugins: {
              legend: {
                position: 'top',
                align: 'end',
                labels: {
                  boxWidth: 10,
                  boxHeight: 10,
                  usePointStyle: true,
                  pointStyle: 'circle',
                  font: { family: "'Plus Jakarta Sans', sans-serif", size: 11, weight: '600' },
                  color: '#5e5750',
                  padding: 12
                }
              },
              tooltip: {
                backgroundColor: 'rgba(20, 18, 16, 0.92)',
                titleFont: { family: "'Plus Jakarta Sans', sans-serif", size: 12, weight: '700' },
                bodyFont: { family: "'Plus Jakarta Sans', sans-serif", size: 11.5 },
                padding: 10,
                cornerRadius: 8,
                boxPadding: 4
              }
            },
            scales: {
              x: {
                grid: { display: false },
                ticks: {
                  font: { family: "'Plus Jakarta Sans', sans-serif", size: 11 },
                  color: '#8e8479'
                }
              },
              y: {
                beginAtZero: true,
                ticks: {
                  precision: 0,
                  stepSize: 1,
                  font: { family: "'Plus Jakarta Sans', sans-serif", size: 11 },
                  color: '#8e8479'
                },
                grid: {
                  color: 'rgba(22, 19, 17, 0.05)',
                  drawBorder: false
                }
              }
            }
          }
        });
      }

      // 2. Donut Distribution Chart
      const donutCtx = document.getElementById('chart-donut-canvas');
      if (donutCtx) {
        if (donutChartInstance) {
          donutChartInstance.destroy();
        }
        
        const totalLog = <?= (int)$total ?>;
        const donutData = totalLog > 0 
          ? [<?= (int)$hadir ?>, <?= (int)$terlambat ?>, <?= (int)$izinCount ?>]
          : [0, 0, 0];

        donutChartInstance = new Chart(donutCtx, {
          type: 'doughnut',
          data: {
            labels: ['Tepat Waktu', 'Terlambat', 'Izin / Sakit'],
            datasets: [{
              data: totalLog > 0 ? donutData : [1],
              backgroundColor: totalLog > 0 
                ? ['#1e5a40', '#9c2436', '#d4a362']
                : ['#e4ded5'],
              borderWidth: 2,
              borderColor: '#ffffff',
              hoverOffset: 5
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            plugins: {
              legend: { display: false },
              tooltip: {
                enabled: (totalLog > 0),
                backgroundColor: 'rgba(20, 18, 16, 0.92)',
                titleFont: { family: "'Plus Jakarta Sans', sans-serif", size: 12, weight: '700' },
                bodyFont: { family: "'Plus Jakarta Sans', sans-serif", size: 11.5 },
                padding: 10,
                cornerRadius: 8
              }
            }
          }
        });
      }
    }

    // Inisialisasi Chart saat halaman dimuat
    document.addEventListener('DOMContentLoaded', () => {
      initDashboardCharts();
    });

    // ============================================
    // LIVE TABLE FILTER & SEARCH
    // ============================================
    let currentStatusFilter = 'all';

    function setStatusFilter(status, btn) {
      currentStatusFilter = status;
      document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      filterTable();
    }

    function filterTable() {
      const query = (document.getElementById('table-search').value || '').toLowerCase().trim();
      const rows = document.querySelectorAll('.table-row');
      let visibleCount = 0;

      rows.forEach(row => {
        const rowStatus = row.getAttribute('data-status');
        const rowSearch = row.getAttribute('data-search');

        const matchStatus = (currentStatusFilter === 'all' || rowStatus === currentStatusFilter);
        const matchSearch = (!query || rowSearch.includes(query));

        if (matchStatus && matchSearch) {
          row.style.display = '';
          visibleCount++;
        } else {
          row.style.display = 'none';
        }
      });

      document.getElementById('table-count-text').textContent = `Menampilkan ${visibleCount} data`;
    }

    // ============================================
    // CSV EXPORT
    // ============================================
    function exportCSV() {
      const rows = document.querySelectorAll('.table-row');
      if (!rows.length) {
        alert('Tidak ada data untuk diekspor.');
        return;
      }

      let csv = 'No,Nama,NIM,Waktu,Koordinat,Jarak,Status\n';
      let idx = 1;

      rows.forEach(row => {
        if (row.style.display !== 'none') {
          const name = row.querySelector('.person-name')?.textContent.trim() || '';
          const nim  = row.querySelector('.person-nim')?.textContent.trim() || '';
          const timeCells = row.querySelectorAll('.time-cell span');
          const timeMasuk = timeCells[0]?.textContent.replace('Masuk:', '').trim() || '';
          const timePulang = timeCells[1]?.textContent.replace('Pulang:', '').trim() || '';
          const gps  = row.querySelector('.gps-cell')?.textContent.replace(/\s+/g, ' ').trim() || '';
          const dist = row.querySelector('.gps-dist span')?.textContent.trim() || '';
          const stat = row.querySelector('.status-badge span:last-child')?.textContent.trim() || '';

          csv += `"${idx}","${name}","${nim}","${timeMasuk}","${timePulang}","${gps}","${dist}","${stat}"\n`;
          idx++;
        }
      });

      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `Presensi_Attendance_${new Date().toISOString().slice(0, 10)}.csv`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
    }

    // ============================================
    // INSPECTION DETAIL MODAL
    // ============================================
    let curFotoMasuk = '';
    let curFotoPulang = '';

    function openInspectionModal(nama, nim, foto, fotoPulang, waktu, waktuPulang, lat, lng, status, dist, catatan) {
      document.getElementById('modal-nama').textContent = nama;
      document.getElementById('modal-nim').textContent  = nim;
      document.getElementById('modal-waktu').textContent = waktu;
      document.getElementById('modal-waktu-pulang').innerHTML = waktuPulang
        ? `<span style="color:#1e5a40;font-weight:700;"><i class="fa-solid fa-circle-check" style="margin-right:4px;"></i>${waktuPulang}</span>`
        : `<span style="color:#8e8479;font-style:italic;">Belum Melakukan Presensi Pulang</span>`;
      document.getElementById('modal-coord').textContent = `${lat}, ${lng}`;
      document.getElementById('modal-dist').textContent  = (dist && dist !== '-') ? `${dist} meter dari pusat` : '-';
      document.getElementById('modal-status').textContent = status;

      const catRow = document.getElementById('modal-row-catatan');
      const catVal = document.getElementById('modal-catatan');
      if (catRow && catVal) {
        if (catatan && catatan.trim() !== '') {
          catVal.textContent = catatan;
          catRow.style.display = 'flex';
        } else {
          catRow.style.display = 'none';
        }
      }

      curFotoMasuk = (foto && foto.trim() !== '') ? foto : '';
      curFotoPulang = (fotoPulang && fotoPulang.trim() !== '') ? fotoPulang : '';

      const switchWrap = document.getElementById('modal-foto-switch-wrap');
      const container = document.getElementById('modal-foto-container');
      const imgEl = document.getElementById('modal-img');

      if (curFotoMasuk && curFotoPulang) {
        switchWrap.style.display = 'flex';
        switchModalPhoto('masuk');
      } else if (curFotoMasuk) {
        switchWrap.style.display = 'none';
        switchModalPhoto('masuk');
      } else if (curFotoPulang) {
        switchWrap.style.display = 'none';
        switchModalPhoto('pulang');
      } else {
        switchWrap.style.display = 'none';
        container.style.display = 'none';
        imgEl.style.display = 'none';
      }

      document.getElementById('inspectionModal').classList.add('open');
    }

    function switchModalPhoto(type) {
      const container = document.getElementById('modal-foto-container');
      const imgEl = document.getElementById('modal-img');
      const badge = document.getElementById('modal-foto-type');
      const btnMasuk = document.getElementById('btn-foto-masuk');
      const btnPulang = document.getElementById('btn-foto-pulang');

      if (type === 'pulang' && curFotoPulang) {
        imgEl.src = curFotoPulang;
        imgEl.style.display = 'block';
        container.style.display = 'flex';
        badge.textContent = 'Foto Pulang';
        if (btnMasuk) btnMasuk.classList.remove('active');
        if (btnPulang) btnPulang.classList.add('active');
      } else if (curFotoMasuk) {
        imgEl.src = curFotoMasuk;
        imgEl.style.display = 'block';
        container.style.display = 'flex';
        badge.textContent = 'Foto Masuk';
        if (btnMasuk) btnMasuk.classList.add('active');
        if (btnPulang) btnPulang.classList.remove('active');
      }
    }

    function closeInspectionModal() {
      document.getElementById('inspectionModal').classList.remove('open');
    }

    function closeModalOnBackdrop(e) {
      if (e.target.id === 'inspectionModal') {
        closeInspectionModal();
      }
    }

    // ============================================
    // SETTINGS MAP & GEOFENCE
    // ============================================
    let sMap = null, sMarker = null, sCircle = null;
    let gpsLat = null, gpsLng = null;

    function initSettingsMap() {
      if (sMap) {
        sMap.invalidateSize();
        return;
      }

      const lat = parseFloat(document.getElementById('f-lat').value) || <?= $kampusLat ?>;
      const lng = parseFloat(document.getElementById('f-lng').value) || <?= $kampusLng ?>;
      const rad = parseInt(document.getElementById('f-radius').value) || <?= $maxRadius ?>;

      sMap = L.map('settings-map').setView([lat, lng], 16);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors', maxZoom: 19
      }).addTo(sMap);

      const markerHtml = `
        <div style="width:40px;height:40px;background:linear-gradient(135deg, #dfa86a, #c7924d);border:2.5px solid #ffffff;box-shadow:0 6px 20px rgba(199,146,77,0.45);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#141210;font-size:16px;">
          <i class="fa-solid fa-building-columns"></i>
        </div>
      `;

      sMarker = L.marker([lat, lng], {
        draggable: true,
        icon: L.divIcon({ className: '', html: markerHtml, iconAnchor: [20, 40] })
      }).addTo(sMap).bindPopup('<b>Pusat Lokasi Geofence</b><br>Geser untuk menentukan koordinat');

      sCircle = L.circle([lat, lng], {
        radius: rad,
        color: '#d4a362',
        fillColor: '#d4a362',
        fillOpacity: 0.16,
        weight: 2
      }).addTo(sMap);

      sMarker.on('dragend', e => {
        const p = e.target.getLatLng();
        document.getElementById('f-lat').value = p.lat.toFixed(6);
        document.getElementById('f-lng').value = p.lng.toFixed(6);
        sCircle.setLatLng(p);
      });

      sMap.on('click', e => {
        sMarker.setLatLng(e.latlng);
        sCircle.setLatLng(e.latlng);
        document.getElementById('f-lat').value = e.latlng.lat.toFixed(6);
        document.getElementById('f-lng').value = e.latlng.lng.toFixed(6);
      });

      detectGPS();
    }

    function onRadiusChange(val) {
      document.getElementById('radius-val-label').textContent = `${val} meter`;
      if (sCircle) {
        sCircle.setRadius(parseInt(val) || 100);
      }
    }

    function updateMapFromForm() {
      if (!sMap) return;
      const lat = parseFloat(document.getElementById('f-lat').value);
      const lng = parseFloat(document.getElementById('f-lng').value);
      const rad = parseInt(document.getElementById('f-radius').value) || 100;

      if (isNaN(lat) || isNaN(lng)) return;

      sMarker.setLatLng([lat, lng]);
      sCircle.setLatLng([lat, lng]);
      sCircle.setRadius(rad);
      sMap.setView([lat, lng], sMap.getZoom());
    }

    function updateCutoffLabel() {
      const j = String(document.getElementById('f-jam').value || '8').padStart(2, '0');
      const m = String(document.getElementById('f-menit').value || '0').padStart(2, '0');
      document.getElementById('cutoff-preview-time').textContent = `${j}:${m} WIB`;
    }

    // ============================================
    // GPS SENSOR DETECTION
    // ============================================
    function detectGPS() {
      if (!navigator.geolocation) {
        document.getElementById('gps-status').textContent = 'Tidak didukung perangkat ini';
        return;
      }

      document.getElementById('gps-status').textContent = 'Mendeteksi sensor…';

      navigator.geolocation.getCurrentPosition(
        pos => {
          gpsLat = pos.coords.latitude;
          gpsLng = pos.coords.longitude;
          document.getElementById('gps-status').textContent  = 'Aktif & Terkunci';
          document.getElementById('gps-coord').textContent   = `${gpsLat.toFixed(6)}, ${gpsLng.toFixed(6)}`;
          document.getElementById('gps-akurasi').textContent = `±${Math.round(pos.coords.accuracy)} meter`;
        },
        err => {
          const msg = err.code === 1 ? 'Izin Akses Ditolak' : err.code === 2 ? 'Posisi Tidak Ditemukan' : 'Waktu Habis';
          document.getElementById('gps-status').textContent = `Tidak Aktif (${msg})`;
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
      );
    }

    function gunakanLokasiSaatIni() {
      if (gpsLat === null) {
        detectGPS();
        setTimeout(() => {
          if (gpsLat !== null) {
            terapkanGPS();
          } else {
            showToast('error', 'Sensor lokasi belum terkunci. Mohon izinkan akses lokasi pada peramban.');
          }
        }, 3000);
        return;
      }
      terapkanGPS();
    }

    function terapkanGPS() {
      document.getElementById('f-lat').value = gpsLat.toFixed(6);
      document.getElementById('f-lng').value = gpsLng.toFixed(6);
      if (sMap) {
        sMarker.setLatLng([gpsLat, gpsLng]);
        sCircle.setLatLng([gpsLat, gpsLng]);
        sMap.setView([gpsLat, gpsLng], 17);
      }
      showToast('success', 'Koordinat pusat berhasil disinkronkan dengan posisi GPS Anda.');
    }

    // ============================================
    // SIMPAN SETTINGS VIA AJAX
    // ============================================
    async function simpanSettings() {
      const btn = document.getElementById('btn-simpan');
      const payload = {
        kampus_lat:  document.getElementById('f-lat').value,
        kampus_lng:  document.getElementById('f-lng').value,
        max_radius:  document.getElementById('f-radius').value,
        batas_jam:   document.getElementById('f-jam').value,
        batas_menit: document.getElementById('f-menit').value
      };

      if (!payload.kampus_lat || !payload.kampus_lng) {
        showToast('error', 'Koordinat latitude dan longitude tidak boleh kosong.');
        return;
      }

      btn.disabled = true;
      btn.innerHTML = '<span class="spinner"></span> Menyimpan parameter ke server…';

      try {
        const res = await fetch('../api/settings.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (json.status === 'success') {
          showToast('success', json.message || 'Konfigurasi geofencing berhasil disimpan.');
        } else {
          showToast('error', json.message || 'Gagal menyimpan konfigurasi.');
        }
      } catch(e) {
        showToast('error', 'Gagal menghubungi server API: ' + e.message);
      } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Simpan Seluruh Perubahan Konfigurasi';
      }
    }

    function showToast(type, msg) {
      const toast = document.getElementById('toast-alert');
      const icon  = document.getElementById('toast-icon');
      const text  = document.getElementById('toast-msg');

      toast.className = `toast-alert toast-${type} show`;
      icon.className = type === 'success' ? 'fa-solid fa-circle-check' : 'fa-solid fa-triangle-exclamation';
      text.textContent = msg;

      toast.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      setTimeout(() => {
        toast.classList.remove('show');
      }, 5000);
    }
  </script>
</body>
</html>
