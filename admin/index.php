<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';

$stmt = $pdo->query('SELECT id, company_name, company_logo, room, start_time, end_time FROM bookings ORDER BY start_time DESC');
$bookings = $stmt->fetchAll();
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

<p><a href="booking_form.php">Lägg till ny bokning</a></p>

<table border="1" cellpadding="4">
<tr>
    <th>Företag</th>
    <th>Logga</th>
    <th>Lokal</th>
    <th>Start</th>
    <th>Slut</th>
    <th>Åtgärder</th>
</tr>
<?php foreach ($bookings as $booking): ?>
<tr>
    <td><?= htmlspecialchars($booking['company_name']) ?></td>
    <td><?= $booking['company_logo'] ? htmlspecialchars($booking['company_logo']) : '-' ?></td>
    <td><?= htmlspecialchars($booking['room']) ?></td>
    <td><?= htmlspecialchars($booking['start_time']) ?></td>
    <td><?= htmlspecialchars($booking['end_time']) ?></td>
    <td>
        <a href="booking_form.php?id=<?= (int) $booking['id'] ?>">Redigera</a>
        &nbsp;
        <form method="post" action="delete_booking.php" onsubmit="return confirm('Ta bort bokningen?');">
            <input type="hidden" name="id" value="<?= (int) $booking['id'] ?>">
            <button type="submit">Ta bort</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
<?php if (!$bookings): ?>
<tr><td colspan="6">Inga bokningar.</td></tr>
<?php endif; ?>
</table>
</body>
</html>
