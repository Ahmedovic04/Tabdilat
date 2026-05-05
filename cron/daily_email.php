<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../mail_helper.php';


// This script should be run via cron at 6:50 AM Qatar time
// Example cron: 50 6 * * * php /path/to/cron/daily_email.php

$db = getDB();
$today = date('Y-m-d');

// Fetch all requests for today
$stmt = $db->prepare("
    SELECT r.*, c.name as class_name, u1.name as requester_name, u2.name as substitute_name
    FROM rased_requests r
    JOIN rased_classes c ON r.class_id = c.id
    JOIN rased_users u1 ON r.requester_id = u1.id
    JOIN rased_users u2 ON r.substitute_id = u2.id
    WHERE r.request_date = ?
    ORDER BY r.period_number ASC
");
$stmt->execute([$today]);
$requests = $stmt->fetchAll();

if (empty($requests)) {
    // Optional: Send email even if no substitutions? The user said "all substitutions for this day"
    // If none, we can send a message saying "No substitutions today".
    $message = "لا توجد أي تبديلات حصص مسجلة لهذا اليوم: " . $today;
} else {
    $message = "تقرير تبديلات الحصص ليوم: " . $today . "\n";
    $message .= "------------------------------------------\n\n";
    
    foreach ($requests as $req) {
        $status = 'معلق';
        if ($req['deputy_status'] === 'approved') $status = 'معتمد';
        elseif ($req['deputy_status'] === 'rejected') $status = 'مرفوض';
        
        $message .= "• الحصة: {$req['period_number']}\n";
        $message .= "  - الصف: {$req['class_name']}\n";
        $message .= "  - المعلم الغائب: {$req['requester_name']}\n";
        $message .= "  - المعلم البديل: {$req['substitute_name']}\n";
        $message .= "  - الحالة: $status\n";
        $message .= "------------------------------------------\n";
    }
}

$to = 'allusersgroup@gmail.com';
$subject = "تقرير تبديلات الحصص اليومي - " . $today;

if (sendRasedEmail($to, $subject, $message)) {
    echo "Email sent successfully for $today";
} else {
    echo "Failed to send email.";
}
