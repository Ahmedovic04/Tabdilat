<?php
require_once '../config.php';
startSecureSession();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['rased_user_id']) || $_SESSION['rased_role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$db = getDB();
$teacher_id = $_SESSION['rased_user_id'];
$action = $_GET['action'] ?? '';

if ($action === 'get_classes') {
    $date = $_GET['date'] ?? '';
    if (!$date) { echo json_encode(['success' => false]); exit; }
    
    // Day of week: 0=Sunday, 4=Thursday
    $timestamp = strtotime($date);
    $day_of_week = date('w', $timestamp); // 0 (for Sunday) through 6 (for Saturday)
    
    if ($day_of_week > 4) { // Friday or Saturday
        echo json_encode(['success' => true, 'classes' => []]);
        exit;
    }
    
    $stmt = $db->prepare("
        SELECT tc.period_number, tc.class_id, c.name as class_name 
        FROM rased_teacher_classes tc 
        JOIN rased_classes c ON tc.class_id = c.id 
        WHERE tc.teacher_id = ? AND tc.day_of_week = ?
        ORDER BY tc.period_number
    ");
    $stmt->execute([$teacher_id, $day_of_week]);
    $classes = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'classes' => $classes, 'day' => $day_of_week]);
    exit;
}

if ($action === 'get_substitutes') {
    $class_id = (int)($_GET['class_id'] ?? 0);
    $day_of_week = (int)($_GET['day'] ?? -1);
    $period = (int)($_GET['period'] ?? 0);
    
    if (!$class_id || $day_of_week < 0 || !$period) {
        echo json_encode(['success' => false, 'message' => 'بيانات ناقصة']);
        exit;
    }
    
    // Find teachers who teach this class in general
    // BUT are NOT busy in this day/period
    $stmt = $db->prepare("
        SELECT DISTINCT u.id, u.name 
        FROM rased_teacher_classes tc
        JOIN rased_users u ON tc.teacher_id = u.id
        WHERE tc.class_id = ? AND u.id != ?
        AND u.id NOT IN (
            SELECT teacher_id FROM rased_teacher_classes WHERE day_of_week = ? AND period_number = ?
        )
    ");
    $stmt->execute([$class_id, $teacher_id, $day_of_week, $period]);
    $substitutes = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'substitutes' => $substitutes]);
    exit;
}

if ($action === 'submit_request') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || empty($data['requests'])) {
        echo json_encode(['success' => false, 'message' => 'لا توجد بيانات']);
        exit;
    }
    
    $date = $data['date'];
    
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("
            INSERT INTO rased_requests 
            (requester_id, substitute_id, class_id, request_date, period_number) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($data['requests'] as $req) {
            $stmt->execute([
                $teacher_id, 
                $req['substitute_id'], 
                $req['class_id'], 
                $date, 
                $req['period_number']
            ]);
        }
        $db->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'حدث خطأ']);
    }
    exit;
}
