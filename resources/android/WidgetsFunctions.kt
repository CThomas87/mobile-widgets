package com.nativephp.plugins.mobile_widgets

import android.content.Context
import android.util.Log
import android.util.Base64
import android.graphics.BitmapFactory
import androidx.work.BackoffPolicy
import androidx.work.Constraints
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.NetworkType
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.bridge.BridgeResponse
import com.nativephp.plugins.mobile_widgets.widgets.NativePhpWidgetProvider
import com.nativephp.plugins.mobile_widgets.widgets.NativePhpWidgetUpdateWorker
import org.json.JSONArray
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL
import java.io.ByteArrayOutputStream
import java.util.concurrent.TimeUnit

object WidgetBridgeFunctions {

    private const val TAG = "WidgetBridgeFunctions"
    private const val PREFS_NAME = "nativephp_mobile_widgets"
    private const val PAYLOAD_KEY = "widget_payload"
    private const val CONFIG_KEY = "widget_config"
    private const val IMAGE_DATA_KEY = "widget_image_data_base64"
    private const val IMAGE_ERROR_KEY = "widget_image_cache_error"
    private const val WORK_NAME = "nativephp_widget_periodic_refresh"
    private const val MAX_IMAGE_BYTES = 1_500_000

    private fun prefs(context: Context) = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)

    private fun mapToJson(map: Map<String, Any>): String = JSONObject(map).toString()

    private fun jsonToMap(json: String): Map<String, Any> {
        val objectValue = runCatching { JSONObject(json) }.getOrElse { JSONObject() }

        fun toAny(value: Any?): Any = when (value) {
            is JSONObject -> {
                val result = mutableMapOf<String, Any>()
                val keys = value.keys()
                while (keys.hasNext()) {
                    val key = keys.next()
                    result[key] = toAny(value.opt(key))
                }
                result
            }
            is JSONArray -> {
                val result = mutableListOf<Any>()
                for (index in 0 until value.length()) {
                    result.add(toAny(value.opt(index)))
                }
                result
            }
            JSONObject.NULL, null -> ""
            else -> value
        }

        return toAny(objectValue) as? Map<String, Any> ?: emptyMap()
    }

    private fun anyMap(value: Any?): Map<String, Any> {
        @Suppress("UNCHECKED_CAST")
        return (value as? Map<String, Any>) ?: emptyMap()
    }

    private fun normalizeMap(value: Any?): Map<String, Any> {
        return when (value) {
            is Map<*, *> -> {
                value.entries
                    .filter { it.key is String }
                    .associate { (it.key as String) to (it.value ?: "") }
            }
            is JSONObject -> jsonToMap(value.toString())
            is String -> jsonToMap(value)
            else -> emptyMap()
        }
    }

    private fun isHttpsUrl(value: String): Boolean {
        return value.trim().startsWith("https://", ignoreCase = true)
    }

    private fun resolvePayload(parameters: Map<String, Any>): Map<String, Any> {
        val nestedPayload = normalizeMap(parameters["payload"])

        if (nestedPayload.isNotEmpty()) {
            return nestedPayload
        }

        return parameters
            .filterKeys { key ->
                key != "app_group" && key != "payload"
            }
            .mapValues { (_, value) -> value }
    }

    private fun resolveImageUrl(payload: Map<String, Any>): String {
        fun asString(value: Any?): String {
            return (value as? String)?.trim().orEmpty()
        }

        val directCandidates = listOf(
            asString(payload["image_url"]),
            asString(payload["image"]),
            asString(payload["imageUrl"])
        )

        val firstDirect = directCandidates.firstOrNull { isHttpsUrl(it) }

        if (firstDirect != null) {
            return firstDirect
        }

        val firstImageLike = payload.entries.firstNotNullOfOrNull { (key, value) ->
            val candidate = asString(value)

            if (key.contains("image", ignoreCase = true) && isHttpsUrl(candidate)) {
                candidate
            } else {
                null
            }
        }

        return firstImageLike ?: ""
    }

    private fun triggerWidgetReload(context: Context) {
        Log.i(TAG, "Triggering widget reload")
        NativePhpWidgetProvider.updateAllWidgets(context)
    }

    private fun schedulePeriodicRefresh(context: Context, intervalMinutes: Long) {
        val request = PeriodicWorkRequestBuilder<NativePhpWidgetUpdateWorker>(
            intervalMinutes,
            TimeUnit.MINUTES
        )
            .setConstraints(
                Constraints.Builder()
                    .setRequiresBatteryNotLow(true)
                    .setRequiredNetworkType(NetworkType.NOT_REQUIRED)
                    .build()
            )
            .setBackoffCriteria(
                BackoffPolicy.EXPONENTIAL,
                15,
                TimeUnit.MINUTES
            )
            .build()

        WorkManager.getInstance(context).enqueueUniquePeriodicWork(
            WORK_NAME,
            ExistingPeriodicWorkPolicy.UPDATE,
            request
        )
    }

    private fun stopPeriodicRefresh(context: Context) {
        WorkManager.getInstance(context).cancelUniqueWork(WORK_NAME)
    }

    private fun validateImageBytes(bytes: ByteArray): String? {
        if (bytes.isEmpty()) {
            return "EMPTY_RESPONSE"
        }

        if (bytes.size > MAX_IMAGE_BYTES) {
            return "IMAGE_TOO_LARGE"
        }

        return try {
            val options = BitmapFactory.Options().apply {
                inJustDecodeBounds = true
            }

            BitmapFactory.decodeByteArray(bytes, 0, bytes.size, options)

            if (options.outWidth <= 0 || options.outHeight <= 0) {
                "INVALID_IMAGE"
            } else {
                null
            }
        } catch (_: Exception) {
            "INVALID_IMAGE"
        }
    }

    private fun downloadImageBytes(urlValue: String): Pair<ByteArray?, String?> {
        return try {
            val connection = (URL(urlValue).openConnection() as HttpURLConnection).apply {
                requestMethod = "GET"
                connectTimeout = 7000
                readTimeout = 10000
                doInput = true
                instanceFollowRedirects = true
                setRequestProperty("User-Agent", "NativePHP-Widgets/1.0")
            }

            connection.connect()

            if (connection.responseCode !in 200..299) {
                val error = "HTTP_${connection.responseCode}"
                Log.e(TAG, "Image download failed. $error")
                connection.disconnect()
                return Pair(null, error)
            }

            val contentLength = connection.contentLengthLong
            if (contentLength > MAX_IMAGE_BYTES) {
                connection.disconnect()
                return Pair(null, "IMAGE_TOO_LARGE")
            }

            val bytes = connection.inputStream.use { input ->
                val buffer = ByteArray(8192)
                val output = ByteArrayOutputStream()
                var total = 0

                while (true) {
                    val read = input.read(buffer)

                    if (read <= 0) {
                        break
                    }

                    total += read

                    if (total > MAX_IMAGE_BYTES) {
                        throw IllegalStateException("IMAGE_TOO_LARGE")
                    }

                    output.write(buffer, 0, read)
                }

                output.toByteArray()
            }
            connection.disconnect()

            val validationError = validateImageBytes(bytes)
            if (validationError != null) {
                Pair(null, validationError)
            } else {
                Pair(bytes, null)
            }
        } catch (e: Exception) {
            if (e.message == "IMAGE_TOO_LARGE") {
                Log.e(TAG, "Image download failed: IMAGE_TOO_LARGE", e)
                return Pair(null, "IMAGE_TOO_LARGE")
            }

            val error = e::class.java.simpleName + ": " + (e.message ?: "unknown")
            Log.e(TAG, "Image download failed: $error", e)
            Pair(null, error)
        }
    }

    class SetData(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val payload = resolvePayload(parameters)
            val payloadJson = mapToJson(payload)
            val imageUrl = resolveImageUrl(payload)

            val editor = prefs(context)
                .edit()
                .putString(PAYLOAD_KEY, payloadJson)
                .remove(IMAGE_ERROR_KEY)

            var imageCached = false
            var imageCacheError: String? = null

            if (isHttpsUrl(imageUrl)) {
                val (bytes, error) = downloadImageBytes(imageUrl)

                if (bytes != null) {
                    editor.putString(IMAGE_DATA_KEY, Base64.encodeToString(bytes, Base64.NO_WRAP))
                    imageCached = true
                } else {
                    editor.remove(IMAGE_DATA_KEY)
                    imageCacheError = error ?: "DOWNLOAD_FAILED"
                    editor.putString(IMAGE_ERROR_KEY, imageCacheError)
                }
            } else {
                editor.remove(IMAGE_DATA_KEY)

                if (imageUrl.isNotEmpty()) {
                    imageCacheError = "IMAGE_URL_MUST_BE_HTTPS"
                    editor.putString(IMAGE_ERROR_KEY, imageCacheError)
                }
            }

            val saved = editor.commit()

            if (!saved) {
                Log.e(TAG, "Failed to persist widget payload")

                return BridgeResponse.error(
                    "WIDGET_PAYLOAD_SAVE_FAILED",
                    "Failed to persist widget payload"
                )
            }

            Log.i(TAG, "Saved widget payload. Keys=${payload.keys}")

            triggerWidgetReload(context)

            return BridgeResponse.success(mapOf(
                "saved" to true,
                "keys" to payload.keys.toList(),
                "image_cached" to imageCached,
                "image_cache_error" to (imageCacheError ?: "")
            ))
        }
    }

    class ReloadAll(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            Log.i(TAG, "ReloadAll called")
            triggerWidgetReload(context)

            return BridgeResponse.success(mapOf(
                "reloaded" to true
            ))
        }
    }

    class Configure(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val options = parameters.toMutableMap()
            val backgroundWorkersEnabled = options["background_workers_enabled"] as? Boolean ?: false
            val scheduledTasksEnabled = options["scheduled_tasks_enabled"] as? Boolean ?: false
            val refreshIntervalMinutes =
                ((options["refresh_interval_minutes"] as? Number)?.toLong() ?: 30L).coerceAtLeast(15L)
            val optionsJson = mapToJson(options)

            val saved = prefs(context)
                .edit()
                .putString(CONFIG_KEY, optionsJson)
                .commit()

            if (!saved) {
                Log.e(TAG, "Failed to persist widget config")

                return BridgeResponse.error(
                    "WIDGET_CONFIG_SAVE_FAILED",
                    "Failed to persist widget config"
                )
            }

            Log.i(TAG, "Saved widget config. Style=${options["widget_style"]}, Size=${options["widget_size"]}")

            val schedulingEnabled = backgroundWorkersEnabled && scheduledTasksEnabled

            if (schedulingEnabled) {
                try {
                    schedulePeriodicRefresh(context, refreshIntervalMinutes)
                } catch (e: Exception) {
                    Log.e(TAG, "Failed to schedule widget updates", e)

                    return BridgeResponse.error(
                        "WIDGET_SCHEDULE_FAILED",
                        "Failed to schedule widget updates: ${e.message ?: "Unknown error"}"
                    )
                }
            } else {
                stopPeriodicRefresh(context)
            }

            return BridgeResponse.success(mapOf(
                "configured" to true,
                "scheduling_enabled" to schedulingEnabled,
                "refresh_interval_minutes" to refreshIntervalMinutes
            ))
        }
    }

    class GetStatus(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val payload = prefs(context).getString(PAYLOAD_KEY, "{}") ?: "{}"
            val config = prefs(context).getString(CONFIG_KEY, "{}") ?: "{}"
            val imageCached = !prefs(context).getString(IMAGE_DATA_KEY, "").isNullOrEmpty()
            val imageCacheError = prefs(context).getString(IMAGE_ERROR_KEY, "") ?: ""

            Log.i(TAG, "GetStatus called")

            return BridgeResponse.success(mapOf(
                "status" to "ready",
                "payload" to jsonToMap(payload),
                "config" to jsonToMap(config),
                "image_cached" to imageCached,
                "image_cache_error" to imageCacheError
            ))
        }
    }
}
