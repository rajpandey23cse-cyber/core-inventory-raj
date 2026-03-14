<?php
$user = currentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'CoreInventory') ?> | CoreInventory</title>
    <link rel="stylesheet" href="/inventory/assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <i class="fa-solid fa-warehouse"></i>
        <span>CoreInventory</span>
    </div>
    <nav class="sidebar-nav">
        <ul>
            <li class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <a href="/inventory/dashboard.php"><i class="fa fa-gauge-high"></i><span>Dashboard</span></a>
            </li>
            <li class="nav-section">INVENTORY</li>
            <li class="<?= $currentPage === 'products' ? 'active' : '' ?>">
                <a href="/inventory/products.php"><i class="fa fa-box"></i><span>Products</span></a>
                <!-- <a href="/products.php"><i class="fa fa-box"></i><span>Products</span></a> -->
            </li>
            <li class="<?= $currentPage === 'receipts' ? 'active' : '' ?>">
                <a href="/inventory/receipts.php"><i class="fa fa-truck-ramp-box"></i><span>Receipts</span></a>
            </li>
            <li class="<?= $currentPage === 'deliveries' ? 'active' : '' ?>">
                <a href="/inventory/deliveries.php"><i class="fa fa-truck-fast"></i><span>Deliveries</span></a>
            </li>
            <li class="<?= $currentPage === 'transfers' ? 'active' : '' ?>">
                <a href="/inventory/transfers.php"><i class="fa fa-right-left"></i><span>Transfers</span></a>
            </li>
            <li class="<?= $currentPage === 'adjustments' ? 'active' : '' ?>">
                <a href="/inventory/adjustments.php"><i class="fa fa-sliders"></i><span>Adjustments</span></a>
            </li>
            <li class="<?= $currentPage === 'history' ? 'active' : '' ?>">
                <a href="/inventory/history.php"><i class="fa fa-clock-rotate-left"></i><span>Move History</span></a>
            </li>
            <li class="nav-section">MASTER DATA</li>
            <li class="<?= $currentPage === 'warehouses' ? 'active' : '' ?>">
                <a href="/inventory/warehouses.php"><i class="fa fa-building"></i><span>Warehouses</span></a>
            </li>
            <li class="<?= $currentPage === 'suppliers' ? 'active' : '' ?>">
                <a href="/inventory/suppliers.php"><i class="fa fa-handshake"></i><span>Suppliers</span></a>
            </li>
            <li class="<?= $currentPage === 'customers' ? 'active' : '' ?>">
                <a href="/inventory/customers.php"><i class="fa fa-users"></i><span>Customers</span></a>
            </li>
            <li class="nav-section">ACCOUNT</li>
            <li class="<?= $currentPage === 'profile' ? 'active' : '' ?>">
                <a href="/inventory/profile.php"><i class="fa fa-user-gear"></i><span>Profile</span></a>
            </li>
            <?php if ($user['role'] === 'admin'): ?>
            <li class="<?= $currentPage === 'settings' ? 'active' : '' ?>">
                <a href="/inventory/settings.php"><i class="fa fa-gear"></i><span>Settings</span></a>
            </li>
            <?php endif; ?>
            <li>
                <a href="/inventory/includes/auth.php?action=logout"><i class="fa fa-right-from-bracket"></i><span>Logout</span></a>
            </li>
        </ul>
    </nav>
</aside>
<!-- Topbar -->
<div class="main-wrapper">
<header class="topbar">
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fa fa-bars"></i></button>
    <div class="topbar-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></div>
    <div class="topbar-right">
        <div class="topbar-user" onclick="toggleUserMenu()">
            <div class="user-avatar">
                <?php if ($user['avatar']): ?>
                    <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="">
                <?php else: ?>
                    <span><?= strtoupper(substr($user['name'], 0, 1)) ?></span>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <span class="user-name"><?= htmlspecialchars($user['name']) ?></span>
                <span class="user-role"><?= ucfirst($user['role']) ?></span>
            </div>
            <i class="fa fa-chevron-down"></i>
            <div class="user-dropdown" id="userDropdown">
                <a href="/inventory/profile.php"><i class="fa fa-user"></i> Profile</a>
                <a href="/inventory/includes/auth.php?action=logout"><i class="fa fa-right-from-bracket"></i> Logout</a>
            </div>
        </div>
    </div>
</header>
<main class="main-content">
