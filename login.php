<?php
// ============================================================
// login.php — Halaman Login Admin TKA Kecamatan (VERSI FINAL)
// ============================================================
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/helper.php';
// Centralized external paths
if (file_exists(__DIR__ . '/config/paths.php')) {
  require_once __DIR__ . '/config/paths.php';
}

if (isLoggedIn()) {
    redirect(dashboardUrlByRole($_SESSION['role']));
}

$error    = '';
$lockSisa = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $ipKey    = 'login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

    if (!cekRateLimit($ipKey, 5, 300)) {
        $lockSisa = sisaWaktuKunci($ipKey);
        $menit    = ceil($lockSisa / 60);
        $error    = "Terlalu banyak percobaan login. Coba lagi dalam <strong>{$menit} menit</strong>.";
    } elseif ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $result = login($username, $password);
        if ($result['status']) {
            resetRateLimit($ipKey);
            logActivity($conn, 'Login', 'Berhasil login sebagai ' . $result['role']);
            redirect(dashboardUrlByRole($result['role']));
        } else {
            $error = $result['message'];
        }
    }
}

$namaAplikasi      = getSetting($conn, 'nama_aplikasi', 'TKA Kecamatan');
$namaPenyelenggara = getSetting($conn, 'nama_penyelenggara', '');
$tahunPelajaran    = getSetting($conn, 'tahun_pelajaran', '2025/2026');
$logoFilePath      = getSetting($conn, 'logo_file_path', '');
$logoUrl           = getSetting($conn, 'logo_url', '');

// ── FIX: PENENTUAN LOGO AKTIF SECARA DINAMIS DAN AMAN ──
if (!empty($logoFilePath)) {
    $logoAktif = BASE_URL . '/' . ltrim($logoFilePath, '/');
} elseif (!empty($logoUrl)) {
    if (strpos($logoUrl, 'http://') !== 0 && strpos($logoUrl, 'https://') !== 0) {
        $logoAktif = BASE_URL . '/' . ltrim($logoUrl, '/');
    } else {
        // Jika URL di database statis/hardcoded, kita ambil path esensialnya saja
        $parsedUrl = parse_url($logoUrl);
        $pathHanya = $parsedUrl['path'] ?? '';
        
        // Cari folder /assets/ ke kanan untuk menghindari sisa subfolder lama
        if (strpos($pathHanya, '/assets/') !== false) {
            $pathHanya = strstr($pathHanya, '/assets/');
        }
        $logoAktif = BASE_URL . '/' . ltrim($pathHanya, '/');
    }
} else {
    $logoAktif = '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Admin — <?= e($namaAplikasi) ?></title>

<!-- FIX: Semua path Favicon diubah menjadi Dinamis mengikuti BASE_URL -->
<link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/assets/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/assets/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="<?= BASE_URL ?>/assets/favicon-16x16.png">
<link rel="apple-touch-icon" sizes="180x180" href="<?= BASE_URL ?>/assets/apple-touch-icon.png">

<link href="<?= defined('CDN_BOOTSTRAP_CSS') ? CDN_BOOTSTRAP_CSS : 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' ?>" rel="stylesheet">
<link href="<?= defined('CDN_BOOTSTRAP_ICONS') ? CDN_BOOTSTRAP_ICONS : 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css' ?>" rel="stylesheet">
<link href="<?= defined('FONTS_PLUS_JAKARTA') ? FONTS_PLUS_JAKARTA : 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&display=swap' ?>" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --navy:#1e3a8a;--navy-h:#1e40af;--navy-d:#172e6e;--navy-m:#2348a8;
  --blue:#3b82f6;
  --g50:#f8fafc;--g100:#f1f5f9;--g200:#e2e8f0;--g300:#cbd5e1;
  --g400:#94a3b8;--g600:#475569;--g800:#1e293b;
  --red-bg:#fef2f2;--red-br:#fca5a5;--red-tx:#dc2626;
  --grn-bg:#f0fdf4;--grn-br:#bbf7d0;--grn-tx:#15803d;
  --ylw-bg:#fefce8;--ylw-br:#fde68a;--ylw-tx:#854d0e;
}

body{
  font-family:'Plus Jakarta Sans','Segoe UI',sans-serif;
  min-height:100vh;
  display:flex;align-items:center;justify-content:center;
  padding:24px 16px;
  background:var(--g100);
}

/* ══ Card ══ */
.login-card{
  display:flex;width:100%;max-width:720px;min-height:500px;
  border-radius:20px;overflow:hidden;
  box-shadow:0 8px 40px rgba(30,58,138,.18),0 2px 8px rgba(0,0,0,.06);
}

/* ══ PANEL KIRI ══ */
.panel-left{
  width:48%;
  background:linear-gradient(155deg,var(--navy-m) 0%,var(--navy) 55%,var(--navy-d) 100%);
  position:relative;display:flex;flex-direction:column;
  align-items:center;justify-content:center;
  padding:36px 24px;overflow:hidden;
}
.panel-left::before{content:'';position:absolute;width:320px;height:320px;border-radius:50%;border:1px solid rgba(255,255,255,.08);top:-90px;left:-90px}
.panel-left::after {content:'';position:absolute;width:200px;height:200px;border-radius:50%;border:1px solid rgba(255,255,255,.07);bottom:-55px;right:-55px}
.deco-ring{position:absolute;width:140px;height:140px;border-radius:50%;background:rgba(255,255,255,.04);bottom:48px;left:-34px}

.icon-circle{
  width:90px;height:90px;border-radius:50%;
  background:rgba(255,255,255,.15);border:2px solid rgba(255,255,255,.35);
  display:flex;align-items:center;justify-content:center;
  margin-bottom:14px;position:relative;z-index:1;flex-shrink:0;
  overflow:hidden;
}
.icon-circle i{font-size:36px;color:#fff}
.icon-circle img{width:80px;height:80px;object-fit:contain;border-radius:50%;}
.logo-row{
  display:flex;align-items:center;justify-content:center;gap:10px;
  margin-bottom:14px;position:relative;z-index:1;
}
.logo-side{
  width:64px;height:64px;border-radius:50%;
  background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.24);
  display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;
}
.logo-side img{width:48px;height:48px;object-fit:contain;}

.left-app{
  font-size:19px;font-weight:800;letter-spacing:.4px;
  color:rgba(255,255,255,.95);text-transform:uppercase;
  position:relative;z-index:1;text-align:center;margin-bottom:6px;
  line-height:1.35;
}

.left-school{
  font-size:21px;font-weight:900;
  color:#fff;text-align:center;line-height:1.2;
  position:relative;z-index:1;margin-bottom:6px;
  letter-spacing:-.2px;
}

.left-sub{
  font-size:11px;color:rgba(255,255,255,.58);
  text-align:center;line-height:1.65;
  position:relative;z-index:1;margin-bottom:12px;max-width:200px;
}

.left-badge{
  display:inline-flex;align-items:center;gap:5px;
  background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);
  border-radius:20px;padding:4px 13px;
  font-size:10.5px;font-weight:700;color:rgba(255,255,255,.8);
  position:relative;z-index:1;
}
.left-badge i{font-size:11px}

.left-divider{width:100%;height:1px;background:rgba(255,255,255,.13);margin:16px 0;position:relative;z-index:1}

.left-features{
  list-style:none;padding:0;width:100%;
  position:relative;z-index:1;
  display:flex;flex-direction:column;gap:9px;margin-bottom:14px;
}
.left-features li{
  display:flex;align-items:center;gap:9px;
  font-size:11.5px;font-weight:600;color:rgba(255,255,255,.82);line-height:1.4;
}
.left-features li i{font-size:13px;color:rgba(255,255,255,.5);flex-shrink:0}

.left-quote{
  font-size:10.5px;font-style:italic;color:rgba(255,255,255,.42);line-height:1.65;
  position:relative;z-index:1;
  border-left:2px solid rgba(255,255,255,.17);padding-left:10px;align-self:flex-start;
}

/* ══ PANEL KANAN ══ */
.panel-right{
  flex:1;background:#fff;
  display:flex;flex-direction:column;justify-content:center;
  padding:38px 34px 30px;
}
.form-title  {font-size:25px;font-weight:900;color:var(--g800);margin-bottom:4px;letter-spacing:-.3px}
.form-tagline{font-size:12.5px;color:var(--g400);margin-bottom:22px}

.alert-box{display:flex;align-items:flex-start;gap:8px;border-radius:8px;padding:9px 12px;font-size:12.5px;margin-bottom:14px;line-height:1.5}
.alert-box.error  {background:var(--red-bg);border:1px solid var(--red-br);color:var(--red-tx)}
.alert-box.warn   {background:var(--ylw-bg);border:1px solid var(--ylw-br);color:var(--ylw-tx)}
.alert-box.success{background:var(--grn-bg);border:1px solid var(--grn-br);color:var(--grn-tx)}
.alert-box .ai{font-size:15px;flex-shrink:0;margin-top:1px}

.field-lbl{
  display:flex;justify-content:space-between;align-items:center;
  font-size:11px;font-weight:800;color:var(--g600);
  text-transform:uppercase;letter-spacing:.65px;margin-bottom:5px;
}
.field-lbl a{font-size:11.5px;font-weight:600;color:var(--blue);text-decoration:none;text-transform:none;letter-spacing:0}
.field-lbl a:hover{text-decoration:underline}
.field-wrap{position:relative;display:flex;align-items:center;margin-bottom:14px}
.field-icon{position:absolute;left:12px;color:var(--g300);font-size:15px;pointer-events:none}
.field-input{
  width:100%;background:var(--g50);
  border:1.5px solid var(--g200);border-radius:9px;
  padding:10px 12px 10px 38px;
  font-size:13.5px;font-family:inherit;color:var(--g800);
  transition:border .15s,box-shadow .15s;outline:none;
}
.field-input:focus{border-color:var(--navy);background:#eff6ff;box-shadow:0 0 0 3px rgba(30,58,138,.09)}
.field-input::placeholder{color:var(--g300);font-size:13px}
.toggle-pw{position:absolute;right:11px;background:none;border:none;padding:0;cursor:pointer;color:var(--g400);font-size:15px;line-height:1}
.toggle-pw:hover{color:var(--navy)}

.btn-masuk{
  width:100%;background:var(--navy);border:none;border-radius:9px;
  padding:12px 16px;font-size:14.5px;font-weight:800;font-family:inherit;color:#fff;
  cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;
  transition:background .15s;box-shadow:0 3px 12px rgba(30,58,138,.28);margin-top:2px;
}
.btn-masuk:hover{background:var(--navy-h)}
.btn-masuk i{font-size:15px}

.divider{display:flex;align-items:center;gap:10px;color:var(--g400);font-size:11.5px;margin:15px 0}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--g200)}

.btn-peserta{
  display:flex;align-items:center;justify-content:center;gap:7px;
  width:100%;
  background:#dc2626; /* merah */
  border:1.5px solid #b91c1c;
  border-radius:9px;padding:10px 16px;font-size:13.5px;font-weight:700;font-family:inherit;
  color:#fff;text-decoration:none;transition:all .15s;box-shadow:0 6px 18px rgba(220,38,38,.18);
}
.btn-peserta:hover{background:#b91c1c;border-color:#7f1d1d;color:#fff;transform:translateY(-1px)}

.footer-inner{text-align:center;margin-top:18px;font-size:11px;color:var(--g400)}
.footer-inner strong{color:var(--g600)}
.footer-inner a{color:var(--navy);font-weight:700;text-decoration:none}
.footer-inner a:hover{text-decoration:underline}

/* ══ MOBILE MEDIA QUERIES ══ */
@media(max-width:600px){
  html,body{
    height:100%;
    overflow:hidden;
  }
  body{
    padding:0;
    align-items:stretch;
    flex-direction:column;
    min-height:100vh;
    background:linear-gradient(160deg,var(--navy-m) 0%,var(--navy) 55%,var(--navy-d) 100%);
    position:relative;
    overflow:hidden;
  }
  body::before{
    content:'';position:fixed;z-index:0;
    width:280px;height:280px;border-radius:50%;
    border:1px solid rgba(255,255,255,.1);
    top:-70px;left:-70px;pointer-events:none;
  }
  body::after{
    content:'';position:fixed;z-index:0;
    width:200px;height:200px;border-radius:50%;
    border:1px solid rgba(255,255,255,.08);
    top:60px;right:-60px;pointer-events:none;
  }

  .login-card{
    flex-direction:column;
    border-radius:0;
    height:100vh;
    min-height:unset;
    box-shadow:none;
    position:relative;z-index:1;
    overflow:hidden;
  }

  .panel-left{
    width:100%;background:transparent;
    padding:40px 24px 12px;
    align-items:center;justify-content:flex-end;
    flex-shrink:0;
    min-height:unset;
    height:auto;
  }
  .panel-left::before,.panel-left::after,.deco-ring{display:none}
  .logo-row{gap:8px;margin-bottom:8px}
  .logo-side{width:52px;height:52px}
  .logo-side img{width:38px;height:38px}
  .icon-circle{width:70px;height:70px;margin-bottom:8px}
  .icon-circle i{font-size:26px}
  .icon-circle img{width:62px;height:62px}
  .left-app{font-size:14px;font-weight:800;letter-spacing:.4px;margin-bottom:2px}
  .left-school{font-size:15px;margin-bottom:2px}
  .left-sub{font-size:9.5px;margin-bottom:6px;max-width:260px}
  .left-badge{font-size:9px;padding:2px 9px}
  .left-divider,.left-features,.left-quote{display:none}

  .panel-right{
    background:#fff;
    border-radius:22px 22px 0 0;
    padding:22px 20px env(safe-area-inset-bottom, 24px);
    box-shadow:0 -6px 30px rgba(0,0,0,.18);
    flex:1;
    min-height:0;
    overflow-y:auto;
    -webkit-overflow-scrolling:touch;
    justify-content:flex-start;
  }
  .form-title{font-size:21px}
  .form-tagline{font-size:12px;margin-bottom:16px}
  .field-input{font-size:14px;padding:11px 12px 11px 38px}
  .field-wrap{margin-bottom:12px}
  .btn-masuk{padding:12px;font-size:14.5px}
  .divider{margin:12px 0}
  .footer-inner{margin-top:14px;padding-bottom:8px}
}
</style>
</head>
<body>

<div class="login-card">

  <!-- Panel Kiri -->
  <div class="panel-left">
    <div class="deco-ring"></div>

    <div class="logo-row">
      <div class="logo-side">
        <img src="<?= BASE_URL ?>/assets/osn.png" alt="OSN">
      </div>

      <div class="icon-circle">
        <?php if (!empty($logoAktif)): ?>
          <img src="<?= htmlspecialchars($logoAktif) ?>" alt="Logo">
        <?php else: ?>
          <i class="bi bi-mortarboard-fill"></i>
        <?php endif; ?>
      </div>

      <div class="logo-side">
        <img src="<?= BASE_URL ?>/assets/kkops.png" alt="KKOPS">
      </div>
    </div>

    <div class="left-app"><?= e($namaAplikasi) ?></div>
    <div class="left-school"><?= e($namaPenyelenggara ?: $namaAplikasi) ?></div>

    <?php if ($namaPenyelenggara): ?>
      <div class="left-sub">Sistem Computer Based Test</div>
    <?php endif; ?>

    <div class="left-badge">
      <i class="bi bi-calendar3"></i>
      Tahun Pelajaran <?= e($tahunPelajaran) ?>
    </div>

    <div class="left-divider"></div>

    <ul class="left-features">
      <li><i class="bi bi-shield-check-fill"></i> Sistem ujian aman &amp; terenkripsi</li>
      <li><i class="bi bi-lightning-charge-fill"></i> Penilaian otomatis &amp; real-time</li>
      <li><i class="bi bi-bar-chart-line-fill"></i> Laporan hasil ujian terperinci</li>
      <li><i class="bi bi-people-fill"></i> Manajemen peserta &amp; soal terpadu</li>
    </ul>

    <div class="left-quote">
      &ldquo;Asesmen yang baik adalah kunci<br>pembelajaran yang bermakna.&rdquo;
    </div>
  </div>

  <!-- Panel Kanan -->
  <div class="panel-right">

    <div class="form-title">Login</div>
    <div class="form-tagline">Masuk sebagai administrator sistem.</div>

    <?php if (isset($_GET['timeout'])): ?>
    <div class="alert-box warn">
      <span class="ai">⏱</span>
      <span>Sesi berakhir karena tidak aktif. Silakan login kembali.</span>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['logout'])): ?>
    <div class="alert-box success">
      <span class="ai">✅</span>
      <span>Anda berhasil keluar dari sistem.</span>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'role'): ?>
    <div class="alert-box warn">
      <span class="ai">⚠️</span>
      <span>Role akun tidak dikenali. Hubungi administrator.</span>
    </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
    <div class="alert-box error">
      <span class="ai">⚠️</span><span><?= e($error) ?></span>
    </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off" novalidate>
      <?= csrfField() ?>

      <label class="field-lbl">Username</label>
      <div class="field-wrap">
        <i class="bi bi-person field-icon"></i>
        <input type="text" name="username" class="field-input"
               placeholder="Masukkan username"
               value="<?= e($_POST['username'] ?? '') ?>"
               autocomplete="username" required autofocus>
      </div>

      <label class="field-lbl">Password</label>
      <div class="field-wrap">
        <i class="bi bi-lock field-icon"></i>
        <input type="password" id="passwordInput" name="password" class="field-input"
               placeholder="Masukkan password"
               autocomplete="current-password" required>
        <button type="button" class="toggle-pw" id="togglePassword" title="Tampilkan/sembunyikan">
          <i class="bi bi-eye" id="eyeIcon"></i>
        </button>
      </div>

      <button type="submit" class="btn-masuk">
        <i class="bi bi-box-arrow-in-right"></i> Masuk
      </button>

    </form>

    <div class="divider">atau</div>

    <!-- FIX: Memanfaatkan BASE_URL untuk mengalihkan ke login peserta -->
    <a href="<?= BASE_URL ?>/ujian/login_peserta.php" class="btn-peserta">
      <i class="bi bi-pencil-square"></i> Login Sebagai Peserta Ujian
    </a>

    <div class="footer-inner">
      &copy; <?= date('Y') ?> <?= e($namaAplikasi) ?> &mdash;
      Dikembangkan oleh <strong>KKOPS-BTG</strong>&nbsp;
 
       </div>

  </div>

</div>

<script>
document.getElementById('togglePassword').addEventListener('click', function(){
    const pw  = document.getElementById('passwordInput');
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