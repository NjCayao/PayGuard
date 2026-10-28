package app.payguard.mobile

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent

class BootReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action != Intent.ACTION_BOOT_COMPLETED &&
            intent.action != Intent.ACTION_MY_PACKAGE_REPLACED
        ) {
            return
        }
        val app = context.applicationContext
        Watchdog.schedule(app)
        SendWorker.enqueue(app)
        try {
            VoiceAnnouncer.warmUp(app)
        } catch (_: Exception) {
        }
    }
}
