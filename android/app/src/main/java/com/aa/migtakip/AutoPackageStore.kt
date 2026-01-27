package com.aa.migtakip

import android.content.Context
import org.json.JSONObject

data class AutoPackageEvent(val date: String, val packages: Int, val overtimeHours: Double = 0.0) {
    fun toJson(): String = JSONObject()
        .put("date", date)
        .put("packages", packages)
        .put("overtime_hours", overtimeHours)
        .toString()
}

object AutoPackageStore {
    private const val PREFS = "migtakip_auto"
    private const val KEY_LAST = "last_event"
    private const val KEY_LAST_SENT = "last_sent"

    fun saveDetected(context: Context, ev: AutoPackageEvent) {
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            .edit()
            .putString(KEY_LAST, ev.toJson())
            .apply()
    }

    fun loadDetected(context: Context): String? =
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            .getString(KEY_LAST, null)

    fun markSent(context: Context, ev: AutoPackageEvent) {
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            .edit()
            .putString(KEY_LAST_SENT, ev.toJson())
            .apply()
    }

    fun wasSent(context: Context, ev: AutoPackageEvent): Boolean {
        val last = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            .getString(KEY_LAST_SENT, null) ?: return false
        return last == ev.toJson()
    }
}
