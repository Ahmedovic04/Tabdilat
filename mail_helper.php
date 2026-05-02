<?php
/**
 * Advanced SMTP Mail Helper for Rased System
 * Connects directly to Gmail SMTP servers via SSL/TLS
 */

function sendRasedEmail($to, $subject, $message) {
    // --- Gmail SMTP Configuration ---
    $smtp_host = "ssl://smtp.gmail.com";
    $smtp_port = 465;
    $smtp_user = 'abo.hyzar41@gmail.com';
    $smtp_pass = 'YOUR_APP_PASSWORD_HERE'; // MUST BE A 16-CHARACTER APP PASSWORD
    // --------------------------------

    $header = "To: <" . $to . ">\r\n";
    $header .= "From: Rased System <" . $smtp_user . ">\r\n";
    $header .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $header .= "MIME-Version: 1.0\r\n";
    $header .= "Content-Type: text/plain; charset=utf-8\r\n";
    $header .= "Content-Transfer-Encoding: 8bit\r\n";
    $header .= "X-Mailer: PHP/" . phpversion();

    // Open connection to Gmail
    $socket = @fsockopen($smtp_host, $smtp_port, $errno, $errstr, 10);
    if (!$socket) return false;

    function get_response($socket) {
        $res = "";
        while ($str = fgets($socket, 515)) {
            $res .= $str;
            if (substr($str, 3, 1) == " ") break;
        }
        return $res;
    }

    get_response($socket);
    fwrite($socket, "EHLO " . $_SERVER['HTTP_HOST'] . "\r\n");
    get_response($socket);
    fwrite($socket, "AUTH LOGIN\r\n");
    get_response($socket);
    fwrite($socket, base64_encode($smtp_user) . "\r\n");
    get_response($socket);
    fwrite($socket, base64_encode($smtp_pass) . "\r\n");
    get_response($socket);
    fwrite($socket, "MAIL FROM: <" . $smtp_user . ">\r\n");
    get_response($socket);
    fwrite($socket, "RCPT TO: <" . $to . ">\r\n");
    get_response($socket);
    fwrite($socket, "DATA\r\n");
    get_response($socket);
    fwrite($socket, $header . "\r\n" . $message . "\r\n.\r\n");
    get_response($socket);
    fwrite($socket, "QUIT\r\n");
    fclose($socket);

    return true;
}

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

    $stmtCoord = $db->prepare("SELECT email FROM rased_users WHERE subject_id = ? AND role = 'coordinator' AND email IS NOT NULL");
    
    $stmtCoord->execute([$req['req_sub_id']]);
    $req_coord_email = $stmtCoord->fetchColumn();
    
    $stmtCoord->execute([$req['sub_sub_id']]);
    $sub_coord_email = $stmtCoord->fetchColumn();

    $to_emails = array_unique(array_filter([$req['req_email'], $req['sub_email'], $req_coord_email, $sub_coord_email]));
    
    if (empty($to_emails)) return;

    $subject = "إشعار اعتماد تبديل حصة - نظام راصد";
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
            "يرجى مراجعة الجدول المحدث والالتزام بالمواعيد.\n\n" .
            "نظام راصد تبديلاتي - مدرسة معيذر الابتدائية";

    foreach ($to_emails as $email) {
        sendRasedEmail($email, $subject, $body);
    }
}
