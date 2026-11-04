<?php
require_once 'includes/functions.php';
require_login();

$ticket_id = (int) ($_GET['id'] ?? 0);

// Joins all 5 tables. users is joined twice with different aliases:
// "checkin" is who created the ticket, "cashier" is who took the payment.
$stmt = $conn->prepare("
    SELECT t.ticket_id, t.entry_time, t.exit_time, t.fee,
           TIMESTAMPDIFF(MINUTE, t.entry_time, t.exit_time) AS minutes_parked,
           v.plate_number, v.make, v.model, v.color,
           s.spot_number, s.spot_type, s.hourly_rate,
           p.payment_id, p.amount, p.method, p.paid_at,
           checkin.username AS checked_in_by,
           cashier.username AS paid_to
    FROM tickets t
    JOIN vehicles      v       ON v.vehicle_id   = t.vehicle_id
    JOIN parking_spots s       ON s.spot_id      = t.spot_id
    JOIN users         checkin ON checkin.user_id = t.created_by
    JOIN payments      p       ON p.ticket_id    = t.ticket_id
    JOIN users         cashier ON cashier.user_id = p.processed_by
    WHERE t.ticket_id = ?
");
$stmt->bind_param('i', $ticket_id);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();

$page_title = 'Receipt';
require 'includes/header.php';
?>

<?php if (!$r): ?>
    <div class="alert alert-error">No paid ticket with that number.</div>
<?php else: ?>
<section class="card receipt">
    <h1>Parking Receipt</h1>
    <p class="muted">Receipt #<?= (int) $r['payment_id'] ?> &middot; Ticket #<?= (int) $r['ticket_id'] ?></p>
    <dl class="details">
        <dt>Plate</dt><dd><span class="plate"><?= e($r['plate_number']) ?></span></dd>
        <dt>Vehicle</dt><dd><?= e(trim($r['color'] . ' ' . $r['make'] . ' ' . $r['model'])) ?: '-' ?></dd>
        <dt>Spot</dt><dd><?= e($r['spot_number']) ?> (<?= e($r['spot_type']) ?>)</dd>
        <dt>Rate</dt><dd><?= money($r['hourly_rate']) ?> per hour</dd>
        <dt>Entered</dt><dd><?= e(date('M j, Y g:i A', strtotime($r['entry_time']))) ?></dd>
        <dt>Exited</dt><dd><?= e(date('M j, Y g:i A', strtotime($r['exit_time']))) ?></dd>
        <dt>Time parked</dt><dd><?= duration($r['minutes_parked']) ?></dd>
        <dt>Paid by</dt><dd><?= e(ucfirst($r['method'])) ?></dd>
        <dt>Checked in by</dt><dd><?= e($r['checked_in_by']) ?></dd>
        <dt>Paid to</dt><dd><?= e($r['paid_to']) ?></dd>
        <dt>Total</dt><dd class="total"><?= money($r['amount']) ?></dd>
    </dl>
    <div class="no-print">
        <button class="btn" onclick="window.print()">Print</button>
        <a class="btn btn-primary" href="index.php">Back to Dashboard</a>
    </div>
</section>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
