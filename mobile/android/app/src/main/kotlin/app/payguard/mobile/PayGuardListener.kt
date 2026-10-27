package app.payguard.mobile

import android.app.Notification
import android.content.ComponentName
import android.service.notification.NotificationListenerService
import android.service.notification.StatusBarNotification
import java.util.UUID

class PayGuardListener : NotificationListenerService() {

    override fun onNotificationPosted(sbn: StatusBarNotification?) {
        if (sbn == null) {
            return
        }
        if (sbn.packageName !in watchedPackages()) {
            return
        }
        val extras = sbn.notification?.extras ?: return
        val title = extras.getCharSequence(Notification.EXTRA_TITLE)?.toString()?.take(500)
        val text = extras.getCharSequence(Notification.EXTRA_TEXT)?.toString()?.take(2000)
        val bigText = extras.getCharSequence(Notification.EXTRA_BIG_TEXT)?.toString()?.take(5000)
        val postedMs = if (sbn.postTime > 0) sbn.postTime else System.currentTimeMillis()

        Thread {
            try {
                val app = applicationContext
                QueueDb(app).enqueue(
                    QueuedItem(
                        id = 0,
                        key = UUID.randomUUID().toString(),
                        pkg = sbn.packageName,
                        title = title,
                        text = text,
                        bigText = bigText,
                        postedMs = postedMs,
                    ),
                )
                val result = PaymentSender.sendPending(app)
                if (result.pending > 0) {
                    SendWorker.enqueue(app)
                }
            } catch (_: Exception) {
            }
        }.start()
    }

    override fun onListenerDisconnected() {
        try {
            NotificationListenerService.requestRebind(
                ComponentName(this, PayGuardListener::class.java),
            )
        } catch (_: Exception) {
        }
    }

    private fun watchedPackages(): Set<String> {
        return resources.getStringArray(R.array.watched_packages).toSet()
    }
}
