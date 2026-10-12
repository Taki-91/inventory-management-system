<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = $pageTitle ?? 'Inventory Management System';
$currentUser = $_SESSION['user']['username'] ?? 'Pengguna';
?>

<header class="app-header">
    <div class="app-header__brand">
        <strong>Inventory Management System</strong>
    </div>

    <div class="app-header__info">
        <span>
            <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>
        </span>
        <span>
            Login sebagai:
            <?= htmlspecialchars($currentUser, ENT_QUOTES, 'UTF-8') ?>
        </span>
    </div>
</header>