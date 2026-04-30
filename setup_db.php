<?php
/**
 * Database Setup Script
 * Run this once to create the database and import sample data
 */

echo "=== Orphanage Management System - Database Setup ===\n\n";

try {
    // Connect without database
    $pdo = new PDO('mysql:host=localhost;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "[OK] Connected to MySQL\n";

    // Read SQL file
    $sql = file_get_contents(__DIR__ . '/config/setup.sql');
    
    // Split by semicolons (simple split for our SQL)
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $count = 0;
    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            try {
                $pdo->exec($stmt);
                $count++;
            } catch (PDOException $e) {
                // Skip duplicate entry errors (running setup again)
                if ($e->getCode() != 23000) {
                    echo "[WARN] " . $e->getMessage() . "\n";
                }
            }
        }
    }
    
    echo "[OK] Executed $count SQL statements\n";
    
    // Verify tables
    $pdo->exec('USE orphanage_db');
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "[OK] Tables created: " . implode(', ', $tables) . "\n";
    
    // Verify data
    $orphanCount = $pdo->query("SELECT COUNT(*) FROM orphans")->fetchColumn();
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $adminCount = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
    $donationCount = $pdo->query("SELECT COUNT(*) FROM donations")->fetchColumn();
    
    echo "\n=== Database Summary ===\n";
    echo "Admins: $adminCount\n";
    echo "Users: $userCount\n";
    echo "Orphans: $orphanCount\n";
    echo "Donations: $donationCount\n";
    echo "\n[SUCCESS] Database setup complete!\n";
    echo "\nDefault Credentials:\n";
    echo "  Admin: admin / admin123\n";
    echo "  User: rajesh@email.com / admin123\n";
    echo "  User: sunita@email.com / admin123\n";
    
} catch (PDOException $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    echo "\nMake sure MySQL is running in XAMPP Control Panel.\n";
}
?>
