<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id'])) {
    $id = (int) $_POST['id'];

    // Blockerar borttagning om lokalen har bokningar (historiska, aktiva
    // eller kommande) kopplade till sig, så inga bokningsrader orphanas.
    // Foreign key-constraint på bookings.room_id backar upp detta även om
    // den här kontrollen på något sätt kringgås.
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE room_id = ?');
    $stmt->execute([$id]);
    $bookingCount = (int) $stmt->fetchColumn();

    if ($bookingCount === 0) {
        $stmt = $pdo->prepare('DELETE FROM rooms WHERE id = ?');
        $stmt->execute([$id]);
    } else {
        $_SESSION['room_delete_error'] = 'Lokalen har bokningar kopplade till sig och kan inte tas bort.';
    }
}

header('Location: rooms.php');
exit;
