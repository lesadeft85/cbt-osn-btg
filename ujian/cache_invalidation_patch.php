<?php
// ============================================================
// PATCH untuk core/helper.php
// Tambahkan fungsi ini ke helper.php yang sudah ada.
//
// FIX #4: Cache session (_jawaban_cache) tidak punya mekanisme
//         invalidasi saat data diubah langsung dari sisi admin.
//         Solusi: simpan "cache version" per ujian_id di tabel ujian
//         (kolom cache_version INT DEFAULT 0).
//         Setiap kali admin mengubah jawaban → increment cache_version.
//         ajax_soal.php membandingkan versi sebelum pakai cache.
// ============================================================

/**
 * Invalidasi cache jawaban session untuk satu ujian.
 * Dipanggil dari:
 *   - Panel admin saat edit/hapus jawaban peserta
 *   - Selesai ujian (optional, session anyway mati)
 *
 * @param mysqli $conn
 * @param int    $ujianId
 */
function invalidateJawabanCache(mysqli $conn, int $ujianId): void
{
    // Increment versi cache di DB — tanpa prepared statement karena
    // $ujianId sudah di-cast int (aman)
    $conn->query(
        "UPDATE ujian SET cache_version = COALESCE(cache_version, 0) + 1
         WHERE id = $ujianId"
    );
}

/**
 * Cek apakah cache session masih valid untuk ujian ini.
 * Sisipkan di awal ajax_soal.php sebelum pakai $_SESSION['_jawaban_cache'].
 *
 * @param mysqli $conn
 * @param int    $ujianId
 * @return bool  true = cache valid, false = perlu rebuild dari DB
 */
function isJawabanCacheValid(mysqli $conn, int $ujianId): bool
{
    $res = $conn->query(
        "SELECT COALESCE(cache_version, 0) AS v FROM ujian WHERE id = $ujianId LIMIT 1"
    );
    if (!$res) return false;
    $dbVersion = (int)($res->fetch_assoc()['v'] ?? 0);

    $sessVersion = (int)($_SESSION['_jawaban_cache_version'] ?? -1);
    if ($sessVersion !== $dbVersion) {
        // Versi tidak cocok → hapus cache lama, simpan versi baru
        unset($_SESSION['_jawaban_cache'], $_SESSION['_teks_jawaban_cache']);
        $_SESSION['_jawaban_cache_version'] = $dbVersion;
        return false;
    }
    return true;
}

// ── Cara pakai di ajax_soal.php ───────────────────────────────
// Ganti blok "Fallback: cache belum ada" dengan:
//
//   if (!isset($_SESSION['_jawaban_cache']) || !isJawabanCacheValid($conn, $ujianId)) {
//       $_SESSION['_jawaban_cache']      = [];
//       $_SESSION['_teks_jawaban_cache'] = [];
//       $jrAll = $conn->query(
//           "SELECT soal_id, jawaban, teks_jawaban FROM jawaban
//            WHERE ujian_id=$ujianId AND peserta_id=$pesertaId"
//       );
//       if ($jrAll) while ($j = $jrAll->fetch_assoc()) {
//           $_SESSION['_jawaban_cache'][$j['soal_id']] = $j['jawaban'];
//           if ($j['teks_jawaban'] !== null)
//               $_SESSION['_teks_jawaban_cache'][$j['soal_id']] = $j['teks_jawaban'];
//       }
//   }
//
// ── Cara pakai di panel admin saat edit jawaban ───────────────
//   invalidateJawabanCache($conn, $ujianId);
