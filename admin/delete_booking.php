<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id'])) {
    $id = (int) $_POST['id'];

    $stmt = $pdo->prepare('SELECT company_logo FROM bookings WHERE id = ?');
    $stmt->execute([$id]);
    $booking = $stmt->fetch();

    $stmt = $pdo->prepare('DELETE FROM bookings WHERE id = ?');
    $stmt->execute([$id]);

    if ($booking && $booking['company_logo'] && !logoInUseElsewhere($pdo, $booking['company_logo'])) {
        $path = __DIR__ . '/../uploads/' . $booking['company_logo'];
        if (is_file($path)) {
            unlink($path);
        }
    }
}

header('Location: index.php');
exit;
