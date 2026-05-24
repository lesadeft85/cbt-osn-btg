<?php
// ============================================================
// admin/download_template_peserta_csv.php — Download Template Import Peserta (.csv)
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
requireLogin('admin_kecamatan');

$filename = "template_import_peserta.csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
// BOM untuk Excel agar deteksi UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header sesuai format import
$header = ['nama', 'kelas', 'kode_sekolah',];
fputcsv($output, $header);

// Contoh baris
fputcsv($output, ['Andi Pratama',  'VI A', 'SDN Bantargebang 1',]);
fputcsv($output, ['Budi Santoso',  'VI B', 'SDN Bantargebang 1']);
fputcsv($output, ['Citra Dewi',    'VI', 'SDN Bantargebang 1']);
fputcsv($output, ['Dian Rahma',    'VIII', 'SDN Bantargebang 1']);
fputcsv($output, ['Eko Setiawan',  'IX B', 'SDN Bantargebang 1']);

fclose($output);
exit;
