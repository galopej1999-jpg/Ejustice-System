<?php
require_once __DIR__ . '/../config/db.php';

// Simple protection: only run once in development
// In real deployment, delete this file.
try {
    $pdo->beginTransaction();

    $users = [
        ['System Admin','admin@example.com','system_admin'],
        ['Sample Complainant','complainant@example.com','complainant'],
        ['Police Staff','police@example.com','police_staff'],
        ['MTC Staff','mtcstaff@example.com','mtc_staff'],
        ['MTC Judge','mtcjudge@example.com','mtc_judge'],
        ['RTC Staff','rtcstaff@example.com','rtc_staff'],
        ['RTC Judge','rtcjudge@example.com','rtc_judge'],
        ['Barangay Staff','barangay@example.com','barangay_staff'],
        ['Punong Barangay','punongbarangay@example.com','punong_barangay'],
        ['Lupon Secretary','lupon@example.com','lupon_secretary'],
    ];

    foreach ($users as $u) {
        [$name,$email,$role] = $u;
        $hash = password_hash('password', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT IGNORE INTO users (full_name, email, password_hash, role) VALUES (:n,:e,:p,:r)");
        $stmt->execute([
            ':n' => $name,
            ':e' => $email,
            ':p' => $hash,
            ':r' => $role,
        ]);
    }

    // Seed a default barangay
    $stmt = $pdo->prepare("INSERT IGNORE INTO barangay_info (barangay_name, municipality, province, punong_barangay_name, contact_number, email, address) 
                          VALUES (:bn, :m, :p, :pb, :cn, :e, :a)");
    $stmt->execute([
        ':bn' => 'Barangay Sample',
        ':m' => 'City of Manila',
        ':p' => 'National Capital Region',
        ':pb' => 'Punong Barangay Sample',
        ':cn' => '(02) 1234-5678',
        ':e' => 'barangay@sample.gov.ph',
        ':a' => '123 Sample St, Sample Barangay, Manila, NCR'
    ]);

    // Assign barangay staff to the sample barangay
    $barangayStmt = $pdo->query("SELECT id FROM barangay_info WHERE barangay_name = 'Barangay Sample'");
    $barangay = $barangayStmt->fetch();
    
    if ($barangay) {
        $barangayId = $barangay['id'];
        $roles = [
            ['barangay@example.com', 'Barangay Clerk'],
            ['punongbarangay@example.com', 'Punong Barangay'],
            ['lupon@example.com', 'Lupon Secretary']
        ];
        
        foreach ($roles as [$email, $position]) {
            $userStmt = $pdo->query("SELECT id FROM users WHERE email = '$email'");
            $user = $userStmt->fetch();
            
            if ($user) {
                $assignStmt = $pdo->prepare("INSERT IGNORE INTO barangay_users (user_id, barangay_id, position) VALUES (:uid, :bid, :pos)");
                $assignStmt->execute([
                    ':uid' => $user['id'],
                    ':bid' => $barangayId,
                    ':pos' => $position
                ]);
            }
        }
    }

    $pdo->commit();
    echo "Demo users and barangay information created. You can now login using the credentials in README.txt. Please delete this file for security.";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error seeding users: " . $e->getMessage();
}

