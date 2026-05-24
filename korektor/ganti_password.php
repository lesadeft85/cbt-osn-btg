<?php
// ============================================================
// korektor/ganti_password.php — Ganti Password Korektor
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireKorektor();

$namaAplikasi = getSetting($conn, 'nama_aplikasi', 'TKA Kecamatan');
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();

    $passwordLama = $_POST['password_lama'] ?? '';
    $passwordBaru = $_POST['password_baru'] ?? '';
    $konfirmasi   = $_POST['konfirmasi'] ?? '';

    if ($passwordLama === '' || $passwordBaru === '' || $konfirmasi === '') {
        $error = 'Semua field wajib diisi.';
    } elseif (strlen($passwordBaru) < 6) {
        $error = 'Password baru minimal 6 karakter.';
    } elseif ($passwordBaru !== $konfirmasi) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        // Ambil hash password saat ini
        $userId = (int)$_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || !password_verify($passwordLama, $row['password'])) {
            $error = 'Password lama tidak benar.';
        } else {
            $newHash = password_hash($passwordBaru, PASSWORD_BCRYPT);
            $stmt2 = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt2->bind_param('si', $newHash, $userId);
            $stmt2->execute();
            $stmt2->close();
            $success = 'Password berhasil diubah! Gunakan password baru saat login berikutnya.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ganti Password — <?= e($namaAplikasi) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
*{box-sizing:border-box}
body{background:#f1f5f9;font-family:'Segoe UI',Arial,sans-serif;min-height:100vh}
.topbar{background:#1e3a8a;padding:0 20px;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 2px 8px rgba(0,0,0,.2)}
.topbar-brand{font-size:16px;font-weight:900;color:#fff;letter-spacing:.5px}
.topbar-brand span{background:rgba(255,255,255,.15);padding:2px 10px;border-radius:20px;font-size:12px;font-weight:700;margin-left:8px;color:#bfdbfe}
.topbar-right{display:flex;align-items:center;gap:12px}
.user-info{color:rgba(255,255,255,.85);font-size:13px}
.btn-topbar{background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);color:#fff;border-radius:8px;padding:5px 14px;font-size:12px;font-weight:700;text-decoration:none;transition:background .15s}
.btn-topbar:hover{background:rgba(255,255,255,.25);color:#fff}

.wrap{max-width:500px;margin:40px auto;padding:0 16px}

.page-card{background:#fff;border-radius:16px;padding:28px 24px;box-shadow:0 2px 20px rgba(0,0,0,.09)}
.page-title{font-size:18px;font-weight:800;color:#1e293b;margin-bottom:4px}
.page-sub{font-size:13px;color:#94a3b8;margin-bottom:24px}

.field-label{font-size:11px;font-weight:800;color:#475569;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;display:block}
.field-wrap{position:relative;display:flex;align-items:center}
.field-icon{position:absolute;left:12px;color:#94a3b8;font-size:15px;pointer-events:none;z-index:1}
.field-input{width:100%;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:8px;padding:10px 40px 10px 38px;font-size:14px;color:#1e293b;transition:border .15s,box-shadow .15s;outline:none}
.field-input:focus{border-color:#1e3a8a;background:#eff6ff;box-shadow:0 0 0 3px rgba(30,58,138,.1)}
.toggle-pw{position:absolute;right:12px;background:none;border:none;padding:0;cursor:pointer;color:#94a3b8;font-size:16px;z-index:1}
.toggle-pw:hover{color:#1e3a8a}

.error-alert{background:#fef2f2;border:1.5px solid #fca5a5;border-radius:8px;padding:10px 14px;font-size:13px;color:#dc2626;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.success-alert{background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:8px;padding:10px 14px;font-size:13px;color:#15803d;margin-bottom:16px;display:flex;align-items:center;gap:8px}

.btn-simpan{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;background:#1e3a8a;border:none;border-radius:10px;padding:13px;font-size:15px;font-weight:800;color:#fff;cursor:pointer;transition:background .15s;margin-top:8px;box-shadow:0 3px 10px rgba(30,58,138,.3)}
.btn-simpan:hover{background:#1e40af}

.btn-back{display:flex;align-items:center;justify-content:center;gap:6px;width:100%;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:10px;padding:11px;font-size:14px;font-weight:700;color:#475569;text-decoration:none;margin-top:10px;transition:all .15s}
.btn-back:hover{border-color:#1e3a8a;color:#1e3a8a;background:#eff6ff}

.strength-bar{height:5px;border-radius:3px;background:#e2e8f0;margin-top:6px;overflow:hidden}
.strength-fill{height:100%;border-radius:3px;transition:width .3s,background .3s}
.strength-text{font-size:11px;margin-top:3px;font-weight:600}
</style>
</head>
<body>

<!-- Topbar -->
<div class="topbar">
  <div class="topbar-brand">
    <?= e($namaAplikasi) ?>
    <span>✏️ Korektor</span>
  </div>
  <div class="topbar-right">
    <span class="user-info"><i class="bi bi-person-circle me-1"></i><?= e($_SESSION['nama']) ?></span>
    <a href="<?= BASE_URL ?>/korektor/index.php" class="btn-topbar">
      <i class="bi bi-house me-1"></i>Dashboard
    </a>
    <a href="<?= BASE_URL ?>/logout.php" class="btn-topbar">
      <i class="bi bi-box-arrow-right me-1"></i>Keluar
    </a>
  </div>
</div>

<div class="wrap">
  <div class="page-card">
    <div class="page-title"><i class="bi bi-shield-lock me-2 text-primary"></i>Ganti Password</div>
    <div class="page-sub">Masukkan password lama dan password baru Anda.</div>

    <?php if ($error): ?>
    <div class="error-alert"><i class="bi bi-exclamation-triangle-fill"></i><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="success-alert"><i class="bi bi-check-circle-fill"></i><?= e($success) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <?= csrfField() ?>

      <!-- Password Lama -->
      <div style="margin-bottom:16px">
        <label class="field-label">Password Lama</label>
        <div class="field-wrap">
          <i class="bi bi-lock field-icon"></i>
          <input type="password" name="password_lama" id="pwLama" class="field-input"
                 placeholder="Masukkan password lama" required>
          <button type="button" class="toggle-pw" onclick="togglePw('pwLama','eyeLama')">
            <i class="bi bi-eye" id="eyeLama"></i>
          </button>
        </div>
      </div>

      <!-- Password Baru -->
      <div style="margin-bottom:8px">
        <label class="field-label">Password Baru</label>
        <div class="field-wrap">
          <i class="bi bi-lock-fill field-icon"></i>
          <input type="password" name="password_baru" id="pwBaru" class="field-input"
                 placeholder="Minimal 6 karakter" required oninput="cekKekuatan(this.value)">
          <button type="button" class="toggle-pw" onclick="togglePw('pwBaru','eyeBaru')">
            <i class="bi bi-eye" id="eyeBaru"></i>
          </button>
        </div>
        <div class="strength-bar"><div class="strength-fill" id="strengthBar" style="width:0%"></div></div>
        <div class="strength-text" id="strengthText" style="color:#94a3b8"></div>
      </div>

      <!-- Konfirmasi -->
      <div style="margin-bottom:20px">
        <label class="field-label">Konfirmasi Password Baru</label>
        <div class="field-wrap">
          <i class="bi bi-lock-fill field-icon"></i>
          <input type="password" name="konfirmasi" id="pwKonfirmasi" class="field-input"
                 placeholder="Ulangi password baru" required>
          <button type="button" class="toggle-pw" onclick="togglePw('pwKonfirmasi','eyeKonfirmasi')">
            <i class="bi bi-eye" id="eyeKonfirmasi"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-simpan">
        <i class="bi bi-check-circle-fill"></i> Simpan Password
      </button>
    </form>

    <a href="<?= BASE_URL ?>/korektor/index.php" class="btn-back">
      <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
    </a>
  </div>
</div>

<script>
function togglePw(inputId, eyeId) {
    const inp = document.getElementById(inputId);
    const eye = document.getElementById(eyeId);
    if (inp.type === 'password') {
        inp.type = 'text';
        eye.className = 'bi bi-eye-slash';
    } else {
        inp.type = 'password';
        eye.className = 'bi bi-eye';
    }
}

function cekKekuatan(pw) {
    const bar  = document.getElementById('strengthBar');
    const text = document.getElementById('strengthText');
    let score = 0;
    if (pw.length >= 6)  score++;
    if (pw.length >= 10) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;

    const levels = [
        {w:'0%',   c:'#e2e8f0', t:''},
        {w:'25%',  c:'#ef4444', t:'Lemah'},
        {w:'50%',  c:'#f59e0b', t:'Cukup'},
        {w:'75%',  c:'#3b82f6', t:'Kuat'},
        {w:'100%', c:'#16a34a', t:'Sangat Kuat'},
    ];
    const lvl = levels[Math.min(score, 4)];
    bar.style.width = lvl.w;
    bar.style.background = lvl.c;
    text.style.color = lvl.c;
    text.textContent = lvl.t;
}
</script>
</body>
</html>
