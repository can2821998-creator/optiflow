package tr.com.optiflow.asistan

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.content.Context
import android.graphics.Color
import android.graphics.PixelFormat
import android.graphics.Typeface
import android.graphics.drawable.GradientDrawable
import android.os.Handler
import android.os.Looper
import android.provider.Settings
import android.view.Gravity
import android.view.View
import android.view.WindowManager
import android.widget.LinearLayout
import android.widget.TextView
import org.json.JSONObject

/** Telefon çalarken ekranın üstünde arayan kartı (+ aynı bilgiyle bildirim; kilit ekranında da görünür). */
object Kart {
    private var gorunum: View? = null
    private val ana = Handler(Looper.getMainLooper())
    private const val KANAL = "arayan"
    private const val BILDIRIM = 7

    fun goster(ctx: Context, b: JSONObject) {
        kaldir(ctx)
        val baslik = b.optString("baslik", "Arayan")
        val numara = b.optString("numara", "")
        val satirlar = b.optJSONArray("satirlar")?.let { a -> (0 until a.length()).map { a.optString(it) } } ?: emptyList()
        bildirim(ctx, baslik, numara, satirlar)
        if (!Settings.canDrawOverlays(ctx)) return
        val d = ctx.resources.displayMetrics.density
        fun dp(v: Int) = (v * d).toInt()
        val kutu = LinearLayout(ctx).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(dp(18), dp(14), dp(18), dp(14))
            background = GradientDrawable().apply {
                cornerRadius = dp(20).toFloat()
                colors = intArrayOf(Color.parseColor("#160B0F"), Color.parseColor("#5C0F1F"))
                orientation = GradientDrawable.Orientation.TL_BR
            }
            elevation = dp(12).toFloat()
        }
        kutu.addView(TextView(ctx).apply {
            text = "OptiFlow · " + numara
            setTextColor(Color.parseColor("#FF95A6")); textSize = 12f
        })
        kutu.addView(TextView(ctx).apply {
            text = baslik
            setTextColor(Color.WHITE); textSize = 22f; setTypeface(typeface, Typeface.BOLD)
            setPadding(0, dp(2), 0, dp(6))
        })
        for (s in satirlar) {
            kutu.addView(TextView(ctx).apply {
                text = "• $s"
                setTextColor(Color.parseColor("#F4E8EA")); textSize = 15f
                setPadding(0, dp(3), 0, dp(3))
            })
        }
        kutu.addView(TextView(ctx).apply {
            text = "Kapatmak için dokunun"
            setTextColor(Color.parseColor("#B8A3A8")); textSize = 11f
            gravity = Gravity.END
            setPadding(0, dp(6), 0, 0)
        })
        kutu.setOnClickListener { kaldir(ctx) }
        val lp = WindowManager.LayoutParams(
            WindowManager.LayoutParams.MATCH_PARENT,
            WindowManager.LayoutParams.WRAP_CONTENT,
            WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY,
            WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE or WindowManager.LayoutParams.FLAG_LAYOUT_IN_SCREEN,
            PixelFormat.TRANSLUCENT,
        ).apply {
            gravity = Gravity.TOP
            y = dp(56)
            horizontalMargin = 0.03f
        }
        try {
            ctx.getSystemService(WindowManager::class.java).addView(kutu, lp)
            gorunum = kutu
            ana.postDelayed({ kaldir(ctx) }, 90_000)
        } catch (e: Exception) {
            Kayit.yaz(ctx, "Kart gösterilemedi: ${e.message}")
        }
    }

    fun kaldir(ctx: Context) {
        gorunum?.let { v -> try { ctx.getSystemService(WindowManager::class.java).removeView(v) } catch (_: Exception) {} }
        gorunum = null
        try { ctx.getSystemService(NotificationManager::class.java).cancel(BILDIRIM) } catch (_: Exception) {}
    }

    private fun bildirim(ctx: Context, baslik: String, numara: String, satirlar: List<String>) {
        try {
            val nm = ctx.getSystemService(NotificationManager::class.java)
            if (!nm.areNotificationsEnabled()) return
            nm.createNotificationChannel(NotificationChannel(KANAL, "Arayan bilgisi", NotificationManager.IMPORTANCE_HIGH).apply {
                description = "Telefon çalarken arayanın sipariş durumu"
                setSound(null, null)
                enableVibration(false)
            })
            val n = Notification.Builder(ctx, KANAL)
                .setSmallIcon(R.drawable.ic_on)
                .setContentTitle(baslik + if (numara.isNotEmpty()) " · $numara" else "")
                .setContentText(satirlar.firstOrNull() ?: "")
                .setStyle(Notification.BigTextStyle().bigText(satirlar.joinToString("\n")))
                .setCategory(Notification.CATEGORY_STATUS)
                .setVisibility(Notification.VISIBILITY_PRIVATE)
                .setTimeoutAfter(120_000)
                .setAutoCancel(true)
                .build()
            nm.notify(BILDIRIM, n)
        } catch (_: Exception) {}
    }
}
