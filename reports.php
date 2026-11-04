<?php
require_once 'includes/functions.php';
require_login();

// Report 1: Revenue by spot type
// payments -> tickets -> parking_spots, grouped by the spot type
$by_type = $conn->query("
    SELECT s.spot_type,
           COUNT(p.payment_id) AS payments,
           SUM(p.amount)       AS revenue,
           AVG(p.amount)       AS avg_fee
    FROM payments p
    JOIN tickets       t ON t.ticket_id = p.ticket_id
    JOIN parking_spots s ON s.spot_id   = t.spot_id
    GROUP BY s.spot_type
    ORDER BY revenue DESC
");

// Report 2: Payments handled by each staff member
// LEFT JOIN keeps staff who have not taken any payments yet
$by_staff = $conn->query("
    SELECT u.username, u.role,
           COUNT(p.payment_id)        AS payments,
           COALESCE(SUM(p.amount), 0) AS collected
    FROM users u
    LEFT JOIN payments p ON p.processed_by = u.user_id
    GROUP BY u.user_id, u.username, u.role
    ORDER BY collected DESC
");

// Report 3: Top 5 customers by number of visits
$top_vehicles = $conn->query("
    SELECT v.plate_number, v.owner_name,
           COUNT(t.ticket_id)         AS visits,
           COALESCE(SUM(p.amount), 0) AS total_paid
    FROM vehicles v
    JOIN tickets       t ON t.vehicle_id = v.vehicle_id
    LEFT JOIN payments p ON p.ticket_id  = t.ticket_id
    GROUP BY v.vehicle_id, v.plate_number, v.owner_name
    ORDER BY visits DESC, total_paid DESC
    LIMIT 5
");

$page_title = 'Reports';
require 'includes/header.php';
?>

<h1>Reports</h1>

<div class="grid-2 reports">
    <section class="card">
        <h2>Revenue by Spot Type</h2>
        <table>
            <thead><tr><th>Type</th><th>Payments</th><th>Revenue</th><th>Avg Fee</th></tr></thead>
            <tbody>
            <?php while ($r = $by_type->fetch_assoc()): ?>
                <tr>
                    <td><?= e(type_label($r['spot_type'])) ?></td>
                    <td><?= (int) $r['payments'] ?></td>
                    <td><?= money($r['revenue']) ?></td>
                    <td><?= money($r['avg_fee']) ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </section>

    <section class="card">
        <h2>Payments by Staff</h2>
        <table>
            <thead><tr><th>Staff</th><th>Role</th><th>Payments</th><th>Collected</th></tr></thead>
            <tbody>
            <?php while ($r = $by_staff->fetch_assoc()): ?>
                <tr>
                    <td><?= e($r['username']) ?></td>
                    <td><?= e(ucfirst($r['role'])) ?></td>
                    <td><?= (int) $r['payments'] ?></td>
                    <td><?= money($r['collected']) ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </section>
</div>

<section class="card">
    <h2>Top Customers</h2>
    <table>
        <thead><tr><th>Plate</th><th>Owner</th><th>Visits</th><th>Total Paid</th></tr></thead>
        <tbody>
        <?php while ($r = $top_vehicles->fetch_assoc()): ?>
            <tr>
                <td><span class="plate"><?= e($r['plate_number']) ?></span></td>
                <td><?= e($r['owner_name'] ?: '-') ?></td>
                <td><?= (int) $r['visits'] ?></td>
                <td><?= money($r['total_paid']) ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</section>

<?php require 'includes/footer.php'; ?>
