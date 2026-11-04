<?php
require_once 'includes/functions.php';
require_login();

// Search by plate. An empty search matches every ticket.
$plate = clean_plate($_GET['plate'] ?? '');
$like  = '%' . $plate . '%';

// Full ticket history. INNER JOINs for vehicle, spot and staff (every ticket has them).
// LEFT JOIN for payments because active tickets have not been paid yet.
$stmt = $conn->prepare("
    SELECT t.ticket_id, t.entry_time, t.exit_time, t.status,
           TIMESTAMPDIFF(MINUTE, t.entry_time, COALESCE(t.exit_time, NOW())) AS minutes_parked,
           v.plate_number, s.spot_number, u.username AS checked_in_by,
           p.amount, p.method
    FROM tickets t
    JOIN vehicles      v ON v.vehicle_id = t.vehicle_id
    JOIN parking_spots s ON s.spot_id    = t.spot_id
    JOIN users         u ON u.user_id    = t.created_by
    LEFT JOIN payments p ON p.ticket_id  = t.ticket_id
    WHERE v.plate_number LIKE ?
    ORDER BY t.entry_time DESC
");
$stmt->bind_param('s', $like);
$stmt->execute();
$tickets = $stmt->get_result();

$page_title = 'Tickets';
require 'includes/header.php';
?>

<h1>Ticket History</h1>

<form method="get" class="card filters">
    <label>Plate <input type="text" name="plate" value="<?= e($plate) ?>" class="upper"></label>
    <div class="filter-buttons">
        <button class="btn btn-primary">Search</button>
        <a href="tickets.php" class="btn">Clear</a>
    </div>
</form>

<section class="card">
    <p class="muted"><?= $tickets->num_rows ?> ticket<?= $tickets->num_rows === 1 ? '' : 's' ?> found</p>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Ticket</th><th>Plate</th><th>Spot</th><th>Entered</th><th>Exited</th><th>Duration</th><th>Status</th><th>Paid</th><th>Staff</th><th></th></tr></thead>
        <tbody>
        <?php while ($t = $tickets->fetch_assoc()): ?>
            <tr>
                <td>#<?= (int) $t['ticket_id'] ?></td>
                <td><span class="plate"><?= e($t['plate_number']) ?></span></td>
                <td><?= e($t['spot_number']) ?></td>
                <td><?= e(date('M j, g:i A', strtotime($t['entry_time']))) ?></td>
                <td><?= $t['exit_time'] ? e(date('M j, g:i A', strtotime($t['exit_time']))) : '-' ?></td>
                <td><?= duration($t['minutes_parked']) ?></td>
                <td><span class="badge <?= $t['status'] === 'active' ? 'red' : 'green' ?>"><?= e(ucfirst($t['status'])) ?></span></td>
                <td><?= $t['amount'] !== null ? money($t['amount']) . ' <small class="muted">' . e($t['method']) . '</small>' : '-' ?></td>
                <td><?= e($t['checked_in_by']) ?></td>
                <td>
                    <?php if ($t['status'] === 'active'): ?>
                        <a class="btn btn-small" href="exit.php?ticket=<?= (int) $t['ticket_id'] ?>">Check Out</a>
                    <?php else: ?>
                        <a class="btn btn-small" href="receipt.php?id=<?= (int) $t['ticket_id'] ?>">Receipt</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>
</section>

<?php require 'includes/footer.php'; ?>
