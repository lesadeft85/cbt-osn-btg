<?php
// Skrip migrasi: tambahkan kolom `kode_sekolah` ke tabel `peserta` jika belum ada.
require_once __DIR__ . '/../config/database.php';

function columnExists(mysqli $conn, $table, $column) {
    $t = $conn->real_escape_string($table);
    $c = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
    return $res && $res->num_rows > 0;
}

if (columnExists($conn, 'peserta', 'kode_sekolah')) {
    echo "Kolom kode_sekolah sudah ada di tabel peserta.\n";
    exit(0);
}

$sql = "ALTER TABLE `peserta` ADD COLUMN `kode_sekolah` VARCHAR(100) DEFAULT NULL AFTER `sekolah_id`";
if ($conn->query($sql) === TRUE) {
    echo "Sukses: kolom kode_sekolah ditambahkan ke tabel peserta.\n";
    exit(0);
} else {
    echo "Gagal menambahkan kolom: " . $conn->error . "\n";
    exit(1);
}
