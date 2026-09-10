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
$currentPage = 'historik';
?>
<!DOCTYPE html>
<html lang="sv">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Historik - hhk-skylt admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Open+Sans:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tabler-icons/3.46.0/tabler-icons.min.css">
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="admin-page">
    <?php require __DIR__ . '/nav.php'; ?>

    <h1>Historik</h1>

    <?php require __DIR__ . '/booking_table.php'; ?>
</div>
</body>
</html>
