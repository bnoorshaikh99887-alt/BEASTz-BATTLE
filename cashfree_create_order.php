<?php
require_once __DIR__ . '/common/config.php';
check_user_auth();

$user_id = (int)$_SESSION['user_id'];
$amount_inr = isset($_POST['amount']) ? (float)$_POST['amount'] : 0.00;

if ($amount_inr < 10) {
    header("Location: wallet.php?error=" . urlencode("Minimum deposit amount ₹10.00 hai."));
    exit;
}

$app_id = '1494190b594e98ed762a2a943320914941';
$secret_key = 'cfsk_ma_prod_979295dd86c34ea7eef3d56c1e07d25e_27097462';

// Fetch user data
$u_stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
$u_stmt->bind_param("i", $user_id);
$u_stmt->execute();
$u = $u_stmt->get_result()->fetch_assoc();
$u_stmt->close();

$order_id = 'order_' . time() . '_' . $user_id;

// Check if running on HTTPS
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

$post_fields = [
    "order_id" => $order_id,
    "order_amount" => $amount_inr,
    "order_currency" => "INR",
    "customer_details" => [
        "customer_id" => "cust_" . $user_id,
        "customer_name" => !empty($u['username']) ? $u['username'] : "AdeptPlayer",
        "customer_email" => !empty($u['email']) ? $u['email'] : "player{$user_id}@adeptplay.com",
        "customer_phone" => "9876543210"
    ]
];

// Only send order_meta if on real HTTPS domain, otherwise Cashfree SDK modal handles it seamlessly
if ($is_https) {
    $host = $_SERVER['HTTP_HOST'];
    $base_dir = rtrim(dirname($_SERVER['REQUEST_URI']), '/\\');
    $post_fields["order_meta"] = [
        "return_url" => "https://" . $host . $base_dir . "/cashfree_return.php?order_id={order_id}"
    ];
}

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
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Connecting to Cashfree - Adept Play</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://sdk.cashfree.com/js/v3/cashfree.js"></script>
</head>
<body class="bg-slate-950 text-white min-h-screen flex flex-col items-center justify-center p-4">
    <div class="w-full max-w-sm bg-slate-900 border border-slate-800 rounded-2xl p-6 text-center shadow-2xl">
        <div class="w-12 h-12 mx-auto rounded-full border-4 border-violet-500 border-t-transparent animate-spin mb-4"></div>
        <h3 class="text-base font-bold mb-1">Opening Cashfree Gateway...</h3>
        <p class="text-xs text-slate-400 mb-4">Amount: <span class="text-emerald-400 font-mono font-bold"><?= format_inr($amount_inr); ?></span></p>

        <button onclick="launchCashfree()" class="w-full py-2.5 bg-violet-600 hover:bg-violet-500 text-white rounded-xl text-xs font-bold transition">
            Continue to Cashfree
        </button>
        <a href="wallet.php" class="block text-xs text-slate-500 hover:text-slate-300 mt-4">Cancel</a>
    </div>

    <script>
        const cashfree = Cashfree({
            mode: "production"
        });

        function launchCashfree() {
            cashfree.checkout({
                paymentSessionId: "<?= $payment_session_id; ?>"
            }).then(function(result) {
                if (result.error) {
                    alert(result.error.message || "Payment cancelled");
                    window.location.href = "wallet.php?error=" + encodeURIComponent(result.error.message || "Payment cancelled");
                    return;
                }
                // Redirect on completion to verify and credit wallet
                window.location.href = "cashfree_return.php?order_id=<?= urlencode($order_id); ?>";
            });
        }

        window.onload = function() {
            launchCashfree();
        };
    </script>
</body>
</html>