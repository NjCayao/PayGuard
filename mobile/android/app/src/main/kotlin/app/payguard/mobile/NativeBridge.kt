package app.payguard.mobile

import android.app.Activity
import android.content.ComponentName
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.os.PowerManager
import android.provider.Settings
import io.flutter.plugin.common.MethodCall
import io.flutter.plugin.common.MethodChannel

object NativeBridge {

    fun handle(call: MethodCall, result: MethodChannel.Result, activity: Activity) {
        val app = activity.applicationContext
        try {
            when (call.method) {
                "secureGet" -> {
                    val key = call.argument<String>("key") ?: ""
                    result.success(SecureStore(app).getString(key))
                }
                "secureSet" -> {
                    val key = call.argument<String>("key") ?: ""
                    val value = call.argument<String>("value") ?: ""
                    SecureStore(app).setString(key, value)
                    result.success(null)
                }
                "secureClear" -> {
                    SecureStore(app).clear()
                    result.success(null)
                }
                "deviceBrand" -> result.success(Build.BRAND ?: "")
                "deviceModel" -> result.success(Build.MODEL ?: "")
                "sdkInt" -> result.success(Build.VERSION.SDK_INT)
                "isListenerEnabled" -> result.success(isListenerEnabled(activity))
                "openNotificationListenerSettings" -> {
                    activity.startActivity(Intent(Settings.ACTION_NOTIFICATION_LISTENER_SETTINGS))
                    result.success(null)
                }
                "isIgnoringBatteryOptimizations" -> {
                    val power = app.getSystemService(PowerManager::class.java)
                    result.success(power.isIgnoringBatteryOptimizations(app.packageName))
                }
                "requestIgnoreBatteryOptimizations" -> {
                    val intent = Intent(
                        Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS,
                        Uri.parse("package:${app.packageName}"),
                    )
                    activity.startActivity(intent)
                    result.success(null)
                }
                "openAppSettings" -> {
                    val intent = Intent(
                        Settings.ACTION_APPLICATION_DETAILS_SETTINGS,
                        Uri.fromParts("package", app.packageName, null),
                    )
                    activity.startActivity(intent)
                    result.success(null)
                }
                "pendingCount" -> result.success(QueueDb(app).pendingCount())
                "lastSentAt" -> result.success(QueueDb(app).lastSentMs())
                "lastContactAt" -> result.success(PaymentSender.getLastContactMs(app))
                "sentHistory" -> result.success(sentHistory(app))
                "clearQueue" -> {
                    QueueDb(app).clearAll()
                    result.success(null)
                }
                "triggerSync" -> {
                    Thread {
                        try {
                            val sync = PaymentSender.sendPending(app)
                            PaymentSender.sendHeartbeat(app)
                            if (sync.pending > 0) {
                                SendWorker.enqueue(app)
                            }
                        } catch (_: Exception) {
                        }
                    }.start()
                    result.success(null)
                }
                "isSessionInvalid" -> result.success(PaymentSender.isAuthFailed(app))
                "clearSessionInvalid" -> {
                    PaymentSender.setAuthFailed(app, false)
                    result.success(null)
                }
                else -> result.notImplemented()
            }
        } catch (e: Exception) {
            result.error("native_error", e.message, null)
        }
    }

    private fun isListenerEnabled(activity: Activity): Boolean {
        val flat = ComponentName(activity, PayGuardListener::class.java).flattenToString()
        val enabled = Settings.Secure.getString(
            activity.contentResolver,
            "enabled_notification_listeners",
        ) ?: return false
        return enabled.split(":").any { it.equals(flat, ignoreCase = true) }
    }

    private fun sentHistory(activity: android.content.Context): List<Map<String, Any?>> {
        return QueueDb(activity).sentHistory().map { item ->
            val text = listOfNotNull(
                item.title?.takeIf { it.isNotBlank() },
                item.text?.takeIf { it.isNotBlank() },
            ).joinToString("\n").take(500)
            mapOf(
                "text" to text,
                "postedMs" to item.postedMs,
                "sentMs" to item.sentMs,
            )
        }
    }
}
