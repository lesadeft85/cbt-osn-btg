<?php
// ============================================================
// core/lisensi.php — Sistem Lisensi Key TKA Kecamatan
// ============================================================
// Format key: XXXX-XXXX-XXXX-XXXX-XXXX (20 karakter hex + 4 dash)
//
// Struktur key (20 hex chars):
//   [0..7]  = 8 hex = 4 karakter pertama nama (bin2hex), X-padded
//   [8..15] = 8 hex = tanggal expired format YYYYMMDD as hex
//   [16..19]= 4 hex = HMAC-SHA256 singkat untuk verifikasi integritas
//
// PERBAIKAN: Format lama (16 char) gagal karena payload XOR lebih panjang
//            dari 12 hex yang tersedia → decode selalu return null.
//            Format baru 20 char menyimpan expired secara langsung sebagai
//            integer YYYYMMDD (hex), sehingga tidak ada truncation.
// ============================================================

define('LISENSI_SECRET', 'TKA_K3c4m4t4n_S3cr3t_2026!');
define('LISENSI_FILE',   __DIR__ . '/../.license');

// ── Generate key dari tanggal expired + nama instansi ────────
function lisensiGenerate(string $namaInstansi, string $expiredDate): string
{
    // Normalisasi nama: uppercase, strip non-alphanum, max 20 char
    $nama    = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($namaInstansi)), 0, 20));

    // Ambil 4 char pertama, pad dengan 'X' supaya selalu 4 char
    $namaKey = str_pad(substr($nama, 0, 4), 4, 'X');
    $namaHex = strtoupper(bin2hex($namaKey));   // 8 hex chars

    // Encode tanggal sebagai integer YYYYMMDD → 8 hex chars
    $dateInt = (int) str_replace('-', '', $expiredDate); // e.g. 20271231
    $dateHex = strtoupper(sprintf('%08X', $dateInt));    // 8 hex chars

    // HMAC 4-char untuk verifikasi integritas (anti-tamper)
    $hmac = strtoupper(
        substr(hash_hmac('sha256', $namaKey . '|' . $expiredDate, LISENSI_SECRET), 0, 4)
    ); // 4 hex chars

    // Total: 8 + 8 + 4 = 20 hex chars → XXXX-XXXX-XXXX-XXXX-XXXX
    $base = $namaHex . $dateHex . $hmac;
    return implode('-', str_split($base, 4));
}

// ── Decode key → [nama, expired] ─────────────────────────────
function lisensiDecode(string $key): ?array
{
    $raw = strtoupper(str_replace('-', '', trim($key)));

    // Harus 20 hex chars
    if (strlen($raw) !== 20) return null;
    if (!ctype_xdigit($raw)) return null;

    $namaHex = substr($raw, 0, 8);
    $dateHex = substr($raw, 8, 8);
    $hmacKey = substr($raw, 16, 4);

    // Recover nama (4 char, mungkin ada X padding)
    $namaKey  = strtoupper(hex2bin($namaHex));      // 4 chars
    $namaDisp = rtrim($namaKey, 'X');               // hapus padding untuk display

    // Recover tanggal dari integer YYYYMMDD
    $dateInt = hexdec($dateHex);
    $ds      = str_pad((string) $dateInt, 8, '0', STR_PAD_LEFT);
    $expiredDate = substr($ds, 0, 4) . '-' . substr($ds, 4, 2) . '-' . substr($ds, 6, 2);

    // Validasi format tanggal
    if (!checkdate((int)substr($ds,4,2), (int)substr($ds,6,2), (int)substr($ds,0,4))) {
        return null;
    }

    // Verifikasi HMAC
    $expectedHmac = strtoupper(
        substr(hash_hmac('sha256', $namaKey . '|' . $expiredDate, LISENSI_SECRET), 0, 4)
    );
    if (!hash_equals($hmacKey, $expectedHmac)) return null;

    return [
        'nama'    => $namaKey,   // 4-char key (internal)
        'expired' => $expiredDate,
    ];
}

// ── Simpan lisensi ke file ────────────────────────────────────
function lisensiSimpan(string $key): bool
{
    $decoded = lisensiDecode($key);
    $data    = json_encode([
        'key'      => strtoupper(str_replace([' ', "\t"], '', $key)),
        'aktivasi' => date('Y-m-d H:i:s'),
        'host'     => gethostname(),
        'nama'     => $decoded['nama'] ?? '',   // simpan nama dari decode
    ]);
    $enc = base64_encode($data . '|' . hash_hmac('sha256', $data, LISENSI_SECRET));
    return file_put_contents(LISENSI_FILE, $enc) !== false;
}

// ── Baca lisensi dari file ────────────────────────────────────
function lisensiFile(): ?array
{
    if (!file_exists(LISENSI_FILE)) return null;
    $raw = file_get_contents(LISENSI_FILE);
    $dec = base64_decode($raw);
    $pos = strrpos($dec, '|');
    if ($pos === false) return null;
    $data = substr($dec, 0, $pos);
    $sig  = substr($dec, $pos + 1);
    if (!hash_equals(hash_hmac('sha256', $data, LISENSI_SECRET), $sig)) return null;
    return json_decode($data, true);
}

// ── Cek status lisensi (main function) ───────────────────────
// Return: ['valid'=>bool, 'expired'=>bool, 'hari_sisa'=>int, 'nama'=>str, 'expired_date'=>str]
function lisensiCek(): array
{
    $empty = ['valid' => false, 'expired' => false, 'hari_sisa' => 0, 'nama' => '', 'expired_date' => ''];

    $file = lisensiFile();
    if (!$file || empty($file['key'])) return $empty;

    $decoded = lisensiDecode($file['key']);
    if (!$decoded) return $empty;

    $today    = date('Y-m-d');
    $expDate  = $decoded['expired'];
    $hariSisa = (int) ceil((strtotime($expDate) - strtotime($today)) / 86400);
    $expired  = $hariSisa < 0;

    // Nama: gunakan yang tersimpan di file (lebih deskriptif) jika ada
    $nama = !empty($file['nama']) ? $file['nama'] : $decoded['nama'];

    return [
        'valid'        => true,
        'expired'      => $expired,
        'hari_sisa'    => max(0, $hariSisa),
        'nama'         => $nama,
        'expired_date' => $expDate,
    ];
}

// ── Middleware: blokir akses jika lisensi tidak valid ─────────
function lisensiGuard(): void
{
    $excludes = ['/aktivasi.php', '/core/', '/config/', '/assets/'];
    $uri      = $_SERVER['REQUEST_URI'] ?? '';
    foreach ($excludes as $ex) {
        if (strpos($uri, $ex) !== false) return;
    }

    $status = lisensiCek();

    if (!$status['valid']) {
        $base = defined('BASE_URL') ? BASE_URL : '';
        header('Location: ' . $base . '/aktivasi.php');
        exit;
    }

    if ($status['expired']) {
        $base = defined('BASE_URL') ? BASE_URL : '';
        header('Location: ' . $base . '/aktivasi.php?expired=1');
        exit;
    }

    if ($status['hari_sisa'] <= 14) {
        $_SESSION['lisensi_warning'] = $status['hari_sisa'];
    } else {
        unset($_SESSION['lisensi_warning']);
    }
}
