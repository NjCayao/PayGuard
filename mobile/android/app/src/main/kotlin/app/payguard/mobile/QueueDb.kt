package app.payguard.mobile

import android.content.ContentValues
import android.content.Context
import android.database.sqlite.SQLiteDatabase
import android.database.sqlite.SQLiteOpenHelper

data class QueuedItem(
    val id: Long,
    val key: String,
    val pkg: String,
    val title: String?,
    val text: String?,
    val bigText: String?,
    val postedMs: Long,
)

data class SentItem(
    val title: String?,
    val text: String?,
    val postedMs: Long,
    val sentMs: Long,
)

class QueueDb(context: Context) :
    SQLiteOpenHelper(context.applicationContext, DB_NAME, null, DB_VERSION) {

    override fun onCreate(db: SQLiteDatabase) {
        db.execSQL(
            """CREATE TABLE queue (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                idempotency_key TEXT UNIQUE NOT NULL,
                package TEXT NOT NULL,
                title TEXT,
                body TEXT,
                big_text TEXT,
                posted_ms INTEGER NOT NULL,
                attempts INTEGER NOT NULL DEFAULT 0,
                created_ms INTEGER NOT NULL
            )""",
        )
        db.execSQL(
            """CREATE TABLE history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT,
                body TEXT,
                posted_ms INTEGER NOT NULL,
                sent_ms INTEGER NOT NULL
            )""",
        )
    }

    override fun onUpgrade(db: SQLiteDatabase, oldVersion: Int, newVersion: Int) {
    }

    fun enqueue(item: QueuedItem): Long {
        val values = ContentValues().apply {
            put("idempotency_key", item.key)
            put("package", item.pkg)
            put("title", item.title)
            put("body", item.text)
            put("big_text", item.bigText)
            put("posted_ms", item.postedMs)
            put("created_ms", System.currentTimeMillis())
        }
        return writableDatabase.insertWithOnConflict(
            "queue",
            null,
            values,
            SQLiteDatabase.CONFLICT_IGNORE,
        )
    }

    fun takePending(limit: Int): List<QueuedItem> {
        val items = mutableListOf<QueuedItem>()
        readableDatabase
            .query(
                "queue",
                arrayOf("id", "idempotency_key", "package", "title", "body", "big_text", "posted_ms"),
                null,
                null,
                null,
                null,
                "id ASC",
                limit.toString(),
            ).use { cursor ->
                while (cursor.moveToNext()) {
                    items.add(
                        QueuedItem(
                            id = cursor.getLong(0),
                            key = cursor.getString(1),
                            pkg = cursor.getString(2),
                            title = cursor.getString(3),
                            text = cursor.getString(4),
                            bigText = cursor.getString(5),
                            postedMs = cursor.getLong(6),
                        ),
                    )
                }
            }
        return items
    }

    fun delete(id: Long) {
        writableDatabase.delete("queue", "id = ?", arrayOf(id.toString()))
    }

    fun pendingCount(): Int {
        readableDatabase.rawQuery("SELECT COUNT(*) FROM queue", null).use { cursor ->
            return if (cursor.moveToFirst()) cursor.getInt(0) else 0
        }
    }

    fun clearAll() {
        writableDatabase.delete("queue", null, null)
        writableDatabase.delete("history", null, null)
    }

    fun recordSent(title: String?, text: String?, postedMs: Long) {
        val values = ContentValues().apply {
            put("title", title)
            put("body", text)
            put("posted_ms", postedMs)
            put("sent_ms", System.currentTimeMillis())
        }
        val db = writableDatabase
        db.insert("history", null, values)
        db.execSQL(
            "DELETE FROM history WHERE id NOT IN " +
                "(SELECT id FROM history ORDER BY id DESC LIMIT $HISTORY_LIMIT)",
        )
    }

    fun sentHistory(): List<SentItem> {
        val items = mutableListOf<SentItem>()
        readableDatabase
            .query(
                "history",
                arrayOf("title", "body", "posted_ms", "sent_ms"),
                null,
                null,
                null,
                null,
                "id DESC",
                HISTORY_LIMIT.toString(),
            ).use { cursor ->
                while (cursor.moveToNext()) {
                    items.add(
                        SentItem(
                            title = cursor.getString(0),
                            text = cursor.getString(1),
                            postedMs = cursor.getLong(2),
                            sentMs = cursor.getLong(3),
                        ),
                    )
                }
            }
        return items
    }

    fun lastSentMs(): Long? {
        readableDatabase
            .rawQuery("SELECT MAX(sent_ms) FROM history", null)
            .use { cursor ->
                if (cursor.moveToFirst() && !cursor.isNull(0)) {
                    return cursor.getLong(0)
                }
                return null
            }
    }

    companion object {
        private const val DB_NAME = "payguard_queue.db"
        private const val DB_VERSION = 1
        private const val HISTORY_LIMIT = 50
    }
}
