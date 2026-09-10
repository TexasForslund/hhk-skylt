<?php
require_once __DIR__ . '/../db.php';

$allRooms = $pdo->query('SELECT id, name FROM rooms ORDER BY name')->fetchAll();

$activeStmt = $pdo->prepare('
    SELECT company_name, company_logo
    FROM bookings
    WHERE room_id = ? AND ? BETWEEN start_time AND end_time
    ORDER BY start_time
    LIMIT 1
');

$now = date('Y-m-d H:i:s');

$rooms = [];
foreach ($allRooms as $roomRow) {
    $activeStmt->execute([$roomRow['id'], $now]);
    $active = $activeStmt->fetch();
    $rooms[] = [
        'room' => $roomRow['name'],
        'company_name' => $active['company_name'] ?? null,
        'company_logo' => $active['company_logo'] ?? null,
    ];
}
?>
<!DOCTYPE html>
<html lang="sv">
<head>
<meta charset="UTF-8">
<title>Våra konferenslokaler</title>
</head>
<body>
<h1>Våra konferenslokaler</h1>

<?php if (!$rooms): ?>
<p>Inga lokaler registrerade.</p>
<?php endif; ?>

<?php foreach ($rooms as $room): ?>
<div>
    <h2><?= htmlspecialchars($room['room']) ?></h2>
    <?php if ($room['company_name'] !== null): ?>
        <?php if ($room['company_logo']): ?>
        <img src="/uploads/<?= htmlspecialchars($room['company_logo']) ?>" alt="<?= htmlspecialchars($room['company_name']) ?>">
        <?php else: ?>
        <p><?= htmlspecialchars($room['company_name']) ?></p>
        <?php endif; ?>
    <?php else: ?>
        <p>Ledig</p>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<script>
setTimeout(function () {
    location.reload();
}, 30000);
</script>
</body>
</html>
