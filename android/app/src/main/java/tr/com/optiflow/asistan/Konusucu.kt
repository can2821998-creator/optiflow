package tr.com.optiflow.asistan

import android.content.Context
import android.media.AudioAttributes
import android.os.Bundle
import android.speech.tts.TextToSpeech
import android.speech.tts.UtteranceProgressListener
import java.util.Locale
import java.util.UUID
import java.util.concurrent.CountDownLatch
import java.util.concurrent.TimeUnit

/** Telefonun kendi Türkçe seslendirmesi (TextToSpeech). soyle() bitene kadar bekler: iş parçacığında çağırın. */
class Konusucu(ctx: Context, private val hazirOldu: (Boolean, String) -> Unit) : TextToSpeech.OnInitListener {
    private val tts = TextToSpeech(ctx.applicationContext, this)
    @Volatile var hazir = false
        private set
    private var bekleyen: CountDownLatch? = null

    override fun onInit(status: Int) {
        if (status != TextToSpeech.SUCCESS) {
            hazirOldu(false, "Seslendirme başlatılamadı")
            return
        }
        val r = tts.setLanguage(Locale("tr", "TR"))
        hazir = r >= TextToSpeech.LANG_AVAILABLE
        tts.setSpeechRate(0.95f)
        tts.setOnUtteranceProgressListener(object : UtteranceProgressListener() {
            override fun onStart(utteranceId: String?) {}
            override fun onDone(utteranceId: String?) { bekleyen?.countDown() }
            @Deprecated("Deprecated in Java")
            override fun onError(utteranceId: String?) { bekleyen?.countDown() }
            override fun onError(utteranceId: String?, errorCode: Int) { bekleyen?.countDown() }
        })
        hazirOldu(hazir, if (hazir) "Türkçe ses hazır" else "Türkçe ses paketi yok: Ayarlar › Metin okuma çıktısı › Türkçe sesi indirin")
    }

    fun soyle(metin: String, kanal: String): Boolean {
        if (!hazir || metin.isBlank()) return false
        val usage = if (kanal == "medya") AudioAttributes.USAGE_MEDIA else AudioAttributes.USAGE_VOICE_COMMUNICATION
        tts.setAudioAttributes(AudioAttributes.Builder().setUsage(usage).setContentType(AudioAttributes.CONTENT_TYPE_SPEECH).build())
        val l = CountDownLatch(1)
        bekleyen = l
        val id = UUID.randomUUID().toString()
        tts.speak(metin, TextToSpeech.QUEUE_FLUSH, Bundle(), id)
        // Uzun metin için üst sınır: ~ 14 karakter/sn
        return l.await((metin.length / 10L + 8L).coerceAtMost(90L), TimeUnit.SECONDS)
    }

    fun sustur() {
        tts.stop()
        bekleyen?.countDown()
    }

    fun kapat() {
        sustur()
        tts.shutdown()
    }
}
