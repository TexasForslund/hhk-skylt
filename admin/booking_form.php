<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/helpers.php';

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
$return = (($_GET['return'] ?? '') === 'historik') ? 'historik' : 'index';
$returnUrl = $return === 'historik' ? 'historik.php' : 'index.php';
$booking = null;

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM bookings WHERE id = ?');
    $stmt->execute([$id]);
    $booking = $stmt->fetch();
    if (!$booking) {
        $id = null;
    }
}

$rooms = $pdo->query('SELECT id, name FROM rooms ORDER BY name')->fetchAll();
$roomIds = array_column($rooms, 'id');

// En rad per unikt företagsnamn, med loggan från dess senaste bokning, så
// receptionen kan återanvända tidigare inmatade uppgifter istället för att
// skriva in samma företag och ladda upp samma logga på nytt varje gång.
$previousCompanies = $pdo->query('
    SELECT company_name, company_logo
    FROM bookings b1
    WHERE id = (SELECT MAX(id) FROM bookings b2 WHERE b2.company_name = b1.company_name)
    ORDER BY company_name
')->fetchAll();
$validPreviousLogos = array_filter(array_column($previousCompanies, 'company_logo'));

$errors = [];
$values = [
    'company_name' => $booking['company_name'] ?? '',
    'room_id' => $booking['room_id'] ?? '',
    'start_time' => toDatetimeLocal($booking['start_time'] ?? null),
    'end_time' => toDatetimeLocal($booking['end_time'] ?? null),
];
$currentLogo = $booking['company_logo'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['company_name'] = trim($_POST['company_name'] ?? '');
    $values['room_id'] = trim($_POST['room_id'] ?? '');
    $values['start_time'] = trim($_POST['start_time'] ?? '');
    $values['end_time'] = trim($_POST['end_time'] ?? '');

    if ($values['company_name'] === '') {
        $errors[] = 'Företagsnamn krävs.';
    }

    $roomIdValid = $values['room_id'] !== '' && in_array((int) $values['room_id'], $roomIds, true);
    if (!$roomIdValid) {
        $errors[] = 'Välj en giltig lokal.';
    }

    $timesValid = false;
    if ($values['start_time'] === '' || $values['end_time'] === '') {
        $errors[] = 'Start- och sluttid krävs.';
    } elseif ($values['end_time'] <= $values['start_time']) {
        $errors[] = 'Sluttid måste vara efter starttid.';
    } else {
        $timesValid = true;
    }

    $startTime = null;
    $endTime = null;

    if ($roomIdValid && $timesValid) {
        $startTime = toMysqlDatetime($values['start_time']);
        $endTime = toMysqlDatetime($values['end_time']);

        $overlapStmt = $pdo->prepare('
            SELECT company_name, start_time, end_time
            FROM bookings
            WHERE room_id = ?
                AND start_time < ?
                AND end_time > ?
                AND id != ?
            LIMIT 1
        ');
        $overlapStmt->execute([(int) $values['room_id'], $endTime, $startTime, $id ?? 0]);
        $overlap = $overlapStmt->fetch();

        if ($overlap) {
            $errors[] = sprintf(
                'Lokalen är redan bokad av %s (%s - %s) under den valda tiden.',
                $overlap['company_name'],
                $overlap['start_time'],
                $overlap['end_time']
            );
        }
    }

    $companyLogo = $currentLogo;
    $oldLogoToDelete = null;

    if (isset($_POST['remove_logo']) && $currentLogo) {
        $oldLogoToDelete = $currentLogo;
        $companyLogo = null;
    }

    $newFileUploaded = !empty($_FILES['company_logo']['name']);

    if ($newFileUploaded) {
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
    } else {
        // Ingen ny fil uppladdad - om receptionen valde ett tidigare
        // företag med en logga, återanvänd samma fil istället för att
        // kräva en ny uppladdning.
        $reusedLogo = trim($_POST['reused_logo'] ?? '');

        if ($reusedLogo !== '' && !$oldLogoToDelete && in_array($reusedLogo, $validPreviousLogos, true)) {
            if ($currentLogo && $currentLogo !== $reusedLogo) {
                $oldLogoToDelete = $currentLogo;
            }
            $companyLogo = $reusedLogo;
        }
    }

    if (!$errors) {
        if ($id) {
            $stmt = $pdo->prepare('UPDATE bookings SET company_name = ?, company_logo = ?, room_id = ?, start_time = ?, end_time = ? WHERE id = ?');
            $stmt->execute([$values['company_name'], $companyLogo, (int) $values['room_id'], $startTime, $endTime, $id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO bookings (company_name, company_logo, room_id, start_time, end_time) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$values['company_name'], $companyLogo, (int) $values['room_id'], $startTime, $endTime]);
        }

        if ($oldLogoToDelete && !logoInUseElsewhere($pdo, $oldLogoToDelete, $id)) {
            $oldPath = UPLOAD_DIR . $oldLogoToDelete;
            if (is_file($oldPath)) {
                unlink($oldPath);
            }
        }

        header('Location: ' . $returnUrl);
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

<?php render_errors($errors); ?>

<?php
$actionParams = [];
if ($id) {
    $actionParams['id'] = $id;
}
if ($return === 'historik') {
    $actionParams['return'] = 'historik';
}
$actionUrl = 'booking_form.php' . ($actionParams ? '?' . http_build_query($actionParams) : '');
?>
<form method="post" action="<?= htmlspecialchars($actionUrl) ?>" enctype="multipart/form-data">
    <?php if ($id): ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <?php endif; ?>

    <?php if ($previousCompanies): ?>
    <p>
        <label>Återanvänd tidigare företag:<br>
        <select id="company_picker">
            <option value="">-- Skriv in nytt/eget företag --</option>
            <?php foreach ($previousCompanies as $previousCompany): ?>
            <option value="<?= htmlspecialchars($previousCompany['company_name']) ?>" data-logo="<?= htmlspecialchars($previousCompany['company_logo'] ?? '') ?>">
                <?= htmlspecialchars($previousCompany['company_name']) ?><?= $previousCompany['company_logo'] ? '' : ' (ingen logga sparad)' ?>
            </option>
            <?php endforeach; ?>
        </select></label>
        <br>
        <span id="reused_logo_info"></span>
    </p>
    <?php endif; ?>

    <input type="hidden" name="reused_logo" id="reused_logo" value="">

    <p>
        <label>Företagsnamn:<br>
        <input type="text" name="company_name" id="company_name" value="<?= htmlspecialchars($values['company_name']) ?>" required></label>
    </p>

    <p>
        <label>Lokal:<br>
        <select name="room_id" required>
            <option value="">Välj lokal</option>
            <?php foreach ($rooms as $room): ?>
            <option value="<?= (int) $room['id'] ?>" <?= (string) $room['id'] === (string) $values['room_id'] ? 'selected' : '' ?>><?= htmlspecialchars($room['name']) ?></option>
            <?php endforeach; ?>
        </select></label>
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
        <input type="file" name="company_logo" accept=".jpg,.jpeg,.png,.svg" onchange="document.getElementById('reused_logo').value = ''; document.getElementById('reused_logo_info').textContent = this.value ? 'Ny uppladdad fil används istället för eventuell återanvänd logga.' : '';"></label>
    </p>

    <p><button type="submit"><?= $id ? 'Spara ändringar' : 'Skapa bokning' ?></button></p>
</form>

<p><a href="<?= htmlspecialchars($returnUrl) ?>"><?= $return === 'historik' ? 'Tillbaka till historik' : 'Tillbaka till listan' ?></a></p>

<script>
function fillCompany(select) {
    var option = select.options[select.selectedIndex];
    var name = option.value;
    var logo = option.getAttribute('data-logo') || '';
    var info = document.getElementById('reused_logo_info');

    if (name === '') {
        info.textContent = '';
        return;
    }

    document.getElementById('company_name').value = name;
    document.getElementById('reused_logo').value = logo;
    document.querySelector('input[name="company_logo"]').value = '';
    info.textContent = logo ? 'Återanvänder logga: ' + logo : 'Inget tidigare logga sparad för detta företag.';
}

<?php if ($previousCompanies): ?>
document.getElementById('company_picker').addEventListener('change', function () {
    fillCompany(this);
});
<?php endif; ?>
</script>
</body>
</html>
