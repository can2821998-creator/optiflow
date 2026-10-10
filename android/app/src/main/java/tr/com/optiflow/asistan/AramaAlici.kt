package tr.com.optiflow.asistan

import android.Manifest
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Handler
import android.os.Looper
import android.telephony.SmsManager
import android.telephony.TelephonyManager

/**
 * Telefonun arama durumu (sistem yayını; uygulama kapalıyken de gelir):
 *  ÇALIYOR → OptiFlow'dan arayanın bilgisi alınır, ekranın üstünde kart gösterilir.
 *  AÇILDI  → kart kapanır.
 *  BİTTİ   → hiç açılmadıysa OptiFlow'a "cevapsız" bildirilir (geri aranacaklar) ve
 *            OptiFlow izin verirse arayana mağaza hattından SMS gönderilir.
 */
class AramaAlici : BroadcastReceiver() {

    companion object {
        private const val TERCIH = "arama"

        fun numaraKaydet(ctx: Context, numara: String) {
            ctx.applicationContext.getSharedPreferences(TERCIH, Context.MODE_PRIVATE).edit()
                .putString("numara", numara).putLong("numara_zaman", System.currentTimeMillis()).apply()
        }

        fun numara(ctx: Context): String {
            val p = ctx.applicationContext.getSharedPreferences(TERCIH, Context.MODE_PRIVATE)
            return if (System.currentTimeMillis() - p.getLong("numara_zaman", 0) < 5 * 60_000L) p.getString("numara", "") ?: "" else ""
        }
    }

    private val ana = Handler(Looper.getMainLooper())

    override fun onReceive(ctx: Context, intent: Intent) {
        if (intent.action != TelephonyManager.ACTION_PHONE_STATE_CHANGED) return
        val durum = intent.getStringExtra(TelephonyManager.EXTRA_STATE) ?: return
        val uyg = ctx.applicationContext
        val p = uyg.getSharedPreferences(TERCIH, Context.MODE_PRIVATE)
        val onceki = p.getString("durum", "bos") ?: "bos"
        val ayar = Ayarlar(uyg)

        when (durum) {
            TelephonyManager.EXTRA_STATE_RINGING -> {
                if (onceki == "caliyor") return          // bazı telefonlar yayını iki kez gönderir
                p.edit().putString("durum", "caliyor").apply()
                val neden = ayar.beklemeNedeni()
                if (neden != null) {
                    Kayit.yaz(uyg, "Arama geldi; kart gösterilmedi: $neden", false)
                    return
                }
                val bekle = goAsync()
                Thread {
                    try {
                        Thread.sleep(500)               // arama tanıma numarayı yazsın
                        val num = numara(uyg)
                        val b = Api.istek(ayar, "bilgi", mapOf("numara" to num))
                        if (!b.optBoolean("ok")) {
                            Kayit.yaz(uyg, "Arayan bilgisi alınamadı: " + b.optString("hata"))
                        } else {
                            ana.post { if (p.getString("durum", "") == "caliyor") Kart.goster(uyg, b) }
                            Kayit.yaz(uyg, "Arama: " + (if (num.isEmpty()) "numara gizli / tanınamadı" else if (b.optBoolean("tanindi")) "kayıtlı müşteri" else "kayıtlı olmayan numara"), false)
                        }
                    } catch (e: Exception) {
                        Kayit.yaz(uyg, "Kart hatası: ${e.message}")
                    } finally {
                        bekle.finish()
                    }
                }.start()
            }
            TelephonyManager.EXTRA_STATE_OFFHOOK -> {
                p.edit().putString("durum", if (onceki == "caliyor") "acildi" else "giden").apply()
                ana.post { Kart.kaldir(uyg) }
            }
            TelephonyManager.EXTRA_STATE_IDLE -> {
                p.edit().putString("durum", "bos").apply()
                ana.post { Kart.kaldir(uyg) }
                if (onceki != "caliyor" || ayar.beklemeNedeni() != null) return
                val bekle = goAsync()
                Thread {
                    try {
                        cevapsiz(uyg, ayar, numara(uyg))
                    } catch (e: Exception) {
                        Kayit.yaz(uyg, "Cevapsız arama işlenemedi: ${e.message}")
                    } finally {
                        bekle.finish()
                    }
                }.start()
            }
        }
    }

    private fun cevapsiz(ctx: Context, ayar: Ayarlar, num: String) {
        val c = Api.istek(ayar, "cevapsiz", mapOf("numara" to num))
        if (!c.optBoolean("ok")) {
            Kayit.yaz(ctx, "Cevapsız arama OptiFlow'a yazılamadı: " + c.optString("hata"))
            return
        }
        if (!c.optBoolean("gonder")) {
            Kayit.yaz(ctx, "Cevapsız arama kaydedildi; SMS yok (" + c.optString("neden") + ")", false)
            return
        }
        val kayit = c.optInt("kayit").toString()
        if (ctx.checkSelfPermission(Manifest.permission.SEND_SMS) != PackageManager.PERMISSION_GRANTED) {
            Api.istek(ayar, "mesaj_sonucu", mapOf("kayit" to kayit, "gitti" to "0", "hata" to "SMS izni yok"))
            Kayit.yaz(ctx, "SMS gönderilemedi: SMS izni verilmemiş")
            return
        }
        try {
            val sms = ctx.getSystemService(SmsManager::class.java)
            val parcalar = sms.divideMessage(c.optString("metin"))
            sms.sendMultipartTextMessage(num, null, parcalar, null, null)
            Api.istek(ayar, "mesaj_sonucu", mapOf("kayit" to kayit, "gitti" to "1"))
            Kayit.yaz(ctx, "Cevapsız aramaya SMS gönderildi (${parcalar.size} parça)")
        } catch (e: Exception) {
            Api.istek(ayar, "mesaj_sonucu", mapOf("kayit" to kayit, "gitti" to "0", "hata" to (e.message ?: "hata")))
            Kayit.yaz(ctx, "SMS gönderilemedi: ${e.message}")
        }
    }
}
