<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 16:48:46 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2026-01-04 13:24:02
*/
// 2026-01-03: Aggiunto link Info nel menu di navigazione
// 2026-01-04: Aggiunto link per installazione degli aggiornamenti

?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Magazzino Componenti</title>
    <link rel="icon" href="/magazzino/favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="/magazzino/favicon.ico" type="image/x-icon">
    <link href="/magazzino/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/magazzino/assets/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/magazzino/assets/css/jquery-ui.css">
    <script src="/magazzino/assets/js/jquery-3.6.0.min.js"></script>
    <script src="/magazzino/assets/js/jquery-ui.min.js"></script>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand" href="/magazzino/index.php">
      <img src="/magazzino/assets/img/logo.jpg" alt="logo" class="me-2" style="height:40px;">
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
      data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false"
      aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto">
        <?php if (isset($_SESSION['user_id'])): ?>
          <li class="nav-item">
            <a class="nav-link" href="/magazzino/warehouse/components.php">
              <i class="fa-solid fa-microchip me-1"></i>Componenti
            </a>
          </li>

          <?php if (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="magazzinoDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fa-solid fa-warehouse me-1"></i>Magazzino
            </a>
            <ul class="dropdown-menu" aria-labelledby="magazzinoDropdown">
              <li>
                <a class="dropdown-item" href="/magazzino/warehouse/locations.php">
                  <i class="fa-solid fa-location-dot me-2 text-secondary"></i>Posizioni
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="/magazzino/warehouse/compartments.php">
                  <i class="fa-solid fa-boxes-stacked me-2 text-secondary"></i>Comparti
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="/magazzino/warehouse/categories.php">
                  <i class="fa-solid fa-tags me-2 text-secondary"></i>Categorie
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="/magazzino/warehouse/bulk_move_components.php">
                  <i class="fa-solid fa-right-left me-2 text-secondary"></i>Sposta componenti
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="/magazzino/warehouse/bulk_swap_components.php">
                  <i class="fa-solid fa-retweet me-2 text-secondary"></i>Scambia componenti
                </a>
              </li>
            </ul>
          </li>
          <?php endif; ?>
        <?php endif; ?>
      </ul>

      <ul class="navbar-nav ms-auto">
        <?php if (isset($_SESSION['user_id'])): ?>
          <li class="nav-item">
            <a class="nav-link" href="/magazzino/info.php">
              <i class="fa-solid fa-circle-info me-1"></i>Info
            </a>
          </li>
          <?php if (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
          <li class="nav-item">
            <a class="nav-link" href="/magazzino/update/index.php">
              <i class="fa-solid fa-download me-1"></i>Aggiornamento
            </a>
          </li>
          <?php endif; ?>
          <li class="nav-item">
            <a class="nav-link" href="/magazzino/logout.php">
              <i class="fa-solid fa-right-from-bracket me-1"></i>Logout (<?= htmlspecialchars($_SESSION['username']) ?>)
            </a>
          </li>
        <?php else: ?>
          <li class="nav-item">
            <a class="nav-link" href="/magazzino/login.php">
              <i class="fa-solid fa-right-to-bracket me-1"></i>Login
            </a>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<div class="container mt-2">
