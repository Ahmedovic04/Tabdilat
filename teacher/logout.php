<?php
require_once '../config.php';
startSecureSession();
unset($_SESSION['rased_user_id']);
unset($_SESSION['rased_username']);
unset($_SESSION['rased_name']);
unset($_SESSION['rased_role']);
unset($_SESSION['rased_is_new']);
header('Location: ../login.php');
exit;
