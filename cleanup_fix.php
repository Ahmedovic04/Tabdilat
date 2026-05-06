<?php
require_once 'config.php';
$db = getDB();

echo "<div style='direction:rtl; font-family:sans-serif;'>";
echo "<h3>بدء عملية تنظيف ودمج الحسابات المكررة...</h3>";

function normalize_arabic($str) {
    $str = trim($str);
    $str = str_replace(['أ', 'إ', 'آ'], 'ا', $str);
    $str = str_replace('ة', 'ه', $str);
    $str = str_replace('ى', 'ي', $str);
    return preg_replace('/\s+/', ' ', $str);
}

try {
    // 1. Fetch all teacher users
    $users = $db->query("SELECT id, name, username FROM rased_users WHERE role = 'teacher' ORDER BY id ASC")->fetchAll();
    
    $unique_names = [];
    $duplicates_found = 0;

    foreach ($users as $u) {
        $norm = normalize_arabic($u['name']);
        
        if (!isset($unique_names[$norm])) {
            $unique_names[$norm] = $u['id'];
            echo "✅ الحفاظ على الحساب الأصلي: {$u['name']} (ID: {$u['id']})<br>";
        } else {
            // It's a duplicate!
            $original_id = $unique_names[$norm];
            $duplicates_found++;
            
            echo "⚠️ دمج حساب مكرر: {$u['name']} (ID: {$u['id']}) -> إلى الحساب الأصلي (ID: {$original_id})<br>";
            
            // Move classes
            $stmtClasses = $db->prepare("UPDATE rased_teacher_classes SET teacher_id = ? WHERE teacher_id = ?");
            $stmtClasses->execute([$original_id, $u['id']]);
            
            // Move requests (as requester)
            $stmtReq1 = $db->prepare("UPDATE rased_requests SET requester_id = ? WHERE requester_id = ?");
            $stmtReq1->execute([$original_id, $u['id']]);
            
            // Move requests (as substitute)
            $stmtReq2 = $db->prepare("UPDATE rased_requests SET substitute_id = ? WHERE substitute_id = ?");
            $stmtReq2->execute([$original_id, $u['id']]);
            
            // Delete duplicate user
            $stmtDel = $db->prepare("DELETE FROM rased_users WHERE id = ?");
            $stmtDel->execute([$u['id']]);
        }
    }

    echo "<h4>تمت العملية بنجاح!</h4>";
    echo "إجمالي الحسابات التي تم دمجها وحذفها: $duplicates_found <br>";
    echo "<p>يرجى العودة لصفحة المستخدمين للتأكد من نظافة القائمة.</p>";
    echo "<a href='deputy/users.php'>العودة لإدارة المستخدمين</a>";

} catch (Exception $e) {
    echo "❌ خطأ أثناء التنظيف: " . $e->getMessage();
}

echo "</div>";
