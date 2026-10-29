<?php
// install.php - Database Table Installer for Adept Play
require_once __DIR__ . '/common/config.php';

echo "<!DOCTYPE html><html><head><title>Install Database</title><script src='https://cdn.tailwindcss.com'></script></head><body class='bg-gray-900 text-white p-6 font-sans'>";
echo "<div class='max-w-md mx-auto bg-gray-800 p-6 rounded-xl border border-gray-700 shadow-2xl'>";
echo "<h1 class='text-xl font-bold text-amber-400 mb-4 flex items-center'><span class='w-3 h-3 bg-amber-400 rounded-full mr-2 animate-ping'></span> Installing Adept Play Database...</h1>";

if (!$conn || $conn->connect_error) {
    die("<div class='bg-red-500/20 text-red-400 p-4 rounded-lg border border-red-500/50 mb-4 font-mono text-sm'>Database Connection Failed: " . ($conn ? $conn->connect_error : 'Init error') . "</div></div></body></html>");
}

$queries = [
    // 1. Users Table
    "CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) DEFAULT '',
        `username` VARCHAR(50) NOT NULL UNIQUE,
        `email` VARCHAR(100) DEFAULT '',
        `phone` VARCHAR(20) DEFAULT '',
        `password` VARCHAR(255) NOT NULL,
        `wallet` DECIMAL(10, 2) DEFAULT 0.00,
        `role` ENUM('user', 'admin') DEFAULT 'user',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // 2. Tournaments Table
    "CREATE TABLE IF NOT EXISTS `tournaments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(150) NOT NULL,
        `game` VARCHAR(50) DEFAULT 'BGMI',
        `map` VARCHAR(50) DEFAULT 'Erangel',
        `match_type` VARCHAR(50) DEFAULT 'Squad',
        `entry_fee` DECIMAL(10, 2) DEFAULT 0.00,
        `per_kill` DECIMAL(10, 2) DEFAULT 0.00,
        `prize_pool` DECIMAL(10, 2) DEFAULT 0.00,
        `match_time` DATETIME NOT NULL,
        `total_slots` INT DEFAULT 100,
        `joined_slots` INT DEFAULT 0,
        `room_id` VARCHAR(50) DEFAULT '',
        `room_password` VARCHAR(50) DEFAULT '',
        `status` ENUM('upcoming', 'ongoing', 'completed', 'cancelled') DEFAULT 'upcoming',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // 3. User Joined Tournaments
    "CREATE TABLE IF NOT EXISTS `user_tournaments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `tournament_id` INT NOT NULL,
        `game_name` VARCHAR(100) NOT NULL,
        `slot_no` INT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // 4. Deposits Table
    "CREATE TABLE IF NOT EXISTS `deposits` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `order_id` VARCHAR(100) NOT NULL UNIQUE,
        `amount` DECIMAL(10, 2) NOT NULL,
        `method` VARCHAR(50) DEFAULT 'Cashfree',
        `status` ENUM('PENDING', 'SUCCESS', 'FAILED') DEFAULT 'PENDING',
        `reference_id` VARCHAR(100) DEFAULT '',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // 5. Withdrawals Table
    "CREATE TABLE IF NOT EXISTS `withdrawals` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `amount` DECIMAL(10, 2) NOT NULL,
        `upi_id` VARCHAR(100) NOT NULL,
        `status` ENUM('PENDING', 'APPROVED', 'REJECTED') DEFAULT 'PENDING',
        `admin_note` VARCHAR(255) DEFAULT '',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // 6. Settings Table
    "CREATE TABLE IF NOT EXISTS `settings` (
        `key` VARCHAR(50) PRIMARY KEY,
        `value` TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

$errors = [];
foreach ($queries as $sql) {
    if (!$conn->query($sql)) {
        $errors[] = $conn->error;
    }
}

// Create default admin user if not exists (username: admin, pass: admin123)
$admin_pass = password_hash('admin123', PASSWORD_BCRYPT);
$conn->query("INSERT IGNORE INTO `users` (`name`, `username`, `email`, `phone`, `password`, `wallet`, `role`) 
              VALUES ('Admin', 'admin', 'admin@adeptplay.com', '9878385810', '$admin_pass', 1000.00, 'admin')");

// Seed default settings
$conn->query("INSERT IGNORE INTO `settings` (`key`, `value`) VALUES 
              ('upi_id', '9878385810@upi'),
              ('min_deposit', '50'),
              ('min_withdrawal', '100'),
              ('whatsapp_no', '9878385810')");

if (empty($errors)) {
    echo "<div class='bg-emerald-500/20 text-emerald-400 p-4 rounded-lg border border-emerald-500/50 mb-4 font-semibold'>";
    echo "✅ Database & All Tables Installed Successfully!";
    echo "</div>";
    echo "<div class='text-sm space-y-2 text-gray-300'>";
    echo "<p><strong class='text-white'>Admin Account:</strong></p>";
    echo "<p class='bg-gray-900 p-2 rounded font-mono text-xs'>Username: <span class='text-amber-400'>admin</span><br>Password: <span class='text-amber-400'>admin123</span></p>";
    echo "</div>";
    echo "<a href='/index.php' class='mt-6 block text-center bg-amber-500 hover:bg-amber-600 text-black font-bold py-2.5 rounded-lg shadow-lg'>Go to Home Page</a>";
} else {
    echo "<div class='bg-red-500/20 text-red-400 p-4 rounded-lg border border-red-500/50 mb-4'>";
    echo "Errors occurred: <br>" . implode("<br>", $errors);
    echo "</div>";
}

echo "</div></body></html>";
