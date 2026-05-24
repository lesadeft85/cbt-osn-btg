<?php
// ============================================================
// login.php — Halaman Login Admin TKA Kecamatan
// ============================================================
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/helper.php';
// Centralized external paths
if (file_exists(__DIR__ . '/../config/paths.php')) {
    require_once __DIR__ . '/../config/paths.php';
}

if (isLoggedIn()) {
    redirect(dashboardUrlByRole($_SESSION['role']));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $result = login($username, $password);
        if ($result['status']) {
            logActivity($conn, 'Login', 'Berhasil login sebagai ' . $result['role']);
            redirect(dashboardUrlByRole($result['role']));
        } else {
            $error = $result['message'];
        }
    }
}

$namaAplikasi      = getSetting($conn, 'nama_aplikasi', 'TKA Kecamatan');
$namaPenyelenggara = getSetting($conn, 'nama_penyelenggara', '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin — <?= e($namaAplikasi) ?></title>
    <link href="<?= defined('CDN_BOOTSTRAP_CSS') ? CDN_BOOTSTRAP_CSS : 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' ?>" rel="stylesheet">
    <link href="<?= defined('CDN_BOOTSTRAP_ICONS') ? CDN_BOOTSTRAP_ICONS : 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css' ?>" rel="stylesheet">
    <style>
        :root {
            --primary: #1e3a8a;
            --primary-dark: #1e40af;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e0e7ff 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            max-width: 420px;
            width: 100%;
            background: #fff;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(30, 58, 138, 0.12);
            transition: transform 0.3s ease;
        }
        .login-card:hover { transform: translateY(-8px); }

        /* Header */
        .header {
            background: linear-gradient(135deg, var(--primary), #3b82f6);
            color: white;
            padding: 32px 28px;
            text-align: center;
            position: relative;
        }
        .logo {
            width: 92px;
            height: 92px;
            background: rgba(255,255,255,0.95);
            border-radius: 50%;
            padding: 8px;
            margin: 0 auto 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .judul-app {
            font-size: 22px;
            font-weight: 900;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .judul-penyelenggara {
            font-size: 14px;
            opacity: 0.9;
            margin-top: 6px;
        }
        .admin-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.2);
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 700;
            margin-top: 12px;
            backdrop-filter: blur(8px);
        }

        /* Form */
        .form-area {
            padding: 32px 28px;
        }
        .field-label {
            font-size: 13px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 8px;
        }
        .field-wrap {
            position: relative;
            margin-bottom: 20px;
        }
        .field-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 18px;
            z-index: 2;
        }
        .field-input {
            width: 100%;
            padding: 14px 14px 14px 48px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.25s;
        }
        .field-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(30, 58, 138, 0.1);
            outline: none;
        }
        .toggle-pw {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748b;
            font-size: 20px;
            cursor: pointer;
        }

        .btn-masuk {
            width: 100%;
            background: var(--primary);
            border: none;
            padding: 15px;
            font-size: 16px;
            font-weight: 800;
            border-radius: 12px;
            margin-top: 8px;
            box-shadow: 0 6px 15px rgba(30, 58, 138, 0.3);
            transition: all 0.3s;
        }
        .btn-masuk:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 24px 0;
            color: #94a3b8;
            font-size: 14px;
        }
        .divider::before, .divider::after {
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }

        .btn-peserta {
            width: 100%;
            padding: 13px;
            border: 2px solid #e2e8f0;
            color: #475569;
            font-weight: 700;
            border-radius: 12px;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
        }
        .btn-peserta:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: #eff6ff;
        }

        /* Alert */
        .alert-custom {
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
        }
        .error-alert   { background:#fef2f2; border:1px solid #fca5a5; color:#dc2626; }
        .success-alert { background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; }
        .warn-alert    { background:#fefce8; border:1px solid #fde68a; color:#854d0e; }

        .footer-area {
            text-align: center;
            padding: 20px 0 10px;
            font-size: 13px;
            color: #64748b;
        }
    </style>
</head>
<body>
<div class="login-card">

    <!-- Header -->
    <div class="header">
        <div class="logo">
            <img src="data:image/png;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/..." 
                 alt="Logo" style="width:100%;height:100%;object-fit:contain;border-radius:50%;">
        </div>
        <div class="judul-app"><?= e($namaAplikasi) ?></div>
        <?php if ($namaPenyelenggara): ?>
            <div class="judul-penyelenggara"><?= e($namaPenyelenggara) ?></div>
        <?php endif; ?>
        <div class="admin-badge">
            <i class="bi bi-shield-lock-fill"></i> Administrator
        </div>
    </div>

    <div class="form-area">

        <?php if (isset($_GET['timeout'])): ?>
        <div class="alert-custom warn-alert">
            <span style="font-size:18px">⏱</span> Sesi berakhir. Silakan login kembali.
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['logout'])): ?>
        <div class="alert-custom success-alert">
            <span style="font-size:18px">✅</span> Anda berhasil keluar.
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'role'): ?>
        <div class="alert-custom warn-alert">
            <span style="font-size:18px">⚠️</span> Role akun tidak dikenali.
        </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
        <div class="alert-custom error-alert">
            <span style="font-size:18px">⚠️</span> <?= e($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off" novalidate>
            <div class="field-wrap">
                <label class="field-label">Username</label>
                <i class="bi bi-person field-icon"></i>
                <input type="text" name="username" class="field-input"
                       placeholder="Masukkan username"
                       value="<?= e($_POST['username'] ?? '') ?>" required autofocus>
            </div>

            <div class="field-wrap">
                <label class="field-label">Password</label>
                <i class="bi bi-lock field-icon"></i>
                <input type="password" id="passwordInput" name="password" class="field-input"
                       placeholder="Masukkan password" required>
                <button type="button" class="toggle-pw" id="togglePassword">
                    <i class="bi bi-eye" id="eyeIcon"></i>
                </button>
            </div>

            <button type="submit" class="btn-masuk">
                <i class="bi bi-box-arrow-in-right"></i> MASUK
            </button>
        </form>

        <div class="divider">atau</div>

        <a href="<?= BASE_URL ?>/ujian/login_peserta.php" class="btn-peserta">
            <i class="bi bi-pencil-square"></i> Login sebagai Peserta Ujian
        </a>
    </div>

</div>

<!-- Footer -->
<div class="footer-area">
    &copy; <?= date('Y') ?> <?= e($namaAplikasi) ?><br>
    Dikembangkan oleh <strong>Cahyana Wijaya</strong>
</div>

<script>
document.getElementById('togglePassword').addEventListener('click', function(){
    const pw = document.getElementById('passwordInput');
    const eye = document.getElementById('eyeIcon');
    if (pw.type === 'password') {
        pw.type = 'text';
        eye.className = 'bi bi-eye-slash';
    } else {
        pw.type = 'password';
        eye.className = 'bi bi-eye';
    }
});
</script>
</body>
</html>