package com.nativephp.plugins.mobile_widgets.widgets

import android.content.Context
import android.util.Log
import androidx.work.Worker
import androidx.work.WorkerParameters

class NativePhpWidgetUpdateWorker(
    context: Context,
    workerParams: WorkerParameters
) : Worker(context, workerParams) {

    companion object {
        private const val TAG = "NativePhpWidgetUpdateWorker"
    }

    override fun doWork(): Result {
        return try {
            NativePhpWidgetProvider.updateAllWidgets(applicationContext)

            Result.success()
        } catch (exception: Exception) {
            Log.e(TAG, "Widget update worker failed", exception)
            Result.retry()
        }
    }
}
