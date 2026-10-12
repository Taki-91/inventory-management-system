
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
    $pdo->beginTransaction();

    $check = $pdo->prepare(
        'SELECT id FROM products WHERE id = :id FOR UPDATE'
    );
    $check->execute(['id' => $id]);

    if (!$check->fetch()) {
        $pdo->rollBack();
        header('Location: index.php?error=notfound');
        exit;
    }

    $history = $pdo->prepare(
        'SELECT COUNT(*) FROM stock_movements WHERE product_id = :id'
    );
    $history->execute(['id' => $id]);

    if ((int) $history->fetchColumn() > 0) {
        $pdo->rollBack();
        header('Location: index.php?error=has_history');
        exit;
    }

    $relations = $pdo->prepare(
        'SELECT COUNT(*) FROM product_suppliers WHERE product_id = :id'
    );
    $relations->execute(['id' => $id]);

    if ((int) $relations->fetchColumn() > 0) {
        $pdo->rollBack();
        header('Location: index.php?error=has_suppliers');
        exit;
    }

    $delete = $pdo->prepare(
        'DELETE FROM products WHERE id = :id'
    );
    $delete->execute(['id' => $id]);

    $pdo->commit();

    header('Location: index.php?success=deleted');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    header('Location: index.php?error=delete_failed');
    exit;
}
