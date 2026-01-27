package com.aa.migtakip

import android.accessibilityservice.AccessibilityService
import android.view.accessibility.AccessibilityEvent
import android.view.accessibility.AccessibilityNodeInfo
import java.time.LocalDate
import java.time.format.DateTimeFormatter
import java.util.Locale
import java.util.regex.Pattern

class MigPackAccessibilityService : AccessibilityService() {

    companion object {
        const val ACTION_PACKAGES_DETECTED = "com.aa.migtakip.PACKAGES_DETECTED"
        // Kotlin string literals require escaping backslashes for regex patterns.
        private val PKG_REGEX = Pattern.compile(
            "Paketler\\s*\\((\\d+)\\)",
            Pattern.CASE_INSENSITIVE or Pattern.UNICODE_CASE
        )
        private val DATE_REGEX = Pattern.compile(
            "\\b(\\d{2})\\s+([A-Za-zÇĞİÖŞÜçğıöşü]+)\\s+([A-Za-zÇĞİÖŞÜçğıöşü]+)\\b"
        )
        private val MONTHS = mapOf(
            "OCAK" to 1, "ŞUBAT" to 2, "SUBAT" to 2, "MART" to 3, "NİSAN" to 4, "NISAN" to 4,
            "MAYIS" to 5, "HAZİRAN" to 6, "HAZIRAN" to 6, "TEMMUZ" to 7, "AĞUSTOS" to 8, "AGUSTOS" to 8,
            "EYLÜL" to 9, "EYLUL" to 9, "EKİM" to 10, "EKIM" to 10, "KASIM" to 11, "ARALIK" to 12
        )
    }

    override fun onAccessibilityEvent(event: AccessibilityEvent?) {
        if (event == null) return
        val pkg = event.packageName?.toString() ?: return
        if (pkg != Config.MIGPACK_PACKAGE) return

        val root = rootInActiveWindow ?: return

        // DFS sırası ile: en son görülen tarih + paketler
        var lastDateText: String? = null
        var monthNumber: Int? = null

        fun dfs(node: AccessibilityNodeInfo?) {
            if (node == null) return
            val t = node.text?.toString()?.trim()
            if (!t.isNullOrBlank()) {
                // Ay dropdown'u: OCAK gibi tek kelime
                val up = t.uppercase(Locale("tr","TR"))
                if (MONTHS.containsKey(up)) monthNumber = MONTHS[up]

                // Gün satırı: 02 Ocak Cuma
                val dm = DATE_REGEX.matcher(t)
                if (dm.find()) {
                    lastDateText = dm.group(0)
                }

                // Paketler: Paketler (46)
                val pm = PKG_REGEX.matcher(t)
                if (pm.find()) {
                    val packages = pm.group(1).toIntOrNull() ?: return
                    val date = resolveDate(lastDateText, monthNumber) ?: return
                    sendDetected(date, packages)
                    // bir kere yakalayınca devam etmeyelim
                    return
                }
            }
            for (i in 0 until node.childCount) {
                dfs(node.getChild(i))
            }
        }

        dfs(root)
    }

    private fun resolveDate(dateText: String?, monthNumber: Int?): String? {
        if (dateText.isNullOrBlank()) return null
        val m = DATE_REGEX.matcher(dateText)
        if (!m.find()) return null
        val day = m.group(1).toIntOrNull() ?: return null

        val now = LocalDate.now()
        val month = monthNumber ?: run {
            // dateText içinde ay adı varsa onu çöz
            val parts = dateText.split(" ")
            if (parts.size >= 2) {
                val up = parts[1].uppercase(Locale("tr","TR"))
                MONTHS[up]
            } else null
        } ?: return null

        val year = now.year
        val d = try { LocalDate.of(year, month, day) } catch (_: Exception) { return null }
        return d.format(DateTimeFormatter.ISO_DATE)
    }

    private fun sendDetected(date: String, packages: Int) {
        val intent = android.content.Intent(ACTION_PACKAGES_DETECTED).apply {
            putExtra("date", date)
            putExtra("packages", packages)
            putExtra("overtime_hours", 0.0)
        }
        sendBroadcast(intent)
    }

    override fun onInterrupt() { }
}
