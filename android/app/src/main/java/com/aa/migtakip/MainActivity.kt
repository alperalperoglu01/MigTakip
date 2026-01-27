package com.aa.migtakip

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.net.Uri
import android.os.Bundle
import android.provider.Settings
import android.view.View
import android.webkit.CookieManager
import android.webkit.WebChromeClient
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.Toast
import androidx.activity.ComponentActivity
import androidx.core.content.ContextCompat
import com.google.android.material.button.MaterialButton
import com.google.android.material.appbar.MaterialToolbar
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject
import android.content.ComponentName

class MainActivity : ComponentActivity() {

    private lateinit var webView: WebView
    private var setupCard: View? = null
    private val http = OkHttpClient()

    private val receiver = object : BroadcastReceiver() {
        override fun onReceive(context: Context, intent: Intent) {
            val date = intent.getStringExtra("date") ?: return
            val packages = intent.getIntExtra("packages", -1)
            if (packages < 0) return
            val overtime = intent.getDoubleExtra("overtime_hours", 0.0)
            val ev = AutoPackageEvent(date, packages, overtime)
            AutoPackageStore.saveDetected(this@MainActivity, ev)
            trySendToServer(ev)
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        val toolbar = findViewById<MaterialToolbar>(R.id.toolbar)
        toolbar.setNavigationOnClickListener {
            if (webView.canGoBack()) webView.goBack() else finish()
        }
        toolbar.setOnMenuItemClickListener { item ->
            when (item.itemId) {
                R.id.action_refresh -> { webView.reload(); true }
                R.id.action_open_migpack -> {
                    launchMigPack()
                    true
                }
                R.id.action_accessibility -> {
                    openAccessibilitySettings()
                    true
                }
                else -> false
            }
        }

        setupCard = findViewById(R.id.setupCard)
        findViewById<MaterialButton>(R.id.btnOpenAccessibility).setOnClickListener {
            openAccessibilitySettings()
        }

        webView = findViewById(R.id.webview)
        setupWebView()
        webView.loadUrl(Config.BASE_URL)

        ContextCompat.registerReceiver(
            this,
            receiver,
            IntentFilter(MigPackAccessibilityService.ACTION_PACKAGES_DETECTED),
            ContextCompat.RECEIVER_NOT_EXPORTED
        )

        updateSetupCardVisibility()
    }

    override fun onResume() {
        super.onResume()
        updateSetupCardVisibility()
    }

    override fun onDestroy() {
        super.onDestroy()
        unregisterReceiver(receiver)
        webView.destroy()
    }

    private fun setupWebView() {
        val s = webView.settings
        s.javaScriptEnabled = true
        s.domStorageEnabled = true
        s.cacheMode = WebSettings.LOAD_DEFAULT
        s.useWideViewPort = true
        s.loadWithOverviewMode = true
        s.userAgentString = s.userAgentString + " MigTakipAndroid/1.0"

        CookieManager.getInstance().setAcceptCookie(true)

        webView.webViewClient = object : WebViewClient() {
            override fun onPageFinished(view: WebView?, url: String?) {
                super.onPageFinished(view, url)
                // Sayfa açılınca elde bekleyen event varsa sunucuya yollamayı dene
                AutoPackageStore.loadDetected(this@MainActivity)?.let { json ->
                    try {
                        val o = JSONObject(json)
                        val ev = AutoPackageEvent(
                            o.getString("date"),
                            o.getInt("packages"),
                            o.optDouble("overtime_hours", 0.0)
                        )
                        trySendToServer(ev)
                    } catch (_: Exception) {}
                }
            }
        }
        webView.webChromeClient = WebChromeClient()
    }

    private fun trySendToServer(ev: AutoPackageEvent) {
        if (AutoPackageStore.wasSent(this, ev)) return

        // WebView cookie'lerini alıp aynı session ile API'ye POST atıyoruz.
        val cookies = CookieManager.getInstance().getCookie(Config.BASE_URL) ?: ""
        if (cookies.isBlank()) return

        val endpoint = if (Config.BASE_URL.endsWith("/")) Config.BASE_URL + Config.AUTO_API_PATH
        else Config.BASE_URL + "/" + Config.AUTO_API_PATH

        val bodyJson = ev.toJson()
        val req = Request.Builder()
            .url(endpoint)
            .addHeader("Content-Type", "application/json")
            .addHeader("Cookie", cookies)
            .post(bodyJson.toRequestBody("application/json; charset=utf-8".toMediaType()))
            .build()

        Thread {
            try {
                http.newCall(req).execute().use { resp ->
                    if (resp.isSuccessful) {
                        AutoPackageStore.markSent(this, ev)
                        runOnUiThread {
                            Toast.makeText(this, "${ev.date} – ${ev.packages} paket kaydedildi ✅", Toast.LENGTH_SHORT).show()
                            // UI güncellemek için sayfayı yenile
                            webView.reload()
                        }
                    }
                }
            } catch (_: Exception) {}
        }.start()
    }

    private fun launchMigPack() {
    try {
        // 1) Önce normal launch intent dene
        packageManager.getLaunchIntentForPackage(Config.MIGPACK_PACKAGE)?.let { intent ->
            startActivity(intent)
            return
        }

        // 2) Olmazsa MainActivity ile direkt aç (sende var: app.migpack.MainActivity)
        val intent = Intent().apply {
            component = ComponentName(
                Config.MIGPACK_PACKAGE,
                "${Config.MIGPACK_PACKAGE}.MainActivity"
            )
            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        }
        startActivity(intent)

    } catch (e: Exception) {
        Toast.makeText(this, "MigPack açılamadı: ${Config.MIGPACK_PACKAGE}", Toast.LENGTH_LONG).show()
    }
}


    private fun openAccessibilitySettings() {
        try {
            startActivity(Intent(Settings.ACTION_ACCESSIBILITY_SETTINGS))
            Toast.makeText(this, "Ayarlar açıldı: Erişilebilirlik → MigTakip → Aç", Toast.LENGTH_LONG).show()
        } catch (_: Exception) {
            Toast.makeText(this, "Ayarlar açılamadı.", Toast.LENGTH_LONG).show()
        }
    }

    private fun updateSetupCardVisibility() {
        val enabled = isAccessibilityServiceEnabled()
        setupCard?.visibility = if (enabled) View.GONE else View.VISIBLE
    }

    private fun isAccessibilityServiceEnabled(): Boolean {
        // ENABLED_ACCESSIBILITY_SERVICES içinde servisimizin ComponentName'i var mı kontrol ediyoruz
        return try {
            val enabledServices = Settings.Secure.getString(contentResolver, Settings.Secure.ENABLED_ACCESSIBILITY_SERVICES) ?: ""
            val comp = "${packageName}/${MigPackAccessibilityService::class.java.name}"
            enabledServices.split(':').any { it.equals(comp, ignoreCase = true) }
        } catch (_: Exception) {
            false
        }
    }
}
