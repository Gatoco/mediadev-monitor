package cl.semaops.control;

import android.app.Activity;
import android.app.AlertDialog;
import android.content.SharedPreferences;
import android.os.Bundle;
import android.text.InputType;
import android.view.KeyEvent;
import android.view.View;
import android.view.WindowManager;
import android.webkit.JavascriptInterface;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.EditText;

/**
 * Full-screen shell for the Mission Control demo panel (/demo?k=...).
 * The default URL is baked in at build time (res/values/config.xml); long-press BACK to change it.
 */
public class MainActivity extends Activity {
    private WebView web;
    private SharedPreferences prefs;

    @Override
    protected void onCreate(Bundle state) {
        super.onCreate(state);
        getWindow().addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON);
        getWindow().setStatusBarColor(0xFF080A0F);
        getWindow().setNavigationBarColor(0xFF080A0F);
        prefs = getSharedPreferences("control", MODE_PRIVATE);

        web = new WebView(this);
        web.setBackgroundColor(0xFF080A0F);
        web.getSettings().setJavaScriptEnabled(true);
        web.getSettings().setDomStorageEnabled(true);
        web.addJavascriptInterface(new Bridge(), "Android");
        web.setWebViewClient(new WebViewClient() {
            @Override
            public void onReceivedError(WebView view, WebResourceRequest req, WebResourceError err) {
                if (req.isForMainFrame()) showOffline();
            }
        });
        setContentView(web);
        load();
    }

    private String url() {
        return prefs.getString("url", getString(R.string.default_url));
    }

    private void load() {
        web.loadUrl(url());
    }

    private void showOffline() {
        String html = "<html><body style='background:#080a0f;color:#e6eaf0;font-family:sans-serif;padding:32px 20px'>"
            + "<h2 style='font-size:20px'>No se pudo conectar con el monitor</h2>"
            + "<p style='color:#a3adbd;margin:12px 0 24px'>Revisa que el PC tenga el comando de la demo corriendo y que el celular esté en Tailscale.</p>"
            + "<p style='color:#6b7585;font-size:13px;word-break:break-all'>" + android.text.TextUtils.htmlEncode(url()) + "</p>"
            + "<button onclick='Android.retry()' style='width:100%;padding:16px;margin-top:24px;border-radius:14px;border:0;background:#2fb57c;color:#fff;font-size:16px'>Reintentar</button>"
            + "<button onclick='Android.edit()' style='width:100%;padding:16px;margin-top:12px;border-radius:14px;border:1px solid #333;background:#151a23;color:#e6eaf0;font-size:16px'>Cambiar dirección</button>"
            + "</body></html>";
        web.loadDataWithBaseURL(null, html, "text/html", "utf-8", null);
    }

    private void editUrl() {
        final EditText input = new EditText(this);
        input.setInputType(InputType.TYPE_TEXT_VARIATION_URI);
        input.setText(url());
        new AlertDialog.Builder(this)
            .setTitle("Dirección del control")
            .setView(input)
            .setPositiveButton("Guardar", (d, w) -> {
                prefs.edit().putString("url", input.getText().toString().trim()).apply();
                load();
            })
            .setNeutralButton("Restaurar", (d, w) -> {
                prefs.edit().remove("url").apply();
                load();
            })
            .setNegativeButton("Cancelar", null)
            .show();
    }

    private class Bridge {
        @JavascriptInterface
        public void retry() { runOnUiThread(MainActivity.this::load); }

        @JavascriptInterface
        public void edit() { runOnUiThread(MainActivity.this::editUrl); }
    }

    @Override
    public void onWindowFocusChanged(boolean focus) {
        super.onWindowFocusChanged(focus);
        if (focus) {
            getWindow().getDecorView().setSystemUiVisibility(View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY
                | View.SYSTEM_UI_FLAG_HIDE_NAVIGATION | View.SYSTEM_UI_FLAG_LAYOUT_HIDE_NAVIGATION);
        }
    }

    @Override
    public boolean onKeyDown(int code, KeyEvent e) {
        if (code == KeyEvent.KEYCODE_BACK) {
            e.startTracking();
            return true;
        }
        return super.onKeyDown(code, e);
    }

    @Override
    public boolean onKeyLongPress(int code, KeyEvent e) {
        if (code == KeyEvent.KEYCODE_BACK) {
            editUrl();
            return true;
        }
        return super.onKeyLongPress(code, e);
    }

    @Override
    public boolean onKeyUp(int code, KeyEvent e) {
        if (code == KeyEvent.KEYCODE_BACK && e.isTracking() && !e.isCanceled()) {
            if (web.canGoBack()) web.goBack(); else finish();
            return true;
        }
        return super.onKeyUp(code, e);
    }
}
