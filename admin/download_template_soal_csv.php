<?php
// ============================================================
// admin/download_template_soal_csv.php — Download Template Import Soal (.csv)
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
requireLogin('admin_kecamatan');

$filename = "template_import_soal.csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
// BOM untuk Excel agar deteksi UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header sesuai format import (kolom I = essay_bobot, hanya diisi untuk tipe essay)
$header = ['kategori', 'tipe_soal', 'pertanyaan', 'pilihan_a', 'pilihan_b', 'pilihan_c', 'pilihan_d', 'jawaban_benar', 'essay_bobot'];
fputcsv($output, $header);

// Contoh baris PG
fputcsv($output, ['Matematika', 'pg', 'Hasil dari 125 x 8 adalah...', '100', '800', '1.000', '1.600', 'c', '']);
// Contoh baris BS
fputcsv($output, ['IPA', 'bs', 'Matahari terbit dari arah Timur.', 'Benar', 'Salah', '', '', 'benar', '']);
// Contoh baris MCMA
fputcsv($output, ['IPS', 'mcma', 'Manakah pulau besar di Indonesia?', 'Jawa', 'Sumatra', 'Bali', 'Kalimantan', 'a,b,d', '']);
// Contoh baris ESSAY (kolom H kosong, kolom I = bobot)
fputcsv($output, ['Bahasa Indonesia', 'essay', 'Jelaskan pengertian teks narasi dan berikan contohnya!', '', '', '', '', '', '10']);

fclose($output);
exit;
