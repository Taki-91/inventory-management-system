
<?php

require_once __DIR__ . '/config/database.php';

$username = 'admin';
$password = 'Admin123!';
$fullName = 'Administrator';
$role = 'admin';

$check = $pdo->prepare(
    'SELECT id FROM users WHERE username = :username'
);
$check->execute(['username' => $username]);

if ($check->fetch()) {
    exit('Akun admin sudah tersedia.');
}

$sql = 'INSERT INTO users
        (username, password_hash, full_name, role)
        VALUES (:username, :password_hash, :full_name, :role)';

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'username' => $username,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'full_name' => $fullName,
    'role' => $role
]);

echo 'Akun admin berhasil dibuat.';
