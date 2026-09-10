<?php
// Delad hjälpfunktion för enhetlig felutskrift i adminvyerna.

function render_errors(array $errors): void
{
    if (!$errors) {
        return;
    }
    echo '<ul class="errors">';
    foreach ($errors as $error) {
        echo '<li>' . htmlspecialchars($error) . '</li>';
    }
    echo '</ul>';
}

// Företag kan numera återanvända en tidigare uppladdad logga över flera
// bokningar, så en logotypfil får bara tas bort om ingen annan bokning
// (än den som eventuellt just sparas/tas bort) längre pekar på den.
function logoInUseElsewhere(PDO $pdo, string $logo, ?int $excludeBookingId = null): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE company_logo = ? AND id != ?');
    $stmt->execute([$logo, $excludeBookingId ?? 0]);
    return (int) $stmt->fetchColumn() > 0;
}

// Slår ihop start- och sluttid till en rad, t.ex. "10 sep, 12:30-16:30".
// Visar bara startdatumet (inte slutdatumet), även om bokningen sträcker
// sig över midnatt, och årtal bara om bokningen inte är i innevarande år.
function formatBookingTime(string $start, string $end): string
{
    $months = [
        1 => 'jan', 2 => 'feb', 3 => 'mar', 4 => 'apr', 5 => 'maj', 6 => 'jun',
        7 => 'jul', 8 => 'aug', 9 => 'sep', 10 => 'okt', 11 => 'nov', 12 => 'dec',
    ];

    $startTs = strtotime($start);
    $endTs = strtotime($end);

    $datePart = (int) date('j', $startTs) . ' ' . $months[(int) date('n', $startTs)];
    if (date('Y', $startTs) !== date('Y')) {
        $datePart .= ' ' . date('Y', $startTs);
    }

    return $datePart . ', ' . date('H:i', $startTs) . '–' . date('H:i', $endTs);
}

// Två bokstäver att visa i platshållarrutan när ett företag saknar logga,
// tagna från det första ordet i företagsnamnet (t.ex. "GT-Konsult AB" -> "GT").
function companyInitials(string $companyName): string
{
    $firstWord = explode(' ', trim($companyName), 2)[0];
    return mb_strtoupper(mb_substr($firstWord, 0, 2));
}
