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

$actionParams = [];
if ($id) {
    $actionParams['id'] = $id;
}
if ($return === 'historik') {
    $actionParams['return'] = 'historik';
}
$actionUrl = 'booking_form.php' . ($actionParams ? '?' . http_build_query($actionParams) : '');

$currentPage = 'bookings';
?>
<!DOCTYPE html>
<html lang="sv">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $id ? 'Redigera bokning' : 'Ny bokning' ?> - hhk-skylt admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Open+Sans:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tabler-icons/3.46.0/tabler-icons.min.css">
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="admin-page">
    <?php require __DIR__ . '/nav.php'; ?>

    <div class="booking-form-container">
        <h1><?= $id ? 'Redigera bokning' : 'Ny bokning' ?></h1>

        <?php render_errors($errors); ?>

        <form method="post" action="<?= htmlspecialchars($actionUrl) ?>" enctype="multipart/form-data" class="booking-form" id="booking-form" data-initial-start="<?= htmlspecialchars($values['start_time']) ?>" data-initial-end="<?= htmlspecialchars($values['end_time']) ?>">
            <?php if ($id): ?>
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <?php endif; ?>

            <!-- Företag -->
            <div class="form-section">
                <label for="company_name">Företag</label>
                <div class="company-input-row">
                    <input type="text" id="company_name" name="company_name" placeholder="Ange företagsnamn" value="<?= htmlspecialchars($values['company_name']) ?>" required autocomplete="off">
                    <?php if ($previousCompanies): ?>
                    <div class="reuse-dropdown">
                        <button type="button" class="btn-reuse" id="reuse-toggle" aria-expanded="false">
                            Återanvänd <i class="ti ti-chevron-down" aria-hidden="true"></i>
                        </button>
                        <div class="reuse-panel" id="reuse-panel" hidden>
                            <?php foreach ($previousCompanies as $previousCompany): ?>
                            <button type="button" class="reuse-option" data-name="<?= htmlspecialchars($previousCompany['company_name']) ?>" data-logo="<?= htmlspecialchars($previousCompany['company_logo'] ?? '') ?>">
                                <?= htmlspecialchars($previousCompany['company_name']) ?><?= $previousCompany['company_logo'] ? '' : ' <span class="reuse-option-note">(ingen logga)</span>' ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <input type="hidden" name="reused_logo" id="reused_logo" value="">
                <p class="field-hint" id="reused_logo_info"></p>
            </div>

            <!-- Lokal -->
            <div class="form-section">
                <label>Lokal</label>
                <div class="room-picker" id="room-picker">
                    <?php foreach ($rooms as $room): ?>
                    <button type="button" class="room-btn<?= (string) $room['id'] === (string) $values['room_id'] ? ' selected' : '' ?>" data-room-id="<?= (int) $room['id'] ?>"><?= htmlspecialchars($room['name']) ?></button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="room_id" id="room_id_field" value="<?= htmlspecialchars($values['room_id']) ?>">
            </div>

            <!-- Datum och tid -->
            <div class="form-section">
                <label>Datum och tid</label>
                <div class="datetime-grid">
                    <div class="datetime-box" id="calendar-box"></div>
                    <div class="datetime-box" id="time-box"></div>
                </div>
                <input type="hidden" name="start_time" id="start_time_field" value="<?= htmlspecialchars($values['start_time']) ?>">
                <input type="hidden" name="end_time" id="end_time_field" value="<?= htmlspecialchars($values['end_time']) ?>">
            </div>

            <!-- Logotyp -->
            <div class="form-section">
                <label>Logotyp</label>
                <?php if ($currentLogo): ?>
                <p class="field-hint">
                    Nuvarande logga: <?= htmlspecialchars($currentLogo) ?>
                    &nbsp;&middot;&nbsp;
                    <label class="inline-checkbox"><input type="checkbox" name="remove_logo" value="1"> Ta bort loggan</label>
                </p>
                <?php endif; ?>
                <label class="upload-drop" id="upload-drop">
                    <i class="ti ti-upload" aria-hidden="true"></i>
                    <span>Välj fil eller dra hit</span>
                    <input type="file" name="company_logo" id="company_logo_input" accept=".jpg,.jpeg,.png,.svg" hidden>
                </label>
                <p class="field-hint" id="upload-filename"></p>
            </div>

            <div class="form-actions">
                <a href="<?= htmlspecialchars($returnUrl) ?>" class="btn-secondary">Avbryt</a>
                <button type="submit" class="btn-primary"><?= $id ? 'Spara ändringar' : 'Skapa bokning' ?></button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    'use strict';

    var form = document.getElementById('booking-form');

    /* ---------- Återanvänd företag ---------- */

    var reuseToggle = document.getElementById('reuse-toggle');
    var reusePanel = document.getElementById('reuse-panel');
    var companyNameInput = document.getElementById('company_name');
    var reusedLogoField = document.getElementById('reused_logo');
    var reusedLogoInfo = document.getElementById('reused_logo_info');

    if (reuseToggle && reusePanel) {
        reuseToggle.addEventListener('click', function () {
            var isOpen = !reusePanel.hidden;
            reusePanel.hidden = isOpen;
            reuseToggle.setAttribute('aria-expanded', String(!isOpen));
        });

        document.addEventListener('click', function (event) {
            if (!reusePanel.hidden && !reusePanel.contains(event.target) && event.target !== reuseToggle && !reuseToggle.contains(event.target)) {
                reusePanel.hidden = true;
                reuseToggle.setAttribute('aria-expanded', 'false');
            }
        });

        Array.prototype.forEach.call(reusePanel.querySelectorAll('.reuse-option'), function (option) {
            option.addEventListener('click', function () {
                var name = option.getAttribute('data-name') || '';
                var logo = option.getAttribute('data-logo') || '';

                companyNameInput.value = name;
                reusedLogoField.value = logo;
                document.getElementById('company_logo_input').value = '';
                reusedLogoInfo.textContent = logo ? 'Återanvänder logga: ' + logo : 'Inget tidigare logga sparad för detta företag.';

                reusePanel.hidden = true;
                reuseToggle.setAttribute('aria-expanded', 'false');
            });
        });
    }

    /* ---------- Lokal ---------- */

    var roomPicker = document.getElementById('room-picker');
    var roomIdField = document.getElementById('room_id_field');

    if (roomPicker) {
        Array.prototype.forEach.call(roomPicker.querySelectorAll('.room-btn'), function (btn) {
            btn.addEventListener('click', function () {
                Array.prototype.forEach.call(roomPicker.querySelectorAll('.room-btn'), function (b) {
                    b.classList.remove('selected');
                });
                btn.classList.add('selected');
                roomIdField.value = btn.getAttribute('data-room-id');
            });
        });
    }

    /* ---------- Logotyp: dra-och-släpp ---------- */

    var uploadDrop = document.getElementById('upload-drop');
    var uploadInput = document.getElementById('company_logo_input');
    var uploadFilename = document.getElementById('upload-filename');

    if (uploadDrop && uploadInput) {
        uploadInput.addEventListener('change', function () {
            reusedLogoField.value = '';
            if (uploadInput.files && uploadInput.files[0]) {
                uploadFilename.textContent = 'Vald fil: ' + uploadInput.files[0].name;
                if (reusedLogoInfo) {
                    reusedLogoInfo.textContent = 'Ny uppladdad fil används istället för eventuell återanvänd logga.';
                }
            } else {
                uploadFilename.textContent = '';
            }
        });

        ['dragenter', 'dragover'].forEach(function (eventName) {
            uploadDrop.addEventListener(eventName, function (event) {
                event.preventDefault();
                uploadDrop.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(function (eventName) {
            uploadDrop.addEventListener(eventName, function (event) {
                event.preventDefault();
                uploadDrop.classList.remove('dragover');
            });
        });

        uploadDrop.addEventListener('drop', function (event) {
            var files = event.dataTransfer && event.dataTransfer.files;
            if (files && files.length) {
                uploadInput.files = files;
                uploadInput.dispatchEvent(new Event('change'));
            }
        });
    }

    /* ---------- Datum och tid ---------- */

    var MONTH_NAMES = ['januari', 'februari', 'mars', 'april', 'maj', 'juni', 'juli', 'augusti', 'september', 'oktober', 'november', 'december'];
    var WEEKDAY_LETTERS = ['M', 'T', 'O', 'T', 'F', 'L', 'S'];

    function pad(n) {
        return n < 10 ? '0' + n : String(n);
    }

    function parseDatetimeLocal(str) {
        if (!str) {
            return null;
        }
        var parts = str.split('T');
        var dateParts = parts[0].split('-').map(Number);
        var timeParts = (parts[1] || '00:00').split(':').map(Number);
        return {
            y: dateParts[0],
            m: dateParts[1] - 1,
            d: dateParts[2],
            h: timeParts[0],
            min: timeParts[1]
        };
    }

    var initialStart = parseDatetimeLocal(form.dataset.initialStart);
    var initialEnd = parseDatetimeLocal(form.dataset.initialEnd);
    var today = new Date();

    var state = {
        date: initialStart ? { y: initialStart.y, m: initialStart.m, d: initialStart.d } : { y: today.getFullYear(), m: today.getMonth(), d: today.getDate() },
        startHour: initialStart ? initialStart.h : null,
        startMinute: initialStart ? initialStart.min : 0,
        endHour: initialEnd ? initialEnd.h : null,
        endMinute: initialEnd ? initialEnd.min : 0
    };
    var viewYear = state.date.y;
    var viewMonth = state.date.m;

    var startTimeField = document.getElementById('start_time_field');
    var endTimeField = document.getElementById('end_time_field');
    var calendarBox = document.getElementById('calendar-box');
    var timeBox = document.getElementById('time-box');

    function sync() {
        if (state.date && state.startHour !== null) {
            startTimeField.value = state.date.y + '-' + pad(state.date.m + 1) + '-' + pad(state.date.d) + 'T' + pad(state.startHour) + ':' + pad(state.startMinute);
        } else {
            startTimeField.value = '';
        }

        if (state.date && state.endHour !== null) {
            endTimeField.value = state.date.y + '-' + pad(state.date.m + 1) + '-' + pad(state.date.d) + 'T' + pad(state.endHour) + ':' + pad(state.endMinute);
        } else {
            endTimeField.value = '';
        }
    }

    function renderCalendar() {
        var firstOfMonth = new Date(viewYear, viewMonth, 1);
        var startWeekday = (firstOfMonth.getDay() + 6) % 7; // 0 = måndag
        var daysInThisMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
        var daysInPrevMonth = new Date(viewYear, viewMonth, 0).getDate();

        var html = '';
        html += '<div class="calendar-header">';
        html += '<span class="calendar-title">' + MONTH_NAMES[viewMonth] + ' ' + viewYear + '</span>';
        html += '<div class="calendar-nav">';
        html += '<button type="button" class="link-btn" data-action="today">Idag</button>';
        html += '<button type="button" class="calendar-nav-btn" data-action="prev" aria-label="Föregående månad"><i class="ti ti-chevron-left" aria-hidden="true"></i></button>';
        html += '<button type="button" class="calendar-nav-btn" data-action="next" aria-label="Nästa månad"><i class="ti ti-chevron-right" aria-hidden="true"></i></button>';
        html += '</div>';
        html += '</div>';

        html += '<div class="cal-grid cal-weekdays">';
        WEEKDAY_LETTERS.forEach(function (letter) {
            html += '<div class="cal-weekday">' + letter + '</div>';
        });
        html += '</div>';

        html += '<div class="cal-grid">';

        for (var i = 0; i < startWeekday; i++) {
            html += '<span class="cal-day cal-day-outside">' + (daysInPrevMonth - startWeekday + 1 + i) + '</span>';
        }

        for (var day = 1; day <= daysInThisMonth; day++) {
            var isSelected = state.date && state.date.y === viewYear && state.date.m === viewMonth && state.date.d === day;
            html += '<button type="button" class="cal-day' + (isSelected ? ' selected' : '') + '" data-day="' + day + '">' + day + '</button>';
        }

        var totalCells = startWeekday + daysInThisMonth;
        var trailing = (7 - (totalCells % 7)) % 7;
        for (var t = 1; t <= trailing; t++) {
            html += '<span class="cal-day cal-day-outside">' + t + '</span>';
        }

        html += '</div>';

        calendarBox.innerHTML = html;

        calendarBox.querySelector('[data-action="today"]').addEventListener('click', function () {
            var now = new Date();
            viewYear = now.getFullYear();
            viewMonth = now.getMonth();
            state.date = { y: viewYear, m: viewMonth, d: now.getDate() };
            renderCalendar();
            sync();
        });

        calendarBox.querySelector('[data-action="prev"]').addEventListener('click', function () {
            viewMonth -= 1;
            if (viewMonth < 0) {
                viewMonth = 11;
                viewYear -= 1;
            }
            renderCalendar();
        });

        calendarBox.querySelector('[data-action="next"]').addEventListener('click', function () {
            viewMonth += 1;
            if (viewMonth > 11) {
                viewMonth = 0;
                viewYear += 1;
            }
            renderCalendar();
        });

        Array.prototype.forEach.call(calendarBox.querySelectorAll('.cal-day[data-day]'), function (btn) {
            btn.addEventListener('click', function () {
                state.date = { y: viewYear, m: viewMonth, d: parseInt(btn.getAttribute('data-day'), 10) };
                renderCalendar();
                sync();
            });
        });
    }

    function formatHour(h) {
        return pad(h) + ':00';
    }

    function renderTimePicker() {
        var html = '<div class="time-picker">';

        // Starttid
        html += '<div class="time-column">';
        html += '<div class="time-column-label">Starttid</div>';
        if (state.startHour === null) {
            html += '<div class="time-options">';
            for (var h = 0; h <= 23; h++) {
                html += '<button type="button" class="time-option" data-start-hour="' + h + '">' + formatHour(h) + '</button>';
            }
            html += '</div>';
        } else {
            html += '<button type="button" class="time-chip" id="start-chip">';
            html += '<span>' + formatHour(state.startHour) + '</span>';
            html += '<i class="ti ti-pencil" aria-hidden="true"></i>';
            html += '</button>';
        }
        html += '</div>';

        // Sluttid
        html += '<div class="time-column">';
        if (state.startHour !== null) {
            html += '<div class="time-column-label">Sluttid</div>';
            if (state.endHour === null) {
                html += '<div class="time-options">';
                for (var eh = state.startHour + 1; eh <= 23; eh++) {
                    html += '<button type="button" class="time-option" data-end-hour="' + eh + '">' + formatHour(eh) + '</button>';
                }
                html += '</div>';
            } else {
                html += '<button type="button" class="time-chip" id="end-chip">';
                html += '<span>' + formatHour(state.endHour) + '</span>';
                html += '<i class="ti ti-pencil" aria-hidden="true"></i>';
                html += '</button>';
            }
        }
        html += '</div>';

        html += '</div>';

        timeBox.innerHTML = html;

        Array.prototype.forEach.call(timeBox.querySelectorAll('[data-start-hour]'), function (btn) {
            btn.addEventListener('click', function () {
                state.startHour = parseInt(btn.getAttribute('data-start-hour'), 10);
                state.startMinute = 0;
                state.endHour = null;
                state.endMinute = 0;
                renderTimePicker();
                sync();
            });
        });

        Array.prototype.forEach.call(timeBox.querySelectorAll('[data-end-hour]'), function (btn) {
            btn.addEventListener('click', function () {
                state.endHour = parseInt(btn.getAttribute('data-end-hour'), 10);
                state.endMinute = 0;
                renderTimePicker();
                sync();
            });
        });

        var startChip = document.getElementById('start-chip');
        if (startChip) {
            startChip.addEventListener('click', function () {
                state.startHour = null;
                state.endHour = null;
                renderTimePicker();
                sync();
            });
        }

        var endChip = document.getElementById('end-chip');
        if (endChip) {
            endChip.addEventListener('click', function () {
                state.endHour = null;
                renderTimePicker();
                sync();
            });
        }
    }

    renderCalendar();
    renderTimePicker();
})();
</script>
</body>
</html>
