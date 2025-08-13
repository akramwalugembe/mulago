<?php
// ==============================================
// DATABASE CONFIGURATION (matches your structure)
// ==============================================
function getDBConnection() {
    $host = 'localhost';
    $dbname = 'mulagopharmacy';
    $username = 'PharmUser';
    $password = 'Seper3P@ssword!2025';
    
    try {
        $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $conn;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// ==============================================
// ADMIN USER CONFIGURATION (customize these)
// ==============================================
$adminDetails = [
    'username' => 'pharmacy_admin',
    'password' => 'SecurePharmacy@2025',
    'full_name' => 'System Administrator',
    'email' => 'admin@mulagopharmacy.com',
    'department' => 'Main Pharmacy',
    'role' => 'admin'
];

// ==============================================
// HTML OUTPUT STYLING
// ==============================================
?>
<!DOCTYPE html>
<html>
<head>
    <title>Mulago Pharmacy - Admin Setup</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .success { color: #155724; background-color: #d4edda; padding: 15px; border-radius: 4px; margin: 20px 0; }
        .error { color: #721c24; background-color: #f8d7da; padding: 15px; border-radius: 4px; margin: 20px 0; }
        .warning { color: #856404; background-color: #fff3cd; padding: 15px; border-radius: 4px; margin: 20px 0; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; }
        .credentials { background: #e2e3e5; padding: 15px; border-radius: 4px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Mulago Pharmacy Admin Setup</h1>
        <?php
        try {
            // Get database connection
            $db = getDBConnection();
            
            // Start transaction
            $db->beginTransaction();

            // 1. Create department if not exists
            $stmt = $db->prepare("INSERT IGNORE INTO departments 
                                (department_name, location, contact_person, contact_phone) 
                                VALUES (?, 'Main Building', 'Head Pharmacist', '+256700000000')");
            $stmt->execute([$adminDetails['department']]);
            
            // Get department ID
            $stmt = $db->prepare("SELECT department_id FROM departments WHERE department_name = ? LIMIT 1");
            $stmt->execute([$adminDetails['department']]);
            $deptId = $stmt->fetchColumn();

            if (!$deptId) {
                throw new Exception("Failed to create or find department");
            }

            // 2. Create admin user with hashed password
            $passwordHash = password_hash($adminDetails['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            
            $stmt = $db->prepare("INSERT INTO users 
                                (username, password_hash, full_name, email, department_id, role, is_active) 
                                VALUES (?, ?, ?, ?, ?, ?, 1)
                                ON DUPLICATE KEY UPDATE 
                                password_hash = VALUES(password_hash),
                                is_active = 1,
                                updated_at = CURRENT_TIMESTAMP");
            
            $success = $stmt->execute([
                $adminDetails['username'],
                $passwordHash,
                $adminDetails['full_name'],
                $adminDetails['email'],
                $deptId,
                $adminDetails['role']
            ]);

            if (!$success) {
                throw new Exception("Failed to create admin user");
            }

            // Commit transaction
            $db->commit();

            // Output success
            echo "<div class='success'>
                    <h3>✓ Admin user created successfully!</h3>
                  </div>
                  <div class='credentials'>
                    <h4>Login Credentials:</h4>
                    <p><strong>Username:</strong> {$adminDetails['username']}</p>
                    <p><strong>Password:</strong> {$adminDetails['password']}</p>
                    <p><strong>Login URL:</strong> <a href='auth/login.php'>auth/login.php</a></p>
                  </div>
                  <div class='warning'>
                    <h3>⚠ IMPORTANT SECURITY NOTICE</h3>
                    <ol>
                        <li>Change this password immediately after first login</li>
                        <li><strong>DELETE this script from your server</strong></li>
                        <li>Consider creating additional admin accounts</li>
                        <li>Restrict access to your database credentials</li>
                    </ol>
                  </div>";

        } catch (Exception $e) {
            // Rollback on error
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            
            echo "<div class='error'>
                    <h3>✗ Error creating admin user</h3>
                    <p><strong>Error:</strong> {$e->getMessage()}</p>
                    <p>Please check:</p>
                    <ul>
                        <li>Database server is running</li>
                        <li>Database credentials are correct</li>
                        <li>Database tables exist (run your SQL schema first)</li>
                    </ul>
                  </div>";
        }
        ?>
    </div>
</body>
</html>