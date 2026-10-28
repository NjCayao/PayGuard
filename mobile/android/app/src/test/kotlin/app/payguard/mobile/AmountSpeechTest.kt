package app.payguard.mobile

import org.junit.Assert.assertEquals
import org.junit.Test

class AmountSpeechTest {

    @Test
    fun describesCentsOnly() {
        assertEquals("10 céntimos", AmountSpeech.describe("0.10"))
    }

    @Test
    fun describesSingleCent() {
        assertEquals("1 céntimo", AmountSpeech.describe("0.01"))
    }

    @Test
    fun describesWholeSoles() {
        assertEquals("5 soles", AmountSpeech.describe("5.00"))
    }

    @Test
    fun describesSingleSol() {
        assertEquals("1 sol", AmountSpeech.describe("1.00"))
    }

    @Test
    fun describesSolesAndCents() {
        assertEquals("50 soles con 50 céntimos", AmountSpeech.describe("50.50"))
    }

    @Test
    fun describesSingleSolWithCents() {
        assertEquals("1 sol con 5 céntimos", AmountSpeech.describe("1.05"))
    }

    @Test
    fun describesLargeAmount() {
        assertEquals("1250 soles", AmountSpeech.describe("1250.00"))
    }
}
