<?php
// cashfree_create_order.php - Live Production Order Creation
require_once __DIR__ . '/common/config.php';
check_user_auth();

$user_id = (int)$_SESSION['user_id'];
$amount_inr = isset($_POST['amount']) ? (float)$_POST['amount'] : 0.00;

if ($amount_inr < 10) {
    header("Location: wallet.php?error=" . urlencode("Minimum deposit amount ₹10.00 hai."));
    exit;
}

$app_id = CASHFREE_APP_ID;
$secret_key = CASHFREE_SECRET_KEY;

// Fetch user data
$u_stmt = $conn->prepare("SELECT username, email, phone FROM users WHERE id = ?");
$u_stmt->bind_param("i", $user_id);
$u_stmt->execute();
$u = $u_stmt->get_result()->fetch_assoc();
$u_stmt->close();

$order_id = 'order_' . time() . '_' . $user_id;
$cust_phone = (!empty($u['phone']) && strlen($u['phone']) >= 10) ? preg_replace('/[^0-9]/', '', $u['phone']) : "9878385810";
if (strlen($cust_phone) > 10) $cust_phone = substr($cust_phone, -10);

$post_fields = [
    "order_id" => $order_id,
    "order_amount" => $amount_inr,
    "order_currency" => "INR",
    "customer_details" => [
        "customer_id" => "cust_" . $user_id,
        "customer_name" => !empty($u['username']) ? $u['username'] : "AdeptPlayer",
        "customer_email" => !empty($u['email']) ? $u['email'] : "player{$user_id}@adeptplay.com",
        "customer_phone" => $cust_phone
    ],
    "order_meta" => [
        "return_url" => "https://beastz-battle.vercel.app/cashfree_return.php?order_id={order_id}"
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

$response = curl_exec($ch);
$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err = curl_error($ch);
curl_close($ch);

if ($curl_err) {
    header("Location: wallet.php?error=" . urlencode("Cashfree Server Connection Error: " . $curl_err));
    exit;
}

$res_data = json_decode($response, true);
if ($http_status !== 200 || !isset($res_data['payment_session_id'])) {
    $err_message = $res_data['message'] ?? 'Unable to create Cashfree order.';
    header("Location: wallet.php?error=" . urlencode("Cashfree Error ($http_status): " . $err_message));
    exit;
}

$payment_session_id = $res_data['payment_session_id'];

// Save initial pending deposit in database
$dep_stmt = $conn->prepare("INSERT INTO deposits (user_id, order_id, amount, method, status) VALUES (?, ?, ?, 'Cashfree', 'PENDING') ON DUPLICATE KEY UPDATE amount = ?");
if ($dep_stmt) {
    $dep_stmt->bind_param("isdd", $user_id, $order_id, $amount_inr, $amount_inr);
    $dep_stmt->execute();
    $dep_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Connecting to Cashfree - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://sdk.cashfree.com/js/v3/cashfree.js"></script>
</head>
<body class="bg-[#0b0f19] text-white min-h-screen flex flex-col items-center justify-center p-4">
    <div class="w-full max-w-sm bg-[#131b2e] border border-gray-800 rounded-2xl p-6 text-center shadow-2xl">
        <div class="w-12 h-12 mx-auto rounded-full border-4 border-amber-500 border-t-transparent animate-spin mb-4"></div>
        <h3 class="text-base font-bold mb-1">Redirecting to Cashfree Payment...</h3>
        <p class="text-xs text-gray-400 mb-4">Amount: <span class="text-amber-400 font-mono font-bold"><?= format_inr($amount_inr); ?></span></p>
        
        <button onclick="launchCashfree()" class="w-full py-3 bg-gradient-to-r from-amber-500 to-amber-600 text-black rounded-xl text-xs font-extrabold uppercase tracking-wider shadow-lg transition active:scale-95">
            Proceed to Pay
        </button>
        <a href="/wallet.php" class="block text-xs text-gray-500 hover:text-gray-300 mt-4">Cancel Transaction</a>
    </div>

    <script>
        const cashfree = Cashfree({
            mode: "production"
        });

        function launchCashfree() {
            cashfree.checkout({
                paymentSessionId: "<?= $payment_session_id; ?>",
                redirectTarget: "_self" // Mobile browser full-screen redirect (UPI apps support)
            });
        }

        window.onload = function() {
            setTimeout(launchCashfree, 500);
        };
    </script>
</body>
</html>
