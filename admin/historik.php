<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';

$now = date('Y-m-d H:i:s');

$stmt = $pdo->prepare('
    SELECT b.id, b.company_name, b.company_logo, r.name AS room_name, b.start_time, b.end_time
    FROM bookings b
    LEFT JOIN rooms r ON r.id = b.room_id
    WHERE b.end_time < ?
    ORDER BY b.end_time DESC
');
$stmt->execute([$now]);
$bookings = $stmt->fetchAll();

$return = 'historik';
?>
<!DOCTYPE html>
<html lang="sv">
<head>
<meta charset="UTF-8">
<title>Historik - hhk-skylt admin</title>
</head>
<body>
<h1>Historik</h1>
<p>
    Inloggad som <?= htmlspecialchars($_SESSION['admin_username'] ?? '') ?>.
    <a href="logout.php">Logga ut</a>
</p>

<p><a href="index.php">Tillbaka till aktuella bokningar</a></p>

<?php require __DIR__ . '/booking_table.php'; ?>
</body>
</html>
