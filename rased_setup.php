<?php
require_once 'config.php';

echo "Starting Rased Tabdeelaty Setup...\n";

try {
    $db = getDB();

    // 1. Create Tables
    $db->exec("
        CREATE TABLE IF NOT EXISTS rased_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            name VARCHAR(100) NOT NULL,
            role ENUM('teacher', 'coordinator', 'deputy') DEFAULT 'teacher',
            subject_id INT NULL,
            is_new BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS rased_subjects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) UNIQUE NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS rased_classes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) UNIQUE NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS rased_teacher_classes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id INT NOT NULL,
            class_id INT NOT NULL,
            day_of_week INT NOT NULL,
            period_number INT NOT NULL,
            FOREIGN KEY (teacher_id) REFERENCES rased_users(id) ON DELETE CASCADE,
            FOREIGN KEY (class_id) REFERENCES rased_classes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS rased_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            requester_id INT NOT NULL,
            substitute_id INT NOT NULL,
            class_id INT NOT NULL,
            request_date DATE NOT NULL,
            period_number INT NOT NULL,
            repayment_date DATE NULL,
            repayment_period INT NULL,
            req_coordinator_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            sub_coordinator_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            deputy_status ENUM('pending', 'approved', 'approved_with_mod', 'rejected') DEFAULT 'pending',
            repayment_status ENUM('pending', 'completed') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (requester_id) REFERENCES rased_users(id),
            FOREIGN KEY (substitute_id) REFERENCES rased_users(id),
            FOREIGN KEY (class_id) REFERENCES rased_classes(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Fix Duplicate Subjects (Migration & Cleanup)
    try {
        // First, ensure the 'name' column is unique to prevent future duplicates
        $db->exec("ALTER TABLE rased_subjects ADD UNIQUE (name)");
    } catch (Exception $e) {
        // If duplicates already exist, UNIQUE index creation will fail. 
        // We need to manually clean them up first.
        $db->exec("
            DELETE t1 FROM rased_subjects t1
            INNER JOIN rased_subjects t2 
            WHERE t1.id > t2.id AND t1.name = t2.name
        ");
        // Now try adding unique again
        try { $db->exec("ALTER TABLE rased_subjects ADD UNIQUE (name)"); } catch(Exception $e2){}
    }

    // Seed Subjects securely with INSERT IGNORE
    $subjects = ['لغة عربية', 'لغة إنجليزية', 'رياضيات', 'اجتماعيات', 'تربية رياضية', 'فنون', 'حوسبة'];
    $stmtSub = $db->prepare("INSERT IGNORE INTO rased_subjects (name) VALUES (?)");
    foreach ($subjects as $sub) {
        $stmtSub->execute([$sub]);
    }

    echo "Setup, Cleanup and Seeding completed successfully.<br>\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>\n";
}
