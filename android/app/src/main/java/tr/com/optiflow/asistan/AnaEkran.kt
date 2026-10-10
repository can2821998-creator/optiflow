package tr.com.optiflow.asistan

import android.Manifest
import android.app.Activity
import android.app.AlertDialog
import android.app.role.RoleManager
import android.content.Intent
import android.content.pm.PackageManager
import android.graphics.Color
import android.graphics.Typeface
import android.graphics.drawable.GradientDrawable
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.os.PowerManager
import android.provider.Settings
import android.text.InputType
import android.widget.Button
import android.widget.EditText
import android.widget.LinearLayout
import android.widget.ScrollView
import android.widget.TextView
import android.widget.Toast

/** Kurulum ve durum ekranı. Asistanın kendisi AsistanServisi'nde çalışır; bu ekran kapalıyken de. */
class AnaEkran : Activity() {
    private lateinit var ayar: Ayarlar
    private lateinit var kutu: LinearLayout
    private val ana = Handler(Looper.getMainLooper())
    private val bordo = Color.parseColor("#8F1A2E")
    private val gece = Color.parseColor("#160B0F")

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        ayar = Ayarlar(this)
        val kaydir = ScrollView(this)
        kutu = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(dp(18), dp(22), dp(18), dp(28))
        }
        kaydir.addView(kutu)
        setContentView(kaydir)
        baglantiLinki(intent)
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        baglantiLinki(intent)
    }

    override fun onResume() {
        super.onResume()
        AsistanServisi.o?.yenidenBaslat()
        ciz()
    }

    private fun dp(v: Int) = (v * resources.displayMetrics.density).toInt()

    private fun izinVar(p: String) = checkSelfPermission(p) == PackageManager.PERMISSION_GRANTED

    private fun rolVar(): Boolean = getSystemService(RoleManager::class.java).isRoleHeld(RoleManager.ROLE_CALL_SCREENING)

    private fun pilSerbest(): Boolean = getSystemService(PowerManager::class.java).isIgnoringBatteryOptimizations(packageName)

    private fun yazi(metin: String, boy: Float = 15f, kalin: Boolean = false, renk: Int = gece): TextView = TextView(this).apply {
        text = metin
        textSize = boy
        setTextColor(renk)
        if (kalin) setTypeface(typeface, Typeface.BOLD)
        setPadding(0, dp(4), 0, dp(4))
    }

    private fun dugme(metin: String, ana: Boolean = true, tik: () -> Unit): Button = Button(this).apply {
        text = metin
        isAllCaps = false
        textSize = 15f
        setTextColor(if (ana) Color.WHITE else gece)
        background = GradientDrawable().apply {
            cornerRadius = dp(12).toFloat()
            if (ana) setColor(bordo) else { setColor(Color.WHITE); setStroke(dp(1), Color.parseColor("#D9CDCF")) }
        }
        setOnClickListener { tik() }
        layoutParams = LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, LinearLayout.LayoutParams.WRAP_CONTENT).apply { topMargin = dp(8) }
    }

    private fun adim(tamam: Boolean, baslik: String, aciklama: String, dugmeMetni: String?, tik: (() -> Unit)?) {
        val satir = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(dp(14), dp(12), dp(14), dp(12))
            background = GradientDrawable().apply {
                cornerRadius = dp(14).toFloat()
                setColor(if (tamam) Color.parseColor("#EEF8F2") else Color.parseColor("#FBF1F3"))
            }
            layoutParams = LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, LinearLayout.LayoutParams.WRAP_CONTENT).apply { topMargin = dp(10) }
        }
        satir.addView(yazi((if (tamam) "✓  " else "•  ") + baslik, 16f, true, if (tamam) Color.parseColor("#17663F") else bordo))
        if (!tamam || dugmeMetni == null) satir.addView(yazi(aciklama, 13.5f, false, Color.parseColor("#4A3C41")))
        if (!tamam && dugmeMetni != null && tik != null) satir.addView(dugme(dugmeMetni, true, tik))
        kutu.addView(satir)
    }

    private fun ciz() {
        kutu.removeAllViews()
        kutu.addView(yazi("OptiFlow Asistan", 26f, true))
        kutu.addView(yazi(if (ayar.bagli) "Bağlı mağaza: ${ayar.magazaAdi.ifEmpty { "#" + ayar.magaza }}" else "Mağaza telefonuna gelen ve açılamayan aramaları karşılar.", 14f, false, Color.parseColor("#7D6A70")))

        val servis = AsistanServisi.o
        val neden = servis?.hazirMi()
        val durumMetni = when {
            servis == null -> "Asistan çalışmıyor: aşağıdaki adımları tamamlayın."
            neden != null -> "Asistan beklemede: $neden"
            else -> "Asistan hazır. Arama ${ayar.bekleme} sn içinde açılmazsa karşılar."
        }
        kutu.addView(yazi(durumMetni, 16f, true, if (servis != null && neden == null) Color.parseColor("#17663F") else bordo).apply { setPadding(0, dp(12), 0, dp(4)) })

        adim(ayar.bagli, "OptiFlow'a bağlan", "OptiFlow › Telefon asistanı › \"Bağlama kodu al\". Karekodu bu telefonun kamerasıyla okutun ya da kodu buraya girin.", "Kodla bağla") { kodlaBagla() }
        val izinler = listOf(Manifest.permission.READ_PHONE_STATE, Manifest.permission.ANSWER_PHONE_CALLS, Manifest.permission.RECORD_AUDIO)
        adim(izinler.all { izinVar(it) }, "İzinler", "Telefon durumu, aramayı açma ve mikrofon izni.", "İzinleri ver") {
            val l = izinler.toMutableList()
            if (Build.VERSION.SDK_INT >= 33) l.add(Manifest.permission.POST_NOTIFICATIONS)
            requestPermissions(l.toTypedArray(), 1)
        }
        adim(rolVar(), "Arayan numarayı tanıma", "Asistanın arayanı tanıması için \"arama tanıma\" görevini OptiFlow Asistan'a verin. Aramalar engellenmez.", "Görevi ver") {
            startActivityForResult(getSystemService(RoleManager::class.java).createRequestRoleIntent(RoleManager.ROLE_CALL_SCREENING), 2)
        }
        adim(servis != null, "Ses erişimi (erişilebilirlik)", "Ayarlar › Erişilebilirlik › Yüklü uygulamalar › OptiFlow Asistan › Aç. \"Kısıtlanmış ayar\" uyarısı çıkarsa önce \"Uygulama bilgisi\"ne girip sağ üstteki ⋮ menüsünden \"Kısıtlanmış ayarlara izin ver\"i seçin.", "Erişilebilirlik ayarlarını aç") {
            startActivity(Intent(Settings.ACTION_ACCESSIBILITY_SETTINGS))
        }
        if (servis == null) kutu.addView(dugme("Uygulama bilgisi (kısıtlanmış ayar)", false) {
            startActivity(Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS, Uri.parse("package:$packageName")))
        })
        adim(pilSerbest(), "Pil kısıtlaması", "Telefon uygulamayı uyutmasın diye pil kısıtlamasını kaldırın.", "Kısıtlamayı kaldır") {
            startActivity(Intent(Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS, Uri.parse("package:$packageName")))
        }

        kutu.addView(yazi("Deneme ve ayarlar", 18f, true).apply { setPadding(0, dp(20), 0, dp(2)) })
        kutu.addView(yazi("Deneme konuşması arama olmadan çalışır: asistan konuşur, siz telefona tuşa basmadan sesle cevap verin.", 13.5f, false, Color.parseColor("#4A3C41")))
        kutu.addView(dugme("Deneme konuşması başlat") { deneme() })
        kutu.addView(dugme(if (ayar.yerelAcik) "Bu telefonda duraklat" else "Bu telefonda yeniden başlat", false) {
            ayar.yerelAcik = !ayar.yerelAcik
            ciz()
        })
        kutu.addView(dugme("Asistan sesi: " + (if (ayar.sesKanali == "medya") "medya kanalı" else "görüşme kanalı") + " (değiştir)", false) {
            ayar.sesKanali = if (ayar.sesKanali == "medya") "cagri" else "medya"
            Toast.makeText(this, "Arayan asistanı duyamıyorsa diğer kanalı deneyin.", Toast.LENGTH_LONG).show()
            ciz()
        })
        if (ayar.bagli) kutu.addView(dugme("Bağlantıyı kaldır", false) {
            AlertDialog.Builder(this).setMessage("Bu telefonun OptiFlow bağlantısı kaldırılsın mı? Asistan arama açmaz.")
                .setPositiveButton("Kaldır") { _, _ -> ayar.baglantiyiKaldir(); ciz() }
                .setNegativeButton("Vazgeç", null).show()
        })

        kutu.addView(yazi("Son olaylar", 18f, true).apply { setPadding(0, dp(20), 0, dp(2)) })
        val olaylar = synchronized(AsistanServisi.olaylar) { AsistanServisi.olaylar.toList() }
        kutu.addView(yazi(if (olaylar.isEmpty()) "Henüz olay yok." else olaylar.joinToString("\n"), 12.5f, false, Color.parseColor("#4A3C41")).apply {
            typeface = Typeface.MONOSPACE
        })
        kutu.addView(dugme("Yenile", false) { ciz() })
    }

    private fun deneme() {
        val s = AsistanServisi.o
        if (s == null) {
            Toast.makeText(this, "Önce \"Ses erişimi\"ni açın.", Toast.LENGTH_LONG).show()
            return
        }
        if (!ayar.bagli) {
            Toast.makeText(this, "Önce OptiFlow'a bağlanın.", Toast.LENGTH_LONG).show()
            return
        }
        val giris = EditText(this).apply {
            hint = "Müşteri telefonu (boş: kayıtsız arayan)"
            inputType = InputType.TYPE_CLASS_PHONE
        }
        AlertDialog.Builder(this).setTitle("Deneme konuşması").setMessage("Hangi numaradan aranıyormuş gibi olsun?")
            .setView(giris)
            .setPositiveButton("Başlat") { _, _ ->
                s.gorusmeyiBaslat(true, giris.text.toString())
                AlertDialog.Builder(this).setMessage("Asistan konuşuyor. Bitince bu pencereyi kapatın.")
                    .setPositiveButton("Durdur") { _, _ -> s.denemeyiDurdur(); ciz() }
                    .setOnDismissListener { ana.postDelayed({ ciz() }, 500) }
                    .show()
            }
            .setNegativeButton("Vazgeç", null).show()
    }

    private fun kodlaBagla(sunucu: String? = null, magaza: String? = null, kod: String? = null) {
        val kap = LinearLayout(this).apply { orientation = LinearLayout.VERTICAL; setPadding(dp(18), dp(6), dp(18), 0) }
        val sunucuG = EditText(this).apply { setText(sunucu ?: ayar.sunucu); hint = "Sunucu adresi"; inputType = InputType.TYPE_TEXT_VARIATION_URI }
        val magazaG = EditText(this).apply { setText(magaza ?: ""); hint = "Mağaza no"; inputType = InputType.TYPE_CLASS_NUMBER }
        val kodG = EditText(this).apply { setText(kod ?: ""); hint = "6 haneli kod"; inputType = InputType.TYPE_CLASS_NUMBER }
        kap.addView(sunucuG); kap.addView(magazaG); kap.addView(kodG)
        AlertDialog.Builder(this).setTitle("OptiFlow'a bağlan").setView(kap)
            .setPositiveButton("Bağlan") { _, _ -> bagla(sunucuG.text.toString(), magazaG.text.toString().toIntOrNull() ?: 0, kodG.text.toString().trim()) }
            .setNegativeButton("Vazgeç", null).show()
    }

    private fun bagla(sunucu: String, magaza: Int, kod: String) {
        if (!sunucu.startsWith("https://") && !sunucu.startsWith("http://127.") && !sunucu.startsWith("http://10.")) {
            Toast.makeText(this, "Sunucu adresi https:// ile başlamalı.", Toast.LENGTH_LONG).show()
            return
        }
        Thread {
            val c = Api.istek(sunucu, magaza, "", "bagla", mapOf("kod" to kod, "cihaz" to (Build.MANUFACTURER + " " + Build.MODEL).trim()))
            ana.post {
                if (c.optBoolean("ok")) {
                    ayar.sunucu = sunucu
                    ayar.magaza = magaza
                    ayar.anahtar = c.optString("anahtar")
                    ayar.magazaAdi = c.optString("magaza")
                    Toast.makeText(this, "Bağlandı: " + ayar.magazaAdi, Toast.LENGTH_LONG).show()
                    AsistanServisi.o?.yenidenBaslat()
                } else {
                    AlertDialog.Builder(this).setTitle("Bağlanamadı").setMessage(c.optString("hata", "Bilinmeyen hata")).setPositiveButton("Tamam", null).show()
                }
                ciz()
            }
        }.start()
    }

    /** optiflow-asistan://bagla?u=…&m=…&k=… (OptiFlow'daki karekoddan) */
    private fun baglantiLinki(i: Intent?) {
        val v = i?.data ?: return
        if (v.scheme != "optiflow-asistan" || v.host != "bagla") return
        val u = v.getQueryParameter("u") ?: return
        val m = v.getQueryParameter("m") ?: return
        val k = v.getQueryParameter("k") ?: return
        setIntent(Intent())
        AlertDialog.Builder(this).setTitle("OptiFlow'a bağlansın mı?")
            .setMessage("Bu telefon mağaza no $m için telefon asistanı olarak bağlanacak.\n$u")
            .setPositiveButton("Bağlan") { _, _ -> bagla(u, m.toIntOrNull() ?: 0, k) }
            .setNegativeButton("Vazgeç", null).show()
    }

    @Deprecated("Deprecated in Java")
    override fun onActivityResult(requestCode: Int, resultCode: Int, data: Intent?) {
        super.onActivityResult(requestCode, resultCode, data)
        ciz()
    }

    override fun onRequestPermissionsResult(requestCode: Int, permissions: Array<out String>, grantResults: IntArray) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults)
        AsistanServisi.o?.yenidenBaslat()
        ciz()
    }
}
