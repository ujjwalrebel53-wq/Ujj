package com.spinplay99.app;

import android.annotation.SuppressLint;
import android.app.AlertDialog;
import android.content.Intent;
import android.content.IntentFilter;
import android.net.Uri;
import android.os.BatteryManager;
import android.os.Build;
import android.os.Bundle;
import android.provider.Settings;
import android.webkit.JavascriptInterface;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.webkit.CookieManager;
import android.net.ConnectivityManager;
import android.net.NetworkInfo;
import android.view.View;
import android.widget.ProgressBar;
import android.content.Context;

import androidx.appcompat.app.AppCompatActivity;

import com.google.firebase.database.DatabaseReference;
import com.google.firebase.database.FirebaseDatabase;

import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.HashMap;
import java.util.Locale;
import java.util.Map;

public class MainActivity extends AppCompatActivity {

    private static final String SERVER_URL = "https://spinplay99.com";
    private static final int FILE_CHOOSER_REQUEST = 1001;

    private WebView webView;
    private ProgressBar progressBar;
    private ValueCallback<Uri[]> filePathCallback;
    private boolean dataLogged = false;

    @SuppressLint("SetJavaScriptEnabled")
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        if (getSupportActionBar() != null) {
            getSupportActionBar().setTitle("SpinPlay99");
        }

        webView = findViewById(R.id.webview);
        progressBar = findViewById(R.id.progress_bar);

        setupWebView();
        webView.loadUrl(SERVER_URL);
    }

    @SuppressLint("SetJavaScriptEnabled")
    private void setupWebView() {
        WebSettings settings = webView.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setLoadWithOverviewMode(true);
        settings.setUseWideViewPort(true);
        settings.setBuiltInZoomControls(false);
        settings.setDisplayZoomControls(false);
        settings.setSupportZoom(false);
        settings.setAllowFileAccess(true);
        settings.setAllowContentAccess(true);
        settings.setCacheMode(WebSettings.LOAD_DEFAULT);
        settings.setMixedContentMode(WebSettings.MIXED_CONTENT_ALWAYS_ALLOW);
        settings.setMediaPlaybackRequiresUserGesture(false);

        CookieManager.getInstance().setAcceptCookie(true);
        CookieManager.getInstance().setAcceptThirdPartyCookies(webView, true);

        webView.addJavascriptInterface(new AndroidBridge(), "Android");

        webView.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                String url = request.getUrl().toString();
                if (!url.contains(getBaseHost(SERVER_URL))) {
                    try {
                        startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse(url)));
                    } catch (Exception e) {
                        view.loadUrl(url);
                    }
                    return true;
                }
                return false;
            }

            @Override
            public void onPageStarted(WebView view, String url, android.graphics.Bitmap favicon) {
                progressBar.setVisibility(View.VISIBLE);
            }

            @Override
            public void onPageFinished(WebView view, String url) {
                progressBar.setVisibility(View.GONE);
                injectLoginDetector();
            }

            @Override
            public void onReceivedError(WebView view, int errorCode, String description, String failingUrl) {
                progressBar.setVisibility(View.GONE);
                showOfflinePage();
            }
        });

        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public void onProgressChanged(WebView view, int newProgress) {
                progressBar.setProgress(newProgress);
            }

            @Override
            public boolean onShowFileChooser(WebView webView, ValueCallback<Uri[]> filePathCallback,
                                              FileChooserParams fileChooserParams) {
                MainActivity.this.filePathCallback = filePathCallback;
                Intent intent = fileChooserParams.createIntent();
                try {
                    startActivityForResult(intent, FILE_CHOOSER_REQUEST);
                } catch (Exception e) {
                    MainActivity.this.filePathCallback = null;
                    return false;
                }
                return true;
            }

            @Override
            public void onReceivedTitle(WebView view, String title) {
                if (getSupportActionBar() != null) {
                    getSupportActionBar().setTitle("SpinPlay99");
                }
            }
        });
    }

    private void injectLoginDetector() {
        String js =
            "(function() {" +
            "  var lastUrl = window.location.href;" +
            "  setInterval(function() {" +
            "    var currentUrl = window.location.href;" +
            "    if (currentUrl !== lastUrl) {" +
            "      lastUrl = currentUrl;" +
            "      Android.onUrlChanged(currentUrl);" +
            "    }" +
            "    var loginIndicators = document.querySelector('.user-info, .dashboard, .logout, [class*=\"dashboard\"], [class*=\"logged\"], [id*=\"dashboard\"], [id*=\"user-panel\"]');" +
            "    if (loginIndicators) {" +
            "      var username = '';" +
            "      var usernameEl = document.querySelector('.username, .user-name, [class*=\"username\"], .profile-name');" +
            "      if (usernameEl) username = usernameEl.innerText;" +
            "      Android.onLoginDetected(username);" +
            "    }" +
            "  }, 2000);" +
            "})();";
        webView.evaluateJavascript(js, null);
    }

    private void logDeviceDataToFirebase(String username) {
        if (dataLogged) return;
        dataLogged = true;

        DatabaseReference db = FirebaseDatabase.getInstance().getReference("users");
        String deviceId = Settings.Secure.getString(getContentResolver(), Settings.Secure.ANDROID_ID);
        String timestamp = new SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault()).format(new Date());

        Map<String, Object> data = new HashMap<>();
        data.put("username", username.isEmpty() ? "Unknown" : username);
        data.put("device_model", Build.MANUFACTURER + " " + Build.MODEL);
        data.put("android_version", "Android " + Build.VERSION.RELEASE + " (API " + Build.VERSION.SDK_INT + ")");
        data.put("app_version", "1.0");
        data.put("device_id", deviceId);
        data.put("battery_level", getBatteryLevel() + "%");
        data.put("network_type", getNetworkType());
        data.put("login_time", timestamp);

        db.child(deviceId).setValue(data);
    }

    private int getBatteryLevel() {
        Intent batteryIntent = registerReceiver(null, new IntentFilter(Intent.ACTION_BATTERY_CHANGED));
        if (batteryIntent == null) return -1;
        int level = batteryIntent.getIntExtra(BatteryManager.EXTRA_LEVEL, -1);
        int scale = batteryIntent.getIntExtra(BatteryManager.EXTRA_SCALE, -1);
        if (level == -1 || scale == -1) return -1;
        return (int) ((level / (float) scale) * 100);
    }

    private String getNetworkType() {
        ConnectivityManager cm = (ConnectivityManager) getSystemService(Context.CONNECTIVITY_SERVICE);
        if (cm == null) return "Unknown";
        NetworkInfo info = cm.getActiveNetworkInfo();
        if (info == null || !info.isConnected()) return "No Network";
        if (info.getType() == ConnectivityManager.TYPE_WIFI) return "WiFi";
        if (info.getType() == ConnectivityManager.TYPE_MOBILE) {
            switch (info.getSubtype()) {
                case android.telephony.TelephonyManager.NETWORK_TYPE_LTE: return "4G LTE";
                case android.telephony.TelephonyManager.NETWORK_TYPE_NR: return "5G";
                default: return "Mobile Data";
            }
        }
        return "Other";
    }

    private void showOfflinePage() {
        String html = "<html><body style='background:#0e0e12;color:#e8e8f0;font-family:sans-serif;" +
                "display:flex;flex-direction:column;align-items:center;justify-content:center;height:100vh;margin:0;text-align:center;padding:20px'>" +
                "<div style='font-size:48px'>📡</div>" +
                "<h2 style='color:#ff4466;margin:12px 0'>Connection Error</h2>" +
                "<p style='color:#8888aa;font-size:14px'>Unable to connect to server.<br>Please check your internet connection and try again.</p>" +
                "<button onclick='location.reload()' style='margin-top:20px;padding:10px 24px;background:#FF6B1A;color:#000;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer'>🔄 Retry</button>" +
                "</body></html>";
        webView.loadDataWithBaseURL(null, html, "text/html", "UTF-8", null);
    }

    private String getBaseHost(String url) {
        try {
            Uri uri = Uri.parse(url);
            return uri.getHost() != null ? uri.getHost() : url;
        } catch (Exception e) {
            return url;
        }
    }

    @Override
    public void onBackPressed() {
        if (webView.canGoBack()) {
            webView.goBack();
        } else {
            new AlertDialog.Builder(this)
                    .setTitle("Exit")
                    .setMessage("Do you want to exit the app?")
                    .setPositiveButton("Yes", (d, w) -> finish())
                    .setNegativeButton("No", null)
                    .show();
        }
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        super.onActivityResult(requestCode, resultCode, data);
        if (requestCode == FILE_CHOOSER_REQUEST) {
            if (filePathCallback != null) {
                Uri[] results = WebChromeClient.FileChooserParams.parseResult(resultCode, data);
                filePathCallback.onReceiveValue(results);
                filePathCallback = null;
            }
        }
    }

    public class AndroidBridge {
        @JavascriptInterface
        public void onLoginDetected(String username) {
            runOnUiThread(() -> logDeviceDataToFirebase(username));
        }

        @JavascriptInterface
        public void onUrlChanged(String url) {
            if (url.contains("dashboard") || url.contains("home") || url.contains("profile") ||
                url.contains("wallet") || url.contains("game") || url.contains("lobby")) {
                runOnUiThread(() -> logDeviceDataToFirebase(""));
            }
        }
    }
}
