<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/helpers.php';

$now = date('Y-m-d H:i:s');

$stmt = $pdo->prepare('
    SELECT b.id, b.company_name, b.company_logo, r.name AS room_name, b.start_time, b.end_time
    FROM bookings b
    LEFT JOIN rooms r ON r.id = b.room_id
    WHERE b.end_time >= ?
    ORDER BY b.start_time ASC
');
$stmt->execute([$now]);
$bookings = $stmt->fetchAll();

$currentPage = 'bookings';
?>
<!DOCTYPE html>
<html lang="sv">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Bokningar - hhk-skylt admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Open+Sans:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tabler-icons/3.46.0/tabler-icons.min.css">
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="admin-page">
    <?php require __DIR__ . '/nav.php'; ?>

    <h1>Aktuella bokningar</h1>

    <table class="booking-list">
    <thead>
    <tr>
        <th>Företag</th>
        <th>Lokal</th>
        <th>Tid</th>
        <th class="text-right">Åtgärder</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($bookings as $booking): ?>
    <tr>
        <td>
            <div class="company-cell">
                <?php if ($booking['company_logo']): ?>
                <img src="/uploads/<?= htmlspecialchars($booking['company_logo']) ?>" alt="" class="company-logo-image">
                <?php else: ?>
                <span class="company-thumb-placeholder"><?= htmlspecialchars(companyInitials($booking['company_name'])) ?></span>
                <?php endif; ?>
                <span class="company-name"><?= htmlspecialchars($booking['company_name']) ?></span>
            </div>
        </td>
        <td><?= $booking['room_name'] ? htmlspecialchars($booking['room_name']) : '(ingen lokal)' ?></td>
        <td><?= htmlspecialchars(formatBookingTime($booking['start_time'], $booking['end_time'])) ?></td>
        <td>
            <div class="row-actions">
                <a href="booking_form.php?id=<?= (int) $booking['id'] ?>" title="Redigera" aria-label="Redigera bokning för <?= htmlspecialchars($booking['company_name']) ?>">
                    <i class="ti ti-edit" aria-hidden="true"></i>
                </a>
                <form method="post" action="delete_booking.php" onsubmit="return confirm('Ta bort bokningen?');">
                    <input type="hidden" name="id" value="<?= (int) $booking['id'] ?>">
                    <button type="submit" title="Ta bort" aria-label="Ta bort bokning för <?= htmlspecialchars($booking['company_name']) ?>">
                        <i class="ti ti-trash" aria-hidden="true"></i>
                    </button>
                </form>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$bookings): ?>
    <tr><td colspan="4">Inga aktuella bokningar.</td></tr>
    <?php endif; ?>
    </tbody>
    </table>
</div>
</body>
</html>
