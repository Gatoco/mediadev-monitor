package dev.t420.chaoslab

import org.json.JSONObject
import java.io.IOException
import java.net.HttpURLConnection
import java.net.URL

/** Contenedor tal como lo devuelve GET /lab/app/containers. */
data class Container(
    val service: String,
    val container: String,
    val containerState: String,
    val monitorState: String,
    val sites: Int,
) {
    val running: Boolean get() = containerState == "running"
}

data class ContainersResponse(val agentOk: Boolean, val containers: List<Container>)

class ApiException(message: String) : IOException(message)

/**
 * Cliente de la API de la app en el panel (Laravel):
 *   GET  /lab/app/containers
 *   POST /lab/app/containers/{service}/{start|stop}
 * Autentica con Bearer CHAOS_APP_TOKEN y, si el panel está detrás de
 * Cloudflare Access, con un Service Token (CF-Access-Client-Id/Secret).
 */
class ChaosApi(private val settings: Settings) {

    fun containers(): ContainersResponse {
        val json = JSONObject(request("GET", "/lab/app/containers"))
        val items = json.getJSONArray("containers")
        val list = (0 until items.length()).map { i ->
            val c = items.getJSONObject(i)
            Container(
                service = c.getString("service"),
                container = c.optString("container", c.getString("service")),
                containerState = c.optString("container_state", "unknown"),
                monitorState = c.optString("monitor_state", "unmonitored"),
                sites = c.optInt("sites", 0),
            )
        }
        return ContainersResponse(json.optBoolean("agent_ok", false), list)
    }

    fun setRunning(service: String, running: Boolean) {
        request("POST", "/lab/app/containers/$service/${if (running) "start" else "stop"}")
    }

    private fun request(method: String, path: String): String {
        val base = settings.serverUrl.trimEnd('/')
        if (base.isEmpty()) throw ApiException("Configura la URL del servidor")

        val conn = URL(base + path).openConnection() as HttpURLConnection
        try {
            conn.requestMethod = method
            conn.connectTimeout = 5_000
            // Apagar/encender espera al chaos-agent (hasta ~45 s en el servidor).
            conn.readTimeout = if (method == "POST") 60_000 else 10_000
            conn.setRequestProperty("Accept", "application/json")
            conn.setRequestProperty("Authorization", "Bearer ${settings.appToken}")
            if (settings.cfClientId.isNotEmpty()) {
                conn.setRequestProperty("CF-Access-Client-Id", settings.cfClientId)
                conn.setRequestProperty("CF-Access-Client-Secret", settings.cfClientSecret)
            }
            if (method == "POST") {
                conn.doOutput = true
                conn.setFixedLengthStreamingMode(0)
                conn.outputStream.close()
            }

            val code = conn.responseCode
            val body = (if (code in 200..299) conn.inputStream else conn.errorStream)
                ?.bufferedReader()?.use { it.readText() }.orEmpty()

            if (code !in 200..299) {
                val detail = runCatching { JSONObject(body).optString("error") }.getOrNull()
                throw ApiException(
                    when {
                        !detail.isNullOrEmpty() -> detail
                        code == 401 -> "Token de la app inválido"
                        code == 403 -> "Cloudflare Access rechazó la conexión"
                        code == 404 -> "El Chaos Lab no está habilitado en el servidor"
                        else -> "El servidor respondió HTTP $code"
                    }
                )
            }
            return body
        } finally {
            conn.disconnect()
        }
    }
}
