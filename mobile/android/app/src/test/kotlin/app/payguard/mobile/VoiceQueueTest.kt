package app.payguard.mobile

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class VoiceQueueTest {

    @Test
    fun keepsArrivalOrder() {
        val queue = VoiceQueue()
        queue.add("first", false)
        queue.add("second", false)

        assertEquals("first", queue.next()?.text)
        assertEquals("second", queue.next()?.text)
        assertNull(queue.next())
    }

    @Test
    fun finishReportsIdleOnlyWhenEmpty() {
        val queue = VoiceQueue()
        val first = queue.add("first", false)
        queue.add("second", false)

        queue.next()
        assertFalse(queue.finish(first.id))

        val second = queue.next()
        assertNotNull(second)
        assertTrue(queue.finish(second!!.id))
        assertTrue(queue.isIdle())
    }

    @Test
    fun retryRequeuesOnceThenDrops() {
        val queue = VoiceQueue()
        val entry = queue.add("hello", false)
        queue.next()

        val again = queue.retry(entry.id)
        assertNotNull(again)
        assertEquals("hello", again!!.text)
        assertNotEquals(entry.id, again.id)

        queue.next()
        assertNull(queue.retry(again.id))
        assertTrue(queue.isIdle())
    }

    @Test
    fun retryOfUnknownIdIsNull() {
        assertNull(VoiceQueue().retry("missing"))
    }

    @Test
    fun retriedBudgetResetsWhenIdle() {
        val queue = VoiceQueue()
        val first = queue.add("hello", false)
        queue.next()
        val again = queue.retry(first.id)!!
        queue.next()
        queue.finish(again.id)

        val later = queue.add("hello", false)
        queue.next()
        assertNotNull(queue.retry(later.id))
    }

    @Test
    fun activeIdsListsSpokenEntries() {
        val queue = VoiceQueue()
        val first = queue.add("first", false)
        queue.add("second", false)
        queue.next()

        assertEquals(listOf(first.id), queue.activeIds())
    }
}
