package app.payguard.mobile

import android.content.Context

class VoiceStore(context: Context) {
    private val prefs = context.applicationContext
        .getSharedPreferences(VoicePrefs.PREFS_NAME, Context.MODE_PRIVATE)

    fun shouldAnnounce(key: String, windowMinutes: Int, nowMs: Long = System.currentTimeMillis()): Boolean {
        val cutoff = nowMs - windowMinutes * 60_000L
        val entries = prefs.getStringSet(KEY_ANNOUNCED, emptySet()).orEmpty()
            .mapNotNull { parseEntry(it) }
            .filter { it.second > cutoff }
        if (entries.any { it.first == key }) {
            return false
        }
        val updated = (entries + (key to nowMs))
            .takeLast(MAX_ENTRIES)
            .map { "${it.first}:${it.second}" }
            .toSet()
        prefs.edit().putStringSet(KEY_ANNOUNCED, updated).apply()
        return true
    }

    private fun parseEntry(raw: String): Pair<String, Long>? {
        val key = raw.substringBeforeLast(":", "")
        val ms = raw.substringAfterLast(":", "").toLongOrNull()
        if (key.isEmpty() || ms == null) {
            return null
        }
        return key to ms
    }

    companion object {
        private const val KEY_ANNOUNCED = "voice_announced"
        private const val MAX_ENTRIES = 50

        fun voiceKey(joinedText: String): String {
            return TextNormalizer.sha256Hex(TextNormalizer.normalize(joinedText))
        }
    }
}
