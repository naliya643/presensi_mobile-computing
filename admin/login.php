<?php
// ============================================
// Smart Attendance System - Executive Admin Login
// The Luxury Marketing Council Corporate Aesthetic
// ============================================
session_start();
require_once '../api/koneksi.php';

header_remove('Content-Type');
header('Content-Type: text/html; charset=utf-8');

// Jika sudah login, redirect langsung ke dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit();
}

$conn = getConnection();

// AUTO-SETUP DATABASE TABLE `admins`
// Otomatis membuat tabel admins dan akun default jika belum ada di database
$conn->query("
    CREATE TABLE IF NOT EXISTS `admins` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `username` VARCHAR(50) NOT NULL UNIQUE,
      `password` VARCHAR(255) NOT NULL,
      `nama` VARCHAR(100) NOT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Cek apakah akun default 'admin' sudah ada, jika belum buat dengan password 'admin123'
$chkAdmin = $conn->query("SELECT id FROM `admins` WHERE username = 'admin' LIMIT 1");
if ($chkAdmin && $chkAdmin->num_rows === 0) {
    $defaultPassHash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO `admins` (username, password, nama) VALUES (?, ?, ?)");
    $defUser = 'admin';
    $defName = 'Administrator Utama';
    $stmt->bind_param("sss", $defUser, $defaultPassHash, $defName);
    $stmt->execute();
    $stmt->close();
}

$errorMsg = '';
$successMsg = '';

if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out') {
    $successMsg = 'Anda telah berhasil keluar dari sesi administrasi.';
}

// Proses form login saat POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $errorMsg = 'Silakan isi username dan kata sandi Anda.';
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, nama FROM `admins` WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res && $res->num_rows === 1) {
            $admin = $res->fetch_assoc();
            // Verifikasi password (mendukung password_hash BCRYPT dan fallback MD5)
            $isPasswordValid = false;
            if (password_verify($password, $admin['password'])) {
                $isPasswordValid = true;
            } elseif (md5($password) === $admin['password']) {
                $isPasswordValid = true;
                // Re-hash ke modern bcrypt untuk upgrade keamanan otomatis
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $upd = $conn->prepare("UPDATE `admins` SET password = ? WHERE id = ?");
                $upd->bind_param("si", $newHash, $admin['id']);
                $upd->execute();
                $upd->close();
            }

            if ($isPasswordValid) {
                // Set sesi login
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_nama'] = $admin['nama'];
                $_SESSION['login_time'] = time();

                header('Location: index.php');
                exit();
            } else {
                $errorMsg = 'Kata sandi yang Anda masukkan tidak sesuai.';
            }
        } else {
            $errorMsg = 'Akun administrator dengan username tersebut tidak ditemukan.';
        }
        $stmt->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Authentication · Smart Attendance Enterprise</title>
  
  <!-- Google Fonts: Cinzel & Plus Jakarta Sans -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Font Awesome 6 CDN -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

  <style>
    *, *::before, *::after {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    :root {
      --bg-base: #faf8f5;
      --bg-surface: #f4f0e8;
      --card-bg: #ffffff;
      --card-border: rgba(197, 155, 86, 0.28);
      --border-subtle: rgba(20, 18, 16, 0.08);

      --obsidian: #141210;
      --obsidian-surface: #1e1b18;
      --text-main: #141210;
      --text-muted: #6b625b;
      --text-soft: #948b84;

      --gold-primary: #d4a362;
      --gold-glow: #dfa86a;
      --gold-dark: #b8853b;
      --gold-soft: rgba(212, 163, 98, 0.08);
      --gold-border: rgba(212, 163, 98, 0.4);

      --danger-corp: #9c2436;
      --danger-soft: #fdf0f2;
      --danger-border: #f2c7ce;

      --success-corp: #1e5a40;
      --success-soft: #edf6f1;
      --success-border: #bfe0cf;

      --radius-xl: 24px;
      --radius-lg: 16px;
      --radius-md: 12px;
      --radius-sm: 8px;

      --shadow-luxury: 0 20px 60px rgba(20, 18, 16, 0.08), 0 4px 16px rgba(197, 155, 86, 0.06);
    }

    body {
      font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
      background-color: var(--bg-base);
      color: var(--text-main);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 24px 16px;
      position: relative;
      overflow-x: hidden;
    }

    /* Ambient luxury aura */
    body::before {
      content: '';
      position: fixed;
      width: 650px;
      height: 650px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(212, 163, 98, 0.09) 0%, transparent 70%);
      top: -200px;
      right: -150px;
      pointer-events: none;
      z-index: 0;
    }

    body::after {
      content: '';
      position: fixed;
      width: 600px;
      height: 600px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(212, 163, 98, 0.07) 0%, transparent 70%);
      bottom: -180px;
      left: -150px;
      pointer-events: none;
      z-index: 0;
    }

    .auth-container {
      width: 100%;
      max-width: 460px;
      position: relative;
      z-index: 10;
    }

    /* Top branding */
    .auth-header {
      text-align: center;
      margin-bottom: 28px;
    }

    .brand-crest {
      width: 62px;
      height: 62px;
      margin: 0 auto 16px;
      border-radius: var(--radius-lg);
      background: linear-gradient(135deg, #dfa86a 0%, #c7924d 100%);
      border: 1px solid rgba(255, 240, 215, 0.6);
      box-shadow: 0 8px 24px rgba(199, 146, 77, 0.35);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #14110e;
      font-size: 1.55rem;
    }

    .brand-title {
      font-family: 'Cinzel', serif;
      font-size: 1.5rem;
      font-weight: 800;
      letter-spacing: 0.05em;
      color: var(--obsidian);
      text-transform: uppercase;
      line-height: 1.2;
      margin-bottom: 6px;
    }

    .brand-subtitle {
      font-size: 0.8rem;
      font-weight: 600;
      color: var(--text-muted);
      letter-spacing: 0.12em;
      text-transform: uppercase;
    }

    /* Card */
    .auth-card {
      background: var(--card-bg);
      border: 1.5px solid var(--card-border);
      border-radius: var(--radius-xl);
      padding: 36px 32px;
      box-shadow: var(--shadow-luxury);
      position: relative;
      overflow: hidden;
    }

    .auth-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, #b8853b, #dfa86a, #c7924d);
    }

    .card-heading {
      margin-bottom: 24px;
      padding-bottom: 16px;
      border-bottom: 1px solid rgba(212, 163, 98, 0.2);
    }

    .card-heading h2 {
      font-family: 'Cinzel', serif;
      font-size: 1.15rem;
      font-weight: 700;
      color: var(--obsidian);
      letter-spacing: 0.04em;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .card-heading h2 i {
      color: var(--gold-primary);
      font-size: 1.05rem;
    }

    .card-heading p {
      font-size: 0.82rem;
      color: var(--text-muted);
      margin-top: 4px;
      line-height: 1.5;
    }

    /* Alert Banners */
    .alert-banner {
      padding: 12px 16px;
      border-radius: var(--radius-md);
      font-size: 0.82rem;
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 20px;
      line-height: 1.45;
    }

    .alert-banner.danger {
      background: var(--danger-soft);
      border: 1px solid var(--danger-border);
      color: var(--danger-corp);
    }

    .alert-banner.success {
      background: var(--success-soft);
      border: 1px solid var(--success-border);
      color: var(--success-corp);
    }

    .alert-banner i {
      font-size: 1rem;
      flex-shrink: 0;
    }

    /* Form */
    .form-group {
      margin-bottom: 20px;
    }

    .form-label {
      display: block;
      font-size: 0.78rem;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      color: var(--obsidian);
      margin-bottom: 8px;
    }

    .input-wrapper {
      position: relative;
    }

    .input-icon-left {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-soft);
      font-size: 0.95rem;
      transition: color 0.2s ease;
      pointer-events: none;
    }

    .auth-input {
      width: 100%;
      padding: 13px 42px 13px 40px;
      background: #ffffff;
      border: 1.5px solid var(--border-subtle);
      border-radius: var(--radius-md);
      font-family: inherit;
      font-size: 0.9rem;
      color: var(--obsidian);
      outline: none;
      transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .auth-input:focus {
      border-color: var(--gold-primary);
      box-shadow: 0 0 0 3.5px rgba(212, 163, 98, 0.2);
    }

    .auth-input:focus + .input-icon-left {
      color: var(--gold-primary);
    }

    .toggle-pass-btn {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: var(--text-soft);
      font-size: 0.95rem;
      cursor: pointer;
      padding: 6px;
      transition: color 0.2s ease;
    }

    .toggle-pass-btn:hover {
      color: var(--obsidian);
    }

    /* Button */
    .btn-auth-submit {
      width: 100%;
      padding: 14px 20px;
      margin-top: 10px;
      background: linear-gradient(135deg, #dfa86a 0%, #c7924d 100%);
      border: 1px solid rgba(255, 240, 215, 0.5);
      border-radius: var(--radius-md);
      color: #14110e;
      font-family: inherit;
      font-size: 0.88rem;
      font-weight: 800;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      cursor: pointer;
      box-shadow: 0 6px 20px rgba(199, 146, 77, 0.4);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .btn-auth-submit:hover {
      background: linear-gradient(135deg, #e8b377 0%, #d49c55 100%);
      transform: translateY(-2px);
      box-shadow: 0 8px 26px rgba(199, 146, 77, 0.5);
    }

    .btn-auth-submit:active {
      transform: translateY(0);
    }

    /* Footer link */
    .auth-footer {
      text-align: center;
      margin-top: 24px;
    }

    .back-home-link {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      color: var(--text-muted);
      text-decoration: none;
      font-size: 0.8rem;
      font-weight: 600;
      transition: all 0.2s ease;
    }

    .back-home-link:hover {
      color: var(--gold-dark);
      transform: translateX(-2px);
    }
  </style>
</head>
<body>

  <div class="auth-container">
    
    <!-- BRANDING TOP -->
    <div class="auth-header">
      <div class="brand-crest">
        <i class="fa-solid fa-shield-halved"></i>
      </div>
      <h1 class="brand-title">Smart Attendance</h1>
      <span class="brand-subtitle">Executive Governance Portal</span>
    </div>

    <!-- LOGIN CARD -->
    <div class="auth-card">
      <div class="card-heading">
        <h2><i class="fa-solid fa-lock"></i> Autentikasi Administrator</h2>
        <p>Akses terbatas hanya untuk staf administrasi dan pengelola sistem presensi.</p>
      </div>

      <!-- NOTIFICATIONS -->
      <?php if (!empty($errorMsg)): ?>
        <div class="alert-banner danger">
          <i class="fa-solid fa-circle-exclamation"></i>
          <div><?= htmlspecialchars($errorMsg) ?></div>
        </div>
      <?php endif; ?>

      <?php if (!empty($successMsg)): ?>
        <div class="alert-banner success">
          <i class="fa-solid fa-circle-check"></i>
          <div><?= htmlspecialchars($successMsg) ?></div>
        </div>
      <?php endif; ?>

      <!-- FORM -->
      <form method="POST" action="login.php" autocomplete="off">
        <div class="form-group">
          <label class="form-label" for="username">Username</label>
          <div class="input-wrapper">
            <input type="text" id="username" name="username" class="auth-input" placeholder="Masukkan username admin" required autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            <i class="fa-solid fa-user-shield input-icon-left"></i>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Kata Sandi</label>
          <div class="input-wrapper">
            <input type="password" id="password" name="password" class="auth-input" placeholder="Masukkan kata sandi" required>
            <i class="fa-solid fa-key input-icon-left"></i>
            <button type="button" class="toggle-pass-btn" onclick="togglePasswordVisibility()" aria-label="Lihat kata sandi">
              <i class="fa-regular fa-eye" id="eye-icon"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-auth-submit">
          <i class="fa-solid fa-arrow-right-to-bracket"></i>
          <span>Masuk ke Dashboard</span>
        </button>
      </form>
    </div>

    <!-- BACK TO MAIN PAGE -->
    <div class="auth-footer">
      <a href="../index.html" class="back-home-link">
        <i class="fa-solid fa-arrow-left"></i>
        <span>Kembali ke Halaman Utama</span>
      </a>
    </div>

  </div>

  <script>
    function togglePasswordVisibility() {
      const passInput = document.getElementById('password');
      const eyeIcon = document.getElementById('eye-icon');
      if (passInput.type === 'password') {
        passInput.type = 'text';
        eyeIcon.classList.remove('fa-eye');
        eyeIcon.classList.add('fa-eye-slash');
      } else {
        passInput.type = 'password';
        eyeIcon.classList.remove('fa-eye-slash');
        eyeIcon.classList.add('fa-eye');
      }
    }
  </script>
</body>
</html>
