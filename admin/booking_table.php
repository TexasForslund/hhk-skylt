<?php
// Delad tabellpartial för bokningslistan. Förväntar sig $bookings (rader
// med id, company_name, company_logo, room_name, start_time, end_time)
// och $return ('index' eller 'historik') i scope, inkluderas från
// historik.php.

$returnQuery = $return === 'historik' ? '&return=historik' : '';
?>
<table class="booking-list">
<thead>
<tr>
    <th>Företag</th>
    <th>Logga</th>
    <th>Lokal</th>
    <th>Start</th>
    <th>Slut</th>
    <th class="text-right">Åtgärder</th>
</tr>
</thead>
<tbody>
<?php foreach ($bookings as $booking): ?>
<tr>
    <td><?= htmlspecialchars($booking['company_name']) ?></td>
    <td><?= $booking['company_logo'] ? htmlspecialchars($booking['company_logo']) : '-' ?></td>
    <td><?= $booking['room_name'] ? htmlspecialchars($booking['room_name']) : '(ingen lokal)' ?></td>
    <td><?= htmlspecialchars($booking['start_time']) ?></td>
    <td><?= htmlspecialchars($booking['end_time']) ?></td>
    <td>
        <div class="row-actions">
            <a href="booking_form.php?id=<?= (int) $booking['id'] ?><?= $returnQuery ?>" title="Redigera" aria-label="Redigera bokning för <?= htmlspecialchars($booking['company_name']) ?>">
                <i class="ti ti-edit" aria-hidden="true"></i>
            </a>
            <form method="post" action="delete_booking.php" onsubmit="return confirm('Ta bort bokningen?');">
                <input type="hidden" name="id" value="<?= (int) $booking['id'] ?>">
                <?php if ($return === 'historik'): ?>
                <input type="hidden" name="return" value="historik">
                <?php endif; ?>
                <button type="submit" title="Ta bort" aria-label="Ta bort bokning för <?= htmlspecialchars($booking['company_name']) ?>">
                    <i class="ti ti-trash" aria-hidden="true"></i>
                </button>
            </form>
        </div>
    </td>
</tr>
<?php endforeach; ?>
<?php if (!$bookings): ?>
<tr><td colspan="6">Inga bokningar.</td></tr>
<?php endif; ?>
</tbody>
</table>
