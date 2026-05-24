<?php
// ============================================================
// admin/export_soal.php — Export Bank Soal ke XLSX
// Format output = format import (Format Panduan):
// Sheet 1 "Template Soal" : Kategori|Tipe|Pertanyaan|pA|pB|pC|pD|Jawaban|Bobot
// Sheet 2 "Soal Bacaan"   : Grup|Kategori|Tipe|TeksBacaan|Pertanyaan|pA|pB|pC|pD|Jawaban|Bobot
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('admin_kecamatan');

$filterKat = (int)($_GET['kategori_id'] ?? 0);
$where = $filterKat ? "WHERE s.kategori_id = $filterKat" : '';

$res = $conn->query("
    SELECT k.nama_kategori,
           s.teks_bacaan, s.pertanyaan,
           s.pilihan_a, s.pilihan_b, s.pilihan_c, s.pilihan_d,
           s.jawaban_benar, s.tipe_soal, s.essay_bobot
    FROM soal s
    JOIN kategori_soal k ON k.id = s.kategori_id
    $where
    ORDER BY k.nama_kategori, s.id
");

if (!$res || $res->num_rows === 0) {
    setFlash('warning', 'Tidak ada soal untuk diekspor.');
    redirect(BASE_URL . '/admin/soal.php');
}

// Pisahkan soal biasa dan soal bacaan
$soalBiasa  = [];
$soalBacaan = [];
while ($r = $res->fetch_assoc()) {
    if (trim($r['teks_bacaan'] ?? '') !== '') {
        $soalBacaan[] = $r;
    } else {
        $soalBiasa[] = $r;
    }
}

// ── Fallback CSV jika ZipArchive tidak tersedia ───────────────
if (!class_exists('\ZipArchive') && !extension_loaded('zip')) {
    $filename = 'soal_export_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

    // Sheet 1: soal biasa
    fputcsv($output, ['=== TEMPLATE SOAL (Soal Biasa) ===']);
    fputcsv($output, ['Kategori','Tipe Soal','Pertanyaan','Pilihan A','Pilihan B','Pilihan C','Pilihan D','Jawaban Benar','Bobot Essay']);
    foreach ($soalBiasa as $r) {
        fputcsv($output, [
            $r['nama_kategori'],
            strtolower($r['tipe_soal']),
            $r['pertanyaan'],
            $r['pilihan_a']    ?? '',
            $r['pilihan_b']    ?? '',
            $r['pilihan_c']    ?? '',
            $r['pilihan_d']    ?? '',
            $r['jawaban_benar'] ?? '',
            $r['tipe_soal'] === 'essay' ? (int)$r['essay_bobot'] : '',
        ]);
    }

    // Sheet 2: soal bacaan
    if (!empty($soalBacaan)) {
        fputcsv($output, []);
        fputcsv($output, ['=== SOAL BACAAN ===']);
        fputcsv($output, ['Grup','Kategori','Tipe Soal','Teks Bacaan','Pertanyaan','Pilihan A','Pilihan B','Pilihan C','Pilihan D','Jawaban Benar','Bobot Essay']);
        $grupCounter = 1;
        $teksBacaanGrup = [];
        $teksSudahDitulis = [];
        foreach ($soalBacaan as $r) {
            $key = md5(trim($r['teks_bacaan']));
            if (!isset($teksBacaanGrup[$key])) $teksBacaanGrup[$key] = $grupCounter++;
            $grupNo = $teksBacaanGrup[$key];
            $teksKolom = !isset($teksSudahDitulis[$key]) ? $r['teks_bacaan'] : '↑';
            $teksSudahDitulis[$key] = true;
            fputcsv($output, [
                $grupNo,
                $r['nama_kategori'],
                strtolower($r['tipe_soal']),
                $teksKolom,
                $r['pertanyaan'],
                $r['pilihan_a']    ?? '',
                $r['pilihan_b']    ?? '',
                $r['pilihan_c']    ?? '',
                $r['pilihan_d']    ?? '',
                $r['jawaban_benar'] ?? '',
                $r['tipe_soal'] === 'essay' ? (int)$r['essay_bobot'] : '',
            ]);
        }
    }
    fclose($output);
    exit;
}

// ── Helper escape XML ─────────────────────────────────────────
function xlsxEsc(string $v): string {
    // Hapus karakter kontrol tidak valid dalam XML
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v);
    return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

// ── Builder XLSX multi-sheet ──────────────────────────────────
function buildXlsxMultiSheet(array $sheets): string {
    // $sheets = [ ['name'=>'Sheet1', 'header'=>[], 'data'=>[], 'colWidths'=>[]] ]

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml"  ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/styles.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    foreach ($sheets as $i => $_) {
        $n = $i + 1;
        $contentTypes .= "\n  <Override PartName=\"/xl/worksheets/sheet{$n}.xml\"
    ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
    }
    $contentTypes .= "\n</Types>";

    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"
    Target="xl/workbook.xml"/>
</Relationships>';

    // workbook.xml
    $wbSheets = '';
    $wbRelsContent = '';
    foreach ($sheets as $i => $sh) {
        $n = $i + 1;
        $shName = xlsxEsc($sh['name']);
        $wbSheets .= "<sheet name=\"{$shName}\" sheetId=\"{$n}\" r:id=\"rId{$n}\"/>";
        $wbRelsContent .= "<Relationship Id=\"rId{$n}\"
      Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\"
      Target=\"worksheets/sheet{$n}.xml\"/>";
    }
    // styles rId
    $stylesId = count($sheets) + 1;
    $wbRelsContent .= "<Relationship Id=\"rId{$stylesId}\"
      Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles\"
      Target=\"styles.xml\"/>";

    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>' . $wbSheets . '</sheets>
</workbook>';

    $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . $wbRelsContent .
'</Relationships>';

    // styles.xml — style 0=normal, 1=header bold biru, 2=wrap text
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts>
    <font><sz val="11"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font>
  </fonts>
  <fills>
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF4472C4"/></patternFill></fill>
  </fills>
  <borders><border><left/><right/><top/><bottom/><diagonal/></border></borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"><alignment wrapText="1"/></xf>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"><alignment wrapText="1"/></xf>
  </cellXfs>
</styleSheet>';

    $colLetters = ['A','B','C','D','E','F','G','H','I','J','K','L'];

    $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
    $zip = new \ZipArchive();
    $zip->open($tmpFile, \ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml',        $contentTypes);
    $zip->addFromString('_rels/.rels',                $rels);
    $zip->addFromString('xl/workbook.xml',            $workbook);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
    $zip->addFromString('xl/styles.xml',              $styles);

    foreach ($sheets as $i => $sh) {
        $n = $i + 1;
        $header    = $sh['header'];
        $data      = $sh['data'];
        $colWidths = $sh['colWidths'] ?? [];

        $sheetXml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $sheetXml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        // Kolom lebar
        if (!empty($colWidths)) {
            $sheetXml .= '<cols>';
            foreach ($colWidths as $ci => $w) {
                $cn = $ci + 1;
                $sheetXml .= "<col min=\"{$cn}\" max=\"{$cn}\" width=\"{$w}\" customWidth=\"1\"/>";
            }
            $sheetXml .= '</cols>';
        }

        $sheetXml .= '<sheetData>';

        // Header row
        $sheetXml .= '<row r="1">';
        foreach ($header as $ci => $h) {
            $cell = $colLetters[$ci] . '1';
            $sheetXml .= "<c r=\"{$cell}\" t=\"inlineStr\" s=\"1\"><is><t>" . xlsxEsc((string)$h) . "</t></is></c>";
        }
        $sheetXml .= '</row>';

        // Data rows
        foreach ($data as $ri => $rowData) {
            $rowNum = $ri + 2;
            $sheetXml .= "<row r=\"{$rowNum}\">";
            foreach ($rowData as $ci => $val) {
                $cell = $colLetters[$ci] . $rowNum;
                $val  = (string)($val ?? '');
                $sheetXml .= "<c r=\"{$cell}\" t=\"inlineStr\" s=\"2\"><is><t>" . xlsxEsc($val) . "</t></is></c>";
            }
            $sheetXml .= '</row>';
        }

        $sheetXml .= '</sheetData>';
        $sheetXml .= '<pageSetup orientation="landscape"/>';
        $sheetXml .= '</worksheet>';

        $zip->addFromString("xl/worksheets/sheet{$n}.xml", $sheetXml);
    }

    $zip->close();
    $content = file_get_contents($tmpFile);
    unlink($tmpFile);
    return $content;
}

// ── Siapkan Sheet 1: Template Soal (soal biasa) ───────────────
$sheet1Header = [
    'Kategori', 'Tipe Soal', 'Pertanyaan',
    'Pilihan A', 'Pilihan B', 'Pilihan C', 'Pilihan D',
    'Jawaban Benar', 'Bobot Essay'
];
$sheet1ColWidths = [20, 10, 50, 25, 25, 25, 25, 15, 12];
$sheet1Data = [];
foreach ($soalBiasa as $r) {
    $sheet1Data[] = [
        $r['nama_kategori'],
        strtolower($r['tipe_soal']),
        $r['pertanyaan'],
        $r['pilihan_a']     ?? '',
        $r['pilihan_b']     ?? '',
        $r['pilihan_c']     ?? '',
        $r['pilihan_d']     ?? '',
        $r['jawaban_benar'] ?? '',
        $r['tipe_soal'] === 'essay' ? (int)$r['essay_bobot'] : '',
    ];
}

$allSheets = [
    [
        'name'      => 'Template Soal',
        'header'    => $sheet1Header,
        'data'      => $sheet1Data,
        'colWidths' => $sheet1ColWidths,
    ]
];

// ── Siapkan Sheet 2: Soal Bacaan (jika ada) ───────────────────
if (!empty($soalBacaan)) {
    $sheet2Header = [
        'Grup', 'Kategori', 'Tipe Soal', 'Teks Bacaan', 'Pertanyaan',
        'Pilihan A', 'Pilihan B', 'Pilihan C', 'Pilihan D',
        'Jawaban Benar', 'Bobot Essay'
    ];
    $sheet2ColWidths = [6, 20, 10, 45, 45, 20, 20, 20, 20, 15, 12];
    $sheet2Data = [];

    $grupCounter      = 1;
    $teksBacaanGrup   = [];
    $teksSudahDitulis = [];

    foreach ($soalBacaan as $r) {
        $key = md5(trim($r['teks_bacaan']));
        if (!isset($teksBacaanGrup[$key])) {
            $teksBacaanGrup[$key] = $grupCounter++;
        }
        $grupNo = $teksBacaanGrup[$key];

        // Teks bacaan hanya ditulis sekali per grup, berikutnya pakai tanda ↑
        $teksKolom = !isset($teksSudahDitulis[$key]) ? $r['teks_bacaan'] : '↑';
        $teksSudahDitulis[$key] = true;

        $sheet2Data[] = [
            (string)$grupNo,
            $r['nama_kategori'],
            strtolower($r['tipe_soal']),
            $teksKolom,
            $r['pertanyaan'],
            $r['pilihan_a']     ?? '',
            $r['pilihan_b']     ?? '',
            $r['pilihan_c']     ?? '',
            $r['pilihan_d']     ?? '',
            $r['jawaban_benar'] ?? '',
            $r['tipe_soal'] === 'essay' ? (int)$r['essay_bobot'] : '',
        ];
    }

    $allSheets[] = [
        'name'      => 'Soal Bacaan',
        'header'    => $sheet2Header,
        'data'      => $sheet2Data,
        'colWidths' => $sheet2ColWidths,
    ];
}

// ── Generate & Download ───────────────────────────────────────
$xlsxContent = buildXlsxMultiSheet($allSheets);
$namaFile    = 'soal_export_' . date('Ymd_His') . '.xlsx';
$totalSoal   = count($soalBiasa) + count($soalBacaan);

logActivity($conn, 'Export Soal', "Export {$totalSoal} soal ke XLSX" . ($filterKat ? " (kat ID: $filterKat)" : ''));

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $namaFile . '"');
header('Content-Length: ' . strlen($xlsxContent));
header('Pragma: no-cache');
header('Expires: 0');
echo $xlsxContent;
exit;
