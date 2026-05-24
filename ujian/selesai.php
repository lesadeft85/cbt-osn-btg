<?php
// ujian/selesai.php — Halaman setelah ujian selesai + pembahasan dropdown
if (session_status() === PHP_SESSION_NONE) { session_name('TKA_PESERTA'); session_start(); }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/helper.php';
// Centralized external paths
if (file_exists(__DIR__ . '/../config/paths.php')) {
  require_once __DIR__ . '/../config/paths.php';
}

$nama    = $_SESSION['peserta_nama']    ?? 'Peserta';
$kelas   = $_SESSION['peserta_kelas']   ?? '';
$sekolah = $_SESSION['peserta_sekolah'] ?? '';
$benar   = (int)($_SESSION['hasil_benar'] ?? 0);
$nilai   = (float)($_SESSION['hasil_nilai'] ?? 0);
$total   = (int)($_SESSION['hasil_total'] ?? 0);
$detail  = $_SESSION['hasil_detail'] ?? [];
$namaAplikasi     = getSetting($conn, 'nama_aplikasi', 'TKA Kecamatan');
$tampilPembahasan = getSetting($conn, 'tampil_pembahasan', '1');

$peringkat = $totalPeserta = $mapelNama = null;
if (!empty($_SESSION['ujian_id'])) {
    $ujianId = (int)$_SESSION['ujian_id'];
    $_qU = $conn->query("SELECT jadwal_id, kategori_id FROM ujian WHERE id=$ujianId LIMIT 1");
    $ujianRow = ($_qU && $_qU->num_rows > 0) ? $_qU->fetch_assoc() : null;
    $jadwalId = $ujianRow['jadwal_id'] ?? null;
    $katId    = $ujianRow['kategori_id'] ?? null;
    if ($jadwalId) {
        $nilaiSaya = (float)($_SESSION['hasil_nilai'] ?? 0);
        $_qR = $conn->query("SELECT COUNT(*) AS c FROM hasil_ujian WHERE jadwal_id=$jadwalId AND nilai > $nilaiSaya");
        $peringkat = (($_qR && $_qR->num_rows > 0) ? (int)$_qR->fetch_assoc()['c'] : 0) + 1;
        $_rT = $conn->query("SELECT COUNT(*) AS c FROM hasil_ujian WHERE jadwal_id=$jadwalId");
        $totalPeserta = $_rT ? (int)($_rT->fetch_assoc()['c'] ?? 0) : 0;
        $_rM = $conn->query("SELECT k.nama_kategori FROM jadwal_ujian j LEFT JOIN kategori_soal k ON k.id = COALESCE($katId, j.kategori_id) WHERE j.id=$jadwalId LIMIT 1");
        $mapelNama = ($_rM && $_rM->num_rows > 0) ? ($_rM->fetch_assoc()['nama_kategori'] ?? null) : null;
    }
}

// Predikat + palet warna per grade
$grades = [
    90 => ['A','Istimewa',   '#7c3aed','#ede9fe','#ddd6fe','🏆'],
    80 => ['B','Sangat Baik','#0284c7','#e0f2fe','#bae6fd','🌟'],
    70 => ['C','Baik',       '#059669','#d1fae5','#a7f3d0','✅'],
    60 => ['D','Cukup',      '#d97706','#fef3c7','#fde68a','💪'],
     0 => ['E','Perlu Bimbingan','#dc2626','#fee2e2','#fecaca','📖'],
];
[$predikat,$keterangan,$clrMain,$clrBg,$clrAccent,$emoji] = ['E','Perlu Bimbingan','#dc2626','#fee2e2','#fecaca','📖'];
foreach ($grades as $min => $g) {
    if ($nilai >= $min) { [$predikat,$keterangan,$clrMain,$clrBg,$clrAccent,$emoji] = $g; break; }
}

$adaEssay = false;
foreach ($detail as $d) { if (($d['tipe_soal']??'') === 'essay') { $adaEssay = true; break; } }

// FIX: Ambil salah & kosong dari session (dihitung akurat di submit.php)
// Jangan hitung ulang $salah = $total - $benar karena essay dianggap "salah" padahal belum dinilai
$salah   = (int)($_SESSION['hasil_salah']  ?? ($total - $benar));
$kosong  = (int)($_SESSION['hasil_kosong'] ?? 0);
$pct     = $total > 0 ? round($benar / $total * 100) : 0;
$initials = mb_strtoupper(mb_substr($nama,0,1));

function labelJwb($soal,$kode) {
    if (!$kode) return '<span style="color:#94a3b8;font-style:italic">Tidak dijawab</span>';
    if ($soal['tipe_soal']==='bs') return $kode==='benar'?'BENAR':'SALAH';
    if ($soal['tipe_soal']==='essay') return '<span style="color:#059669">✓ Sudah dijawab</span>';
    $map=['a'=>'A','b'=>'B','c'=>'C','d'=>'D'];
    return implode(', ',array_map(fn($k)=>$map[$k]??strtoupper($k),explode(',',$kode)));
}
function cekBenar($soal,$jawaban) {
    if (!$jawaban) return false;
    if ($soal['tipe_soal']==='essay') return null;
    $a=explode(',',strtolower($jawaban)); $b=explode(',',strtolower($soal['jawaban_benar']??''));
    sort($a); sort($b); return $a===$b;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Hasil Ujian — <?= e($namaAplikasi) ?></title>
<link href="<?= defined('FONTS_PLUS_JAKARTA') ? FONTS_PLUS_JAKARTA : 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&display=swap' ?>" rel="stylesheet">
<link href="<?= defined('CDN_BOOTSTRAP_ICONS') ? CDN_BOOTSTRAP_ICONS : 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css' ?>" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --navy:#1e3a8a;--navy-h:#1e40af;--navy-d:#172e6e;--navy-m:#2348a8;
  --navy-light:#eff6ff;--navy-border:#bfdbfe;
  --g50:#f8fafc;--g100:#f1f5f9;--g200:#e2e8f0;--g300:#cbd5e1;
  --g400:#94a3b8;--g600:#475569;--g700:#334155;--g800:#1e293b;
  --grn:#16a34a;--grn-bg:#f0fdf4;--grn-br:#86efac;
  --red:#dc2626;--red-bg:#fef2f2;--red-br:#fca5a5;
  --gold:#f59e0b;--gold-bg:#fefce8;--gold-br:#fcd34d;--gold-tx:#92400e;
  --purple:#7c3aed;--purple-bg:#f5f3ff;--purple-br:#c4b5fd;
  /* Grade color — overridden by PHP inline */
  --grade-main: <?= $clrMain ?>;
  --grade-bg:   <?= $clrBg ?>;
  --grade-acc:  <?= $clrAccent ?>;
}
body{
  font-family:'Plus Jakarta Sans',sans-serif;
  background:var(--g100);
  min-height:100vh;padding-bottom:80px;color:var(--g800);
}
.page{max-width:680px;margin:0 auto;padding:20px 14px}

/* ── HERO ── */
.hero{
  background:#fff;border-radius:18px;overflow:hidden;
  box-shadow:0 4px 24px rgba(30,58,138,.13);
  margin-bottom:16px;
  animation:slideUp .45s ease both;
}
@keyframes slideUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}

.hero-top{
  background:linear-gradient(135deg,var(--navy-m) 0%,var(--navy) 55%,var(--navy-d) 100%);
  padding:28px 24px 24px;text-align:center;color:#fff;
  position:relative;overflow:hidden;
}
.hero-top::before{content:'';position:absolute;top:-40px;right:-40px;width:180px;height:180px;background:rgba(255,255,255,.06);border-radius:50%}
.hero-top::after{content:'';position:absolute;bottom:-50px;left:-30px;width:160px;height:160px;background:rgba(255,255,255,.05);border-radius:50%}

.emoji-big{font-size:48px;line-height:1;margin-bottom:10px;display:block;animation:bounce .5s ease .2s both}
@keyframes bounce{0%{transform:scale(0)}70%{transform:scale(1.15)}100%{transform:scale(1)}}

.hero-title{font-size:24px;font-weight:900;letter-spacing:-.3px;margin-bottom:5px;position:relative;z-index:1}
.hero-name{font-size:14px;font-weight:700;opacity:.9;position:relative;z-index:1}
.hero-sub{font-size:12px;opacity:.7;margin-top:3px;position:relative;z-index:1}
.chip-status{
  display:inline-flex;align-items:center;gap:6px;margin-top:12px;
  background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.3);
  border-radius:20px;padding:5px 14px;font-size:11.5px;font-weight:800;
  position:relative;z-index:1;
}

/* ── SCORE SECTION ── */
.score-section{
  padding:24px;display:grid;
  grid-template-columns:auto 1fr;gap:24px;align-items:center;
}
.score-circle{
  width:120px;height:120px;border-radius:50%;
  background:var(--navy-light);border:4px solid var(--navy-border);
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  flex-shrink:0;box-shadow:0 0 0 8px rgba(30,58,138,.07);
}
.score-num{font-size:48px;font-weight:900;line-height:1;color:var(--navy)}
.score-lbl{font-size:10px;color:var(--g400);font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-top:2px}

.predikat-badge{
  display:inline-flex;align-items:center;gap:5px;
  background:var(--navy);color:#fff;font-size:12px;font-weight:800;
  padding:5px 14px;border-radius:20px;margin-bottom:12px;
}

.stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.stat-card{border-radius:10px;padding:10px 8px;text-align:center}
.stat-card.g{background:var(--grn-bg);border:1.5px solid var(--grn-br)}
.stat-card.r{background:var(--red-bg);border:1.5px solid var(--red-br)}
.stat-card.b{background:var(--navy-light);border:1.5px solid var(--navy-border)}
.stat-card .n{font-size:28px;font-weight:900;line-height:1}
.stat-card.g .n{color:var(--grn)}
.stat-card.r .n{color:var(--red)}
.stat-card.b .n{color:var(--navy)}
.stat-card .l{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--g400);margin-top:2px}

.prog-wrap{margin-top:10px}
.prog-hdr{display:flex;justify-content:space-between;font-size:11.5px;font-weight:700;color:var(--g600);margin-bottom:5px}
.prog-track{height:9px;background:var(--g200);border-radius:10px;overflow:hidden}
.prog-fill{height:100%;width:<?= $pct ?>%;background:linear-gradient(90deg,var(--navy),var(--navy-m));border-radius:10px;transition:width 1s ease}

/* ── INFO GRID ── */
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px}
.info-card{
  background:#fff;border-radius:12px;padding:14px 16px;
  box-shadow:0 2px 10px rgba(30,58,138,.07);
  display:flex;align-items:flex-start;gap:11px;
  border:1.5px solid var(--g200);
  animation:slideUp .45s ease both;
}
.ic-icon{width:38px;height:38px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}
.ic-icon.blue{background:var(--navy-light);color:var(--navy)}
.ic-icon.amber{background:var(--gold-bg);color:var(--gold)}
.ic-icon.purple{background:var(--purple-bg);color:var(--purple)}
.ic-icon.green{background:var(--grn-bg);color:var(--grn)}
.ic-body .lbl{font-size:10.5px;font-weight:700;color:var(--g400);text-transform:uppercase;letter-spacing:.5px}
.ic-body .val{font-size:12.5px;font-weight:700;color:var(--g800);margin-top:3px;line-height:1.4}

/* ── RANKING BANNER ── */
.rank-banner{
  background:var(--gold-bg);border:2px solid var(--gold-br);
  border-radius:14px;padding:16px 20px;
  display:flex;align-items:center;gap:16px;
  margin-bottom:14px;
  box-shadow:0 4px 16px rgba(245,158,11,.12);
  animation:slideUp .45s ease .15s both;
}
.rank-medal{font-size:44px;line-height:1;flex-shrink:0}
.rank-text .lbl{font-size:10.5px;font-weight:800;color:var(--gold-tx);text-transform:uppercase;letter-spacing:.7px}
.rank-num{font-size:38px;font-weight:900;color:#b45309;line-height:1;margin:2px 0;letter-spacing:-1px}
.rank-sub{font-size:12px;color:var(--gold-tx)}
.rank-note{font-size:11px;color:#b45309;margin-top:3px;font-style:italic}

/* ── NOTICES ── */
.notice{
  background:#fff;border-radius:11px;border-left:4px solid var(--navy);
  padding:12px 15px;font-size:12.5px;color:var(--g600);
  display:flex;gap:10px;align-items:flex-start;margin-bottom:10px;
  box-shadow:0 1px 6px rgba(0,0,0,.05);
}
.notice i{color:var(--navy);font-size:16px;flex-shrink:0;margin-top:1px}
.notice.warn{border-left-color:var(--gold)}
.notice.warn i{color:var(--gold)}

/* ── TOMBOL ── */
.action-row{display:flex;gap:9px;flex-wrap:wrap;margin-bottom:20px}
.btn-a{
  display:inline-flex;align-items:center;gap:7px;
  padding:10px 20px;border-radius:10px;
  font-size:13px;font-weight:800;text-decoration:none;border:none;cursor:pointer;
  font-family:inherit;transition:background .15s,box-shadow .15s;
}
.btn-a:hover{box-shadow:0 4px 14px rgba(0,0,0,.14)}
.btn-a.dark{background:var(--g800);color:#fff}
.btn-a.dark:hover{background:var(--navy)}
.btn-a.green{background:var(--grn);color:#fff}
.btn-a.green:hover{background:#15803d}
.btn-a.outline{background:#fff;color:var(--g700);border:1.5px solid var(--g200)}

/* ── PEMBAHASAN ── */
.section-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.section-title{font-size:15px;font-weight:900;color:var(--g800);display:flex;align-items:center;gap:7px}
.btn-sm{
  font-size:11.5px;padding:5px 11px;border-radius:8px;
  border:1.5px solid var(--g200);background:#fff;color:var(--g600);
  cursor:pointer;font-family:inherit;font-weight:700;transition:background .15s;
}
.btn-sm:hover{background:var(--navy-light);border-color:var(--navy-border);color:var(--navy)}

.soal-card{
  background:#fff;border-radius:13px;margin-bottom:9px;
  box-shadow:0 1px 8px rgba(30,58,138,.07);overflow:hidden;
  border:1.5px solid transparent;transition:border-color .2s;
}
.soal-card.benar{border-color:var(--grn-br);border-left:4px solid var(--grn)}
.soal-card.salah{border-color:var(--red-br);border-left:4px solid var(--red)}
.soal-card.skip{border-color:var(--g200);border-left:4px solid var(--g300)}
.soal-card.essay{border-color:var(--purple-br);border-left:4px solid var(--purple)}

.soal-hdr{display:flex;align-items:center;gap:10px;padding:12px 15px;cursor:pointer;transition:background .15s}
.soal-hdr:hover{background:var(--g50)}

.nomor{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:900;flex-shrink:0}
.nomor.benar{background:var(--grn-bg);color:#166534}
.nomor.salah{background:var(--red-bg);color:#991b1b}
.nomor.skip{background:var(--g100);color:var(--g600)}
.nomor.essay{background:var(--purple-bg);color:var(--purple)}

.soal-preview{flex:1;font-size:13px;font-weight:600;color:var(--g800);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.chip{font-size:10.5px;font-weight:800;padding:3px 10px;border-radius:20px;flex-shrink:0}
.chip.benar{background:var(--grn-bg);color:#166534}
.chip.salah{background:var(--red-bg);color:#991b1b}
.chip.skip{background:var(--g100);color:var(--g600)}
.chip.essay{background:var(--purple-bg);color:var(--purple)}

.chevron{font-size:12px;color:var(--g400);transition:transform .22s;flex-shrink:0}
.chevron.open{transform:rotate(180deg)}

.soal-body{display:none;padding:0 15px 15px;border-top:1px dashed var(--g200)}
.soal-body.show{display:block}
.soal-teks{font-size:13.5px;line-height:1.75;color:var(--g800);margin:12px 0 10px;font-weight:600}

.bacaan-box{background:var(--purple-bg);border:1.5px solid var(--purple-br);border-radius:9px;padding:11px 14px;font-size:12.5px;color:#4c1d95;line-height:1.8;margin-bottom:12px}
.bacaan-lbl{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.8px;color:var(--purple);margin-bottom:5px;display:flex;align-items:center;gap:4px}

.pilihan-list{list-style:none;display:flex;flex-direction:column;gap:6px;margin-bottom:12px}
.pilihan-list li{display:flex;align-items:flex-start;gap:9px;padding:9px 13px;border-radius:9px;font-size:13px;border:1.5px solid var(--g200);font-weight:600;background:var(--g50)}
.pilihan-list li.kunci{background:var(--grn-bg);border-color:var(--grn-br);color:#166534}
.pilihan-list li.salah-pilih{background:var(--red-bg);border-color:var(--red-br);color:#991b1b}
.huruf{font-weight:900;flex-shrink:0;min-width:18px;color:var(--g400)}
.pilihan-list li.kunci .huruf{color:var(--grn)}
.pilihan-list li.salah-pilih .huruf{color:var(--red)}

.jwb-row{display:flex;align-items:center;gap:9px;font-size:12.5px;flex-wrap:wrap;padding-top:10px;border-top:1px dashed var(--g200);margin-bottom:9px}
.jwb-label{font-weight:800;color:var(--g600)}
.badge{font-size:11.5px;font-weight:800;padding:3px 10px;border-radius:20px;display:inline-flex;align-items:center;gap:4px}
.badge.ok{background:var(--grn-bg);color:#166534}
.badge.no{background:var(--red-bg);color:#991b1b}
.badge.skip{background:var(--g100);color:var(--g600)}

.pembahasan{background:var(--navy-light);border-left:4px solid var(--navy);border-radius:0 9px 9px 0;padding:11px 14px;font-size:13px;color:var(--g800);line-height:1.7;margin-top:9px}
.pb-lbl{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.8px;color:var(--navy);margin-bottom:5px;display:flex;align-items:center;gap:4px}

.essay-box{background:var(--purple-bg);border:1.5px solid var(--purple-br);border-radius:9px;padding:11px 14px;font-size:13px;line-height:1.7;color:var(--g700);margin-bottom:12px}
.essay-lbl{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.8px;color:var(--purple);margin-bottom:5px;display:flex;align-items:center;gap:4px}

@media(max-width:560px){
  .score-section{grid-template-columns:1fr;text-align:center}
  .score-circle{margin:0 auto}
  .info-grid{grid-template-columns:1fr}
  .rank-banner{flex-direction:column;text-align:center}
  .action-row{flex-direction:column}
  .btn-a{justify-content:center}
}
</style>
</head>
<body>
<div class="page">

<!-- HERO -->
<div class="hero">
  <div class="hero-top">
    <span class="emoji-big"><?= $adaEssay ? '📝' : $emoji ?></span>
    <div class="hero-title">Ujian Selesai!</div>
    <div class="hero-name"><?= e($nama) ?></div>
    <div class="hero-sub">
      <?= e($sekolah) ?><?= $kelas ? ' · Kelas '.e($kelas) : '' ?>
      <?= $mapelNama ? ' · '.e($mapelNama) : '' ?>
    </div>
    <?php if ($adaEssay): ?>
    <div class="chip-status"><i class="bi bi-hourglass-split"></i> Menunggu Penilaian Esai</div>
    <?php else: ?>
    <div class="chip-status"><i class="bi bi-patch-check-fill"></i> Selesai &amp; Dinilai</div>
    <?php endif; ?>
  </div>

  <div class="score-section">
    <div class="score-circle">
      <div class="score-num"><?= number_format($nilai,0) ?></div>
      <div class="score-lbl"><?= $adaEssay ? 'Sementara' : 'Nilai' ?></div>
    </div>
    <div>
      <?php if (!$adaEssay): ?>
      <div class="predikat-badge"><?= $emoji ?> Predikat <?= $predikat ?> &mdash; <?= $keterangan ?></div>
      <?php endif; ?>
      <div class="stats-grid">
        <div class="stat-card g"><div class="n"><?= $benar ?></div><div class="l">Benar</div></div>
        <div class="stat-card r"><div class="n"><?= $salah ?></div><div class="l">Salah</div></div>
        <div class="stat-card b"><div class="n"><?= $total ?></div><div class="l">Total</div></div>
      </div>
      <div class="prog-wrap">
        <div class="prog-hdr">
          <span>Tingkat Kebenaran</span>
          <span style="color:var(--navy);font-weight:900"><?= $pct ?>%</span>
        </div>
        <div class="prog-track"><div class="prog-fill"></div></div>
      </div>
    </div>
  </div>
</div>

<!-- INFO CARDS -->
<div class="info-grid">
  <div class="info-card">
    <div class="ic-icon blue"><i class="bi bi-person-circle"></i></div>
    <div class="ic-body">
      <div class="lbl">Peserta</div>
      <div class="val"><?= e($nama) ?><?= $kelas ? ', Kelas '.e($kelas) : '' ?></div>
    </div>
  </div>
  <div class="info-card" style="animation-delay:.1s">
    <div class="ic-icon purple"><i class="bi bi-building"></i></div>
    <div class="ic-body">
      <div class="lbl">Sekolah</div>
      <div class="val"><?= e($sekolah) ?: '—' ?></div>
    </div>
  </div>
  <?php if ($mapelNama): ?>
  <div class="info-card" style="animation-delay:.15s">
    <div class="ic-icon amber"><i class="bi bi-book-fill"></i></div>
    <div class="ic-body">
      <div class="lbl">Mata Pelajaran</div>
      <div class="val"><?= e($mapelNama) ?></div>
    </div>
  </div>
  <?php endif; ?>
  <div class="info-card" style="animation-delay:.2s">
    <div class="ic-icon green"><i class="bi bi-calendar-check"></i></div>
    <div class="ic-body">
      <div class="lbl">Waktu Selesai</div>
      <div class="val"><?= date('d M Y, H:i') ?> WIB</div>
    </div>
  </div>
</div>

<!-- RANKING -->
<?php if ($peringkat !== null && $totalPeserta !== null): ?>
<div class="rank-banner">
  <div class="rank-medal"><?= $peringkat===1?'🥇':($peringkat===2?'🥈':($peringkat===3?'🥉':'🏅')) ?></div>
  <div class="rank-text">
    <div class="lbl"><i class="bi bi-trophy-fill" style="color:var(--gold)"></i> Peringkat Sementara<?= $mapelNama ? ' — '.e($mapelNama) : '' ?></div>
    <div class="rank-num">#<?= $peringkat ?></div>
    <div class="rank-sub">dari <strong><?= $totalPeserta ?></strong> peserta yang sudah selesai</div>
    <?php if ($peringkat===1): ?><div class="rank-note">🎉 Nilai tertinggi saat ini!</div><?php endif; ?>
    <div class="rank-note">* Peringkat dapat berubah setelah semua peserta selesai</div>
  </div>
</div>
<?php endif; ?>

<!-- NOTICES -->
<div class="notice">
  <i class="bi bi-info-circle-fill"></i>
  <span>Hasil ujian telah tersimpan otomatis. Silakan kembalikan komputer ke posisi semula dan tunggu instruksi pengawas.</span>
</div>
<?php if ($adaEssay): ?>
<div class="notice warn">
  <i class="bi bi-pencil-square"></i>
  <span><strong>Catatan:</strong> Ujian ini memiliki soal esai yang perlu dinilai manual. Nilai akhir akan diperbarui setelah penilaian selesai.</span>
</div>
<?php endif; ?>

<!-- TOMBOL AKSI -->
<div class="action-row">
  <?php $cetakPid=(int)($_SESSION['peserta_id']??0); $cetakJid=(int)($jadwalId??0); ?>
  <a href="<?= BASE_URL ?>/ujian/cetak_hasil_peserta.php?peserta_id=<?= $cetakPid ?>&jadwal_id=<?= $cetakJid ?>"
     target="_blank" class="btn-a dark">
    <i class="bi bi-printer-fill"></i> Cetak Laporan
  </a>
  <?php
  $waEnabled = getSetting($conn,'wa_share_hasil','1');
  if ($waEnabled!=='0'):
    $waText=urlencode("🎓 *{$nama}* telah menyelesaikan ujian!\n📚 Mapel: ".($mapelNama??"Umum")."\n📊 Nilai: *".number_format($nilai,0)."* ({$emoji} Predikat {$predikat} — {$keterangan})\n✅ Benar: {$benar} dari {$total} soal\n🏫 {$sekolah} · Kelas {$kelas}");
  ?>
  <a href="https://wa.me/?text=<?= $waText ?>" target="_blank" class="btn-a green">
    <i class="bi bi-whatsapp"></i> Bagikan ke WA
  </a>
  <?php endif; ?>
  <a href="<?= BASE_URL ?>/ujian/cek_nilai.php?kode=<?= e($_SESSION['peserta_kode']??'') ?>" class="btn-a outline">
    <i class="bi bi-bar-chart-line"></i> Lihat Riwayat Nilai
  </a>
</div>

<!-- PEMBAHASAN -->
<?php if ($tampilPembahasan==='1' && !empty($detail)): ?>
<div class="section-hdr">
  <div class="section-title"><span>📋</span> Pembahasan Soal</div>
  <div style="display:flex;gap:7px">
    <button class="btn-sm" onclick="document.querySelectorAll('.soal-body').forEach(b=>b.classList.add('show'));document.querySelectorAll('.chevron').forEach(c=>c.classList.add('open'))">
      <i class="bi bi-chevron-double-down"></i> Buka
    </button>
    <button class="btn-sm" onclick="document.querySelectorAll('.soal-body').forEach(b=>b.classList.remove('show'));document.querySelectorAll('.chevron').forEach(c=>c.classList.remove('open'))">
      <i class="bi bi-chevron-double-up"></i> Tutup
    </button>
  </div>
</div>

<?php foreach ($detail as $i => $s):
  $jwbSiswa  = $s['jawaban_siswa'] ?? null;
  $benarSoal = cekBenar($s, $jwbSiswa);
  $isEssay   = $s['tipe_soal']==='essay';
  $skip      = !$jwbSiswa;
  $cls       = $isEssay?'essay':($skip?'skip':($benarSoal?'benar':'salah'));
  $labels    = ['benar'=>'✓ Benar','salah'=>'✗ Salah','skip'=>'Tidak Dijawab','essay'=>'Esai'];
  $kunciArr  = array_map('trim',explode(',',strtolower($s['jawaban_benar']??'')));
  $siswaArr  = $jwbSiswa?array_map('trim',explode(',',strtolower($jwbSiswa))):[];
  $preview   = mb_substr(strip_tags($s['pertanyaan']),0,85).(mb_strlen($s['pertanyaan'])>85?'…':'');
?>
<div class="soal-card <?= $cls ?>">
  <div class="soal-hdr" onclick="tog(<?= $i ?>)">
    <div class="nomor <?= $cls ?>"><?= $i+1 ?></div>
    <div class="soal-preview"><?= e($preview) ?></div>
    <div class="chip <?= $cls ?>"><?= $labels[$cls]??'' ?></div>
    <i class="bi bi-chevron-down chevron" id="chv-<?= $i ?>"></i>
  </div>
  <div class="soal-body" id="bod-<?= $i ?>">
    <?php if (!empty(trim($s['teks_bacaan']??''))): ?>
    <div class="bacaan-box">
      <div class="bacaan-lbl"><i class="bi bi-book-half"></i> Teks Bacaan</div>
      <?= nl2br(e($s['teks_bacaan'])) ?>
    </div>
    <?php endif; ?>
    <p class="soal-teks"><?= nl2br(e($s['pertanyaan'])) ?></p>

    <?php if ($isEssay): ?>
    <div class="essay-box">
      <div class="essay-lbl"><i class="bi bi-pencil-square"></i> Jawaban Esai Anda</div>
      <?php $teksEssay=trim($s['teks_jawaban']??''); ?>
      <?= $teksEssay ? nl2br(e($teksEssay)) : '<span style="color:var(--g400);font-style:italic">Tidak dijawab</span>' ?>
    </div>
    <?php elseif ($s['tipe_soal']==='bs'): ?>
    <ul class="pilihan-list">
      <?php foreach(['benar'=>'BENAR','salah'=>'SALAH'] as $v=>$lbl):
        $ik=in_array($v,$kunciArr); $dp=in_array($v,$siswaArr);
        $pc=$ik?'kunci':($dp?'salah-pilih':'');
      ?>
      <li class="<?= $pc ?>">
        <span class="huruf"><i class="bi bi-<?= $ik?'check-circle-fill':($dp?'x-circle-fill':'circle') ?>"></i></span>
        <span style="flex:1"><?= $lbl ?></span>
        <?php if($ik):?><span style="font-size:10.5px;font-weight:800;color:var(--grn)">KUNCI</span><?php endif;?>
        <?php if($dp&&!$ik):?><span style="font-size:10.5px;font-weight:800;color:var(--red)">ANDA</span><?php endif;?>
      </li>
      <?php endforeach;?>
    </ul>
    <?php else: ?>
    <ul class="pilihan-list">
      <?php foreach(['a'=>$s['pilihan_a'],'b'=>$s['pilihan_b'],'c'=>$s['pilihan_c'],'d'=>$s['pilihan_d']] as $h=>$isi):
        if(!$isi) continue;
        $ik=in_array($h,$kunciArr); $dp=in_array($h,$siswaArr);
        $pc=$ik?'kunci':($dp?'salah-pilih':'');
      ?>
      <li class="<?= $pc ?>">
        <span class="huruf"><?= strtoupper($h) ?>.</span>
        <span style="flex:1"><?= e($isi) ?></span>
        <?php if($ik):?><i class="bi bi-check-circle-fill" style="color:var(--grn);margin-left:auto;flex-shrink:0"></i><?php endif;?>
        <?php if($dp&&!$ik):?><i class="bi bi-x-circle-fill" style="color:var(--red);margin-left:auto;flex-shrink:0"></i><?php endif;?>
      </li>
      <?php endforeach;?>
    </ul>
    <?php endif;?>

    <?php if(!$isEssay):?>
    <div class="jwb-row">
      <span class="jwb-label">Jawaban Anda:</span>
      <?php if($skip):?>
        <span class="badge skip">Tidak Dijawab</span>
      <?php elseif($benarSoal===true):?>
        <span class="badge ok"><i class="bi bi-check-lg"></i><?= labelJwb($s,$jwbSiswa) ?> — Benar</span>
      <?php elseif($benarSoal===false):?>
        <span class="badge no"><i class="bi bi-x-lg"></i><?= labelJwb($s,$jwbSiswa) ?> — Salah</span>
        <span style="font-size:11.5px;color:var(--g400)">Kunci: <strong style="color:var(--grn)"><?= labelJwb($s,$s['jawaban_benar']) ?></strong></span>
      <?php endif;?>
    </div>
    <?php endif;?>

    <?php if(!empty(trim($s['pembahasan']??''))):?>
    <div class="pembahasan">
      <div class="pb-lbl"><i class="bi bi-lightbulb-fill"></i> Pembahasan</div>
      <?= nl2br(e($s['pembahasan'])) ?>
    </div>
    <?php endif;?>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

</div>
<script>
function tog(i){
  const b=document.getElementById('bod-'+i);
  const c=document.getElementById('chv-'+i);
  const o=b.classList.toggle('show');
  c.classList.toggle('open',o);
}
</script>
</body></html>
<?php session_unset(); session_destroy(); ?>
