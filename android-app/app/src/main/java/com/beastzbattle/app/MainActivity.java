package com.beastzbattle.app;

import android.annotation.SuppressLint;
import android.app.Activity;
import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.graphics.Bitmap;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.View;
import android.view.Window;
import android.view.WindowManager;
import android.webkit.JavascriptInterface;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.FrameLayout;
import android.widget.Toast;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;

public class MainActivity extends Activity {

    private WebView webView;
    private ValueCallback<Uri[]> uploadMessage;
    private final static int FILECHOOSER_RESULTCODE = 101;
    private final static int PERMISSION_REQUEST_NOTIFICATIONS = 102;
    private static final String APP_URL = "https://beastz-battle.vercel.app";
    private static final String CHANNEL_ID = "beastz_push_alerts";
    
    private Handler notificationHandler;
    private Runnable notificationPoller;
    private SharedPreferences prefs;

    @SuppressLint("SetJavaScriptEnabled")
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        prefs = getSharedPreferences("beastz_prefs", MODE_PRIVATE);

        // Hardware acceleration & status bar styling
        Window window = getWindow();
        window.addFlags(WindowManager.LayoutParams.FLAG_DRAWS_SYSTEM_BAR_BACKGROUNDS);
        window.clearFlags(WindowManager.LayoutParams.FLAG_TRANSLUCENT_STATUS);

        // Root layout - absolutely clean, zero top progress bars or green lines
        FrameLayout rootLayout = new FrameLayout(this);
        rootLayout.setBackgroundColor(0xFF05070E); // Deep cyber dark

        webView = new WebView(this);
        webView.setBackgroundColor(0xFF05070E);
        webView.setLayerType(View.LAYER_TYPE_HARDWARE, null);
        webView.setOverScrollMode(View.OVER_SCROLL_NEVER);
        webView.setVerticalScrollBarEnabled(false);
        webView.setHorizontalScrollBarEnabled(false);

        rootLayout.addView(webView, new FrameLayout.LayoutParams(
                FrameLayout.LayoutParams.MATCH_PARENT, 
                FrameLayout.LayoutParams.MATCH_PARENT
        ));

        setContentView(rootLayout);

        // 1. Create High-Priority Notification Channel
        createNotificationChannel();

        // 2. Ask Notification Permission immediately on launch (Android 13+ / API 33+)
        if (Build.VERSION.SDK_INT >= 33) {
            if (checkSelfPermission("android.permission.POST_NOTIFICATIONS") != PackageManager.PERMISSION_GRANTED) {
                requestPermissions(new String[]{"android.permission.POST_NOTIFICATIONS"}, PERMISSION_REQUEST_NOTIFICATIONS);
            }
        }

        // Configure WebView
        WebSettings settings = webView.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setDatabaseEnabled(true);
        settings.setAllowFileAccess(true);
        settings.setAllowContentAccess(true);
        settings.setUseWideViewPort(true);
        settings.setLoadWithOverviewMode(true);
        settings.setCacheMode(WebSettings.LOAD_DEFAULT);
        settings.setRenderPriority(WebSettings.RenderPriority.HIGH);
        settings.setSupportZoom(false);
        settings.setBuiltInZoomControls(false);

        // Custom User-Agent to recognize Native Android App
        settings.setUserAgentString(settings.getUserAgentString() + " BEASTzNativeApp/1.2.0 Android");

        // Native JS Bridge for Direct Web-to-Native Notification Call
        webView.addJavascriptInterface(new Object() {
            @JavascriptInterface
            public void postNativeNotification(String title, String message, String actionUrl) {
                showSystemNotification(title, message, actionUrl);
            }
        }, "BEASTzNative");

        webView.setWebViewClient(new WebViewClient() {
            @Override
            public void onPageStarted(WebView view, String url, Bitmap favicon) {
                super.onPageStarted(view, url, favicon);
            }

            @Override
            public void onPageFinished(WebView view, String url) {
                super.onPageFinished(view, url);
            }

            @Override
            public boolean shouldOverrideUrlLoading(WebView view, String url) {
                if (url == null) return false;

                // Support UPI apps (PhonePe, GooglePay, Paytm, BHIM, WhatsApp)
                if (url.startsWith("upi://") || url.startsWith("intent://") || 
                    url.startsWith("whatsapp://") || url.startsWith("phonepe://") || 
                    url.startsWith("paytmmp://") || url.startsWith("tez://")) {
                    try {
                        Intent intent = Intent.parseUri(url, Intent.URI_INTENT_SCHEME);
                        if (intent != null) {
                            startActivity(intent);
                            return true;
                        }
                    } catch (Exception e) {
                        try {
                            Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(url));
                            startActivity(intent);
                            return true;
                        } catch (Exception e2) {
                            Toast.makeText(MainActivity.this, "Supported payment app not installed", Toast.LENGTH_SHORT).show();
                            return true;
                        }
                    }
                }

                // Keep Cashfree payment gateway & internal URLs inside WebView
                if (url.contains("beastz-battle.vercel.app") || 
                    url.contains("cashfree.com") || 
                    url.contains("cashfree") ||
                    url.contains("merchants.cashfree.com")) {
                    return false;
                }

                // External links to browser
                try {
                    Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(url));
                    startActivity(intent);
                    return true;
                } catch (Exception e) {
                    return false;
                }
            }

            @Override
            public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
                super.onReceivedError(view, request, error);
                
                // Ignore aborted requests and third-party gateways
                if (error != null) {
                    int errCode = error.getErrorCode();
                    if (errCode == -3 || errCode == WebViewClient.ERROR_UNKNOWN) {
                        return;
                    }
                }

                if (request != null && request.getUrl() != null) {
                    String reqUrl = request.getUrl().toString();
                    if (reqUrl.contains("cashfree.com") || reqUrl.contains("cashfree") || reqUrl.contains("analytics")) {
                        view.post(new Runnable() {
                            @Override
                            public void run() {
                                view.loadUrl(APP_URL + "?tab=wallet");
                            }
                        });
                        return;
                    }
                }

                // Clean offline fallback if main domain drops
                if (request != null && request.isForMainFrame()) {
                    String curUrl = view.getUrl();
                    if (curUrl != null && (curUrl.contains("cashfree") || curUrl.contains("return"))) {
                        view.loadUrl(APP_URL + "?tab=wallet");
                        return;
                    }

                    String errorHtml = "<html><body style='background-color:#05070e;color:#ffffff;font-family:sans-serif;text-align:center;padding:60px 20px;'>"
                            + "<div style='width:64px;height:64px;margin:0 auto 16px;background:rgba(250,204,21,0.15);border:1px solid #facc15;border-radius:20px;display:flex;align-items:center;justify-content:center;'><span style='font-size:30px;'>&#9889;</span></div>"
                            + "<h2 style='color:#facc15;margin:0 0 8px;font-size:20px;font-weight:900;'>Network Disconnected</h2>"
                            + "<p style='color:#94a3b8;font-size:13px;line-height:1.5;margin:0 0 24px;'>Please check your mobile data or Wi-Fi connection.</p>"
                            + "<button onclick='location.reload()' style='background:#facc15;color:#05070e;border:none;padding:12px 30px;border-radius:12px;font-weight:900;font-size:13px;cursor:pointer;'>RETRY</button>"
                            + "</body></html>";
                    view.loadDataWithBaseURL(null, errorHtml, "text/html", "UTF-8", null);
                }
            }
        });

        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public boolean onShowFileChooser(WebView webView, ValueCallback<Uri[]> filePathCallback, FileChooserParams fileChooserParams) {
                if (uploadMessage != null) {
                    uploadMessage.onReceiveValue(null);
                    uploadMessage = null;
                }
                uploadMessage = filePathCallback;
                Intent intent = fileChooserParams.createIntent();
                try {
                    startActivityForResult(intent, FILECHOOSER_RESULTCODE);
                } catch (Exception e) {
                    uploadMessage = null;
                    return false;
                }
                return true;
            }
        });

        webView.loadUrl(APP_URL);

        // 3. Start Real-Time Background Push Notification Poller (Zomato-Style)
        startNotificationPoller();
    }

    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(
                    CHANNEL_ID,
                    "BEASTz BATTLE Announcements",
                    NotificationManager.IMPORTANCE_HIGH
            );
            channel.setDescription("Live tournament alerts, room passwords & updates");
            channel.enableVibration(true);
            channel.enableLights(true);
            NotificationManager manager = getSystemService(NotificationManager.class);
            if (manager != null) {
                manager.createNotificationChannel(channel);
            }
        }
    }

    public void showSystemNotification(String title, String message, String actionUrl) {
        try {
            NotificationManager manager = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
            Intent intent = new Intent(this, MainActivity.class);
            if (actionUrl != null && !actionUrl.isEmpty()) {
                if (actionUrl.startsWith("http")) {
                    intent.setData(Uri.parse(actionUrl));
                } else {
                    intent.setData(Uri.parse(APP_URL + (actionUrl.startsWith("/") ? "" : "/") + actionUrl));
                }
            }
            PendingIntent pendingIntent = PendingIntent.getActivity(
                    this, 
                    (int) System.currentTimeMillis(), 
                    intent, 
                    PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE
            );

            Notification.Builder builder;
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                builder = new Notification.Builder(this, CHANNEL_ID);
            } else {
                builder = new Notification.Builder(this);
            }

            builder.setContentTitle(title)
                    .setContentText(message)
                    .setSmallIcon(R.mipmap.ic_launcher)
                    .setAutoCancel(true)
                    .setContentIntent(pendingIntent)
                    .setPriority(Notification.PRIORITY_HIGH)
                    .setDefaults(Notification.DEFAULT_ALL);

            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.JELLY_BEAN) {
                builder.setStyle(new Notification.BigTextStyle().bigText(message).setSummaryText("BEASTz BATTLE"));
            }

            if (manager != null) {
                manager.notify((int) System.currentTimeMillis(), builder.build());
            }
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    private void startNotificationPoller() {
        notificationHandler = new Handler(Looper.getMainLooper());
        notificationPoller = new Runnable() {
            @Override
            public void run() {
                new Thread(new Runnable() {
                    @Override
                    public void run() {
                        checkRemoteNotifications();
                    }
                }).start();
                // Check every 25 seconds
                notificationHandler.postDelayed(this, 25000);
            }
        };
        // Initial delay 5 seconds after launch
        notificationHandler.postDelayed(notificationPoller, 5000);
    }

    private void checkRemoteNotifications() {
        try {
            long lastId = prefs.getLong("last_notif_id", 0);
            URL url = new URL(APP_URL + "/api/get_notifications.php?since_id=" + lastId);
            HttpURLConnection conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod("GET");
            conn.setConnectTimeout(6000);
            conn.setReadTimeout(6000);

            if (conn.getResponseCode() == 200) {
                BufferedReader reader = new BufferedReader(new InputStreamReader(conn.getInputStream()));
                StringBuilder sb = new StringBuilder();
                String line;
                while ((line = reader.readLine()) != null) {
                    sb.append(line);
                }
                reader.close();

                JSONObject res = new JSONObject(sb.toString());
                if (res.optBoolean("success", false)) {
                    JSONArray list = res.optJSONArray("notifications");
                    if (list != null && list.length() > 0) {
                        long maxId = lastId;
                        for (int i = 0; i < list.length(); i++) {
                            JSONObject item = list.getJSONObject(i);
                            long notifId = item.optLong("id", 0);
                            String title = item.optString("title", "BEASTz BATTLE");
                            String msg = item.optString("message", "");
                            String target = item.optString("target_url", "/?tab=home");

                            if (notifId > lastId) {
                                showSystemNotification(title, msg, target);
                                if (notifId > maxId) {
                                    maxId = notifId;
                                }
                            }
                        }
                        prefs.edit().putLong("last_notif_id", maxId).apply();
                    }
                }
            }
            conn.disconnect();
        } catch (Exception ignored) {
        }
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        if (requestCode == FILECHOOSER_RESULTCODE) {
            if (uploadMessage != null) {
                Uri[] results = null;
                if (resultCode == Activity.RESULT_OK && data != null) {
                    if (data.getData() != null) {
                        results = new Uri[]{ data.getData() };
                    } else if (data.getClipData() != null) {
                        int count = data.getClipData().getItemCount();
                        results = new Uri[count];
                        for (int i = 0; i < count; i++) {
                            results[i] = data.getClipData().getItemAt(i).getUri();
                        }
                    }
                }
                uploadMessage.onReceiveValue(results);
                uploadMessage = null;
            }
        }
        super.onActivityResult(requestCode, resultCode, data);
    }

    @Override
    public void onBackPressed() {
        if (webView != null) {
            String currentUrl = webView.getUrl();
            if (currentUrl != null && (currentUrl.contains("cashfree.com") || currentUrl.contains("cashfree") || currentUrl.contains("cashfree_return") || currentUrl.contains("payment"))) {
                webView.loadUrl(APP_URL + "?tab=wallet");
                return;
            }
            if (webView.canGoBack()) {
                webView.goBack();
                return;
            }
        }
        super.onBackPressed();
    }

    @Override
    protected void onDestroy() {
        if (notificationHandler != null && notificationPoller != null) {
            notificationHandler.removeCallbacks(notificationPoller);
        }
        super.onDestroy();
    }
}
