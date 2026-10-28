package app.payguard.mobile

import android.content.Context
import android.media.AudioAttributes
import android.os.Handler
import android.os.Looper
import android.speech.tts.TextToSpeech
import java.util.UUID

data class VoiceStatus(
    val enabled: Boolean,
    val alarmStream: Boolean,
    val spanishAvailable: Boolean?,
    val localeTag: String?,
)

object VoiceAnnouncer {
    private data class Utterance(val text: String, val alarm: Boolean)

    private val pending = mutableListOf<Utterance>()
    private var tts: TextToSpeech? = null
    private var initializing = false
    private var ready = false

    const val TEST_PHRASE = "Prueba de voz. Yape recibido, 10 soles, de Juan."

    fun warmUp(context: Context) {
        val app = context.applicationContext
        onMain {
            try {
                ensureInit(app)
            } catch (_: Exception) {
            }
        }
    }

    fun announce(context: Context, text: String) {
        val app = context.applicationContext
        if (!VoicePrefs(app).isEnabled()) {
            return
        }
        enqueue(app, text, VoicePrefs(app).useAlarmStream())
    }

    fun test(context: Context) {
        val app = context.applicationContext
        enqueue(app, TEST_PHRASE, VoicePrefs(app).useAlarmStream())
    }

    fun status(context: Context): VoiceStatus {
        val app = context.applicationContext
        warmUp(app)
        val prefs = VoicePrefs(app)
        return VoiceStatus(
            enabled = prefs.isEnabled(),
            alarmStream = prefs.useAlarmStream(),
            spanishAvailable = prefs.spanishAvailable(),
            localeTag = prefs.localeTag(),
        )
    }

    private fun enqueue(app: Context, text: String, alarm: Boolean) {
        val clean = text.trim().take(MAX_UTTERANCE)
        if (clean.isEmpty()) {
            return
        }
        onMain {
            try {
                pending.add(Utterance(clean, alarm))
                if (ensureInit(app)) {
                    flush()
                }
            } catch (_: Exception) {
            }
        }
    }

    private fun ensureInit(app: Context): Boolean {
        tts?.let { return ready }
        if (initializing) {
            return false
        }
        initializing = true
        tts = TextToSpeech(app) { state -> onTtsInit(app, state) }
        return false
    }

    private fun onTtsInit(app: Context, state: Int) {
        initializing = false
        if (state != TextToSpeech.SUCCESS) {
            tts = null
            pending.clear()
            return
        }
        ready = true
        try {
            applyVoice(app, tts ?: return)
            flush()
        } catch (_: Exception) {
        }
    }

    private fun applyVoice(app: Context, engine: TextToSpeech) {
        val prefs = VoicePrefs(app)
        val available = runCatching { engine.voices.orEmpty().map { it.locale }.toSet() }
            .getOrDefault(emptySet())
        val preferred = app.resources.getStringArray(R.array.voice_locale_preference).toList()
        val locale = VoiceSelector.pick(available, preferred)
        if (locale == null) {
            prefs.setSpanishAvailable(false)
            prefs.setLocaleTag(null)
            return
        }
        val result = engine.setLanguage(locale)
        val ok = result != TextToSpeech.LANG_MISSING_DATA &&
            result != TextToSpeech.LANG_NOT_SUPPORTED
        prefs.setSpanishAvailable(ok)
        prefs.setLocaleTag(locale.toLanguageTag())
    }

    private fun flush() {
        val engine = tts ?: return
        if (!ready) {
            return
        }
        val items = pending.toList()
        pending.clear()
        for (item in items) {
            val usage = if (item.alarm) {
                AudioAttributes.USAGE_ALARM
            } else {
                AudioAttributes.USAGE_NOTIFICATION
            }
            engine.setAudioAttributes(
                AudioAttributes.Builder()
                    .setUsage(usage)
                    .setContentType(AudioAttributes.CONTENT_TYPE_SPEECH)
                    .build(),
            )
            engine.speak(item.text, TextToSpeech.QUEUE_ADD, null, UUID.randomUUID().toString())
        }
    }

    private fun onMain(action: () -> Unit) {
        if (Looper.myLooper() == Looper.getMainLooper()) {
            action()
        } else {
            Handler(Looper.getMainLooper()).post(action)
        }
    }

    private const val MAX_UTTERANCE = 300
}
