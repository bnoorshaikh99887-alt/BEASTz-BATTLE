<?php
require_once __DIR__ . '/common/config.php';

$message = "";
$status = "success";

try {
    // 1. Create deposits table if not exists
    $conn->query("CREATE TABLE IF NOT EXISTS `deposits` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `amount` DECIMAL(10,2) NOT NULL,
        `transaction_id` VARCHAR(100) NOT NULL,
        `status` ENUM('Pending','Completed','Rejected') NOT NULL DEFAULT 'Pending',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    // 2. Create withdrawals table if not exists
    $conn->query("CREATE TABLE IF NOT EXISTS `withdrawals` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `amount` DECIMAL(10,2) NOT NULL,
        `upi_id` VARCHAR(255) NOT NULL,
        `status` ENUM('Pending','Completed','Rejected') NOT NULL DEFAULT 'Pending',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    // 3. Create settings table if not exists
    $conn->query("CREATE TABLE IF NOT EXISTS `settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `setting_key` VARCHAR(60) NOT NULL UNIQUE,
        `setting_value` TEXT NULL
    ) ENGINE=InnoDB;");

    // 4. Safely check if upi_id exists in users table
    $col_check = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '{$db_name}' AND TABLE_NAME = 'users' AND COLUMN_NAME = 'upi_id'");
    if ($col_check && $col_check->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN upi_id VARCHAR(255) NULL AFTER wallet_balance;");
    }

    // Default settings seed
    $conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES 
        ('admin_upi_id', 'adeptplay@upi'),
        ('admin_qr_image', ''),
        ('support_whatsapp', '+919878385810')");

    $message = "Database schema updated successfully! You can now safely delete this file.";
} catch (Exception $e) {
    $status = "error";
    $message = "Migration Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migration - Adept Play</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl text-center">
        <div class="w-14 h-14 mx-auto rounded-2xl <?= ($status === 'success') ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-400 border border-rose-500/30' ?> flex items-center justify-center text-2xl mb-4">
            <i class="fa-solid <?= ($status === 'success') ? 'fa-check-double' : 'fa-triangle-exclamation' ?>"></i>
        </div>
        
        <h2 class="text-xl font-bold text-white mb-2">Database Schema Update</h2>
        
        <div class="p-4 rounded-xl <?= ($status === 'success') ? 'bg-emerald-950/40 border border-emerald-800/60 text-emerald-300' : 'bg-rose-950/40 border border-rose-800/60 text-rose-300' ?> text-sm font-semibold mb-6">
            <?= ($status === 'success') ? "✅ " . $message : $message; ?>
        </div>

        <div class="flex gap-2">
            <a href="wallet.php" class="flex-1 py-3 bg-violet-600 hover:bg-violet-700 text-white rounded-xl text-xs font-bold transition">
                Go to Wallet
            </a>
            <a href="admin/index.php" class="flex-1 py-3 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-bold transition">
                Admin Panel
            </a>
        </div>
    </div>
</body>
</html>