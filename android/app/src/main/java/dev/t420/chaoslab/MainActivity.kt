package dev.t420.chaoslab

import android.app.Activity
import android.app.AlertDialog
import android.graphics.Color
import android.graphics.Typeface
import android.graphics.drawable.GradientDrawable
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.text.InputType
import android.util.TypedValue
import android.view.Gravity
import android.view.View
import android.widget.Button
import android.widget.EditText
import android.widget.LinearLayout
import android.widget.ProgressBar
import android.widget.ScrollView
import android.widget.TextView
import java.util.concurrent.Executors

/**
 * Pantalla única: lista de contenedores del stack de clones con su estado y
 * un botón para apagar/encender cada uno.
 */
class MainActivity : Activity() {

    private object C {
        val BG = Color.parseColor("#080a0f")
        val SURFACE = Color.parseColor("#0f131a")
        val SURFACE2 = Color.parseColor("#151a23")
        val LAMP_OFF = Color.parseColor("#1b212b")
        val TEXT = Color.parseColor("#e6eaf0")
        val TEXT2 = Color.parseColor("#a3adbd")
        val MUTED = Color.parseColor("#7a8494")
        val OK = Color.parseColor("#2fb57c")
        val WARN = Color.parseColor("#e0a23a")
        val NONWP = Color.parseColor("#7c8798")
        val DOWN = Color.parseColor("#e5484d")
    }

    private lateinit var settings: Settings
    private lateinit var api: ChaosApi
    private lateinit var list: LinearLayout
    private lateinit var banner: TextView
    private lateinit var progress: ProgressBar

    private val io = Executors.newSingleThreadExecutor()
    private val main = Handler(Looper.getMainLooper())
    private val busy = mutableSetOf<String>()
    private var containers: List<Container> = emptyList()

    private val poll = object : Runnable {
        override fun run() {
            load()
            main.postDelayed(this, POLL_MS)
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        settings = Settings(this)
        api = ChaosApi(settings)
        setContentView(buildLayout())
        if (!settings.configured) showSettings()
    }

    override fun onResume() {
        super.onResume()
        main.post(poll)
    }

    override fun onPause() {
        super.onPause()
        main.removeCallbacks(poll)
    }

    override fun onDestroy() {
        io.shutdownNow()
        super.onDestroy()
    }

    // ---------------------------------------------------------------- datos

    private fun load() {
        if (!settings.configured) {
            showBanner("Configura el servidor y el token (⚙)", C.WARN)
            return
        }
        progress.visibility = View.VISIBLE
        io.execute {
            val result = runCatching { api.containers() }
            main.post {
                progress.visibility = View.INVISIBLE
                result.onSuccess { r ->
                    containers = r.containers
                    if (r.agentOk) hideBanner()
                    else showBanner("El servidor no logra hablar con el chaos-agent", C.WARN)
                    render()
                }.onFailure { showBanner(it.message ?: "Sin conexión con el servidor", C.DOWN) }
            }
        }
    }

    private fun toggle(c: Container) {
        val turnOn = !c.running
        val run = {
            busy += c.service
            render()
            io.execute {
                val result = runCatching { api.setRunning(c.service, turnOn) }
                main.post {
                    busy -= c.service
                    result.onFailure { showBanner(it.message ?: "No se pudo cambiar el contenedor", C.DOWN) }
                    load()
                }
            }
        }

        if (turnOn) {
            run()
        } else {
            val sites = if (c.sites == 1) "1 sitio monitoreado" else "${c.sites} sitios monitoreados"
            AlertDialog.Builder(this, android.R.style.Theme_Material_Dialog_Alert)
                .setTitle("¿Apagar ${c.service}?")
                .setMessage("Se detendrá ${c.container} ($sites). El monitor debería marcarlo caído tras 3 chequeos fallidos.")
                .setPositiveButton("Apagar") { _, _ -> run() }
                .setNegativeButton("Cancelar", null)
                .show()
        }
    }

    // ---------------------------------------------------------------- vista

    private fun buildLayout(): View {
        val root = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setBackgroundColor(C.BG)
            fitsSystemWindows = true
        }

        val header = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity = Gravity.CENTER_VERTICAL
            setPadding(dp(16), dp(18), dp(8), dp(10))
        }
        val titles = LinearLayout(this).apply { orientation = LinearLayout.VERTICAL }
        titles.addView(text("SEMAOPS · CHAOS LAB", 11f, C.MUTED, mono = true))
        titles.addView(text("Contenedores", 24f, C.TEXT, bold = true))
        header.addView(titles, LinearLayout.LayoutParams(0, -2, 1f))

        progress = ProgressBar(this).apply {
            isIndeterminate = true
            visibility = View.INVISIBLE
            indeterminateTintList = android.content.res.ColorStateList.valueOf(C.MUTED)
        }
        header.addView(progress, LinearLayout.LayoutParams(dp(20), dp(20)))

        header.addView(text("⚙", 22f, C.TEXT2).apply {
            setPadding(dp(14), dp(6), dp(10), dp(6))
            setOnClickListener { showSettings() }
            contentDescription = "Ajustes"
        })
        root.addView(header)

        banner = text("", 13f, C.TEXT).apply {
            setPadding(dp(12), dp(10), dp(12), dp(10))
            visibility = View.GONE
        }
        root.addView(banner, LinearLayout.LayoutParams(-1, -2).apply {
            setMargins(dp(16), 0, dp(16), dp(8))
        })

        list = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(dp(16), dp(4), dp(16), dp(24))
        }
        root.addView(ScrollView(this).apply { addView(list) }, LinearLayout.LayoutParams(-1, 0, 1f))
        return root
    }

    private fun render() {
        list.removeAllViews()
        if (containers.isEmpty()) {
            list.addView(text("Sin contenedores todavía.", 14f, C.MUTED).apply {
                setPadding(0, dp(24), 0, 0)
                gravity = Gravity.CENTER
            }, LinearLayout.LayoutParams(-1, -2))
            return
        }
        containers.forEach { list.addView(card(it)) }
    }

    private fun card(c: Container): View {
        val working = c.service in busy
        val (stateLabel, stateColor) = containerState(c.containerState)

        val card = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity = Gravity.CENTER_VERTICAL
            setPadding(dp(14), dp(14), dp(14), dp(14))
            background = rounded(C.SURFACE, C.SURFACE2)
            layoutParams = LinearLayout.LayoutParams(-1, -2).apply { bottomMargin = dp(10) }
        }

        // Lámpara de estado del contenedor.
        card.addView(View(this).apply {
            background = GradientDrawable().apply {
                shape = GradientDrawable.OVAL
                setColor(if (c.running) C.OK else if (c.containerState == "unknown") C.LAMP_OFF else C.DOWN)
            }
        }, LinearLayout.LayoutParams(dp(12), dp(12)).apply { rightMargin = dp(12) })

        val info = LinearLayout(this).apply { orientation = LinearLayout.VERTICAL }
        info.addView(text(c.service, 16f, C.TEXT, bold = true))
        info.addView(text(c.container, 11f, C.MUTED, mono = true))
        val (monLabel, monColor) = monitorState(c.monitorState)
        info.addView(LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            setPadding(0, dp(6), 0, 0)
            addView(text(stateLabel, 12f, stateColor, mono = true))
            addView(text("  ·  monitor: ", 12f, C.MUTED))
            addView(text(monLabel, 12f, monColor))
        })
        card.addView(info, LinearLayout.LayoutParams(0, -2, 1f))

        val button = Button(this).apply {
            isAllCaps = false
            textSize = 14f
            typeface = Typeface.DEFAULT_BOLD
            minWidth = dp(96)
            isEnabled = !working && c.containerState != "unknown" && c.containerState != "missing"
            text = when {
                working -> if (c.running) "Apagando…" else "Encendiendo…"
                c.running -> "Apagar"
                else -> "Encender"
            }
            val accent = if (c.running) C.DOWN else C.OK
            setTextColor(if (isEnabled) accent else C.MUTED)
            background = rounded(C.SURFACE2, if (isEnabled) accent else C.LAMP_OFF)
            stateListAnimator = null
            setOnClickListener { toggle(c) }
        }
        card.addView(button, LinearLayout.LayoutParams(-2, dp(42)).apply { leftMargin = dp(10) })
        return card
    }

    private fun containerState(s: String): Pair<String, Int> = when (s) {
        "running" -> "encendido" to C.OK
        "exited", "created", "dead" -> "apagado" to C.DOWN
        "restarting" -> "reiniciando" to C.WARN
        "paused" -> "pausado" to C.WARN
        "missing" -> "no existe" to C.MUTED
        else -> "sin datos" to C.MUTED
    }

    private fun monitorState(s: String): Pair<String, Int> = when (s) {
        "wp-full" -> "OK" to C.OK
        "wp-degraded" -> "WP degradado" to C.WARN
        "non-wp" -> "no-WP" to C.NONWP
        "down" -> "caído" to C.DOWN
        "unknown" -> "verificando" to C.TEXT2
        else -> "sin monitorear" to C.MUTED
    }

    private fun showBanner(msg: String, color: Int) {
        banner.text = msg
        banner.background = rounded(C.SURFACE, color)
        banner.visibility = View.VISIBLE
    }

    private fun hideBanner() {
        banner.visibility = View.GONE
    }

    private fun showSettings() {
        val form = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(dp(20), dp(8), dp(20), 0)
        }
        fun field(label: String, value: String, secret: Boolean = false): EditText {
            form.addView(text(label, 12f, C.TEXT2).apply { setPadding(0, dp(10), 0, 0) })
            return EditText(this).apply {
                setText(value)
                setSingleLine()
                setTextColor(C.TEXT)
                inputType = if (secret) InputType.TYPE_CLASS_TEXT or InputType.TYPE_TEXT_VARIATION_PASSWORD
                else InputType.TYPE_CLASS_TEXT or InputType.TYPE_TEXT_VARIATION_URI
                form.addView(this)
            }
        }
        val url = field("URL del panel", settings.serverUrl)
        val token = field("Token de la app (CHAOS_APP_TOKEN)", settings.appToken, secret = true)
        val cfId = field("Cloudflare Access Client ID (opcional)", settings.cfClientId)
        val cfSecret = field("Cloudflare Access Client Secret (opcional)", settings.cfClientSecret, secret = true)

        AlertDialog.Builder(this, android.R.style.Theme_Material_Dialog_Alert)
            .setTitle("Conexión")
            .setView(ScrollView(this).apply { addView(form) })
            .setPositiveButton("Guardar") { _, _ ->
                settings.serverUrl = url.text.toString()
                settings.appToken = token.text.toString()
                settings.cfClientId = cfId.text.toString()
                settings.cfClientSecret = cfSecret.text.toString()
                load()
            }
            .setNegativeButton("Cancelar", null)
            .show()
    }

    // ------------------------------------------------------------- helpers

    private fun text(s: String, size: Float, color: Int, bold: Boolean = false, mono: Boolean = false) =
        TextView(this).apply {
            text = s
            setTextSize(TypedValue.COMPLEX_UNIT_SP, size)
            setTextColor(color)
            typeface = when {
                mono -> Typeface.MONOSPACE
                bold -> Typeface.DEFAULT_BOLD
                else -> Typeface.DEFAULT
            }
        }

    private fun rounded(fill: Int, stroke: Int) = GradientDrawable().apply {
        cornerRadius = dp(12).toFloat()
        setColor(fill)
        setStroke(dp(1), stroke)
    }

    private fun dp(v: Int) = (v * resources.displayMetrics.density).toInt()

    companion object {
        private const val POLL_MS = 5_000L
    }
}
