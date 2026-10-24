<?php
// modules/auth/login.php - Halaman Login JANGKAR NTT

if (isset($_SESSION['user_id'])) {
    header('Location: index.php?page=dashboard');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Username dan kata sandi wajib diisi.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        $isValid = false;
        if ($user && password_verify($password, $user['password'])) {
            $isValid = true;
        }

        if ($isValid) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['nip'] = $user['nip'];
            $_SESSION['role'] = $user['role'];

            $upd = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $upd->execute([$user['id']]);

            header('Location: index.php?page=dashboard');
            exit;
        } else {
            $error = 'Username atau kata sandi tidak valid / akun dinonaktifkan.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - JANGKAR NTT | Pajak Kendaraan di Atas Air</title>
  <link rel="stylesheet" href="assets/css/desktop.css">
</head>
<body class="login-page">
  <div class="login-card" style="width: 100%; max-width: 440px; margin: 16px;">
    <div class="login-header">
      <div style="margin-bottom: 10px;">
        <img src="assets/img/logontt.png" alt="Logo Pemprov NTT" style="height: 72px; width: auto; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.5));">
      </div>
      <h2 style="font-size: 20px; font-weight: 800; letter-spacing: 1px; color: #fbbf24;">JANGKAR NTT</h2>
      <div style="font-size: 11px; font-weight: 600; color: #e2e8f0; margin-top: 2px;">
        JARINGAN ADMINISTRASI NOTIFIKASI & KETETAPAN PAJAK KENDARAAN AIR
      </div>
      <p style="font-size: 10.5px; color: #94a3b8; margin-top: 5px;">
        BADAN PENDAPATAN DAN ASET DAERAH<br>PROVINSI NUSA TENGGARA TIMUR
      </p>
    </div>

    <form method="POST" action="index.php?page=login" class="login-body">
      <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
          <?= renderIcon('alert-triangle', '', 16) ?>
          <span><?= htmlspecialchars($error) ?></span>
        </div>
      <?php endif; ?>

      <div class="form-group">
        <label class="form-label" for="username">Nama Pengguna (Username)</label>
        <div class="input-group">
          <span class="input-prefix"><?= renderIcon('user', '', 14) ?></span>
          <input type="text" name="username" id="username" class="form-control" placeholder="Contoh: admin / operator / kasir" required autofocus value="<?= htmlspecialchars($_POST['username'] ?? 'admin') ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Kata Sandi (Password)</label>
        <div class="input-group">
          <span class="input-prefix"><?= renderIcon('key', '', 14) ?></span>
          <input type="password" name="password" id="password" class="form-control" placeholder="Masukkan kata sandi" required value="admin123">
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="justify-content: center; height: 40px; font-size: 13px; font-weight: 700; margin-top: 8px; background: #0284c7; border-color: #0369a1;">
        <?= renderIcon('log-out', '', 16) ?>
        <span>MASUK KE SISTEM JANGKAR NTT</span>
      </button>

      <!-- Akun Demo Cepat -->
      <div style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 6px; padding: 10px; margin-top: 4px;">
        <div style="font-size: 11px; font-weight: 700; color: #475569; margin-bottom: 6px;">PILIHAN AKUN OPERATOR PENGUJIAN:</div>
        <div style="display: flex; gap: 6px;">
          <button type="button" class="btn btn-secondary btn-sm" style="flex:1;" onclick="setLogin('admin', 'admin123')">Admin</button>
          <button type="button" class="btn btn-secondary btn-sm" style="flex:1;" onclick="setLogin('operator', 'operator123')">Operator</button>
          <button type="button" class="btn btn-secondary btn-sm" style="flex:1;" onclick="setLogin('kasir', 'kasir123')">Kasir</button>
        </div>
      </div>
    </form>

    <div class="login-footer">
      BPAD Provinsi Nusa Tenggara Timur &bull; JANGKAR NTT Versi 2.0
    </div>
  </div>

  <script>
    function setLogin(u, p) {
      document.getElementById('username').value = u;
      document.getElementById('password').value = p;
    }
  </script>
</body>
</html>
