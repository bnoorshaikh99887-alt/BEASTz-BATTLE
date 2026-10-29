<?php
ob_start();
require_once __DIR__ . '/common/config.php';
check_user_auth();

$user_id = (int)$_SESSION['user_id'];
$amount_inr = isset($_POST['amount']) ? (float)$_POST['amount'] : 0.00;

if ($amount_inr < 10) {
    safe_redirect("wallet.php?error=" . urlencode("Minimum deposit amount ₹10.00 hai."));
    exit;
}

// Cashfree credentials (read from settings table, fallback to constants)
$app_id = get_setting('cashfree_app_id', '');
$secret_key = get_setting('cashfree_secret_key', '');
if (empty($app_id)) $app_id = CASHFREE_APP_ID;
if (empty($secret_key)) $secret_key = CASHFREE_SECRET_KEY;

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
    $err_message = $res_data['message'] ?? 'Payment session create nahi ho paya.';
    safe_redirect("wallet.php?error=" . urlencode("Gateway Notice ($http_status): " . $err_message));
    exit;
}

$payment_session_id = $res_data['payment_session_id'];
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Connecting to Gateway - BEASTzBATTLE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-slate-950 text-white min-h-screen flex flex-col items-center justify-center p-4">
    <div class="w-full max-w-sm bg-slate-900 border border-slate-800 rounded-2xl p-6 text-center shadow-2xl">
        <div class="w-12 h-12 mx-auto rounded-full border-4 border-violet-500 border-t-transparent animate-spin mb-4"></div>
        <h3 class="text-base font-bold text-white mb-1">Connecting to Cashfree Gateway...</h3>
        <p class="text-xs text-slate-400 mb-2">Amount: <span class="text-emerald-400 font-mono font-bold"><?= format_inr($amount_inr); ?></span></p>
        <p class="text-[11px] text-slate-500 mb-5">Opening secure payment page (UPI, GPay, PhonePe, Paytm, Cards)...</p>

        <!-- Direct POST Form to Cashfree Checkout Endpoint -->
        <form id="cashfreeCheckoutForm" method="POST" action="https://api.cashfree.com/pg/view/sessions/checkout">
            <input type="hidden" name="payment_session_id" value="<?= htmlspecialchars($payment_session_id); ?>">
            <button type="submit" class="w-full py-3 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white rounded-xl text-xs font-bold transition shadow-lg shadow-emerald-600/30 flex items-center justify-center gap-2">
                <i class="fa-solid fa-lock text-xs"></i>
                <span>Continue to Cashfree Payment</span>
            </button>
        </form>

        <a href="wallet.php" class="inline-block text-xs text-slate-500 hover:text-slate-300 mt-4 transition">
            &larr; Cancel and return to Wallet
        </a>
    </div>

    <script>
        // Auto-redirect by submitting the form immediately
        document.addEventListener("DOMContentLoaded", function() {
            const form = document.getElementById('cashfreeCheckoutForm');
            if (form) {
                setTimeout(function() {
                    form.submit();
                }, 200);
            }
        });
    </script>
</body>
</html>
