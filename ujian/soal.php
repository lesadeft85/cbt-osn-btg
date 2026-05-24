<?php
// ============================================================
// ujian/soal.php — 1 soal per halaman + ragu-ragu
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_name('TKA_PESERTA');
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/helper.php';
// Centralized external paths
if (file_exists(__DIR__ . '/../config/paths.php')) {
  require_once __DIR__ . '/../config/paths.php';
}

if (empty($_SESSION['peserta_id'])) {
    redirect(BASE_URL . '/ujian/login_peserta.php');
}

$pesertaId = (int)$_SESSION['peserta_id'];
$ujianId   = (int)$_SESSION['ujian_id'];
$jadwalId  = (int)($_SESSION['jadwal_id'] ?? 0);

// ── Ambil semua setting dalam 1 query (bukan 3x getSetting) ──
if (!isset($_SESSION['_settings_cache'])) {
    $settingKeys = ['nama_aplikasi','jumlah_soal','acak_pilihan'];
    $keysIn      = implode("','", $settingKeys);
    $settingRes  = $conn->query("SELECT setting_key, setting_value FROM pengaturan WHERE setting_key IN ('$keysIn')");
    $settingsCache = [];
    if ($settingRes) while ($s = $settingRes->fetch_assoc()) {
        $settingsCache[$s['setting_key']] = $s['setting_value'];
    }
    $_SESSION['_settings_cache'] = $settingsCache;
}
$settings     = $_SESSION['_settings_cache'];
$namaAplikasi = $settings['nama_aplikasi'] ?? 'TKA Kecamatan';
$jumlahSoal   = (int)($settings['jumlah_soal'] ?? 0);
$acakPilihan  = ($settings['acak_pilihan'] ?? '0') === '1';

// ── Override jumlah soal dari jadwal (cache SHOW COLUMNS di session) ──
if ($jadwalId) {
    // SHOW COLUMNS hanya dijalankan sekali per session, bukan tiap request
    if (!isset($_SESSION['_col_jumlah_soal_exists'])) {
        $_colCek = $conn->query("SHOW COLUMNS FROM jadwal_ujian LIKE 'jumlah_soal'");
        $_SESSION['_col_jumlah_soal_exists'] = ($_colCek && $_colCek->num_rows > 0) ? 1 : 0;
    }
    if ($_SESSION['_col_jumlah_soal_exists']) {
        $_qJdSoal = $conn->query("SELECT jumlah_soal FROM jadwal_ujian WHERE id=$jadwalId LIMIT 1");
        if ($_qJdSoal && $_qJdSoal->num_rows > 0) {
            $jdSoalRow = $_qJdSoal->fetch_assoc();
            if (!empty($jdSoalRow['jumlah_soal'])) {
                $jumlahSoal = (int)$jdSoalRow['jumlah_soal'];
            }
        }
    }
}

// ── Jika jumlahSoal 0 → pakai semua soal di bank ─────────────
if ($jumlahSoal <= 0) {
    $jadwalKatIdTemp = (int)($_SESSION['jadwal_kategori_id'] ?? 0);
    $katWhereTemp    = $jadwalKatIdTemp ? "WHERE kategori_id=$jadwalKatIdTemp" : '';
    $_qJmlBank = $conn->query("SELECT COUNT(*) AS c FROM soal $katWhereTemp");
    $jumlahSoal = $_qJmlBank ? max(1, (int)$_qJmlBank->fetch_assoc()['c']) : 20;
}

// ── Validasi sesi ujian ────────────────────────────────────────
$_qUjian = $conn->query(
    "SELECT id, peserta_id, waktu_mulai, waktu_selesai, nilai, token_id,
            jadwal_id, kategori_id, soal_order, pelanggaran, last_activity
     FROM ujian WHERE id=$ujianId AND peserta_id=$pesertaId AND waktu_selesai IS NULL LIMIT 1"
);
$ujian = ($_qUjian && $_qUjian->num_rows > 0) ? $_qUjian->fetch_assoc() : null;
if (!$ujian) {
    session_unset(); session_destroy();
    redirect(BASE_URL . '/ujian/selesai.php');
}

updateUjianActivity($conn, $ujianId);

// ── Hitung sisa waktu ─────────────────────────────────────────
$jamSelesai   = $_SESSION['jam_selesai'] ?? null;
$tanggalUjian = $_SESSION['tanggal_ujian'] ?? date('Y-m-d');
$sisaDetik    = $jamSelesai ? max(0, strtotime("$tanggalUjian $jamSelesai") - time()) : 0;
if ($sisaDetik <= 0) redirect(BASE_URL . '/ujian/submit.php?auto=1');

// ── Urutan soal (cache di session + DB) ───────────────────────
if (empty($_SESSION['soal_order'])) {
    // Coba ambil dari DB dulu (jika session expired tapi ujian masih aktif)
    if (!empty($ujian['soal_order'])) {
        $_SESSION['soal_order'] = json_decode($ujian['soal_order'], true) ?: [];
    }
}

if (empty($_SESSION['soal_order'])) {
    // Buat urutan soal baru — proporsional per tipe
    $jadwalKatId    = (int)($_SESSION['jadwal_kategori_id'] ?? 0);
    $kategoriFilter = $jadwalKatId ? "WHERE kategori_id=$jadwalKatId" : '';

    $tipeRes  = $conn->query("SELECT tipe_soal, COUNT(*) as total FROM soal $kategoriFilter GROUP BY tipe_soal");
    $tipeData = [];
    if ($tipeRes) while ($t = $tipeRes->fetch_assoc()) $tipeData[$t['tipe_soal']] = (int)$t['total'];

    $jumlahTipe = count($tipeData);
    $soalList   = [];

    if ($jumlahTipe > 0) {
        $perTipe = (int)floor($jumlahSoal / $jumlahTipe);
        $sisa    = $jumlahSoal - ($perTipe * $jumlahTipe);

        $ambilPerTipe = [];
        $totalBisa    = 0;
        $i = 0;
        foreach ($tipeData as $tipe => $totalTersedia) {
            $target = $perTipe + ($i === 0 ? $sisa : 0);
            $ambil  = min($target, $totalTersedia);
            $ambilPerTipe[$tipe] = $ambil;
            $totalBisa += $ambil;
            $i++;
        }

        // Distribusikan kekurangan ke tipe yang masih ada stok
        $kurang = $jumlahSoal - $totalBisa;
        if ($kurang > 0) {
            foreach ($tipeData as $tipe => $totalTersedia) {
                if ($kurang <= 0) break;
                $sisaStok = $totalTersedia - $ambilPerTipe[$tipe];
                if ($sisaStok > 0) {
                    $tambah = min($kurang, $sisaStok);
                    $ambilPerTipe[$tipe] += $tambah;
                    $kurang -= $tambah;
                }
            }
        }

        foreach ($tipeData as $tipe => $totalTersedia) {
            $ambil = $ambilPerTipe[$tipe];
            if ($ambil <= 0) continue;
            $tipeEsc = $conn->real_escape_string($tipe);
            $katWhere = $jadwalKatId ? "AND kategori_id=$jadwalKatId" : '';

            // Ambil semua ID, acak di PHP (hindari ORDER BY RAND())
            $idRes  = $conn->query("SELECT id FROM soal WHERE tipe_soal='$tipeEsc' $katWhere");
            $allIds = [];
            if ($idRes) { while ($row = $idRes->fetch_assoc()) $allIds[] = (int)$row['id']; $idRes->free(); }
            if (!empty($allIds)) {
                shuffle($allIds);
                $pickedIds = array_slice($allIds, 0, $ambil);
                $inStr = implode(',', $pickedIds);
                $res = $conn->query(
                    "SELECT id,tipe_soal,pertanyaan,teks_bacaan,gambar,
                            pilihan_a,pilihan_b,pilihan_c,pilihan_d,
                            gambar_pilihan_a,gambar_pilihan_b,gambar_pilihan_c,gambar_pilihan_d
                     FROM soal WHERE id IN ($inStr)"
                );
                if ($res) { while ($r = $res->fetch_assoc()) $soalList[] = $r; $res->free(); }
            }
        }
        shuffle($soalList);
    }

    $_SESSION['soal_order'] = array_column($soalList, 'id');

    // Simpan ke DB agar tidak hilang jika session expired
    $soalOrderJson = $conn->real_escape_string(json_encode($_SESSION['soal_order']));
    $conn->query("UPDATE ujian SET soal_order='$soalOrderJson' WHERE id=$ujianId");

} else {
    // Soal order sudah ada di session — muat data soal dari DB
    $ids = implode(',', array_map('intval', $_SESSION['soal_order'] ?: [0]));
    $res = $conn->query(
        "SELECT id,tipe_soal,pertanyaan,teks_bacaan,gambar,
                pilihan_a,pilihan_b,pilihan_c,pilihan_d,
                gambar_pilihan_a,gambar_pilihan_b,gambar_pilihan_c,gambar_pilihan_d
         FROM soal WHERE id IN ($ids) ORDER BY FIELD(id,$ids)"
    );
    $soalList = [];
    if ($res) while ($r = $res->fetch_assoc()) $soalList[] = $r;
}

$totalSoal = count($soalList);

// ── Guard: soal gagal dimuat ──────────────────────────────────
if ($totalSoal === 0) {
    ?><!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Soal Tidak Tersedia</title>
<link href="<?= defined('CDN_BOOTSTRAP_CSS') ? CDN_BOOTSTRAP_CSS : 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' ?>" rel="stylesheet">
</head><body style="background:#f1f5f9;padding:40px 16px;font-family:'Segoe UI',Arial,sans-serif">
<div style="max-width:480px;margin:0 auto;background:#fff;border-radius:16px;padding:32px;box-shadow:0 2px 20px rgba(0,0,0,.1);text-align:center">
  <div style="font-size:56px;margin-bottom:16px">⚠️</div>
  <h4 style="color:#dc2626;font-weight:800;margin-bottom:8px">Soal Tidak Dapat Dimuat</h4>
  <p style="color:#475569;font-size:14px;margin-bottom:20px">
    Terjadi masalah saat memuat soal ujian.<br>
    <strong>Ujian Anda belum terekam — jangan tutup browser ini.</strong>
  </p>
  <div style="background:#fef2f2;border:1.5px solid #fca5a5;border-radius:8px;padding:12px;font-size:13px;color:#dc2626;margin-bottom:20px">
    Hubungi pengawas ujian sekarang untuk mendapatkan bantuan.
  </div>
  <button onclick="location.reload()" style="background:#1e3a8a;color:#fff;border:none;border-radius:8px;padding:10px 24px;font-size:14px;font-weight:700;cursor:pointer">
    🔄 Coba Muat Ulang
  </button>
</div>
</body></html><?php
    exit;
}

$noAktif = max(1, min((int)($_GET['no'] ?? 1), $totalSoal));
$soal    = $soalList[$noAktif - 1];
$soalId  = $soal['id'];

// ── Ambil semua jawaban (1 query, cache di session) ───────────
// Tidak perlu query ulang setiap pindah soal — cukup ambil sekali dan cache
if (!isset($_SESSION['_jawaban_cache']) || !is_array($_SESSION['_jawaban_cache'])) {
    $_SESSION['_jawaban_cache']       = [];
    $_SESSION['_teks_jawaban_cache']  = [];
    $jr = $conn->query(
        "SELECT soal_id, jawaban, teks_jawaban FROM jawaban
         WHERE ujian_id=$ujianId AND peserta_id=$pesertaId"
    );
    if ($jr) while ($j = $jr->fetch_assoc()) {
        $_SESSION['_jawaban_cache'][$j['soal_id']]      = $j['jawaban'];
        if ($j['teks_jawaban'] !== null)
            $_SESSION['_teks_jawaban_cache'][$j['soal_id']] = $j['teks_jawaban'];
    }
}
$jawabans     = $_SESSION['_jawaban_cache'];
$teksJawabans = $_SESSION['_teks_jawaban_cache'] ?? [];

if (!isset($_SESSION['ragu'])) $_SESSION['ragu'] = [];
$raguList = $_SESSION['ragu'];

$jwbAktif   = $jawabans[$soalId] ?? null;
$isRagu     = in_array($soalId, $raguList);
$sdhJawab   = count($jawabans);
$belumJawab = $totalSoal - $sdhJawab;
$jumlahRagu = count($raguList);

// ── Acak urutan pilihan per peserta ──────────────────────────
if ($acakPilihan && $soal['tipe_soal'] === 'pg') {
    $sessionKey = "pilihan_order_{$soalId}";
    if (!isset($_SESSION[$sessionKey])) {
        $order = ['a','b','c','d'];
        $order = array_filter($order, fn($k) => !empty($soal['pilihan_'.$k]));
        $order = array_values($order);
        shuffle($order);
        $mapping = [];
        $labels  = ['a','b','c','d'];
        foreach ($order as $i => $asli) {
            $mapping[$labels[$i]] = $asli;
        }
        $_SESSION[$sessionKey] = $mapping;
    }
    $pilihanMapping = $_SESSION[$sessionKey];
} else {
    $pilihanMapping = null;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ujian — <?= e($namaAplikasi) ?></title>
<link href="<?= defined('CDN_BOOTSTRAP_CSS') ? CDN_BOOTSTRAP_CSS : 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' ?>" rel="stylesheet">
<link href="<?= defined('CDN_BOOTSTRAP_ICONS') ? CDN_BOOTSTRAP_ICONS : 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css' ?>" rel="stylesheet">
<style>
/* ── Reset & Base ───────────────────────────────────────────── */
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
body{background:#eef2f7;font-family:'Segoe UI',Arial,sans-serif;margin:0;min-height:100vh}

/* ── Topbar ─────────────────────────────────────────────────── */
.topbar{position:sticky;top:0;z-index:100;background:linear-gradient(90deg,#1a3faa,#1e40af);padding:0 20px;display:flex;align-items:center;height:56px;gap:14px;box-shadow:0 2px 10px rgba(0,0,0,.25)}
.topbar-name{color:#fff;font-weight:700;font-size:14px;flex:1;line-height:1.3;min-width:0}
.topbar-name span{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.topbar-name small{display:block;font-weight:400;font-size:11px;opacity:.75;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.timer-box{background:rgba(255,255,255,.15);border-radius:8px;padding:5px 14px;color:#fff;font-size:22px;font-weight:900;font-family:monospace;letter-spacing:2px;min-width:96px;text-align:center;border:1.5px solid rgba(255,255,255,.2);flex-shrink:0}
.timer-box.warning{background:#dc2626;border-color:#dc2626;animation:blink .8s infinite}
@keyframes blink{50%{opacity:.65}}
.btn-selesai-top{background:#f59e0b;border:none;color:#1e293b;font-weight:700;font-size:13px;border-radius:7px;padding:7px 14px;cursor:pointer;flex-shrink:0;white-space:nowrap}
.btn-selesai-top:hover{background:#d97706}

/* ── Layout Desktop ─────────────────────────────────────────── */
.main-wrap{display:flex;gap:14px;padding:14px;max-width:1200px;margin:0 auto}
.soal-area{flex:1;min-width:0}
.side-panel{width:220px;flex-shrink:0}

/* ── Kartu Soal ─────────────────────────────────────────────── */
.soal-card{background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.07);overflow:hidden}
.soal-card-head{display:flex;align-items:center;justify-content:space-between;padding:12px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;flex-wrap:wrap;gap:6px}
.soal-card-body{display:grid;grid-template-columns:1fr 1px 1fr;min-height:340px}
.soal-kiri{padding:22px 24px;overflow-y:auto}
.soal-divider{width:1px;background:#e2e8f0;margin:16px 0}
.soal-kanan{padding:22px 24px;display:flex;flex-direction:column;justify-content:flex-start}
.soal-card-foot{padding:12px 20px;border-top:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;background:#f8fafc;gap:10px}
.soal-badge{display:inline-flex;align-items:center;gap:6px;background:#eff6ff;color:#1a56db;font-size:12px;font-weight:700;border-radius:20px;padding:4px 12px;border:1px solid #bfdbfe}
.soal-text{font-size:15.5px;line-height:1.9;color:#1e293b}
.soal-img{max-width:100%;border-radius:8px;margin-bottom:14px;border:1px solid #e2e8f0;display:block}
.ragu-badge{background:#fef3c7;color:#b45309;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;border:1px solid #fcd34d}

/* ── Pilihan Jawaban ────────────────────────────────────────── */
.pilihan-label{font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;margin-bottom:12px}
.pilihan-item{display:flex;align-items:flex-start;gap:12px;padding:11px 14px;border-radius:10px;cursor:pointer;border:2px solid #e2e8f0;margin-bottom:8px;transition:all .15s;font-size:14.5px;color:#334155;background:#fafafa;user-select:none}
.pilihan-item:active{transform:scale(.98)}
.pilihan-item:hover{border-color:#1a56db;background:#eff6ff}
.pilihan-item.selected{border-color:#1a56db;background:#eff6ff}
.huruf-box{width:30px;height:30px;border-radius:50%;border:2px solid #cbd5e1;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;color:#64748b;flex-shrink:0;margin-top:1px;transition:all .15s}
.pilihan-item.selected .huruf-box{background:#1a56db;border-color:#1a56db;color:#fff}
.pilihan-teks{flex:1;line-height:1.5;padding-top:3px}
.pilihan-item.mcma-selected{border-color:#7c3aed;background:#f5f3ff}
.pilihan-item.mcma-selected .huruf-box{background:#7c3aed;border-color:#7c3aed;color:#fff}
.mcma-info{background:#f5f3ff;border:1px solid #ddd6fe;border-radius:8px;padding:8px 12px;margin-bottom:12px;font-size:13px;color:#6d28d9;font-weight:600}

/* ── Tombol Navigasi ────────────────────────────────────────── */
.btn-ragu{border:2px solid #f59e0b;color:#b45309;background:#fffbeb;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:all .15s}
.btn-ragu.aktif{background:#f59e0b;color:#fff;border-color:#f59e0b}
.btn-nav{padding:9px 20px;border-radius:9px;font-weight:700;font-size:14px;border:none;cursor:pointer;display:flex;align-items:center;gap:6px;transition:all .15s}
.btn-prev{background:#e2e8f0;color:#475569}
.btn-prev:hover:not(:disabled){background:#cbd5e1}
.btn-next{background:#1a56db;color:#fff}
.btn-next:hover:not(:disabled){background:#1e40af}
.btn-nav:disabled{opacity:.4;cursor:not-allowed}
.btn-nav:active:not(:disabled){transform:scale(.97)}
@keyframes spin{to{transform:rotate(360deg)}}

/* ── Panel Navigasi Soal ────────────────────────────────────── */
.panel-card{background:#fff;border-radius:14px;padding:16px;box-shadow:0 2px 10px rgba(0,0,0,.07);position:sticky;top:70px}
.panel-title{font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;margin-bottom:12px}
.nav-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:5px;margin-bottom:14px}
.nav-btn{aspect-ratio:1;border-radius:7px;border:1.5px solid #e2e8f0;background:#f8fafc;font-size:12px;font-weight:700;cursor:pointer;color:#64748b;transition:all .15s}
.nav-btn:hover{border-color:#1a56db;color:#1a56db;background:#eff6ff}
.nav-btn:active{transform:scale(.92)}
.nav-btn.answered{background:#1a56db;border-color:#1a56db;color:#fff}
.nav-btn.ragu{background:#f59e0b;border-color:#f59e0b;color:#fff}
.nav-btn.current{box-shadow:0 0 0 2.5px #1a56db,0 0 0 4.5px #bfdbfe}
.nav-btn.answered.ragu{background:#f59e0b;border-color:#f59e0b}
.legend{display:flex;flex-wrap:wrap;gap:6px;font-size:11px;color:#64748b;margin-bottom:14px;align-items:center}
.leg-dot{width:13px;height:13px;border-radius:4px;display:inline-block;flex-shrink:0}
.progress-info{font-size:12px;color:#64748b;margin-bottom:14px;line-height:1.8}
.btn-submit-side{width:100%;border-radius:9px;padding:11px;font-weight:700;font-size:14px;border:none;cursor:pointer;background:#10b981;color:#fff;transition:all .15s}
.btn-submit-side:hover{background:#059669}
.modal-content{border:none;border-radius:16px;box-shadow:0 8px 40px rgba(0,0,0,.18)}

/* ── Navigasi bawah mobile (sticky bottom bar) ──────────────── */
.mobile-nav-bar{display:none}

/* ── Drawer navigasi soal (mobile) ─────────────────────────── */
.nav-drawer-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:200;opacity:0;transition:opacity .25s}
.nav-drawer-backdrop.open{opacity:1}
.nav-drawer{position:fixed;bottom:0;left:0;right:0;background:#fff;border-radius:20px 20px 0 0;z-index:201;padding:20px 16px 32px;transform:translateY(100%);transition:transform .3s cubic-bezier(.32,1,.5,1);max-height:75vh;overflow-y:auto}
.nav-drawer.open{transform:translateY(0)}
.nav-drawer-handle{width:36px;height:4px;background:#e2e8f0;border-radius:2px;margin:0 auto 16px}
.nav-drawer-title{font-size:13px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;text-align:center}
.nav-drawer .nav-grid{grid-template-columns:repeat(7,1fr)}
.nav-drawer .progress-stat{display:flex;justify-content:center;gap:20px;margin-bottom:16px}
.nav-drawer .stat-pill{display:flex;align-items:center;gap:5px;font-size:12px;font-weight:700}

/* ── Responsive: Tablet ─────────────────────────────────────── */
@media(max-width:900px){
  .main-wrap{flex-direction:column;padding:10px;gap:10px;padding-bottom:80px}
  .side-panel{width:100%;order:2}
  .soal-area{order:1}
  .panel-card{position:static}
  .soal-card-body{grid-template-columns:1fr;min-height:unset}
  .soal-divider{display:none}
  .soal-kiri{padding:16px 16px 8px}
  .soal-kanan{padding:8px 16px 16px;border-top:1px solid #e2e8f0}
  .soal-text{font-size:15px}
  .pilihan-item{padding:10px 12px;font-size:14px}
  .huruf-box{width:28px;height:28px;font-size:12px}
  .nav-grid{grid-template-columns:repeat(6,1fr)}
  .nav-btn{font-size:11px}
  .btn-nav{padding:9px 14px;font-size:13px}
  .btn-ragu{padding:8px 12px;font-size:12px}
  .topbar{padding:0 12px;height:52px}
  .topbar-name{font-size:13px}
  .timer-box{font-size:18px;min-width:80px;padding:4px 10px}
  .btn-selesai-top{font-size:12px;padding:6px 10px}
  .soal-badge{font-size:11px}
  .soal-card-foot{flex-wrap:wrap;gap:8px}
}

/* ── Responsive: Mobile (≤ 600px) ──────────────────────────── */
@media(max-width:600px){
  /* Topbar: sembunyikan nama, tampilkan inisial + timer */
  .topbar{height:52px;padding:0 10px;gap:8px}
  .topbar-name span{display:none}
  .topbar-name small{display:none}
  .topbar-name::before{
    content:attr(data-inisial);
    display:flex;align-items:center;justify-content:center;
    width:34px;height:34px;border-radius:50%;
    background:rgba(255,255,255,.2);color:#fff;
    font-size:14px;font-weight:800
  }
  .timer-box{font-size:18px;min-width:72px;padding:4px 8px;letter-spacing:1px}
  .btn-selesai-top{display:none} /* diganti mobile-nav-bar */

  /* Layout: tanpa side panel, padding bawah untuk bottom bar */
  .main-wrap{padding:8px 8px 84px;gap:8px}
  .side-panel{display:none} /* navigasi soal via drawer */

  /* Kartu soal */
  .soal-card{border-radius:12px}
  .soal-card-head{padding:10px 14px}
  .soal-badge{font-size:11px;padding:3px 10px}
  .soal-kiri{padding:14px 14px 6px}
  .soal-kanan{padding:6px 14px 14px;border-top:1px solid #e2e8f0}
  .soal-text{font-size:15px;line-height:1.8}
  .pilihan-label{font-size:10px;margin-bottom:10px}

  /* Pilihan: lebih besar untuk jari */
  .pilihan-item{padding:13px 12px;font-size:14.5px;border-radius:12px;margin-bottom:9px}
  .huruf-box{width:34px;height:34px;font-size:13px;flex-shrink:0}
  .pilihan-teks{font-size:14px;line-height:1.6}

  /* Footer kartu: susun vertikal */
  .soal-card-foot{
    padding:10px 14px;
    display:grid;
    grid-template-columns:1fr auto 1fr;
    align-items:center;
    gap:8px
  }
  .btn-ragu{
    justify-content:center;
    padding:9px 10px;
    font-size:12px;
    border-radius:10px
  }
  .btn-nav{
    padding:11px 10px;
    font-size:13px;
    border-radius:10px;
    justify-content:center
  }
  .btn-prev{width:100%}
  .btn-next{width:100%}

  /* Bottom nav bar sticky */
  .mobile-nav-bar{
    display:flex;
    position:fixed;bottom:0;left:0;right:0;
    background:#fff;
    border-top:1px solid #e2e8f0;
    padding:8px 12px;
    gap:8px;
    z-index:150;
    box-shadow:0 -2px 12px rgba(0,0,0,.1)
  }
  .mobile-nav-bar .btn-nav-soal{
    flex:1;
    background:#f1f5f9;color:#475569;
    border:none;border-radius:10px;
    padding:10px 6px;font-size:13px;font-weight:700;
    cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px
  }
  .mobile-nav-bar .btn-open-drawer{
    flex:2;
    background:#eff6ff;color:#1a56db;
    border:1.5px solid #bfdbfe;border-radius:10px;
    padding:10px 8px;font-size:12px;font-weight:700;
    cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px
  }
  .mobile-nav-bar .btn-submit-mobile{
    flex:1;
    background:#10b981;color:#fff;
    border:none;border-radius:10px;
    padding:10px 6px;font-size:13px;font-weight:700;
    cursor:pointer;display:flex;align-items:center;justify-content:center;gap:4px
  }
  .nav-drawer .nav-grid{grid-template-columns:repeat(7,1fr);gap:6px}
  .nav-drawer .nav-btn{font-size:11px;border-radius:8px}

  /* Modal lebih nyaman di HP */
  .modal-dialog{margin:10px}
  .modal-content{border-radius:20px}
}
</style>
</head>
<body>

<?php $_inisial = mb_strtoupper(mb_substr(strip_tags($_SESSION['peserta_nama'] ?? 'P'), 0, 1)); ?>
<div class="topbar">
  <div class="topbar-name" data-inisial="<?= e($_inisial) ?>">
    <span><?= e($_SESSION['peserta_nama']) ?></span>
    <small><?= e($_SESSION['peserta_kelas']) ?> &middot; <?= e($_SESSION['peserta_sekolah']) ?></small>
  </div>
  <div style="display:flex;align-items:center;gap:8px">
    <i class="bi bi-clock" style="color:rgba(255,255,255,.7);font-size:16px"></i>
    <div class="timer-box" id="timerBox">--:--</div>
  </div>
  <button class="btn-selesai-top" onclick="bukaModalSelesai()">
    <i class="bi bi-check-circle me-1"></i>Selesai
  </button>
</div>

<!-- Mobile Bottom Nav Bar -->
<div class="mobile-nav-bar" id="mobileNavBar">
  <button class="btn-nav-soal" id="btnPrevMobile" onclick="pindahSoal(window.noAktif-1)" <?php echo $noAktif<=1?'disabled':''; ?>>
    <i class="bi bi-chevron-left"></i>
  </button>
  <button class="btn-open-drawer" onclick="bukaDrawer()">
    <i class="bi bi-grid-3x3-gap me-1"></i>
    <span id="mNavSoal">Soal <?= $noAktif ?>/<?= $totalSoal ?></span>&nbsp;
    <span style="background:#1a56db;color:#fff;border-radius:20px;padding:1px 8px;font-size:11px" id="mDijawabBadge"><?= $sdhJawab ?></span>
  </button>
  <button class="btn-submit-mobile" onclick="bukaModalSelesai()">
    <i class="bi bi-send"></i>
  </button>
  <button class="btn-nav-soal" id="btnNextMobile" onclick="<?php echo $noAktif < $totalSoal ? 'pindahSoal(window.noAktif+1)' : 'bukaModalSelesai()'; ?>">
    <i class="bi bi-chevron-right"></i>
  </button>
</div>

<!-- Drawer Navigasi Soal Mobile -->
<div class="nav-drawer-backdrop" id="drawerBackdrop" onclick="tutupDrawer()"></div>
<div class="nav-drawer" id="navDrawer">
  <div class="nav-drawer-handle"></div>
  <div class="nav-drawer-title">Navigasi Soal</div>
  <div style="display:flex;justify-content:center;gap:16px;margin-bottom:14px">
    <div style="display:flex;align-items:center;gap:5px;font-size:12px;font-weight:700">
      <div style="width:12px;height:12px;background:#1a56db;border-radius:3px"></div>
      <span id="dDijawab"><?= $sdhJawab ?></span> dijawab
    </div>
    <div style="display:flex;align-items:center;gap:5px;font-size:12px;font-weight:700">
      <div style="width:12px;height:12px;background:#f59e0b;border-radius:3px"></div>
      <span id="dRagu"><?= $jumlahRagu ?></span> ragu
    </div>
    <div style="display:flex;align-items:center;gap:5px;font-size:12px;font-weight:700;color:#ef4444">
      <div style="width:12px;height:12px;background:#fef2f2;border:1.5px solid #fca5a5;border-radius:3px"></div>
      <span id="dBelum"><?= $belumJawab ?></span> belum
    </div>
  </div>
  <div class="nav-grid" id="drawerNavGrid">
    <?php foreach ($soalList as $idx => $s):
      $n = $idx+1; $sid = $s['id'];
      $cls = '';
      if ($n===$noAktif)            $cls .= ' current';
      if (isset($jawabans[$sid]))   $cls .= ' answered';
      if (in_array($sid,$raguList)) $cls .= ' ragu';
    ?>
    <button class="nav-btn<?= $cls ?>" id="drawernav-<?= $n ?>" onclick="pindahSoal(<?= $n ?>);tutupDrawer()"><?= $n ?></button>
    <?php endforeach; ?>
  </div>
  <button style="width:100%;background:#10b981;color:#fff;border:none;border-radius:10px;padding:12px;font-size:14px;font-weight:700;cursor:pointer;margin-top:8px" onclick="bukaModalSelesai();tutupDrawer()">
    <i class="bi bi-send me-2"></i>Selesai &amp; Kirim
  </button>
</div>

<div class="main-wrap">

<div class="soal-area">
    <div class="soal-card">

      <div class="soal-card-head">
        <div class="soal-badge"><i class="bi bi-question-circle"></i> Soal <?= $noAktif ?> dari <?= $totalSoal ?></div>
        <?php if ($isRagu): ?><span class="ragu-badge">&#9888; Ragu-ragu</span><?php endif; ?>
      </div>

      <div class="soal-card-body">

        <div class="soal-kiri">
          <div id="teksBacaanWrap">
          <?php if (!empty($soal['teks_bacaan'])): ?>
          <div style="background:#f0f9ff;border-left:4px solid #1a56db;border-radius:0 8px 8px 0;padding:14px 16px;margin-bottom:16px;font-size:13.5px;line-height:1.9;color:#1e293b;">
            <div style="font-size:10px;font-weight:700;color:#1a56db;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">
              &#128196; Bacalah teks berikut!
            </div>
            <?= nl2br(e($soal['teks_bacaan'])) ?>
          </div>
          <?php endif; ?>
          <?php if ($soal['gambar']): ?>
          <img src="<?= BASE_URL ?>/assets/uploads/soal/<?= e($soal['gambar']) ?>" class="soal-img" alt="Gambar soal">
          <?php endif; ?>
          </div>
          <div class="soal-text"><?= nl2br(e($soal['pertanyaan'])) ?></div>
        </div>

        <div class="soal-divider"></div>

        <div class="soal-kanan">
          <div class="pilihan-label">Pilih Jawaban</div>
          <div id="pilihanWrap">
          <?php if ($soal['tipe_soal'] === 'bs'): ?>
            <?php foreach (['benar'=>'Benar','salah'=>'Salah'] as $val=>$label): ?>
            <div class="pilihan-item <?= $jwbAktif===$val?'selected':'' ?>" onclick="pilihJawaban('<?= $val ?>',this,<?= $soalId ?>)">
              <div class="huruf-box"><?= $val==='benar'?'B':'S' ?></div>
              <div class="pilihan-teks"><?= $label ?></div>
            </div>
            <?php endforeach; ?>

          <?php elseif ($soal['tipe_soal'] === 'mcma'):
            $jwbMcmaArr = $jwbAktif ? explode(',', $jwbAktif) : [];
          ?>
            <div class="mcma-info">
              <i class="bi bi-info-circle me-1"></i>Boleh pilih lebih dari satu jawaban yang benar.
            </div>
            <?php foreach (['a','b','c','d'] as $h):
              $teks = $soal['pilihan_'.$h]??'';
              if($teks==='') continue;
              $dipilih = in_array($h, $jwbMcmaArr);
            ?>
            <div class="pilihan-item <?= $dipilih?'mcma-selected':'' ?>"
                 onclick="pilihMcma('<?= $h ?>',this,<?= $soalId ?>)">
              <div class="huruf-box"><?= strtoupper($h) ?></div>
              <div class="pilihan-teks"><?= e($teks) ?></div>
              <div style="margin-left:auto;flex-shrink:0">
                <div class="mcma-check" style="width:20px;height:20px;border-radius:4px;border:2px solid <?= $dipilih?'#7c3aed':'#cbd5e1' ?>;background:<?= $dipilih?'#7c3aed':'transparent' ?>;display:flex;align-items:center;justify-content:center">
                  <?php if($dipilih): ?><svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2 6l3 3 5-5" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg><?php endif; ?>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
            <input type="hidden" id="mcmaValue" value="<?= e($jwbAktif??'') ?>">

          <?php elseif ($soal['tipe_soal'] === 'essay'): ?>
            <div style="background:#f0fdf4;border:1.5px solid #86efac;border-radius:10px;padding:10px 14px;margin-bottom:12px;font-size:13px;color:#166534;font-weight:600">
              <i class="bi bi-pencil-square me-1"></i>Soal Uraian — Tulis jawaban Anda di bawah ini.
            </div>
            <?php $teksEssayAktif = $teksJawabans[$soalId] ?? ''; ?>
            <textarea id="essayJawaban" class="form-control" rows="6" maxlength="3000"
                      placeholder="Tulis jawaban Anda di sini..."
                      style="font-size:14px;line-height:1.8;resize:vertical;border-radius:10px;border:2px solid #e2e8f0;padding:12px"
                      oninput="simpanEssay(this, <?= $soalId ?>)"><?= e($teksEssayAktif) ?></textarea>
            <div class="d-flex justify-content-between mt-1 px-1">
              <span class="text-muted" style="font-size:11px">Maksimal 3000 karakter</span>
              <span id="essayCharCount" style="font-size:11px;color:#64748b"><?= mb_strlen($teksEssayAktif) ?>/3000</span>
            </div>
            <div id="essaySaveStatus" class="mt-2" style="font-size:12px;color:#22c55e;display:none">
              <i class="bi bi-check-circle me-1"></i>Tersimpan
            </div>

          <?php else: ?>
            <?php
            $adaGambarPilihan = isset($soal['gambar_pilihan_a']);
            $pilihanLoop = $pilihanMapping ? array_keys($pilihanMapping) : ['a','b','c','d'];
            foreach ($pilihanLoop as $hTampil):
                $hAsli   = $pilihanMapping ? $pilihanMapping[$hTampil] : $hTampil;
                $teks    = $soal['pilihan_'.$hAsli] ?? '';
                $gambarP = $adaGambarPilihan ? ($soal['gambar_pilihan_'.$hAsli] ?? '') : '';
                if ($teks==='' && $gambarP==='') continue;
            ?>
            <div class="pilihan-item <?= $jwbAktif===$hAsli?'selected':'' ?>"
                 onclick="pilihJawaban('<?= $hAsli ?>',this,<?= $soalId ?>)">
              <div class="huruf-box"><?= strtoupper($hTampil) ?></div>
              <div class="pilihan-teks">
                <?php if ($gambarP): ?>
                <img src="<?= BASE_URL ?>/assets/uploads/soal/<?= e($gambarP) ?>"
                     style="max-width:180px;max-height:100px;border-radius:6px;display:block;margin-bottom:4px"
                     alt="Gambar pilihan <?= strtoupper($hTampil) ?>">
                <?php endif; ?>
                <?= e($teks) ?>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
          </div>
        </div>

      </div>

      <div class="soal-card-foot">
        <button class="btn-nav btn-prev" onclick="pindahSoal(<?= $noAktif-1 ?>)" <?= $noAktif<=1?'disabled':'' ?>>
          <i class="bi bi-chevron-left"></i> Sebelumnya
        </button>
        <button class="btn-ragu <?= $isRagu?'aktif':'' ?>" id="btnRagu" onclick="toggleRagu(<?= $soalId ?>)">
          <i class="bi bi-flag-fill"></i> <?= $isRagu?'Hapus Ragu':'Ragu-ragu' ?>
        </button>
        <?php if ($noAktif < $totalSoal): ?>
        <button class="btn-nav btn-next" onclick="pindahSoal(<?= $noAktif+1 ?>)">
          Selanjutnya <i class="bi bi-chevron-right"></i>
        </button>
        <?php else: ?>
        <button class="btn-nav btn-next" style="background:#10b981" onclick="bukaModalSelesai()">
          <i class="bi bi-check-circle"></i> Selesai
        </button>
        <?php endif; ?>
      </div>

    </div>
  </div>

  <div class="side-panel">
    <div class="panel-card">
      <div class="panel-title">Navigasi Soal</div>
      <div class="nav-grid">
      <?php foreach ($soalList as $idx => $s):
        $n = $idx+1; $sid = $s['id'];
        $cls = '';
        if ($n===$noAktif)            $cls .= ' current';
        if (isset($jawabans[$sid]))   $cls .= ' answered';
        if (in_array($sid,$raguList)) $cls .= ' ragu';
      ?>
      <button class="nav-btn<?= $cls ?>" id="navbtn-<?= $n ?>" onclick="pindahSoal(<?= $n ?>)"><?= $n ?></button>
      <?php endforeach; ?>
      </div>

      <div class="legend">
        <span class="leg-dot" style="background:#1a56db"></span>Dijawab
        <span class="leg-dot" style="background:#f59e0b;margin-left:6px"></span>Ragu
        <span class="leg-dot" style="background:#f8fafc;border:1.5px solid #e2e8f0;margin-left:6px"></span>Belum
      </div>

      <div class="progress-info">
        <span style="color:#1a56db;font-weight:700" id="countDijawab"><?= $sdhJawab ?></span> dijawab &nbsp;&middot;&nbsp;
        <span style="color:#f59e0b;font-weight:700" id="countRagu"><?= $jumlahRagu ?></span> ragu-ragu &nbsp;&middot;&nbsp;
        <span style="color:#ef4444;font-weight:700" id="countBelum"><?= $belumJawab ?></span> belum
      </div>

      <button class="btn-submit-side" onclick="bukaModalSelesai()">
        <i class="bi bi-send me-2"></i>Selesai &amp; Kirim
      </button>
    </div>
  </div>

</div>

<!-- Modal Konfirmasi -->
<div class="modal fade" id="modalSelesai" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold"><i class="bi bi-send-check me-2 text-success"></i>Kirim Jawaban?</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center py-3">
        <div class="d-flex justify-content-center gap-4 mb-3">
          <div><div style="font-size:32px;font-weight:900;color:#1a56db" id="mDijawab"><?= $sdhJawab ?></div><div style="font-size:12px;color:#94a3b8">Dijawab</div></div>
          <div><div style="font-size:32px;font-weight:900;color:#f59e0b" id="mRagu"><?= $jumlahRagu ?></div><div style="font-size:12px;color:#94a3b8">Ragu-ragu</div></div>
          <div><div style="font-size:32px;font-weight:900;color:#ef4444" id="mBelum"><?= $belumJawab ?></div><div style="font-size:12px;color:#94a3b8">Belum</div></div>
        </div>
        <p class="text-muted mb-0" style="font-size:13px">Jawaban yang sudah dikirim <strong>tidak dapat diubah</strong>.</p>
      </div>
      <div class="modal-footer border-0 justify-content-center gap-2 pt-0">
        <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal"><i class="bi bi-arrow-left me-1"></i>Kembali Periksa</button>
        <form method="POST" action="<?= BASE_URL ?>/ujian/submit.php">
          <input type="hidden" name="confirm" value="1">
          <button type="submit" class="btn btn-success fw-bold px-4"><i class="bi bi-send me-1"></i>Ya, Kirim Sekarang</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Overlay Start -->
<div id="fsStart" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.98);z-index:99998;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:30px">
  <div style="font-size:56px;margin-bottom:16px">🔒</div>
  <h2 id="fsTitleText" style="color:#fff;font-size:22px;font-weight:900;margin-bottom:8px">Mode Ujian Penuh</h2>
  <p id="fsInfoText" style="color:#94a3b8;font-size:15px;margin-bottom:6px;max-width:380px">Ujian akan berjalan dalam mode layar penuh.</p>
  <p style="color:#f59e0b;font-size:13px;font-weight:700;margin-bottom:28px">Dilarang berpindah tab atau keluar layar penuh selama ujian.</p>
  <button id="btnMulaiFs" style="background:#1a56db;color:#fff;border:none;border-radius:12px;padding:14px 40px;font-size:16px;font-weight:800;cursor:pointer;display:flex;align-items:center;gap:8px;margin:0 auto">
    🚀 Mulai Ujian
  </button>
  <p style="color:#475569;font-size:12px;margin-top:20px">Klik tombol di atas untuk memulai</p>
</div>

<!-- Overlay Peringatan -->
<div id="fsOverlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.97);z-index:99999;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:30px;transition:background .12s ease, box-shadow .12s ease">
  <div style="font-size:60px;margin-bottom:16px">⚠️</div>
  <h2 style="color:#fff;font-size:24px;font-weight:900;margin-bottom:8px;letter-spacing:.2px">Peringatan: Anda Keluar dari Mode Ujian!</h2>
  <p style="color:#cbd5e1;font-size:15px;font-weight:600;margin-bottom:8px">Segera kembali ke layar penuh. Pelanggaran dicatat oleh sistem.</p>
  <p style="color:#f59e0b;font-size:15px;font-weight:900;margin-bottom:24px" id="fsHitung">Kembali dalam 5 detik...</p>
  <button id="btnFsKembali" onclick="masuKembali()" style="background:#1a56db;color:#fff;border:none;border-radius:10px;padding:12px 32px;font-size:15px;font-weight:800;cursor:pointer">
    🔒 Kembali ke Ujian
  </button>
  <p style="color:#ef4444;font-size:12px;margin-top:16px" id="fsWarning"></p>
</div>

<script src="<?= defined('CDN_BOOTSTRAP_JS') ? CDN_BOOTSTRAP_JS : 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js' ?>"></script>
<script>
const BASE_URL  = '<?= BASE_URL ?>';
const soalId    = <?= $soalId ?>;
const noAktif   = <?= $noAktif ?>;
const totalSoal = <?= $totalSoal ?>;
let dijawab     = <?= $sdhJawab ?>;
let jumlahRagu  = <?= $jumlahRagu ?>;
let sisaDetik   = <?= $sisaDetik ?>;
let sdh         = <?= $jwbAktif ? 'true' : 'false' ?>;

const timerBox = document.getElementById('timerBox');
function updateTimer(){
  if(sisaDetik<=0){timerBox.textContent='00:00';timerBox.classList.add('warning');window.location.href=BASE_URL+'/ujian/submit.php?auto=1';return;}
  const m=Math.floor(sisaDetik/60),s=sisaDetik%60;
  timerBox.textContent=String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
  if(sisaDetik<=300) timerBox.classList.add('warning');
  sisaDetik--;
}
setInterval(updateTimer,1000); updateTimer();

function pilihJawaban(val,el,sid){
  document.querySelectorAll('.pilihan-item').forEach(p=>p.classList.remove('selected'));
  el.classList.add('selected');
  const _soalId=sid||soalId, _noAktif=noAktif;
  fetch(BASE_URL+'/ujian/ajax_jawab.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`soal_id=${_soalId}&jawaban=${val}`})
  .then(r=>r.json()).then(d=>{
    if(d.expired){window.location.href=BASE_URL+'/ujian/submit.php?auto=1';return;}
    if(d.ok){
      const nb=document.getElementById('navbtn-'+_noAktif);
      if(nb){nb.classList.add('answered');nb.classList.remove('current');}
      if(!sdh){sdh=true;dijawab++;updateStat();}
    }
  }).catch(()=>{});
}

let mcmaSelected=(document.getElementById('mcmaValue')?.value||'').split(',').filter(v=>v!=='');
function pilihMcma(huruf,el,sid){
  const idx=mcmaSelected.indexOf(huruf);
  const check=el.querySelector('.mcma-check');
  if(idx===-1){
    mcmaSelected.push(huruf);
    el.classList.add('mcma-selected');
    el.querySelector('.huruf-box').style.cssText='background:#7c3aed;border-color:#7c3aed;color:#fff';
    if(check){check.style.background='#7c3aed';check.style.borderColor='#7c3aed';check.innerHTML='<svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2 6l3 3 5-5" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>';}
  }else{
    mcmaSelected.splice(idx,1);
    el.classList.remove('mcma-selected');
    el.querySelector('.huruf-box').style.cssText='';
    if(check){check.style.background='transparent';check.style.borderColor='#cbd5e1';check.innerHTML='';}
  }
  mcmaSelected.sort();
  const jwbStr=mcmaSelected.join(',');
  if(document.getElementById('mcmaValue'))document.getElementById('mcmaValue').value=jwbStr;
  if(mcmaSelected.length>0){
    const _soalId=sid||soalId,_noAktif=noAktif;
    fetch(BASE_URL+'/ujian/ajax_jawab.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`soal_id=${_soalId}&jawaban=${encodeURIComponent(jwbStr)}&tipe=mcma`})
    .then(r=>r.json()).then(d=>{
      if(d.expired){window.location.href=BASE_URL+'/ujian/submit.php?auto=1';return;}
      if(d.ok){const nb=document.getElementById('navbtn-'+_noAktif);if(nb){nb.classList.add('answered');nb.classList.remove('current');}if(!sdh){sdh=true;dijawab++;updateStat();}}
    }).catch(()=>{});
  }
}

function updateStat(){
  const belum=totalSoal-dijawab;
  ['countBelum','mBelum'].forEach(id=>{const e=document.getElementById(id);if(e)e.textContent=belum;});
  ['countDijawab','mDijawab'].forEach(id=>{const e=document.getElementById(id);if(e)e.textContent=dijawab;});
  ['countRagu','mRagu'].forEach(id=>{const e=document.getElementById(id);if(e)e.textContent=jumlahRagu;});
  // Update mobile drawer stats
  const dD=document.getElementById('dDijawab');if(dD)dD.textContent=dijawab;
  const dR=document.getElementById('dRagu');if(dR)dR.textContent=jumlahRagu;
  const dB=document.getElementById('dBelum');if(dB)dB.textContent=belum;
  const mb=document.getElementById('mDijawabBadge');if(mb)mb.textContent=dijawab;
}

// ── Drawer navigasi soal (mobile) ────────────────────────────
function bukaDrawer(){
  document.getElementById('navDrawer').classList.add('open');
  const bd=document.getElementById('drawerBackdrop');
  bd.style.display='block';
  setTimeout(()=>bd.classList.add('open'),10);
  document.body.style.overflow='hidden';
}
function tutupDrawer(){
  document.getElementById('navDrawer').classList.remove('open');
  const bd=document.getElementById('drawerBackdrop');
  bd.classList.remove('open');
  setTimeout(()=>{bd.style.display='none';},260);
  document.body.style.overflow='';
}

function toggleRagu(sid){
  fetch(BASE_URL+'/ujian/ajax_ragu.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`soal_id=${sid}`})
  .then(r=>r.json()).then(d=>{
    const btn=document.getElementById('btnRagu');
    const nb=document.getElementById('navbtn-'+noAktif);
    let badge=document.querySelector('.ragu-badge');
    if(d.ragu){
      btn.classList.add('aktif');btn.innerHTML='<i class="bi bi-flag-fill"></i> Hapus Ragu';
      if(nb)nb.classList.add('ragu');jumlahRagu++;
      if(!badge){badge=document.createElement('span');badge.className='ragu-badge';badge.textContent='⚠ Ragu-ragu';document.querySelector('.soal-card-head').appendChild(badge);}
    }else{
      btn.classList.remove('aktif');btn.innerHTML='<i class="bi bi-flag-fill"></i> Ragu-ragu';
      if(nb)nb.classList.remove('ragu');jumlahRagu=Math.max(0,jumlahRagu-1);
      if(badge)badge.remove();
    }
    updateStat();
  }).catch(()=>{});
}

function pindahSoal(no){
  if(no<1||no>totalSoal)return;
  document.querySelectorAll('.nav-btn').forEach(b=>b.classList.remove('current'));
  const nb=document.getElementById('navbtn-'+no);
  if(nb)nb.classList.add('current');
  fetch(BASE_URL+'/ujian/ajax_soal.php?no='+no)
  .then(r=>r.json())
  .then(d=>{
    if(!d.ok){window.location.href=BASE_URL+'/ujian/soal.php?no='+no;return;}
    window.noAktif=d.no;window.soalId=d.soalId;
    clearTimeout(essayTimer);essayTimer=null;
    sdh=d.navBtns?.some(b=>b.n===d.no&&b.cls?.includes('answered'))??!!d.jwbAktif;
    mcmaSelected=(d.jwbAktif&&d.tipe==='mcma')?d.jwbAktif.split(',').filter(v=>v!==''):[];
    document.querySelector('.soal-badge').innerHTML='<i class="bi bi-question-circle"></i> Soal '+d.no+' dari '+d.total;
    document.querySelector('.soal-text').innerHTML=d.pertanyaan;
    document.getElementById('pilihanWrap').innerHTML=d.pilihanHtml;
    const tbEl=document.getElementById('teksBacaanWrap');
    if(tbEl)tbEl.innerHTML=d.teksBacaan+d.gambar;
    const raguBadge=document.querySelector('.ragu-badge');
    if(d.isRagu){if(!raguBadge){const bd=document.createElement('span');bd.className='ragu-badge';bd.textContent='⚠ Ragu-ragu';document.querySelector('.soal-card-head').appendChild(bd);}}
    else{if(raguBadge)raguBadge.remove();}
    const btnRagu=document.getElementById('btnRagu');
    if(btnRagu){btnRagu.className='btn-ragu'+(d.isRagu?' aktif':'');btnRagu.innerHTML='<i class="bi bi-flag-fill"></i> '+(d.isRagu?'Hapus Ragu':'Ragu-ragu');btnRagu.onclick=()=>toggleRagu(d.soalId);}
    const btnPrev=document.querySelector('.btn-prev');
    const btnNext=document.querySelector('.btn-next');
    if(btnPrev){btnPrev.disabled=d.no<=1;btnPrev.onclick=()=>pindahSoal(d.no-1);}
    if(btnNext){
      if(d.no<d.total){btnNext.innerHTML='Selanjutnya <i class="bi bi-chevron-right"></i>';btnNext.style.background='';btnNext.onclick=()=>pindahSoal(d.no+1);}
      else{btnNext.innerHTML='<i class="bi bi-check-circle"></i> Selesai';btnNext.style.background='#10b981';btnNext.onclick=bukaModalSelesai;}
    }
    dijawab=d.sdhJawab;jumlahRagu=d.jumlahRagu;updateStat();
    // Sync mobile bottom bar label
    const mns=document.getElementById('mNavSoal');if(mns)mns.textContent='Soal '+d.no+'/'+d.total;
    // Sync prev/next mobile buttons
    const bPm=document.getElementById('btnPrevMobile');if(bPm)bPm.disabled=d.no<=1;
    const bNm=document.getElementById('btnNextMobile');
    if(bNm){bNm.onclick=d.no<d.total?()=>pindahSoal(d.no+1):bukaModalSelesai;}
    // Sync drawer nav buttons
    d.navBtns.forEach(btn=>{const el=document.getElementById('drawernav-'+btn.n);if(el)el.className='nav-btn'+(btn.cls?' '+btn.cls:'');});
    d.navBtns.forEach(btn=>{const el=document.getElementById('navbtn-'+btn.n);if(el)el.className='nav-btn'+(btn.cls?' '+btn.cls:'');});
    history.replaceState(null,'',BASE_URL+'/ujian/soal.php?no='+d.no);
  })
  .catch(()=>{window.location.href=BASE_URL+'/ujian/soal.php?no='+no;});
}

function bukaModalSelesai(){new bootstrap.Modal(document.getElementById('modalSelesai')).show();}

// Ping tetap hidup setiap 60 detik
setInterval(()=>{fetch(BASE_URL+'/ujian/ajax_jawab.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'ping=1'}).catch(()=>{});},60000);

let essayTimer=null;
function simpanEssay(el,sid){
  const teks=el.value;
  const counter=document.getElementById('essayCharCount');
  if(counter)counter.textContent=teks.length+'/3000';
  const status=document.getElementById('essaySaveStatus');
  if(status)status.style.display='none';
  clearTimeout(essayTimer);
  essayTimer=setTimeout(()=>{
    if(teks.trim()==='')return;
    fetch(BASE_URL+'/ujian/ajax_jawab.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'soal_id='+sid+'&jawaban=essay&teks_jawaban='+encodeURIComponent(teks)+'&tipe=essay'})
    .then(r=>r.json()).then(d=>{
      if(d.expired){window.location.href=BASE_URL+'/ujian/submit.php?auto=1';return;}
      if(d.ok){if(status){status.style.display='block';setTimeout(()=>{status.style.display='none';},2000);}const nb=document.getElementById('navbtn-'+noAktif);if(nb)nb.classList.add('answered');if(!sdh){sdh=true;dijawab++;updateStat();}}
    }).catch(()=>{});
  },800);
}

// ── Fullscreen & Anti Pindah Tab ──────────────────────────────
let pelanggaranCount=0,fsHitungInterval=null,fsSedangReload=true;
let alarmInterval=null, alarmCtx=null;
let flashInterval=null;
const fsOverlay=document.getElementById('fsOverlay');
const fsWarning=document.getElementById('fsWarning');
const fsHitung=document.getElementById('fsHitung');

function isFullscreen(){return!!(document.fullscreenElement||document.webkitFullscreenElement||document.mozFullScreenElement||document.msFullscreenElement);}
function masukFullscreen(){
  const el=document.documentElement;
  let p;
  if(el.requestFullscreen)p=el.requestFullscreen();
  else if(el.webkitRequestFullscreen)p=el.webkitRequestFullscreen();
  else if(el.mozRequestFullScreen)p=el.mozRequestFullScreen();
  else if(el.msRequestFullscreen)p=el.msRequestFullscreen();
  if(p&&typeof p.catch==='function')p.catch(()=>{});
}
function setBtnFsState(enabled, text){
  const btnFsKembali=document.getElementById('btnFsKembali');
  if(!btnFsKembali)return;
  btnFsKembali.disabled=!enabled;
  btnFsKembali.style.opacity=enabled?'1':'0.7';
  btnFsKembali.style.cursor=enabled?'pointer':'not-allowed';
  if(text) btnFsKembali.textContent=text;
}
function startAlarm(){
  stopAlarm();
  try{
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    if(!AudioCtx) return;
    alarmCtx = new AudioCtx();
    const beep = () => {
      if(!alarmCtx) return;
      const now = alarmCtx.currentTime;
      const makeTone = (freq, start, duration, peakGain) => {
        const osc = alarmCtx.createOscillator();
        const gain = alarmCtx.createGain();
        osc.type = 'sawtooth';
        osc.frequency.value = freq;
        gain.gain.value = 0.0001;
        osc.connect(gain);
        gain.connect(alarmCtx.destination);
        gain.gain.exponentialRampToValueAtTime(peakGain, now + start + 0.01);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + start + duration);
        osc.start(now + start);
        osc.stop(now + start + duration + 0.02);
      };
      makeTone(1040, 0.00, 0.18, 0.42);
      makeTone(1320, 0.20, 0.18, 0.48);
      makeTone(920,  0.42, 0.22, 0.40);
    };
    beep();
    alarmInterval = setInterval(beep, 420);
  }catch(e){}
}
function stopAlarm(){
  if(alarmInterval){ clearInterval(alarmInterval); alarmInterval=null; }
  if(alarmCtx){
    try{ alarmCtx.close(); }catch(e){}
    alarmCtx = null;
  }
}
function startFlash(){
  stopFlash();
  if(!fsOverlay) return;
  let on = false;
  flashInterval = setInterval(() => {
    on = !on;
    fsOverlay.style.background = on ? 'rgba(127,29,29,.98)' : 'rgba(15,23,42,.97)';
    fsOverlay.style.boxShadow = on ? 'inset 0 0 0 6px rgba(239,68,68,.35)' : 'none';
  }, 180);
}
function stopFlash(){
  if(flashInterval){ clearInterval(flashInterval); flashInterval=null; }
  if(fsOverlay){
    fsOverlay.style.background = 'rgba(15,23,42,.97)';
    fsOverlay.style.boxShadow = 'none';
  }
}
function tampilkanFsBlocked(){
  fsOverlay.style.display='flex';
  fsWarning.textContent='Browser memblokir fullscreen otomatis. Klik tombol untuk melanjutkan.';
  setBtnFsState(true, '🔒 Klik untuk Layar Penuh');
}
function tampilkanPeringatan(){
  if(document.getElementById('fsStart').style.display==='flex')return;
  if(fsOverlay.style.display==='flex')return;
  pelanggaranCount++;
  fetch(BASE_URL+'/ujian/ajax_jawab.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'violation=1'}).catch(()=>{});
  fsOverlay.style.display='flex';
  fsWarning.textContent='⚠ Pelanggaran dicatat oleh sistem!';
  let hitung=5;fsHitung.textContent='Kembali dalam '+hitung+' detik...';
  setBtnFsState(false, '🔒 Tunggu hitungan selesai');
  startAlarm();
  startFlash();
  if(fsHitungInterval)clearInterval(fsHitungInterval);
  fsHitungInterval=setInterval(()=>{hitung--;fsHitung.textContent='Kembali dalam '+hitung+' detik...';if(hitung<=0){clearInterval(fsHitungInterval);masuKembali();}},1000);
}
function masuKembali(){
  if(fsHitungInterval)clearInterval(fsHitungInterval);
  setBtnFsState(true, '🔒 Kembali ke Ujian');
  const cobaMasuk = () => {
    masukFullscreen();
    setTimeout(() => {
      if (isFullscreen()) {
        fsOverlay.style.display='none';
        fsWarning.textContent='';
        stopAlarm();
        stopFlash();
      } else {
        tampilkanFsBlocked();
      }
    }, 250);
  };
  cobaMasuk();
  setTimeout(() => { if (!isFullscreen() && fsOverlay.style.display !== 'none') cobaMasuk(); }, 900);
}
function cekFullscreen(){if(fsSedangReload)return;if(!sessionStorage.getItem('fs_started'))return;if(!isFullscreen())tampilkanPeringatan();}
document.addEventListener('fullscreenchange',cekFullscreen);
document.addEventListener('webkitfullscreenchange',cekFullscreen);
document.addEventListener('mozfullscreenchange',cekFullscreen);
document.addEventListener('MSFullscreenChange',cekFullscreen);
document.addEventListener('visibilitychange',()=>{if(fsSedangReload)return;if(!sessionStorage.getItem('fs_started'))return;if(document.hidden)tampilkanPeringatan();});
window.addEventListener('blur',()=>{if(fsSedangReload)return;if(!sessionStorage.getItem('fs_started'))return;setTimeout(()=>{if(!document.hasFocus()&&!isFullscreen())tampilkanPeringatan();},500);});
document.addEventListener('contextmenu',e=>e.preventDefault());
document.addEventListener('keydown',e=>{
  const key=e.key.toLowerCase();
  if(e.altKey&&(key==='tab'||key==='f4')){e.preventDefault();tampilkanPeringatan();}
  if(e.ctrlKey&&(key==='w'||key==='t'||key==='n'||key==='r'))e.preventDefault();
  if(key==='escape'){e.preventDefault();if(sessionStorage.getItem('fs_started')&&!isFullscreen())masukFullscreen();}
  if(key==='f11'){e.preventDefault();masukFullscreen();}
});
window.addEventListener('load',()=>{
  const sudahMulai=sessionStorage.getItem('fs_started');
  const overlay=document.getElementById('fsStart');
  const judulFs=document.getElementById('fsTitleText');
  const infoFs=document.getElementById('fsInfoText');
  const btnMulai=document.getElementById('btnMulaiFs');
  fsSedangReload=false;
  if(!sudahMulai){overlay.style.display='flex';}
  else{if(judulFs)judulFs.textContent='Lanjutkan Ujian';if(infoFs)infoFs.textContent='Klik tombol di bawah untuk kembali ke mode layar penuh.';if(btnMulai)btnMulai.innerHTML='🔒 Masuk Layar Penuh';overlay.style.display='flex';}
});
document.getElementById('btnMulaiFs').addEventListener('click',()=>{
  sessionStorage.setItem('fs_started','1');
  document.getElementById('fsStart').style.display='none';
  fsSedangReload=true;masukFullscreen();
  stopAlarm();
  stopFlash();
  setTimeout(()=>{fsSedangReload=false;},1500);
});
</script>
</body>
</html>