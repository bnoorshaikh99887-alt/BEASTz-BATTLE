<?php
ob_start();
require_once __DIR__ . '/common/config.php';
check_user_auth();

$user_id = (int)$_SESSION['user_id'];
$amount_inr = isset($_POST['amount']) ? (float)$_POST['amount'] : 0.00;

if ($amount_inr < 10) {
    safe_redirect("wallet.php?error=" . urlencode("Minimum deposit amount is ₹10.00."));
    exit;
}

// Cashfree credentials (read from settings table, fallback to constants)
$app_id = get_setting('cashfree_app_id', '');
$secret_key = get_setting('cashfree_secret_key', '');
if (empty($app_id) && defined('CASHFREE_APP_ID')) $app_id = CASHFREE_APP_ID;
if (empty($secret_key) && defined('CASHFREE_SECRET_KEY')) $secret_key = CASHFREE_SECRET_KEY;

// Fetch user data
$u_stmt = $conn->prepare("SELECT username, email, phone FROM users WHERE id = ?");
$u_stmt->bind_param("i", $user_id);
$u_stmt->execute();
$u = $u_stmt->get_result()->fetch_assoc();
$u_stmt->close();

$order_id = 'order_' . time() . '_' . $user_id;
$cust_phone = (!empty($u['phone']) && strlen($u['phone']) == 10) ? $u['phone'] : "9878385810";
$cust_email = !empty($u['email']) ? $u['email'] : "player{$user_id}@beastzbattle.com";
$cust_name = !empty($u['username']) ? $u['username'] : "BeastzPlayer";

$host = $_SERVER['HTTP_HOST'] ?? 'beastz-battle.vercel.app';
$return_url = "https://" . $host . "/cashfree_return.php?order_id={order_id}";

$post_fields = [
    "order_id" => $order_id,
    "order_amount" => $amount_inr,
    "order_currency" => "INR",
    "customer_details" => [
        "customer_id" => "cust_" . $user_id,
        "customer_name" => $cust_name,
        "customer_email" => $cust_email,
        "customer_phone" => $cust_phone
    ],
    "order_meta" => [
        "return_url" => $return_url
    ]
];

$endpoint = "https://api.cashfree.com/pg/orders";
$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_fields));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "x-api-version: 2023-08-01",
    "x-client-id: " . $app_id,
    "x-client-secret: " . $secret_key
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$response = curl_exec($ch);
$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err = curl_error($ch);
curl_close($ch);

if ($curl_err) {
    safe_redirect("wallet.php?error=" . urlencode("Gateway Server Connection Error: " . $curl_err));
    exit;
}

$res_data = json_decode($response, true);
if ($http_status !== 200 || !isset($res_data['payment_session_id'])) {
    $err_message = $res_data['message'] ?? 'Unable to create payment session. Please verify payment settings or use UPI QR deposit.';
    safe_redirect("wallet.php?error=" . urlencode("Gateway Notice ($http_status): " . $err_message));
    exit;
}

$payment_session_id = $res_data['payment_session_id'];

// Securely log order into cashfree_orders table for atomic state verification
if ($conn && !$conn->connect_error) {
    @$conn->query("CREATE TABLE IF NOT EXISTS `cashfree_orders` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `order_id` VARCHAR(100) NOT NULL UNIQUE,
        `user_id` INT NOT NULL,
        `amount` DECIMAL(10, 2) NOT NULL,
        `status` ENUM('PENDING', 'PAID', 'FAILED', 'USER_DROPPED') NOT NULL DEFAULT 'PENDING',
        `payment_session_id` VARCHAR(255) NULL,
        `cf_payment_id` VARCHAR(100) NULL,
        `raw_response` TEXT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_user` (`user_id`),
        INDEX `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $ins_cf = $conn->prepare("INSERT INTO cashfree_orders (order_id, user_id, amount, status, payment_session_id) VALUES (?, ?, ?, 'PENDING', ?) ON DUPLICATE KEY UPDATE payment_session_id = VALUES(payment_session_id)");
    if ($ins_cf) {
        $ins_cf->bind_param("sids", $order_id, $user_id, $amount_inr, $payment_session_id);
        $ins_cf->execute();
        $ins_cf->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Connecting to Gateway - BEASTzBATTLE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Official Cashfree Web JS SDK v3 -->
    <script src="https://sdk.cashfree.com/js/v3/cashfree.js"></script>
</head>
<body class="bg-slate-900 text-white min-h-screen flex flex-col items-center justify-center p-4">
    <div class="w-full max-w-sm bg-slate-800 border border-slate-700 rounded-3xl p-6 text-center shadow-2xl">
        <div class="w-12 h-12 mx-auto rounded-full border-4 border-blue-500 border-t-transparent animate-spin mb-4"></div>
        <h3 class="text-base font-extrabold text-white mb-1">Connecting to Gateway...</h3>
        <p class="text-xs text-slate-400 mb-2">Amount: <span class="text-blue-400 font-mono font-black"><?= format_inr($amount_inr); ?></span></p>
        <p class="text-[11px] text-slate-400 mb-5">Opening secure checkout (GPay, PhonePe, Paytm, Cards, NetBanking)...</p>
        
        <button id="manualPayBtn" type="button" onclick="launchCashfree()" class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-black transition shadow-xs flex items-center justify-center gap-2 active:scale-95">
            <i class="fa-solid fa-lock text-xs"></i>
            <span>Pay <?= format_inr($amount_inr); ?> Now</span>
        </button>

        <a href="wallet.php" class="inline-block text-xs font-bold text-slate-400 hover:text-slate-300 mt-4 transition">
            &larr; Cancel and return to Wallet
        </a>
    </div>

    <script>
        var cashfree;
        try {
            cashfree = Cashfree({ mode: "production" });
        } catch(e) {
            console.error("Cashfree SDK init error:", e);
        }

        function launchCashfree() {
            if (cashfree) {
                var checkoutOptions = {
                    paymentSessionId: "<?= $payment_session_id; ?>",
                    redirectTarget: "_self"
                };
                cashfree.checkout(checkoutOptions);
            } else {
                window.location.href = "https://payments.cashfree.com/order/#" + "<?= $payment_session_id; ?>";
            }
        }

        document.addEventListener("DOMContentLoaded", function() {
            setTimeout(launchCashfree, 300);
        });
    </script>
</body>
</html>
