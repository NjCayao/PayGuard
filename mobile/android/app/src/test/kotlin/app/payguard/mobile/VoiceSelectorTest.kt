package app.payguard.mobile

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test
import java.util.Locale

class VoiceSelectorTest {

    private val preferred = listOf("es-PE", "es-US", "es-ES")

    @Test
    fun prefersPeruSpanish() {
        val available = setOf(Locale("es", "ES"), Locale("es", "PE"), Locale("en", "US"))

        assertEquals(Locale("es", "PE"), VoiceSelector.pick(available, preferred))
    }

    @Test
    fun fallsBackThroughPreferenceOrder() {
        assertEquals(
            Locale("es", "US"),
            VoiceSelector.pick(setOf(Locale("es", "US"), Locale("es", "ES")), preferred),
        )
        assertEquals(
            Locale("es", "ES"),
            VoiceSelector.pick(setOf(Locale("es", "ES"), Locale("en", "US")), preferred),
        )
    }

    @Test
    fun usesAnySpanishAsLastResort() {
        assertEquals(
            Locale("es", "MX"),
            VoiceSelector.pick(setOf(Locale("es", "MX"), Locale("en", "US")), preferred),
        )
    }

    @Test
    fun returnsNullWithoutSpanish() {
        assertNull(VoiceSelector.pick(setOf(Locale("en", "US")), preferred))
    }
}
