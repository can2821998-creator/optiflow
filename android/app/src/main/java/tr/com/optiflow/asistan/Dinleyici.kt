package tr.com.optiflow.asistan

import android.annotation.SuppressLint
import android.content.Context
import android.content.Intent
import android.media.AudioFormat
import android.media.AudioRecord
import android.media.MediaRecorder
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.ParcelFileDescriptor
import android.os.SystemClock
import android.speech.RecognitionListener
import android.speech.RecognizerIntent
import android.speech.SpeechRecognizer
import java.io.OutputStream
import java.util.concurrent.ArrayBlockingQueue
import java.util.concurrent.CountDownLatch
import java.util.concurrent.TimeUnit
import java.util.concurrent.atomic.AtomicReference

/**
 * Arayanı dinler: mikrofonu KENDİSİ açar (AudioRecord, 16 kHz).
 *  - Tuş sesleri (DTMF) bu ses üzerinde çözülür.
 *  - Aynı ses Android 13+ konuşma tanımaya boru (EXTRA_AUDIO_SOURCE) ile verilir; böylece tanıma
 *    servisi mikrofonu ayrıca açmaya çalışmaz (görüşme sırasında buna izin verilmez).
 * dinle() bitene kadar bekler: iş parçacığında çağırın.
 */
class Dinleyici(private val ctx: Context, private val ana: Handler) {
    data class Sonuc(val metin: String?, val tus: String?, val seviye: Int = 0)

    @Volatile var durdur = false

    @SuppressLint("MissingPermission")
    fun dinle(mod: String, olay: (String) -> Unit): Sonuc {
        durdur = false
        val oran = 16000
        val sinir = when (mod) { "numara" -> 16000L; "not" -> 22000L; else -> 10000L }
        val enAz = AudioRecord.getMinBufferSize(oran, AudioFormat.CHANNEL_IN_MONO, AudioFormat.ENCODING_PCM_16BIT)
        val kayit = try {
            AudioRecord(MediaRecorder.AudioSource.VOICE_RECOGNITION, oran, AudioFormat.CHANNEL_IN_MONO, AudioFormat.ENCODING_PCM_16BIT, maxOf(enAz, oran))
        } catch (e: Exception) {
            olay("Mikrofon açılamadı: ${e.message}")
            return Sonuc(null, null)
        }
        if (kayit.state != AudioRecord.STATE_INITIALIZED) {
            olay("Mikrofon açılamadı (izin ya da ses erişimi kapalı)")
            kayit.release()
            return Sonuc(null, null)
        }

        // Konuşma tanıma (Android 13+: kendi sesimizi borudan veririz)
        val metin = AtomicReference<String?>(null)
        val sozBitti = CountDownLatch(1)
        val tanici = AtomicReference<SpeechRecognizer?>(null)
        var yaz: OutputStream? = null
        var okuUcu: ParcelFileDescriptor? = null
        if (Build.VERSION.SDK_INT >= 33 && SpeechRecognizer.isRecognitionAvailable(ctx)) {
            val boru = ParcelFileDescriptor.createPipe()
            okuUcu = boru[0]
            yaz = ParcelFileDescriptor.AutoCloseOutputStream(boru[1])
            val oku = boru[0]
            ana.post {
                try {
                    val t = if (SpeechRecognizer.isOnDeviceRecognitionAvailable(ctx)) SpeechRecognizer.createOnDeviceSpeechRecognizer(ctx)
                    else SpeechRecognizer.createSpeechRecognizer(ctx)
                    tanici.set(t)
                    t.setRecognitionListener(object : RecognitionListener {
                        override fun onReadyForSpeech(params: Bundle?) {}
                        override fun onBeginningOfSpeech() {}
                        override fun onRmsChanged(rmsdB: Float) {}
                        override fun onBufferReceived(buffer: ByteArray?) {}
                        override fun onEndOfSpeech() {}
                        override fun onPartialResults(partialResults: Bundle?) {}
                        override fun onEvent(eventType: Int, params: Bundle?) {}
                        override fun onResults(results: Bundle?) {
                            val l = results?.getStringArrayList(SpeechRecognizer.RESULTS_RECOGNITION)
                            metin.set(l?.firstOrNull())
                            sozBitti.countDown()
                        }
                        override fun onError(error: Int) {
                            if (error != SpeechRecognizer.ERROR_NO_MATCH && error != SpeechRecognizer.ERROR_SPEECH_TIMEOUT) {
                                olay("Konuşma tanıma hatası $error")
                            }
                            sozBitti.countDown()
                        }
                    })
                    val i = Intent(RecognizerIntent.ACTION_RECOGNIZE_SPEECH).apply {
                        putExtra(RecognizerIntent.EXTRA_LANGUAGE_MODEL, RecognizerIntent.LANGUAGE_MODEL_FREE_FORM)
                        putExtra(RecognizerIntent.EXTRA_LANGUAGE, "tr-TR")
                        putExtra(RecognizerIntent.EXTRA_MAX_RESULTS, 1)
                        putExtra(RecognizerIntent.EXTRA_AUDIO_SOURCE, oku)
                        putExtra(RecognizerIntent.EXTRA_AUDIO_SOURCE_CHANNEL_COUNT, 1)
                        putExtra(RecognizerIntent.EXTRA_AUDIO_SOURCE_ENCODING, AudioFormat.ENCODING_PCM_16BIT)
                        putExtra(RecognizerIntent.EXTRA_AUDIO_SOURCE_SAMPLING_RATE, oran)
                        putExtra(RecognizerIntent.EXTRA_SPEECH_INPUT_COMPLETE_SILENCE_LENGTH_MILLIS, if (mod == "not") 2500L else 1500L)
                    }
                    t.startListening(i)
                } catch (e: Exception) {
                    olay("Konuşma tanıma başlatılamadı: ${e.message}")
                    sozBitti.countDown()
                }
            }
        } else {
            sozBitti.countDown()   // tanıma yok: yalnızca tuşlar
        }

        // Boruya yazma ayrı iş parçacığında: tanıma servisi sesi okumazsa (desteklemiyorsa) boru dolar ve
        // yazma kilitlenir — dinleme bu yüzden asla takılmamalı. Kuyruk doluysa ses parçası atılır.
        val kuyruk = ArrayBlockingQueue<ByteArray>(200)
        val yazilacak = yaz
        val yazici = if (yazilacak != null) Thread {
            try {
                while (true) {
                    val parca = kuyruk.take()
                    if (parca.isEmpty()) break
                    yazilacak.write(parca)
                }
            } catch (_: Exception) {
            } finally {
                try { yazilacak.close() } catch (_: Exception) {}
            }
        }.apply { isDaemon = true; start() } else null
        val dtmf = DtmfCozucu(oran)
        val tuslar = StringBuilder()
        val tampon = ShortArray(oran / 50)          // 20 ms
        val bas = SystemClock.elapsedRealtime()
        var sonTus = 0L
        var tepe = 0.0
        kayit.startRecording()
        try {
            while (!durdur && SystemClock.elapsedRealtime() - bas < sinir) {
                val n = kayit.read(tampon, 0, tampon.size)
                if (n <= 0) continue
                var e = 0.0
                for (i in 0 until n) { val v = tampon[i] / 32768.0; e += v * v }
                tepe = maxOf(tepe, Math.sqrt(e / n))
                dtmf.isle(tampon, n)?.let {
                    tuslar.append(it)
                    sonTus = SystemClock.elapsedRealtime()
                }
                if (yazici != null) {
                    val parca = ByteArray(n * 2)
                    for (i in 0 until n) {
                        parca[2 * i] = (tampon[i].toInt() and 0xff).toByte()
                        parca[2 * i + 1] = (tampon[i].toInt() shr 8 and 0xff).toByte()
                    }
                    kuyruk.offer(parca)
                }
                val simdi = SystemClock.elapsedRealtime()
                if (mod == "numara") {
                    if (tuslar.endsWith("#") || tuslar.count { it.isDigit() } >= 10 || (tuslar.isNotEmpty() && simdi - sonTus > 4000)) break
                } else if (tuslar.isNotEmpty() && simdi - sonTus > 700) {
                    break   // menüde tek tuş yeter
                }
                if (sozBitti.count == 0L && tuslar.isEmpty()) break
            }
        } finally {
            try { kayit.stop() } catch (_: Exception) {}
            kayit.release()
            kuyruk.clear()
            kuyruk.offer(ByteArray(0))   // yazıcıya "bitti"
            yazici?.interrupt()
        }
        if (tuslar.isEmpty()) sozBitti.await(4, TimeUnit.SECONDS)
        ana.post {
            try { tanici.get()?.destroy() } catch (_: Exception) {}
            try { okuUcu?.close() } catch (_: Exception) {}
        }
        // seviye: en yüksek ses (0–100). 0–1 → mikrofona hiç ses gelmiyor (görüşmede ses erişimi engellenmiş olabilir)
        return Sonuc(metin.get()?.takeIf { it.isNotBlank() }, tuslar.toString().takeIf { it.isNotEmpty() }, (tepe * 100).toInt().coerceIn(0, 100))
    }
}
