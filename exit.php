<?php
require_once 'includes/functions.php';
require_login();

$errors = [];

// The fee rule: every started hour is charged, with a minimum of 1 hour.
// Example: 1h 10m parked = 2 hours charged.
// The time is worked out in MySQL using NOW().
$fee_sql = "GREATEST(1, CEIL(TIMESTAMPDIFF(MINUTE, t.entry_time, NOW()) / 60)) * s.hourly_rate";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $ticket_id = (int) ($_POST['ticket_id'] ?? 0);
    $method    = $_POST['method'] ?? '';

    // Only allow the two payment methods the database accepts
    if (!in_array($method, ['cash', 'card'], true)) {
        $errors[] = 'Pick a payment method.';
    }

    if (!$errors) {
        // Close the ticket, free the spot and save the payment in one transaction.
        // If any step fails, all of it is undone.
        $conn->begin_transaction();
        try {
            // Get the active ticket and work out the fee
            $stmt = $conn->prepare("
                SELECT t.ticket_id, t.spot_id, $fee_sql AS fee
                FROM tickets t
                JOIN parking_spots s ON s.spot_id = t.spot_id
                WHERE t.ticket_id = ? AND t.status = 'active'
            ");
            $stmt->bind_param('i', $ticket_id);
            $stmt->execute();
            $ticket = $stmt->get_result()->fetch_assoc();

            if (!$ticket) {
                throw new Exception('That ticket is not active. It may already be checked out.');
            }

            $fee     = $ticket['fee'];
            $user_id = $_SESSION['user_id'];

            // 1. Close the ticket
            $stmt = $conn->prepare("UPDATE tickets SET exit_time = NOW(), fee = ?, status = 'closed' WHERE ticket_id = ?");
            $stmt->bind_param('di', $fee, $ticket_id);
            $stmt->execute();

            // 2. Free the spot
            $stmt = $conn->prepare('UPDATE parking_spots SET is_occupied = FALSE WHERE spot_id = ?');
            $stmt->bind_param('i', $ticket['spot_id']);
            $stmt->execute();

            // 3. Record the payment
            $stmt = $conn->prepare('INSERT INTO payments (ticket_id, amount, method, processed_by) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('idsi', $ticket_id, $fee, $method, $user_id);
            $stmt->execute();

            $conn->commit();
            flash('Payment of ' . money($fee) . " received. Ticket #$ticket_id closed.");
            redirect('receipt.php?id=' . $ticket_id);
        } catch (Exception $ex) {
            $conn->rollback();
            $errors[] = $ex instanceof mysqli_sql_exception ? 'Database error. Please try again.' : $ex->getMessage();
        }
    }
}

// If a ticket was picked, load its details to show on the page
$selected = null;
$selected_id = (int) ($_POST['ticket_id'] ?? $_GET['ticket'] ?? 0);

if ($selected_id > 0) {
    $stmt = $conn->prepare("
        SELECT t.ticket_id, t.entry_time,
               TIMESTAMPDIFF(MINUTE, t.entry_time, NOW()) AS minutes_parked,
               GREATEST(1, CEIL(TIMESTAMPDIFF(MINUTE, t.entry_time, NOW()) / 60)) AS hours_charged,
               $fee_sql AS fee,
               v.plate_number, v.make, v.model, v.color, v.owner_name,
               s.spot_number, s.spot_type, s.hourly_rate
        FROM tickets t
        JOIN vehicles      v ON v.vehicle_id = t.vehicle_id
        JOIN parking_spots s ON s.spot_id    = t.spot_id
        WHERE t.ticket_id = ? AND t.status = 'active'
    ");
    $stmt->bind_param('i', $selected_id);
    $stmt->execute();
    $selected = $stmt->get_result()->fetch_assoc();
}

// Search box: find an active ticket by plate
$search = clean_plate($_GET['plate'] ?? '');
$like   = '%' . $search . '%';

$stmt = $conn->prepare("
    SELECT t.ticket_id, t.entry_time,
           TIMESTAMPDIFF(MINUTE, t.entry_time, NOW()) AS minutes_parked,
           $fee_sql AS fee,
           v.plate_number, s.spot_number
    FROM tickets t
    JOIN vehicles      v ON v.vehicle_id = t.vehicle_id
    JOIN parking_spots s ON s.spot_id    = t.spot_id
    WHERE t.status = 'active' AND v.plate_number LIKE ?
    ORDER BY t.entry_time
");
$stmt->bind_param('s', $like);
$stmt->execute();
$active = $stmt->get_result();

$page_title = 'Check Out';
require 'includes/header.php';
?>

<h1>Check Out a Car</h1>

<?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>

<?php if ($selected): ?>
<section class="card form-card">
    <h2>Ticket #<?= (int) $selected['ticket_id'] ?></h2>
    <dl class="details">
        <dt>Plate</dt><dd><span class="plate"><?= e($selected['plate_number']) ?></span></dd>
        <dt>Vehicle</dt><dd><?= e(trim($selected['color'] . ' ' . $selected['make'] . ' ' . $selected['model'])) ?: '-' ?></dd>
        <dt>Owner</dt><dd><?= e($selected['owner_name'] ?: '-') ?></dd>
        <dt>Spot</dt><dd><?= e($selected['spot_number']) ?> (<?= e($selected['spot_type']) ?>, <?= money($selected['hourly_rate']) ?>/hr)</dd>
        <dt>Entered</dt><dd><?= e(date('M j, Y g:i A', strtotime($selected['entry_time']))) ?></dd>
        <dt>Time parked</dt><dd><?= duration($selected['minutes_parked']) ?> (<?= (int) $selected['hours_charged'] ?> hour<?= $selected['hours_charged'] == 1 ? '' : 's' ?> charged)</dd>
        <dt>Amount due</dt><dd class="total"><?= money($selected['fee']) ?></dd>
    </dl>

    <form method="post" data-confirm="Take payment of <?= money($selected['fee']) ?> and close this ticket?">
        <input type="hidden" name="ticket_id" value="<?= (int) $selected['ticket_id'] ?>">
        <div class="radio-row">
            <label class="radio"><input type="radio" name="method" value="cash" required> Cash</label>
            <label class="radio"><input type="radio" name="method" value="card"> Card</label>
        </div>
        <button type="submit" class="btn btn-primary">Take Payment &amp; Check Out</button>
        <a href="exit.php" class="btn">Cancel</a>
    </form>
</section>
<?php elseif ($selected_id > 0 && !$errors): ?>
    <div class="alert alert-error">Ticket #<?= $selected_id ?> is not active.</div>
<?php endif; ?>

<section class="card">
    <div class="card-head">
        <h2>Cars in the Lot</h2>
        <form method="get" class="search">
            <input type="text" name="plate" placeholder="Search plate" value="<?= e($search) ?>" class="upper">
            <button class="btn">Search</button>
        </form>
    </div>

    <?php if ($active->num_rows === 0): ?>
        <p class="muted">No parked cars<?= $search ? ' match that plate' : '' ?>.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Ticket</th><th>Plate</th><th>Spot</th><th>Entered</th><th>Time Parked</th><th>Fee So Far</th><th></th></tr></thead>
        <tbody>
        <?php while ($a = $active->fetch_assoc()): ?>
            <tr>
                <td>#<?= (int) $a['ticket_id'] ?></td>
                <td><span class="plate"><?= e($a['plate_number']) ?></span></td>
                <td><?= e($a['spot_number']) ?></td>
                <td><?= e(date('M j, g:i A', strtotime($a['entry_time']))) ?></td>
                <td><?= duration($a['minutes_parked']) ?></td>
                <td><?= money($a['fee']) ?></td>
                <td><a class="btn btn-small" href="exit.php?ticket=<?= (int) $a['ticket_id'] ?>">Select</a></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</section>

<?php require 'includes/footer.php'; ?>
