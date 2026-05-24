<?php
// ============================================================
// core/auth.php
// Autentikasi pengguna — TKA Kecamatan
//
// Skema tabel users:
//   id, username, password, role, sekolah_id
//
// Role yang dikenal:
//   admin_kecamatan → /admin/dashboard.php
//   sekolah         → /sekolah/dashboard.php
//   korektor        → /korektor/index.php
//   kolektor        → /kolektor/index.php
// ============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/session.php';
// Pastikan helper dimuat karena kita menggunakan fungsi redirect() di sini
require_once __DIR__ . '/helper.php'; 

/**
 * Proses login.
 * Role TIDAK dikirim dari form — dibaca langsung dari database.
 */
function login(string $username, string $password): array {
    global $conn;

    $stmt = $conn->prepare(
        "SELECT id, username, nama_lengkap, foto_profil, password, role, sekolah_id
         FROM users WHERE username = ? LIMIT 1"
    );
    if (!$stmt) return ['status' => false, 'message' => 'Kesalahan sistem. Coba lagi.'];

    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        $stmt->close();
        return ['status' => false, 'message' => 'Username atau password salah.'];
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    if (!password_verify($password, $user['password'])) {
        return ['status' => false, 'message' => 'Username atau password salah.'];
    }

    session_regenerate_id(true);
    $_SESSION['user_id']     = (int) $user['id'];
    $_SESSION['username']    = $user['username'];
    $_SESSION['nama']        = $user['nama_lengkap'] ?: $user['username'];
    $_SESSION['role']        = $user['role'];
    $_SESSION['sekolah_id']  = $user['sekolah_id'] ? (int)$user['sekolah_id'] : null;
    $_SESSION['foto_profil'] = $user['foto_profil'] ?? null;
    $_SESSION['logged_in']   = true;

    return ['status' => true, 'role' => $user['role']];
}

/**
 * FIX: Mengembalikan relative path (tanpa BASE_URL).
 * Biarkan fungsi redirect() yang menempelkan BASE_URL secara dinamis.
 */
function dashboardUrlByRole(string $role): string {
    switch ($role) {
        case 'admin_kecamatan': return '/admin/dashboard.php';
        case 'sekolah':         return '/sekolah/dashboard.php';
        case 'korektor':        return '/korektor/index.php';
        case 'kolektor':        return '/kolektor/index.php';
        default:                return '/login.php?error=role';
    }
}

/** Cek apakah user sudah login. */
function isLoggedIn(): bool {
    return !empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Wajib login; redirect ke login jika belum.
 * Opsional: paksa role tertentu (string atau array).
 */
function requireLogin($role = null): void {
    if (!isLoggedIn()) {
        // PERBAIKAN: Gunakan fungsi redirect() agar subfolder otomatis terdeteksi
        redirect('/login.php');
    }
    if ($role !== null) {
        $roles = (array) $role;
        if (!in_array($_SESSION['role'], $roles, true)) {
            // PERBAIKAN: Gunakan fungsi redirect()
            redirect(dashboardUrlByRole($_SESSION['role']));
        }
    }
}

/** Shortcut: izinkan admin_kecamatan ATAU sekolah. */
function requireAnyStaff(): void {
    requireLogin(['admin_kecamatan', 'sekolah']);
}

/** Shortcut: izinkan admin_kecamatan, korektor, ATAU kolektor. */
function requireKorektor(): void {
    requireLogin(['admin_kecamatan', 'korektor', 'kolektor']);
}

/** Mengembalikan data user saat ini dari session, atau null. */
function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    return [
        'id'          => $_SESSION['user_id'],
        'username'    => $_SESSION['username'],
        'nama'        => $_SESSION['nama'],
        'role'        => $_SESSION['role'],
        'sekolah_id'  => $_SESSION['sekolah_id'] ?? null,
        'foto_profil' => $_SESSION['foto_profil'] ?? null,
    ];
}

/** Hapus session dan redirect ke halaman login. */
function logout(): void {
    session_unset();
    session_destroy();
    // PERBAIKAN: Gunakan fungsi redirect() bawaan helper
    redirect('/login.php?logout=1');
}