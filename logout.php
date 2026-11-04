<?php
require_once 'includes/functions.php';

// Clear everything in the session and destroy it
$_SESSION = [];
session_destroy();

header('Location: login.php');
exit;
