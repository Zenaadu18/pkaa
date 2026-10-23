<?php
// layouts/header.php - Header Desktop Topbar JANGKAR NTT
$currentUser = currentUser();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : '' ?>JANGKAR NTT | BPAD Prov. Nusa Tenggara Timur</title>
  <link rel="stylesheet" href="assets/css/desktop.css">
  <link rel="stylesheet" href="assets/css/print.css" media="print">
</head>
<body>
<div class="app-wrapper">
  <!-- Toast Container untuk Notifikasi Modern -->
  <div id="toastContainer"></div>

  <!-- Topbar Header Desktop -->
  <header class="desktop-topbar">
    <div class="topbar-brand">
      <!-- Tombol Minimized / Expand Sidebar -->
      <button type="button" class="btn-sidebar-toggle" onclick="toggleSidebar()" title="Perkecil / Perluas Menu Sidebar">
        <?= renderIcon('menu', '', 16) ?>
      </button>

      <div style="display:flex; align-items:center; justify-content:center; padding: 2px;">
        <img src="assets/img/logontt.png" alt="Logo NTT" style="height: 38px; width: auto; filter: drop-shadow(0 1px 2px rgba(0,0,0,0.4));">
      </div>
      <div class="topbar-titles">
        <h1>
          JANGKAR NTT 
          <span class="badge-system">PAJAK KENDARAAN AIR PROV. NTT</span>
        </h1>
        <p>Jaringan Administrasi Notifikasi & Ketetapan Pajak Kendaraan Air &bull; BPAD Prov. Nusa Tenggara Timur</p>
      </div>
    </div>

    <div class="topbar-actions">
      <!-- Live Clock -->
      <div class="topbar-clock" id="liveClock">
        <?= date('d/m/Y H:i:s') ?>
      </div>

      <!-- Layar Bantu Shortcut Button -->
      <button type="button" class="btn btn-secondary btn-sm" onclick="bukaLayarBantu()" title="Pencarian Cepat Data Kapal (Ctrl+B)">
        <?= renderIcon('search', '', 14) ?>
        <span>Layar Bantu</span>
        <span class="btn-shortcut-hint">Ctrl+B</span>
      </button>

      <!-- User Profile -->
      <div class="topbar-user">
        <div class="user-avatar" style="background:#0284c7;">
          <?= strtoupper(substr($currentUser['nama_lengkap'] ?? 'U', 0, 1)) ?>
        </div>
        <div class="user-info">
          <span class="user-name"><?= htmlspecialchars($currentUser['nama_lengkap'] ?? 'Operator') ?></span>
          <span class="user-role"><?= htmlspecialchars($currentUser['role'] ?? 'OPERATOR') ?></span>
        </div>
      </div>

      <!-- Logout -->
      <a href="index.php?page=logout" class="btn btn-danger btn-sm" title="Keluar dari Aplikasi" onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem?')">
        <?= renderIcon('log-out', '', 14) ?>
        <span>Keluar</span>
      </a>
    </div>
  </header>

  <div class="app-body">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    
    <!-- Main Content Container -->
    <main class="desktop-content">
