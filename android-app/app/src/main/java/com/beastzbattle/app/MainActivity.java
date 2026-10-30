package com.beastzbattle.app;

import android.annotation.SuppressLint;
import android.app.Activity;
import android.content.Intent;
import android.graphics.Bitmap;
import android.net.Uri;
import android.os.Bundle;
import android.view.View;
import android.view.Window;
import android.view.WindowManager;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.FrameLayout;
import android.widget.Toast;

public class MainActivity extends Activity {

    private WebView webView;
    private ValueCallback<Uri[]> uploadMessage;
    private final static int FILECHOOSER_RESULTCODE = 101;
    private static final String APP_URL = "https://beastz-battle.vercel.app";

    @SuppressLint("SetJavaScriptEnabled")
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        // Hardware acceleration & status bar styling
        Window window = getWindow();
        window.addFlags(WindowManager.LayoutParams.FLAG_DRAWS_SYSTEM_BAR_BACKGROUNDS);
        window.clearFlags(WindowManager.LayoutParams.FLAG_TRANSLUCENT_STATUS);

        // Root layout - clean, full screen without top progress line
        FrameLayout rootLayout = new FrameLayout(this);
        rootLayout.setBackgroundColor(0xFF070A12); // Deep cyber dark

        webView = new WebView(this);
        webView.setBackgroundColor(0xFF070A12);
        webView.setLayerType(View.LAYER_TYPE_HARDWARE, null);
        webView.setOverScrollMode(View.OVER_SCROLL_NEVER);
        webView.setVerticalScrollBarEnabled(false);
        webView.setHorizontalScrollBarEnabled(false);

        rootLayout.addView(webView, new FrameLayout.LayoutParams(
                FrameLayout.LayoutParams.MATCH_PARENT, 
                FrameLayout.LayoutParams.MATCH_PARENT
        ));

        setContentView(rootLayout);

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
        settings.setUserAgentString(settings.getUserAgentString() + " BEASTzNativeApp/1.0.0 Android");

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

                // 1. Support UPI and external app intents (PhonePe, GooglePay, Paytm, BHIM, WhatsApp)
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

                // 2. Keep Cashfree payment gateway & main domain inside WebView seamlessly
                if (url.contains("beastz-battle.vercel.app") || 
                    url.contains("cashfree.com") || 
                    url.contains("cashfree") ||
                    url.contains("merchants.cashfree.com")) {
                    return false;
                }

                // 3. Fallback to external browser for foreign links
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
                
                // IGNORE aborted requests (net::ERR_ABORTED / code -3 / user cancel / back press)
                if (error != null) {
                    int errCode = error.getErrorCode();
                    if (errCode == -3 || errCode == WebViewClient.ERROR_UNKNOWN) {
                        return;
                    }
                }

                if (request != null && request.getUrl() != null) {
                    String reqUrl = request.getUrl().toString();
                    // Never trigger disconnect screen for Cashfree or analytics
                    if (reqUrl.contains("cashfree.com") || reqUrl.contains("cashfree") || reqUrl.contains("analytics")) {
                        // User cancelled payment or back pressed -> gracefully return to wallet
                        view.post(new Runnable() {
                            @Override
                            public void run() {
                                view.loadUrl(APP_URL + "?tab=wallet");
                            }
                        });
                        return;
                    }
                }

                // Only show offline screen if the MAIN app domain itself fails to connect
                if (request != null && request.isForMainFrame()) {
                    String curUrl = view.getUrl();
                    if (curUrl != null && (curUrl.contains("cashfree") || curUrl.contains("return"))) {
                        view.loadUrl(APP_URL + "?tab=wallet");
                        return;
                    }

                    String errorHtml = "<html><body style='background-color:#070a12;color:#ffffff;font-family:sans-serif;text-align:center;padding:60px 20px;'>"
                            + "<div style='width:60px;height:60px;margin:0 auto 16px;background:rgba(250,204,21,0.15);border:1px solid #facc15;border-radius:18px;display:flex;align-items:center;justify-content:center;'><span style='font-size:28px;'>&#9889;</span></div>"
                            + "<h2 style='color:#facc15;margin:0 0 8px;font-size:20px;font-weight:800;'>Network Disconnected</h2>"
                            + "<p style='color:#94a3b8;font-size:13px;line-height:1.5;margin:0 0 24px;'>Please check your mobile data or Wi-Fi connection.</p>"
                            + "<button onclick='location.reload()' style='background:#facc15;color:#070a12;border:none;padding:12px 30px;border-radius:12px;font-weight:900;font-size:13px;cursor:pointer;letter-spacing:0.5px;'>RETRY CONNECTION</button>"
                            + "</body></html>";
                    view.loadDataWithBaseURL(null, errorHtml, "text/html", "UTF-8", null);
                }
            }
        });

        webView.setWebChromeClient(new WebChromeClient() {
            // Real Profile Picture file / camera chooser
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
            // If user is inside Cashfree gateway and presses back, gracefully navigate back to wallet
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
