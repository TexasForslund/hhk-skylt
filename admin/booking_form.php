<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';

const ALLOWED_LOGO_EXTENSIONS = ['jpg', 'jpeg', 'png', 'svg'];
const UPLOAD_DIR = __DIR__ . '/../uploads/';

function toDatetimeLocal(?string $mysqlDatetime): string
{
    if (!$mysqlDatetime) {
        return '';
    }
    return str_replace(' ', 'T', substr($mysqlDatetime, 0, 16));
}

function toMysqlDatetime(string $datetimeLocal): string
{
    return str_replace('T', ' ', $datetimeLocal) . ':00';
}

// Kontrollerar att den uppladdade filen faktiskt är av den typ dess
// filändelse anger, inte bara att filnamnet ser rätt ut.
function isValidLogoUpload(array $file, string $extension): bool
{
    if (in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
        $info = @getimagesize($file['tmp_name']);
        if ($info === false) {
            return false;
        }
        $expected = $extension === 'png' ? IMAGETYPE_PNG : IMAGETYPE_JPEG;
        return $info[2] === $expected;
    }

    if ($extension === 'svg') {
        $content = file_get_contents($file['tmp_name'], false, null, 0, 4096);
        return $content !== false && stripos($content, '<svg') !== false;
    }

    return false;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : null);
$booking = null;

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM bookings WHERE id = ?');
    $stmt->execute([$id]);
    $booking = $stmt->fetch();
    if (!$booking) {
        $id = null;
    }
}

$errors = [];
$values = [
    'company_name' => $booking['company_name'] ?? '',
    'room' => $booking['room'] ?? '',
    'start_time' => toDatetimeLocal($booking['start_time'] ?? null),
    'end_time' => toDatetimeLocal($booking['end_time'] ?? null),
];
$currentLogo = $booking['company_logo'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['company_name'] = trim($_POST['company_name'] ?? '');
    $values['room'] = trim($_POST['room'] ?? '');
    $values['start_time'] = trim($_POST['start_time'] ?? '');
    $values['end_time'] = trim($_POST['end_time'] ?? '');

    if ($values['company_name'] === '') {
        $errors[] = 'Företagsnamn krävs.';
    }
    if ($values['room'] === '') {
        $errors[] = 'Lokal krävs.';
    }
    if ($values['start_time'] === '' || $values['end_time'] === '') {
        $errors[] = 'Start- och sluttid krävs.';
    }

    $companyLogo = $currentLogo;
    $oldLogoToDelete = null;

    if (isset($_POST['remove_logo']) && $currentLogo) {
        $oldLogoToDelete = $currentLogo;
        $companyLogo = null;
    }

    if (!empty($_FILES['company_logo']['name'])) {
        $file = $_FILES['company_logo'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Filuppladdningen misslyckades.';
        } else {
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($extension, ALLOWED_LOGO_EXTENSIONS, true) || !isValidLogoUpload($file, $extension)) {
                $errors[] = 'Ogiltig filtyp. Endast jpg, png och svg tillåts.';
            } else {
                $newName = bin2hex(random_bytes(16)) . '.' . $extension;
                $destination = UPLOAD_DIR . $newName;

                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    $errors[] = 'Kunde inte spara den uppladdade filen.';
                } else {
                    if ($currentLogo) {
                        $oldLogoToDelete = $currentLogo;
                    }
                    $companyLogo = $newName;
                }
            }
        }
    }

    if (!$errors) {
        $startTime = toMysqlDatetime($values['start_time']);
        $endTime = toMysqlDatetime($values['end_time']);

        if ($id) {
            $stmt = $pdo->prepare('UPDATE bookings SET company_name = ?, company_logo = ?, room = ?, start_time = ?, end_time = ? WHERE id = ?');
            $stmt->execute([$values['company_name'], $companyLogo, $values['room'], $startTime, $endTime, $id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO bookings (company_name, company_logo, room, start_time, end_time) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$values['company_name'], $companyLogo, $values['room'], $startTime, $endTime]);
        }

        if ($oldLogoToDelete) {
            $oldPath = UPLOAD_DIR . $oldLogoToDelete;
            if (is_file($oldPath)) {
                unlink($oldPath);
            }
        }

        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="sv">
<head>
<meta charset="UTF-8">
<title><?= $id ? 'Redigera bokning' : 'Ny bokning' ?> - hhk-skylt admin</title>
</head>
<body>
<h1><?= $id ? 'Redigera bokning' : 'Ny bokning' ?></h1>

<?php if ($errors): ?>
<ul>
    <?php foreach ($errors as $error): ?>
    <li><?= htmlspecialchars($error) ?></li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>

<form method="post" action="booking_form.php<?= $id ? '?id=' . (int) $id : '' ?>" enctype="multipart/form-data">
    <?php if ($id): ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <?php endif; ?>

    <p>
        <label>Företagsnamn:<br>
        <input type="text" name="company_name" value="<?= htmlspecialchars($values['company_name']) ?>" required></label>
    </p>

    <p>
        <label>Lokal:<br>
        <input type="text" name="room" value="<?= htmlspecialchars($values['room']) ?>" required></label>
    </p>

    <p>
        <label>Starttid:<br>
        <input type="datetime-local" name="start_time" value="<?= htmlspecialchars($values['start_time']) ?>" required></label>
    </p>

    <p>
        <label>Sluttid:<br>
        <input type="datetime-local" name="end_time" value="<?= htmlspecialchars($values['end_time']) ?>" required></label>
    </p>

    <p>
        <?php if ($currentLogo): ?>
        Nuvarande logga: <?= htmlspecialchars($currentLogo) ?><br>
        <label><input type="checkbox" name="remove_logo" value="1"> Ta bort loggan</label><br>
        <?php endif; ?>
        <label>Ladda upp ny logga (jpg, png eller svg):<br>
        <input type="file" name="company_logo" accept=".jpg,.jpeg,.png,.svg"></label>
    </p>

    <p><button type="submit"><?= $id ? 'Spara ändringar' : 'Skapa bokning' ?></button></p>
</form>

<p><a href="index.php">Tillbaka till listan</a></p>
</body>
</html>
