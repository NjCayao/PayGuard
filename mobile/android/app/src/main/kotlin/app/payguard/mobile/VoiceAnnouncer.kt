package app.payguard.mobile

import android.content.Context
import android.media.AudioAttributes
import android.media.AudioFocusRequest
import android.media.AudioManager
import android.os.Handler
import android.os.Looper
import android.speech.tts.TextToSpeech
import android.speech.tts.UtteranceProgressListener

data class VoiceStatus(
    val enabled: Boolean,
    val alarmStream: Boolean,
    val spanishAvailable: Boolean?,
    val localeTag: String?,
)

object VoiceAnnouncer {
    private val queue = VoiceQueue()
    private var tts: TextToSpeech? = null
    private var initializing = false
    private var ready = false
    private var flushScheduled = false
    private var focus: AudioFocusRequest? = null
    private var appContext: Context? = null

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
                appContext = app
                queue.add(clean, alarm)
                if (ensureInit(app)) {
                    scheduleFlush(app)
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
            queue.clear()
            return
        }
        ready = true
        try {
            val engine = tts ?: return
            applyVoice(app, engine)
            listen(engine)
            scheduleFlush(app)
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

    private fun scheduleFlush(app: Context) {
        if (flushScheduled || queue.pendingCount() == 0) {
            return
        }
        flushScheduled = true
        val delayMs = runCatching { app.resources.getInteger(R.integer.voice_announce_delay_ms) }
            .getOrDefault(DEFAULT_DELAY_MS)
            .toLong()
            .coerceAtLeast(0)
        mainHandler().postDelayed(
            {
                flushScheduled = false
                try {
                    flush(app)
                } catch (_: Exception) {
                }
            },
            delayMs,
        )
    }

    private fun flush(app: Context) {
        val engine = tts ?: return
        if (!ready) {
            return
        }
        requestFocus(app)
        while (true) {
            val item = queue.next() ?: break
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
            engine.speak(item.text, TextToSpeech.QUEUE_ADD, null, item.id)
        }
    }

    private fun listen(engine: TextToSpeech) {
        engine.setOnUtteranceProgressListener(
            object : UtteranceProgressListener() {
                override fun onStart(utteranceId: String?) {
                }

                override fun onDone(utteranceId: String?) {
                    utteranceId ?: return
                    if (queue.finish(utteranceId)) {
                        abandonFocus(appContext)
                    }
                }

                override fun onError(utteranceId: String?) {
                    repeatOnce(utteranceId)
                }

                override fun onStop(utteranceId: String?, interrupted: Boolean) {
                    repeatOnce(utteranceId)
                }
            },
        )
    }

    private fun repeatOnce(utteranceId: String?) {
        utteranceId ?: return
        val again = queue.retry(utteranceId)
        val app = appContext
        if (again != null && app != null) {
            scheduleFlush(app)
        } else if (queue.isIdle()) {
            abandonFocus(app)
        }
    }

    private fun requestFocus(app: Context) {
        if (focus != null) {
            return
        }
        val manager = app.getSystemService(Context.AUDIO_SERVICE) as? AudioManager ?: return
        val request = AudioFocusRequest.Builder(AudioManager.AUDIOFOCUS_GAIN_TRANSIENT_MAY_DUCK)
            .setAudioAttributes(
                AudioAttributes.Builder()
                    .setUsage(AudioAttributes.USAGE_NOTIFICATION)
                    .setContentType(AudioAttributes.CONTENT_TYPE_SPEECH)
                    .build(),
            )
            .setOnAudioFocusChangeListener(::onFocusChange)
            .build()
        if (manager.requestAudioFocus(request) == AudioManager.AUDIOFOCUS_REQUEST_GRANTED) {
            focus = request
        }
    }

    private fun onFocusChange(change: Int) {
        if (change != AudioManager.AUDIOFOCUS_LOSS) {
            return
        }
        try {
            tts?.stop()
        } catch (_: Exception) {
        }
        abandonFocus(appContext)
        var moved = false
        for (id in queue.activeIds()) {
            if (queue.retry(id) != null) {
                moved = true
            }
        }
        val app = appContext
        if (moved && app != null) {
            scheduleFlush(app)
        }
    }

    private fun abandonFocus(app: Context?) {
        val request = focus ?: return
        focus = null
        app ?: return
        try {
            (app.getSystemService(Context.AUDIO_SERVICE) as? AudioManager)
                ?.abandonAudioFocusRequest(request)
        } catch (_: Exception) {
        }
    }

    private fun onMain(action: () -> Unit) {
        if (Looper.myLooper() == Looper.getMainLooper()) {
            action()
        } else {
            mainHandler().post(action)
        }
    }

    private fun mainHandler(): Handler = Handler(Looper.getMainLooper())

    private const val MAX_UTTERANCE = 300
    private const val DEFAULT_DELAY_MS = 1500
}
