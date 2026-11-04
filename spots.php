<?php
require_once 'includes/functions.php';
require_login();

$types  = ['regular', 'compact', 'handicap', 'ev'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin(); // only admins can change spots

    $action = $_POST['action'] ?? '';

    // Delete
    if ($action === 'delete') {
        $id = (int) $_POST['spot_id'];
        try {
            // Only delete if nobody is parked there
            $stmt = $conn->prepare('DELETE FROM parking_spots WHERE spot_id = ? AND is_occupied = FALSE');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            if ($stmt->affected_rows === 1) {
                flash('Spot deleted.');
            } else {
                flash('Can\'t delete a spot while a car is parked in it.', 'error');
            }
        } catch (mysqli_sql_exception $ex) {
            // Foreign key error: old tickets point to this spot
            flash('This spot has ticket history, so it can\'t be deleted.', 'error');
        }
        redirect('spots.php');
    }

    // Add
    $number = strtoupper(trim($_POST['spot_number'] ?? ''));
    $type   = $_POST['spot_type'] ?? '';
    $rate   = $_POST['hourly_rate'] ?? '';

    if (!preg_match('/^[A-Z0-9\-]{1,10}$/', $number)) {
        $errors[] = 'Spot number must be 1 to 10 letters or numbers, like A4.';
    }
    if (!in_array($type, $types, true)) {
        $errors[] = 'Pick a spot type.';
    }
    if (!is_numeric($rate) || $rate <= 0 || $rate > 999) {
        $errors[] = 'Hourly rate must be a number above 0.';
    }

    if (!$errors) {
        try {
            $stmt = $conn->prepare('INSERT INTO parking_spots (spot_number, spot_type, hourly_rate) VALUES (?, ?, ?)');
            $stmt->bind_param('ssd', $number, $type, $rate);
            $stmt->execute();
            flash("Spot $number added.");
            redirect('spots.php');
        } catch (mysqli_sql_exception $ex) {
            // 1062 = duplicate value in a UNIQUE column
            $errors[] = $ex->getCode() == 1062 ? "Spot $number already exists." : 'Database error. Please try again.';
        }
    }
}

// List every spot with its total visits and earnings.
// LEFT JOINs keep spots that have never been used (they show 0).
$spots = $conn->query("
    SELECT s.spot_id, s.spot_number, s.spot_type, s.hourly_rate, s.is_occupied,
           COUNT(t.ticket_id)          AS total_visits,
           COALESCE(SUM(p.amount), 0)  AS total_earned
    FROM parking_spots s
    LEFT JOIN tickets  t ON t.spot_id   = s.spot_id
    LEFT JOIN payments p ON p.ticket_id = t.ticket_id
    GROUP BY s.spot_id, s.spot_number, s.spot_type, s.hourly_rate, s.is_occupied
    ORDER BY s.spot_number
");

$is_admin = $_SESSION['role'] === 'admin';
$page_title = 'Parking Spots';
require 'includes/header.php';
?>

<h1>Parking Spots</h1>

<?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>

<?php if ($is_admin): ?>
<form method="post" class="card form-card">
    <h2>Add a Spot</h2>
    <input type="hidden" name="action" value="add">
    <div class="grid-3">
        <label>Spot Number
            <input type="text" name="spot_number" required maxlength="10" class="upper"
                   value="<?= e($_POST['spot_number'] ?? '') ?>">
        </label>
        <label>Type
            <select name="spot_type" required>
                <?php foreach ($types as $t): ?>
                    <option value="<?= $t ?>"><?= type_label($t) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Hourly Rate ($)
            <input type="number" name="hourly_rate" step="0.25" min="0.25" required
                   value="<?= e($_POST['hourly_rate'] ?? '') ?>">
        </label>
    </div>
    <button class="btn btn-primary">Add Spot</button>
</form>
<?php endif; ?>

<section class="card">
    <h2>All Spots</h2>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Spot</th><th>Type</th><th>Rate</th><th>Status</th><th>Visits</th><th>Earned</th><?php if ($is_admin): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
        <?php while ($s = $spots->fetch_assoc()): ?>
            <tr>
                <td><strong><?= e($s['spot_number']) ?></strong></td>
                <td><?= e(type_label($s['spot_type'])) ?></td>
                <td><?= money($s['hourly_rate']) ?>/hr</td>
                <td><?= $s['is_occupied'] ? '<span class="badge red">Occupied</span>' : '<span class="badge green">Open</span>' ?></td>
                <td><?= (int) $s['total_visits'] ?></td>
                <td><?= money($s['total_earned']) ?></td>
                <?php if ($is_admin): ?>
                <td>
                    <form method="post" data-confirm="Delete spot <?= e($s['spot_number']) ?>?">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="spot_id" value="<?= (int) $s['spot_id'] ?>">
                        <button class="btn btn-small btn-danger">Delete</button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>
</section>

<?php require 'includes/footer.php'; ?>
