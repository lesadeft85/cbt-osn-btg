<?php
// ============================================================
// korektor/koreksi.php — Halaman Koreksi Essay per Peserta
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireKorektor();

// ── Fungsi recalc (sama dengan admin/nilai_essay.php) ────────
function recalcEssayNilai($conn, int $ujianId): void {
    $uRes = $conn->query("SELECT soal_order FROM ujian WHERE id=$ujianId LIMIT 1");
    if (!$uRes || $uRes->num_rows === 0) return;
    $uRow      = $uRes->fetch_assoc();
    $soalOrder = json_decode($uRow['soal_order'] ?? '[]', true) ?: [];
    if (empty($soalOrder)) return;
    $idsStr = implode(',', array_map('intval', $soalOrder));

    $res = $conn->query(
        "SELECT s.id AS soal_id, s.essay_bobot,
                j.skor_essay, j.id AS jawaban_id
         FROM soal s
         LEFT JOIN jawaban j ON j.soal_id = s.id AND j.ujian_id = $ujianId
         WHERE s.id IN ($idsStr) AND s.tipe_soal = 'essay'"
    );
    if (!$res) return;

    $totalBobot = $totalSkor = $jmlEssayTotal = 0;
    $semuaDinilai = true;

    while ($r = $res->fetch_assoc()) {
        $bobot = max(1, (int)($r['essay_bobot'] ?? 10));
        $totalBobot += $bobot;
        $jmlEssayTotal++;
        if ($r['jawaban_id'] === null || $r['skor_essay'] === null) {
            $semuaDinilai = false;
        } else {
            $totalSkor += (float)$r['skor_essay'];
        }
    }
    $res->free();
    if ($totalBobot === 0 || $jmlEssayTotal === 0) return;

    if ($semuaDinilai) {
        $nilaiEssay = round(($totalSkor / $totalBobot) * 100, 2);
        $hRes = $conn->query("SELECT jml_benar, total_soal FROM hasil_ujian WHERE ujian_id=$ujianId LIMIT 1");
        if ($hRes && $hRes->num_rows > 0) {
            $h         = $hRes->fetch_assoc();
            $totalSoal = max(1, (int)$h['total_soal']);
            $jmlPg     = max(0, $totalSoal - $jmlEssayTotal);
            $nilaiPg   = ($jmlPg > 0) ? round(((int)$h['jml_benar'] / $jmlPg) * 100, 2) : 0;
            if ($jmlPg > 0 && $jmlEssayTotal > 0) {
                $nilaiAkhir = round(($nilaiPg * $jmlPg + $nilaiEssay * $jmlEssayTotal) / $totalSoal, 2);
            } elseif ($jmlEssayTotal === 0) {
                $nilaiAkhir = $nilaiPg;
            } else {
                $nilaiAkhir = $nilaiEssay;
            }
            $conn->query("UPDATE hasil_ujian SET nilai_essay=$nilaiEssay, essay_dinilai=1, nilai=$nilaiAkhir WHERE ujian_id=$ujianId");
            $conn->query("UPDATE ujian SET nilai=$nilaiAkhir WHERE id=$ujianId");
        } else {
            $conn->query("UPDATE hasil_ujian SET nilai_essay=$nilaiEssay, essay_dinilai=1 WHERE ujian_id=$ujianId");
        }
    } else {
        $conn->query("UPDATE hasil_ujian SET essay_dinilai=0 WHERE ujian_id=$ujianId");
    }
}

$ujianId = (int)($_GET['ujian_id'] ?? 0);
if (!$ujianId) { header('Location: ' . BASE_URL . '/korektor/index.php'); exit; }

// ── Simpan filter dari index.php agar bisa kembali ke posisi yg sama ──
$backKelas    = trim($_GET['kelas']      ?? '');
$backRombel   = trim($_GET['rombel']     ?? '');
$backKat      = (int)($_GET['kat']       ?? 0);
$backJadwal   = (int)($_GET['jadwal_id'] ?? 0);
$backStatus   = trim($_GET['status']     ?? 'pending');
$backSekolah  = (int)($_GET['sekolah_id'] ?? 0);

// Bangun URL kembali ke index dengan filter yang sama
$backParams = array_filter([
    'kelas'      => $backKelas,
    'rombel'     => $backRombel,
    'kat'        => $backKat    ?: null,
    'jadwal_id'  => $backJadwal ?: null,
    'status'     => $backStatus,
    'sekolah_id' => $backSekolah ?: null,
]);
$backUrl = BASE_URL . '/korektor/index.php' . ($backParams ? '?' . http_build_query($backParams) : '');

// ── Ambil daftar semua ujian sesuai filter untuk navigasi prev/next ──
$whereNav = "WHERE (h.ada_essay = 1 OR EXISTS (
    SELECT 1 FROM jawaban jx JOIN soal sx ON sx.id = jx.soal_id
    WHERE jx.ujian_id = h.ujian_id AND sx.tipe_soal = 'essay'
))";
if ($backJadwal)  $whereNav .= " AND h.jadwal_id = $backJadwal";
if ($backKat)     $whereNav .= " AND COALESCE(h.kategori_id, j.kategori_id) = $backKat";
if ($backStatus === 'pending') $whereNav .= " AND h.essay_dinilai = 0";
if ($backStatus === 'done')    $whereNav .= " AND h.essay_dinilai = 1";
if ($backSekolah) $whereNav .= " AND p.sekolah_id = $backSekolah";
if ($backKelas !== '') {
    if ($backRombel !== '') {
        $bke = $conn->real_escape_string($backKelas . ' ' . $backRombel);
        $whereNav .= " AND p.kelas = '$bke'";
    } else {
        $bke = $conn->real_escape_string($backKelas);
        $whereNav .= " AND (p.kelas = '$bke' OR p.kelas LIKE '$bke %')";
    }
}
$navRes = $conn->query("
    SELECT h.ujian_id FROM hasil_ujian h
    JOIN peserta p ON p.id = h.peserta_id
    LEFT JOIN jadwal_ujian j ON j.id = h.jadwal_id
    $whereNav
    ORDER BY h.essay_dinilai ASC, h.waktu_selesai DESC
");
$navIds = [];
if ($navRes) while ($nr = $navRes->fetch_assoc()) $navIds[] = (int)$nr['ujian_id'];
$navCurrent = array_search($ujianId, $navIds);
$navPrev    = ($navCurrent !== false && $navCurrent > 0)                  ? $navIds[$navCurrent - 1] : null;
$navNext    = ($navCurrent !== false && $navCurrent < count($navIds) - 1) ? $navIds[$navCurrent + 1] : null;
$navTotal   = count($navIds);
$navPos     = $navCurrent !== false ? $navCurrent + 1 : 1;

// Helper URL navigasi
function navUrl(string $base, int $uid, array $bp): string {
    $p = array_merge($bp, ['ujian_id' => $uid]);
    return $base . '/korektor/koreksi.php?' . http_build_query(array_filter($p));
}

// ── POST: Simpan nilai satu soal ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'nilai') {
    csrfVerify();
    $jawId  = (int)$_POST['jawaban_id'];
    $soalId = (int)$_POST['soal_id'];
    $skor   = max(0, (float)$_POST['skor']);
    $bobot  = max(1, (float)$_POST['bobot']);
    $skor   = min($skor, $bobot);
    $ujId   = (int)$_POST['ujian_id'];

    if ($jawId > 0) {
        $st = $conn->prepare("UPDATE jawaban SET skor_essay=?, dinilai_at=NOW() WHERE id=?");
        $st->bind_param('di', $skor, $jawId);
        $st->execute(); $st->close();
    } else {
        $uRow = $conn->query("SELECT peserta_id FROM ujian WHERE id=$ujId LIMIT 1")->fetch_assoc();
        $pid  = (int)($uRow['peserta_id'] ?? 0);
        if ($pid && $soalId) {
            $conn->query(
                "INSERT INTO jawaban (ujian_id,peserta_id,soal_id,jawaban,teks_jawaban,skor_essay,dinilai_at)
                 VALUES ($ujId,$pid,$soalId,'','', $skor, NOW())
                 ON DUPLICATE KEY UPDATE skor_essay=$skor, dinilai_at=NOW()"
            );
        }
    }
    recalcEssayNilai($conn, $ujId);
    logActivity($conn, 'Korektor Nilai Esai', "Ujian $ujId | Soal $soalId | Skor $skor/$bobot");
    setFlash('success', 'Nilai berhasil disimpan.');
    $filterQuery = http_build_query(array_filter([
        'ujian_id'   => $ujId,
        'kelas'      => trim($_POST['back_kelas']     ?? ''),
        'rombel'     => trim($_POST['back_rombel']    ?? ''),
        'kat'        => (int)($_POST['back_kat']      ?? 0) ?: null,
        'jadwal_id'  => (int)($_POST['back_jadwal']   ?? 0) ?: null,
        'status'     => trim($_POST['back_status']    ?? 'pending'),
        'sekolah_id' => (int)($_POST['back_sekolah']  ?? 0) ?: null,
    ]));
    header('Location: ' . BASE_URL . '/korektor/koreksi.php?' . $filterQuery);
    exit;
}

// ── POST: Selesai penilaian (auto 0 yang belum dinilai) ───────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'selesai_nilai') {
    csrfVerify();
    $ujId    = (int)$_POST['ujian_id'];
    $uPesRes = $conn->query("SELECT peserta_id, soal_order FROM ujian WHERE id=$ujId LIMIT 1");
    if ($uPesRes && $uPesRes->num_rows > 0) {
        $uPesRow   = $uPesRes->fetch_assoc();
        $pidBulk   = (int)$uPesRow['peserta_id'];
        $orderBulk = json_decode($uPesRow['soal_order'] ?? '[]', true) ?: [];
        if (!empty($orderBulk) && $pidBulk) {
            $idsBulk  = implode(',', array_map('intval', $orderBulk));
            $essayRes = $conn->query("SELECT id FROM soal WHERE id IN ($idsBulk) AND tipe_soal='essay'");
            if ($essayRes) while ($eRow = $essayRes->fetch_assoc()) {
                $eid = (int)$eRow['id'];
                $conn->query(
                    "INSERT INTO jawaban (ujian_id,peserta_id,soal_id,jawaban,teks_jawaban,skor_essay,dinilai_at)
                     VALUES ($ujId,$pidBulk,$eid,'','',0,NOW())
                     ON DUPLICATE KEY UPDATE
                       skor_essay=COALESCE(skor_essay,0),
                       dinilai_at=COALESCE(dinilai_at,NOW())"
                );
            }
        }
    }
    $conn->query(
        "UPDATE jawaban j JOIN soal s ON s.id=j.soal_id
         SET j.skor_essay=0, j.dinilai_at=NOW()
         WHERE j.ujian_id=$ujId AND s.tipe_soal='essay' AND j.skor_essay IS NULL"
    );
    recalcEssayNilai($conn, $ujId);
    setFlash('success', 'Penilaian selesai. Jawaban yang belum dinilai diberi skor 0.');
    // Auto-next: cari ujian berikutnya dari daftar filter
    $backSekolahPost = (int)($_POST['back_sekolah'] ?? 0);
    $nextUjian       = (int)($_POST['next_ujian_id'] ?? 0);
    $filterQueryBack = array_filter([
        'kelas'      => trim($_POST['back_kelas']    ?? ''),
        'rombel'     => trim($_POST['back_rombel']   ?? ''),
        'kat'        => (int)($_POST['back_kat']     ?? 0) ?: null,
        'jadwal_id'  => (int)($_POST['back_jadwal']  ?? 0) ?: null,
        'status'     => trim($_POST['back_status']   ?? 'pending'),
        'sekolah_id' => $backSekolahPost ?: null,
    ]);
    if ($nextUjian) {
        // Lanjut ke siswa berikutnya
        $nextParams = array_merge($filterQueryBack, ['ujian_id' => $nextUjian]);
        header('Location: ' . BASE_URL . '/korektor/koreksi.php?' . http_build_query($nextParams));
    } else {
        // Tidak ada siswa berikutnya, kembali ke daftar
        header('Location: ' . BASE_URL . '/korektor/index.php' . ($filterQueryBack ? '?' . http_build_query($filterQueryBack) : ''));
    }
    exit;
}

// ── Ambil data ujian & peserta ────────────────────────────────
$infoRes = $conn->query("
    SELECT h.*, h.nilai AS nilai_akhir, h.essay_dinilai,
           p.nama, p.kelas, p.kode_peserta,
           s2.nama_sekolah, k.nama_kategori,
           j.keterangan AS jadwal_nama
    FROM hasil_ujian h
    JOIN peserta p ON p.id = h.peserta_id
    LEFT JOIN sekolah s2 ON s2.id = p.sekolah_id
    LEFT JOIN jadwal_ujian j ON j.id = h.jadwal_id
    LEFT JOIN kategori_soal k ON k.id = COALESCE(h.kategori_id, j.kategori_id)
    WHERE h.ujian_id = $ujianId LIMIT 1
");
$infoUjian = ($infoRes && $infoRes->num_rows > 0) ? $infoRes->fetch_assoc() : null;
if (!$infoUjian) { header('Location: ' . BASE_URL . '/korektor/index.php'); exit; }

// ── Ambil soal essay + jawaban ────────────────────────────────
$uSoalRes = $conn->query("SELECT soal_order, peserta_id FROM ujian WHERE id=$ujianId LIMIT 1");
$uSoalRow = ($uSoalRes && $uSoalRes->num_rows > 0) ? $uSoalRes->fetch_assoc() : null;
$soalIds  = [];
if ($uSoalRow && $uSoalRow['soal_order']) {
    $soalIds = array_map('intval', json_decode($uSoalRow['soal_order'], true) ?: []);
}
$pesertaId = $uSoalRow ? (int)$uSoalRow['peserta_id'] : 0;

$detailEssay = [];
if (!empty($soalIds)) {
    $idsStr = implode(',', $soalIds);
    $res = $conn->query("
        SELECT j.id AS jawaban_id, j.teks_jawaban, j.skor_essay, j.dinilai_at,
               s.id AS soal_id, s.pertanyaan, s.jawaban_benar AS kunci_jawaban,
               s.pembahasan, s.essay_bobot
        FROM soal s
        LEFT JOIN jawaban j ON j.soal_id=s.id AND j.ujian_id=$ujianId
        WHERE s.id IN ($idsStr) AND s.tipe_soal='essay'
        ORDER BY FIELD(s.id, $idsStr)
    ");
    if ($res) while ($r = $res->fetch_assoc()) $detailEssay[] = $r;
}

$jmlTotal   = count($detailEssay);
$jmlDinilai = count(array_filter($detailEssay, fn($d) => $d['skor_essay'] !== null));
$pctDone    = $jmlTotal > 0 ? round($jmlDinilai / $jmlTotal * 100) : 0;

$namaAplikasi = getSetting($conn, 'nama_aplikasi', 'TKA Kecamatan');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Koreksi Essay — <?= e($namaAplikasi) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --navy:#1e3a8a;--navy-h:#1e40af;--navy-d:#172e6e;--navy-m:#2348a8;
  --navy-light:#eff6ff;--navy-border:#bfdbfe;
  --grn:#16a34a;--grn-bg:#f0fdf4;--grn-br:#bbf7d0;
  --red:#dc2626;--red-bg:#fef2f2;--red-br:#fca5a5;
  --gold:#f59e0b;--gold-bg:#fefce8;--gold-br:#fcd34d;--gold-tx:#92400e;
  --g50:#f8fafc;--g100:#f1f5f9;--g200:#e2e8f0;--g300:#cbd5e1;
  --g400:#94a3b8;--g600:#475569;--g700:#334155;--g800:#1e293b;
}
body{background:var(--g100);font-family:'Plus Jakarta Sans',sans-serif;min-height:100vh;color:var(--g800)}

/* Topbar */
.topbar{background:linear-gradient(90deg,var(--navy-m),var(--navy));padding:0 20px;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 2px 10px rgba(30,58,138,.2)}
.topbar-left{display:flex;align-items:center;gap:10px}
.btn-back{background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);color:#fff;border-radius:8px;padding:5px 13px;font-size:12px;font-weight:700;text-decoration:none;display:flex;align-items:center;gap:5px;transition:background .15s}
.btn-back:hover{background:rgba(255,255,255,.28);color:#fff}
.topbar-title{font-size:15px;font-weight:800;color:#fff}
.topbar-right{display:flex;align-items:center;gap:10px}
.btn-logout{background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);color:#fff;border-radius:8px;padding:5px 13px;font-size:12px;font-weight:700;text-decoration:none}
.btn-logout:hover{background:rgba(255,255,255,.28);color:#fff}

/* Progress nav bar */
.nav-bar{background:#fff;border-bottom:1.5px solid var(--navy-border);padding:10px 20px;display:flex;align-items:center;gap:14px;position:sticky;top:56px;z-index:99;box-shadow:0 2px 6px rgba(30,58,138,.07);flex-wrap:wrap}
.nav-progress{display:flex;align-items:center;gap:10px;flex:1;min-width:0}
.pb-lbl{font-size:12px;font-weight:700;color:var(--g600);white-space:nowrap}
.pb-track{flex:1;height:8px;background:var(--g200);border-radius:4px;overflow:hidden;min-width:60px}
.pb-fill{height:100%;border-radius:4px;background:var(--grn);transition:width .4s}
.pb-pct{font-size:12px;font-weight:800;color:var(--grn);white-space:nowrap}
.nav-siswa{display:flex;align-items:center;gap:8px;flex-shrink:0}
.nav-pos{font-size:12px;font-weight:800;color:var(--navy);background:var(--navy-light);border:1px solid var(--navy-border);border-radius:20px;padding:3px 12px;white-space:nowrap}
.btn-nav{display:inline-flex;align-items:center;gap:5px;padding:5px 13px;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none;border:1.5px solid var(--g200);background:#fff;color:var(--g600);transition:all .15s;white-space:nowrap}
.btn-nav:hover{background:var(--navy-light);border-color:var(--navy-border);color:var(--navy)}
.btn-nav.disabled{opacity:.35;pointer-events:none}
.btn-nav.next{background:var(--navy);color:#fff;border-color:var(--navy)}
.btn-nav.next:hover{background:var(--navy-h)}

.wrap{max-width:780px;margin:0 auto;padding:20px 16px 80px}

/* Info peserta */
.peserta-card{background:#fff;border-radius:12px;padding:14px 20px;box-shadow:0 2px 10px rgba(30,58,138,.07);margin-bottom:16px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;border:1.5px solid var(--navy-border)}
.avatar{width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,var(--navy-m),var(--navy));color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:900;flex-shrink:0}
.peserta-nama{font-size:16px;font-weight:900;color:var(--g800)}
.peserta-meta{font-size:12px;color:var(--g400);margin-top:2px;display:flex;align-items:center;flex-wrap:wrap;gap:4px}
.kode-chip{font-family:'Courier New',monospace;background:var(--navy-light);color:var(--navy);font-size:11px;font-weight:700;padding:1px 7px;border-radius:5px;border:1px solid var(--navy-border)}
.status-badge{margin-left:auto;padding:5px 14px;border-radius:20px;font-size:12px;font-weight:800;white-space:nowrap}
.status-badge.done{background:var(--grn-bg);color:#166534;border:1px solid var(--grn-br)}
.status-badge.pending{background:var(--gold-bg);color:var(--gold-tx);border:1px solid var(--gold-br)}

/* Soal card */
.soal-card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(30,58,138,.06);margin-bottom:14px;overflow:hidden;border:1.5px solid var(--g200)}
.soal-card.dinilai{border-color:var(--grn-br);border-left:4px solid var(--grn)}
.soal-card.belum-dinilai{border-color:var(--gold-br);border-left:4px solid var(--gold)}
.soal-header{padding:12px 16px;display:flex;align-items:center;gap:10px;border-bottom:1px solid var(--g100);background:var(--g50)}
.soal-no{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:900;flex-shrink:0}
.soal-no.dinilai{background:var(--grn-bg);color:#166534}
.soal-no.belum{background:var(--gold-bg);color:var(--gold-tx)}
.soal-header-txt{flex:1;font-size:13px;font-weight:700;color:var(--g800);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.skor-badge{font-size:12px;font-weight:800;padding:3px 10px;border-radius:12px;flex-shrink:0}
.skor-badge.ok{background:var(--grn-bg);color:#166534;border:1px solid var(--grn-br)}
.skor-badge.pending{background:var(--g100);color:var(--g400)}

.soal-body{padding:16px}
.section-label{font-size:10px;font-weight:800;color:var(--g400);text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px;display:flex;align-items:center;gap:4px}
.pertanyaan-box{background:var(--g50);border-radius:9px;padding:12px 14px;font-size:13.5px;line-height:1.8;color:var(--g800);margin-bottom:12px;border:1px solid var(--g200)}
.kunci-box{background:var(--navy-light);border-left:4px solid var(--navy);border-radius:0 9px 9px 0;padding:10px 14px;font-size:13px;line-height:1.7;margin-bottom:12px;color:var(--g800)}
.pembahasan-box{background:var(--grn-bg);border-left:4px solid var(--grn);border-radius:0 9px 9px 0;padding:10px 14px;font-size:13px;line-height:1.7;margin-bottom:12px;color:var(--g800)}
.jawaban-box{border:1.5px solid var(--g200);border-radius:9px;padding:12px 14px;font-size:13.5px;line-height:1.8;min-height:70px;white-space:pre-wrap;color:var(--g800);background:#fffdf0;margin-bottom:14px}
.jawaban-box.kosong{background:var(--g50);color:var(--g400);font-style:italic}

/* Form skor */
.skor-form{display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap}
.skor-input-wrap{display:flex;align-items:center;gap:8px}
.skor-input{width:90px;border:2px solid var(--g200);border-radius:8px;padding:8px 12px;font-size:18px;font-weight:900;text-align:center;outline:none;transition:border .15s;color:var(--navy)}
.skor-input:focus{border-color:var(--navy);background:var(--navy-light)}
.skor-max{font-size:13px;color:var(--g400);font-weight:600}
.btn-simpan{background:var(--navy);color:#fff;border:none;border-radius:8px;padding:9px 22px;font-size:13px;font-weight:800;cursor:pointer;transition:background .15s;display:flex;align-items:center;gap:6px;font-family:inherit}
.btn-simpan:hover{background:var(--navy-h)}
.btn-simpan.update{background:var(--gold-tx)}
.btn-simpan.update:hover{background:#b45309}
.quick-btns{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;align-items:center}
.quick-btn{background:var(--g50);border:1.5px solid var(--g200);border-radius:6px;padding:4px 12px;font-size:12px;font-weight:800;cursor:pointer;color:var(--g600);transition:all .15s}
.quick-btn:hover{background:var(--navy);color:#fff;border-color:var(--navy)}
.dinilai-info{font-size:11px;color:var(--g400);margin-top:6px;display:flex;align-items:center;gap:4px}

/* Selesai card */
.selesai-card{background:#fff;border-radius:12px;padding:18px 20px;box-shadow:0 2px 10px rgba(30,58,138,.07);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-top:8px;border:1.5px solid var(--g200)}
.selesai-info{font-size:13px;color:var(--g600);flex:1}
.selesai-info strong{display:block;font-size:15px;color:var(--g800);margin-bottom:2px}
.selesai-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.btn-selesai{background:var(--grn);color:#fff;border:none;border-radius:10px;padding:10px 24px;font-size:13.5px;font-weight:800;cursor:pointer;transition:background .15s;display:flex;align-items:center;gap:7px;font-family:inherit}
.btn-selesai:hover{background:#15803d}
.btn-selesai.next-selesai{background:var(--navy)}
.btn-selesai.next-selesai:hover{background:var(--navy-h)}

@media(max-width:540px){
  .nav-bar{gap:8px}
  .nav-progress{min-width:100%;order:2}
  .nav-siswa{order:1;width:100%;justify-content:space-between}
}
</style>
</head>
<body>

<!-- Topbar -->
<div class="topbar">
  <div class="topbar-left">
    <a href="<?= $backUrl ?>" class="btn-back">
      <i class="bi bi-arrow-left"></i> Daftar
    </a>
    <span class="topbar-title">Koreksi Essay</span>
  </div>
  <div class="topbar-right">
    <span style="color:rgba(255,255,255,.7);font-size:12px"><?= e($_SESSION['nama'] ?? '') ?></span>
    <a href="<?= BASE_URL ?>/logout.php" class="btn-logout">
      <i class="bi bi-box-arrow-right"></i> Keluar
    </a>
  </div>
</div>

<!-- Nav bar: progress + prev/next -->
<div class="nav-bar">
  <div class="nav-progress">
    <span class="pb-lbl">Soal dinilai</span>
    <div class="pb-track"><div class="pb-fill" style="width:<?= $pctDone ?>%"></div></div>
    <span class="pb-pct"><?= $jmlDinilai ?>/<?= $jmlTotal ?> (<?= $pctDone ?>%)</span>
  </div>
  <div class="nav-siswa">
    <?php if ($navTotal > 0): ?>
    <span class="nav-pos">Siswa <?= $navPos ?> / <?= $navTotal ?></span>
    <?php endif; ?>
    <?php if ($navPrev): ?>
    <a href="<?= navUrl(BASE_URL, $navPrev, $backParams) ?>" class="btn-nav">
      <i class="bi bi-chevron-left"></i> Sebelumnya
    </a>
    <?php else: ?>
    <span class="btn-nav disabled"><i class="bi bi-chevron-left"></i> Sebelumnya</span>
    <?php endif; ?>
    <?php if ($navNext): ?>
    <a href="<?= navUrl(BASE_URL, $navNext, $backParams) ?>" class="btn-nav next">
      Berikutnya <i class="bi bi-chevron-right"></i>
    </a>
    <?php else: ?>
    <span class="btn-nav disabled">Berikutnya <i class="bi bi-chevron-right"></i></span>
    <?php endif; ?>
  </div>
</div>

<div class="wrap">

  <?= renderFlash() ?>

  <!-- Info peserta -->
  <div class="peserta-card">
    <div class="avatar"><?= mb_strtoupper(mb_substr($infoUjian['nama'], 0, 2)) ?></div>
    <div style="flex:1;min-width:0">
      <div class="peserta-nama"><?= e($infoUjian['nama']) ?></div>
      <div class="peserta-meta">
        <i class="bi bi-building" style="font-size:11px"></i>
        <?= e($infoUjian['nama_sekolah'] ?? '-') ?>
        <span style="color:var(--g300)">·</span>
        Kelas <?= e($infoUjian['kelas'] ?? '-') ?>
        <span class="kode-chip"><?= e($infoUjian['kode_peserta']) ?></span>
        <?php if ($infoUjian['nama_kategori']): ?>
        <span style="color:var(--g300)">·</span>
        <?= e($infoUjian['nama_kategori']) ?>
        <?php endif; ?>
      </div>
    </div>
    <span class="status-badge <?= $infoUjian['essay_dinilai'] ? 'done' : 'pending' ?>">
      <?= $infoUjian['essay_dinilai'] ? '✅ Selesai Dinilai' : '⏳ Belum Selesai' ?>
    </span>
  </div>

  <?php if (empty($detailEssay)): ?>
  <div class="soal-card" style="padding:40px;text-align:center;color:#94a3b8">
    <i class="bi bi-inbox" style="font-size:36px;display:block;margin-bottom:10px"></i>
    Peserta ini tidak memiliki soal essay.
  </div>
  <?php else: ?>

  <!-- Daftar soal essay -->
  <?php foreach ($detailEssay as $idx => $d):
    $sudahDinilai = $d['skor_essay'] !== null;
    $bobot = max(1, (int)($d['essay_bobot'] ?? 10));
    $singkat = mb_substr(strip_tags($d['pertanyaan']), 0, 60) . (mb_strlen($d['pertanyaan']) > 60 ? '…' : '');
    $quickScores = [0, round($bobot * 0.25), round($bobot * 0.5), round($bobot * 0.75), $bobot];
    $quickScores = array_unique($quickScores);
    $formId = 'form_soal_' . $idx;
  ?>
  <div class="soal-card <?= $sudahDinilai ? 'dinilai' : 'belum-dinilai' ?>" id="soal-<?= $idx ?>">
    <div class="soal-header">
      <span class="soal-no <?= $sudahDinilai ? 'dinilai' : 'belum' ?>"><?= $idx + 1 ?></span>
      <span class="soal-header-txt"><?= e($singkat) ?></span>
      <span class="skor-badge <?= $sudahDinilai ? 'ok' : 'pending' ?>">
        <?= $sudahDinilai ? $d['skor_essay'] . '/' . $bobot : 'Belum dinilai' ?>
      </span>
    </div>
    <div class="soal-body">

      <!-- Pertanyaan -->
      <div class="section-label">Pertanyaan</div>
      <div class="pertanyaan-box"><?= nl2br(e($d['pertanyaan'])) ?></div>

      <!-- Kunci jawaban -->
      <?php if ($d['kunci_jawaban']): ?>
      <div class="section-label">Kunci / Rubrik Jawaban</div>
      <div class="kunci-box"><?= nl2br(e($d['kunci_jawaban'])) ?></div>
      <?php endif; ?>

      <!-- Pembahasan -->
      <?php if (!empty($d['pembahasan'])): ?>
      <div class="section-label"><i class="bi bi-lightbulb-fill"></i> Pembahasan</div>
      <div class="pembahasan-box"><?= nl2br(e($d['pembahasan'])) ?></div>
      <?php endif; ?>

      <!-- Jawaban peserta -->
      <div class="section-label">Jawaban Peserta</div>
      <?php if ($d['teks_jawaban']): ?>
      <div class="jawaban-box"><?= e($d['teks_jawaban']) ?></div>
      <?php else: ?>
      <div class="jawaban-box kosong"><i class="bi bi-dash-circle me-1"></i>Tidak dijawab</div>
      <?php endif; ?>

      <!-- Form penilaian -->
      <form method="POST" id="<?= $formId ?>">
        <?= csrfField() ?>
        <input type="hidden" name="aksi"       value="nilai">
        <input type="hidden" name="jawaban_id" value="<?= $d['jawaban_id'] ?? 0 ?>">
        <input type="hidden" name="soal_id"    value="<?= $d['soal_id'] ?>">
        <input type="hidden" name="ujian_id"   value="<?= $ujianId ?>">
        <input type="hidden" name="bobot"      value="<?= $bobot ?>">
        <input type="hidden" name="back_kelas"    value="<?= e($backKelas) ?>">
        <input type="hidden" name="back_rombel"   value="<?= e($backRombel) ?>">
        <input type="hidden" name="back_kat"      value="<?= $backKat ?>">
        <input type="hidden" name="back_jadwal"   value="<?= $backJadwal ?>">
        <input type="hidden" name="back_status"   value="<?= e($backStatus) ?>">
        <input type="hidden" name="back_sekolah"  value="<?= $backSekolah ?>">

        <div class="skor-form">
          <div>
            <div class="section-label">Skor (0 – <?= $bobot ?>)</div>
            <div class="skor-input-wrap">
              <input type="number" name="skor" class="skor-input"
                     id="skor_<?= $idx ?>"
                     min="0" max="<?= $bobot ?>" step="0.5" required
                     value="<?= $d['skor_essay'] ?? '' ?>"
                     placeholder="0">
              <span class="skor-max">/ <?= $bobot ?> poin</span>
            </div>
          </div>
          <button type="submit" class="btn-simpan <?= $sudahDinilai ? 'update' : '' ?>">
            <i class="bi bi-save"></i>
            <?= $sudahDinilai ? 'Perbarui' : 'Simpan' ?>
          </button>
        </div>

        <!-- Tombol skor cepat -->
        <?php if ($d['teks_jawaban']): ?>
        <div class="quick-btns">
          <span style="font-size:11px;color:#94a3b8;align-self:center">Cepat:</span>
          <?php foreach ($quickScores as $qs): ?>
          <button type="button" class="quick-btn"
                  onclick="document.getElementById('skor_<?= $idx ?>').value='<?= $qs ?>'">
            <?= $qs ?>
          </button>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($sudahDinilai && $d['dinilai_at']): ?>
        <div class="dinilai-info">
          <i class="bi bi-clock me-1"></i>
          Dinilai: <?= date('d/m/Y H:i', strtotime($d['dinilai_at'])) ?>
        </div>
        <?php endif; ?>
      </form>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- Tombol selesai penilaian -->
  <div class="selesai-card">
    <div class="selesai-info">
      <strong>Tandai Penilaian Selesai</strong>
      Jawaban yang belum dinilai akan otomatis diberi skor 0.
    </div>
    <div class="selesai-actions">
      <form method="POST" onsubmit="return confirm('Tandai semua penilaian essay peserta ini selesai?')">
        <?= csrfField() ?>
        <input type="hidden" name="aksi"          value="selesai_nilai">
        <input type="hidden" name="ujian_id"      value="<?= $ujianId ?>">
        <input type="hidden" name="next_ujian_id" value="0">
        <input type="hidden" name="back_kelas"    value="<?= e($backKelas) ?>">
        <input type="hidden" name="back_rombel"   value="<?= e($backRombel) ?>">
        <input type="hidden" name="back_kat"      value="<?= $backKat ?>">
        <input type="hidden" name="back_jadwal"   value="<?= $backJadwal ?>">
        <input type="hidden" name="back_status"   value="<?= e($backStatus) ?>">
        <input type="hidden" name="back_sekolah"  value="<?= $backSekolah ?>">
        <button type="submit" class="btn-selesai">
          <i class="bi bi-check-circle-fill"></i>
          Selesai & Kembali ke Daftar
        </button>
      </form>
      <?php if ($navNext): ?>
      <form method="POST" onsubmit="return confirm('Tandai selesai lalu lanjut ke siswa berikutnya?')">
        <?= csrfField() ?>
        <input type="hidden" name="aksi"          value="selesai_nilai">
        <input type="hidden" name="ujian_id"      value="<?= $ujianId ?>">
        <input type="hidden" name="next_ujian_id" value="<?= $navNext ?>">
        <input type="hidden" name="back_kelas"    value="<?= e($backKelas) ?>">
        <input type="hidden" name="back_rombel"   value="<?= e($backRombel) ?>">
        <input type="hidden" name="back_kat"      value="<?= $backKat ?>">
        <input type="hidden" name="back_jadwal"   value="<?= $backJadwal ?>">
        <input type="hidden" name="back_status"   value="<?= e($backStatus) ?>">
        <input type="hidden" name="back_sekolah"  value="<?= $backSekolah ?>">
        <button type="submit" class="btn-selesai next-selesai">
          <i class="bi bi-arrow-right-circle-fill"></i>
          Selesai & Siswa Berikutnya
        </button>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <?php endif; ?>
</div>

<script>
// Auto-scroll ke soal pertama yang belum dinilai
document.addEventListener('DOMContentLoaded', () => {
    const belum = document.querySelector('.soal-no.belum');
    if (belum) {
        setTimeout(() => belum.closest('.soal-card')?.scrollIntoView({ behavior:'smooth', block:'start' }), 300);
    }
});

// Keyboard shortcut: Alt+← prev, Alt+→ next
document.addEventListener('keydown', e => {
    if (!e.altKey) return;
    <?php if ($navPrev): ?>
    if (e.key === 'ArrowLeft') { e.preventDefault(); location.href = '<?= navUrl(BASE_URL, $navPrev, $backParams) ?>'; }
    <?php endif; ?>
    <?php if ($navNext): ?>
    if (e.key === 'ArrowRight') { e.preventDefault(); location.href = '<?= navUrl(BASE_URL, $navNext, $backParams) ?>'; }
    <?php endif; ?>
});
</script>
</body>
</html>
