package app.payguard.mobile

import android.app.AlarmManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent

object Watchdog {
    private const val INTERVAL_MS = 15 * 60 * 1000L

    fun schedule(context: Context) {
        val app = context.applicationContext
        val intent = Intent(app, WatchdogReceiver::class.java)
        val pending = PendingIntent.getBroadcast(
            app,
            0,
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )
        val alarm = app.getSystemService(AlarmManager::class.java)
        alarm.setAndAllowWhileIdle(
            AlarmManager.RTC_WAKEUP,
            System.currentTimeMillis() + INTERVAL_MS,
            pending,
        )
    }
}
