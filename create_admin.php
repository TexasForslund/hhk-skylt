<?php
// Engångsskript för att skapa den första (och enda) raden i admin_users.
// Körs bara från kommandoraden, aldrig via webbläsaren.
//
// Användning: php create_admin.php <användarnamn> <lösenord>

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Detta skript får bara köras från kommandoraden.');
}

require_once __DIR__ . '/db.php';

$username = $argv[1] ?? null;
$password = $argv[2] ?? null;

if (!$username || !$password) {
    fwrite(STDERR, "Användning: php create_admin.php <användarnamn> <lösenord>\n");
    exit(1);
}

$stmt = $pdo->prepare('SELECT id FROM admin_users WHERE username = ?');
$stmt->execute([$username]);

if ($stmt->fetch()) {
    fwrite(STDERR, "Användarnamnet finns redan.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)');
$stmt->execute([$username, $hash]);

echo "Admin-användaren '{$username}' skapades.\n";
