<?php
require_once 'config.php';
startSecureSession();

if (!isset($_SESSION['rased_user_id'])) {
    header('Location: login.php');
    exit;
}

$role = $_SESSION['rased_role'];

if ($role === 'teacher') {
    header('Location: teacher/index.php');
} elseif ($role === 'coordinator') {
    header('Location: coordinator/index.php');
} elseif ($role === 'deputy') {
    header('Location: deputy/index.php');
} else {
    // Fallback
    echo "Role not recognized.";
    exit;
}
