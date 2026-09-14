<?php require_once 'auth.php'; requireLogin(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StockTrack - Barangay Puguis</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/stocktrack/assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar app-navbar navbar-expand-lg navbar-dark px-3">
    <a class="navbar-brand fw-bold" href="/stocktrack/dashboard.php">
        <i class="bi bi-box-seam me-2"></i>StockTrack
    </a>
    <span class="navbar-text ms-auto text-white me-3">
        <i class="bi bi-person-circle me-1"></i>
        <?php echo htmlspecialchars($_SESSION['full_name']); ?> 
        (<?php echo ucfirst($_SESSION['role']); ?>)
    </span>
    <a href="/stocktrack/logout.php" class="btn btn-outline-light btn-sm">
        <i class="bi bi-box-arrow-right me-1"></i>Logout
    </a>
</nav>
<div class="d-flex">
