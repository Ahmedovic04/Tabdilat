<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../mail_helper.php';


// This script should be run via cron at 6:50 AM Qatar time
// Example cron: 50 6 * * * php /path/to/cron/daily_email.php

$db = getDB();
$today = date('Y-m-d');
$message = "";

// Fetch all requests for today (substitutions) - approved by substitute is now fully approved
$stmt = $db->prepare("
    SELECT r.*, c.name as class_name, u1.name as requester_name, u2.name as substitute_name
    FROM rased_requests r
    JOIN rased_classes c ON r.class_id = c.id
    JOIN rased_users u1 ON r.requester_id = u1.id
    JOIN rased_users u2 ON r.substitute_id = u2.id
    WHERE r.request_date = ? AND (r.sub_coordinator_status = 'approved' OR r.deputy_status = 'approved')
    ORDER BY r.period_number ASC
");
$stmt->execute([$today]);
$requests = $stmt->fetchAll();

// Fetch all compensation sessions scheduled for today - approved by substitute is now fully approved
$stmtCompensation = $db->prepare("
    SELECT r.*, c.name as class_name, u1.name as requester_name, u2.name as substitute_name
    FROM rased_requests r
    JOIN rased_classes c ON r.class_id = c.id
    JOIN rased_users u1 ON r.requester_id = u1.id
    JOIN rased_users u2 ON r.substitute_id = u2.id
    WHERE r.repayment_date = ? AND (r.sub_coordinator_status = 'approved' OR r.deputy_status = 'approved')
    ORDER BY r.repayment_period ASC
");
$stmtCompensation->execute([$today]);
$compensations = $stmtCompensation->fetchAll();

// Build message for substitutions
if (empty($requests) && empty($compensations)) {
    $message = "📋 تقرير يوم: " . $today . "\n";
    $message .= "==========================================\n\n";
    $message .= "لا توجد أي تبديلات حصص أو تعويضات مسجلة لهذا اليوم.\n";
    $message .= "\n🔄 لا توجد حصص تعويض مجدولة لهذا اليوم.\n";
} else {
    // Substitutions Section
    if (!empty($requests)) {
        $message .= "📋 تقرير تبديلات الحصص ليوم: " . $today . "\n";
        $message .= "==========================================\n\n";
        
        foreach ($requests as $req) {
            $status = 'معلق';
            if ($req['sub_coordinator_status'] === 'approved' || $req['deputy_status'] === 'approved') {
                $status = '✅ معتمد نهائياً (وافق البديل)';
            } elseif ($req['deputy_status'] === 'rejected') {
                $status = '❌ مرفوض';
            }
            
            $message .= "• الحصة: {$req['period_number']}\n";
            $message .= "  - الصف: {$req['class_name']}\n";
            $message .= "  - المعلم الغائب: {$req['requester_name']}\n";
            $message .= "  - المعلم البديل: {$req['substitute_name']}\n";
            $message .= "  - الحالة: $status\n";
            $message .= "------------------------------------------\n";
        }
    } else {
        $message .= "📋 لا توجد تبديلات حصص لهذا اليوم.\n\n";
    }
    
    // Compensation Section
    $message .= "\n\n";
    if (!empty($compensations)) {
        $message .= "🔄 حصص التعويض المجدولة ليوم: " . $today . "\n";
        $message .= "==========================================\n\n";
        
        foreach ($compensations as $comp) {
            $message .= "• الحصة: {$comp['repayment_period']}\n";
            $message .= "  - الصف: {$comp['class_name']}\n";
            $message .= "  - المعلم المكلف بالتعويض: {$comp['substitute_name']}\n";
            $message .= "  - المعلم المستفيد من التعويض: {$comp['requester_name']}\n";
            $message .= "  - تاريخ التبديل الأصلي: {$comp['request_date']}\n";
            $message .= "  - الحصة الأصلية: {$comp['period_number']}\n";
            $message .= "------------------------------------------\n";
        }
    } else {
        $message .= "🔄 لا توجد حصص تعويض مجدولة لهذا اليوم.\n";
    }
}

$to = 'fursan2019@QatarEducation.onmicrosoft.com';
$subject = "تقرير تبديلات الحصص والتعويضات اليومي - " . $today;

if (sendRasedEmail($to, $subject, $message)) {
    echo "Email sent successfully for $today";
} else {
    echo "Failed to send email.";
}
