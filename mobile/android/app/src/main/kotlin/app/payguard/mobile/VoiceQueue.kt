package app.payguard.mobile

import java.util.UUID

class VoiceQueue {
    data class Entry(val id: String, val text: String, val alarm: Boolean)

    private val pending = ArrayDeque<Entry>()
    private val active = LinkedHashMap<String, Entry>()
    private val retried = mutableSetOf<String>()

    fun add(text: String, alarm: Boolean): Entry {
        val entry = Entry(UUID.randomUUID().toString(), text, alarm)
        pending.addLast(entry)
        return entry
    }

    fun next(): Entry? {
        val entry = pending.removeFirstOrNull() ?: return null
        active[entry.id] = entry
        return entry
    }

    fun pendingCount(): Int = pending.size

    fun activeIds(): List<String> = active.keys.toList()

    fun isIdle(): Boolean = pending.isEmpty() && active.isEmpty()

    fun finish(id: String): Boolean {
        active.remove(id)
        if (pending.isEmpty() && active.isEmpty()) {
            retried.clear()
            return true
        }
        return false
    }

    fun retry(id: String): Entry? {
        val entry = active.remove(id) ?: return null
        if (!retried.add(id)) {
            return null
        }
        val again = Entry(UUID.randomUUID().toString(), entry.text, entry.alarm)
        retried.add(again.id)
        pending.addFirst(again)
        return again
    }

    fun clear() {
        pending.clear()
        active.clear()
        retried.clear()
    }
}
