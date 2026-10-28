package app.payguard.mobile

import java.math.BigDecimal
import java.math.RoundingMode

data class VoicePayment(
    val payerName: String?,
    val amount: String?,
)

class YapeVoiceParser(
    ignorePatterns: List<String>,
    receivedMarkers: List<String>,
    paymentPatterns: List<String>,
    amountPatterns: List<String>,
) {
    private val ignore = ignorePatterns.map { Regex(it, RegexOption.IGNORE_CASE) }
    private val markers = receivedMarkers.map { Regex(it, RegexOption.IGNORE_CASE) }
    private val payments = paymentPatterns.map {
        Regex(it, setOf(RegexOption.IGNORE_CASE, RegexOption.DOT_MATCHES_ALL))
    }
    private val amounts = amountPatterns.map { Regex(it, RegexOption.IGNORE_CASE) }

    fun parse(text: String): VoicePayment? {
        if (ignore.any { it.containsMatchIn(text) }) {
            return null
        }
        if (markers.none { it.containsMatchIn(text) }) {
            return null
        }

        var payer: String? = null
        var rest = text

        for (pattern in payments) {
            val match = pattern.find(text) ?: continue
            payer = match.groups["payer"]?.value?.let { cleanName(it) }
            rest = match.groups["rest"]?.value ?: text
            break
        }

        return VoicePayment(payerName = payer, amount = findAmount(rest))
    }

    private fun findAmount(rest: String): String? {
        for (pattern in amounts) {
            val raw = pattern.find(rest)?.groups?.get("amount")?.value ?: continue
            normalizeAmount(raw)?.let { return it }
        }
        return null
    }

    private fun cleanName(name: String): String? {
        val clean = name.replace("*", "").replace("\\s+".toRegex(), " ").trim()
        return clean.takeIf { it.isNotEmpty() }?.take(MAX_NAME)
    }

    private fun normalizeAmount(raw: String): String? {
        var clean = raw.replace(" ", "")
        if ('.' in clean && ',' in clean) {
            clean = if (clean.lastIndexOf('.') > clean.lastIndexOf(',')) {
                clean.replace(",", "")
            } else {
                clean.replace(".", "").replace(",", ".")
            }
        } else if (',' in clean) {
            clean = if (Regex(",\\d{2}$").containsMatchIn(clean)) {
                clean.replace(",", ".")
            } else {
                clean.replace(",", "")
            }
        }
        val value = clean.toBigDecimalOrNull() ?: return null
        if (value <= BigDecimal.ZERO || value >= MAX_AMOUNT) {
            return null
        }
        return value.setScale(2, RoundingMode.HALF_UP).toPlainString()
    }

    companion object {
        private const val MAX_NAME = 120
        private val MAX_AMOUNT = BigDecimal(100000000)
    }
}
