<?php
$db_host = "gateway01.ap-southeast-1.prod.aws.tidbcloud.com";
$db_user = "33NZV583e1gPsMh.root";
$db_pass = "ZOxtqv6OtRayGxb1";
$db_name = "test";
$db_port = 4000;

$status_message = "";
$status_type = "";

$conn = mysqli_init();
$conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
$conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
@$conn->real_connect($db_host, $db_user, $db_pass, $db_name, $db_port, NULL, MYSQLI_CLIENT_SSL);

if ($conn->connect_error) {
    die("TiDB Cloud Connection Error: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_installer'])) {
    $queries = [
        "CREATE TABLE IF NOT EXISTS `users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(60) NOT NULL UNIQUE,
            `email` VARCHAR(120) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `wallet_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `upi_id` VARCHAR(255) NULL,
            `status` ENUM('active','blocked') NOT NULL DEFAULT 'active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );",

        "CREATE TABLE IF NOT EXISTS `admin` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(60) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL
        );",

        "CREATE TABLE IF NOT EXISTS `tournaments` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(150) NOT NULL,
            `game_name` VARCHAR(80) NOT NULL,
            `entry_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `prize_pool` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `match_time` DATETIME NOT NULL,
            `room_id` VARCHAR(100) NULL DEFAULT '',
            `room_password` VARCHAR(100) NULL DEFAULT '',
            `commission_percent` INT NOT NULL DEFAULT 20,
            `status` ENUM('Upcoming','Live','Completed') NOT NULL DEFAULT 'Upcoming',
            `winner_user_id` INT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );",

        "CREATE TABLE IF NOT EXISTS `participants` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `tournament_id` INT NOT NULL,
            `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_participant` (`user_id`, `tournament_id`)
        );",

        "CREATE TABLE IF NOT EXISTS `transactions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `amount` DECIMAL(10,2) NOT NULL,
            `type` ENUM('credit','debit') NOT NULL,
            `description` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );",

        "CREATE TABLE IF NOT EXISTS `deposits` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `amount` DECIMAL(10,2) NOT NULL,
            `transaction_id` VARCHAR(100) NOT NULL,
            `status` ENUM('Pending','Completed','Rejected') NOT NULL DEFAULT 'Pending',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );",

        "CREATE TABLE IF NOT EXISTS `withdrawals` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `amount` DECIMAL(10,2) NOT NULL,
            `upi_id` VARCHAR(255) NOT NULL,
            `status` ENUM('Pending','Completed','Rejected') NOT NULL DEFAULT 'Pending',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );",

        "CREATE TABLE IF NOT EXISTS `settings` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `setting_key` VARCHAR(60) NOT NULL UNIQUE,
            `setting_value` TEXT NULL
        );"
    ];

    foreach ($queries as $sql) {
        if (!$conn->query($sql)) {
            $status_message = "SQL Error: " . $conn->error;
            $status_type = "error";
            break;
        }
    }

    if ($status_type !== "error") {
        // Insert Admin (admin / admin123)
        $hashed = password_hash("admin123", PASSWORD_DEFAULT);
        $conn->query("INSERT IGNORE INTO admin (username, password) VALUES ('admin', '$hashed')");

        // Insert Settings
        $conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES 
            ('admin_upi_id', 'adeptplay@upi'),
            ('cashfree_app_id', '1494190b594e98ed762a2a943320914941'),
            ('cashfree_secret_key', 'cfsk_ma_prod_979295dd86c34ea7eef3d56c1e07d25e_27097462'),
            ('cashfree_env', 'PROD'),
            ('support_whatsapp', '+919878385810')");

        // Insert Tournaments
        $conn->query("INSERT IGNORE INTO tournaments (title, game_name, entry_fee, prize_pool, match_time, status) VALUES 
            ('BGMI Battle Royale Solo Championship', 'Battlegrounds Mobile India', 50.00, 1500.00, DATE_ADD(NOW(), INTERVAL 2 HOUR), 'Upcoming'),
            ('Free Fire Max Squad Clash Cup', 'Free Fire Max', 30.00, 900.00, DATE_ADD(NOW(), INTERVAL 4 HOUR), 'Upcoming')");

        $status_message = "TiDB Cloud Database setup completed successfully!";
        $status_type = "success";
        header("Refresh: 2; url=login.php");
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install Adept Play (TiDB Cloud)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl text-center">
        <div class="w-14 h-14 mx-auto rounded-2xl bg-gradient-to-tr from-violet-600 to-indigo-500 flex items-center justify-center text-white text-2xl mb-3 shadow-lg">
            <i class="fa-solid fa-cloud"></i>
        </div>
        <h2 class="text-xl font-bold text-white">TiDB Cloud Setup</h2>
        <p class="text-xs text-slate-400 mb-4">Initialize schema on TiDB Serverless Database</p>

        <?php if (!empty($status_message)): ?>
            <div class="p-3 mb-4 rounded-xl text-xs font-semibold <?= ($status_type === 'success') ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300' ?>">
                <?= $status_message; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <button type="submit" name="run_installer" class="w-full py-3 bg-violet-600 hover:bg-violet-500 text-white font-bold rounded-xl text-xs shadow-lg transition">
                Initialize Cloud Database
            </button>
        </form>
    </div>
</body>
</html>