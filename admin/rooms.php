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
?>
<!DOCTYPE html>
<html lang="sv">
<head>
<meta charset="UTF-8">
<title>Lokaler - hhk-skylt admin</title>
</head>
<body>
<h1>Lokaler</h1>

<?php render_errors($errors); ?>

<table border="1" cellpadding="4">
<tr>
    <th>Namn</th>
    <th>Åtgärder</th>
</tr>
<?php foreach ($rooms as $room): ?>
<tr>
    <td><?= htmlspecialchars($room['name']) ?></td>
    <td>
        <a href="rooms.php?edit=<?= (int) $room['id'] ?>">Redigera</a>
        &nbsp;
        <form method="post" action="delete_room.php" onsubmit="return confirm('Ta bort lokalen?');">
            <input type="hidden" name="id" value="<?= (int) $room['id'] ?>">
            <button type="submit">Ta bort</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
<?php if (!$rooms): ?>
<tr><td colspan="2">Inga lokaler.</td></tr>
<?php endif; ?>
</table>

<h2><?= $editId ? 'Redigera lokal' : 'Lägg till lokal' ?></h2>

<form method="post" action="rooms.php">
    <input type="hidden" name="save_room" value="1">
    <?php if ($editId): ?>
    <input type="hidden" name="id" value="<?= (int) $editId ?>">
    <?php endif; ?>

    <p>
        <label>Namn:<br>
        <input type="text" name="name" value="<?= htmlspecialchars($nameValue) ?>" required></label>
    </p>

    <p><button type="submit"><?= $editId ? 'Spara ändringar' : 'Lägg till' ?></button></p>
</form>

<?php if ($editId): ?>
<p><a href="rooms.php">Avbryt redigering</a></p>
<?php endif; ?>

<p><a href="index.php">Tillbaka till bokningar</a></p>
</body>
</html>
