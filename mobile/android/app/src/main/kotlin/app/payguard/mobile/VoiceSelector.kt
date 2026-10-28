package app.payguard.mobile

import java.util.Locale

object VoiceSelector {

    fun parseTag(tag: String): Locale {
        val parts = tag.split("-", "_")
        return if (parts.size >= 2) {
            Locale(parts[0], parts[1])
        } else {
            Locale(parts[0])
        }
    }

    fun pick(available: Set<Locale>, preferredTags: List<String>): Locale? {
        for (tag in preferredTags) {
            val wanted = parseTag(tag)
            if (wanted in available) {
                return wanted
            }
        }
        return available.firstOrNull { it.language == "es" }
    }
}
