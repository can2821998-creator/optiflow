package tr.com.optiflow.asistan

import kotlin.math.cos
import kotlin.math.PI

/**
 * Tuş sesi (DTMF) çözücü: arayanın bastığı tuşlar hoparlörden mikrofona gelir; 20 ms'lik bloklarda
 * Goertzel ile 4 alçak + 4 yüksek frekansa bakılır. İki güçlü saf bileşen 2 blok üst üste görülünce
 * tuş bir kez yayılır (konuşma bu koşulu nadiren sağlar).
 */
class DtmfCozucu(private val oran: Int) {
    private val alcak = doubleArrayOf(697.0, 770.0, 852.0, 941.0)
    private val yuksek = doubleArrayOf(1209.0, 1336.0, 1477.0, 1633.0)
    private val tablo = arrayOf(
        charArrayOf('1', '2', '3', 'A'),
        charArrayOf('4', '5', '6', 'B'),
        charArrayOf('7', '8', '9', 'C'),
        charArrayOf('*', '0', '#', 'D'),
    )
    private var aday: Char? = null
    private var sayac = 0
    private var yayildi = false

    private fun goertzel(s: ShortArray, n: Int, f: Double): Double {
        val k = 2.0 * cos(2.0 * PI * f / oran)
        var s1 = 0.0
        var s2 = 0.0
        for (i in 0 until n) {
            val s0 = s[i] / 32768.0 + k * s1 - s2
            s2 = s1
            s1 = s0
        }
        return s1 * s1 + s2 * s2 - k * s1 * s2
    }

    fun isle(s: ShortArray, n: Int): Char? {
        var enerji = 0.0
        for (i in 0 until n) {
            val v = s[i] / 32768.0
            enerji += v * v
        }
        if (n <= 0 || enerji / n < 1e-5) {   // sessizlik
            aday = null; sayac = 0; yayildi = false
            return null
        }
        val olcek = enerji * n / 2.0
        val pl = alcak.map { goertzel(s, n, it) / olcek }
        val ph = yuksek.map { goertzel(s, n, it) / olcek }
        val il = pl.indices.maxByOrNull { pl[it] } ?: 0
        val ih = ph.indices.maxByOrNull { ph[it] } ?: 0
        val ikinciL = pl.filterIndexed { i, _ -> i != il }.maxOrNull() ?: 0.0
        val ikinciH = ph.filterIndexed { i, _ -> i != ih }.maxOrNull() ?: 0.0
        val gecerli = pl[il] > 0.15 && ph[ih] > 0.15 &&
            ikinciL < pl[il] * 0.3 && ikinciH < ph[ih] * 0.3 &&
            pl[il] / ph[ih] in 0.125..8.0
        if (!gecerli) {
            aday = null; sayac = 0; yayildi = false
            return null
        }
        val t = tablo[il][ih]
        if (t == aday) {
            sayac++
        } else {
            aday = t; sayac = 1; yayildi = false
        }
        if (sayac >= 2 && !yayildi) {
            yayildi = true
            return t
        }
        return null
    }
}
