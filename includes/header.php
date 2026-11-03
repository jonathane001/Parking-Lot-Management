<?php
// Top of every page. Set $page_title before including this file.
$current = basename($_SERVER['PHP_SELF']);

function nav_link($file, $label, $current)
{
    $class = $file === $current ? ' class="active"' : '';
    echo '<a href="' . $file . '"' . $class . '>' . $label . '</a>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title ?? 'Parking Lot') ?> | Parking Lot Manager</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php if (!empty($_SESSION['user_id'])): ?>
<header class="topbar">
    <div class="brand"><span class="logo">P</span> Parking Lot Manager</div>
    <button class="menu-btn" id="menuBtn" aria-label="Menu">&#9776;</button>
    <nav id="mainNav">
        <?php
        nav_link('index.php', 'Dashboard', $current);
        nav_link('entry.php', 'Check In', $current);
        nav_link('exit.php', 'Check Out', $current);
        nav_link('tickets.php', 'Tickets', $current);
        nav_link('vehicles.php', 'Vehicles', $current);
        nav_link('spots.php', 'Spots', $current);
        nav_link('reports.php', 'Reports', $current);
        ?>
        <a href="logout.php" class="logout">Log out (<?= e($_SESSION['username']) ?>)</a>
    </nav>
</header>
<?php endif; ?>

<main class="container">
<?php
// Show the flash message once, then delete it
if (!empty($_SESSION['flash'])) {
    $f = $_SESSION['flash'];
    echo '<div class="alert alert-' . e($f['type']) . '">' . e($f['message']) . '</div>';
    unset($_SESSION['flash']);
}
?>
