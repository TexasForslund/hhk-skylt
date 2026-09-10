<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/helpers.php';

$errors = [];

if (!empty($_SESSION['room_delete_error'])) {
    $errors[] = $_SESSION['room_delete_error'];
    unset($_SESSION['room_delete_error']);
}

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
$editingRoom = null;

if ($editId) {
    $stmt = $pdo->prepare('SELECT * FROM rooms WHERE id = ?');
    $stmt->execute([$editId]);
    $editingRoom = $stmt->fetch();
    if (!$editingRoom) {
        $editId = null;
    }
}

$nameValue = $editingRoom['name'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_room'])) {
    $id = (!empty($_POST['id'])) ? (int) $_POST['id'] : null;
    $nameValue = trim($_POST['name'] ?? '');

    if ($nameValue === '') {
        $errors[] = 'Lokalnamn krävs.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM rooms WHERE name = ? AND id != ?');
        $stmt->execute([$nameValue, $id ?? 0]);
        if ($stmt->fetch()) {
            $errors[] = 'En lokal med det namnet finns redan.';
        }
    }

    if (!$errors) {
        if ($id) {
            $stmt = $pdo->prepare('UPDATE rooms SET name = ? WHERE id = ?');
            $stmt->execute([$nameValue, $id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO rooms (name) VALUES (?)');
            $stmt->execute([$nameValue]);
        }

        header('Location: rooms.php');
        exit;
    }

    $editId = $id;
}

$rooms = $pdo->query('SELECT id, name FROM rooms ORDER BY name')->fetchAll();
$currentPage = 'rooms';
?>
<!DOCTYPE html>
<html lang="sv">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Lokaler - hhk-skylt admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Open+Sans:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tabler-icons/3.46.0/tabler-icons.min.css">
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="admin-page">
    <?php require __DIR__ . '/nav.php'; ?>

    <h1>Lokaler</h1>

    <?php render_errors($errors); ?>

<table class="booking-list">
<thead>
<tr>
    <th>Namn</th>
    <th class="text-right">Åtgärder</th>
</tr>
</thead>
<tbody>
<?php foreach ($rooms as $room): ?>
<tr>
    <td><?= htmlspecialchars($room['name']) ?></td>
    <td>
        <div class="row-actions">
            <a href="rooms.php?edit=<?= (int) $room['id'] ?>" title="Redigera" aria-label="Redigera lokal <?= htmlspecialchars($room['name']) ?>">
                <i class="ti ti-edit" aria-hidden="true"></i>
            </a>
            <form method="post" action="delete_room.php" onsubmit="return confirm('Ta bort lokalen?');">
                <input type="hidden" name="id" value="<?= (int) $room['id'] ?>">
                <button type="submit" title="Ta bort" aria-label="Ta bort lokal <?= htmlspecialchars($room['name']) ?>">
                    <i class="ti ti-trash" aria-hidden="true"></i>
                </button>
            </form>
        </div>
    </td>
</tr>
<?php endforeach; ?>
<?php if (!$rooms): ?>
<tr><td colspan="2">Inga lokaler.</td></tr>
<?php endif; ?>
</tbody>
</table>

<h2><?= $editId ? 'Redigera lokal' : 'Lägg till lokal' ?></h2>

<form method="post" action="rooms.php" class="room-form">
    <input type="hidden" name="save_room" value="1">
    <?php if ($editId): ?>
    <input type="hidden" name="id" value="<?= (int) $editId ?>">
    <?php endif; ?>

    <div class="room-form-row">
        <input type="text" name="name" placeholder="Lokalnamn" value="<?= htmlspecialchars($nameValue) ?>" required>
        <button type="submit" class="btn-primary">
            <i class="ti ti-<?= $editId ? 'check' : 'plus' ?>" aria-hidden="true"></i>
            <?= $editId ? 'Spara ändringar' : 'Lägg till' ?>
        </button>
    </div>
</form>

    <?php if ($editId): ?>
    <p><a href="rooms.php">Avbryt redigering</a></p>
    <?php endif; ?>
</div>
</body>
</html>
