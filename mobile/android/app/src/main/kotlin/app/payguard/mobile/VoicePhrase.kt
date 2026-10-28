package app.payguard.mobile

object VoicePhrase {

    const val GENERIC = "Yape recibido"

    fun build(payment: VoicePayment?): String {
        if (payment == null) {
            return GENERIC
        }
        val amount = payment.amount?.let { runCatching { AmountSpeech.describe(it) }.getOrNull() }
        val payer = payment.payerName?.takeIf { it.isNotBlank() }
        if (amount == null && payer == null) {
            return GENERIC
        }
        return buildString {
            append(GENERIC)
            if (amount != null) {
                append(", ").append(amount)
            }
            if (payer != null) {
                append(", de ").append(payer)
            }
        }
    }
}
