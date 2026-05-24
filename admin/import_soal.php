<?php
// ============================================================
// admin/import_soal.php  — Import Soal dari Excel (.xls/.xlsx)
// Kolom: kategori | pertanyaan | pilihan_a | pilihan_b |
//         pilihan_c | pilihan_d | jawaban_benar | tipe_soal (opsional: pg/bs/mcma)
// Untuk MCMA: jawaban_benar = "a,b" atau "ab" (huruf digabung)
// Untuk BS  : jawaban_benar = "benar" atau "salah", pilihan_c/d kosong
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../vendor/simplexlsx/SimpleXLSX.php';
requireLogin('admin_kecamatan');

/* ── Semua kategori untuk lookup nama → id ─────────────────── */
$katRes = $conn->query("SELECT id, nama_kategori FROM kategori_soal ORDER BY nama_kategori");
$katArr = [];   // nama lowercase → id
$katById = [];  // id → nama
if ($katRes) while ($k = $katRes->fetch_assoc()) {
    $katArr[strtolower(trim($k['nama_kategori']))] = (int)$k['id'];
    $katById[(int)$k['id']] = $k['nama_kategori'];
}

/* ── Kategori untuk dropdown filter ────────────────────────── */
$results   = null;
$errors    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrfVerify();
    /* ── Validasi file ───────────────────────────────────────── */
    if (empty($_FILES['file_excel']['name']) || $_FILES['file_excel']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File Excel wajib dipilih dan berhasil diupload.';
    } else {
        $origName = $_FILES['file_excel']['name'];
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $maxSize  = 5 * 1024 * 1024; // 5 MB

        if (!in_array($ext, ['xls', 'xlsx', 'csv'])) {
            $errors[] = 'Format file harus <strong>.xlsx</strong>, <strong>.xls</strong>, atau <strong>.csv</strong>.';
        } elseif ($_FILES['file_excel']['size'] > $maxSize) {
            $errors[] = 'Ukuran file maksimal 5 MB.';
        }
    }

    /* ── Pilihan kategori default (opsional) ─────────────────── */
    $defaultKatId = (int)($_POST['default_kategori_id'] ?? 0);

    if (!$errors) {
        $tmpFile = $_FILES['file_excel']['tmp_name'];
        $rows    = [];

        if ($ext === 'csv') {
            // Handle CSV
            if (($handle = fopen($tmpFile, "r")) !== FALSE) {
                // Deteksi BOM UTF-8
                $bom = fread($handle, 3);
                if ($bom !== "\xEF\xBB\xBF") {
                    rewind($handle);
                }
                while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
                    $rows[] = $data;
                }
                // Jika hanya 1 kolom, mungkin pemisahnya titik koma (;)
                if (count($rows) > 0 && count($rows[0]) === 1 && (strpos($rows[0][0], ';') !== false)) {
                    rewind($handle);
                    if ($bom === "\xEF\xBB\xBF") fread($handle, 3);
                    $rows = [];
                    while (($data = fgetcsv($handle, 10000, ";")) !== FALSE) {
                        $rows[] = $data;
                    }
                }
                fclose($handle);
            }
            if (empty($rows)) {
                $errors[] = 'File CSV kosong atau tidak bisa dibaca.';
            }
        } else {
            // Handle XLSX
            if ($ext === 'xls') {
                $errors[] = 'Format <strong>.xls</strong> (Excel 97-2003) tidak didukung secara langsung. '
                          . 'Silakan <strong>Save As</strong> file Anda ke format <strong>.xlsx</strong> (Excel Workbook) atau <strong>.csv</strong>.';
            } else {
                if (!class_exists('\ZipArchive') && !extension_loaded('zip')) {
                    $errors[] = 'Ekstensi <strong>zip</strong> (ZipArchive) belum aktif di server Anda. '
                              . 'Silakan gunakan format <strong>.csv</strong> sebagai alternatif, atau aktifkan ekstensi zip di konfigurasi PHP server Anda.';
                } else {
                    $xlsx = SimpleXLSX::parse($tmpFile);
                    if (!$xlsx) {
                        $errors[] = 'File tidak bisa dibaca. Pastikan format <strong>.xlsx</strong> (bukan .xls yang direname) dan tidak terproteksi password.';
                    } else {
                        // Baca sheet pertama (Template Soal) + semua sheet lain
                        // SimpleXLSX hanya punya rows(int) dan sheetsCount() — tidak ada sheetNames()
                        // Deteksi sheet "Soal Bacaan" dari header baris pertama (kolom A = "Grup" atau "A")
                        $rows = $xlsx->rows(0); // Sheet 1: Template Soal (default)
                        $totalSheets = $xlsx->sheetsCount();
                        // Proses SEMUA sheet setelah sheet pertama sebagai Soal Bacaan
                        for ($sIdx = 1; $sIdx < $totalSheets; $sIdx++) {
                            $sheetRows = $xlsx->rows($sIdx);
                            if (empty($sheetRows)) continue;
                            foreach ($sheetRows as &$rb) {
                                while (count($rb) < 12) $rb[] = '';
                                $rb[11] = '__BACAAN__';
                            }
                            unset($rb);
                            $rows = array_merge($rows, $sheetRows);
                        }
                    }
                }
            }
        }

        if (!$errors && !empty($rows)) {
            $berhasil = 0;
            $gagal    = 0;
            $log      = [];

            /* Deteksi baris header — skip baris judul & header kolom */
            // Helper: cek apakah sebuah baris adalah baris header/judul (bukan data soal)
            function isHeaderRow(array $row): bool {
                $r11 = trim($row[11] ?? '');
                // Baris dari sheet Soal Bacaan sudah ditandai — header-nya dideteksi tersendiri
                if ($r11 === '__BACAAN__') {
                    // Header sheet Soal Bacaan: kolom A berisi 'grup' atau angka yang tidak valid
                    // Data valid: kolom A = angka (grup), kolom C = pg/bs/mcma/essay
                    $a = strtolower(trim($row[0] ?? ''));
                    $c = strtolower(trim($row[2] ?? ''));
                    return !is_numeric($a) && !in_array($c, ['pg','bs','mcma','essay']);
                }
                $h0 = strtolower(trim($row[0] ?? ''));
                $h1 = strtolower(trim($row[1] ?? ''));
                $h2 = strtolower(trim($row[2] ?? ''));
                // Baris kosong semua
                if ($h0 === '' && $h1 === '' && $h2 === '') return true;
                // Baris judul panjang (seperti "📋 TEMPLATE IMPORT SOAL...")
                if (mb_strlen($h0) > 20) return true;
                // Baris nama kolom
                // Hanya kata yang pasti tidak akan jadi nilai data
                $headerWords = ['kategori','grup','pertanyaan',
                                'pilihan a','pilihan b','pilihan c','pilihan d',
                                'jawaban benar','teks bacaan','tipe soal','bobot essay'];
                $matchCount = 0;
                foreach ([$h0, $h1, $h2] as $hx) {
                    foreach ($headerWords as $hw) {
                        if (strpos($hx, $hw) !== false) { $matchCount++; break; }
                    }
                }
                return $matchCount >= 2;
            }

            $startRow = 0;
            for ($hi = 0; $hi < min(6, count($rows)); $hi++) {
                while (count($rows[$hi]) < 12) $rows[$hi][] = '';
                if (isHeaderRow($rows[$hi])) {
                    $startRow = $hi + 1;
                } else {
                    break;
                }
            }

            /* Prepare statement */
            $stmt = $conn->prepare(
                "INSERT INTO soal (kategori_id, tipe_soal, pertanyaan, teks_bacaan, pilihan_a, pilihan_b, pilihan_c, pilihan_d, jawaban_benar, essay_bobot)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            // Helper: cocokkan teks jawaban ke huruf pilihan
            function cocokkanJawaban(string $jwbRaw, string $pA, string $pB, string $pC, string $pD): string {
                if (in_array($jwbRaw, ['a','b','c','d'])) return $jwbRaw;
                $pilihanMap = [
                    'a' => strtolower(trim($pA)),
                    'b' => strtolower(trim($pB)),
                    'c' => strtolower(trim($pC)),
                    'd' => strtolower(trim($pD)),
                ];
                $cari = strtolower(trim($jwbRaw));
                foreach ($pilihanMap as $huruf => $teks) {
                    if ($teks !== '' && $teks === $cari) return $huruf;
                }
                foreach ($pilihanMap as $huruf => $teks) {
                    if ($teks !== '' && ((strpos($cari, $teks) !== false) || (strpos($teks, $cari) !== false))) return $huruf;
                }
                return '';
            }

            for ($i = $startRow; $i < count($rows); $i++) {
                $row = $rows[$i];
                // Perpanjang dulu agar isHeaderRow() bisa cek index 11
                while (count($row) < 12) $row[] = '';
                // Skip baris header yang ada di tengah (misalnya header sheet Soal Bacaan)
                if (isHeaderRow($row)) continue;

                /* ── Parsing 9 kolom ─────────────────────────────── */
                // Kolom A: kategori (nama atau ID)
                // Kolom B: teks_bacaan (opsional, kosongkan jika tidak ada)
                // Kolom C: pertanyaan
                // Kolom D: pilihan_a
                // Kolom E: pilihan_b
                // Kolom F: pilihan_c
                // Kolom G: pilihan_d
                // Kolom H: jawaban_benar (a/b/c/d | benar/salah | a,b untuk MCMA)
                // Kolom I: tipe_soal (pg/bs/mcma) — opsional, default pg
                // Kompatibel mundur: jika hanya 8 kolom, dianggap format lama (tanpa teks_bacaan)

                $kolomKat = trim($row[0] ?? '');

                // Deteksi format: lihat kolom I (index 8)
                // Format BARU (9 kolom): kolom I berisi tipe soal (pg/bs/mcma)
                // Format LAMA (8 kolom): kolom I kosong, kolom H berisi tipe soal
                // Deteksi apakah baris dari sheet "Soal Bacaan"
                $isSheetBacaan = ($row[11] === '__BACAAN__');

                // FORMAT PANDUAN: A=Kat, B=Tipe(pg/bs/mcma/essay), C=Pert, D-G=Pilihan, H=Jwb, I=Bobot
                // Format yang dihasilkan export_soal.php
                $kolB = strtolower(trim($row[1] ?? ''));
                $isFormatPanduan = !$isSheetBacaan && in_array($kolB, ['pg','bs','mcma','essay']);

                // FORMAT BARU: A=Kat, B=TeksBacaan, C=Pert, ..., I=Tipe
                $kol9 = strtolower(trim($row[8] ?? ''));
                $isFormatBaru = !$isSheetBacaan && !$isFormatPanduan && (
                    in_array($kol9, ['pg','bs','mcma','essay']) ||
                    strlen(trim($row[1] ?? '')) > 5
                );

                if ($isSheetBacaan) {
                    // Format Soal Bacaan: A=Grup, B=Kat, C=Tipe, D=TeksBacaan, E=Pert, F=pA, G=pB, H=pC, I=pD, J=Jwb, K=Bobot
                    $grupId     = trim($row[0] ?? '');
                    $kolomKat   = trim($row[1] ?? '');
                    $tipe       = strtolower(trim($row[2] ?? 'pg'));
                    $teksBacaan = trim($row[3] ?? '');
                    $pert       = trim($row[4] ?? '');
                    $pA         = trim($row[5] ?? '');
                    $pB         = trim($row[6] ?? '');
                    $pC         = trim($row[7] ?? '');
                    $pD         = trim($row[8] ?? '');
                    $jwbRaw     = strtolower(trim($row[9] ?? ''));
                    $essayBobot = (int)(trim($row[10] ?? '10')) ?: 10;

                    // Propagasi teks bacaan antar soal dalam grup yang sama
                    // Jika kolom A (Grup) kosong, gunakan '__nogroup__' sebagai key default
                    static $grupTeksBacaan = [];
                    $grupKey = ($grupId !== '') ? $grupId : '__nogroup__';
                    $isTeksPlaceholder = (strpos($teksBacaan, '↑') !== false)
                                      || strtolower($teksBacaan) === 'lihat baris atas'
                                      || $teksBacaan === '';
                    if (!$isTeksPlaceholder) {
                        // Ada teks → simpan sebagai teks grup ini
                        $grupTeksBacaan[$grupKey] = $teksBacaan;
                    } elseif (isset($grupTeksBacaan[$grupKey])) {
                        // Kosong/placeholder → ambil dari grup yang sama
                        $teksBacaan = $grupTeksBacaan[$grupKey];
                    }

                } elseif ($isFormatPanduan) {
                    // FORMAT PANDUAN (dari export_soal.php):
                    // A=Kategori, B=Tipe Soal, C=Pertanyaan, D=pA, E=pB, F=pC, G=pD, H=Jawaban, I=Bobot
                    $teksBacaan = '';
                    $tipe       = $kolB;
                    $pert       = trim($row[2] ?? '');
                    $pA         = trim($row[3] ?? '');
                    $pB         = trim($row[4] ?? '');
                    $pC         = trim($row[5] ?? '');
                    $pD         = trim($row[6] ?? '');
                    $jwbRaw     = strtolower(trim($row[7] ?? ''));
                    $essayBobot = (int)(trim($row[8] ?? '10')) ?: 10;

                } elseif ($isFormatBaru) {
                    // Format baru: A=kat, B=teks_bacaan, C=pert, D=pA, E=pB, F=pC, G=pD, H=jwb, I=tipe
                    $teksBacaan = trim($row[1] ?? '');
                    $pert       = trim($row[2] ?? '');
                    $pA         = trim($row[3] ?? '');
                    $pB         = trim($row[4] ?? '');
                    $pC         = trim($row[5] ?? '');
                    $pD         = trim($row[6] ?? '');
                    $jwbRaw     = strtolower(trim($row[7] ?? ''));
                    $tipe       = strtolower(trim($row[8] ?? 'pg'));
                    $essayBobot = (int)(trim($row[9] ?? '10')) ?: 10;
                } else {
                    // Format lama: A=kat, B=pert, C=pA, D=pB, E=pC, F=pD, G=jwb, H=tipe, I=essay_bobot
                    $teksBacaan = '';
                    $isSheetBacaan = false;
                    $pert       = trim($row[1] ?? '');
                    $pA         = trim($row[2] ?? '');
                    $pB         = trim($row[3] ?? '');
                    $pC         = trim($row[4] ?? '');
                    $pD         = trim($row[5] ?? '');
                    $jwbRaw     = strtolower(trim($row[6] ?? ''));
                    $tipe       = strtolower(trim($row[7] ?? 'pg'));
                    $essayBobot = (int)(trim($row[8] ?? '10')) ?: 10;
                }
                $tipe = strtolower(trim($tipe));
                if (!in_array($tipe, ['pg','bs','mcma','essay'])) $tipe = 'pg';

                // Normalisasi jawaban berdasarkan tipe
                if ($tipe === 'bs') {
                    // BS: jawaban bisa huruf (a/b/c/d) atau teks (benar/salah)
                    if (in_array($jwbRaw, ['a','b','c','d'])) {
                        $jwb = $jwbRaw; // sudah huruf, langsung pakai
                    } elseif (in_array($jwbRaw, ['benar','salah'])) {
                        $jwb = $jwbRaw;
                    } elseif (in_array($jwbRaw, ['true','ya','iya','betul','correct'])) {
                        $jwb = 'benar';
                    } elseif (in_array($jwbRaw, ['false','tidak','salah','wrong','tidak benar'])) {
                        $jwb = 'salah';
                    } else {
                        // Coba cocokkan dengan teks pilihan
                        $cocok = cocokkanJawaban($jwbRaw, $pA, $pB, $pC, $pD);
                        $jwb = $cocok ?: 'benar';
                    }
                    // Konversi huruf ke benar/salah jika pA=Benar, pB=Salah
                    if (in_array($jwb, ['a','b','c','d'])) {
                        // Cari teks pilihan yang dipilih
                        $pilihanArr = ['a'=>$pA,'b'=>$pB,'c'=>$pC,'d'=>$pD];
                        $teksTerpilih = strtolower(trim($pilihanArr[$jwb] ?? ''));
                        if ($teksTerpilih === 'benar' || $teksTerpilih === 'true') $jwb = 'benar';
                        elseif ($teksTerpilih === 'salah' || $teksTerpilih === 'false') $jwb = 'salah';
                        // Jika bukan benar/salah, tetap pakai huruf (sistem simpan sebagai huruf)
                    }
                    if (!$pA) $pA = 'Benar';
                    if (!$pB) $pB = 'Salah';
                } elseif ($tipe === 'mcma') {
                    // MCMA: bisa huruf (a,b) atau teks dipisah koma
                    $jwbParts = array_map('trim', explode(',', $jwbRaw));
                    $jwbHuruf = [];
                    foreach ($jwbParts as $part) {
                        $part = strtolower($part);
                        if (in_array($part, ['a','b','c','d'])) {
                            $jwbHuruf[] = $part;
                        } elseif (strlen($part) === 1 && in_array($part, ['a','b','c','d'])) {
                            $jwbHuruf[] = $part;
                        } else {
                            // Coba cocokkan teks ke huruf
                            $h = cocokkanJawaban($part, $pA, $pB, $pC, $pD);
                            if ($h) $jwbHuruf[] = $h;
                        }
                    }
                    // Kalau tidak ada koma, coba split per karakter
                    if (empty($jwbHuruf) && !(strpos($jwbRaw, ',') !== false)) {
                        $chars = str_split($jwbRaw);
                        $chars = array_filter($chars, fn($c) => in_array($c, ['a','b','c','d']));
                        $jwbHuruf = array_values($chars);
                    }
                    sort($jwbHuruf);
                    $jwb = implode(',', array_unique($jwbHuruf));
                } else {
                    // PG: huruf langsung atau cocokkan teks
                    if (in_array($jwbRaw, ['a','b','c','d'])) {
                        $jwb = $jwbRaw;
                    } else {
                        $jwb = cocokkanJawaban($jwbRaw, $pA, $pB, $pC, $pD);
                    }
                }

                // Skip baris kosong
                if (!$pert && !$pA) continue;

                // Resolusi kategori
                $katId = 0;
                if (is_numeric($kolomKat)) {
                    $katId = (int)$kolomKat;
                } elseif ($kolomKat !== '') {
                    $katId = $katArr[strtolower($kolomKat)] ?? 0;
                    // Fuzzy match: "B. Indonesia" cocok ke "Bahasa Indonesia"
                    if (!$katId) {
                        $cariKat = strtolower(trim($kolomKat));
                        foreach ($katArr as $namaKat => $idKat) {
                            if (strpos($namaKat, $cariKat) !== false ||
                                strpos($cariKat, $namaKat) !== false) {
                                $katId = $idKat;
                                $katArr[$cariKat] = $katId;
                                break;
                            }
                        }
                    }
                }
                if (!$katId) {
                    // Coba buat kategori baru jika belum ada
                    if ($kolomKat !== '' && !is_numeric($kolomKat)) {
                        $katNamaBersih = $conn->real_escape_string($kolomKat);
                        $conn->query("INSERT IGNORE INTO kategori_soal (nama_kategori) VALUES ('$katNamaBersih')");
                        if ($conn->insert_id) {
                            $katId = $conn->insert_id;
                            $katArr[strtolower($kolomKat)] = $katId; // cache
                        } else {
                            // Coba ambil ulang (mungkin sudah ada dengan case berbeda)
                            $rKat = $conn->query("SELECT id FROM kategori_soal WHERE LOWER(nama_kategori)=LOWER('$katNamaBersih') LIMIT 1");
                            if ($rKat && $rKat->num_rows > 0) $katId = (int)$rKat->fetch_assoc()['id'];
                        }
                    }
                    if (!$katId) $katId = $defaultKatId;
                }

                // Validasi
                $rowErr = [];
                if (!$pert) $rowErr[] = 'pertanyaan kosong';
                if (!$katId) $rowErr[] = 'kategori tidak ditemukan';
                if ($tipe === 'pg') {
                    if (!$pA || !$pB || !$pC || !$pD) $rowErr[] = 'pilihan A-D tidak lengkap';
                    if (!$jwb) $rowErr[] = "Jawaban '{$jwbRaw}' tidak cocok dengan pilihan A/B/C/D manapun";
                } elseif ($tipe === 'bs') {
                    if (!$jwb) $rowErr[] = "Jawaban BS tidak valid (isi: a/b/c/d atau 'benar'/'salah')";
                } elseif ($tipe === 'mcma') {
                    if (!$pA || !$pB) $rowErr[] = 'minimal pilihan A dan B wajib diisi';
                    if (!$jwb || count(explode(',', $jwb)) < 2)
                        $rowErr[] = "MCMA harus ≥2 jawaban benar (contoh: a,b)";
                } elseif ($tipe === 'essay') {
                    // Essay: tidak perlu pilihan jawaban, jawaban_benar dikosongkan
                    $jwb = ''; // essay tidak punya kunci jawaban
                }

                if ($rowErr) {
                    $gagal++;
                    $log[] = ['no'=>$i+1, 'status'=>'gagal',
                              'pesan'=>implode('; ', $rowErr),
                              'preview'=>mb_substr($pert ?: $kolomKat, 0, 60)];
                    continue;
                }

                // Cek duplikat
                $pertEsc = $conn->real_escape_string($pert);
                $cekDup  = $conn->query("SELECT id FROM soal WHERE pertanyaan='$pertEsc' LIMIT 1");
                if ($cekDup && $cekDup->num_rows > 0) {
                    $log[] = ['no'=>$i+1, 'status'=>'lewati',
                              'pesan'=>'soal sudah ada (duplikat)',
                              'preview'=>mb_substr($pert, 0, 60),
                              'kat'=>$katById[$katId] ?? "ID $katId",
                              'tipe'=>strtoupper($tipe)];
                    continue;
                }

                $essayBotot = ($tipe === 'essay') ? $essayBobot : 10;
                $stmt->bind_param('issssssssi', $katId, $tipe, $pert, $teksBacaan, $pA, $pB, $pC, $pD, $jwb, $essayBotot);
                if ($stmt->execute()) {
                    $berhasil++;
                    $log[] = ['no'=>$i+1, 'status'=>'ok',
                              'preview'=>mb_substr($pert, 0, 60),
                              'kat'=>$katById[$katId] ?? "ID $katId",
                              'tipe'=>strtoupper($tipe)];
                } else {
                    $gagal++;
                    $log[] = ['no'=>$i+1, 'status'=>'gagal',
                              'pesan'=>$conn->error,
                              'preview'=>mb_substr($pert, 0, 60)];
                }
            }
            $stmt->close();
            $lewati  = count(array_filter($log, fn($l) => $l['status'] === 'lewati'));
            $results = compact('berhasil', 'gagal', 'lewati', 'log');

            if ($berhasil > 0) {
                logActivity($conn, 'Import soal', "$berhasil soal berhasil diimport");
                setFlash('success', "<strong>$berhasil soal</strong> berhasil diimport." .
                                    ($gagal  ? " <strong>$gagal baris</strong> gagal." : '') .
                                    ($lewati ? " <strong>$lewati soal</strong> dilewati (duplikat)." : ''));
            } elseif ($gagal > 0) {
                setFlash('error', "Semua <strong>$gagal baris</strong> gagal diimport. Periksa format file.");
            } elseif ($lewati > 0) {
                setFlash('info', "Semua <strong>$lewati soal</strong> sudah ada, tidak ada yang diimport baru.");
            } else {
                setFlash('info', 'Tidak ada data yang diproses. Pastikan file tidak kosong.');
            }
        }
    }
}

$pageTitle  = 'Import Soal Excel';
$activeMenu = 'importsoal';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-file-earmark-excel me-2 text-success"></i>Import Soal dari Excel</h2>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/soal.php">Bank Soal</a></li>
            <li class="breadcrumb-item active">Import Excel</li>
        </ol></nav>
    </div>
    <a href="<?= BASE_URL ?>/admin/soal.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali ke Bank Soal
    </a>
</div>

<?= renderFlash() ?>

<?php if ($errors): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <h6 class="fw-bold mb-1"><i class="bi bi-exclamation-triangle me-1"></i>Terdapat Kesalahan</h6>
    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">

    <!-- ── Form Upload ── -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-upload me-2 text-primary"></i>Upload File Excel
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="formImport">
            <?= csrfField() ?>

                    <div class="mb-3">
                        <label class="form-label">Kategori Default <span class="text-muted small">(opsional)</span></label>
                        <select name="default_kategori_id" class="form-select">
                            <option value="">— Gunakan kolom Kategori di Excel —</option>
                            <?php foreach ($katById as $kid => $knm): ?>
                            <option value="<?= $kid ?>"><?= e($knm) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Digunakan jika kolom kategori di Excel kosong atau tidak dikenali.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            File Excel <span class="text-danger">*</span>
                        </label>
                        <div class="upload-zone" id="uploadZone">
                            <input type="file" name="file_excel" id="fileInput"
                                   accept=".xls,.xlsx,.csv" required class="d-none">
                            <div id="uploadPrompt" onclick="document.getElementById('fileInput').click()"
                                 style="cursor:pointer">
                                <i class="bi bi-cloud-upload fs-2 text-primary d-block mb-2"></i>
                                <p class="fw-semibold mb-1">Klik untuk pilih file</p>
                                <p class="text-muted small mb-0">atau drag & drop di sini</p>
                                <p class="text-muted small">Format: <strong>.xlsx</strong>, <strong>.xls</strong>, atau <strong>.csv</strong> — Maks. 5 MB</p>
                            </div>
                            <div id="fileSelected" class="d-none text-center">
                                <i class="bi bi-file-earmark-excel fs-2 text-success d-block mb-2"></i>
                                <p class="fw-semibold mb-1" id="fileName">-</p>
                                <p class="text-muted small" id="fileSize">-</p>
                                <button type="button" class="btn btn-xs btn-outline-danger mt-1"
                                        onclick="resetFile()">
                                    <i class="bi bi-x me-1"></i>Ganti File
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success w-100 fw-bold py-2" id="btnImport" disabled>
                        <i class="bi bi-upload me-2"></i>Proses Import
                    </button>
                </form>
            </div>
        </div>

        <!-- Download Template -->
        <div class="card mt-3">
            <div class="card-header"><i class="bi bi-download me-2"></i>Template Excel</div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Download template resmi agar format file sesuai:
                </p>
                <a href="<?= BASE_URL ?>/admin/download_template_soal.php"
                   class="btn btn-outline-success w-100 mb-2">
                    <i class="bi bi-file-earmark-excel me-2"></i>Download Template (.xlsx)
                </a>
                <a href="<?= BASE_URL ?>/admin/download_template_soal_csv.php"
                   class="btn btn-outline-secondary w-100">
                    <i class="bi bi-file-earmark-text me-2"></i>Download Template (.csv)
                </a>
            </div>
        </div>
    </div>

    <!-- ── Panduan + Hasil ── -->
    <div class="col-lg-7">

        <!-- Hasil Import -->
        <?php if ($results): ?>
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span>
                    <i class="bi bi-clipboard-check me-2"></i>Hasil Import
                </span>
                <div class="d-flex gap-2">
                    <span class="badge bg-success"><?= $results['berhasil'] ?> berhasil</span>
                    <?php if (($results['lewati'] ?? 0) > 0): ?>
                    <span class="badge bg-warning text-dark"><?= $results['lewati'] ?> dilewati</span>
                    <?php endif; ?>
                    <?php if ($results['gagal'] > 0): ?>
                    <span class="badge bg-danger"><?= $results['gagal'] ?> gagal</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Progress bar -->
            <?php $totalRows = $results['berhasil'] + $results['gagal']; ?>
            <div class="p-3 border-bottom">
                <div class="d-flex justify-content-between small mb-1">
                    <span><?= $results['berhasil'] ?> baris berhasil</span>
                    <span><?= $totalRows ?> total diproses</span>
                </div>
                <div class="progress" style="height:8px">
                    <div class="progress-bar bg-success"
                         style="width:<?= $totalRows > 0 ? round($results['berhasil']/$totalRows*100) : 0 ?>%"></div>
                    <div class="progress-bar bg-danger"
                         style="width:<?= $totalRows > 0 ? round($results['gagal']/$totalRows*100) : 0 ?>%"></div>
                </div>
            </div>

            <div style="max-height:360px; overflow-y:auto">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th style="width:50px">Baris</th>
                            <th>Pertanyaan</th>
                            <th style="width:90px">Kategori</th>
                            <th style="width:70px" class="text-center">Status</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($results['log'] as $lg): ?>
                    <tr class="<?= $lg['status']==='ok' ? 'table-success' : ($lg['status']==='lewati' ? 'table-warning' : 'table-danger') ?>">
                        <td><?= $lg['no'] ?></td>
                        <td class="small"><?= e(mb_substr($lg['preview'], 0, 55)) ?><?= mb_strlen($lg['preview']) > 55 ? '…' : '' ?></td>
                        <td class="small"><?= e($lg['kat'] ?? '-') ?></td>
                        <td class="text-center">
                            <?php if ($lg['status']==='ok'): ?>
                            <span class="badge bg-success">✓ OK</span>
                            <?php elseif ($lg['status']==='lewati'): ?>
                            <span class="badge bg-warning text-dark">⟳ Lewati</span>
                            <?php else: ?>
                            <span class="badge bg-danger">✗ Gagal</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= e($lg['pesan'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($results['berhasil'] > 0): ?>
            <div class="card-footer">
                <a href="<?= BASE_URL ?>/admin/soal.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-eye me-1"></i>Lihat Semua Soal
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Format panduan -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-info-circle me-2 text-info"></i>Format File Excel
            </div>
            <div class="card-body">
                <p class="fw-semibold mb-3">Urutan kolom (baris pertama bisa header):</p>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm small">
                        <thead class="table-primary">
                            <tr>
                                <th>A: Kategori</th>
                                <th>B: Tipe Soal</th>
                                <th>C: Pertanyaan</th>
                                <th>D: Pilihan A</th>
                                <th>E: Pilihan B</th>
                                <th>F: Pilihan C</th>
                                <th>G: Pilihan D</th>
                                <th>H: Jawaban</th>
                                <th>I: Bobot*</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="table-light">
                                <td>Matematika</td>
                                <td><span class="badge bg-primary">pg</span></td>
                                <td>Hasil dari 125 × 8 adalah...</td>
                                <td>100</td><td>800</td><td>1.000</td><td>1.600</td>
                                <td class="fw-bold text-success">c</td>
                                <td class="text-muted">—</td>
                            </tr>
                            <tr>
                                <td>IPA</td>
                                <td><span class="badge bg-warning text-dark">bs</span></td>
                                <td>Matahari terbit dari Timur.</td>
                                <td>Benar</td><td>Salah</td><td></td><td></td>
                                <td class="fw-bold text-success">benar</td>
                                <td class="text-muted">—</td>
                            </tr>
                            <tr class="table-light">
                                <td>IPS</td>
                                <td><span class="badge bg-warning text-dark">mcma</span></td>
                                <td>Pilih pulau besar di Indonesia!</td>
                                <td>Jawa</td><td>Sumatra</td><td>Bali</td><td>Kalimantan</td>
                                <td class="fw-bold text-success">a,b,d</td>
                                <td class="text-muted">—</td>
                            </tr>
                            <tr class="table-success">
                                <td>B. Indonesia</td>
                                <td><span class="badge bg-success">essay</span></td>
                                <td>Jelaskan pengertian teks narasi!</td>
                                <td></td><td></td><td></td><td></td>
                                <td class="text-muted fst-italic">kosong</td>
                                <td class="fw-bold text-success">10</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="row g-2 mt-2">
                    <div class="col-md-6">
                        <div class="alert alert-success py-2 mb-0 small">
                            <strong>✓ Kolom A (Kategori)</strong> bisa berisi:<br>
                            • Nama kategori (cth: <code>Matematika</code>)<br>
                            • ID kategori (cth: <code>1</code>)<br>
                            • Kosong → pakai kategori default di atas
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-warning py-2 mb-0 small">
                            <strong>⚠ Kolom G (Jawaban)</strong> harus huruf kecil:<br>
                            <code>a</code>, <code>b</code>, <code>c</code>, atau <code>d</code><br>
                            Huruf kapital (A/B/C/D) akan otomatis dikonversi.
                        </div>
                    </div>
                </div>
                <div class="alert alert-info py-2 mt-2 mb-0 small">
                    <strong>ℹ Format yang didukung:</strong>
                    <code>.xlsx</code> (direkomendasikan), <code>.xls</code>, dan <code>.csv</code>.<br>
                    Jika <code>.xlsx</code> gagal karena masalah server (ZipArchive), gunakan format <strong>.csv</strong>.<br>
                    Gambar soal tidak bisa diimport via Excel/CSV (tambahkan manual di Bank Soal).
                </div>
            </div>
        </div>

        <!-- Daftar kategori yang ada -->
        <?php if (!empty($katById)): ?>
        <div class="card mt-3">
            <div class="card-header"><i class="bi bi-tags me-2"></i>Kategori Tersedia</div>
            <div class="card-body py-2">
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($katById as $kid => $knm): ?>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                        <?= $kid ?> — <?= e($knm) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php if (empty($katById)): ?>
                <p class="text-muted small mb-0">
                    Belum ada kategori. <a href="<?= BASE_URL ?>/admin/kategori.php">Buat kategori dulu</a>.
                </p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.upload-zone {
    border: 2px dashed var(--border); border-radius: var(--radius);
    padding: 28px 20px; text-align: center;
    background: #f8fafc; transition: var(--transition);
}
.upload-zone.drag-over { border-color: var(--primary); background: #eff6ff; }
</style>
<script>
const fileInput   = document.getElementById('fileInput');
const uploadZone  = document.getElementById('uploadZone');
const uploadPrompt= document.getElementById('uploadPrompt');
const fileSelected= document.getElementById('fileSelected');
const btnImport   = document.getElementById('btnImport');

fileInput.addEventListener('change', showFile);

function showFile() {
    const f = fileInput.files[0];
    if (!f) return;
    document.getElementById('fileName').textContent = f.name;
    document.getElementById('fileSize').textContent = (f.size/1024/1024).toFixed(2) + ' MB';
    uploadPrompt.classList.add('d-none');
    fileSelected.classList.remove('d-none');
    btnImport.disabled = false;
}
function resetFile() {
    fileInput.value = '';
    uploadPrompt.classList.remove('d-none');
    fileSelected.classList.add('d-none');
    btnImport.disabled = true;
}

// Drag & Drop
uploadZone.addEventListener('dragover', e => { e.preventDefault(); uploadZone.classList.add('drag-over'); });
uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('drag-over'));
uploadZone.addEventListener('drop', e => {
    e.preventDefault(); uploadZone.classList.remove('drag-over');
    const dt = e.dataTransfer;
    if (dt.files[0]) { fileInput.files = dt.files; showFile(); }
});

// Progress saat submit
document.getElementById('formImport').addEventListener('submit', () => {
    btnImport.disabled = true;
    btnImport.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memproses…';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
