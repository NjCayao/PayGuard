package app.payguard.mobile

import org.junit.Assert.assertEquals
import org.junit.Test

class TextNormalizerTest {

    @Test
    fun joinsPresentParts() {
        assertEquals(
            "Title\nBody",
            TextNormalizer.join("Title", "Body", null),
        )
        assertEquals("", TextNormalizer.join(null, "  ", ""))
    }

    @Test
    fun collapsesWhitespaceAndLowercases() {
        assertEquals(
            "yape! juan pagó s/ 50",
            TextNormalizer.normalize("  Yape!\nJUAN   pagó S/ 50 "),
        )
    }

    @Test
    fun sha256HexIsStable() {
        assertEquals(
            TextNormalizer.sha256Hex("abc"),
            TextNormalizer.sha256Hex("abc"),
        )
        assertEquals(64, TextNormalizer.sha256Hex("abc").length)
    }
}
