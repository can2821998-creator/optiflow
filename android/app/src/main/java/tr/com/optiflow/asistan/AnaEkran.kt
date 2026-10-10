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
import org.json.JSONObject
import android.provider.Settings
import android.text.InputType
import android.widget.Button
import android.widget.EditText
import android.widget.LinearLayout
import android.widget.ScrollView
import android.widget.TextView
import android.widget.Toast

/** Kurulum ve durum ekranı. Asıl iş AramaAlici / AramaTanima / Kart'ta; bu ekran kapalıyken de çalışır. */
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
        ciz()
        ayarlariYenile()
    }

    /** OptiFlow'daki ayarlar (açık/kapalı) — uygulama her açıldığında */
    private fun ayarlariYenile() {
        if (!ayar.bagli) return
        Thread {
            val c = Api.istek(ayar, "ayar")
            ana.post {
                if (c.optBoolean("ok")) {
                    ayar.sunucuAcik = c.optBoolean("acik", true)
                    ayar.magazaAdi = c.optString("magaza", ayar.magazaAdi)
                } else if (c.optBoolean("yeniden_bagla")) {
                    ayar.baglantiyiKaldir()
                    Kayit.yaz(this, "OptiFlow bağlantıyı kaldırmış: yeniden bağlayın", false)
                }
                ciz()
            }
        }.start()
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
        kutu.addView(yazi(if (ayar.bagli) "Bağlı mağaza: ${ayar.magazaAdi.ifEmpty { "#" + ayar.magaza }}" else "Telefon çalarken arayanın sipariş durumunu gösterir; açılamayan aramaya SMS gönderir.", 14f, false, Color.parseColor("#7D6A70")))

        val izinler = mutableListOf(Manifest.permission.READ_PHONE_STATE, Manifest.permission.SEND_SMS)
        if (Build.VERSION.SDK_INT >= 33) izinler.add(Manifest.permission.POST_NOTIFICATIONS)
        val hepsi = ayar.bagli && izinler.all { izinVar(it) } && rolVar() && Settings.canDrawOverlays(this)
        val neden = ayar.beklemeNedeni()
        val durumMetni = when {
            !hepsi -> "Kurulum tamamlanmadı: aşağıdaki adımları yapın."
            neden != null -> "Beklemede: $neden"
            else -> "Hazır. Telefon çalınca kart çıkar; açılamayan aramaya SMS gider."
        }
        kutu.addView(yazi(durumMetni, 16f, true, if (hepsi && neden == null) Color.parseColor("#17663F") else bordo).apply { setPadding(0, dp(12), 0, dp(4)) })

        adim(ayar.bagli, "OptiFlow'a bağlan", "OptiFlow › Telefon asistanı › \"Bağlama kodu al\". Karekodu bu telefonun kamerasıyla okutun ya da kodu buraya girin.", "Kodla bağla") { kodlaBagla() }
        adim(izinler.all { izinVar(it) }, "İzinler", "Telefon durumu (arama geldi / bitti), SMS gönderme ve bildirim izni.", "İzinleri ver") {
            requestPermissions(izinler.toTypedArray(), 1)
        }
        adim(rolVar(), "Arayan numarayı tanıma", "Arayanı tanımak için \"arama tanıma\" görevini OptiFlow Asistan'a verin. Aramalar engellenmez.", "Görevi ver") {
            startActivityForResult(getSystemService(RoleManager::class.java).createRequestRoleIntent(RoleManager.ROLE_CALL_SCREENING), 2)
        }
        adim(Settings.canDrawOverlays(this), "Ekranda kart gösterme", "Telefon çalarken arayan kartı ekranın üstünde çıksın diye \"Diğer uygulamaların üzerinde göster\" iznini açın.", "İzni aç") {
            startActivity(Intent(Settings.ACTION_MANAGE_OVERLAY_PERMISSION, Uri.parse("package:$packageName")))
        }
        adim(pilSerbest(), "Pil kısıtlaması", "Telefon uygulamayı uyutmasın diye pil kısıtlamasını kaldırın.", "Kısıtlamayı kaldır") {
            startActivity(Intent(Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS, Uri.parse("package:$packageName")))
        }
        val uretici = Build.MANUFACTURER.lowercase()
        if (uretici in listOf("xiaomi", "redmi", "poco", "tecno", "infinix", "itel", "oppo", "realme", "vivo", "huawei", "honor", "oneplus")) {
            kutu.addView(yazi("${Build.MANUFACTURER} telefonlarda ayrıca \"Otomatik başlat\"ı açın ve pil ayarını \"Kısıtlama yok\" yapın.", 13.5f, false, bordo).apply { setPadding(0, dp(10), 0, 0) })
            kutu.addView(dugme("Otomatik başlatma ayarı", false) { otomatikBaslat() })
        }

        kutu.addView(yazi("Deneme ve ayarlar", 18f, true).apply { setPadding(0, dp(20), 0, dp(2)) })
        kutu.addView(dugme("Kartı dene (bir numara için)") { kartDene() })
        kutu.addView(dugme(if (ayar.yerelAcik) "Bu telefonda duraklat" else "Bu telefonda yeniden başlat", false) {
            ayar.yerelAcik = !ayar.yerelAcik
            ciz()
        })
        if (ayar.bagli) kutu.addView(dugme("Bağlantıyı kaldır", false) {
            AlertDialog.Builder(this).setMessage("Bu telefonun OptiFlow bağlantısı kaldırılsın mı?")
                .setPositiveButton("Kaldır") { _, _ -> ayar.baglantiyiKaldir(); ciz() }
                .setNegativeButton("Vazgeç", null).show()
        })

        kutu.addView(yazi("Son olaylar", 18f, true).apply { setPadding(0, dp(20), 0, dp(2)) })
        val olaylar = Kayit.liste(this)
        kutu.addView(yazi(if (olaylar.isEmpty()) "Henüz olay yok." else olaylar.joinToString("\n"), 12.5f, false, Color.parseColor("#4A3C41")).apply {
            typeface = Typeface.MONOSPACE
        })
        kutu.addView(dugme("Yenile", false) { ciz() })
    }

    private fun kartDene() {
        if (!ayar.bagli) {
            Toast.makeText(this, "Önce OptiFlow'a bağlanın.", Toast.LENGTH_LONG).show()
            return
        }
        val giris = EditText(this).apply {
            hint = "Müşteri telefonu"
            inputType = InputType.TYPE_CLASS_PHONE
        }
        AlertDialog.Builder(this).setTitle("Kartı dene").setMessage("Bu numara arıyormuş gibi kart gösterilir (SMS gitmez).")
            .setView(giris)
            .setPositiveButton("Göster") { _, _ ->
                val num = giris.text.toString()
                Thread {
                    val b: JSONObject = Api.istek(ayar, "bilgi", mapOf("numara" to num))
                    ana.post {
                        if (b.optBoolean("ok")) {
                            Kart.goster(applicationContext, b)
                            if (!Settings.canDrawOverlays(this)) Toast.makeText(this, "Kart için \"Ekranda kart gösterme\" iznini açın; şimdilik bildirim olarak gösterildi.", Toast.LENGTH_LONG).show()
                        } else {
                            Toast.makeText(this, b.optString("hata", "Bilgi alınamadı"), Toast.LENGTH_LONG).show()
                        }
                    }
                }.start()
            }
            .setNegativeButton("Vazgeç", null).show()
    }

    /** Üreticinin "otomatik başlat" ekranı (yoksa uygulama bilgisi). */
    private fun otomatikBaslat() {
        val adaylar = listOf(
            Intent().setClassName("com.miui.securitycenter", "com.miui.permcenter.autostart.AutoStartManagementActivity"),
            Intent().setClassName("com.transsion.phonemaster", "com.cyin.himgr.autostart.AutoStartActivity"),
            Intent().setClassName("com.coloros.safecenter", "com.coloros.safecenter.permission.startup.StartupAppListActivity"),
            Intent().setClassName("com.vivo.permissionmanager", "com.vivo.permissionmanager.activity.BgStartUpManagerActivity"),
            Intent().setClassName("com.huawei.systemmanager", "com.huawei.systemmanager.startupmgr.ui.StartupNormalAppListActivity"),
        )
        for (i in adaylar) {
            try {
                startActivity(i)
                return
            } catch (_: Exception) {}
        }
        startActivity(Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS, Uri.parse("package:$packageName")))
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
        ciz()
    }
}
