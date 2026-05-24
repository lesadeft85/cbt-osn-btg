<?php
// ============================================================
// ujian/cek_nilai.php — Dashboard Nilai Peserta (upgrade)
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

$namaAplikasi      = getSetting($conn, 'nama_aplikasi', 'TKA Kecamatan');
$namaPenyelenggara = getSetting($conn, 'nama_penyelenggara', '');
$kkm               = (int)getSetting($conn, 'kkm', '60');

$kode    = '';
$peserta = null;
$riwayat = [];
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $kode = strtoupper(trim($_POST['kode_peserta'] ?? ''));
    if (!$kode) {
        $error = 'Kode peserta wajib diisi.';
    } else {
        $kd   = $conn->real_escape_string($kode);
        $pRow = $conn->query(
            "SELECT p.*, s.nama_sekolah FROM peserta p
             LEFT JOIN sekolah s ON s.id = p.sekolah_id
             WHERE p.kode_peserta = '$kd' LIMIT 1"
        );
        if (!$pRow || $pRow->num_rows === 0) {
            $error = 'Kode peserta tidak ditemukan. Pastikan kode sesuai kartu ujian.';
        } else {
            $peserta = $pRow->fetch_assoc();
            $pid     = (int)$peserta['id'];

            // Riwayat nilai lengkap
            $riwayatRes = $conn->query("
                SELECT h.nilai, h.jml_benar, h.jml_salah, h.jml_kosong,
                       h.total_soal, h.waktu_mulai, h.waktu_selesai,
                       FLOOR(h.durasi_detik / 60) AS durasi,
                       h.jadwal_id,
                       COALESCE(k.nama_kategori, 'Umum') AS nama_kategori,
                       COALESCE(k.id, 0) AS kategori_id,
                       jd.tanggal AS jadwal_tanggal, jd.keterangan AS jadwal_ket
                FROM hasil_ujian h
                LEFT JOIN jadwal_ujian jd ON jd.id = h.jadwal_id
                LEFT JOIN kategori_soal k ON k.id = COALESCE(h.kategori_id, jd.kategori_id)
                WHERE h.peserta_id = $pid
                ORDER BY h.waktu_selesai DESC
            ");
            if ($riwayatRes) while ($r = $riwayatRes->fetch_assoc()) $riwayat[] = $r;
        }
    }
}

// FIX #3: Hitung ranking SEMUA jadwal sekaligus dalam 1 query
// (sebelumnya N×2 query per riwayat — sekarang 2 query flat)
$rankingData = [];
if (!empty($riwayat) && $peserta) {
    // Kumpulkan jadwal_id unik yang dimiliki peserta ini
    $jadwalIds = array_unique(array_filter(array_column($riwayat, 'jadwal_id')));

    if (!empty($jadwalIds)) {
        $pid = (int)$peserta['id'];
        $inJadwal = implode(',', array_map('intval', $jadwalIds));

        // Total peserta per jadwal — 1 query
        $qTotal = $conn->query(
            "SELECT jadwal_id, COUNT(*) AS total
             FROM hasil_ujian
             WHERE jadwal_id IN ($inJadwal)
             GROUP BY jadwal_id"
        );
        $totalPerJadwal = [];
        if ($qTotal) while ($row = $qTotal->fetch_assoc()) {
            $totalPerJadwal[(int)$row['jadwal_id']] = (int)$row['total'];
        }

        // Peserta yang nilainya lebih tinggi dari PESERTA INI per jadwal — 1 query
        // Gunakan subquery untuk mendapatkan nilai peserta di masing-masing jadwal
        $qRank = $conn->query(
            "SELECT h.jadwal_id,
                    COUNT(lain.id) + 1 AS peringkat
             FROM hasil_ujian h
             LEFT JOIN hasil_ujian lain
               ON lain.jadwal_id = h.jadwal_id
              AND lain.nilai > h.nilai
             WHERE h.peserta_id = $pid
               AND h.jadwal_id IN ($inJadwal)
             GROUP BY h.jadwal_id, h.nilai"
        );
        $rankPerJadwal = [];
        if ($qRank) while ($row = $qRank->fetch_assoc()) {
            $rankPerJadwal[(int)$row['jadwal_id']] = (int)$row['peringkat'];
        }

        foreach ($jadwalIds as $jid) {
            $jid = (int)$jid;
            $rankingData[$jid] = [
                'rank'  => $rankPerJadwal[$jid] ?? null,
                'total' => $totalPerJadwal[$jid] ?? null,
            ];
        }
    }
}
// ── Setting tampil pembahasan ─────────────────────────────────
$tampilPembahasan = getSetting($conn, 'tampil_pembahasan', '1');

// ── Helper functions (sama seperti selesai.php) ───────────────
function labelJwb2($soal, $kode) {
    if (!$kode) return '<span class="text-muted fst-italic">Tidak dijawab</span>';
    if ($soal['tipe_soal'] === 'bs') return $kode === 'benar' ? 'BENAR' : 'SALAH';
    if ($soal['tipe_soal'] === 'essay') return '<span class="text-success">&#10003; Sudah dijawab</span>';
    $map = ['a'=>'A','b'=>'B','c'=>'C','d'=>'D'];
    return implode(', ', array_map(fn($k)=>$map[$k]??strtoupper($k), explode(',', $kode)));
}
function cekBenar2($soal, $jawaban) {
    if (!$jawaban) return false;
    if ($soal['tipe_soal'] === 'essay') return null;
    $a = explode(',', strtolower($jawaban)); $b = explode(',', strtolower($soal['jawaban_benar']));
    sort($a); sort($b);
    return $a === $b;
}

// ── Ambil detail soal+jawaban per ujian (untuk pembahasan) ────
// Key: jadwal_id => [ detail soal+jawaban ]
$pembahasanData = [];
if ($tampilPembahasan === '1' && $peserta) {
    $pid = (int)$peserta['id'];
    // Ambil semua ujian peserta yang sudah selesai
    $qUjian = $conn->query(
        "SELECT u.id AS ujian_id, u.jadwal_id
         FROM ujian u
         WHERE u.peserta_id = $pid AND u.waktu_selesai IS NOT NULL
         ORDER BY u.waktu_selesai DESC"
    );
    if ($qUjian) while ($uRow = $qUjian->fetch_assoc()) {
        $ujianId  = (int)$uRow['ujian_id'];
        $jadwalId = (int)$uRow['jadwal_id'];

        // Ambil soal + jawaban siswa untuk ujian ini
        $qDetail = $conn->query("
            SELECT s.id AS soal_id, s.pertanyaan, s.tipe_soal,
                   s.pilihan_a, s.pilihan_b, s.pilihan_c, s.pilihan_d,
                   s.jawaban_benar, s.pembahasan,
                   j.jawaban AS jawaban_siswa,
                   j.teks_jawaban
            FROM jawaban j
            JOIN soal s ON s.id = j.soal_id
            WHERE j.ujian_id = $ujianId
            ORDER BY j.id ASC
        ");
        $detail = [];
        if ($qDetail) while ($d = $qDetail->fetch_assoc()) $detail[] = $d;
        if (!empty($detail)) {
            $pembahasanData[$ujianId] = $detail;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cek Nilai — <?= e($namaAplikasi) ?></title>
<link href="<?= defined('CDN_BOOTSTRAP_ICONS') ? CDN_BOOTSTRAP_ICONS : 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css' ?>" rel="stylesheet">
<link href="<?= defined('FONTS_PLUS_JAKARTA') ? FONTS_PLUS_JAKARTA : 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&display=swap' ?>" rel="stylesheet">
<script src="<?= defined('CDN_CHART_JS') ? CDN_CHART_JS : 'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js' ?>"></script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --navy:#1e3a8a;--navy-h:#1e40af;--navy-d:#172e6e;--navy-m:#2348a8;
  --navy-light:#eff6ff;--navy-border:#bfdbfe;
  --g50:#f8fafc;--g100:#f1f5f9;--g200:#e2e8f0;--g300:#cbd5e1;
  --g400:#94a3b8;--g600:#475569;--g700:#334155;--g800:#1e293b;
  --grn:#16a34a;--grn-bg:#f0fdf4;--grn-br:#bbf7d0;
  --red:#dc2626;--red-bg:#fef2f2;--red-br:#fca5a5;
  --gold:#f59e0b;--gold-bg:#fefce8;--gold-br:#fde68a;--gold-tx:#92400e;
}
body{
  font-family:'Plus Jakarta Sans',sans-serif;
  background:var(--g100);min-height:100vh;
  padding:24px 16px 60px;color:var(--g800);
}
.wrap{max-width:660px;margin:0 auto}

/* ── Panel atas (seragam login) ── */
.top-panel{
  display:flex;border-radius:16px;overflow:hidden;
  box-shadow:0 4px 24px rgba(30,58,138,.14);
  margin-bottom:20px;min-height:160px;
}
.tp-left{
  width:45%;
  background:linear-gradient(155deg,var(--navy-m) 0%,var(--navy) 55%,var(--navy-d) 100%);
  padding:24px 20px;display:flex;flex-direction:column;
  align-items:center;justify-content:center;
  position:relative;overflow:hidden;
}
.tp-left::before{content:'';position:absolute;width:180px;height:180px;border-radius:50%;border:1px solid rgba(255,255,255,.08);top:-50px;left:-50px}
.tp-left::after{content:'';position:absolute;width:120px;height:120px;border-radius:50%;border:1px solid rgba(255,255,255,.06);bottom:-35px;right:-35px}
.tp-icon{width:52px;height:52px;border-radius:50%;background:rgba(255,255,255,.15);border:1.5px solid rgba(255,255,255,.3);display:flex;align-items:center;justify-content:center;margin-bottom:10px;position:relative;z-index:1;font-size:22px;color:#fff}
.tp-title{font-size:12.5px;font-weight:900;color:#fff;text-align:center;text-transform:uppercase;letter-spacing:.4px;line-height:1.3;position:relative;z-index:1;margin-bottom:3px}
.tp-sub{font-size:9.5px;color:rgba(255,255,255,.65);text-align:center;position:relative;z-index:1}

.tp-right{
  flex:1;background:#fff;padding:22px 24px;
  display:flex;flex-direction:column;justify-content:center;
}
.form-title{font-size:18px;font-weight:900;color:var(--g800);margin-bottom:3px;letter-spacing:-.2px}
.form-sub{font-size:12px;color:var(--g400);margin-bottom:14px}

/* Alert */
.alert-box{display:flex;align-items:flex-start;gap:8px;border-radius:8px;padding:9px 12px;font-size:12.5px;margin-bottom:12px;line-height:1.5}
.alert-box.error{background:var(--red-bg);border:1px solid var(--red-br);color:var(--red)}

/* Field */
.field-lbl{font-size:10.5px;font-weight:800;color:var(--g600);text-transform:uppercase;letter-spacing:.7px;margin-bottom:5px;display:block}
.field-wrap{position:relative;margin-bottom:12px}
.field-input{width:100%;background:var(--g50);border:1.5px solid var(--g200);border-radius:8px;padding:10px 12px;font-size:15px;font-weight:700;font-family:'Courier New',monospace;letter-spacing:2px;text-transform:uppercase;text-align:center;color:var(--g800);outline:none;transition:border .15s}
.field-input:focus{border-color:var(--navy);background:var(--navy-light);box-shadow:0 0 0 3px rgba(30,58,138,.09)}
.field-hint{font-size:11px;color:var(--g400);text-align:center;margin-top:4px}
.btn-cek{display:flex;align-items:center;justify-content:center;gap:7px;width:100%;background:var(--navy);border:none;border-radius:8px;padding:11px;font-size:13.5px;font-weight:800;font-family:inherit;color:#fff;cursor:pointer;transition:background .15s;box-shadow:0 2px 8px rgba(30,58,138,.25)}
.btn-cek:hover{background:var(--navy-h)}

/* ── Peserta card ── */
.peserta-card{
  background:#fff;border-radius:12px;border:1.5px solid var(--navy-border);
  padding:14px 16px;margin-bottom:16px;
  display:flex;align-items:center;gap:13px;
  box-shadow:0 2px 10px rgba(30,58,138,.08);
}
.avatar{
  width:48px;height:48px;border-radius:50%;
  background:linear-gradient(135deg,var(--navy-m),var(--navy));
  color:#fff;display:flex;align-items:center;justify-content:center;
  font-size:18px;font-weight:900;flex-shrink:0;
}
.peserta-nama{font-size:15px;font-weight:900;color:var(--g800);margin-bottom:2px;text-transform:uppercase}
.peserta-info{font-size:11.5px;color:var(--g600);display:flex;align-items:center;flex-wrap:wrap;gap:4px}
.peserta-kode{font-family:'Courier New',monospace;background:var(--navy-light);border:1px solid var(--navy-border);padding:1px 7px;border-radius:5px;font-size:11px;font-weight:700;color:var(--navy)}

/* ── Stat ringkasan ── */
.stat-row{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px}
.stat-box{background:#fff;border:1.5px solid var(--g200);border-radius:10px;padding:13px 10px;text-align:center;box-shadow:0 1px 4px rgba(0,0,0,.04)}
.stat-val{font-size:26px;font-weight:900;line-height:1;color:var(--navy)}
.stat-lbl{font-size:10px;color:var(--g400);margin-top:3px;font-weight:700;text-transform:uppercase;letter-spacing:.4px}

/* ── Chart ── */
.chart-wrap{background:#fff;border-radius:12px;border:1.5px solid var(--g200);padding:14px 16px;margin-bottom:16px;box-shadow:0 1px 4px rgba(0,0,0,.04)}
.section-ttl{font-size:12px;font-weight:800;color:var(--g600);margin-bottom:10px;display:flex;align-items:center;gap:6px;text-transform:uppercase;letter-spacing:.4px}

/* ── Nilai card ── */
.nilai-card{
  background:#fff;border:1.5px solid var(--g200);border-radius:12px;
  padding:16px;margin-bottom:12px;
  box-shadow:0 1px 6px rgba(0,0,0,.05);
  transition:box-shadow .15s;
}
.nilai-card:hover{box-shadow:0 4px 14px rgba(30,58,138,.10)}
.nilai-card.lulus{border-left:4px solid var(--grn)}
.nilai-card.tidak-lulus{border-left:4px solid var(--red)}
.mapel-badge{display:inline-flex;align-items:center;background:var(--navy-light);color:var(--navy);font-size:11px;font-weight:800;padding:3px 10px;border-radius:20px;border:1px solid var(--navy-border);margin-bottom:8px}
.ranking-badge{background:var(--gold-bg);color:var(--gold-tx);border:1px solid var(--gold-br);border-radius:8px;padding:3px 10px;font-size:11px;font-weight:700;display:inline-flex;align-items:center;gap:4px}
.nilai-besar{font-size:48px;font-weight:900;line-height:1;letter-spacing:-2px}
.nilai-besar.lulus{color:var(--grn)}
.nilai-besar.tidak-lulus{color:var(--red)}
.nilai-badge{display:inline-flex;align-items:center;gap:4px;border-radius:6px;padding:3px 9px;font-size:11px;font-weight:800;margin-top:5px}
.nilai-badge.lulus{background:var(--grn-bg);color:var(--grn);border:1px solid var(--grn-br)}
.nilai-badge.tidak-lulus{background:var(--red-bg);color:var(--red);border:1px solid var(--red-br)}
.stat-mini{display:flex;gap:12px;margin-top:2px;flex-wrap:wrap}
.stat-mini-item{text-align:center;min-width:38px}
.stat-mini-num{font-size:17px;font-weight:900;line-height:1}
.stat-mini-lbl{font-size:9.5px;color:var(--g400);margin-top:1px;font-weight:700}
.tanggal-info{font-size:11px;color:var(--g400);margin-top:10px;padding-top:8px;border-top:1px dashed var(--g200);display:flex;align-items:center;flex-wrap:wrap;gap:6px}

/* ── Pembahasan ── */
.section-pembahasan{background:#fff;border-radius:12px;border:1.5px solid var(--g200);padding:16px;box-shadow:0 1px 6px rgba(0,0,0,.05);margin-bottom:12px}
.pb-section-head{font-size:12px;font-weight:800;color:var(--g600);margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;text-transform:uppercase;letter-spacing:.3px}
.acc-item{background:var(--g50);border-radius:10px;border:1px solid var(--g200);margin-bottom:7px;overflow:hidden}
.acc-btn{width:100%;display:flex;align-items:center;gap:9px;padding:10px 13px;background:none;border:none;cursor:pointer;text-align:left;transition:background .15s;font-family:inherit}
.acc-btn:hover{background:var(--g100)}
.soal-no{display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:50%;font-weight:900;font-size:11px;flex-shrink:0}
.soal-no.benar{background:var(--grn-bg);color:#166534}
.soal-no.salah{background:var(--red-bg);color:#991b1b}
.soal-no.skip{background:var(--g100);color:var(--g600)}
.soal-no.essay{background:#ede9fe;color:#5b21b6}
.acc-soal-text{flex:1;font-size:12.5px;color:var(--g800);font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.acc-badge{flex-shrink:0;font-size:10.5px;padding:2px 8px;border-radius:12px;font-weight:800}
.acc-badge.benar{background:var(--grn-bg);color:#166534}
.acc-badge.salah{background:var(--red-bg);color:#991b1b}
.acc-badge.skip{background:var(--g100);color:var(--g600)}
.acc-badge.essay{background:#ede9fe;color:#5b21b6}
.acc-arrow{flex-shrink:0;font-size:12px;color:var(--g400);transition:transform .25s}
.acc-arrow.open{transform:rotate(180deg)}
.acc-body{display:none;padding:0 13px 14px;border-top:1px solid var(--g200)}
.acc-body.show{display:block}
.pertanyaan{font-size:13.5px;color:var(--g800);line-height:1.75;margin:12px 0 10px}
.pilihan-list{list-style:none;padding:0;margin:0 0 10px;display:flex;flex-direction:column;gap:5px}
.pilihan-list li{display:flex;align-items:flex-start;gap:8px;padding:7px 10px;border-radius:8px;font-size:12.5px;border:1px solid var(--g200)}
.pilihan-list li.kunci{background:var(--grn-bg);border-color:#86efac;font-weight:700;color:#166534}
.pilihan-list li.salah-pilih{background:var(--red-bg);border-color:var(--red-br);color:#991b1b}
.pilihan-huruf{font-weight:800;flex-shrink:0;min-width:18px}
.jwb-row{display:flex;align-items:center;gap:8px;font-size:12px;flex-wrap:wrap;margin-bottom:8px}
.pembahasan-box{background:var(--navy-light);border-left:3px solid var(--navy);border-radius:0 8px 8px 0;padding:10px 14px;font-size:12.5px;color:var(--g800);line-height:1.7;margin-top:8px}
.pb-label{font-weight:800;color:var(--navy);margin-bottom:4px;font-size:10.5px;text-transform:uppercase;letter-spacing:.6px}

.btn-sm-outline{padding:4px 11px;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;font-family:inherit;border:1.5px solid;transition:all .15s}
.btn-sm-outline.green{border-color:var(--grn-br);color:var(--grn);background:var(--grn-bg)}
.btn-sm-outline.green:hover{background:var(--grn);color:#fff}
.btn-sm-outline.gray{border-color:var(--g200);color:var(--g600);background:var(--g50)}
.btn-sm-outline.gray:hover{background:var(--g200)}

/* ── Footer ── */
.footer-area{text-align:center;margin-top:20px}
.footer-area a{color:var(--navy);font-size:12px;font-weight:700;text-decoration:none}
.footer-area a:hover{text-decoration:underline}
.footer-area span{color:var(--g400);font-size:12px;margin:0 6px}

/* ── Empty ── */
.empty-box{text-align:center;padding:36px 20px;color:var(--g400)}
.empty-box i{font-size:36px;display:block;margin-bottom:10px}

/* ── Mobile ── */
@media(max-width:540px){
  .top-panel{flex-direction:column;min-height:unset}
  .tp-left{width:100%;padding:20px 16px;flex-direction:row;gap:12px;justify-content:flex-start}
  .tp-icon{margin-bottom:0;flex-shrink:0}
  .tp-title,.tp-sub{text-align:left}
  .tp-right{padding:18px 16px}
  .stat-row{grid-template-columns:repeat(2,1fr)}
  .stat-val{font-size:22px}
}
</style>
</head>
<body>
<div class="wrap">

  <!-- Panel Atas -->
  <div class="top-panel">
    <div class="tp-left">
      <div class="tp-icon">🔍</div>
      <div>
        <div class="tp-title"><?= e($namaAplikasi) ?></div>
        <?php if ($namaPenyelenggara): ?>
        <div class="tp-sub"><?= e($namaPenyelenggara) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <div class="tp-right">
      <div class="form-title">Cek Nilai Ujian</div>
      <div class="form-sub">Masukkan kode peserta dari kartu ujian Anda.</div>

      <?php if ($error): ?>
      <div class="alert-box error"><span>⚠️</span><span><?= $error ?></span></div>
      <?php endif; ?>

      <form method="POST" autocomplete="off" novalidate>
        <?= csrfField() ?>
        <label class="field-lbl">Kode Peserta</label>
        <div class="field-wrap">
          <input type="text" name="kode_peserta" class="field-input"
                 placeholder="• • • • • • • •"
                 value="<?= e($kode) ?>" maxlength="20" required autofocus>
          <div class="field-hint">Kode ada di kartu ujian Anda</div>
        </div>
        <button type="submit" class="btn-cek">
          <i class="bi bi-search"></i> Cek Nilai Saya
        </button>
      </form>
    </div>
  </div>

  <?php if ($peserta): ?>

  <!-- Info Peserta -->
  <div class="peserta-card">
    <div class="avatar"><?= mb_strtoupper(mb_substr($peserta['nama'],0,2)) ?></div>
    <div style="flex:1;min-width:0">
      <div class="peserta-nama"><?= e($peserta['nama']) ?></div>
      <div class="peserta-info">
        <i class="bi bi-building" style="font-size:11px"></i>
        <?= e($peserta['nama_sekolah'] ?? '-') ?>
        <span style="color:var(--g300)">·</span>
        Kelas <?= e($peserta['kelas'] ?? '-') ?>
        <span class="peserta-kode"><?= e($peserta['kode_peserta']) ?></span>
      </div>
    </div>
  </div>

  <?php if (empty($riwayat)): ?>
  <div class="empty-box">
    <i class="bi bi-inbox"></i>
    Belum ada riwayat ujian.
  </div>

  <?php else:
    $nilaiArr = array_column($riwayat, 'nilai');
    $rataRata = round(array_sum($nilaiArr) / count($nilaiArr), 1);
    $nilaiMax = max($nilaiArr);
    $jmlLulus = count(array_filter($nilaiArr, fn($n) => $n >= $kkm));
    $jmlUjian = count($riwayat);
  ?>

  <!-- Stat Ringkasan -->
  <div class="stat-row">
    <div class="stat-box">
      <div class="stat-val"><?= $jmlUjian ?></div>
      <div class="stat-lbl">Sesi Ujian</div>
    </div>
    <div class="stat-box">
      <div class="stat-val" style="color:var(--grn)"><?= $nilaiMax ?></div>
      <div class="stat-lbl">Nilai Terbaik</div>
    </div>
    <div class="stat-box">
      <div class="stat-val"><?= $rataRata ?></div>
      <div class="stat-lbl">Rata-rata</div>
    </div>
  </div>

  <?php if ($jmlUjian > 1): ?>
  <!-- Grafik Tren -->
  <div class="chart-wrap">
    <div class="section-ttl">📈 Tren Nilai</div>
    <canvas id="chartTren" height="75"></canvas>
  </div>
  <?php endif; ?>

  <!-- Judul Riwayat -->
  <div class="section-ttl" style="margin-bottom:12px">📋 Riwayat Ujian <span style="font-weight:600;color:var(--g400)">(<?= $jmlUjian ?> sesi)</span></div>

  <?php foreach ($riwayat as $r):
    $lulus  = $r['nilai'] >= $kkm;
    [$pred, $ket, $badge] = getNilaiPredikat((float)$r['nilai'], (float)$kkm);
    $jid   = (int)($r['jadwal_id'] ?? 0);
    $rank  = $rankingData[$jid]['rank']  ?? null;
    $total = $rankingData[$jid]['total'] ?? null;
  ?>
  <div class="nilai-card <?= $lulus ? 'lulus' : 'tidak-lulus' ?>">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:6px;margin-bottom:10px">
      <span class="mapel-badge"><?= e($r['nama_kategori']) ?></span>
      <?php if ($rank && $total): ?>
      <span class="ranking-badge">🏅 Ranking <?= $rank ?> / <?= $total ?></span>
      <?php endif; ?>
    </div>
    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
      <div>
        <div class="nilai-besar <?= $lulus ? 'lulus' : 'tidak-lulus' ?>"><?= number_format($r['nilai'],0) ?></div>
        <div class="nilai-badge <?= $lulus ? 'lulus' : 'tidak-lulus' ?>">
          <i class="bi bi-<?= $lulus ? 'check-circle-fill' : 'x-circle-fill' ?>"></i>
          <?= $pred ?> — <?= $ket ?>
        </div>
      </div>
      <div class="stat-mini" style="margin-left:auto">
        <div class="stat-mini-item">
          <div class="stat-mini-num" style="color:var(--grn)"><?= $r['jml_benar'] ?></div>
          <div class="stat-mini-lbl">Benar</div>
        </div>
        <div class="stat-mini-item">
          <div class="stat-mini-num" style="color:var(--red)"><?= $r['jml_salah'] ?></div>
          <div class="stat-mini-lbl">Salah</div>
        </div>
        <div class="stat-mini-item">
          <div class="stat-mini-num" style="color:var(--g400)"><?= $r['jml_kosong'] ?></div>
          <div class="stat-mini-lbl">Kosong</div>
        </div>
        <div class="stat-mini-item">
          <div class="stat-mini-num" style="color:var(--navy)"><?= $r['total_soal'] ?></div>
          <div class="stat-mini-lbl">Total</div>
        </div>
      </div>
    </div>
    <div class="tanggal-info">
      <i class="bi bi-calendar2"></i>
      <?= $r['jadwal_tanggal'] ? date('d F Y', strtotime($r['jadwal_tanggal'])) : '-' ?>
      <?php if ($r['waktu_selesai']): ?>
      <span style="color:var(--g200)">·</span> Selesai <?= date('H:i', strtotime($r['waktu_selesai'])) ?> WIB
      <?php endif; ?>
      <?php if ($r['durasi']): ?>
      <span style="color:var(--g200)">·</span> <?= $r['durasi'] ?> menit
      <?php endif; ?>
    </div>
  </div>

    <?php
    $ujianIdPb  = null;
    $detailPb   = null;
    $jadwalIdR  = (int)($r['jadwal_id'] ?? 0);
    if ($jadwalIdR && $tampilPembahasan === '1') {
        $qMatchUjian = $conn->query(
            "SELECT id FROM ujian WHERE peserta_id={$peserta['id']} AND jadwal_id=$jadwalIdR AND waktu_selesai IS NOT NULL ORDER BY id DESC LIMIT 1"
        );
        if ($qMatchUjian && $qMatchUjian->num_rows > 0) {
            $matchId  = (int)$qMatchUjian->fetch_assoc()['id'];
            $detailPb = $pembahasanData[$matchId] ?? null;
        }
    }
    if ($tampilPembahasan === '1' && !empty($detailPb)):
    ?>
    <div class="section-pembahasan">
      <div class="pb-section-head">
        <span><i class="bi bi-journal-text" style="color:var(--navy)"></i> Pembahasan — <?= e($r['nama_kategori']) ?></span>
        <div style="display:flex;gap:6px">
          <button class="btn-sm-outline green" onclick="bukaGrup(<?= $jid ?>)">
            <i class="bi bi-chevron-double-down"></i> Buka Semua
          </button>
          <button class="btn-sm-outline gray" onclick="tutupGrup(<?= $jid ?>)">
            <i class="bi bi-chevron-double-up"></i> Tutup Semua
          </button>
        </div>
      </div>

      <?php foreach ($detailPb as $i => $s):
        $jwbSiswa  = $s['jawaban_siswa'] ?? null;
        $benarSoal = cekBenar2($s, $jwbSiswa);
        $isEssay   = $s['tipe_soal'] === 'essay';
        $skip      = !$jwbSiswa;
        $noKls     = $isEssay ? 'essay' : ($skip ? 'skip' : ($benarSoal === true ? 'benar' : 'salah'));
        $statusLabel = $isEssay ? 'Esai' : ($skip ? 'Tidak Dijawab' : ($benarSoal === true ? 'Benar' : 'Salah'));
        $kunciArr  = array_map('trim', explode(',', strtolower($s['jawaban_benar'] ?? '')));
        $siswaArr  = $jwbSiswa ? array_map('trim', explode(',', strtolower($jwbSiswa))) : [];
        $singkat   = mb_substr(strip_tags($s['pertanyaan']), 0, 70) . (mb_strlen($s['pertanyaan']) > 70 ? '...' : '');
        $accId     = "pb_{$jid}_{$i}";
      ?>
      <div class="acc-item" id="acc-<?= $accId ?>">
        <button class="acc-btn" onclick="toggleAcc('<?= $accId ?>')">
          <span class="soal-no <?= $noKls ?>"><?= $i+1 ?></span>
          <span class="acc-soal-text"><?= e($singkat) ?></span>
          <span class="acc-badge <?= $noKls ?>"><?= $statusLabel ?></span>
          <i class="bi bi-chevron-down acc-arrow" id="arrow-<?= $accId ?>"></i>
        </button>
        <div class="acc-body" id="body-<?= $accId ?>">
          <p class="pertanyaan"><?= nl2br(e($s['pertanyaan'])) ?></p>

          <?php if ($isEssay): ?>
          <?php elseif ($s['tipe_soal'] === 'bs'): ?>
          <ul class="pilihan-list">
            <?php foreach (['benar'=>'BENAR','salah'=>'SALAH'] as $val=>$lbl):
              $isKunci = in_array($val,$kunciArr); $dipilih = in_array($val,$siswaArr);
              $cls = $isKunci ? 'kunci' : ($dipilih ? 'salah-pilih' : '');
            ?>
            <li class="<?= $cls ?>">
              <?php if ($isKunci): ?><i class="bi bi-check-circle-fill" style="color:var(--grn)"></i>
              <?php elseif ($dipilih): ?><i class="bi bi-x-circle-fill" style="color:var(--red)"></i>
              <?php else: ?><i class="bi bi-circle" style="color:var(--g400)"></i><?php endif; ?>
              <?= $lbl ?>
              <?php if ($isKunci): ?><small style="color:var(--grn);margin-left:4px">(Kunci)</small><?php endif; ?>
              <?php if ($dipilih && !$isKunci): ?><small style="color:var(--red);margin-left:4px">(Jawaban Anda)</small><?php endif; ?>
            </li>
            <?php endforeach; ?>
          </ul>
          <?php else: ?>
          <ul class="pilihan-list">
            <?php foreach (['a'=>$s['pilihan_a'],'b'=>$s['pilihan_b'],'c'=>$s['pilihan_c'],'d'=>$s['pilihan_d']] as $huruf=>$isi):
              if (!$isi) continue;
              $isKunci = in_array($huruf,$kunciArr); $dipilih = in_array($huruf,$siswaArr);
              $cls = $isKunci ? 'kunci' : ($dipilih ? 'salah-pilih' : '');
            ?>
            <li class="<?= $cls ?>">
              <span class="pilihan-huruf"><?= strtoupper($huruf) ?>.</span>
              <span style="flex:1"><?= e($isi) ?></span>
              <?php if ($isKunci): ?><i class="bi bi-check-circle-fill" style="color:var(--grn)"></i><?php endif; ?>
              <?php if ($dipilih && !$isKunci): ?><i class="bi bi-x-circle-fill" style="color:var(--red)"></i><?php endif; ?>
            </li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>

          <div class="jwb-row">
            <span style="font-weight:700;font-size:12px">Jawaban Anda:</span>
            <?php if ($isEssay):
              $teksEssay = trim($s['teks_jawaban'] ?? '');
            ?>
              <span style="background:#ede9fe;color:#5b21b6;font-size:11px;font-weight:700;padding:2px 8px;border-radius:6px"><i class="bi bi-pencil"></i> Esai — Dinilai Manual</span>
              <?php if ($teksEssay): ?>
              <div style="margin-top:6px;padding:8px 12px;background:var(--g50);border:1px solid var(--g200);border-radius:6px;font-size:12px;color:var(--g800);width:100%"><?= nl2br(e($teksEssay)) ?></div>
              <?php else: ?>
              <span style="font-size:12px;color:var(--g400);font-style:italic">Tidak dijawab</span>
              <?php endif; ?>
            <?php elseif ($skip): ?>
              <span style="background:var(--g100);color:var(--g600);font-size:11px;font-weight:700;padding:2px 8px;border-radius:6px">Tidak Dijawab</span>
            <?php elseif ($benarSoal === true): ?>
              <span style="background:var(--grn-bg);color:var(--grn);font-size:11px;font-weight:800;padding:2px 9px;border-radius:6px;border:1px solid var(--grn-br)"><i class="bi bi-check"></i> <?= labelJwb2($s,$jwbSiswa) ?> — Benar</span>
            <?php elseif ($benarSoal === false): ?>
              <span style="background:var(--red-bg);color:var(--red);font-size:11px;font-weight:800;padding:2px 9px;border-radius:6px;border:1px solid var(--red-br)"><i class="bi bi-x"></i> <?= labelJwb2($s,$jwbSiswa) ?> — Salah</span>
              <span style="font-size:11px;color:var(--g600)">Kunci: <strong style="color:var(--grn)"><?= labelJwb2($s,$s['jawaban_benar']) ?></strong></span>
            <?php endif; ?>
          </div>

          <?php if (!empty(trim($s['pembahasan'] ?? ''))): ?>
          <div class="pembahasan-box">
            <div class="pb-label"><i class="bi bi-lightbulb-fill"></i> Pembahasan</div>
            <?= nl2br(e($s['pembahasan'])) ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>
    <?php endif; ?>
    <?php endif; ?>

  <div class="footer-area">
    <a href="<?= BASE_URL ?>/ujian/login_peserta.php">← Halaman Ujian</a>
    <span>|</span>
    <a href="<?= BASE_URL ?>/login.php">← Login Admin</a>
  </div>
</div>

<script>
document.querySelector('.field-input')?.addEventListener('input', function() {
    this.value = this.value.toUpperCase();
});
function toggleAcc(id) {
    const body  = document.getElementById('body-' + id);
    const arrow = document.getElementById('arrow-' + id);
    if (!body) return;
    const isOpen = body.classList.contains('show');
    body.classList.toggle('show', !isOpen);
    arrow.classList.toggle('open', !isOpen);
}
function bukaGrup(jadwalId) {
    document.querySelectorAll(`[id^="body-pb_${jadwalId}_"]`).forEach(b => b.classList.add('show'));
    document.querySelectorAll(`[id^="arrow-pb_${jadwalId}_"]`).forEach(a => a.classList.add('open'));
}
function tutupGrup(jadwalId) {
    document.querySelectorAll(`[id^="body-pb_${jadwalId}_"]`).forEach(b => b.classList.remove('show'));
    document.querySelectorAll(`[id^="arrow-pb_${jadwalId}_"]`).forEach(a => a.classList.remove('open'));
}
<?php if ($peserta && count($riwayat) > 1): ?>
const trenData = <?= json_encode(array_reverse(array_map(fn($r) => [
    'label' => ($r['jadwal_tanggal'] ? date('d/m', strtotime($r['jadwal_tanggal'])) : '?') . ' ' . e($r['nama_kategori']),
    'nilai' => (float)$r['nilai'],
], $riwayat))) ?>;
new Chart(document.getElementById('chartTren'), {
    type: 'line',
    data: {
        labels: trenData.map(d => d.label),
        datasets: [{
            label: 'Nilai',
            data: trenData.map(d => d.nilai),
            borderColor: '#1e3a8a',
            backgroundColor: 'rgba(30,58,138,.07)',
            borderWidth: 2.5,
            pointBackgroundColor: '#1e3a8a',
            pointRadius: 5,
            fill: true,
            tension: 0.3,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { min: 0, max: 100, grid: { color:'rgba(0,0,0,.04)' } },
            x: { ticks: { font: { size: 10 } } }
        }
    }
});
<?php endif; ?>
</script>
</body>
</html>
