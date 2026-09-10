<?php
// Delad navigering för /admin-sidorna. Förväntar sig $currentPage
// ('bookings'|'rooms'|'historik') satt av den inkluderande sidan innan
// den här filen inkluderas.
$currentPage = $currentPage ?? '';
?>
<nav class="admin-nav">
    <div class="admin-nav-links">
        <a href="index.php" class="admin-nav-link<?= $currentPage === 'bookings' ? ' active' : '' ?>">Bokningar</a>
        <a href="rooms.php" class="admin-nav-link<?= $currentPage === 'rooms' ? ' active' : '' ?>">Lokaler</a>
        <a href="historik.php" class="admin-nav-link<?= $currentPage === 'historik' ? ' active' : '' ?>">Historik</a>
    </div>
    <div class="admin-nav-actions">
        <span class="admin-nav-user">
            Inloggad som <?= htmlspecialchars($_SESSION['admin_username'] ?? '') ?>
            &middot; <a href="logout.php">Logga ut</a>
        </span>
        <a href="booking_form.php" class="btn-primary admin-nav-new">+ Ny bokning</a>
    </div>
</nav>
