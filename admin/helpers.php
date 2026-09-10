<?php
// Delad hjälpfunktion för enhetlig felutskrift i adminvyerna.

function render_errors(array $errors): void
{
    if (!$errors) {
        return;
    }
    echo '<ul>';
    foreach ($errors as $error) {
        echo '<li>' . htmlspecialchars($error) . '</li>';
    }
    echo '</ul>';
}
