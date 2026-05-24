<?php
// ============================================================
// admin/ajax_statistik.php — Statistik Realtime (AJAX)
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/helper.php';

function qs($conn, $sql, $col) {
    $r = $conn->query($sql);
    if (!$r) return null;
    $row = $r->fetch_assoc(); $r->free();
    return $row[$col] ?? null;
}

function scoreMatch(string $tipe, string $jawabanSiswa, string $jawabanBenar): bool {
    $tipe = strtolower($tipe);
    if ($tipe === 'essay') return false;
    if ($tipe === 'mcma') {
        $siswa = array_values(array_filter(array_map('trim', explode(',', strtolower($jawabanSiswa)))));
        $kunci = array_values(array_filter(array_map('trim', explode(',', strtolower($jawabanBenar)))));
        $siswa = array_values(array_unique($siswa));
        $kunci = array_values(array_unique($kunci));
        sort($siswa);
        sort($kunci);
        return $siswa === $kunci;
    }
    return strtolower(trim($jawabanSiswa)) === strtolower(trim($jawabanBenar));
}

function hitungSkorSementara(mysqli $conn, array $ujian): array {
    $order = json_decode($ujian['soal_order'] ?? '[]', true);
    if (!is_array($order)) $order = [];

    $soalIds = array_values(array_filter(array_map('intval', $order)));
    $totalSoal = count($soalIds);
    if ($totalSoal === 0) {
        return [
            'benar' => 0,
            'total_soal' => 0,
            'total_soal_pg' => 0,
            'nilai' => 0,
            'jawaban_dijawab' => 0,
            'progress' => 0,
        ];
    }

    $soalMap = [];
    $idList = implode(',', $soalIds);
    $qSoal = $conn->query("SELECT id, tipe_soal, jawaban_benar FROM soal WHERE id IN ($idList)");
    if ($qSoal) while ($row = $qSoal->fetch_assoc()) {
        $soalMap[(int)$row['id']] = $row;
    }

    $jawabanMap = [];
    $ujianId = (int)$ujian['id'];
    $pesertaId = (int)$ujian['peserta_id'];
    $qJawab = $conn->query("SELECT soal_id, jawaban FROM jawaban WHERE ujian_id=$ujianId AND peserta_id=$pesertaId");
    if ($qJawab) while ($row = $qJawab->fetch_assoc()) {
        $jawabanMap[(int)$row['soal_id']] = $row['jawaban'] ?? '';
    }

    $benar = 0;
    $essay = 0;
    foreach ($soalIds as $soalId) {
        $soal = $soalMap[$soalId] ?? null;
        if (!$soal) continue;
        if (($soal['tipe_soal'] ?? '') === 'essay') {
            $essay++;
            continue;
        }
        $jawabanSiswa = $jawabanMap[$soalId] ?? '';
        if ($jawabanSiswa === '') continue;
        if (scoreMatch((string)($soal['tipe_soal'] ?? ''), $jawabanSiswa, (string)($soal['jawaban_benar'] ?? ''))) {
            $benar++;
        }
    }

    $totalSoalPg = max(1, $totalSoal - $essay);
    $nilai = round(($benar / $totalSoalPg) * 100, 2);
    $jawabanDijawab = count($jawabanMap);
    $progress = (int)round(($jawabanDijawab / max(1, $totalSoal)) * 100);

    return [
        'benar' => $benar,
        'total_soal' => $totalSoal,
        'total_soal_pg' => $totalSoalPg,
        'nilai' => $nilai,
        'jawaban_dijawab' => $jawabanDijawab,
        'progress' => $progress,
    ];
}

$pesertaUjian   = (int) qs($conn, "SELECT COUNT(*) AS c FROM ujian WHERE waktu_selesai IS NULL AND waktu_mulai IS NOT NULL", 'c');
$pesertaSelesai = (int) qs($conn, "SELECT COUNT(*) AS c FROM ujian WHERE waktu_selesai IS NOT NULL AND DATE(waktu_selesai)=CURDATE()", 'c');
$pesertaOnline  = (int) qs($conn, "SELECT COUNT(*) AS c FROM ujian WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)", 'c');
$totalPeserta   = (int) qs($conn, "SELECT COUNT(*) AS c FROM peserta", 'c');
$totalSekolah   = (int) qs($conn, "SELECT COUNT(*) AS c FROM sekolah", 'c');
$nilaiRata      = (float)(qs($conn, "SELECT ROUND(AVG(nilai),1) AS r FROM ujian WHERE waktu_selesai IS NOT NULL AND DATE(waktu_selesai)=CURDATE()", 'r') ?? 0);
$terakhirSelesai = qs($conn, "SELECT MAX(waktu_selesai) AS t FROM ujian WHERE waktu_selesai IS NOT NULL", 't');

// Peserta yang sedang ujian, termasuk sekolah, kelas, dan skor sementara
$sedangDetail = [];
$qSedang = $conn->query(
    "SELECT u.id, u.peserta_id, u.jadwal_id, u.waktu_mulai, u.last_activity, u.soal_order,
            p.nama, p.kelas, p.kode_peserta, p.kode_sekolah, s.nama_sekolah,
            jd.kategori_id, k.nama_kategori
     FROM ujian u
     JOIN peserta p ON p.id = u.peserta_id
     LEFT JOIN jadwal_ujian jd ON jd.id = u.jadwal_id
     LEFT JOIN kategori_soal k ON k.id = jd.kategori_id
     LEFT JOIN sekolah s ON s.id = p.sekolah_id
     WHERE u.waktu_selesai IS NULL AND u.waktu_mulai IS NOT NULL
    ORDER BY IFNULL(u.last_activity, u.waktu_mulai) DESC"
);
if ($qSedang) while ($u = $qSedang->fetch_assoc()) {
    $skor = hitungSkorSementara($conn, $u);
    $sedangDetail[] = [
        'nama' => $u['nama'],
        'sekolah' => $u['nama_sekolah'] ?? '-',
        'kelas' => $u['kelas'] ?? '-',
        'kode_peserta' => $u['kode_peserta'] ?? '-',
        'kode_sekolah' => $u['kode_sekolah'] ?? '-',
        'jadwal_id' => $u['jadwal_id'] ?? null,
        'kategori_id' => $u['kategori_id'] ?? null,
        'nama_kategori' => $u['nama_kategori'] ?? '-',
        'mulai' => $u['waktu_mulai'],
        'last_activity' => $u['last_activity'] ?? $u['waktu_mulai'],
        'nilai_sementara' => $skor['nilai'],
        'progress' => $skor['progress'],
        'benar' => $skor['benar'],
        'jawaban_dijawab' => $skor['jawaban_dijawab'],
        'total_soal' => $skor['total_soal'],
    ];
}
usort($sedangDetail, function ($a, $b) {
    $scoreB = (float)($b['nilai_sementara'] ?? 0);
    $scoreA = (float)($a['nilai_sementara'] ?? 0);
    if ($scoreB !== $scoreA) return $scoreB <=> $scoreA;
    return strtotime($b['last_activity'] ?? $b['mulai'] ?? '0') <=> strtotime($a['last_activity'] ?? $a['mulai'] ?? '0');
});

// Peserta yang baru selesai (1 jam terakhir)
$baruSelesai = [];
$res = $conn->query(
    "SELECT p.nama, p.kode_sekolah, s.nama_sekolah, u.nilai
     FROM ujian u
     JOIN peserta p ON p.id=u.peserta_id
     LEFT JOIN sekolah s ON s.id=p.sekolah_id
    WHERE u.waktu_selesai >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
     ORDER BY u.waktu_selesai DESC LIMIT 5"
);
if ($res) while ($r = $res->fetch_assoc()) {
    $baruSelesai[] = [
        'nama'     => $r['nama'],
        'kode_sekolah' => $r['kode_sekolah'] ?? '-',
        'sekolah'  => $r['nama_sekolah'] ?? '-',
        'nilai'    => $r['nilai'],
    ];
}

// Hasil final terbaru 1 jam terakhir
$hasilFinal = [];
$qFinal = $conn->query(
    "SELECT u.nilai, u.waktu_selesai, u.waktu_mulai, u.jadwal_id,
            p.nama, p.kelas, p.kode_peserta, p.kode_sekolah, s.nama_sekolah,
            jd.kategori_id, k.nama_kategori
     FROM ujian u
     JOIN peserta p ON p.id = u.peserta_id
     LEFT JOIN jadwal_ujian jd ON jd.id = u.jadwal_id
     LEFT JOIN kategori_soal k ON k.id = jd.kategori_id
     LEFT JOIN sekolah s ON s.id = p.sekolah_id
    WHERE DATE(u.waktu_selesai) = CURDATE()
     ORDER BY CAST(u.nilai AS DECIMAL(10,2)) DESC, u.waktu_selesai ASC"
);
if ($qFinal) {
    $rank = 1;
    while ($r = $qFinal->fetch_assoc()) {
    $hasilFinal[] = [
        'rank' => $rank++,
        'nama' => $r['nama'],
        'sekolah' => $r['nama_sekolah'] ?? '-',
        'kelas' => $r['kelas'] ?? '-',
        'kode_peserta' => $r['kode_peserta'] ?? '-',
        'kode_sekolah' => $r['kode_sekolah'] ?? '-',
        'jadwal_id' => $r['jadwal_id'] ?? null,
        'kategori_id' => $r['kategori_id'] ?? null,
        'nama_kategori' => $r['nama_kategori'] ?? '-',
        'nilai' => $r['nilai'],
        'selesai' => $r['waktu_selesai'],
        'mulai' => $r['waktu_mulai'],
    ];
    }
}
usort($hasilFinal, function ($a, $b) {
    $scoreB = (float)($b['nilai'] ?? 0);
    $scoreA = (float)($a['nilai'] ?? 0);
    if ($scoreB !== $scoreA) return $scoreB <=> $scoreA;
    return strtotime($a['selesai'] ?? '0') <=> strtotime($b['selesai'] ?? '0');
});
foreach ($hasilFinal as $idx => &$row) {
    $row['rank'] = $idx + 1;
}
unset($row);

$overlayVisible = $pesertaUjian > 0;
if (!$overlayVisible && $pesertaSelesai > 0) {
    $overlayVisible = true;
}
$overlayMode = $pesertaUjian > 0 ? 'live' : ($overlayVisible ? 'recent' : 'hidden');
$overlayLabel = $overlayMode === 'live' ? 'LIVE' : 'AKTIVITAS TERAKHIR';

jsonResponse([
    'peserta_online'  => $pesertaOnline,
    'peserta_ujian'   => $pesertaUjian,
    'peserta_selesai' => $pesertaSelesai,
    'total_peserta'   => $totalPeserta,
    'total_sekolah'   => $totalSekolah,
    'nilai_rata'      => $nilaiRata,
    'overlay_visible' => $overlayVisible,
    'overlay_mode'    => $overlayMode,
    'overlay_label'   => $overlayLabel,
    'terakhir_selesai'=> $terakhirSelesai,
    'sedang_detail'   => $sedangDetail,
    'hasil_final'     => $hasilFinal,
    'baru_selesai'    => $baruSelesai,
    'timestamp'       => date('H:i:s'),
]);
