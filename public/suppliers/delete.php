
<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';

requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

verifyCsrfToken();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header('Location: index.php?error=invalid');
    exit;
}

try {
    $stmt = $pdo->prepare(
        'DELETE FROM suppliers WHERE id = :id'
    );

    $stmt->execute(['id' => $id]);

    if ($stmt->rowCount() > 0) {
        header('Location: index.php?success=deleted');
    } else {
        header('Location: index.php?error=notfound');
    }

    exit;
} catch (PDOException $e) {
    error_log($e->getMessage());

    header('Location: index.php?error=related');
    exit;
}
