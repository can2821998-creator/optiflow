package tr.com.optiflow.asistan

import android.os.SystemClock
import android.telecom.Call
import android.telecom.CallScreeningService

/**
 * "Arama tanıma" rolü: gelen aramanın numarasını öğrenmek için. Aramayı ENGELLEMEZ, sessize almaz;
 * yalnızca numarayı asistana iletir ve aramayı olduğu gibi bırakır.
 */
class AramaTanima : CallScreeningService() {
    override fun onScreenCall(details: Call.Details) {
        if (details.callDirection == Call.Details.DIRECTION_INCOMING) {
            AsistanServisi.sonNumara = details.handle?.schemeSpecificPart ?: ""
            AsistanServisi.sonNumaraZaman = SystemClock.elapsedRealtime()
        }
        respondToCall(details, CallResponse.Builder().build())
    }
}
