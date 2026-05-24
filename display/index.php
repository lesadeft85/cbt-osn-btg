<?php
// ============================================================
// display/index.php — Layar Tunggu / Videotron
// Halaman publik — tidak perlu login
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/helper.php';

// Ambil pengaturan
$namaAplikasi  = getSetting($conn, 'nama_aplikasi', 'Sistem CBT TKA Kecamatan');
$namaKecamatan = getSetting($conn, 'nama_kecamatan', 'Kecamatan');
$displayInfo   = getSetting($conn, 'display_info', 'Selamat datang di Ujian CBT TKA');
$videoUrl      = getSetting($conn, 'display_video_url', '');
$logoFilePath  = getSetting($conn, 'logo_file_path', '');
$logoUrl       = getSetting($conn, 'logo_url', '');
$logoAktif     = $logoFilePath ? BASE_URL . '/' . $logoFilePath : $logoUrl;

// Jadwal ujian berikutnya atau yang sedang aktif
$today   = date('Y-m-d');
$nowTime = date('H:i:s');

// BUG FIX #7: Tambahkan null check agar tidak fatal error jika DB tidak merespons
$_rJa = $conn->query(
    "SELECT id, tanggal, jam_mulai, jam_selesai, durasi_menit, keterangan, status, kategori_id FROM jadwal_ujian
     WHERE tanggal='$today' AND jam_mulai<='$nowTime' AND jam_selesai>='$nowTime' AND status='aktif'
     LIMIT 1"
);
$jadwalAktif = ($_rJa && $_rJa->num_rows > 0) ? $_rJa->fetch_assoc() : null;

$_rJb = $conn->query(
    "SELECT id, tanggal, jam_mulai, jam_selesai, durasi_menit, keterangan, status, kategori_id FROM jadwal_ujian
     WHERE status='aktif' AND (tanggal > '$today' OR (tanggal='$today' AND jam_mulai > '$nowTime'))
     ORDER BY tanggal, jam_mulai LIMIT 1"
);
$jadwalBerikutnya = ($_rJb && $_rJb->num_rows > 0) ? $_rJb->fetch_assoc() : null;

// Statistik ringkas
$_rTp = $conn->query("SELECT COUNT(*) AS c FROM peserta");
$totalPeserta = $_rTp ? (int)$_rTp->fetch_assoc()['c'] : 0;

$_rTs = $conn->query("SELECT COUNT(*) AS c FROM sekolah");
$totalSekolah = $_rTs ? (int)$_rTs->fetch_assoc()['c'] : 0;

$_rSu = $conn->query("SELECT COUNT(*) AS c FROM ujian WHERE waktu_selesai IS NULL AND waktu_mulai IS NOT NULL");
$sedangUjian = $_rSu ? (int)$_rSu->fetch_assoc()['c'] : 0;

$_rSs = $conn->query("SELECT COUNT(*) AS c FROM ujian WHERE waktu_selesai IS NOT NULL AND DATE(waktu_selesai)=CURDATE()");
$sudahSelesai = $_rSs ? (int)$_rSs->fetch_assoc()['c'] : 0;

// Hitung countdown ke jadwal berikutnya
$countdownTarget = null;
if ($jadwalBerikutnya) {
    $countdownTarget = strtotime($jadwalBerikutnya['tanggal'] . ' ' . $jadwalBerikutnya['jam_mulai']);
} elseif ($jadwalAktif) {
    $countdownTarget = strtotime($today . ' ' . $jadwalAktif['jam_selesai']);
}

// Tentukan window tampilan tabel: tampilkan jika ada jadwal aktif
// atau sampai 1 jam setelah jadwal selesai (untuk menampilkan hasil akhir)
$nowMinus1Hour = date('H:i:s', time() - 3600);
$_rShow = $conn->query(
    "SELECT tanggal,jam_mulai,jam_selesai FROM jadwal_ujian
     WHERE status='aktif' AND tanggal='$today' AND
     (
        (jam_mulai <= '$nowTime' AND jam_selesai >= '$nowTime')
        OR
        (jam_selesai >= '$nowMinus1Hour' AND jam_selesai < '$nowTime')
     )
     LIMIT 1"
);
$showWindowStartTs = null;
$showWindowEndTs = null;
if ($_rShow && $_rShow->num_rows > 0) {
    $rowShow = $_rShow->fetch_assoc();
    $showWindowStartTs = strtotime($rowShow['tanggal'] . ' ' . $rowShow['jam_mulai']);
    $showWindowEndTs = strtotime($rowShow['tanggal'] . ' ' . $rowShow['jam_selesai']) + 3600; // +1 jam
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Layar Tunggu — <?= htmlspecialchars($namaAplikasi) ?></title>
<style>
/* ── Base ───────────────────────────────────────── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
    --bg:#0d1117;--surface:#161b22;--surface2:#21262d;
    --primary:#2563eb;--accent:#7c3aed;--green:#10b981;
    --text:#e6edf3;--muted:#8b949e;--border:#30363d;
    --card-bg:#1c2128;
}
html,body{height:100%;background:var(--bg);color:var(--text);font-family:'Segoe UI',system-ui,sans-serif;overflow:hidden}

/* ── Layout ─────────────────────────────────────── */
.display-wrap{display:flex;height:100vh;gap:0}
.col-main{flex:1;display:flex;flex-direction:column;overflow:hidden;position:relative}
.col-side{width:360px;flex-shrink:0;display:flex;flex-direction:column;border-left:1px solid var(--border);background:var(--surface);overflow:hidden}

/* ── Header bar ─────────────────────────────────── */
.top-bar{
    display:flex;align-items:center;justify-content:space-between;
    padding:14px 20px;
    background:linear-gradient(90deg,#1a56db,#7c3aed);
    border-bottom:1px solid rgba(255,255,255,.1);
    flex-shrink:0;
}
.top-bar-title{font-size:18px;font-weight:800;color:#fff;letter-spacing:.3px}
.top-bar-sub{font-size:12px;color:rgba(255,255,255,.75);margin-top:2px}
.live-dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:#10b981;margin-right:6px;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.6;transform:scale(1.3)}}

/* ── Video area ──────────────────────────────────── */
.video-area{flex:1;display:flex;align-items:center;justify-content:center;background:#000;position:relative;overflow:hidden}
.video-area iframe{width:100%;height:100%;border:none}
.realtime-overlay{
    position:absolute;inset:0;z-index:5;
    display:none;overflow:hidden;
    background:rgba(10,14,22,.92);backdrop-filter:blur(10px);
    border:none;border-radius:0;box-shadow:none;
}
.realtime-overlay.is-visible{display:flex;flex-direction:column}
.realtime-overlay-head{
    display:flex;align-items:center;justify-content:space-between;gap:10px;
    padding:14px 18px;border-bottom:1px solid rgba(255,255,255,.08);
    flex-shrink:0;background:rgba(10,14,22,.92);backdrop-filter:blur(10px);position:sticky;top:0;z-index:2
}
.realtime-overlay-title{display:flex;align-items:center;gap:10px;font-size:12px;font-weight:800;color:#fff;text-transform:uppercase;letter-spacing:.7px}
.realtime-live{display:inline-flex;align-items:center;gap:6px;background:rgba(16,185,129,.16);color:#6ee7b7;border:1px solid rgba(16,185,129,.28);border-radius:999px;padding:4px 10px;font-size:10px;font-weight:800}
.realtime-overlay-ts{font-size:11px;color:rgba(255,255,255,.66)}
.realtime-overlay-body{flex:1;overflow:hidden;padding:14px 18px 18px}
.realtime-scroll{height:100%;overflow-y:auto;scroll-behavior:smooth;overscroll-behavior:contain}
.realtime-scroll::-webkit-scrollbar{width:0;height:0}
.realtime-scroll-content{min-height:100%;padding-bottom:160px}
.realtime-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;align-items:stretch}
.realtime-panel{padding:14px 16px;min-height:0;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);border-radius:18px;box-shadow:0 10px 32px rgba(0,0,0,.15)}
.realtime-panel + .realtime-panel{border-left:none}
.realtime-panel-title{display:flex;align-items:center;justify-content:space-between;gap:10px;font-size:11px;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.7px;margin-bottom:10px}
.realtime-list{display:flex;flex-direction:column;gap:8px;min-height:0}
.realtime-row{
    display:grid;grid-template-columns:minmax(0,2.15fr) minmax(90px,.78fr) minmax(0,1.25fr) minmax(96px,.67fr);
    gap:10px;align-items:center;padding:9px 10px;border-radius:12px;
    background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06)
}
.realtime-row-head{
    display:grid;grid-template-columns:minmax(0,2.15fr) minmax(90px,.78fr) minmax(0,1.25fr) minmax(96px,.67fr);
    gap:10px;padding:0 10px 4px;color:rgba(255,255,255,.58);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.7px
}
.realtime-cell{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px}
.realtime-name{font-weight:800;color:#fff;white-space:normal;line-height:1.3;font-size:13px}
.top-badge{
    display:inline-flex;align-items:center;justify-content:center;gap:4px;
    margin-top:6px;padding:4px 9px;border-radius:999px;
    font-size:10px;font-weight:900;letter-spacing:.7px;text-transform:uppercase;
    width:fit-content;line-height:1;border:1px solid transparent
}
.top-badge.rank-1{background:linear-gradient(135deg,#fff4a3,#f59e0b 60%,#d97706);color:#111827;border-color:rgba(255,255,255,.35)}
.top-badge.rank-2{background:linear-gradient(135deg,#ffffff,#d7dee8 60%,#aeb9c8);color:#111827;border-color:rgba(255,255,255,.35)}
.top-badge.rank-3{background:linear-gradient(135deg,#ffcb8b,#ea8f1f 55%,#b45309);color:#fff;border-color:rgba(255,255,255,.18)}
.realtime-sub{font-size:11px;color:rgba(255,255,255,.68);margin-top:3px;white-space:normal;line-height:1.25}
.score-chip.temp,.score-chip.final,.progress-chip{min-width:0;width:100%;justify-content:center}
.realtime-empty{padding:10px 4px;color:rgba(255,255,255,.65);font-size:12px;text-align:center}
@media (max-width: 1100px){
    .realtime-grid{grid-template-columns:1fr}
    .realtime-panel + .realtime-panel{border-left:none}
}

@media (max-width: 768px){
    .realtime-overlay{inset:0}
    .realtime-overlay-head{padding:12px 14px}
    .realtime-overlay-body{padding:12px 14px 14px}
    .realtime-row,.realtime-row-head{grid-template-columns:1.9fr .85fr 1.1fr .65fr}
    .realtime-cell{font-size:11px}
}
.video-placeholder{
    text-align:center;padding:40px;
    background:linear-gradient(135deg,#1a56db22,#7c3aed22);
    border-radius:16px;border:1px solid var(--border);
    max-width:600px;
}
.video-placeholder .big-icon{font-size:80px;margin-bottom:16px;line-height:1}
.video-placeholder h2{font-size:28px;font-weight:800;color:var(--text);margin-bottom:8px}
.video-placeholder p{color:var(--muted);font-size:15px;line-height:1.6}

/* Slide carousel jika tidak ada video */
.slide-show{width:100%;height:100%;position:relative;overflow:hidden}
.slide{
    position:absolute;inset:0;display:flex;flex-direction:column;
    align-items:center;justify-content:center;text-align:center;
    padding:40px;opacity:0;transition:opacity 1s ease;
}
.slide.active{opacity:1}
.slide-1{background:linear-gradient(135deg,#1a56db,#7c3aed)}
.slide-2{background:linear-gradient(135deg,#047857,#065f46)}
.slide-3{background:linear-gradient(135deg,#92400e,#b45309)}
.slide h1{font-size:clamp(24px,4vw,56px);font-weight:900;color:#fff;text-shadow:0 2px 20px rgba(0,0,0,.4);margin-bottom:16px}
.slide p{font-size:clamp(14px,2vw,22px);color:rgba(255,255,255,.85);max-width:600px;line-height:1.6}
.slide .slide-icon{font-size:clamp(48px,8vw,100px);margin-bottom:20px}

/* ── Countdown ───────────────────────────────────── */
.countdown-area{
    padding:20px;border-bottom:1px solid var(--border);
    background:var(--card-bg);flex-shrink:0;
}
.countdown-title{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:10px}
.countdown-blocks{display:flex;gap:8px;justify-content:center}
.cd-block{
    flex:1;text-align:center;background:var(--surface2);
    border:1px solid var(--border);border-radius:10px;padding:10px 4px;
}
.cd-num{font-size:28px;font-weight:900;color:var(--text);font-variant-numeric:tabular-nums;line-height:1}
.cd-lbl{font-size:9px;color:var(--muted);text-transform:uppercase;margin-top:4px;letter-spacing:.5px}
.countdown-label{font-size:12px;color:var(--muted);text-align:center;margin-top:8px}

/* LIVE badge */
.live-badge-full{
    display:flex;align-items:center;justify-content:center;gap:8px;
    background:linear-gradient(90deg,#10b981,#059669);
    border-radius:10px;padding:14px 20px;
    font-size:18px;font-weight:800;color:#fff;
    animation:pulseBg 2s ease-in-out infinite;
}
@keyframes pulseBg{0%,100%{box-shadow:0 0 0 0 rgba(16,185,129,.4)}50%{box-shadow:0 0 20px 8px rgba(16,185,129,.15)}}

/* ── Stat cards ──────────────────────────────────── */
.stats-area{padding:16px;display:flex;flex-direction:column;gap:8px;flex-shrink:0}
.stat-row{
    display:flex;align-items:center;gap:12px;
    background:var(--card-bg);border:1px solid var(--border);
    border-radius:10px;padding:12px;
}
.stat-icon-sm{width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.ic-blue{background:rgba(37,99,235,.2);color:#60a5fa}
.ic-green{background:rgba(16,185,129,.2);color:#34d399}
.ic-orange{background:rgba(245,158,11,.2);color:#fbbf24}
.ic-purple{background:rgba(124,58,237,.2);color:#a78bfa}
.stat-num{font-size:22px;font-weight:800;color:var(--text);line-height:1}
.stat-lbl{font-size:11px;color:var(--muted);margin-top:2px}

/* ── Realtime students ───────────────────────────── */
.realtime-area{padding:16px;border-top:1px solid var(--border);background:linear-gradient(180deg,rgba(12,16,24,.35),rgba(12,16,24,.18))}
.realtime-head{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:8px}
.realtime-title{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.8px}
.realtime-ts{font-size:11px;color:var(--muted);text-align:right}
.realtime-block{background:var(--card-bg);border:1px solid var(--border);border-radius:10px;padding:12px;margin-top:10px}
.realtime-block-title{display:flex;align-items:center;justify-content:space-between;gap:8px;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:10px}
.student-list{display:flex;flex-direction:column;gap:8px}
.student-item{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;padding:10px;border-radius:10px;background:var(--surface2);border:1px solid rgba(255,255,255,.04)}
.student-main{min-width:0;flex:1}
.student-name{font-size:13px;font-weight:800;color:var(--text);line-height:1.25;word-break:break-word}
.student-meta{font-size:11px;color:var(--muted);margin-top:3px;line-height:1.35;word-break:break-word}
.student-badges{display:flex;flex-direction:column;align-items:flex-end;gap:5px;flex-shrink:0}
.score-chip,.progress-chip{display:inline-flex;align-items:center;justify-content:center;min-width:84px;border-radius:999px;padding:4px 10px;font-size:11px;font-weight:800;white-space:nowrap}
.score-chip.temp{background:rgba(59,130,246,.18);color:#93c5fd;border:1px solid rgba(59,130,246,.25)}
.score-chip.final{background:rgba(16,185,129,.18);color:#6ee7b7;border:1px solid rgba(16,185,129,.25)}
.progress-chip{background:rgba(245,158,11,.18);color:#fcd34d;border:1px solid rgba(245,158,11,.25)}
.student-empty{font-size:12px;color:var(--muted);text-align:center;padding:8px 6px}

/* ── Info area ───────────────────────────────────── */
.info-area{flex:1;padding:16px;overflow-y:auto;border-top:1px solid var(--border)}
.info-title{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:10px}
.info-text{font-size:14px;color:var(--text);line-height:1.8;background:var(--card-bg);border-radius:10px;padding:14px;border:1px solid var(--border)}
.jadwal-card{background:var(--card-bg);border:1px solid var(--border);border-radius:10px;padding:14px;margin-top:10px}
.jadwal-card .lbl{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}
.jadwal-card .val{font-size:15px;font-weight:700;color:var(--text);margin-top:2px}

/* ── Clock ───────────────────────────────────────── */
.clock-bar{
    padding:14px;text-align:center;border-top:1px solid var(--border);
    background:var(--card-bg);flex-shrink:0;
}
.clock{font-size:32px;font-weight:900;color:var(--text);font-variant-numeric:tabular-nums;letter-spacing:2px}
.clock-date{font-size:12px;color:var(--muted);margin-top:2px}

/* ── Scrollbar ───────────────────────────────────── */
::-webkit-scrollbar{width:4px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--border);border-radius:2px}
/* Fullscreen blocker overlay */
#fsBlocker{position:fixed;inset:0;display:none;align-items:center;justify-content:center;z-index:99999;background:rgba(0,0,0,0.6);backdrop-filter:blur(6px)}
#fsBlocker.visible{display:flex}
#fsBlocker .inner{background:rgba(10,14,22,.92);padding:22px;border-radius:12px;color:var(--text);text-align:center;max-width:520px;box-shadow:0 8px 36px rgba(0,0,0,.6)}
#fsBlocker .inner h2{font-size:20px;margin-bottom:8px}
#fsBlocker .inner p{color:var(--muted);margin-bottom:14px}
#fsBlocker .inner button{background:var(--primary);color:#fff;border:none;padding:10px 14px;border-radius:8px;font-weight:800;cursor:pointer}
</style>
</head>
<body>

<div class="display-wrap">

    <!-- ══ KOLOM UTAMA (kiri) ══ -->
    <div class="col-main">
        <!-- Top Bar -->
        <div class="top-bar">
            <div>
                <div class="top-bar-title">
                    <span class="live-dot"></span><?= htmlspecialchars($namaAplikasi) ?>
                </div>
                <div class="top-bar-sub">📍 <?= htmlspecialchars($namaKecamatan) ?></div>
            </div>
            <div style="font-size:13px;color:rgba(255,255,255,.8)" id="topClock"></div>
        </div>

        <!-- Area video / slideshow -->
        <div class="video-area">
            <?php if (!empty($videoUrl)): ?>
            <?php
                $videoSrc = htmlspecialchars($videoUrl);
                $videoSrc .= (strpos($videoUrl, '?') === false) ? '?autoplay=1&mute=1&loop=1&controls=0' : '&autoplay=1&mute=1&loop=1&controls=0';
            ?>
            <iframe src="<?= $videoSrc ?>"
                    allow="autoplay; fullscreen" allowfullscreen></iframe>
            <?php else: ?>
            <!-- Slideshow jika tidak ada video -->
            <div class="slide-show" id="slideShow">
                <div class="slide slide-1 active">
                    <div class="slide-icon">
                        <?php if ($logoAktif): ?>
                        <img src="<?= htmlspecialchars($logoAktif) ?>"
                             alt="Logo"
                             style="width:120px;height:120px;object-fit:contain;filter:drop-shadow(0 4px 12px rgba(0,0,0,0.3))"
                             onerror="this.outerHTML='🏫'">
                        <?php else: ?>
                        🏫
                        <?php endif; ?>
                    </div>
                    <h1><?= htmlspecialchars($namaAplikasi) ?></h1>
                    <p><?= htmlspecialchars($namaKecamatan) ?> — Ujian Berbasis Komputer</p>
                </div>
                <div class="slide slide-2">
                    <div class="slide-icon">📝</div>
                    <h1>Siapkan Dirimu</h1>
                    <p>Baca setiap soal dengan teliti.<br>Pastikan jawaban kamu sudah tersimpan.</p>
                </div>
                <div class="slide slide-3">
                    <div class="slide-icon">🎯</div>
                    <h1>Semangat!</h1>
                    <p>Kerjakan dengan jujur dan percaya diri.<br>Hasil terbaik menanti kamu!</p>
                </div>
            </div>
            <?php endif; ?>

            <div class="realtime-overlay" id="realtimeOverlay">
                <div class="realtime-overlay-head">
                    <div class="realtime-overlay-title">
                        <span class="realtime-live" id="realtimeBadge"><span>●</span> LIVE</span>
                        <span>Realtime Siswa Mengerjakan</span>
                    </div>
                    <div class="realtime-overlay-ts" id="rtTimestampOverlay">Memuat...</div>
                </div>
                <div class="realtime-overlay-body">
                    <div class="realtime-scroll" id="realtimeScroll">
                        <div class="realtime-scroll-content">
                            <div class="realtime-panel">
                                <div class="realtime-panel-title">
                                    <span>Peserta Aktif dan Selesai</span>
                                    <span><span id="rtSedangCount">0</span> aktif · <span id="rtSelesaiCount">0</span> selesai</span>
                                </div>
                                <div class="realtime-row-head">
                                    <div>Nama</div><div>Kode Sekolah</div><div>Sekolah</div><div>Nilai</div>
                                </div>
                                <div class="realtime-list" id="activeStudentList">
                                    <div class="realtime-empty">Memuat data siswa aktif...</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ KOLOM SAMPING (kanan) ══ -->
    <div class="col-side">

        <!-- Countdown / Status Ujian -->
        <div class="countdown-area">
            <div class="countdown-title">
                <?= $jadwalAktif ? 'Sesi Ujian Berlangsung' : 'Hitung Mundur' ?>
            </div>

            <?php if ($jadwalAktif): ?>
            <div class="live-badge-full">
                <span>●</span> UJIAN SEDANG BERLANGSUNG
            </div>
            <div class="countdown-label mt-2">
                <?= substr($jadwalAktif['jam_mulai'],0,5) ?> – <?= substr($jadwalAktif['jam_selesai'],0,5) ?>
                · <?= $jadwalAktif['durasi_menit'] ?> menit
            </div>
            <?php else: ?>
            <div class="countdown-blocks">
                <div class="cd-block"><div class="cd-num" id="cdJam">--</div><div class="cd-lbl">Jam</div></div>
                <div class="cd-block"><div class="cd-num" id="cdMenit">--</div><div class="cd-lbl">Menit</div></div>
                <div class="cd-block"><div class="cd-num" id="cdDetik">--</div><div class="cd-lbl">Detik</div></div>
            </div>
            <div class="countdown-label" id="cdLabel">
                <?php if ($jadwalBerikutnya): ?>
                Menuju sesi ujian: <?= formatTanggal($jadwalBerikutnya['tanggal']) ?>
                pukul <?= substr($jadwalBerikutnya['jam_mulai'],0,5) ?>
                <?php else: ?>
                Belum ada jadwal ujian terjadwal
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Statistik Realtime -->
        <div class="stats-area">
            <div class="countdown-title" style="padding:0">Statistik Hari Ini</div>
            <div class="stat-row">
                <div class="stat-icon-sm ic-blue">👥</div>
                <div>
                    <div class="stat-num" id="statPeserta"><?= $totalPeserta ?></div>
                    <div class="stat-lbl">Total Peserta Terdaftar</div>
                </div>
            </div>
            <div class="stat-row">
                <div class="stat-icon-sm ic-green">▶</div>
                <div>
                    <div class="stat-num" id="statUjian"><?= $sedangUjian ?></div>
                    <div class="stat-lbl">Sedang Ujian</div>
                </div>
            </div>
            <div class="stat-row">
                <div class="stat-icon-sm ic-orange">✅</div>
                <div>
                    <div class="stat-num" id="statSelesai"><?= $sudahSelesai ?></div>
                    <div class="stat-lbl">Selesai Hari Ini</div>
                </div>
            </div>
            <div class="stat-row">
                <div class="stat-icon-sm ic-purple">🏫</div>
                <div>
                    <div class="stat-num" id="statSekolah"><?= $totalSekolah ?></div>
                    <div class="stat-lbl">Jumlah Sekolah</div>
                </div>
            </div>
        </div>

        <!-- Info ujian -->
        <div class="info-area">
            <div class="info-title">Informasi Ujian</div>
            <div class="info-text"><?= nl2br(htmlspecialchars($displayInfo)) ?></div>

            <?php if ($jadwalBerikutnya): ?>
            <div class="jadwal-card">
                <div class="lbl">Jadwal Ujian Berikutnya</div>
                <div class="val"><?= formatTanggal($jadwalBerikutnya['tanggal']) ?></div>
                <div style="color:#60a5fa;font-size:14px;margin-top:4px">
                    🕐 <?= substr($jadwalBerikutnya['jam_mulai'],0,5) ?> –
                    <?= substr($jadwalBerikutnya['jam_selesai'],0,5) ?>
                    (<?= $jadwalBerikutnya['durasi_menit'] ?> menit)
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Jam -->
        <div class="clock-bar">
            <div class="clock" id="liveClock">--:--:--</div>
            <div class="clock-date" id="liveDate"></div>
        </div>
    </div>

</div>

<!-- Fullscreen blocker (blur + manual entry) -->
<div id="fsBlocker" aria-hidden="true">
    <div class="inner">
        <h2>Aktifkan Layar Penuh</h2>
        <p>Untuk pengalaman tampilan penuh (kiosk), silakan aktifkan layar penuh. Klik tombol di bawah jika tidak otomatis.</p>
        <button id="fsEnterBtn">Masuk Layar Penuh</button>
    </div>
</div>

<!-- Scroll debug bubble (hidden unless turned on) -->
<div id="scrollDebug" style="position:fixed;right:12px;bottom:120px;background:rgba(0,0,0,.6);color:#fff;padding:8px 10px;border-radius:8px;font-size:12px;z-index:99999;display:none;pointer-events:none">SCROLL</div>

<script>
// ── Jam realtime ─────────────────────────────────────────────
const hariName = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
const bulanName = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

function pad(n){ return String(n).padStart(2,'0'); }

function escHtml(value){
    return String(value ?? '').replace(/[&<>"]|'/g, function (char) {
        return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[char];
    });
}

// Debug flag: set true untuk mengaktifkan log debug di development
window.__DISPLAY_DEBUG = false;

function computeDisplayHash(items){
    return (items || []).map(item => {
        const key = getItemKey(item) || 'unknown';
        const score = getItemScore(item);
        const final = isFinalItem(item) ? 'F' : 'A';
        const progress = item.progress || 0;
        return `${key}:${score}:${final}:${progress}`;
    }).join('|');
}

// Utility: compute stable key for an item (works for active and final payloads)
function getItemKey(item){
    if (!item) return null;
    return item.kode_peserta || item.peserta_kode || item.peserta_id || item.id || item.nisn || item.nis || item.username || item.nama || null;
}

function normalizeItem(item){
    if (!item) return item;
    if (!item.nama) item.nama = item.nama_peserta || item.nama_lengkap || item.username || 'Peserta';
    return item;
}

function getItemScore(item){
    const score = item ? (item.nilai_sementara ?? item.nilai_akhir ?? item.nilai_final ?? item.nilai ?? 0) : 0;
    const parsed = parseFloat(score);
    return Number.isFinite(parsed) ? parsed : 0;
}

function isFinalItem(item){
    return !!(item && (item.is_final || item.selesai || item.nilai_akhir || item.nilai_final));
}

function getTestGroupKey(item){
    if (!item) return '-';
    const key = item.nama_kategori || item.kategori_id || item.jadwal_id || item.keterangan || '-';
    return String(key).trim() || '-';
}

function assignTopRanksByTest(items){
    const groups = new Map();
    (items || []).forEach(item => {
        const key = getTestGroupKey(item);
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(item);
    });
    groups.forEach((groupItems, key) => {
        groupItems.sort((a, b) => {
            const scoreB = getItemScore(b);
            const scoreA = getItemScore(a);
            if (scoreB !== scoreA) return scoreB - scoreA;
            const finalB = isFinalItem(b) ? 1 : 0;
            const finalA = isFinalItem(a) ? 1 : 0;
            if (finalB !== finalA) return finalB - finalA;
            const timeB = new Date(b.last_activity || b.selesai || b.mulai || 0).getTime() || 0;
            const timeA = new Date(a.last_activity || a.selesai || a.mulai || 0).getTime() || 0;
            return timeB - timeA;
        });
        groupItems.forEach((item, index) => {
            item.topRank = index + 1;
            item.topGroupKey = key;
        });
    });
    return items;
}

function syncTopBadge(row, item){
    if (!row) return;
    let badge = row.querySelector('.top-badge');
    if ((item.topRank || 0) >= 1 && (item.topRank || 0) <= 3) {
        if (!badge) {
            badge = document.createElement('div');
            badge.className = `top-badge rank-${item.topRank}`;
            const nameWrap = row.children[0] || row;
            nameWrap.appendChild(badge);
        }
        badge.className = `top-badge rank-${item.topRank}`;
        badge.textContent = `TOP ${item.topRank}`;
    } else if (badge) {
        badge.remove();
    }
}

function restartRealtimeScroll(){
    const overlay = document.getElementById('realtimeOverlay');
    const scrollEl = document.getElementById('realtimeScroll');
    if (!overlay || !scrollEl || !overlay.classList.contains('is-visible')) return;
    window.requestAnimationFrame(() => {
        syncRealtimeScrollBounds();
        if (!realtimeScrollRaf) startRealtimeScroll();
    });
}

// Jadwal tampilan (ms) — dikalkulasi server-side
const scheduleWindowStart = <?= $showWindowStartTs ? ($showWindowStartTs * 1000) : 'null' ?>;
const scheduleWindowEnd = <?= $showWindowEndTs ? ($showWindowEndTs * 1000) : 'null' ?>;
function isWithinScheduleWindow(){
    const now = Date.now();
    return scheduleWindowStart && scheduleWindowEnd ? (now >= scheduleWindowStart && now <= scheduleWindowEnd) : false;
}

function renderActiveStudents(items){
    try { clearEstimatedListHeight(document.getElementById('activeStudentList')); } catch(e){}
    const container = document.getElementById('activeStudentList');
    items = items || [];
    const activeCount = typeof window.__tableActiveCount === 'number' ? window.__tableActiveCount : items.filter(item => !isFinalItem(item)).length;
    const finishedCount = typeof window.__tableFinishedCount === 'number' ? window.__tableFinishedCount : items.filter(item => isFinalItem(item)).length;
    document.getElementById('rtSedangCount').textContent = activeCount;
    document.getElementById('rtSelesaiCount').textContent = finishedCount;
    if (!items || !items.length) {
        container.innerHTML = '<div class="realtime-empty">Belum ada siswa yang sedang ujian</div>';
        return;
    }

    // build map of existing rows
    const existing = new Map();
    Array.from(container.children).forEach(child => {
        if (child.dataset && child.dataset.key) existing.set(child.dataset.key, child);
    });

    // helper to create a new row element with stable sub-nodes
    function createRow(item){
        item = normalizeItem(item);
        const key = getItemKey(item) || ('unknown-' + Math.random().toString(36).slice(2,8));
        const row = document.createElement('div'); row.className = 'realtime-row'; row.dataset.key = key;

        const cell1 = document.createElement('div'); cell1.className = 'realtime-cell';
        const nameEl = document.createElement('div'); nameEl.className = 'realtime-name'; nameEl.textContent = item.nama || '';
        cell1.appendChild(nameEl);
        row.appendChild(cell1);
        syncTopBadge(row, item);

        const cell2 = document.createElement('div'); cell2.className = 'realtime-cell'; cell2.textContent = item.kode_sekolah || '-';
        const cell3 = document.createElement('div'); cell3.className = 'realtime-cell'; cell3.textContent = item.sekolah || '';
        const cell4 = document.createElement('div'); cell4.className = 'realtime-cell';
        const scoreSpan = document.createElement('span'); scoreSpan.className = 'score-chip';
        const isFinal = !!(item.is_final || item.selesai || item.nilai_akhir);
        scoreSpan.classList.add(isFinal ? 'final' : 'temp');
        scoreSpan.textContent = item.nilai_sementara ?? item.nilai_akhir ?? item.nilai_final ?? item.nilai ?? '-';
        cell4.appendChild(scoreSpan);
        const progressDiv = document.createElement('div'); progressDiv.className = 'realtime-sub';
        const progressText = isFinal ? 'Selesai' : (item.progress ? (item.progress + '% selesai') : '');
        if (progressText) { progressDiv.textContent = progressText; cell4.appendChild(progressDiv); }

        row.appendChild(cell2); row.appendChild(cell3); row.appendChild(cell4);
        return row;
    }

    function updateRow(row, item){
        const nameEl = row.querySelector('.realtime-name'); if (nameEl) nameEl.textContent = item.nama || '';
        syncTopBadge(row, item);
        const kodeSekEl = row.children[1]; if (kodeSekEl) kodeSekEl.textContent = item.kode_sekolah || '-';
        const sekolahEl = row.children[2]; if (sekolahEl) sekolahEl.textContent = item.sekolah || '';
        const scoreEl = row.querySelector('.score-chip');
        const isFinal = !!(item.is_final || item.selesai || item.nilai_akhir);
        if (scoreEl) {
            scoreEl.classList.remove('temp','final'); scoreEl.classList.add(isFinal ? 'final' : 'temp');
            scoreEl.textContent = item.nilai_sementara ?? item.nilai_akhir ?? item.nilai_final ?? item.nilai ?? '-';
        }
        const progressDiv = row.querySelector('.realtime-sub:last-of-type');
        const progressText = isFinal ? 'Selesai' : (item.progress ? (item.progress + '% selesai') : '');
        if (progressText) {
            if (progressDiv) progressDiv.textContent = progressText;
            else { const pd = document.createElement('div'); pd.className = 'realtime-sub'; pd.textContent = progressText; row.children[3].appendChild(pd); }
        } else {
            if (progressDiv) progressDiv.remove();
        }
    }

    // iterate desired order and update/insert rows with minimal, ordered DOM moves
    const rankedItems = assignTopRanksByTest(items.map((item, idx) => Object.assign({}, item, { displayIndex: idx + 1 })));
    rankedItems.forEach((item, idx) => {
        item = normalizeItem(item);
        const key = getItemKey(item) || ('unknown-' + idx);
        let row = existing.get(key);
        if (row) {
            updateRow(row, item);
            existing.delete(key);
        } else {
            row = createRow(item);
        }
        const currentAt = container.children[idx];
        if (currentAt !== row) container.insertBefore(row, currentAt || null);
    });

    // remove any leftover rows that are no longer present
    existing.forEach((row) => row.remove());
}

// ── Notifikasi suara untuk hasil final baru ─────────────────────
let __displayAudioCtx = null;
let __knownFinalKeys = new Set();
// keep a map of known finals so finished peserta remain visible across refreshes
window.__knownFinalMap = window.__knownFinalMap || {};
let __prevOverlayVisible = false;
let __prevActiveCount = 0;
let __firstRefresh = true;
function __makeAudioCtx(){
    if (__displayAudioCtx) return __displayAudioCtx;
    try { __displayAudioCtx = new (window.AudioContext || window.webkitAudioContext)(); } catch(e) { __displayAudioCtx = null; }
    return __displayAudioCtx;
}
function playTingNongSound(){
    const ctx = __makeAudioCtx(); if (!ctx) return;
    const now = ctx.currentTime;
    const master = ctx.createGain(); master.gain.setValueAtTime(0.0001, now); master.connect(ctx.destination);
    try { master.gain.exponentialRampToValueAtTime(0.6, now + 0.01); } catch(e){ master.gain.setValueAtTime(0.4, now + 0.01); }

    const osc = ctx.createOscillator(); osc.type = 'sine'; osc.frequency.setValueAtTime(880, now);
    const osc2 = ctx.createOscillator(); osc2.type = 'triangle'; osc2.frequency.setValueAtTime(1320, now + 0.06);
    const g = ctx.createGain(); g.gain.setValueAtTime(0.0001, now);
    g.gain.linearRampToValueAtTime(1.0, now + 0.02);
    g.gain.exponentialRampToValueAtTime(0.0001, now + 0.5);
    osc.connect(g); osc2.connect(g); g.connect(master);
    osc.start(now); osc2.start(now + 0.06);
    osc.stop(now + 0.48); osc2.stop(now + 0.48);
    try { master.gain.exponentialRampToValueAtTime(0.0001, now + 1); } catch(e){ master.gain.setValueAtTime(0.0001, now + 1); }
}
function playNewFinalSound(){
    const ctx = __makeAudioCtx();
    if (!ctx) return;
    const now = ctx.currentTime;
    const master = ctx.createGain(); master.gain.setValueAtTime(0.0001, now); master.connect(ctx.destination);
    // quick ramp to audible level to avoid click
    try { master.gain.exponentialRampToValueAtTime(0.9, now + 0.02); } catch(e) { master.gain.setValueAtTime(0.5, now + 0.02); }

    const playHit = (t) => {
        // two oscillators (square + saw) to create punchy 'ded'
        const osc1 = ctx.createOscillator(); osc1.type = 'square'; osc1.frequency.setValueAtTime(120, t);
        const osc2 = ctx.createOscillator(); osc2.type = 'sawtooth'; osc2.frequency.setValueAtTime(240, t);
        const g = ctx.createGain(); g.gain.setValueAtTime(0.0001, t);
        // fast attack, short decay for punch
        g.gain.linearRampToValueAtTime(1.0, t + 0.01);
        g.gain.exponentialRampToValueAtTime(0.0001, t + 0.18);
        osc1.connect(g); osc2.connect(g); g.connect(master);
        osc1.start(t); osc2.start(t);
        osc1.stop(t + 0.18); osc2.stop(t + 0.18);
    };

    // two hits: ded - ded
    playHit(now + 0.01);
    playHit(now + 0.26);
    // fade master out after sounds
    try { master.gain.exponentialRampToValueAtTime(0.0001, now + 1); } catch(e) { master.gain.setValueAtTime(0.0001, now + 1); }
}

// When animator is running we may defer full DOM updates; estimate list height
function setEstimatedListHeight(container, expectedCount){
    if (!container) return;
    const first = container.querySelector('.realtime-row');
    const rowH = first ? first.offsetHeight : 48;
    container.style.minHeight = (rowH * expectedCount) + 'px';
}
function clearEstimatedListHeight(container){
    if (!container) return;
    container.style.minHeight = '';
}

// Apply a final item directly into the DOM with minimal changes
function applyFinalToDOM(item){
    if (!item) return;
    const key = getItemKey(item);
    const container = document.getElementById('activeStudentList');
    if (!container) return;
    // find existing row by dataset.key
    let row = null;
    Array.from(container.children).forEach(child => { if (!row && child.dataset && child.dataset.key === key) row = child; });
    // build a minimal row element if not found
    function buildRow(it){
        const r = document.createElement('div'); r.className = 'realtime-row'; r.dataset.key = key;
        const c1 = document.createElement('div'); c1.className = 'realtime-cell';
        const nameEl = document.createElement('div'); nameEl.className = 'realtime-name'; nameEl.textContent = it.nama || '';
        c1.appendChild(nameEl);
        const c2 = document.createElement('div'); c2.className = 'realtime-cell'; c2.textContent = it.kode_sekolah || '-';
        const c3 = document.createElement('div'); c3.className = 'realtime-cell'; c3.textContent = it.sekolah || '';
        const c4 = document.createElement('div'); c4.className = 'realtime-cell';
        const scoreSpan = document.createElement('span'); scoreSpan.className = 'score-chip final';
        scoreSpan.textContent = it.nilai_sementara ?? it.nilai_akhir ?? it.nilai_final ?? it.nilai ?? '-';
        c4.appendChild(scoreSpan);
        const progressDiv = document.createElement('div'); progressDiv.className = 'realtime-sub'; progressDiv.textContent = 'Selesai'; c4.appendChild(progressDiv);
        r.appendChild(c1); r.appendChild(c2); r.appendChild(c3); r.appendChild(c4);
        return r;
    }
    if (row) {
        // update fields in place to avoid reflow of entire list
        const nameEl = row.querySelector('.realtime-name'); if (nameEl) nameEl.textContent = item.nama || '';
        const kodeSekEl = row.children[1]; if (kodeSekEl) kodeSekEl.textContent = item.kode_sekolah || '-';
        const sekolahEl = row.children[2]; if (sekolahEl) sekolahEl.textContent = item.sekolah || '';
        let scoreEl = row.querySelector('.score-chip');
        if (!scoreEl) { scoreEl = document.createElement('span'); scoreEl.className = 'score-chip'; row.children[3].appendChild(scoreEl); }
        scoreEl.classList.remove('temp'); scoreEl.classList.add('final');
        scoreEl.textContent = item.nilai_sementara ?? item.nilai_akhir ?? item.nilai_final ?? item.nilai ?? '-';
        let progressDiv = row.querySelector('.realtime-sub:last-of-type');
        if (!progressDiv) { progressDiv = document.createElement('div'); progressDiv.className = 'realtime-sub'; row.children[3].appendChild(progressDiv); }
        progressDiv.textContent = 'Selesai';
    } else {
        // append new final at the end so it becomes part of scrolling
        const newRow = buildRow(item);
        container.appendChild(newRow);
    }
}

// Update an existing row in-place (or no-op if not present). Lightweight updates for progress/score.
function updateRowInDOM(item){
    if (!item) return;
    const key = getItemKey(item);
    if (!key) return;
    const container = document.getElementById('activeStudentList');
    if (!container) return;
    let row = null;
    Array.from(container.children).forEach(child => { if (!row && child.dataset && child.dataset.key === key) row = child; });
    if (!row) return;
    // update basic fields
    try {
        const nameEl = row.querySelector('.realtime-name'); if (nameEl) nameEl.textContent = item.nama || '';
        syncTopBadge(row, item);
        const kodeSekEl = row.children[1]; if (kodeSekEl) kodeSekEl.textContent = item.kode_sekolah || '-';
        const sekolahEl = row.children[2]; if (sekolahEl) sekolahEl.textContent = item.sekolah || '';
        let scoreEl = row.querySelector('.score-chip');
        if (!scoreEl) { scoreEl = document.createElement('span'); scoreEl.className = 'score-chip'; row.children[3].appendChild(scoreEl); }
        const isFinal = !!(item.is_final || item.selesai || item.nilai_akhir);
        scoreEl.classList.remove('temp','final'); scoreEl.classList.add(isFinal ? 'final' : 'temp');
        scoreEl.textContent = item.nilai_sementara ?? item.nilai_akhir ?? item.nilai_final ?? item.nilai ?? '-';
        let progressDiv = row.querySelector('.realtime-sub:last-of-type');
        const progressText = isFinal ? 'Selesai' : (item.progress ? (item.progress + '% selesai') : '');
        if (progressText) {
            if (progressDiv) progressDiv.textContent = progressText;
            else { progressDiv = document.createElement('div'); progressDiv.className = 'realtime-sub'; progressDiv.textContent = progressText; row.children[3].appendChild(progressDiv); }
        } else {
            if (progressDiv) progressDiv.remove();
        }
    } catch(e) {}
}

// Resume audio context on user gesture (some browsers require user interaction)
window.addEventListener('click', function(){
    const c = __displayAudioCtx; if (c && c.state === 'suspended') c.resume().catch(()=>{});
});

let realtimeScrollDir = 1;
let realtimeScrollRaf = null;

function getRealtimeScrollSpeed(scrollEl){
    // Speeds are in "pixels per frame" average. We support an initial very-slow
    // phase (sub-pixel) which will be accumulated by the animator to avoid
    // browser rounding/stall issues while still appearing slower at start.
    const initialPhaseMs = window.__realtimeInitialMs || 8000; // default 8s
    const initialStart = window.__realtimeStartTs || 0;
    const now = Date.now();
    // very slow during initial phase
    if (initialStart && (now - initialStart) < initialPhaseMs) {
        return 0.25; // ~1px every 4 frames
    }
    const slowSpeed = 0.5;
    const fastSpeed = 1;
    const rows = scrollEl.querySelectorAll('.realtime-row');
    const tenthRow = rows[9];
    const slowLimit = tenthRow ? Math.max(0, tenthRow.offsetTop - 18) : 0;
    return scrollEl.scrollTop < slowLimit ? slowSpeed : fastSpeed;
}

function syncRealtimeScrollBounds(){
    const scrollEl = document.getElementById('realtimeScroll');
    if (!scrollEl) return;
    if (scrollEl.scrollHeight <= scrollEl.clientHeight + 4) {
        scrollEl.scrollTop = 0;
    }
}

function animateRealtimeScroll(){
    const overlay = document.getElementById('realtimeOverlay');
    const scrollEl = document.getElementById('realtimeScroll');
    const dbg = document.getElementById('scrollDebug');
    if (window.__DISPLAY_DEBUG) console.debug && console.debug('animateRealtimeScroll tick', Date.now(), 'scrollTop', scrollEl ? scrollEl.scrollTop : null);
    if (!overlay || !scrollEl || !overlay.classList.contains('is-visible')) {
        if (dbg) { dbg.style.display = window.__DISPLAY_DEBUG ? 'block' : 'none'; if (window.__DISPLAY_DEBUG) dbg.textContent = 'SCROLL: idle'; }
        realtimeScrollRaf = window.requestAnimationFrame(animateRealtimeScroll);
        return;
    }

    // ensure programmatic scrolls use auto behavior to avoid conflicts with CSS smooth
    if (!scrollEl.__realtime_originalScrollBehavior) scrollEl.__realtime_originalScrollBehavior = scrollEl.style.scrollBehavior || '';
    if (scrollEl.style.scrollBehavior !== 'auto') scrollEl.style.scrollBehavior = 'auto';
    const maxScroll = Math.max(0, scrollEl.scrollHeight - scrollEl.clientHeight);
    if (maxScroll > 0) {
        // Use an accumulator to allow sub-pixel average speeds while only
        // applying integer pixel moves to avoid fractional rounding problems.
        if (typeof window.__scrollAccumulator === 'undefined') window.__scrollAccumulator = 0;
        const delta = getRealtimeScrollSpeed(scrollEl) || 0;
        window.__scrollAccumulator += delta;
        const step = Math.floor(window.__scrollAccumulator);
        if (step > 0) {
            window.__scrollAccumulator -= step;
            const next = scrollEl.scrollTop + step;
            if (next >= maxScroll) scrollEl.scrollTop = 0;
            else scrollEl.scrollTop = Math.max(0, Math.min(maxScroll, next));
        }
    }

    if (dbg) {
        dbg.style.display = window.__DISPLAY_DEBUG ? 'block' : 'none';
        if (window.__DISPLAY_DEBUG) dbg.textContent = `SCROLL: running\nTop:${Math.round(scrollEl.scrollTop)} H:${scrollEl.scrollHeight} C:${scrollEl.clientHeight}`;
    }
    realtimeScrollRaf = window.requestAnimationFrame(animateRealtimeScroll);
}

function startRealtimeScroll(){
    if (realtimeScrollRaf) return;
    realtimeScrollDir = 1;
    // mark that the realtime animator is active and disable CSS smooth scrolling
    window.__realtimeAnimating = true;
    if (window.__DISPLAY_DEBUG) console.debug && console.debug('startRealtimeScroll invoked');
    const scrollEl = document.getElementById('realtimeScroll');
    if (scrollEl) {
        scrollEl.__realtime_originalScrollBehavior = scrollEl.style.scrollBehavior || '';
        scrollEl.style.scrollBehavior = 'auto';
    }
    // initialize initial-phase timestamp so getRealtimeScrollSpeed can detect start
    window.__realtimeStartTs = Date.now();
    // default initial slow phase length (ms) can be overridden elsewhere
    if (typeof window.__realtimeInitialMs === 'undefined') window.__realtimeInitialMs = 8000;
    // reset accumulator to ensure deterministic behavior each start
    window.__scrollAccumulator = 0;
    realtimeScrollRaf = window.requestAnimationFrame(animateRealtimeScroll);
}

function stopRealtimeScroll(){
    if (realtimeScrollRaf) {
        window.cancelAnimationFrame(realtimeScrollRaf);
        realtimeScrollRaf = null;
        // restore flag and original scroll behavior
        window.__realtimeAnimating = false;
        if (window.__DISPLAY_DEBUG) console.debug && console.debug('stopRealtimeScroll invoked');
        const scrollEl = document.getElementById('realtimeScroll');
        if (scrollEl && typeof scrollEl.__realtime_originalScrollBehavior !== 'undefined') {
            scrollEl.style.scrollBehavior = scrollEl.__realtime_originalScrollBehavior || '';
            delete scrollEl.__realtime_originalScrollBehavior;
        }
        // clear initial-phase markers and accumulator when stopping
        try { delete window.__realtimeStartTs; } catch(e){}
        try { delete window.__scrollAccumulator; } catch(e){}
        // if there is a pending render queued while animating, try flushing it now
        try { if (typeof renderIfPending === 'function') renderIfPending(); } catch(e){}
    }
}

// Render scheduler: when animator is active, postpone heavy renders to avoid fighting programmatic scroll
window.__pendingActiveItems = null;
window.__pendingRenderTimer = null;
function renderIfPending(){
    if (!window.__pendingActiveItems) return;
    if (window.__DISPLAY_DEBUG) console.debug && console.debug('renderIfPending check', {animating: !!window.__realtimeAnimating});
    if (window.__realtimeAnimating) {
        // still animating, reschedule
        if (window.__DISPLAY_DEBUG) console.debug && console.debug('renderIfPending: still animating, reschedule');
        if (!window.__pendingRenderTimer) {
            window.__pendingRenderTimer = setTimeout(() => { window.__pendingRenderTimer = null; renderIfPending(); }, 700);
        }
        return;
    }
    try {
        if (window.__DISPLAY_DEBUG) console.debug && console.debug('renderIfPending: flushing render');
        // clear any estimated height applied while deferring
        try { clearEstimatedListHeight(document.getElementById('activeStudentList')); } catch(e){}
        renderActiveStudents(window.__pendingActiveItems);
    } catch(e) {}
    window.__pendingActiveItems = null;
    restartRealtimeScroll();
}

function updateClock(){
    const now  = new Date();
    const wkt  = pad(now.getHours())+':'+pad(now.getMinutes())+':'+pad(now.getSeconds());
    const tgl  = hariName[now.getDay()]+', '+now.getDate()+' '+bulanName[now.getMonth()]+' '+now.getFullYear();
    document.getElementById('liveClock').textContent = wkt;
    document.getElementById('liveDate').textContent  = tgl;
    document.getElementById('topClock').textContent  = wkt + ' WIB';
}
setInterval(updateClock, 1000);
updateClock();

// ── Countdown ─────────────────────────────────────────────────
<?php if ($countdownTarget && !$jadwalAktif): ?>
let targetTs = <?= $countdownTarget ?> * 1000;
function updateCountdown(){
    const sisa = Math.max(0, Math.floor((targetTs - Date.now()) / 1000));
    const j = Math.floor(sisa / 3600);
    const m = Math.floor((sisa % 3600) / 60);
    const s = sisa % 60;
    document.getElementById('cdJam').textContent   = pad(j);
    document.getElementById('cdMenit').textContent = pad(m);
    document.getElementById('cdDetik').textContent = pad(s);
    if (sisa <= 0) document.getElementById('cdLabel').textContent = '⏰ Ujian segera dimulai!';
}
setInterval(updateCountdown, 1000);
updateCountdown();
<?php endif; ?>

// ── Slideshow (jika tidak ada video) ─────────────────────────
<?php if (empty($videoUrl)): ?>
const slides = document.querySelectorAll('.slide');
let current  = 0;
setInterval(() => {
    slides[current].classList.remove('active');
    current = (current + 1) % slides.length;
    slides[current].classList.add('active');
}, 6000);
<?php endif; ?>

// ── Realtime stats refresh ────────────────────────────────────
function refreshStats(){
    fetch('<?= BASE_URL ?>/admin/ajax_statistik.php')
        .then(r => r.ok ? r.json() : null)
        .then(d => {
            if (!d) return;
            document.getElementById('statPeserta').textContent  = d.total_peserta;
            document.getElementById('statSekolah').textContent  = d.total_sekolah;
            document.getElementById('statUjian').textContent    = d.peserta_ujian;
            document.getElementById('statSelesai').textContent  = d.peserta_selesai;
            const activeNow = (d.sedang_detail || []).map(it => normalizeItem(it));
            const finals = d.hasil_final || [];
            window.__tableActiveCount = activeNow.length;
            window.__tableFinishedCount = finals.length;
            const byKey = new Map();
            activeNow.forEach((item, index) => {
                const key = getItemKey(item) || ('active-' + index);
                byKey.set(key, item);
                if (item.selesai || item.is_final || item.nilai_akhir) {
                    __knownFinalKeys.add(key);
                    try { window.__knownFinalMap[key] = Object.assign({}, item); } catch(e){}
                }
            });
            const prevKnown = new Set(__knownFinalKeys);
            const normalizedFinals = [];
            finals.forEach(f => {
                const nf = normalizeItem(f);
                normalizedFinals.push(nf);
                const key = getItemKey(nf);
                if (!key) return;
                if (byKey.has(key)) {
                    const existing = byKey.get(key);
                    existing.is_final = true;
                    existing.selesai = true;
                    existing.nilai_akhir = nf.nilai_akhir ?? nf.nilai_final ?? nf.nilai ?? nf.nilai_sementara ?? existing.nilai_sementara;
                    byKey.set(key, existing);
                    __knownFinalKeys.add(key);
                    try { window.__knownFinalMap[key] = Object.assign({}, existing); } catch(e){}
                } else {
                    const copy = Object.assign({}, nf, {
                        is_final: true,
                        selesai: true,
                        nilai_sementara: nf.nilai_akhir ?? nf.nilai_final ?? nf.nilai ?? nf.nilai_sementara,
                    });
                    byKey.set(key, copy);
                    __knownFinalKeys.add(key);
                    try { window.__knownFinalMap[key] = Object.assign({}, copy); } catch(e){}
                }
            });
            try {
                const newly = normalizedFinals.filter(nf => {
                    const k = getItemKey(nf);
                    return k && !prevKnown.has(k);
                });
                if (newly.length) playNewFinalSound();
            } catch(e) {}
            // keep finals in the table even if they are no longer active
            __knownFinalKeys.forEach(k => {
                if (!byKey.has(k) && window.__knownFinalMap[k]) {
                    byKey.set(k, window.__knownFinalMap[k]);
                }
            });
            const displayItems = Array.from(byKey.values()).sort((a, b) => {
                const scoreB = getItemScore(b);
                const scoreA = getItemScore(a);
                if (scoreB !== scoreA) return scoreB - scoreA;
                const finalB = isFinalItem(b) ? 1 : 0;
                const finalA = isFinalItem(a) ? 1 : 0;
                if (finalB !== finalA) return finalB - finalA;
                const timeB = new Date(b.last_activity || b.selesai || b.mulai || 0).getTime() || 0;
                const timeA = new Date(a.last_activity || a.selesai || a.mulai || 0).getTime() || 0;
                return timeB - timeA;
            });
            assignTopRanksByTest(displayItems);
            const displayHash = computeDisplayHash(displayItems);
            const shouldRender = displayHash !== window.__prevDisplayHash;
            window.__prevDisplayHash = displayHash;
            if (window.__DISPLAY_DEBUG) console.debug && console.debug('refreshStats displayHash', displayHash, 'shouldRender', shouldRender);
            if (shouldRender) {
                // apply lightweight in-place updates now that ranks exist
                try { displayItems.forEach(it => { try { updateRowInDOM(it); } catch(e){} }); } catch(e){}
            }
            if (window.__DISPLAY_DEBUG) console.debug && console.debug('refreshStats fetched', { overlay_visible: d.overlay_visible, activeCount: activeNow.length, displayCount: displayItems.length, timestamp: d.timestamp });
            const overlay = document.getElementById('realtimeOverlay');
            const newOverlayVisible = isWithinScheduleWindow() || !!d.overlay_visible;
            if (window.__DISPLAY_DEBUG) console.debug && console.debug('refreshStats computed', { newOverlayVisible: newOverlayVisible, activeItemsLength: activeNow.length, displayItemsLength: displayItems.length });
            // Play sound when overlay becomes visible or when new peserta start (only after first refresh)
            if (!__firstRefresh) {
                if (newOverlayVisible && !__prevOverlayVisible) playTingNongSound();
                if ((activeNow.length || 0) > (__prevActiveCount || 0)) playTingNongSound();
            }
            __prevOverlayVisible = !!newOverlayVisible;
            __prevActiveCount = activeNow.length || 0;
            __firstRefresh = false;

            overlay.classList.toggle('is-visible', newOverlayVisible);
            if (newOverlayVisible) {
                document.getElementById('realtimeBadge').innerHTML = '<span>●</span> LIVE';
                document.getElementById('rtTimestampOverlay').textContent = 'Update ' + (d.timestamp || '--:--:--') + ' WIB';
                // preserve scroll position to avoid stopping the auto-scroll when list updates
                const scrollEl = document.getElementById('realtimeScroll');
                const prevScrollTop = scrollEl ? scrollEl.scrollTop : 0;
                const prevScrollHeight = scrollEl ? scrollEl.scrollHeight : 0;
                // If realtime animator is running, queue the items instead of rendering immediately
                if (shouldRender) {
                    if (window.__realtimeAnimating) {
                        window.__pendingActiveItems = displayItems;
                        try { setEstimatedListHeight(document.getElementById('activeStudentList'), displayItems.length); } catch(e){}
                        if (!window.__pendingRenderTimer) {
                            window.__pendingRenderTimer = setTimeout(() => { window.__pendingRenderTimer = null; renderIfPending(); }, 700);
                        }
                    } else {
                        renderActiveStudents(displayItems);
                    }
                }
                // restore proportional scroll so animation continues smoothly
                if (scrollEl) {
                    const newScrollHeight = scrollEl.scrollHeight;
                    const clientH = scrollEl.clientHeight || 0;
                    const maxScroll = Math.max(0, newScrollHeight - clientH);
                    const restored = Math.min(maxScroll, Math.max(0, prevScrollTop + (newScrollHeight - prevScrollHeight)));
                    // if realtime animator is active, avoid forcing scrollTop to prevent fighting the animator
                    if (!window.__realtimeAnimating) {
                        // if change is large, set directly; otherwise adjust minimally to avoid jump
                        if (Math.abs((scrollEl.scrollTop || 0) - restored) > 5) {
                            scrollEl.scrollTop = restored;
                        } else {
                            scrollEl.scrollTop = restored;
                        }
                    }
                    if (window.__DISPLAY_DEBUG) {
                        const dbg = document.getElementById('scrollDebug'); if (dbg) { dbg.style.display = 'block'; dbg.textContent = `RESTORED Top:${Math.round(restored)} H:${newScrollHeight}`; }
                    }
                }
                syncRealtimeScrollBounds();
                restartRealtimeScroll();
            } else {
                try { clearEstimatedListHeight(document.getElementById('activeStudentList')); } catch(e){}
                document.getElementById('activeStudentList').innerHTML = '<div class="realtime-empty">Belum ada siswa yang sedang ujian</div>';
                document.getElementById('rtSedangCount').textContent = '0';
                document.getElementById('rtSelesaiCount').textContent = '0';
                stopRealtimeScroll();
            }
        })
        .catch(() => {});
}
// Poll lebih cepat supaya peserta yang baru mulai ujian masuk ke tabel tanpa jeda panjang.
setInterval(refreshStats, 5000);
refreshStats();

// ── Kiosk / Fullscreen attempt with retry and blur fallback ─────
function showFsBlocker(){
    const blocker = document.getElementById('fsBlocker');
    if (!blocker) return;
    blocker.classList.add('visible');
}

function hideFsBlocker(){
    const blocker = document.getElementById('fsBlocker');
    if (!blocker) return;
    blocker.classList.remove('visible');
}

function tryRequestFullscreen(){
    const el = document.documentElement;
    if (!document.fullscreenEnabled && !document.webkitFullscreenEnabled && !document.msFullscreenEnabled) return false;
    const fn = el.requestFullscreen || el.webkitRequestFullscreen || el.msRequestFullscreen;
    if (!fn) return false;
    try {
        const res = fn.call(el);
        // modern browsers return a Promise — swallow rejections to avoid uncaught errors
        if (res && typeof res.then === 'function') {
            res.then(() => { try { hideFsBlocker(); } catch(e){} }).catch(() => {});
        }
        return true;
    } catch(e) {
        return false;
    }
}

function enterFullScreenOnce(){
    if (document.fullscreenElement) { hideFsBlocker(); return; }
    if (window.__fsAttemptInProgress) return; // avoid overlapping attempts
    window.__fsAttemptInProgress = true;
    let attempts = 0; const maxAttempts = 4; const interval = 400;
    const tick = () => {
        attempts++;
        tryRequestFullscreen();
        if (document.fullscreenElement) { hideFsBlocker(); return; }
        if (attempts < maxAttempts) {
            setTimeout(tick, interval);
        } else {
            // after retries, show blocker overlay requiring manual gesture
            showFsBlocker();
            window.__fsAttemptInProgress = false;
        }
    };
    tick();
    // safety: if still not fullscreen after short delay, show blocker
    setTimeout(() => { if (!document.fullscreenElement) { showFsBlocker(); window.__fsAttemptInProgress = false; } }, (maxAttempts * interval) + 300);
}

document.addEventListener('fullscreenchange', function(){
    if (document.fullscreenElement) hideFsBlocker();
    else showFsBlocker();
});

// Don't attempt fullscreen automatically without a user gesture.
// Instead, show the blocker and wait for a user interaction (click/keydown/touch).
window.__fsUserInteracted = false;
function onFirstUserGesture(){
    window.__fsUserInteracted = true;
    tryRequestFullscreen();
    // remove listeners (they were added with once:true below, but ensure cleanup)
    window.removeEventListener('pointerdown', onFirstUserGesture);
    window.removeEventListener('keydown', onFirstUserGesture);
    window.removeEventListener('touchstart', onFirstUserGesture);
}

document.addEventListener('visibilitychange', function(){
    if (document.hidden) return; // when tab becomes visible, ensure blocker shows if not fullscreen
    if (!document.fullscreenElement) showFsBlocker();
});

document.addEventListener('DOMContentLoaded', function(){
    // show blocker if not fullscreen; allow user to manually enter fullscreen
    if (!document.fullscreenElement) showFsBlocker();
    // wire manual button inside blocker
    const manualBtn = document.getElementById('fsEnterBtn');
    if (manualBtn) manualBtn.addEventListener('click', function(){ window.__fsUserInteracted = true; tryRequestFullscreen(); });

    // listen for a first simple user gesture to attempt fullscreen once (some environments allow it)
    window.addEventListener('pointerdown', onFirstUserGesture, { once: true });
    window.addEventListener('keydown', onFirstUserGesture, { once: true });
    window.addEventListener('touchstart', onFirstUserGesture, { once: true });
});
</script>
</body>
</html>
