<?php
require_once 'includes/functions.php';
require_login();

// Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) $_POST['vehicle_id'];
    try {
        $stmt = $conn->prepare('DELETE FROM vehicles WHERE vehicle_id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        flash('Vehicle deleted.');
    } catch (mysqli_sql_exception $ex) {
        // The foreign key in tickets blocks deleting a car that has visits
        flash('This vehicle has tickets, so it can\'t be deleted.', 'error');
    }
    redirect('vehicles.php');
}

// Search by plate or owner name
$search = trim($_GET['q'] ?? '');
$like   = '%' . $search . '%';

// Each vehicle with its number of visits, total paid, and last visit.
// LEFT JOIN so cars with no visits still show up.
$stmt = $conn->prepare("
    SELECT v.vehicle_id, v.plate_number, v.make, v.model, v.color, v.owner_name, v.owner_phone,
           COUNT(t.ticket_id)         AS visits,
           COALESCE(SUM(p.amount), 0) AS total_paid,
           MAX(t.entry_time)          AS last_visit,
           SUM(t.status = 'active')   AS parked_now
    FROM vehicles v
    LEFT JOIN tickets  t ON t.vehicle_id = v.vehicle_id
    LEFT JOIN payments p ON p.ticket_id  = t.ticket_id
    WHERE v.plate_number LIKE ? OR v.owner_name LIKE ?
    GROUP BY v.vehicle_id, v.plate_number, v.make, v.model, v.color, v.owner_name, v.owner_phone
    ORDER BY last_visit DESC, v.plate_number
");
$stmt->bind_param('ss', $like, $like);
$stmt->execute();
$vehicles = $stmt->get_result();

$page_title = 'Vehicles';
require 'includes/header.php';
?>

<h1>Vehicles</h1>

<section class="card">
    <div class="card-head">
        <h2>All Vehicles</h2>
        <form method="get" class="search">
            <input type="text" name="q" placeholder="Plate or owner" value="<?= e($search) ?>">
            <button class="btn">Search</button>
        </form>
    </div>
    <p class="muted">New vehicles are added automatically when they check in.</p>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Plate</th><th>Vehicle</th><th>Owner</th><th>Phone</th><th>Visits</th><th>Total Paid</th><th>Last Visit</th><th></th></tr></thead>
        <tbody>
        <?php while ($v = $vehicles->fetch_assoc()): ?>
            <tr>
                <td><span class="plate"><?= e($v['plate_number']) ?></span>
                    <?php if ($v['parked_now']): ?><span class="badge red">Parked</span><?php endif; ?></td>
                <td><?= e(trim($v['color'] . ' ' . $v['make'] . ' ' . $v['model'])) ?: '-' ?></td>
                <td><?= e($v['owner_name'] ?: '-') ?></td>
                <td><?= e($v['owner_phone'] ?: '-') ?></td>
                <td><?= (int) $v['visits'] ?></td>
                <td><?= money($v['total_paid']) ?></td>
                <td><?= $v['last_visit'] ? e(date('M j, Y', strtotime($v['last_visit']))) : '-' ?></td>
                <td class="actions">
                    <a class="btn btn-small" href="tickets.php?plate=<?= urlencode($v['plate_number']) ?>">History</a>
                    <form method="post" data-confirm="Delete vehicle <?= e($v['plate_number']) ?>?">
                        <input type="hidden" name="vehicle_id" value="<?= (int) $v['vehicle_id'] ?>">
                        <button class="btn btn-small btn-danger">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>
</section>

<?php require 'includes/footer.php'; ?>
