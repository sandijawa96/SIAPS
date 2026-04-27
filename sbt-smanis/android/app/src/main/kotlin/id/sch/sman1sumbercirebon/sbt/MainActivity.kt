package id.sch.sman1sumbercirebon.sbt

import android.app.ActivityManager
import android.app.NotificationManager
import android.content.Intent
import android.content.IntentFilter
import android.net.Uri
import android.os.BatteryManager
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.provider.Settings
import android.view.View
import android.view.WindowInsets
import android.view.WindowInsetsController
import android.view.WindowManager
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterActivity() {
    private val channelName = "id.sch.sman1sumbercirebon.sbt/guard"
    private var methodChannel: MethodChannel? = null
    private var guardEnabled = false
    private var lockTaskRequested = false
    private var requireScreenPinning = true

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)

        methodChannel = MethodChannel(
            flutterEngine.dartExecutor.binaryMessenger,
            channelName
        )

        methodChannel?.setMethodCallHandler { call, result ->
            when (call.method) {
                "enableExamGuard" -> {
                    enableExamGuard(call.argument<Boolean>("requireScreenPinning") ?: true)
                    result.success(null)
                }
                "disableExamGuard" -> {
                    disableExamGuard()
                    result.success(null)
                }
                "isInMultiWindowMode" -> {
                    result.success(isDeviceInMultiWindowMode())
                }
                "getBatteryInfo" -> {
                    result.success(getBatteryInfo())
                }
                "getDoNotDisturbStatus" -> {
                    result.success(getDoNotDisturbStatus())
                }
                "getOverlayProtectionStatus" -> {
                    result.success(getOverlayProtectionStatus())
                }
                "getScreenPinningStatus" -> {
                    result.success(getScreenPinningStatus())
                }
                "requestScreenPinning" -> {
                    requestScreenPinning(result)
                }
                "openDoNotDisturbSettings" -> {
                    openDoNotDisturbSettings()
                    result.success(null)
                }
                "openExternalUrl" -> {
                    openExternalUrl(call.argument<String>("url"))
                    result.success(null)
                }
                else -> result.notImplemented()
            }
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        window.setSoftInputMode(WindowManager.LayoutParams.SOFT_INPUT_ADJUST_RESIZE)
    }

    override fun onResume() {
        super.onResume()
        if (guardEnabled) {
            enterImmersiveMode()
            if (requireScreenPinning) {
                startExamLockTask()
            }
        }
    }

    override fun onPause() {
        if (guardEnabled) {
            sendGuardEvent(
                "APP_PAUSED",
                "Aplikasi ujian sempat keluar dari tampilan utama."
            )
        }
        super.onPause()
    }

    override fun onStop() {
        if (guardEnabled) {
            sendGuardEvent(
                "APP_STOPPED",
                "Aplikasi ujian masuk latar belakang."
            )
        }
        super.onStop()
    }

    override fun onWindowFocusChanged(hasFocus: Boolean) {
        super.onWindowFocusChanged(hasFocus)
        if (!guardEnabled) return

        if (hasFocus) {
            enterImmersiveMode()
        } else {
            sendGuardEvent(
                "WINDOW_FOCUS_LOST",
                "Fokus layar ujian terganggu. Tutup notifikasi atau aplikasi mengambang."
            )
        }
    }

    override fun onMultiWindowModeChanged(isInMultiWindowMode: Boolean) {
        super.onMultiWindowModeChanged(isInMultiWindowMode)
        if (guardEnabled && isInMultiWindowMode) {
            sendGuardEvent(
                "MULTI_WINDOW",
                "Split-screen atau floating window terdeteksi."
            )
        }
    }

    override fun onPictureInPictureModeChanged(isInPictureInPictureMode: Boolean) {
        super.onPictureInPictureModeChanged(isInPictureInPictureMode)
        if (guardEnabled && isInPictureInPictureMode) {
            sendGuardEvent(
                "PIP_MODE",
                "Mode picture-in-picture tidak diperbolehkan saat ujian."
            )
        }
    }

    private fun enableExamGuard(requireScreenPinning: Boolean) {
        guardEnabled = true
        this.requireScreenPinning = requireScreenPinning
        window.addFlags(
            WindowManager.LayoutParams.FLAG_SECURE or
                WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON
        )

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            window.setHideOverlayWindows(true)
        }

        enterImmersiveMode()
        if (requireScreenPinning) {
            startExamLockTask()
        }
    }

    private fun disableExamGuard() {
        guardEnabled = false
        stopExamLockTask()
        window.clearFlags(
            WindowManager.LayoutParams.FLAG_SECURE or
                WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON
        )

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            window.setHideOverlayWindows(false)
        }

        exitImmersiveMode()
    }

    private fun startExamLockTask() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.LOLLIPOP) {
            return
        }

        if (lockTaskRequested && isLockTaskModeActive()) {
            return
        }

        try {
            startLockTask()
            lockTaskRequested = true
            verifyLockTaskStarted()
        } catch (error: IllegalStateException) {
            sendGuardEvent(
                "LOCK_TASK_UNAVAILABLE",
                "Mode kunci aplikasi belum aktif di perangkat ini."
            )
        } catch (error: SecurityException) {
            sendGuardEvent(
                "LOCK_TASK_UNAVAILABLE",
                "Perangkat belum mengizinkan mode kunci aplikasi."
            )
        }
    }

    private fun requestScreenPinning(result: MethodChannel.Result) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.LOLLIPOP) {
            result.success(getScreenPinningStatus())
            return
        }

        if (!isLockTaskModeActive()) {
            try {
                startLockTask()
                lockTaskRequested = true
            } catch (error: IllegalStateException) {
                sendGuardEvent(
                    "LOCK_TASK_UNAVAILABLE",
                    "Mode kunci aplikasi belum aktif di perangkat ini."
                )
            } catch (error: SecurityException) {
                sendGuardEvent(
                    "LOCK_TASK_UNAVAILABLE",
                    "Perangkat belum mengizinkan mode kunci aplikasi."
                )
            }
        }

        Handler(Looper.getMainLooper()).postDelayed({
            result.success(getScreenPinningStatus())
        }, 900)
    }

    private fun verifyLockTaskStarted() {
        Handler(Looper.getMainLooper()).postDelayed({
            if (guardEnabled && requireScreenPinning && !isLockTaskModeActive()) {
                sendGuardEvent(
                    "LOCK_TASK_NOT_ACTIVE",
                    "Screen pinning belum aktif. Setujui sematan layar sebelum melanjutkan ujian."
                )
            }
        }, 1200)
    }

    private fun stopExamLockTask() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.LOLLIPOP || !lockTaskRequested) {
            return
        }

        try {
            if (isLockTaskModeActive()) {
                stopLockTask()
            }
        } catch (_: IllegalStateException) {
        } catch (_: SecurityException) {
        } finally {
            lockTaskRequested = false
        }
    }

    private fun isLockTaskModeActive(): Boolean {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.LOLLIPOP) {
            return false
        }

        val activityManager = getSystemService(ACTIVITY_SERVICE) as ActivityManager
        return activityManager.lockTaskModeState != ActivityManager.LOCK_TASK_MODE_NONE
    }

    @Suppress("DEPRECATION")
    private fun enterImmersiveMode() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            window.insetsController?.let { controller ->
                controller.hide(
                    WindowInsets.Type.statusBars() or WindowInsets.Type.navigationBars()
                )
                controller.systemBarsBehavior =
                    WindowInsetsController.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE
            }
        } else {
            window.decorView.systemUiVisibility =
                View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY or
                    View.SYSTEM_UI_FLAG_FULLSCREEN or
                    View.SYSTEM_UI_FLAG_HIDE_NAVIGATION or
                    View.SYSTEM_UI_FLAG_LAYOUT_FULLSCREEN or
                    View.SYSTEM_UI_FLAG_LAYOUT_HIDE_NAVIGATION or
                    View.SYSTEM_UI_FLAG_LAYOUT_STABLE
        }
    }

    @Suppress("DEPRECATION")
    private fun exitImmersiveMode() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            window.insetsController?.show(
                WindowInsets.Type.statusBars() or WindowInsets.Type.navigationBars()
            )
        } else {
            window.decorView.systemUiVisibility = View.SYSTEM_UI_FLAG_VISIBLE
        }
    }

    private fun isDeviceInMultiWindowMode(): Boolean {
        return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
            isInMultiWindowMode
        } else {
            false
        }
    }

    private fun getBatteryInfo(): Map<String, Any> {
        val batteryManager = getSystemService(BATTERY_SERVICE) as BatteryManager
        val batteryIntent = registerReceiver(null, IntentFilter(Intent.ACTION_BATTERY_CHANGED))
        var level = -1

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            level = batteryManager.getIntProperty(BatteryManager.BATTERY_PROPERTY_CAPACITY)
        }

        if (level < 0 || level > 100) {
            val rawLevel = batteryIntent?.getIntExtra(BatteryManager.EXTRA_LEVEL, -1) ?: -1
            val scale = batteryIntent?.getIntExtra(BatteryManager.EXTRA_SCALE, -1) ?: -1
            if (rawLevel >= 0 && scale > 0) {
                level = ((rawLevel.toFloat() / scale.toFloat()) * 100).toInt()
            }
        }

        val status = batteryIntent?.getIntExtra(BatteryManager.EXTRA_STATUS, -1) ?: -1
        val isCharging = status == BatteryManager.BATTERY_STATUS_CHARGING ||
            status == BatteryManager.BATTERY_STATUS_FULL

        return mapOf(
            "level" to level,
            "isCharging" to isCharging
        )
    }

    private fun getDoNotDisturbStatus(): Map<String, Any> {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.M) {
            return mapOf(
                "supported" to false,
                "permissionGranted" to false,
                "enabled" to false,
                "label" to "Tidak didukung"
            )
        }

        val manager = getSystemService(NOTIFICATION_SERVICE) as NotificationManager
        val permissionGranted = manager.isNotificationPolicyAccessGranted
        val filter = manager.currentInterruptionFilter
        val enabled = permissionGranted &&
            filter != NotificationManager.INTERRUPTION_FILTER_ALL &&
            filter != NotificationManager.INTERRUPTION_FILTER_UNKNOWN

        return mapOf(
            "supported" to true,
            "permissionGranted" to permissionGranted,
            "enabled" to enabled,
            "label" to interruptionFilterLabel(filter)
        )
    }

    private fun interruptionFilterLabel(filter: Int): String {
        return when (filter) {
            NotificationManager.INTERRUPTION_FILTER_ALL -> "Semua notifikasi aktif"
            NotificationManager.INTERRUPTION_FILTER_PRIORITY -> "Prioritas saja"
            NotificationManager.INTERRUPTION_FILTER_NONE -> "Tanpa gangguan"
            NotificationManager.INTERRUPTION_FILTER_ALARMS -> "Alarm saja"
            else -> "Tidak diketahui"
        }
    }

    private fun getOverlayProtectionStatus(): Map<String, Any> {
        val supported = Build.VERSION.SDK_INT >= Build.VERSION_CODES.S
        return mapOf(
            "supported" to supported,
            "enabled" to (supported && guardEnabled)
        )
    }

    private fun getScreenPinningStatus(): Map<String, Any> {
        val supported = Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP
        val active = supported && isLockTaskModeActive()
        val label = when {
            !supported -> "Android perangkat belum mendukung sematan layar."
            active -> "Sematan layar aktif."
            else -> "Sematan layar belum aktif."
        }

        return mapOf(
            "supported" to supported,
            "active" to active,
            "canRequest" to (supported && !active),
            "label" to label
        )
    }

    private fun openDoNotDisturbSettings() {
        val intent = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            Intent(Settings.ACTION_NOTIFICATION_POLICY_ACCESS_SETTINGS)
        } else {
            Intent(Settings.ACTION_SETTINGS)
        }
        startActivity(intent)
    }

    private fun openExternalUrl(url: String?) {
        if (url.isNullOrBlank()) {
            return
        }

        val intent = Intent(Intent.ACTION_VIEW, Uri.parse(url))
        startActivity(intent)
    }

    private fun sendGuardEvent(type: String, message: String) {
        methodChannel?.invokeMethod(
            "guardEvent",
            mapOf(
                "type" to type,
                "message" to message
            )
        )
    }
}
