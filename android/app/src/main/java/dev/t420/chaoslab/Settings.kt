package dev.t420.chaoslab

import android.content.Context

/** Conexión al panel, guardada en el teléfono. */
class Settings(context: Context) {
    private val prefs = context.getSharedPreferences("chaoslab", Context.MODE_PRIVATE)

    var serverUrl: String
        get() = prefs.getString("server_url", DEFAULT_URL).orEmpty()
        set(v) = prefs.edit().putString("server_url", v.trim()).apply()

    var appToken: String
        get() = prefs.getString("app_token", "").orEmpty()
        set(v) = prefs.edit().putString("app_token", v.trim()).apply()

    var cfClientId: String
        get() = prefs.getString("cf_client_id", "").orEmpty()
        set(v) = prefs.edit().putString("cf_client_id", v.trim()).apply()

    var cfClientSecret: String
        get() = prefs.getString("cf_client_secret", "").orEmpty()
        set(v) = prefs.edit().putString("cf_client_secret", v.trim()).apply()

    val configured: Boolean get() = serverUrl.isNotEmpty() && appToken.isNotEmpty()

    companion object {
        /** Servidor local visto desde el emulador de Android. */
        const val DEFAULT_URL = "http://10.0.2.2:8000"
    }
}
