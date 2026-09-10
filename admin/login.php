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
<title>Logga in - hhk-skylt admin</title>
</head>
<body>
<h1>Logga in</h1>

<?php render_errors($errors); ?>

<form method="post" action="login.php">
    <p>
        <label>Användarnamn:<br>
        <input type="text" name="username" required></label>
    </p>
    <p>
        <label>Lösenord:<br>
        <input type="password" name="password" required></label>
    </p>
    <p><button type="submit">Logga in</button></p>
</form>
</body>
</html>
