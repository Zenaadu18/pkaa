<?php
// config/database.php - Koneksi Database JANGKAR NTT Terpadu (.env Supported)

if (!function_exists('env')) {
    /**
     * Helper mengambil nilai konfigurasi environment
     */
    function env($key, $default = null) {
        // Cek $_ENV, $_SERVER, atau getenv()
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        $val = getenv($key);
        if ($val !== false) {
            return $val;
        }
        return $default;
    }
}

// Parser native file .env jika file .env ada
if (!isset($GLOBALS['__env_loaded'])) {
    $envPath = __DIR__ . '/../.env';
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                [$k, $v] = explode('=', $line, 2);
                $k = trim($k);
                $v = trim($v);
                // Hapus tanda petik jika ada
                $v = trim($v, "\"'");
                $_ENV[$k] = $v;
                putenv("$k=$v");
            }
        }
    }
    $GLOBALS['__env_loaded'] = true;
}

// Konfigurasi koneksi dari .env dengan fallback aman
$host    = env('DB_HOST', '127.0.0.1');
$port    = env('DB_PORT', '3306');
$user    = env('DB_USERNAME', 'root');
$pass    = env('DB_PASSWORD', '');
$dbname  = env('DB_DATABASE', 'db_pkaa');
$charset = env('DB_CHARSET', 'utf8mb4');

if (!isset($GLOBALS['pdo'])) {
    try {
        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        // Coba buat database dari master schema.sql jika database belum tersedia
        try {
            $tempPdo = new PDO("mysql:host=$host;port=$port;charset=$charset", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_MULTI_STATEMENTS => true
            ]);
            
            $schemaPath = __DIR__ . '/../database/schema.sql';
            if (file_exists($schemaPath)) {
                $tempPdo->exec(file_get_contents($schemaPath));
            }
            
            $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=$charset", $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false
            ]);
        } catch (PDOException $ex) {
            die("Koneksi Database Gagal: " . $ex->getMessage());
        }
    }

    $GLOBALS['pdo'] = $pdo;
} else {
    $pdo = $GLOBALS['pdo'];
}

if (!function_exists('ensureSkpdSchemaUpdated')) {
    /**
     * Memastikan kolom status pembatalan dan log audit SKPD tersedia
     */
    function ensureSkpdSchemaUpdated($pdo) {
        if (!empty($GLOBALS['__skpd_schema_checked'])) return;
        $GLOBALS['__skpd_schema_checked'] = true;
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM transaksi_skpd LIKE 'status'");
            if (!$stmt->fetch()) {
                $pdo->exec("
                    ALTER TABLE transaksi_skpd 
                    ADD COLUMN status ENUM('SAH', 'BATAL') NOT NULL DEFAULT 'SAH' AFTER total_bayar,
                    ADD COLUMN tg_akhir_sebelumnya DATE NULL AFTER status,
                    ADD COLUMN alasan_batal TEXT NULL AFTER tg_akhir_sebelumnya,
                    ADD COLUMN dibatalkan_oleh_id INT NULL AFTER alasan_batal,
                    ADD COLUMN tgl_batal DATETIME NULL AFTER dibatalkan_oleh_id
                ");
            }
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS log_cetak_skpd (
                  id INT AUTO_INCREMENT PRIMARY KEY,
                  no_skpd VARCHAR(50) NOT NULL,
                  no_superpkaa VARCHAR(50) NOT NULL,
                  no_objek_pajak VARCHAR(30) NOT NULL,
                  nama_kapal VARCHAR(150) NOT NULL,
                  nama_pemilik VARCHAR(150) NOT NULL,
                  dicetak_oleh_id INT NOT NULL,
                  dicetak_oleh_nama VARCHAR(100) NOT NULL,
                  ip_address VARCHAR(50) NULL,
                  tgl_cetak DATETIME DEFAULT CURRENT_TIMESTAMP,
                  keterangan VARCHAR(255) DEFAULT 'Cetak Lembar Resmi SKPD',
                  INDEX idx_log_skpd (no_skpd),
                  INDEX idx_log_skpd_nopd (no_objek_pajak)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (Exception $e) {
            // Lewati jika belum inisialisasi tabel
        }
    }
}

ensureSkpdSchemaUpdated($pdo);

if (!function_exists('getDB')) {
    /**
     * Mengambil instance tunggal PDO Database
     */
    function getDB() {
        global $pdo;
        return $pdo;
    }
}

return $pdo;
