package tr.com.optiflow.asistan

import android.Manifest
import android.accessibilityservice.AccessibilityService
import android.annotation.SuppressLint
import android.content.pm.PackageManager
import android.media.AudioDeviceInfo
import android.media.AudioManager
import android.os.Build
import android.os.Handler
import android.os.Looper
import android.os.PowerManager
import android.os.SystemClock
import android.telecom.TelecomManager
import android.telephony.TelephonyCallback
import android.telephony.TelephonyManager
import android.util.Log
import android.view.accessibility.AccessibilityEvent
import android.view.accessibility.AccessibilityNodeInfo
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import java.util.concurrent.Executors

/**
 * Asistanın kendisi. Erişilebilirlik hizmeti olarak çalışır çünkü:
 *  - sistem onu sürekli ayakta tutar (telefon yeniden başlasa da kendiliğinden açılır),
 *  - Android, görüşme sırasında mikrofonu yalnızca böyle bir hizmete paylaştırır.
 * Ekran içeriğine YALNIZCA görüşme ekranındaki "Hoparlör" düğmesini bulmak için bakar (yedek yol); başka hiçbir şey okunmaz.
 *
 * Akış: telefon çalar → [bekleme] saniye kimse açmazsa aramayı açar → hoparlörü açar →
 * sunucuya "basla" → söyler → dinler (tuş + konuşma) → "cevap" → … → "bitir" gelince kapatır.
 */
class AsistanServisi : AccessibilityService() {

    companion object {
        @Volatile var o: AsistanServisi? = null
        @Volatile var sonNumara: String = ""
        @Volatile var sonNumaraZaman: Long = 0L
        val olaylar = ArrayDeque<String>()
        private val saat = SimpleDateFormat("HH:mm:ss", Locale("tr", "TR"))

        fun kaydet(m: String) {
            Log.i("OptiFlowAsistan", m)
            synchronized(olaylar) {
                olaylar.addFirst(saat.format(Date()) + "  " + m)
                while (olaylar.size > 40) olaylar.removeLast()
            }
        }
    }

    private lateinit var ayar: Ayarlar
    private lateinit var tm: TelephonyManager
    private lateinit var telecom: TelecomManager
    private lateinit var ses: AudioManager
    private val ana = Handler(Looper.getMainLooper())
    private val isci = Executors.newSingleThreadExecutor()
    private var konusucu: Konusucu? = null
    private var dinleyici: Dinleyici? = null
    private var geriCagri: TelephonyCallback? = null
    private var cevapGorevi: Runnable? = null
    private var durum = TelephonyManager.CALL_STATE_IDLE
    @Volatile private var bizActik = false
    @Volatile private var gorusmeVar = false
    @Volatile private var durdurIstek = false

    override fun onServiceConnected() {
        super.onServiceConnected()
        o = this
        ayar = Ayarlar(this)
        tm = getSystemService(TelephonyManager::class.java)
        telecom = getSystemService(TelecomManager::class.java)
        ses = getSystemService(AudioManager::class.java)
        Api.surum = try { packageManager.getPackageInfo(packageName, 0).versionName ?: "?" } catch (e: Exception) { "?" }
        cokmeYakalayici()
        konusucu = Konusucu(this) { _, mesaj -> olay(mesaj) }
        dinleyici = Dinleyici(this, ana)
        telefonuDinle()
        ayarlariYenile()
        olay("Asistan hizmeti açıldı (sürüm ${Api.surum}, ${Build.MANUFACTURER} ${Build.MODEL}, Android ${Build.VERSION.RELEASE})")
        val p = getSharedPreferences("asistan", MODE_PRIVATE)
        p.getString("son_cokme", null)?.let { olay("Önceki çökme: $it") }
    }

    /** Hizmet çökerse nedeni bir sonraki açılışta OptiFlow'a ve ana ekrana yazılır (tanılama). */
    private fun cokmeYakalayici() {
        val onceki = Thread.getDefaultUncaughtExceptionHandler()
        Thread.setDefaultUncaughtExceptionHandler { t, e ->
            try {
                val ozet = e.javaClass.simpleName + ": " + (e.message ?: "") + " @ " +
                    e.stackTrace.take(5).joinToString(" < ") { it.className.substringAfterLast('.') + "." + it.methodName + ":" + it.lineNumber }
                getSharedPreferences("asistan", MODE_PRIVATE).edit()
                    .putString("son_cokme", java.text.SimpleDateFormat("dd.MM HH:mm", Locale("tr", "TR")).format(Date()) + " " + ozet.take(300)).commit()
                val g = Thread { Api.istek(ayar, "olay", mapOf("metin" to ("ÇÖKME: " + ozet).take(200))) }
                g.start()
                g.join(2500)
            } catch (_: Throwable) {}
            onceki?.uncaughtException(t, e)
        }
    }

    /** Ana ekrandaki "Ses testi": arama olmadan seçili kanaldan bir cümle söyler. */
    fun sesTesti() {
        isci.execute {
            val bas = SystemClock.elapsedRealtime()
            val tamam = konusucu?.soyle("Merhaba, bu bir ses denemesidir. Beni duyuyorsanız asistan konuşabiliyor.", ayar.sesKanali) == true
            olay("Ses testi (" + ayar.sesKanali + " kanalı): " + (if (tamam) "seslendirme bitti" else "seslendirme başlamadı ya da yarım kaldı") +
                " · " + (SystemClock.elapsedRealtime() - bas) / 100 / 10.0 + " sn")
        }
    }

    override fun onAccessibilityEvent(event: AccessibilityEvent?) {}
    override fun onInterrupt() {}

    override fun onDestroy() {
        o = null
        geriCagri?.let { try { tm.unregisterTelephonyCallback(it) } catch (_: Exception) {} }
        konusucu?.kapat()
        isci.shutdownNow()
        super.onDestroy()
    }

    private fun olay(m: String, sunucuya: Boolean = true) {
        kaydet(m)
        if (sunucuya && ::ayar.isInitialized) Api.olay(ayar, m)
    }

    private fun izinVar(p: String) = checkSelfPermission(p) == PackageManager.PERMISSION_GRANTED

    fun hazirMi(): String? = when {
        !ayar.bagli -> "OptiFlow'a bağlı değil"
        !ayar.yerelAcik -> "Bu telefonda duraklatıldı"
        !ayar.sunucuAcik -> "OptiFlow'da kapalı"
        !izinVar(Manifest.permission.ANSWER_PHONE_CALLS) -> "Aramayı açma izni yok"
        !izinVar(Manifest.permission.RECORD_AUDIO) -> "Mikrofon izni yok"
        konusucu?.hazir != true -> "Türkçe ses hazır değil"
        else -> null
    }

    private fun telefonuDinle() {
        if (!izinVar(Manifest.permission.READ_PHONE_STATE)) {
            olay("Telefon durumu izni yok: uygulamada 'İzinleri ver'e dokunun")
            return
        }
        val cb = object : TelephonyCallback(), TelephonyCallback.CallStateListener {
            override fun onCallStateChanged(state: Int) {
                ana.post { durumDegisti(state) }
            }
        }
        try {
            tm.registerTelephonyCallback(mainExecutor, cb)
            geriCagri = cb
        } catch (e: SecurityException) {
            olay("Telefon durumu dinlenemedi: ${e.message}")
        }
    }

    /** İzinler sonradan verildiyse ana ekrandan çağrılır */
    fun yenidenBaslat() {
        if (geriCagri == null) telefonuDinle()
        ayarlariYenile()
    }

    fun ayarlariYenile() {
        if (!ayar.bagli) return
        Thread {
            val c = Api.istek(ayar, "ayar")
            if (c.optBoolean("ok")) {
                ayar.sunucuAcik = c.optBoolean("acik", true)
                ayar.bekleme = c.optInt("bekleme_sn", 20)
                ayar.magazaAdi = c.optString("magaza", ayar.magazaAdi)
            } else if (c.optBoolean("yeniden_bagla")) {
                ayar.baglantiyiKaldir()
                kaydet("OptiFlow bağlantıyı kaldırmış: yeniden bağlayın")
            }
        }.start()
        ana.removeCallbacksAndMessages("yenile")
        ana.postAtTime({ ayarlariYenile() }, "yenile", SystemClock.uptimeMillis() + 30 * 60 * 1000L)
    }

    private fun durumDegisti(yeni: Int) {
        val onceki = durum
        durum = yeni
        when (yeni) {
            TelephonyManager.CALL_STATE_RINGING -> {
                val neden = hazirMi()
                if (neden != null) {
                    kaydet("Arama geldi; asistan açmayacak: $neden")
                    return
                }
                cevapGorevi?.let { ana.removeCallbacks(it) }
                val g = Runnable { cevapla() }
                cevapGorevi = g
                ana.postDelayed(g, ayar.bekleme * 1000L)
                kaydet("Arama çalıyor; ${ayar.bekleme} sn içinde açılmazsa asistan açacak", )
            }
            TelephonyManager.CALL_STATE_OFFHOOK -> {
                cevapGorevi?.let { ana.removeCallbacks(it) }
                cevapGorevi = null
                if (onceki == TelephonyManager.CALL_STATE_RINGING && bizActik && !gorusmeVar) {
                    gorusmeVar = true
                    ana.postDelayed({ gorusmeyiBaslat(false, numara()) }, 900)
                } else if (!bizActik) {
                    kaydet("Arama mağazadan açıldı; asistan karışmıyor", )
                }
            }
            TelephonyManager.CALL_STATE_IDLE -> {
                cevapGorevi?.let { ana.removeCallbacks(it) }
                cevapGorevi = null
                if (gorusmeVar) {
                    durdurIstek = true
                    dinleyici?.durdur = true
                    konusucu?.sustur()
                }
                bizActik = false
            }
        }
    }

    private fun numara(): String =
        if (SystemClock.elapsedRealtime() - sonNumaraZaman < 120_000L) sonNumara else ""

    @SuppressLint("MissingPermission")
    private fun cevapla() {
        cevapGorevi = null
        if (durum != TelephonyManager.CALL_STATE_RINGING) return
        try {
            bizActik = true
            @Suppress("DEPRECATION")
            telecom.acceptRingingCall()
            olay("Asistan aramayı açtı" + if (numara().isEmpty()) " (numara bilinmiyor: arama tanıma rolü verilmemiş olabilir)" else "")
        } catch (e: Exception) {
            bizActik = false
            olay("Arama açılamadı: ${e.message}")
        }
    }

    @Suppress("DEPRECATION")
    private fun hoparlorAcikMi(): Boolean =
        ses.communicationDevice?.type == AudioDeviceInfo.TYPE_BUILTIN_SPEAKER || ses.isSpeakerphoneOn

    private fun hoparlorAc() {
        try {
            val hoparlor = ses.availableCommunicationDevices.firstOrNull { it.type == AudioDeviceInfo.TYPE_BUILTIN_SPEAKER }
            val sonuc = if (hoparlor != null) ses.setCommunicationDevice(hoparlor) else false
            @Suppress("DEPRECATION")
            ses.isSpeakerphoneOn = true
            try {
                ses.setStreamVolume(AudioManager.STREAM_VOICE_CALL, ses.getStreamMaxVolume(AudioManager.STREAM_VOICE_CALL), 0)
                ses.setStreamVolume(AudioManager.STREAM_MUSIC, ses.getStreamMaxVolume(AudioManager.STREAM_MUSIC), 0)
            } catch (_: Exception) {}
            ana.postDelayed({
                val acik = hoparlorAcikMi()
                olay("Hoparlör: setCommunicationDevice=$sonuc, açık=$acik, ses modu=${ses.mode}")
                if (!acik) hoparlorDugmesineBas()
            }, 600)
        } catch (e: Exception) {
            olay("Hoparlör açılamadı: ${e.message}")
            hoparlorDugmesineBas()
        }
    }

    /** Yedek yol: görüşme ekranındaki "Hoparlör" düğmesine erişilebilirlikle basar (başka hiçbir şeye dokunmaz). */
    private fun hoparlorDugmesineBas() {
        try {
            val adlar = listOf("Hoparlör", "Hoparlörü aç", "Hoparlörü", "Speaker", "Speakerphone", "Ses yükselticisi")
            val pencereler = windows.mapNotNull { it.root } + listOfNotNull(rootInActiveWindow)
            for (kok in pencereler) {
                for (ad in adlar) {
                    val dugum = kok.findAccessibilityNodeInfosByText(ad).firstOrNull() ?: continue
                    var d: AccessibilityNodeInfo? = dugum
                    while (d != null && !d.isClickable) d = d.parent
                    if (d != null && !d.isChecked && d.performAction(AccessibilityNodeInfo.ACTION_CLICK)) {
                        olay("Hoparlör düğmesine basıldı (\"$ad\")")
                        return
                    }
                }
            }
            olay("Görüşme ekranında Hoparlör düğmesi bulunamadı")
        } catch (e: Exception) {
            olay("Hoparlör düğmesi: ${e.message}")
        }
    }

    private fun hoparlorKapat() {
        try {
            ses.clearCommunicationDevice()
            @Suppress("DEPRECATION")
            ses.isSpeakerphoneOn = false
        } catch (_: Exception) {}
    }

    /** deneme = true: arama olmadan (ana ekrandaki "Deneme konuşması") */
    fun gorusmeyiBaslat(deneme: Boolean, numara: String) {
        durdurIstek = false
        gorusmeVar = true
        if (!deneme) hoparlorAc()
        isci.execute { gorusme(deneme, numara) }
    }

    fun denemeyiDurdur() {
        durdurIstek = true
        dinleyici?.durdur = true
        konusucu?.sustur()
    }

    @SuppressLint("MissingPermission")
    private fun gorusme(deneme: Boolean, numara: String) {
        val kilit = getSystemService(PowerManager::class.java).newWakeLock(PowerManager.PARTIAL_WAKE_LOCK, "OptiFlowAsistan:gorusme")
        kilit.acquire(10 * 60 * 1000L)
        val bas = SystemClock.elapsedRealtime()
        val k = konusucu
        val d = dinleyici
        var oturum = ""
        try {
            if (k == null || d == null) return
            var c = Api.istek(ayar, "basla", mapOf("numara" to (if (deneme && numara.isEmpty()) "0000000000" else numara)))
            if (!c.optBoolean("ok")) {
                olay("Görüşme başlatılamadı: " + c.optString("hata"))
                k.soyle("Şu anda size yardımcı olamıyorum. Lütfen daha sonra tekrar arayın.", ayar.sesKanali)
                return
            }
            oturum = c.optString("oturum")
            olay("Görüşme başladı (numara " + (if (numara.isEmpty()) "bilinmiyor" else "var") + ", kanal ${ayar.sesKanali}" + (if (!deneme) ", hoparlör ${hoparlorAcikMi()}" else "") + ")")
            var tur = 0
            while (!durdurIstek && tur < 12) {
                tur++
                val sBas = SystemClock.elapsedRealtime()
                val soylendi = k.soyle(c.optString("soyle"), ayar.sesKanali)
                if (tur == 1) olay("Karşılama " + (if (soylendi) "söylendi" else "SÖYLENEMEDİ") + " (" + (SystemClock.elapsedRealtime() - sBas) / 1000 + " sn)")
                if (c.optBoolean("bitir") || durdurIstek) break
                val s = d.dinle(c.optString("mod", "menu")) { kaydet(it) }
                if (durdurIstek) break
                kaydet("Duyulan: " + (s.tus?.let { "tuş $it" } ?: "") + (s.metin?.let { " \"$it\"" } ?: if (s.tus == null) "—" else ""))
                // Sunucuya içerik gönderilmez; yalnızca tanılama: tuş var mı, konuşma tanındı mı, ses seviyesi
                olay("Dinleme: " + (if (s.tus != null) "tuş" else "tuş yok") + " · " + (if (s.metin != null) "konuşma tanındı" else "konuşma yok") +
                    " · ses seviyesi ${s.seviye}" + if (s.seviye <= 1) " (mikrofona ses gelmiyor)" else "")
                val form = mutableMapOf("oturum" to oturum)
                s.metin?.let { form["metin"] = it }
                s.tus?.let { form["tus"] = it }
                c = Api.istek(ayar, "cevap", form)
                if (!c.optBoolean("ok")) {
                    olay("Cevap alınamadı: " + c.optString("hata"))
                    k.soyle("Bağlantıda bir sorun oldu. Mağazamız sizi geri arayacak.", ayar.sesKanali)
                    break
                }
            }
        } catch (e: Exception) {
            olay("Görüşmede hata: ${e.message}")
        } finally {
            val sure = ((SystemClock.elapsedRealtime() - bas) / 1000).toString()
            if (oturum.isNotEmpty()) {
                Api.istek(ayar, "bitti", mapOf("oturum" to oturum, "sure" to sure, "olay" to if (deneme) "deneme" else ""))
            }
            if (!deneme && !durdurIstek) {
                try {
                    @Suppress("DEPRECATION")
                    telecom.endCall()
                } catch (e: Exception) {
                    olay("Arama kapatılamadı: ${e.message}")
                }
            }
            ana.post { if (!deneme) hoparlorKapat() }
            kaydet((if (deneme) "Deneme" else "Görüşme") + " bitti ($sure sn)")
            gorusmeVar = false
            bizActik = false
            if (kilit.isHeld) kilit.release()
        }
    }
}
