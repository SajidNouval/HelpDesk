<?php

// Simple database verification without Laravel bootstrap
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=helpdeskta', 'root', '12345');
    echo "✅ Database connection successful\n";
    
    // Check if tables exist
    $tables = ['users', 'categories', 'staff_profiles'];
    foreach ($tables as $table) {
        $result = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($result->rowCount() > 0) {
            echo "✅ Table '$table' exists\n";
        } else {
            echo "❌ Table '$table' NOT found\n";
        }
    }
    
    // Check if there are any categories
    $result = $pdo->query("SELECT COUNT(*) as count FROM categories");
    $row = $result->fetch(PDO::FETCH_ASSOC);
    echo "✅ Categories in database: " . $row['count'] . "\n";
    
    // Check if there are any users
    $result = $pdo->query("SELECT COUNT(*) as count FROM users");
    $row = $result->fetch(PDO::FETCH_ASSOC);
    echo "✅ Users in database: " . $row['count'] . "\n";
    
    // Check staff_profiles table structure
    $result = $pdo->query("DESCRIBE staff_profiles");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    echo "✅ Staff Profiles columns: " . implode(', ', array_column($columns, 'Field')) . "\n";
    
    echo "\n✅ All verifications passed!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
