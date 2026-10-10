package tr.com.optiflow.asistan

import android.content.Context

/** Uygulama ayarları (cihazda). Anahtar yalnızca bu telefonda durur; sunucuda özeti tutulur. */
class Ayarlar(ctx: Context) {
    private val p = ctx.applicationContext.getSharedPreferences("asistan", Context.MODE_PRIVATE)

    var sunucu: String
        get() = p.getString("sunucu", "https://optiflow.com.tr") ?: "https://optiflow.com.tr"
        set(v) = p.edit().putString("sunucu", v.trim().trimEnd('/')).apply()
    var magaza: Int
        get() = p.getInt("magaza", 0)
        set(v) = p.edit().putInt("magaza", v).apply()
    var anahtar: String
        get() = p.getString("anahtar", "") ?: ""
        set(v) = p.edit().putString("anahtar", v).apply()
    var magazaAdi: String
        get() = p.getString("magaza_adi", "") ?: ""
        set(v) = p.edit().putString("magaza_adi", v).apply()
    /** OptiFlow'daki "Asistan açık" ayarı (sunucudan gelir) */
    var sunucuAcik: Boolean
        get() = p.getBoolean("sunucu_acik", true)
        set(v) = p.edit().putBoolean("sunucu_acik", v).apply()
    /** Bu telefonda geçici olarak duraklatma */
    var yerelAcik: Boolean
        get() = p.getBoolean("yerel_acik", true)
        set(v) = p.edit().putBoolean("yerel_acik", v).apply()
    val bagli: Boolean get() = magaza > 0 && anahtar.length == 40

    /** Kart / SMS çalışmıyorsa nedeni (yoksa null) */
    fun beklemeNedeni(): String? = when {
        !bagli -> "OptiFlow'a bağlı değil"
        !yerelAcik -> "bu telefonda duraklatıldı"
        !sunucuAcik -> "OptiFlow'da kapalı"
        else -> null
    }

    fun baglantiyiKaldir() {
        p.edit().remove("anahtar").remove("magaza").remove("magaza_adi").apply()
    }
}
