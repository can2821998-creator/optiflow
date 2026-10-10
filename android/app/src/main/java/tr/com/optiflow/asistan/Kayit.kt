package tr.com.optiflow.asistan

import android.content.Context
import android.util.Log
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

/** Son olaylar (ana ekranda görünür, telefonda saklanır) + isteğe bağlı OptiFlow tanılama kaydı. */
object Kayit {
    private val saat = SimpleDateFormat("dd.MM HH:mm:ss", Locale("tr", "TR"))

    fun yaz(ctx: Context, m: String, sunucuya: Boolean = true) {
        Log.i("OptiFlowAsistan", m)
        val p = ctx.applicationContext.getSharedPreferences("asistan_olay", Context.MODE_PRIVATE)
        synchronized(this) {
            val satirlar = (listOf(saat.format(Date()) + "  " + m) + (p.getString("liste", "") ?: "").split('\n').filter { it.isNotBlank() }).take(40)
            p.edit().putString("liste", satirlar.joinToString("\n")).apply()
        }
        if (sunucuya) Api.olay(Ayarlar(ctx), m)
    }

    fun liste(ctx: Context): List<String> =
        (ctx.applicationContext.getSharedPreferences("asistan_olay", Context.MODE_PRIVATE).getString("liste", "") ?: "")
            .split('\n').filter { it.isNotBlank() }
}
