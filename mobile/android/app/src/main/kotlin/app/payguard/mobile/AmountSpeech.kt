package app.payguard.mobile

object AmountSpeech {

    fun describe(normalizedAmount: String): String {
        val parts = normalizedAmount.split(".")
        val soles = parts[0].toIntOrNull()?.coerceAtLeast(0) ?: 0
        val cents = parts.getOrNull(1)?.padEnd(2, '0')?.take(2)?.toIntOrNull()?.coerceIn(0, 99) ?: 0
        val solWord = if (soles == 1) "sol" else "soles"
        val centWord = if (cents == 1) "céntimo" else "céntimos"
        return when {
            soles == 0 && cents == 0 -> "0 soles"
            soles == 0 -> "$cents $centWord"
            cents == 0 -> "$soles $solWord"
            else -> "$soles $solWord con $cents $centWord"
        }
    }
}
