<?php
require_once __DIR__ . '/../db.php';

// Två bokstäver att visa i platshållarrutan när ett företag saknar logga,
// tagna från det första ordet i företagsnamnet (t.ex. "GT-Konsult AB" -> "GT").
// Egen kopia (inte admin/helpers.php) eftersom /display är en helt separat,
// gästvänd yta som inte ska bero på adminpanelens filer.
function companyInitials(string $companyName): string
{
    $firstWord = explode(' ', trim($companyName), 2)[0];
    return mb_strtoupper(mb_substr($firstWord, 0, 2));
}

$stmt = $pdo->prepare('
    SELECT r.name AS room, b.company_name, b.company_logo
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

    <div class="room-table">
        <div class="room-table-header">
            <span>Lokal</span>
            <span>Företag</span>
        </div>
        <?php foreach ($rooms as $room): ?>
        <div class="room-row">
            <div class="room-row-name"><?= htmlspecialchars($room['room']) ?></div>
            <div class="room-row-company">
                <?php if ($room['company_logo']): ?>
                <img src="/uploads/<?= htmlspecialchars($room['company_logo']) ?>" alt="" class="room-row-logo">
                <?php else: ?>
                <span class="room-row-logo room-row-logo-placeholder"><?= htmlspecialchars(companyInitials($room['company_name'])) ?></span>
                <?php endif; ?>
                <span class="room-row-company-name"><?= htmlspecialchars($room['company_name']) ?></span>
            </div>
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
