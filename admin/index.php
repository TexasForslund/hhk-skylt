<?php
require_once __DIR__ . '/auth.php';
?>
<!DOCTYPE html>
<html lang="sv">
<head>
<meta charset="UTF-8">
<title>Admin - hhk-skylt</title>
</head>
<body>
<h1>Adminpanel</h1>
<p>
    Inloggad som <?= htmlspecialchars($_SESSION['admin_username'] ?? '') ?>.
    <a href="logout.php">Logga ut</a>
</p>
<p>(Bokningshantering byggs i nästa steg.)</p>
</body>
</html>
