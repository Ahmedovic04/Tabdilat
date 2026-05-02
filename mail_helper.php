<?php
/**
 * Simple SMTP Mail Helper
 * Uses PHP's PHPMailer or a custom lightweight SMTP implementation.
 * For now, I will create a structured configuration in config.php 
 * and a robust mail sender here.
 */

function sendRasedEmail($to, $subject, $message) {
    // Gmail SMTP Configuration
    $smtp_user = 'abo.hyzar41@gmail.com';
    $smtp_pass = 'ruow qwda ikcs prhfE'; // User needs to generate an "App Password" from Google Security settings
    
    $headers = "From: Rased System <{$smtp_user}>\r\n" .
               "Reply-To: {$smtp_user}\r\n" .
               "Content-Type: text/plain; charset=utf-8\r\n" .
               "X-Mailer: PHP/" . phpversion();

    // Note: If the server doesn't have an SMTP relay configured, 
    // the best approach is using a library like PHPMailer.
    // However, to keep it lightweight without composer, we can use the default mail() 
    // if the hosting environment (like Nakama/Docker) is configured with an SMTP relay.
    
    // Attempt to send using default mail (configured via php.ini / ssmtp in docker)
    return @mail($to, $subject, $message, $headers);
}

/**
 * Advanced Email Sender for Substitution
 */
function sendSubstitutionEmails($db, $request_id) {
    $stmt = $db->prepare("
        SELECT r.*, 
               u1.name as req_name, u1.email as req_email, u1.subject_id as req_sub_id,
               u2.name as sub_name, u2.email as sub_email, u2.subject_id as sub_sub_id,
               c.name as class_name
        FROM rased_requests r
        JOIN rased_users u1 ON r.requester_id = u1.id
        JOIN rased_users u2 ON r.substitute_id = u2.id
        JOIN rased_classes c ON r.class_id = c.id
        WHERE r.id = ?
    ");
    $stmt->execute([$request_id]);
    $req = $stmt->fetch();
    
    if (!$req) return;

    // Get coordinators
    $stmtCoord = $db->prepare("SELECT email FROM rased_users WHERE subject_id = ? AND role = 'coordinator'");
    
    $stmtCoord->execute([$req['req_sub_id']]);
    $req_coord_email = $stmtCoord->fetchColumn();
    
    $stmtCoord->execute([$req['sub_sub_id']]);
    $sub_coord_email = $stmtCoord->fetchColumn();

    $to_emails = array_unique(array_filter([$req['req_email'], $req['sub_email'], $req_coord_email, $sub_coord_email]));
    
    if (empty($to_emails)) return;

    $subject = "إشعار اعتماد تبديل حصة - مدرسة معيذر (راصد)";
    $body = "تحية طيبة،\n\nتم اعتماد طلب التبديل رقم #{$request_id} رسمياً من قبل النائب الأكاديمي.\n\n" .
            "التفاصيل:\n" .
            "--------------------------\n" .
            "- المعلم الغائب: {$req['req_name']}\n" .
            "- المعلم البديل: {$req['sub_name']}\n" .
            "- الصف: {$req['class_name']}\n" .
            "- تاريخ التبديل: {$req['request_date']}\n" .
            "- الحصة: {$req['period_number']}\n" .
            "- موعد التعويض: " . ($req['repayment_date'] ?: 'سيتم التحديد لاحقاً') . "\n" .
            "--------------------------\n\n" .
            "يرجى مراجعة الجدول المحدث والالتزام بالمواعيد.\n" .
            "هذا إيميل تلقائي، يرجى عدم الرد عليه.\n\n" .
            "نظام راصد تبديلاتي - مدرسة معيذر الابتدائية";

    foreach ($to_emails as $email) {
        sendRasedEmail($email, $subject, $body);
    }
}
