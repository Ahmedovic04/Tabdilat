<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Riyadh');

// =============================================
// Database Configuration
// =============================================
define('DB_HOST', getenv('MYSQLHOST') ?: 'localhost');
define('DB_NAME', getenv('MYSQLDATABASE') ?: 'railway');
define('DB_USER', getenv('MYSQLUSER') ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: '');
define('DB_CHARSET', 'utf8mb4');
define('DB_PORT', getenv('MYSQLPORT') ?: '3306');

define('SITE_NAME', 'مدرسة معيذر الابتدائية للبنين');
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
             (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$protocol = $is_https ? 'https://' : 'http://';
$default_url = (isset($_SERVER['HTTP_HOST'])) ? $protocol . $_SERVER['HTTP_HOST'] : 'http://localhost';
define('SITE_URL', getenv('APP_URL') ?: (getenv('RAILWAY_PUBLIC_DOMAIN') ? 'https://'.getenv('RAILWAY_PUBLIC_DOMAIN') : $default_url));

// =============================================
// Database Connection (PDO)
// =============================================
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die(json_encode(['success' => false, 'message' => 'خطأ في الاتصال بقاعدة البيانات: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// =============================================
// Session & Auth Helpers
// =============================================
define('SESSION_TIMEOUT', 2400); // 40 minutes in seconds

function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check for session timeout
    if (isset($_SESSION['last_activity'])) {
        $elapsed = time() - $_SESSION['last_activity'];
        if ($elapsed > SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            // Start a new session if needed, but the user is now logged out
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
        }
    }
    
    // Update last activity time
    if (isset($_SESSION['user_id'])) {
        $_SESSION['last_activity'] = time();
    }
}

function requireLogin($role = null) {
    startSecureSession();

    if (!isset($_SESSION['user_id'])) {
        // بدل ما نوقف النظام، نرجع null session
        return false;
    }

    if ($role && $_SESSION['role'] !== $role && $_SESSION['role'] !== 'admin') {
        return false;
    }

    return true;
}

function requireAdmin() {
    startSecureSession();

    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        jsonResponse(false, 'غير مصرح', ['auth' => false]);
    }
}

function isLoggedIn() {
    startSecureSession();
    return isset($_SESSION['user_id']);
}

function currentUser() {
    return [
        'id'        => $_SESSION['user_id'] ?? null,
        'username'  => $_SESSION['username'] ?? null,
        'full_name' => $_SESSION['full_name'] ?? null,
        'role'      => $_SESSION['role'] ?? null,
    ];
}

function logout() {
    startSecureSession();
    session_destroy();
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

// =============================================
// JSON response helpers
// =============================================
function jsonResponse($success, $message = '', $data = []) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

// =============================================
// Auto-reset: mark old calls from previous days
// =============================================
function cleanOldCalls() {
    $db = getDB();
    // مسح الاستدعاءات التي مر عليها أكثر من 15 يوم
    $db->exec("DELETE FROM dismissal_calls WHERE call_date < DATE_SUB(CURRENT_DATE, INTERVAL 15 DAY)");
}
function autoResetCalls() {
    $db = getDB();

    $db->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id INT PRIMARY KEY,
            last_reset DATETIME
        )
    ");

    $stmt = $db->query("SELECT last_reset FROM settings WHERE id = 1");
    $row = $stmt->fetch();

    $now = new DateTime();

    if (!$row) {
        $db->prepare("INSERT INTO settings (id, last_reset) VALUES (1, NOW())")->execute();
        return;
    }

    $last = new DateTime($row['last_reset']);
    $diff = $now->getTimestamp() - $last->getTimestamp();

    if ($diff >= 86400) { // كل 24 ساعة
        cleanOldCalls();
        $db->prepare("UPDATE settings SET last_reset = NOW() WHERE id = 1")->execute();
    }
}
