package app.payguard.mobile

import android.content.Context

class VoicePrefs(context: Context) {
    private val prefs = context.applicationContext
        .getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)

    fun isEnabled(): Boolean {
        return prefs.getBoolean(KEY_ENABLED, true)
    }

    fun setEnabled(value: Boolean) {
        prefs.edit().putBoolean(KEY_ENABLED, value).apply()
    }

    fun useAlarmStream(): Boolean {
        return prefs.getBoolean(KEY_ALARM_STREAM, false)
    }

    fun setAlarmStream(value: Boolean) {
        prefs.edit().putBoolean(KEY_ALARM_STREAM, value).apply()
    }

    fun spanishAvailable(): Boolean? {
        if (!prefs.contains(KEY_SPANISH_OK)) {
            return null
        }
        return prefs.getBoolean(KEY_SPANISH_OK, false)
    }

    fun setSpanishAvailable(value: Boolean) {
        prefs.edit().putBoolean(KEY_SPANISH_OK, value).apply()
    }

    fun localeTag(): String? {
        return prefs.getString(KEY_LOCALE, null)
    }

    fun setLocaleTag(tag: String?) {
        prefs.edit().putString(KEY_LOCALE, tag).apply()
    }

    companion object {
        const val PREFS_NAME = "payguard_voice"
        private const val KEY_ENABLED = "voice_enabled"
        private const val KEY_ALARM_STREAM = "voice_alarm_stream"
        private const val KEY_SPANISH_OK = "voice_spanish_ok"
        private const val KEY_LOCALE = "voice_locale"
    }
}
