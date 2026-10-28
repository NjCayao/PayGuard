package app.payguard.mobile

import java.security.MessageDigest

object TextNormalizer {

    fun join(title: String?, text: String?, bigText: String?): String {
        return listOfNotNull(
            title?.takeIf { it.isNotBlank() },
            text?.takeIf { it.isNotBlank() },
            bigText?.takeIf { it.isNotBlank() },
        ).joinToString("\n")
    }

    fun normalize(text: String): String {
        return text.trim().replace("\\s+".toRegex(), " ").lowercase()
    }

    fun sha256Hex(input: String): String {
        val digest = MessageDigest.getInstance("SHA-256")
        return digest.digest(input.toByteArray(Charsets.UTF_8))
            .joinToString("") { "%02x".format(it) }
    }
}
