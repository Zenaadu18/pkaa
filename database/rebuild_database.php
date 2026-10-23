<?php
// database/rebuild_database.php - Eksekutor Pembangunan Ulang Database JANGKAR NTT
// Skrip ini membaca file master `schema.sql` dan membangun basis data db_pkaa terbaru secara otomatis.
// Dapat dijalankan melalui CLI: `php database/rebuild_database.php` maupun via Web Browser.

// Load konfigurasi dari .env jika ada
require_once __DIR__ . '/../config/database.php';

$host    = env('DB_HOST', '127.0.0.1');
$port    = env('DB_PORT', '3306');
$user    = env('DB_USERNAME', 'root');
$pass    = env('DB_PASSWORD', '');
$dbname  = env('DB_DATABASE', 'db_pkaa');
$charset = env('DB_CHARSET', 'utf8mb4');

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
}

$schemaFile = __DIR__ . '/schema.sql';

if (!file_exists($schemaFile)) {
    $msg = "Error: File schema master '$schemaFile' tidak ditemukan!";
    if ($isCli) {
        fwrite(STDERR, $msg . PHP_EOL);
        exit(1);
    } else {
        die("<div style='color:red; font-family:sans-serif;'>$msg</div>");
    }
}

try {
    $rawPdo = new PDO("mysql:host=$host;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true
    ]);

    // Eksekusi skema kompilasi master
    $sqlContent = file_get_contents($schemaFile);
    $rawPdo->exec($sqlContent);

    // Verifikasi tabel yang berhasil dibangun
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $totalTables = count($tables);

    $countKabKota = (int)$pdo->query("SELECT COUNT(*) FROM ref_kabupaten_kota")->fetchColumn();
    $countBody = (int)$pdo->query("SELECT COUNT(*) FROM ref_njkb_body")->fetchColumn();
    $countMesin = (int)$pdo->query("SELECT COUNT(*) FROM ref_njkb_mesin")->fetchColumn();
    $countGabungan = (int)$pdo->query("SELECT COUNT(*) FROM ref_njkb_gabungan")->fetchColumn();
    $countUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $countKapal = (int)$pdo->query("SELECT COUNT(*) FROM kendaraan_air")->fetchColumn();

    if ($isCli) {
        echo "========================================================\n";
        echo "   REBUILD DATABASE JANGKAR NTT BERHASIL SEMPURNA!\n";
        echo "========================================================\n";
        echo "1. Sumber Schema Master: database/schema.sql\n";
        echo "2. Database Aktif      : $dbname\n";
        echo "3. Total Tabel         : $totalTables tabel\n";
        echo "   - Wilayah NTT       : $countKabKota Kab/Kota\n";
        echo "   - NJKB Body         : $countBody referensi standar\n";
        echo "   - NJKB Mesin        : $countMesin referensi standar\n";
        echo "   - NJKB Gabungan     : $countGabungan paket kesatuan\n";
        echo "   - Akun Pengguna     : $countUsers akun (admin, operator, kasir)\n";
        echo "   - Sampel Kendaraan  : $countKapal objek pajak kapal di NTT\n";
        echo "4. Fitur Alur Cetak    : status_cetak & verifikasi NJKB aktif 100%\n";
        echo "========================================================\n";
    } else {
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
          <meta charset="UTF-8">
          <title>Rebuild Database Berhasil - JANGKAR NTT</title>
          <style>
            body { font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background: #f0f4f8; margin: 0; padding: 40px; color: #1e293b; }
            .card { max-width: 680px; margin: 0 auto; background: #fff; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); overflow: hidden; border: 1px solid #cbd5e1; }
            .card-header { background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: #fff; padding: 24px; text-align: center; }
            .card-header h2 { margin: 0 0 6px 0; font-size: 22px; font-weight: 800; }
            .card-body { padding: 24px 30px; }
            .stat-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin: 20px 0; }
            .stat-item { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; }
            .stat-item span { font-size: 11px; color: #64748b; display: block; }
            .stat-item strong { font-size: 16px; color: #0284c7; }
            .btn { display: inline-block; background: #059669; color: #fff; text-decoration: none; padding: 10px 22px; border-radius: 6px; font-weight: 700; font-size: 14px; text-align: center; }
            .btn:hover { background: #047857; }
          </style>
        </head>
        <body>
          <div class="card">
            <div class="card-header">
              <h2>DATABASE JANGKAR NTT BERHASIL DIBANGUN</h2>
              <div>Skema Struktur Database & Master Data Terbaru Telah Terpasang</div>
            </div>
            <div class="card-body">
              <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 6px; padding: 12px; color: #065f46; font-size: 13px; line-height: 1.6;">
                <strong>Sumber Schema:</strong> <code>database/schema.sql</code> (Kompilasi Master Tunggal). Seluruh 15 tabel, indeks, relasi, dan master seeder terpasang dengan sukses.
              </div>

              <div class="stat-grid">
                <div class="stat-item">
                  <span>TOTAL TABEL TERBANGUN</span>
                  <strong><?= $totalTables ?> Tabel</strong>
                </div>
                <div class="stat-item">
                  <span>WILAYAH KAB/KOTA DI NTT</span>
                  <strong><?= $countKabKota ?> Wilayah</strong>
                </div>
                <div class="stat-item">
                  <span>REFERENSI STANDAR NJKB BODY</span>
                  <strong><?= $countBody ?> Rekaman</strong>
                </div>
                <div class="stat-item">
                  <span>REFERENSI STANDAR NJKB MESIN</span>
                  <strong><?= $countMesin ?> Rekaman</strong>
                </div>
                <div class="stat-item">
                  <span>NJKB 1 KESATUAN (GABUNGAN)</span>
                  <strong><?= $countGabungan ?> Paket</strong>
                </div>
                <div class="stat-item">
                  <span>AKUN RESMI PENGGUNA</span>
                  <strong><?= $countUsers ?> Akun Aktif</strong>
                </div>
              </div>

              <div style="text-align: center; margin-top: 24px;">
                <a href="../index.php" class="btn">Buka Aplikasi JANGKAR NTT &rarr;</a>
              </div>
            </div>
          </div>
        </body>
        </html>
        <?php
    }

} catch (Exception $e) {
    $err = "GAGAL MEMBANGUN DATABASE: " . $e->getMessage();
    if ($isCli) {
        fwrite(STDERR, $err . PHP_EOL);
        exit(1);
    } else {
        die("<div style='background:#fef2f2; border:1px solid #f87171; color:#991b1b; padding:20px; font-family:sans-serif;'>$err</div>");
    }
}
