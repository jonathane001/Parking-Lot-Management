<?php
require_once 'includes/functions.php';
require_login();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Read and validate the form
    $plate   = clean_plate($_POST['plate'] ?? '');
    $spot_id = (int) ($_POST['spot_id'] ?? 0);
    $make    = trim($_POST['make'] ?? '');
    $model   = trim($_POST['model'] ?? '');
    $color   = trim($_POST['color'] ?? '');
    $owner   = trim($_POST['owner_name'] ?? '');
    $phone   = trim($_POST['owner_phone'] ?? '');

    if (!valid_plate($plate)) {
        $errors[] = 'Plate must be 2 to 10 letters or numbers.';
    }
    if ($spot_id <= 0) {
        $errors[] = 'Pick a parking spot.';
    }
    if ($phone !== '' && !preg_match('/^[0-9\-\s\(\)\+]{7,20}$/', $phone)) {
        $errors[] = 'Phone number looks wrong.';
    }
    if (strlen($make) > 30 || strlen($model) > 30 || strlen($color) > 20 || strlen($owner) > 60) {
        $errors[] = 'One of the vehicle fields is too long.';
    }

    if (!$errors) {
        // 2. Do everything in one transaction
        // If something fails, the ticket and the spot change both get undone.
        $conn->begin_transaction();
        try {
            // Find the car, or add it if this is its first visit
            $stmt = $conn->prepare('SELECT vehicle_id FROM vehicles WHERE plate_number = ?');
            $stmt->bind_param('s', $plate);
            $stmt->execute();
            $vehicle = $stmt->get_result()->fetch_assoc();

            if ($vehicle) {
                $vehicle_id = $vehicle['vehicle_id'];
            } else {
                $stmt = $conn->prepare('INSERT INTO vehicles (plate_number, make, model, color, owner_name, owner_phone) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('ssssss', $plate, $make, $model, $color, $owner, $phone);
                $stmt->execute();
                $vehicle_id = $conn->insert_id;
            }

            // The same car can't be parked twice
            $stmt = $conn->prepare("SELECT ticket_id FROM tickets WHERE vehicle_id = ? AND status = 'active'");
            $stmt->bind_param('i', $vehicle_id);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) {
                throw new Exception("Car $plate is already parked in the lot.");
            }

            // Make sure the spot exists and is free
            $stmt = $conn->prepare('SELECT spot_number, is_occupied FROM parking_spots WHERE spot_id = ?');
            $stmt->bind_param('i', $spot_id);
            $stmt->execute();
            $spot = $stmt->get_result()->fetch_assoc();

            if (!$spot) {
                throw new Exception('That spot does not exist.');
            }
            if ($spot['is_occupied']) {
                throw new Exception("Spot {$spot['spot_number']} is already taken.");
            }

            // Create the ticket. entry_time is filled in by MySQL automatically.
            $user_id = $_SESSION['user_id'];
            $stmt = $conn->prepare('INSERT INTO tickets (vehicle_id, spot_id, created_by) VALUES (?, ?, ?)');
            $stmt->bind_param('iii', $vehicle_id, $spot_id, $user_id);
            $stmt->execute();
            $ticket_id = $conn->insert_id;

            // Mark the spot as taken
            $stmt = $conn->prepare('UPDATE parking_spots SET is_occupied = TRUE WHERE spot_id = ?');
            $stmt->bind_param('i', $spot_id);
            $stmt->execute();

            $conn->commit();
            flash("Ticket #$ticket_id created. $plate parked in spot {$spot['spot_number']}.");
            redirect('index.php');
        } catch (Exception $ex) {
            // Something failed, so undo every change made in this transaction
            $conn->rollback();
            $errors[] = $ex instanceof mysqli_sql_exception ? 'Database error. Please try again.' : $ex->getMessage();
        }
    }
}

// Spots that are free right now, for the dropdown
$free_spots = $conn->query('SELECT spot_id, spot_number, spot_type, hourly_rate FROM parking_spots WHERE is_occupied = FALSE ORDER BY spot_number');

// Known plates, for the autocomplete list
$known = $conn->query('SELECT plate_number, make, model FROM vehicles ORDER BY plate_number');

$selected_spot = (int) ($_POST['spot_id'] ?? $_GET['spot'] ?? 0);

$page_title = 'Check In';
require 'includes/header.php';
?>

<h1>Check In a Car</h1>

<?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>

<?php if ($free_spots->num_rows === 0): ?>
    <div class="alert alert-error">The lot is full. Check a car out first.</div>
<?php else: ?>
<form method="post" class="card form-card">

    <div class="grid-2">
        <label>License Plate *
            <input type="text" name="plate" list="plates" required maxlength="15"
                   placeholder="ABC1234" value="<?= e($_POST['plate'] ?? '') ?>" class="upper">
            <datalist id="plates">
                <?php while ($k = $known->fetch_assoc()): ?>
                    <option value="<?= e($k['plate_number']) ?>"><?= e($k['make'] . ' ' . $k['model']) ?></option>
                <?php endwhile; ?>
            </datalist>
        </label>

        <label>Parking Spot *
            <select name="spot_id" required>
                <option value="">Choose a spot</option>
                <?php while ($s = $free_spots->fetch_assoc()): ?>
                    <option value="<?= (int) $s['spot_id'] ?>" <?= $selected_spot === (int) $s['spot_id'] ? 'selected' : '' ?>>
                        <?= e($s['spot_number']) ?> - <?= e(type_label($s['spot_type'])) ?> (<?= money($s['hourly_rate']) ?>/hr)
                    </option>
                <?php endwhile; ?>
            </select>
        </label>
    </div>

    <p class="muted">New car? Fill in the details below. For a car that has been here before, only the plate is needed.</p>

    <div class="grid-3">
        <label>Make <input type="text" name="make" maxlength="30" value="<?= e($_POST['make'] ?? '') ?>"></label>
        <label>Model <input type="text" name="model" maxlength="30" value="<?= e($_POST['model'] ?? '') ?>"></label>
        <label>Color <input type="text" name="color" maxlength="20" value="<?= e($_POST['color'] ?? '') ?>"></label>
        <label>Owner Name <input type="text" name="owner_name" maxlength="60" value="<?= e($_POST['owner_name'] ?? '') ?>"></label>
        <label>Owner Phone <input type="tel" name="owner_phone" maxlength="20" value="<?= e($_POST['owner_phone'] ?? '') ?>"></label>
    </div>

    <button type="submit" class="btn btn-primary">Create Ticket</button>
</form>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
