package tr.com.optiflow.asistan

import android.content.Context
import android.telecom.Call
import android.telecom.CallScreeningService

/**
 * "Arama tanıma" rolü: gelen aramanın numarasını öğrenmek için. Aramayı ENGELLEMEZ, sessize almaz;
 * yalnızca numarayı kaydeder ve aramayı olduğu gibi bırakır.
 */
class AramaTanima : CallScreeningService() {
    override fun onScreenCall(details: Call.Details) {
        if (details.callDirection == Call.Details.DIRECTION_INCOMING) {
            AramaAlici.numaraKaydet(this, details.handle?.schemeSpecificPart ?: "")
        }
        respondToCall(details, CallResponse.Builder().build())
    }
}
