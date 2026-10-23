<?php
// config/auth.php - Manajemen Autentikasi Pengguna & Sesi

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Memeriksa apakah pengguna sudah login
 */
function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . getBasePath() . 'index.php?page=login');
        exit;
    }
}

/**
 * Mengambil informasi pengguna yang sedang login
 */
function currentUser() {
    if (isset($_SESSION['user_id'])) {
        return [
            'id'           => $_SESSION['user_id'],
            'username'     => $_SESSION['username'] ?? '',
            'nama_lengkap' => $_SESSION['nama_lengkap'] ?? '',
            'nip'          => $_SESSION['nip'] ?? '',
            'role'         => $_SESSION['role'] ?? 'OPERATOR',
        ];
    }
    return null;
}

/**
 * Memeriksa apakah role saat ini memiliki akses
 */
function hasRole($roles) {
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    $user = currentUser();
    if (!$user) return false;
    return in_array($user['role'], $roles);
}

/**
 * Mengembalikan root path aplikasi web
 */
function getBasePath() {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $dir = dirname($scriptName);
    if (basename($dir) === 'cetak' || basename($dir) === 'modules' || basename(dirname($dir)) === 'modules') {
        $dir = dirname($dir);
        if (basename($dir) === 'modules') {
            $dir = dirname($dir);
        }
    }
    if ($dir === '/' || $dir === '\\' || $dir === '.') {
        return '/';
    }
    return rtrim($dir, '/') . '/';
}
