<?php
// ============================================================
// admin/export_peserta.php — Export Daftar Peserta ke Excel
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('admin_kecamatan');

$filterSek  = (int)($_GET['sekolah_id'] ?? 0);
$filterKelas = trim($_GET['kelas'] ?? '');
$q           = trim($_GET['q'] ?? '');

// ── Query data peserta ────────────────────────────────────────
$where = "WHERE 1=1";
if ($filterSek)   $where .= " AND p.sekolah_id = $filterSek";
if ($filterKelas) $where .= " AND p.kelas = '" . $conn->real_escape_string($filterKelas) . "'";
if ($q)           $where .= " AND (p.nama LIKE '%" . $conn->real_escape_string($q) . "%' OR p.kode_peserta LIKE '%" . $conn->real_escape_string($q) . "%')";

$res = $conn->query("
    SELECT p.nama, p.kelas, p.kode_peserta, p.kode_sekolah, s.nama_sekolah,
           (SELECT COUNT(*) FROM ujian WHERE peserta_id=p.id AND waktu_selesai IS NOT NULL) AS sdh_ujian,
           (SELECT nilai FROM ujian WHERE peserta_id=p.id AND waktu_selesai IS NOT NULL ORDER BY id DESC LIMIT 1) AS nilai_terakhir
    FROM peserta p
    LEFT JOIN sekolah s ON s.id = p.sekolah_id
    $where
    ORDER BY s.nama_sekolah, p.kelas, p.nama
");

$rows = [];
if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;

$namaAplikasi  = getSetting($conn, 'nama_aplikasi', 'TKA Kecamatan');
$namaKecamatan = getSetting($conn, 'nama_kecamatan', 'Kecamatan');
$tahunPelajaran= getSetting($conn, 'tahun_pelajaran', date('Y') . '/' . (date('Y')+1));

// Nama file
$namaSekolah = '';
if ($filterSek) {
    $sr = $conn->query("SELECT nama_sekolah FROM sekolah WHERE id=$filterSek LIMIT 1");
    if ($sr && $sr->num_rows > 0) $namaSekolah = '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $sr->fetch_assoc()['nama_sekolah']);
}
$filename = 'daftar_peserta' . $namaSekolah . '_' . date('Ymd') . '.xlsx';

// ── Coba pakai PhpSpreadsheet jika tersedia ───────────────────
$phpSpreadsheetPath = __DIR__ . '/../vendor/autoload.php';
$usePhpSpreadsheet  = file_exists($phpSpreadsheetPath);

if ($usePhpSpreadsheet) {
    require_once $phpSpreadsheetPath;
    // Gunakan PhpSpreadsheet jika ada
    exportWithPhpSpreadsheet($rows, $filename, $namaAplikasi, $namaKecamatan, $tahunPelajaran);
} else {
    // Fallback: export sebagai Excel XML (xls) yang bisa dibuka Excel tanpa library
    exportAsExcelXml($rows, $filename, $namaAplikasi, $namaKecamatan, $tahunPelajaran);
}

// ── Export pakai PhpSpreadsheet ───────────────────────────────
function exportWithPhpSpreadsheet(array $rows, string $filename, string $namaApp, string $namaKec, string $tp): void {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Daftar Peserta');

    $kkm = 60;

    // Header info
    $sheet->mergeCells('A1:H1');
    $sheet->setCellValue('A1', $namaApp . ' — Daftar Peserta');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal('center');

    $sheet->mergeCells('A2:H2');
    $sheet->setCellValue('A2', $namaKec . ' · Tahun Pelajaran ' . $tp . ' · Dicetak: ' . date('d/m/Y H:i'));
    $sheet->getStyle('A2')->getAlignment()->setHorizontal('center');

    // Header kolom
    $headers = ['No', 'Nama Peserta', 'Kode Peserta', 'Kode Sekolah', 'Kelas', 'Sekolah', 'Status Ujian', 'Nilai Terakhir'];
    $sheet->fromArray($headers, null, 'A4');
    $sheet->getStyle('A4:H4')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
    $sheet->getStyle('A4:H4')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF1F4E79');
    $sheet->getStyle('A4:H4')->getAlignment()->setHorizontal('center');

    // Data
    $no = 1;
    $row = 5;
    foreach ($rows as $r) {
        $sdh   = (int)$r['sdh_ujian'] > 0;
        $nilai = $r['nilai_terakhir'];
        $sheet->fromArray([
            $no,
            $r['nama'],
            $r['kode_peserta'] ?? '-',
            $r['kode_sekolah'] ?? '-',
            $r['kelas'] ?? '-',
            $r['nama_sekolah'] ?? '-',
            $sdh ? 'Sudah' : 'Belum',
            $nilai !== null ? (int)$nilai : '-',
        ], null, 'A' . $row);
        $no++;
        $row++;
    }

    foreach (range('A', 'H') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// ── Export pakai Excel XML (tidak butuh library) ──────────────
function exportAsExcelXml(array $rows, string $filename, string $namaApp, string $namaKec, string $tp): void {
    $filename = str_replace('.xlsx', '.xls', $filename);

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $kkm = 60;

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="UTF-8">
    <style>
        body { font-family: Calibri, Arial, sans-serif; font-size: 11pt; }
        table { border-collapse: collapse; width: 100%; }
        .judul { font-size: 14pt; font-weight: bold; color: #1F4E79; text-align: center; }
        .sub   { font-size: 10pt; color: #595959; font-style: italic; text-align: center; }
        th { background-color: #1F4E79; color: #FFFFFF; font-weight: bold; text-align: center;
             border: 1px solid #FFFFFF; padding: 6px 8px; }
        td { border: 1px solid #D9D9D9; padding: 4px 8px; vertical-align: middle; }
        .row-alt { background-color: #EBF3FB; }
        .center  { text-align: center; }
        .kode    { text-align: center; font-family: "Courier New"; font-weight: bold; color: #1F4E79; }
        .lulus   { text-align: center; font-weight: bold; color: #166534; }
        .tidak_lulus { text-align: center; font-weight: bold; color: #991B1B; }
        .footer  { font-size: 9pt; color: #94A3B8; font-style: italic; text-align: right; }
    </style></head><body>';

    echo '<table>';

    // Judul
    echo '<tr><td colspan="8" class="judul">' . htmlspecialchars($namaApp) . ' &mdash; Daftar Peserta</td></tr>';
    echo '<tr><td colspan="8" class="sub">' . htmlspecialchars($namaKec) . ' &middot; Tahun Pelajaran ' . htmlspecialchars($tp) . ' &middot; Dicetak: ' . date('d/m/Y H:i') . '</td></tr>';
    echo '<tr><td colspan="8"></td></tr>';

    // Header
    echo '<tr>
        <th width="40">No</th>
        <th width="200">Nama Peserta</th>
        <th width="130">Kode Peserta</th>
        <th width="130">Kode Sekolah</th>
        <th width="70">Kelas</th>
        <th width="180">Sekolah</th>
        <th width="90">Status Ujian</th>
        <th width="90">Nilai Terakhir</th>
    </tr>';

    // Data
    $no = 1;
    foreach ($rows as $r) {
        $alt      = $no % 2 === 0 ? ' class="row-alt"' : '';
        $sdh      = (int)$r['sdh_ujian'] > 0;
        $nilai    = $r['nilai_terakhir'];
        $nilaiStr = $nilai !== null ? (string)(int)$nilai : '-';
        $nilaiClass = $nilai !== null ? ($nilai >= $kkm ? 'lulus' : 'tidak_lulus') : 'center';

        echo '<tr' . $alt . '>
            <td class="center">' . $no . '</td>
            <td>' . htmlspecialchars($r['nama']) . '</td>
            <td class="kode">' . htmlspecialchars($r['kode_peserta'] ?? '-') . '</td>
            <td class="center">' . htmlspecialchars($r['kelas'] ?? '-') . '</td>
            <td>' . htmlspecialchars($r['nama_sekolah'] ?? '-') . '</td>
            <td class="' . ($sdh ? 'lulus' : 'center') . '">' . ($sdh ? 'Sudah' : 'Belum') . '</td>
            <td class="' . $nilaiClass . '">' . $nilaiStr . '</td>
        </tr>';
        $no++;
    }

    // Footer
    echo '<tr><td colspan="8"></td></tr>';
    echo '<tr><td colspan="8" class="footer">Total: ' . count($rows) . ' peserta &middot; Diekspor dari ' . htmlspecialchars($namaApp) . ' &middot; ' . date('d F Y H:i') . '</td></tr>';

    echo '</table></body></html>';
    exit;
}
