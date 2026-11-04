<?php
require_once 'includes/functions.php';

// Already logged in? Go to the dashboard.
if (!empty($_SESSION['user_id'])) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Prepared statement: the username is sent separately from the SQL,
    // so it can never change the query
    $stmt = $conn->prepare('SELECT user_id, username, password_hash, role FROM users WHERE username = ?');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    // password_verify compares the typed password with the saved hash
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id']  = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role']     = $user['role'];
        redirect('index.php');
    }

    // Same message for wrong user or wrong password, so attackers can't guess usernames
    $error = 'Wrong username or password.';
}

$page_title = 'Log In';
require 'includes/header.php';
?>

<div class="login-box card">
    <div class="logo big">P</div>
    <h1>Parking Lot Manager</h1>
    <p class="muted">Staff login</p>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <label>Username
            <input type="text" name="username" required autofocus value="<?= e($_POST['username'] ?? '') ?>">
        </label>
        <label>Password
            <input type="password" name="password" required>
        </label>
        <button type="submit" class="btn btn-primary full">Log In</button>
    </form>
</div>

<?php require 'includes/footer.php'; ?>
