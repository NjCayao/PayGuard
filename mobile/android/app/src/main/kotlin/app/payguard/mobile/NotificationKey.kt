package app.payguard.mobile

object NotificationKey {

    fun stableKey(
        pkg: String,
        sbnKey: String?,
        notificationWhen: Long,
        postTimeMs: Long,
        title: String?,
        text: String?,
        bigText: String?,
    ): String {
        val eventMs = if (notificationWhen > 0) notificationWhen else postTimeMs
        val body = TextNormalizer.normalize(TextNormalizer.join(title, text, bigText))
        return TextNormalizer.sha256Hex("$pkg\n${sbnKey.orEmpty()}\n$eventMs\n$body")
    }
}
