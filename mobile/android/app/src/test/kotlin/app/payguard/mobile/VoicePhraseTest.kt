package app.payguard.mobile

import org.junit.Assert.assertEquals
import org.junit.Test

class VoicePhraseTest {

    @Test
    fun buildsFullPhrase() {
        val phrase = VoicePhrase.build(VoicePayment(payerName = "Antoni Mon", amount = "50.00"))

        assertEquals("Yape recibido, 50 soles, de Antoni Mon", phrase)
    }

    @Test
    fun fallsBackToGenericWhenUnparsed() {
        assertEquals("Yape recibido", VoicePhrase.build(null))
        assertEquals(
            "Yape recibido",
            VoicePhrase.build(VoicePayment(payerName = null, amount = null)),
        )
    }

    @Test
    fun keepsKnownPartsOnly() {
        assertEquals(
            "Yape recibido, de Antoni Mon",
            VoicePhrase.build(VoicePayment(payerName = "Antoni Mon", amount = null)),
        )
        assertEquals(
            "Yape recibido, 10 céntimos",
            VoicePhrase.build(VoicePayment(payerName = null, amount = "0.10")),
        )
    }
}
