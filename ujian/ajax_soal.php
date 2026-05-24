<?php
// ujian/ajax_soal.php — Ambil data soal via AJAX (tanpa reload halaman)
if (session_status() === PHP_SESSION_NONE) { session_name('TKA_PESERTA'); session_start(); }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/helper.php';

header('Content-Type: application/json');

if (empty($_SESSION['peserta_id'])) { echo json_encode(['ok'=>false,'msg'=>'Session expired']); exit; }

$no        = max(1, (int)($_GET['no'] ?? 1));
$ujianId   = (int)$_SESSION['ujian_id'];
$pesertaId = (int)$_SESSION['peserta_id'];

if (empty($_SESSION['soal_order'])) { echo json_encode(['ok'=>false,'msg'=>'Soal tidak ditemukan']); exit; }

$ids     = implode(',', array_map('intval', $_SESSION['soal_order']));
$total   = count($_SESSION['soal_order']);
$no      = max(1, min($no, $total));

// ── 1. Ambil 1 soal — SATU query ─────────────────────────────
$targetId = (int)$_SESSION['soal_order'][$no - 1];
$res = $conn->query(
    "SELECT id,tipe_soal,pertanyaan,teks_bacaan,gambar,
            pilihan_a,pilihan_b,pilihan_c,pilihan_d,
            gambar_pilihan_a,gambar_pilihan_b,gambar_pilihan_c,gambar_pilihan_d
     FROM soal WHERE id=$targetId LIMIT 1"
);
$soal = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
if ($res) $res->free();
if (!$soal) { echo json_encode(['ok'=>false,'msg'=>'Soal tidak ditemukan']); exit; }
$soalId = $soal['id'];

// ── 2. Jawaban & ragu dari SESSION cache ─────────────────────
// DIUBAH: tambah pengecekan isJawabanCacheValid() agar cache
// otomatis di-refresh jika admin mengubah jawaban dari panel.
// Semua logika lain di bawah ini TIDAK berubah sama sekali.
if (!isset($_SESSION['_jawaban_cache']) || !isJawabanCacheValid($conn, $ujianId)) {
    // Cache belum ada atau sudah basi — bangun ulang dari DB
    $_SESSION['_jawaban_cache']      = [];
    $_SESSION['_teks_jawaban_cache'] = [];
    $jrAll = $conn->query(
        "SELECT soal_id, jawaban, teks_jawaban FROM jawaban
         WHERE ujian_id=$ujianId AND peserta_id=$pesertaId"
    );
    if ($jrAll) while ($j = $jrAll->fetch_assoc()) {
        $_SESSION['_jawaban_cache'][$j['soal_id']] = $j['jawaban'];
        if ($j['teks_jawaban'] !== null)
            $_SESSION['_teks_jawaban_cache'][$j['soal_id']] = $j['teks_jawaban'];
    }
}
$jawabans     = $_SESSION['_jawaban_cache'];
$teksJawabans = $_SESSION['_teks_jawaban_cache'] ?? [];
$raguList     = $_SESSION['ragu'] ?? [];

$jwbAktif   = $jawabans[$soalId] ?? null;
$isRagu     = in_array($soalId, $raguList);
$sdhJawab   = count($jawabans);
$jumlahRagu = count($raguList);
$belumJawab = $total - $sdhJawab;

// ── 3. Setting acak pilihan dari SESSION cache — ZERO query ──
if (!isset($_SESSION['_settings_cache']['acak_pilihan'])) {
    $_SESSION['_settings_cache']['acak_pilihan'] = getSetting($conn, 'acak_pilihan', '0');
}
$acakPilihan = $_SESSION['_settings_cache']['acak_pilihan'] === '1';

// ── 4. Build pilihan HTML ─────────────────────────────────────
$baseUrl     = BASE_URL;
$pilihanHtml = '';

if ($soal['tipe_soal'] === 'bs') {
    foreach (['benar'=>'Benar','salah'=>'Salah'] as $val=>$label) {
        $sel   = $jwbAktif===$val ? 'selected' : '';
        $huruf = $val==='benar' ? 'B' : 'S';
        $pilihanHtml .= "<div class=\"pilihan-item $sel\" onclick=\"pilihJawaban('$val',this,$soalId)\"><div class=\"huruf-box\">$huruf</div><div class=\"pilihan-teks\">$label</div></div>";
    }
} elseif ($soal['tipe_soal'] === 'mcma') {
    $jwbMcmaArr   = $jwbAktif ? explode(',', $jwbAktif) : [];
    $pilihanHtml .= '<div class="mcma-info"><i class="bi bi-info-circle me-1"></i>Boleh pilih lebih dari satu jawaban yang benar.</div>';
    foreach (['a','b','c','d'] as $h) {
        $teks = $soal['pilihan_'.$h] ?? '';
        if (!$teks) continue;
        $dipilih = in_array($h, $jwbMcmaArr);
        $selCls  = $dipilih ? 'mcma-selected' : '';
        $chkBg   = $dipilih ? '#7c3aed' : 'transparent';
        $chkBdr  = $dipilih ? '#7c3aed' : '#cbd5e1';
        $chkMark = $dipilih ? '<svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2 6l3 3 5-5" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>' : '';
        $teksEsc = htmlspecialchars($teks);
        $pilihanHtml .= "<div class=\"pilihan-item $selCls\" onclick=\"pilihMcma('$h',this,$soalId)\"><div class=\"huruf-box\">".strtoupper($h)."</div><div class=\"pilihan-teks\">$teksEsc</div><div style=\"margin-left:auto;flex-shrink:0\"><div class=\"mcma-check\" style=\"width:20px;height:20px;border-radius:4px;border:2px solid $chkBdr;background:$chkBg;display:flex;align-items:center;justify-content:center\">$chkMark</div></div></div>";
    }
    $pilihanHtml .= '<input type="hidden" id="mcmaValue" value="'.htmlspecialchars($jwbAktif??'').'">';
} elseif ($soal['tipe_soal'] === 'essay') {
    $teksJwbEssay = $teksJawabans[$soalId] ?? null;
    if ($teksJwbEssay === null) {
        $essayRes = $conn->query(
            "SELECT teks_jawaban FROM jawaban
             WHERE ujian_id=$ujianId AND peserta_id=$pesertaId AND soal_id=$soalId LIMIT 1"
        );
        $teksJwbEssay = ($essayRes && $essayRes->num_rows > 0)
            ? ($essayRes->fetch_assoc()['teks_jawaban'] ?? '')
            : '';
    }
    $teksEsc   = htmlspecialchars($teksJwbEssay);
    $charCount = mb_strlen($teksJwbEssay);
    $pilihanHtml  = '<div style="background:#f0fdf4;border:1.5px solid #86efac;border-radius:10px;padding:10px 14px;margin-bottom:12px;font-size:13px;color:#166534;font-weight:600">'
        . '<i class="bi bi-pencil-square me-1"></i>Soal Uraian — Tulis jawaban Anda di bawah ini.</div>';
    $pilihanHtml .= "<textarea id=\"essayJawaban\" class=\"form-control\" rows=\"6\" maxlength=\"3000\""
        . " placeholder=\"Tulis jawaban Anda di sini...\""
        . " style=\"font-size:14px;line-height:1.8;resize:vertical;border-radius:10px;border:2px solid #e2e8f0;padding:12px\""
        . " oninput=\"simpanEssay(this,$soalId)\">$teksEsc</textarea>";
    $pilihanHtml .= "<div class=\"d-flex justify-content-between mt-1 px-1\">"
        . "<span class=\"text-muted\" style=\"font-size:11px\">Maksimal 3000 karakter</span>"
        . "<span id=\"essayCharCount\" style=\"font-size:11px;color:#64748b\">{$charCount}/3000</span></div>";
    $pilihanHtml .= '<div id="essaySaveStatus" class="mt-2" style="font-size:12px;color:#22c55e;display:none"><i class="bi bi-check-circle me-1"></i>Tersimpan</div>';
} else {
    $sessionKey = "pilihan_order_{$soalId}";
    if ($acakPilihan) {
        if (!isset($_SESSION[$sessionKey])) {
            $order   = array_filter(['a','b','c','d'], fn($k) => !empty($soal['pilihan_'.$k]));
            $order   = array_values($order);
            shuffle($order);
            $labels  = ['a','b','c','d'];
            $mapping = [];
            foreach ($order as $i => $asli) $mapping[$labels[$i]] = $asli;
            $_SESSION[$sessionKey] = $mapping;
        }
        $pilihanMapping = $_SESSION[$sessionKey];
        $pilihanLoop    = array_keys($pilihanMapping);
    } else {
        $pilihanMapping = null;
        $pilihanLoop    = ['a','b','c','d'];
    }

    foreach ($pilihanLoop as $hTampil) {
        $hAsli   = $pilihanMapping ? $pilihanMapping[$hTampil] : $hTampil;
        $teks    = $soal['pilihan_'.$hAsli] ?? '';
        $gambarP = $soal['gambar_pilihan_'.$hAsli] ?? '';
        if (!$teks && !$gambarP) continue;
        $sel        = $jwbAktif===$hAsli ? 'selected' : '';
        $teksEsc    = htmlspecialchars($teks);
        $gambarHtml = $gambarP
            ? "<img src=\"{$baseUrl}/assets/uploads/soal/".htmlspecialchars($gambarP)."\" style=\"max-width:180px;max-height:100px;border-radius:6px;display:block;margin-bottom:4px\" alt=\"\">"
            : '';
        $pilihanHtml .= "<div class=\"pilihan-item $sel\" onclick=\"pilihJawaban('$hAsli',this,$soalId)\"><div class=\"huruf-box\">".strtoupper($hTampil)."</div><div class=\"pilihan-teks\">{$gambarHtml}{$teksEsc}</div></div>";
    }
}

// ── 5. Teks bacaan & gambar ───────────────────────────────────
$teksBacaan = '';
if (!empty($soal['teks_bacaan'])) {
    $tb = htmlspecialchars($soal['teks_bacaan']);
    $teksBacaan = "<div style=\"background:#f0f9ff;border-left:4px solid #1a56db;border-radius:0 8px 8px 0;padding:14px 16px;margin-bottom:18px;font-size:14px;line-height:1.8;color:#1e293b;\"><div style=\"font-size:11px;font-weight:700;color:#1a56db;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;\">&#128196; Bacalah teks berikut!</div>".nl2br($tb)."</div>";
}
$gambarHtml = '';
if ($soal['gambar']) {
    $g = htmlspecialchars($soal['gambar']);
    $gambarHtml = "<img src=\"{$baseUrl}/assets/uploads/soal/$g\" class=\"soal-img\" alt=\"Gambar soal\">";
}

// ── 6. Nav buttons dari SESSION cache — ZERO query ───────────
$navBtns = [];
foreach ($_SESSION['soal_order'] as $idx => $sid) {
    $sid = (int)$sid;
    $n   = $idx + 1;
    $cls = '';
    if ($n === $no)                    $cls .= ' current';
    if (isset($jawabans[$sid]))        $cls .= ' answered';
    if (in_array($sid, $raguList))     $cls .= ' ragu';
    $navBtns[] = ['n' => $n, 'cls' => trim($cls)];
}

echo json_encode([
    'ok'          => true,
    'no'          => $no,
    'total'       => $total,
    'soalId'      => $soalId,
    'tipe'        => $soal['tipe_soal'],
    'pertanyaan'  => nl2br(htmlspecialchars($soal['pertanyaan'])),
    'teksBacaan'  => $teksBacaan,
    'gambar'      => $gambarHtml,
    'pilihanHtml' => $pilihanHtml,
    'jwbAktif'    => $jwbAktif,
    'isRagu'      => $isRagu,
    'sdhJawab'    => $sdhJawab,
    'jumlahRagu'  => $jumlahRagu,
    'belumJawab'  => $belumJawab,
    'navBtns'     => $navBtns,
]);
