package tr.com.optiflow.asistan

import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL
import java.net.URLEncoder

/** OptiFlow asistan.php uç noktası. Ağ çağrısıdır: ana iş parçacığında çağırmayın. */
object Api {
    @Volatile var surum: String = "?"

    fun istek(a: Ayarlar, eylem: String, form: Map<String, String>? = null): JSONObject =
        istek(a.sunucu, a.magaza, a.anahtar, eylem, form)

    fun istek(sunucu: String, magaza: Int, anahtar: String, eylem: String, form: Map<String, String>? = null): JSONObject {
        return try {
            val url = URL(sunucu.trimEnd('/') + "/asistan.php?m=" + magaza + "&eylem=" + eylem)
            val c = url.openConnection() as HttpURLConnection
            c.connectTimeout = 8000
            c.readTimeout = 15000
            c.setRequestProperty("Accept", "application/json")
            c.setRequestProperty("X-Asistan-Surum", surum)
            if (anahtar.isNotEmpty()) c.setRequestProperty("X-Asistan-Anahtar", anahtar)
            if (form != null) {
                c.requestMethod = "POST"
                c.doOutput = true
                c.setRequestProperty("Content-Type", "application/x-www-form-urlencoded; charset=utf-8")
                val govde = form.entries.joinToString("&") {
                    URLEncoder.encode(it.key, "UTF-8") + "=" + URLEncoder.encode(it.value, "UTF-8")
                }
                c.outputStream.use { it.write(govde.toByteArray(Charsets.UTF_8)) }
            }
            val durum = c.responseCode
            val akis = if (durum in 200..299) c.inputStream else c.errorStream
            val metin = akis?.bufferedReader(Charsets.UTF_8)?.use { it.readText() } ?: ""
            c.disconnect()
            try {
                JSONObject(metin)
            } catch (e: Exception) {
                JSONObject().put("ok", false).put("hata", "Sunucu yanıtı okunamadı (HTTP $durum)")
            }
        } catch (e: Exception) {
            JSONObject().put("ok", false).put("hata", "Bağlantı kurulamadı: " + (e.message ?: e.javaClass.simpleName))
        }
    }

    /** Tanılama olayı (OptiFlow › Telefon asistanı sayfasında görünür). Arka planda gönderilir. */
    fun olay(a: Ayarlar, metin: String) {
        if (!a.bagli) return
        Thread { istek(a, "olay", mapOf("metin" to metin.take(200))) }.start()
    }
}
