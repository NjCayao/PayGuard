package app.payguard.mobile

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

class YapeVoiceParserTest {

    private fun parser(): YapeVoiceParser {
        // Mirror of res/values/voice_config.xml (Java regex syntax).
        return YapeVoiceParser(
            ignorePatterns = listOf(
                "\\byapeaste\\b",
                "promoci[óo]n",
                "\\bpromo\\b",
                "descuento",
                "sorteo",
                "\\bcompra\\b",
                "\\bganaste\\b",
                "\\bgana\\b",
                "premio",
                "cashback",
                "oferta",
                "beneficio",
                "participa",
            ),
            receivedMarkers = listOf(
                "te envi[óo] un pago",
                "recibiste",
                "confirmaci[óo]n de pago",
            ),
            paymentPatterns = listOf(
                "(?:^|\\n)\\s*(?:Yape!\\s*)?(?<payer>[^\\n]+?)\\s+te envi[óo] un pago por\\s*(?<rest>.+)$",
            ),
            amountPatterns = listOf(
                "S/\\.?\\s*(?<amount>\\d(?:[\\d.,]*\\d)?)",
                "S/\\.?\\s*(?<amount>[\\d.,]+)",
                "(?<amount>[\\d.,]+)\\s*soles",
            ),
        )
    }

    @Test
    fun parsesRealShapedNotification() {
        val payment = parser().parse(
            "Confirmación de Pago\n" +
                "Antoni Mon* te envió un pago por S/ 0.1. El cód. de seguridad es: 944",
        )

        assertEquals(VoicePayment(payerName = "Antoni Mon", amount = "0.10"), payment)
    }

    @Test
    fun parsesYapeTitleVariant() {
        val payment = parser().parse(
            "Yape!\nJUAN PEREZ te envió un pago por S/ 50",
        )

        assertEquals(VoicePayment(payerName = "JUAN PEREZ", amount = "50.00"), payment)
    }

    @Test
    fun parsesThousandsAndCommaDecimals() {
        val payment = parser().parse(
            "MARIA te envió un pago por S/ 1,250.50",
        )

        assertEquals(VoicePayment(payerName = "MARIA", amount = "1250.50"), payment)
    }

    @Test
    fun parsesSolesWordAmount() {
        val payment = parser().parse(
            "PEDRO te envió un pago por 20 soles",
        )

        assertEquals(VoicePayment(payerName = "PEDRO", amount = "20.00"), payment)
    }

    @Test
    fun ignoresOutgoingTransfer() {
        assertNull(parser().parse("Yapeaste S/ 20 a MARIA LOPEZ"))
    }

    @Test
    fun ignoresPromo() {
        assertNull(parser().parse("Nueva promoción: descuento en tu compra con Yape"))
    }

    @Test
    fun ignoresPaymentShapedPromo() {
        assertNull(parser().parse("¡Juan te envió un pago! Gana premios y cashback, participa en esta oferta con beneficios"))
    }

    @Test
    fun ignoresNotificationWithoutReceivedMarker() {
        assertNull(parser().parse("Yape tiene una nueva versión disponible"))
    }

    @Test
    fun returnsEmptyFieldsWhenMarkerWithoutDetails() {
        val payment = parser().parse("Confirmación de Pago")

        assertEquals(VoicePayment(payerName = null, amount = null), payment)
    }

    @Test
    fun returnsEmptyFieldsForMarkerWithoutPaymentShape() {
        val payment = parser().parse(
            "Recibiste un pago de LUIS por Yape, revísalo en la app",
        )

        assertEquals(VoicePayment(payerName = null, amount = null), payment)
    }
}
