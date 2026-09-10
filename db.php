<?php
// Öppnar en PDO-anslutning till MySQL med uppgifter från config.php.
// config.php finns bara på servern/lokalt och ska ALDRIG committas till Git
// (kopiera config.php.example -> config.php och fyll i riktiga uppgifter).

// Servern kör UTC men bokningstider matas in och ska tolkas som svensk
// lokal tid. Sätts här (inte MySQL:s NOW()) så att alla jämförelser mot
// PHP:s aktuella tid blir korrekta även över sommartidsskiften.
date_default_timezone_set('Europe/Stockholm');

require_once __DIR__ . '/config.php';

$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

$pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
