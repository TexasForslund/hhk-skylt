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
