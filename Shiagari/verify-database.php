#!/usr/bin/env php
<?php
/**
 * SHIAGARI Database Integrity Verification Script
 * 
 * Run this in terminal to verify the login/profile database fix is working:
 * 
 *   php verify-database.php
 * 
 * Or visit in browser:
 *   http://localhost/verify-database.php
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';

// Colors for CLI output
$colors = [
    'reset' => "\033[0m",
    'green' => "\033[32m",
    'red' => "\033[31m",
    'yellow' => "\033[33m",
    'blue' => "\033[34m",
];

function printHeader($text) {
    global $colors;
    echo "\n" . $colors['blue'] . "=== $text ===" . $colors['reset'] . "\n";
}

function printSuccess($text) {
    global $colors;
    echo $colors['green'] . "✓ $text" . $colors['reset'] . "\n";
}

function printError($text) {
    global $colors;
    echo $colors['red'] . "✗ $text" . $colors['reset'] . "\n";
}

function printWarning($text) {
    global $colors;
    echo $colors['yellow'] . "⚠ $text" . $colors['reset'] . "\n";
}

// Determine if running in CLI or browser
$isCLI = php_sapi_name() === 'cli';

if (!$isCLI) {
    echo "<!DOCTYPE html><html><head><style>";
    echo "body { font-family: monospace; background: #0b1626; color: #fff; padding: 20px; }";
    echo "pre { background: #0f172a; padding: 15px; border-radius: 8px; }";
    echo ".success { color: #10b981; }";
    echo ".error { color: #ef4444; }";
    echo ".warning { color: #f59e0b; }";
    echo ".header { color: #3b82f6; font-weight: bold; margin-top: 20px; }";
    echo "</style></head><body><pre>";
}

printHeader("SHIAGARI Database Integrity Verification");

// Check 1: Database credentials configured
printHeader("1. Database Configuration");
if (DB_HOST && DB_NAME && DB_USER !== '') {
    printSuccess("Database host: " . DB_HOST);
    printSuccess("Database name: " . DB_NAME);
    printSuccess("Database user: " . DB_USER);
} else {
    printError("Database credentials not configured in config/database.php");
}

// Check 2: Database connectivity
printHeader("2. Database Connection");
if (isDatabaseConnected()) {
    printSuccess("Successfully connected to database");
} else {
    printError("Cannot connect to database");
    printWarning("Make sure:");
    printWarning("  1. MySQL is running");
    printWarning("  2. Database exists: " . DB_NAME);
    printWarning("  3. Credentials are correct in config/database.php");
}

// Check 3: Required tables
printHeader("3. Required Tables");
$requiredTables = ['users', 'projects', 'project_members', 'posts', 'post_comments'];
$allTableExists = true;

foreach ($requiredTables as $table) {
    $result = queryDB("SHOW TABLES LIKE ?", [$table]);
    if (is_array($result) && count($result) > 0) {
        printSuccess("Table exists: $table");
    } else {
        printError("Table missing: $table");
        $allTableExists = false;
    }
}

if (!$allTableExists) {
    printWarning("Run database setup script:");
    printWarning("  Windows: setup-database.bat");
    printWarning("  Mac/Linux: ./setup-database.sh");
}

// Check 4: Users table structure
printHeader("4. Users Table Structure");
if (isDatabaseConnected()) {
    $columns = queryDB("DESCRIBE users");
    if (is_array($columns) && count($columns) > 0) {
        $requiredColumns = ['id', 'firebase_uid', 'email', 'full_name', 'username', 'role', 'created_at', 'updated_at'];
        $missingColumns = [];
        
        foreach ($requiredColumns as $col) {
            $found = false;
            foreach ($columns as $column) {
                if ($column['Field'] === $col) {
                    $found = true;
                    break;
                }
            }
            if ($found) {
                printSuccess("Column: $col");
            } else {
                printError("Missing column: $col");
                $missingColumns[] = $col;
            }
        }
        
        if (count($missingColumns) > 0) {
            printWarning("Missing columns: " . implode(', ', $missingColumns));
        }
    } else {
        printError("Cannot read users table structure");
    }
}

// Check 5: API files exist
printHeader("5. Required API Files");
$requiredFiles = [
    'api/profile.php',
    'api/health.php',
    'config/database.php',
    'auth/login.php',
    'auth/register.php',
    'profile/profile.html'
];

foreach ($requiredFiles as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        printSuccess("File exists: $file");
    } else {
        printError("File missing: $file");
    }
}

// Check 6: Test data
printHeader("6. User Data Sample");
if (isDatabaseConnected()) {
    $users = queryDB("SELECT COUNT(*) as count FROM users");
    if (is_array($users) && isset($users[0]['count'])) {
        $count = $users[0]['count'];
        printSuccess("Total users in database: $count");
        
        if ($count > 0) {
            $firstUser = getRowDB("SELECT id, email, full_name, username, role, created_at FROM users LIMIT 1");
            if ($firstUser) {
                echo "\nFirst user sample:\n";
                echo "  Email: " . $firstUser['email'] . "\n";
                echo "  Full Name: " . $firstUser['full_name'] . "\n";
                echo "  Username: " . $firstUser['username'] . "\n";
                echo "  Role: " . $firstUser['role'] . "\n";
                echo "  Created: " . $firstUser['created_at'] . "\n";
            }
        } else {
            printWarning("No users in database yet. Create one by registering.");
        }
    }
}

// Summary
printHeader("Summary");
echo "\nNext steps:\n";
echo "1. Make sure all database checks passed (green checkmarks)\n";
echo "2. Visit: http://localhost (login page)\n";
echo "3. Sign up with a test account\n";
echo "4. Go to profile page\n";
echo "5. Profile should show your data from database\n";
echo "6. Run this script again to verify user was synced\n";

// Test API endpoints
printHeader("Testing API Endpoints");
echo "Database Health Check: http://localhost/api/health.php?action=health\n";
echo "User Profile: http://localhost/api/profile.php (requires login)\n";
echo "User Integrity: http://localhost/api/health.php?action=user (requires login)\n";

if (!$isCLI) {
    echo "</pre></body></html>";
}

?>
