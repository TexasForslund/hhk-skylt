<?php
// Enkel session-check. Inkluderas överst i alla skyddade /admin-sidor
// utom login.php.

session_start();

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}
