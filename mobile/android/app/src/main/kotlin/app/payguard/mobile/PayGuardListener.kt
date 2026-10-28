package app.payguard.mobile

import android.app.Notification
import android.content.ComponentName
import android.content.Context
import android.service.notification.NotificationListenerService
import android.service.notification.StatusBarNotification

class PayGuardListener : NotificationListenerService() {

    override fun onListenerConnected() {
        super.onListenerConnected()
        try {
            VoiceAnnouncer.warmUp(this)
        } catch (_: Exception) {
        }
    }

    override fun onNotificationPosted(sbn: StatusBarNotification?) {
        if (sbn == null) {
            return
        }
        if (sbn.packageName !in watchedPackages()) {
            return
        }
        val posted = sbn.notification ?: return
        val extras = posted.extras ?: return
        val title = extras.getCharSequence(Notification.EXTRA_TITLE)?.toString()?.take(500)
        val text = extras.getCharSequence(Notification.EXTRA_TEXT)?.toString()?.take(2000)
        val bigText = extras.getCharSequence(Notification.EXTRA_BIG_TEXT)?.toString()?.take(5000)
        val postedMs = if (sbn.postTime > 0) sbn.postTime else System.currentTimeMillis()

        Thread {
            try {
                val app = applicationContext
                announce(app, title, text, bigText)
                QueueDb(app).enqueue(
                    QueuedItem(
                        id = 0,
                        key = NotificationKey.stableKey(
                            pkg = sbn.packageName,
                            sbnKey = sbn.key,
                            notificationWhen = posted.`when`,
                            postTimeMs = postedMs,
                            title = title,
                            text = text,
                            bigText = bigText,
                        ),
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

    private fun announce(app: Context, title: String?, text: String?, bigText: String?) {
        try {
            if (!VoicePrefs(app).isEnabled()) {
                return
            }
            val joined = TextNormalizer.join(title, text, bigText)
            if (joined.isBlank()) {
                return
            }
            val window = app.resources.getInteger(R.integer.voice_duplicate_minutes)
            if (!VoiceStore(app).shouldAnnounce(VoiceStore.voiceKey(joined), window)) {
                return
            }
            val payment = voiceParser(app).parse(joined) ?: return
            VoiceAnnouncer.announce(app, VoicePhrase.build(payment))
        } catch (_: Exception) {
        }
    }

    private fun voiceParser(app: Context): YapeVoiceParser {
        val res = app.resources
        return YapeVoiceParser(
            ignorePatterns = res.getStringArray(R.array.voice_ignore_patterns).toList(),
            receivedMarkers = res.getStringArray(R.array.voice_received_markers).toList(),
            paymentPatterns = res.getStringArray(R.array.voice_payment_patterns).toList(),
            amountPatterns = res.getStringArray(R.array.voice_amount_patterns).toList(),
        )
    }

    private fun watchedPackages(): Set<String> {
        return resources.getStringArray(R.array.watched_packages).toSet()
    }
}
