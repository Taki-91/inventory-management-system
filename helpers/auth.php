
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin(): void
{
    if (!isset($_SESSION['user'])) {
        header('Location: /inventory-app/public/login.php');
        exit;
    }
}

function requireRole(string $role): void
{
    requireLogin();

    if ($_SESSION['user']['role'] !== $role) {
        http_response_code(403);
        exit('Akses ditolak. Kamu tidak memiliki izin.');
    }
}
