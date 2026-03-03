package com.nativephp.plugins.mobile_widgets.widgets

import android.app.PendingIntent
import android.appwidget.AppWidgetManager
import android.appwidget.AppWidgetProvider
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.graphics.BitmapFactory
import android.net.Uri
import android.util.Base64
import android.util.Log
import android.util.TypedValue
import android.graphics.Color
import android.view.View
import android.widget.RemoteViews
import org.json.JSONObject

class NativePhpWidgetProvider : AppWidgetProvider() {

    override fun onUpdate(context: Context, appWidgetManager: AppWidgetManager, appWidgetIds: IntArray) {
        appWidgetIds.forEach { appWidgetId ->
            updateWidget(context, appWidgetManager, appWidgetId)
        }
    }

    override fun onReceive(context: Context, intent: Intent) {
        super.onReceive(context, intent)

        if (intent.action == AppWidgetManager.ACTION_APPWIDGET_UPDATE) {
            updateAllWidgets(context)
        }
    }

    companion object {
        private const val TAG = "NativePhpWidgetProvider"
        private const val PREFS_NAME = "nativephp_mobile_widgets"
        private const val PAYLOAD_KEY = "widget_payload"
        private const val CONFIG_KEY = "widget_config"
        private const val IMAGE_DATA_KEY = "widget_image_data_base64"
        private const val MAX_WIDGET_IMAGE_DIMENSION = 1024

        private fun decodeCachedBitmap(base64Data: String): android.graphics.Bitmap? {
            if (base64Data.isEmpty()) {
                return null
            }

            val rawBytes = runCatching { Base64.decode(base64Data, Base64.DEFAULT) }.getOrNull() ?: return null

            val bounds = BitmapFactory.Options().apply {
                inJustDecodeBounds = true
            }

            BitmapFactory.decodeByteArray(rawBytes, 0, rawBytes.size, bounds)

            if (bounds.outWidth <= 0 || bounds.outHeight <= 0) {
                return null
            }

            var inSampleSize = 1
            while ((bounds.outWidth / inSampleSize) > MAX_WIDGET_IMAGE_DIMENSION || (bounds.outHeight / inSampleSize) > MAX_WIDGET_IMAGE_DIMENSION) {
                inSampleSize *= 2
            }

            val decodeOptions = BitmapFactory.Options().apply {
                this.inSampleSize = inSampleSize.coerceAtLeast(1)
            }

            return runCatching { BitmapFactory.decodeByteArray(rawBytes, 0, rawBytes.size, decodeOptions) }.getOrNull()
        }

        fun updateAllWidgets(context: Context) {
            val appWidgetManager = AppWidgetManager.getInstance(context)
            val componentName = ComponentName(context, NativePhpWidgetProvider::class.java)
            val appWidgetIds = appWidgetManager.getAppWidgetIds(componentName)

            Log.i(TAG, "updateAllWidgets called. count=${appWidgetIds.size}")

            appWidgetIds.forEach { appWidgetId ->
                updateWidget(context, appWidgetManager, appWidgetId)
            }
        }

        private fun updateWidget(context: Context, appWidgetManager: AppWidgetManager, appWidgetId: Int) {
            val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            val payloadJson = prefs.getString(PAYLOAD_KEY, "{}") ?: "{}"
            val configJson = prefs.getString(CONFIG_KEY, "{}") ?: "{}"
            val imageDataBase64 = prefs.getString(IMAGE_DATA_KEY, "") ?: ""

            val payload = runCatching { JSONObject(payloadJson) }.getOrElse { JSONObject() }
            val config = runCatching { JSONObject(configJson) }.getOrElse { JSONObject() }

            val titleKey = config.optString("widget_title_key", "title")
            val subtitleKey = config.optString("widget_subtitle_key", "subtitle")
            val bodyKey = config.optString("widget_body_key", "body")
            val imageKey = config.optString("widget_image_key", "image_url")
            val statusKey = config.optString("widget_status_key", "status")
            val statusLabelKey = config.optString("widget_status_label_key", "status_label")
            val progressKey = config.optString("widget_progress_key", "progress")
            val updatedAtKey = config.optString("widget_updated_at_key", "updated_at")
            val stateTextKey = config.optString("widget_state_text_key", "state_text")
            val deepLinkPath = config.optString("deep_link_path", "/")
            val widgetStyle = config.optString("widget_style", "card")
            val widgetSize = config.optString("widget_size", "medium")
            val widgetVariant = config.optString("widget_variant", payload.optString("variant", "default"))
            val contentMode = config.optString("widget_content_mode", payload.optString("content_mode", "regular"))

            val title = payload.optString(titleKey, "NativePHP")
            val subtitle = payload.optString(subtitleKey, "")
            val body = payload.optString(bodyKey, "Tap to open app")
            val imageValue = payload.optString(imageKey, payload.optString("image_url", ""))
            val status = payload.optString(statusKey, "")
            val statusLabel = payload.optString(statusLabelKey, "")
            val progressText = payload.optString(progressKey, "")
            val updatedAt = payload.optString(updatedAtKey, "")
            val stateText = payload.optString(stateTextKey, "")

            val resources = context.resources
            val packageName = context.packageName

            val layoutId = resources.getIdentifier("widget_nativephp", "layout", packageName)
            val titleId = resources.getIdentifier("widget_title", "id", packageName)
            val bodyId = resources.getIdentifier("widget_body", "id", packageName)
            val subtitleId = resources.getIdentifier("widget_subtitle", "id", packageName)
            val statusId = resources.getIdentifier("widget_status", "id", packageName)
            val progressId = resources.getIdentifier("widget_progress", "id", packageName)
            val metaId = resources.getIdentifier("widget_meta", "id", packageName)
            val imageId = resources.getIdentifier("widget_image", "id", packageName)
            val rootId = resources.getIdentifier("widget_root", "id", packageName)
            val cardBackgroundId = resources.getIdentifier("widget_bg", "drawable", packageName)
            val compactBackgroundId = resources.getIdentifier("widget_bg_compact", "drawable", packageName)
            val successBackgroundId = resources.getIdentifier("widget_bg_success", "drawable", packageName)
            val warningBackgroundId = resources.getIdentifier("widget_bg_warning", "drawable", packageName)
            val alertBackgroundId = resources.getIdentifier("widget_bg_alert", "drawable", packageName)

            if (layoutId == 0 || titleId == 0 || bodyId == 0 || imageId == 0 || rootId == 0 || subtitleId == 0 || statusId == 0 || progressId == 0 || metaId == 0) {
                Log.e(TAG, "Missing widget resource IDs. layout=$layoutId title=$titleId subtitle=$subtitleId body=$bodyId status=$statusId progress=$progressId meta=$metaId image=$imageId root=$rootId")
                return
            }

            val views = RemoteViews(packageName, layoutId)
            views.setTextViewText(titleId, title)
            views.setTextViewText(subtitleId, subtitle)
            views.setTextViewText(bodyId, body)

            val statusText = if (statusLabel.isNotEmpty()) statusLabel else status
            val progressValue = progressText.toIntOrNull()?.coerceIn(0, 100)
            val metaText = listOf(updatedAt.trim(), stateText.trim())
                .filter { it.isNotEmpty() }
                .joinToString(" · ")

            val compactMode = widgetStyle == "compact" || widgetSize == "small" || contentMode == "compact"
            val detailedMode = contentMode == "detailed"
            val backgroundId = if (compactMode) {
                compactBackgroundId
            } else {
                when (widgetVariant) {
                    "success" -> successBackgroundId
                    "warning" -> warningBackgroundId
                    "alert" -> alertBackgroundId
                    else -> cardBackgroundId
                }
            }

            if (backgroundId != 0) {
                views.setInt(rootId, "setBackgroundResource", backgroundId)
            }

            if (compactMode) {
                views.setViewVisibility(bodyId, View.GONE)
                views.setViewVisibility(subtitleId, if (subtitle.isEmpty()) View.GONE else View.VISIBLE)
                views.setViewVisibility(statusId, View.GONE)
                views.setViewVisibility(progressId, View.GONE)
                views.setViewVisibility(metaId, View.GONE)
                views.setViewPadding(rootId, 10, 10, 10, 10)
                views.setTextViewTextSize(titleId, TypedValue.COMPLEX_UNIT_SP, 13f)
            } else {
                views.setViewVisibility(bodyId, View.VISIBLE)
                views.setViewVisibility(subtitleId, if (subtitle.isEmpty()) View.GONE else View.VISIBLE)
                views.setViewVisibility(statusId, if (statusText.isEmpty()) View.GONE else View.VISIBLE)
                views.setTextViewText(statusId, statusText)
                views.setViewVisibility(progressId, if (progressValue == null) View.GONE else View.VISIBLE)
                views.setViewVisibility(metaId, if (metaText.isEmpty()) View.GONE else View.VISIBLE)
                views.setTextViewText(metaId, metaText)
                views.setViewPadding(rootId, 12, 12, 12, 12)
                views.setTextViewTextSize(titleId, TypedValue.COMPLEX_UNIT_SP, 14f)
                views.setTextViewTextSize(bodyId, TypedValue.COMPLEX_UNIT_SP, 12f)
                views.setTextViewTextSize(subtitleId, TypedValue.COMPLEX_UNIT_SP, 11f)

                if (progressValue != null) {
                    views.setProgressBar(progressId, 100, progressValue, false)
                }

                if (!detailedMode) {
                    views.setViewVisibility(metaId, View.GONE)
                }
            }

            val statusColor = when (status.lowercase()) {
                "live", "ok", "healthy", "success" -> "#86EFAC"
                "warning", "paused" -> "#FCD34D"
                "error", "failed", "alert" -> "#FCA5A5"
                else -> "#FFFFFF"
            }
            views.setTextColor(statusId, Color.parseColor(statusColor))

            val trimmedImage = imageValue.trim()
            val cachedBitmap = decodeCachedBitmap(imageDataBase64)

            Log.i(TAG, "Rendering widget id=$appWidgetId compact=$compactMode title='$title' body='$body' image='${if (trimmedImage.isEmpty()) "(none)" else trimmedImage}'")

            if (!compactMode && cachedBitmap != null) {
                views.setImageViewBitmap(imageId, cachedBitmap)
                views.setViewVisibility(imageId, View.VISIBLE)
            } else if (trimmedImage.isNotEmpty()) {
                views.setViewVisibility(imageId, View.GONE)
            } else {
                views.setViewVisibility(imageId, View.GONE)
            }

            val normalizedPath = if (deepLinkPath.startsWith("/")) deepLinkPath else "/$deepLinkPath"
            val deepLinkUri = Uri.parse("nativephp://app$normalizedPath")

            val deepLinkIntent = (context.packageManager.getLaunchIntentForPackage(context.packageName) ?: Intent()).apply {
                action = Intent.ACTION_VIEW
                data = deepLinkUri
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
            }

            val pendingIntent = PendingIntent.getActivity(
                context,
                appWidgetId,
                deepLinkIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )

            views.setOnClickPendingIntent(rootId, pendingIntent)

            appWidgetManager.updateAppWidget(appWidgetId, views)
            Log.i(TAG, "Updated widget id=$appWidgetId")
        }
    }
}
