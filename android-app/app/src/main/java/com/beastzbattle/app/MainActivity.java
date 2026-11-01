package com.beastzbattle.app;

import android.annotation.SuppressLint;
import android.app.Activity;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.graphics.Bitmap;
import android.graphics.Color;
import android.graphics.Typeface;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.view.Gravity;
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
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.TextView;
import android.widget.Toast;

import com.onesignal.OneSignal;
import com.onesignal.OSNotificationOpenedResult;

import org.json.JSONObject;

public class MainActivity extends Activity {

    private WebView webView;
    private FrameLayout splashLayout;
    private ValueCallback<Uri[]> uploadMessage;
    private final static int FILECHOOSER_RESULTCODE = 101;
    private final static int PERMISSION_REQUEST_NOTIFICATIONS = 102;
    private static final String APP_URL = "https://beastz-battle.vercel.app";
    private static final String CHANNEL_ID = "beastz_push_alerts";
    private static final String ONESIGNAL_APP_ID = "561bd59e-8355-47c8-88cc-0766688a105a";
    private boolean isPageLoaded = false;

    @SuppressLint("SetJavaScriptEnabled")
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        // 1. OneSignal SDK Initialization (Full background & closed-app push capability)
        try {
            OneSignal.setLogLevel(OneSignal.LOG_LEVEL.VERBOSE, OneSignal.LOG_LEVEL.NONE);
            OneSignal.initWithContext(this);
            OneSignal.setAppId(ONESIGNAL_APP_ID);

            // Handle notification taps: Open target URL directly in webview
            OneSignal.setNotificationOpenedHandler(new OneSignal.OSNotificationOpenedHandler() {
                @Override
                public void notificationOpened(OSNotificationOpenedResult result) {
                    try {
                        JSONObject data = result.getNotification().getAdditionalData();
                        if (data != null && data.has("target_url")) {
                            String targetUrl = data.optString("target_url");
                            if (targetUrl != null && !targetUrl.isEmpty()) {
                                runOnUiThread(new Runnable() {
                                    @Override
                                    public void run() {
                                        if (webView != null) {
                                            if (targetUrl.startsWith("http")) {
                                                webView.loadUrl(targetUrl);
                                            } else {
                                                webView.loadUrl(APP_URL + (targetUrl.startsWith("/") ? "" : "/") + targetUrl);
                                            }
                                        }
                                    }
                                });
                            }
                        }
                    } catch (Exception e) {
                        e.printStackTrace();
                    }
                }
            });

            // Display in system status bar even when app is open
            OneSignal.setNotificationWillShowInForegroundHandler(notificationReceivedEvent -> {
                notificationReceivedEvent.complete(notificationReceivedEvent.getNotification());
            });

            // Prompt permission on launch
            OneSignal.promptForPushNotifications();
        } catch (Exception e) {
            e.printStackTrace();
        }

        // 2. Hardware acceleration & status bar styling
        Window window = getWindow();
        window.addFlags(WindowManager.LayoutParams.FLAG_DRAWS_SYSTEM_BAR_BACKGROUNDS);
        window.clearFlags(WindowManager.LayoutParams.FLAG_TRANSLUCENT_STATUS);
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            window.setStatusBarColor(0xFF080A0F); // Deep Cyber Black
            window.setNavigationBarColor(0xFF080A0F);
        }

        // 3. Root layout
        FrameLayout rootLayout = new FrameLayout(this);
        rootLayout.setBackgroundColor(0xFF080A0F);

        // 4. Create WebView with Hardware Acceleration
        webView = new WebView(this);
        webView.setBackgroundColor(0xFF080A0F);
        webView.setLayerType(View.LAYER_TYPE_HARDWARE, null);
        webView.setOverScrollMode(View.OVER_SCROLL_NEVER);
        webView.setVerticalScrollBarEnabled(false);
        webView.setHorizontalScrollBarEnabled(false);

        rootLayout.addView(webView, new FrameLayout.LayoutParams(
                FrameLayout.LayoutParams.MATCH_PARENT,
                FrameLayout.LayoutParams.MATCH_PARENT
        ));

        // 5. Build Fast Native Branded Splash Screen (Zero Black Screen Delay!)
        splashLayout = new FrameLayout(this);
        splashLayout.setBackgroundColor(0xFF080A0F);

        LinearLayout splashContent = new LinearLayout(this);
        splashContent.setOrientation(LinearLayout.VERTICAL);
        splashContent.setGravity(Gravity.CENTER);

        ImageView logoView = new ImageView(this);
        logoView.setImageResource(R.mipmap.ic_launcher);
        LinearLayout.LayoutParams logoParams = new LinearLayout.LayoutParams(180, 180);
        logoParams.bottomMargin = 24;
        splashContent.addView(logoView, logoParams);

        TextView titleView = new TextView(this);
        titleView.setText("BEASTz BATTLE");
        titleView.setTextColor(0xFFFACC15); // Neon Esports Amber/Gold
        titleView.setTextSize(22);
        titleView.setTypeface(Typeface.DEFAULT_BOLD);
        titleView.setGravity(Gravity.CENTER);
        splashContent.addView(titleView);

        TextView subtitleView = new TextView(this);
        subtitleView.setText("Esports Tournaments & Scrims");
        subtitleView.setTextColor(0xFF94A3B8);
        subtitleView.setTextSize(12);
        subtitleView.setGravity(Gravity.CENTER);
        LinearLayout.LayoutParams subParams = new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.WRAP_CONTENT,
                LinearLayout.LayoutParams.WRAP_CONTENT
        );
        subParams.topMargin = 6;
        subParams.bottomMargin = 30;
        splashContent.addView(subtitleView, subParams);

        ProgressBar spinner = new ProgressBar(this);
        spinner.setIndeterminate(true);
        LinearLayout.LayoutParams spinParams = new LinearLayout.LayoutParams(70, 70);
        splashContent.addView(spinner, spinParams);

        FrameLayout.LayoutParams centerParams = new FrameLayout.LayoutParams(
                FrameLayout.LayoutParams.WRAP_CONTENT,
                FrameLayout.LayoutParams.WRAP_CONTENT,
                Gravity.CENTER
        );
        splashLayout.addView(splashContent, centerParams);
        rootLayout.addView(splashLayout, new FrameLayout.LayoutParams(
                FrameLayout.LayoutParams.MATCH_PARENT,
                FrameLayout.LayoutParams.MATCH_PARENT
        ));

        setContentView(rootLayout);

        // 6. Create High-Priority Notification Channel
        createNotificationChannel();

        // 7. Request Notification Permission for Android 13+ (API 33+)
        if (Build.VERSION.SDK_INT >= 33) {
            if (checkSelfPermission("android.permission.POST_NOTIFICATIONS") != PackageManager.PERMISSION_GRANTED) {
                requestPermissions(new String[]{"android.permission.POST_NOTIFICATIONS"}, PERMISSION_REQUEST_NOTIFICATIONS);
            }
        }

        // 8. Configure WebView Settings for Max Speed
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
        settings.setUserAgentString(settings.getUserAgentString() + " BEASTzNativeApp/1.3.0 Android");

        // Native JS Bridge
        webView.addJavascriptInterface(new Object() {
            @JavascriptInterface
            public void postNativeNotification(String title, String message, String actionUrl) {
                // Bridge call if web calls native directly
            }
        }, "BEASTzNative");

        // 9. WebView Client with Instant Splash Dismissal & Seamless Navigation
        webView.setWebViewClient(new WebViewClient() {
            @Override
            public void onPageStarted(WebView view, String url, Bitmap favicon) {
                super.onPageStarted(view, url, favicon);
            }

            @Override
            public void onPageFinished(WebView view, String url) {
                super.onPageFinished(view, url);
                hideSplashScreen();
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

                // Internal app routing
                if (url.contains("beastz-battle.vercel.app") || url.startsWith("/")) {
                    return false;
                }

                // External browser links
                try {
                    Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(url));
                    startActivity(intent);
                    return true;
                } catch (Exception ignored) {
                    return false;
                }
            }

            @Override
            public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
                super.onReceivedError(view, request, error);
                hideSplashScreen();

                if (request != null && request.isForMainFrame()) {
                    String curUrl = view.getUrl();
                    if (curUrl != null && (curUrl.contains("cashfree") || curUrl.contains("return"))) {
                        view.loadUrl(APP_URL + "?tab=wallet");
                        return;
                    }
                    String errorHtml = "<html><body style='background-color:#080a0f;color:#ffffff;font-family:sans-serif;text-align:center;padding:60px 20px;'>"
                            + "<div style='width:64px;height:64px;margin:0 auto 16px;background:rgba(250,204,21,0.15);border:1px solid #facc15;border-radius:20px;display:flex;align-items:center;justify-content:center;'><span style='font-size:30px;'>&#9889;</span></div>"
                            + "<h2 style='color:#facc15;margin:0 0 8px;font-size:20px;font-weight:900;'>Connection Error</h2>"
                            + "<p style='color:#94a3b8;font-size:13px;line-height:1.5;margin:0 0 24px;'>Please check your internet connection.</p>"
                            + "<button onclick='location.reload()' style='background:#facc15;color:#080a0f;border:none;padding:12px 30px;border-radius:12px;font-weight:900;font-size:13px;cursor:pointer;'>RELOAD</button>"
                            + "</body></html>";
                    view.loadDataWithBaseURL(null, errorHtml, "text/html", "UTF-8", null);
                }
            }
        });

        // 10. File Chooser for Screenshot & Avatar Uploads
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

        // 11. Load App
        webView.loadUrl(APP_URL);
    }

    private void hideSplashScreen() {
        if (!isPageLoaded && splashLayout != null) {
            isPageLoaded = true;
            splashLayout.animate()
                    .alpha(0f)
                    .setDuration(280)
                    .withEndAction(new Runnable() {
                        @Override
                        public void run() {
                            splashLayout.setVisibility(View.GONE);
                        }
                    });
        }
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
}
