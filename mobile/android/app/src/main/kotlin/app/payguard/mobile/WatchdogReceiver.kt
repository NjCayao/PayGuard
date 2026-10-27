package app.payguard.mobile

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent

class WatchdogReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        val pending = goAsync()
        Thread {
            try {
                val app = context.applicationContext
                val result = PaymentSender.sendPending(app)
                PaymentSender.sendHeartbeat(app)
                if (result.pending > 0) {
                    SendWorker.enqueue(app)
                }
            } catch (_: Exception) {
            } finally {
                Watchdog.schedule(context)
                pending.finish()
            }
        }.start()
    }
}
