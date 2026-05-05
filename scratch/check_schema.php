<?php
require_once 'config.php';
$db = getDB();
$stmt = $db->query("DESCRIBE rased_requests");
$cols = $stmt->fetchAll();
header('Content-Type: application/json');
echo json_encode($cols, JSON_PRETTY_PRINT);
