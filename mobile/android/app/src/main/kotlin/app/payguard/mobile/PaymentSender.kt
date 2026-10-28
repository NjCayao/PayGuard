package app.payguard.mobile

import android.content.Context
import org.json.JSONObject
import java.io.IOException
import java.net.HttpURLConnection
import java.net.URL

data class SyncResult(
    val sent: Int,
    val pending: Int,
    val authFailed: Boolean,
    val retryable: Boolean,
)

object PaymentSender {
    private const val TIMEOUT_MS = 15000
    private const val BATCH_LIMIT = 100
    private const val STATE_PREFS = "payguard_state"
    private const val AUTH_FAILED_KEY = "auth_failed"
    private const val LAST_CONTACT_KEY = "last_contact"

    fun sendPending(context: Context): SyncResult {
        val app = context.applicationContext
        val store = SecureStore(app)
        val token = store.getString("auth_token")
        if (token.isNullOrEmpty()) {
            return SyncResult(0, QueueDb(app).pendingCount(), false, false)
        }
        val baseUrl = store.getString("base_url") ?: app.getString(R.string.default_api_url)
        val queue = QueueDb(app)

        var sent = 0
        var authFailed = false
        var retryable = false

        for (item in queue.takePending(BATCH_LIMIT)) {
            when (val code = postPayment(baseUrl, token, item)) {
                200, 201 -> {
                    queue.delete(item.id)
                    queue.recordSent(item.title, item.text, item.postedMs)
                    setLastContact(app)
                    sent++
                }
                401 -> {
                    authFailed = true
                    break
                }
                429 -> {
                    retryable = true
                    break
                }
                in 500..599, NETWORK_ERROR -> {
                    retryable = true
                    break
                }
                else -> {
                    queue.delete(item.id)
                }
            }
        }

        if (authFailed) {
            setAuthFailed(app, true)
        } else if (sent > 0) {
            setAuthFailed(app, false)
        }

        return SyncResult(sent, queue.pendingCount(), authFailed, retryable)
    }

    fun sendHeartbeat(context: Context): Boolean {
        val app = context.applicationContext
        val store = SecureStore(app)
        val token = store.getString("auth_token")
        if (token.isNullOrEmpty()) {
            return false
        }
        val baseUrl = store.getString("base_url") ?: app.getString(R.string.default_api_url)
        return try {
            when (post("$baseUrl/api/device/heartbeat", token, null)) {
                200 -> {
                    setAuthFailed(app, false)
                    setLastContact(app)
                    true
                }
                401 -> {
                    setAuthFailed(app, true)
                    false
                }
                else -> false
            }
        } catch (_: IOException) {
            false
        }
    }

    fun isAuthFailed(context: Context): Boolean {
        return context.applicationContext
            .getSharedPreferences(STATE_PREFS, Context.MODE_PRIVATE)
            .getBoolean(AUTH_FAILED_KEY, false)
    }

    fun setAuthFailed(context: Context, failed: Boolean) {
        context.applicationContext
            .getSharedPreferences(STATE_PREFS, Context.MODE_PRIVATE)
            .edit()
            .putBoolean(AUTH_FAILED_KEY, failed)
            .apply()
    }

    fun getLastContactMs(context: Context): Long? {
        val value = context.applicationContext
            .getSharedPreferences(STATE_PREFS, Context.MODE_PRIVATE)
            .getLong(LAST_CONTACT_KEY, 0)
        return if (value > 0) value else null
    }

    private fun setLastContact(context: Context) {
        context.applicationContext
            .getSharedPreferences(STATE_PREFS, Context.MODE_PRIVATE)
            .edit()
            .putLong(LAST_CONTACT_KEY, System.currentTimeMillis())
            .apply()
    }

    private fun postPayment(baseUrl: String, token: String, item: QueuedItem): Int {
        val body = JSONObject()
            .put("package", item.pkg)
            .put("title", item.title)
            .put("text", item.text)
            .put("big_text", item.bigText)
            .put("posted_at_ms", item.postedMs)
            .put("idempotency_key", item.key)
            .toString()
        return try {
            post("$baseUrl/api/device/payments", token, body)
        } catch (_: IOException) {
            NETWORK_ERROR
        }
    }

    private fun post(url: String, token: String, body: String?): Int {
        val connection = (URL(url).openConnection() as HttpURLConnection).apply {
            requestMethod = "POST"
            connectTimeout = TIMEOUT_MS
            readTimeout = TIMEOUT_MS
            setRequestProperty("Accept", "application/json")
            setRequestProperty("Authorization", "Bearer $token")
            if (body != null) {
                doOutput = true
                setRequestProperty("Content-Type", "application/json")
                outputStream.use { it.write(body.toByteArray(Charsets.UTF_8)) }
            }
        }
        try {
            val code = connection.responseCode
            try {
                (if (code < 400) connection.inputStream else connection.errorStream)
                    ?.use { it.readBytes() }
            } catch (_: IOException) {
            }
            return code
        } finally {
            connection.disconnect()
        }
    }

    private const val NETWORK_ERROR = -1
}
