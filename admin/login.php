<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/helpers.php';

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $errors[] = 'Ange användarnamn och lösenord.';
    } else {
        $stmt = $pdo->prepare('SELECT id, password_hash FROM admin_users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $username;
            header('Location: index.php');
            exit;
        } else {
            $errors[] = 'Fel användarnamn eller lösenord.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sv">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Logga in - hhk-skylt admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Open+Sans:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
<div class="login-card">
    <div class="login-panel login-panel-form">
        <p class="login-eyebrow">
            <img src="/assets/logga.png" alt="Hotell Höga Kusten" class="login-logo">
        </p>

        <h1>Logga in</h1>
        <p class="login-subtitle">Logga in för att hantera konferensbokningarna på skylten.</p>

        <?php render_errors($errors); ?>

        <form method="post" action="login.php" class="login-form">
            <p>
                <label for="username">Användarnamn</label>
                <input type="text" id="username" name="username" required>
            </p>
            <p>
                <label for="password">Lösenord</label>
                <input type="password" id="password" name="password" required>
            </p>
            <button type="submit" class="btn-primary">Logga in</button>
        </form>

        <p class="login-help">Kontakta GT Konsult om du glömt uppgifterna.</p>
    </div>

    <div class="login-panel login-panel-image">
        <img src="/assets/login.jpg" alt="" class="login-hero">
    </div>
</div>
</body>
</html>
