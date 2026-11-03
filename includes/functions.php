<?php
// Shared helpers used by every page

session_start();
require_once __DIR__ . '/../config/db.php';

// Escape text before printing it in HTML (stops XSS)
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Send the user to the login page if they are not logged in
function require_login()
{
    if (empty($_SESSION['user_id'])) {
        redirect('login.php');
    }
}

// Only admins can open some pages
function require_admin()
{
    require_login();
    if ($_SESSION['role'] !== 'admin') {
        flash('Only an admin can do that.', 'error');
        redirect('index.php');
    }
}

// One-time message shown at the top of the next page
function flash($message, $type = 'success')
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

function money($amount)
{
    return '$' . number_format((float) $amount, 2);
}

// Plates are saved in capitals with no spaces or dashes, like "KJM4821"
function clean_plate($plate)
{
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $plate));
}

function valid_plate($plate)
{
    return preg_match('/^[A-Z0-9]{2,10}$/', $plate) === 1;
}

// Show how long a car has been parked, like "2h 15m"
function duration($minutes)
{
    $minutes = max(0, (int) $minutes);
    return floor($minutes / 60) . 'h ' . ($minutes % 60) . 'm';
}

// Spot type for display, "ev" shows as "EV"
function type_label($type)
{
    return $type === 'ev' ? 'EV' : ucfirst($type);
}
