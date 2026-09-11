<?php
require_once __DIR__ . '/../db.php';

$stmt = $pdo->prepare('
    SELECT r.name AS room, b.company_name
    FROM bookings b
    JOIN rooms r ON r.id = b.room_id
    WHERE ? BETWEEN b.start_time AND b.end_time
    ORDER BY r.name
');

$now = date('Y-m-d H:i:s');
$stmt->execute([$now]);
$rooms = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="sv">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Våra konferenslokaler</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Inter:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body>
<?php if ($rooms): ?>
<div class="display-page">
    <h1 class="display-title">I våra lokaler</h1>

    <div class="room-list">
        <?php foreach ($rooms as $room): ?>
        <div class="room-entry">
            <div class="room-entry-name"><?= htmlspecialchars($room['room']) ?></div>
            <div class="room-entry-company"><?= htmlspecialchars($room['company_name']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <p class="display-footer">Hotell Höga Kusten</p>
</div>
<?php else: ?>
<div class="display-empty">
    <?php
    // Platshållarsökväg tills en riktig bild laddas upp - samma mönster som
    // login.jpg hanterades för adminsidans inloggningsvy.
    ?>
    <img src="/assets/display-welcome.jpg" alt="" class="display-empty-image">
    <div class="display-empty-overlay">
        <h1 class="display-empty-title">Välkommen till Hotell Höga Kusten</h1>
        <p class="display-footer display-footer-overlay">Hotell Höga Kusten</p>
    </div>
</div>
<?php endif; ?>

<script>
setTimeout(function () {
    location.reload();
}, 30000);
</script>
</body>
</html>
