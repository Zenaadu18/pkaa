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
