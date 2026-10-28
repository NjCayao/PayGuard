package app.payguard.mobile

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotEquals
import org.junit.Test

class NotificationKeyTest {

    @Test
    fun sameNotificationProducesSameKey() {
        val first = NotificationKey.stableKey(
            pkg = "com.bcp.innovacxion.yapeapp",
            sbnKey = "0|com.bcp.innovacxion.yapeapp|1001|null|12345",
            notificationWhen = 1761600000000,
            postTimeMs = 1761600001000,
            title = "Confirmación de Pago",
            text = "Antoni Mon* te envió un pago por S/ 0.1. El cód. de seguridad es: 944",
            bigText = null,
        )
        val second = NotificationKey.stableKey(
            pkg = "com.bcp.innovacxion.yapeapp",
            sbnKey = "0|com.bcp.innovacxion.yapeapp|1001|null|12345",
            notificationWhen = 1761600000000,
            postTimeMs = 1761600009000,
            title = "Confirmación de Pago",
            text = "Antoni Mon*  te envió un pago por S/ 0.1. El cód. de seguridad es: 944",
            bigText = null,
        )

        assertEquals(64, first.length)
        assertEquals(first, second)
    }

    @Test
    fun fallsBackToPostTimeWhenNotificationWhenIsMissing() {
        val withWhen = NotificationKey.stableKey(
            pkg = "com.bcp.innovacxion.yapeapp",
            sbnKey = "key",
            notificationWhen = 1761600000000,
            postTimeMs = 1761600001000,
            title = "t",
            text = "x",
            bigText = null,
        )
        val withPostTime = NotificationKey.stableKey(
            pkg = "com.bcp.innovacxion.yapeapp",
            sbnKey = "key",
            notificationWhen = 0,
            postTimeMs = 1761600000000,
            title = "t",
            text = "x",
            bigText = null,
        )

        assertEquals(withWhen, withPostTime)
    }

    @Test
    fun differentPaymentsProduceDifferentKeys() {
        val base = NotificationKey.stableKey(
            pkg = "com.bcp.innovacxion.yapeapp",
            sbnKey = "key-a",
            notificationWhen = 1761600000000,
            postTimeMs = 1761600000000,
            title = "Confirmación de Pago",
            text = "A te envió un pago por S/ 10",
            bigText = null,
        )
        val otherAmount = NotificationKey.stableKey(
            pkg = "com.bcp.innovacxion.yapeapp",
            sbnKey = "key-b",
            notificationWhen = 1761600060000,
            postTimeMs = 1761600060000,
            title = "Confirmación de Pago",
            text = "A te envió un pago por S/ 11",
            bigText = null,
        )

        assertNotEquals(base, otherAmount)
    }
}
