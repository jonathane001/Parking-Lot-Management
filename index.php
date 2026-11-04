<?php
require_once 'includes/functions.php';
require_login();

// Summary numbers for the cards at the top
$stats = $conn->query("
    SELECT
        COUNT(*)                                   AS total_spots,
        SUM(is_occupied)                           AS occupied,
        SUM(CASE WHEN is_occupied = 0 THEN 1 ELSE 0 END) AS available
    FROM parking_spots
")->fetch_assoc();

$today = $conn->query("
    SELECT COALESCE(SUM(amount), 0) AS revenue, COUNT(*) AS payments
    FROM payments
    WHERE DATE(paid_at) = CURDATE()
")->fetch_assoc();

// Lot map
// LEFT JOIN keeps every spot, even empty ones. The joined ticket and vehicle
// columns are only filled in when a car is parked there right now.
$spots = $conn->query("
    SELECT s.spot_id, s.spot_number, s.spot_type, s.is_occupied,
           v.plate_number, t.ticket_id
    FROM parking_spots s
    LEFT JOIN tickets  t ON t.spot_id = s.spot_id AND t.status = 'active'
    LEFT JOIN vehicles v ON v.vehicle_id = t.vehicle_id
    ORDER BY s.spot_number
");

// Cars parked right now
// INNER JOIN of 4 tables: each active ticket with its car, spot and the staff member who checked it in
$parked = $conn->query("
    SELECT t.ticket_id, t.entry_time,
           TIMESTAMPDIFF(MINUTE, t.entry_time, NOW()) AS minutes_parked,
           v.plate_number, v.make, v.model,
           s.spot_number, s.spot_type, s.hourly_rate,
           u.username AS checked_in_by
    FROM tickets t
    JOIN vehicles      v ON v.vehicle_id = t.vehicle_id
    JOIN parking_spots s ON s.spot_id    = t.spot_id
    JOIN users         u ON u.user_id    = t.created_by
    WHERE t.status = 'active'
    ORDER BY t.entry_time
");

$page_title = 'Dashboard';
require 'includes/header.php';
?>

<h1>Dashboard</h1>

<div class="stats">
    <div class="stat card"><span class="stat-num"><?= (int) $stats['total_spots'] ?></span><span class="stat-label">Total spots</span></div>
    <div class="stat card green"><span class="stat-num"><?= (int) $stats['available'] ?></span><span class="stat-label">Available</span></div>
    <div class="stat card red"><span class="stat-num"><?= (int) $stats['occupied'] ?></span><span class="stat-label">Occupied</span></div>
    <div class="stat card blue"><span class="stat-num"><?= money($today['revenue']) ?></span><span class="stat-label">Revenue today (<?= (int) $today['payments'] ?> payment<?= $today['payments'] == 1 ? '' : 's' ?>)</span></div>
</div>

<section class="card">
    <h2>Lot Map</h2>
    <div class="lot-map">
        <?php while ($s = $spots->fetch_assoc()): ?>
            <?php if ($s['is_occupied']): ?>
                <a class="spot taken" href="exit.php?ticket=<?= (int) $s['ticket_id'] ?>" title="Check out this car">
                    <strong><?= e($s['spot_number']) ?></strong>
                    <span><?= e($s['plate_number']) ?></span>
                    <small><?= e(type_label($s['spot_type'])) ?></small>
                </a>
            <?php else: ?>
                <a class="spot free" href="entry.php?spot=<?= (int) $s['spot_id'] ?>" title="Park a car here">
                    <strong><?= e($s['spot_number']) ?></strong>
                    <span>Open</span>
                    <small><?= e(type_label($s['spot_type'])) ?></small>
                </a>
            <?php endif; ?>
        <?php endwhile; ?>
    </div>
</section>

<section class="card">
    <div class="card-head">
        <h2>Parked Right Now</h2>
        <a href="entry.php" class="btn btn-primary">+ Check In a Car</a>
    </div>
    <?php if ($parked->num_rows === 0): ?>
        <p class="muted">The lot is empty.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead>
            <tr><th>Ticket</th><th>Plate</th><th>Vehicle</th><th>Spot</th><th>Entered</th><th>Time Parked</th><th>Checked In By</th><th></th></tr>
        </thead>
        <tbody>
        <?php while ($p = $parked->fetch_assoc()): ?>
            <tr>
                <td>#<?= (int) $p['ticket_id'] ?></td>
                <td><span class="plate"><?= e($p['plate_number']) ?></span></td>
                <td><?= e($p['make'] . ' ' . $p['model']) ?></td>
                <td><?= e($p['spot_number']) ?> <small class="muted"><?= e(type_label($p['spot_type'])) ?></small></td>
                <td><?= e(date('M j, g:i A', strtotime($p['entry_time']))) ?></td>
                <td><?= duration($p['minutes_parked']) ?></td>
                <td><?= e($p['checked_in_by']) ?></td>
                <td><a class="btn btn-small" href="exit.php?ticket=<?= (int) $p['ticket_id'] ?>">Check Out</a></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</section>

<?php require 'includes/footer.php'; ?>
