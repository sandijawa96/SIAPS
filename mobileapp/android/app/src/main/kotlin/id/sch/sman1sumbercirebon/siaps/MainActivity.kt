package id.sch.sman1sumbercirebon.siaps

import android.content.Intent
import android.content.pm.ApplicationInfo
import android.content.pm.PackageInfo
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.os.Debug
import android.provider.Settings
import android.text.TextUtils
import androidx.core.content.FileProvider
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel
import java.io.File
import java.security.MessageDigest
import java.util.Locale

class MainActivity : FlutterActivity() {
    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)

        configureAppUpdateChannel(flutterEngine)
        configureDeviceSecurityChannel(flutterEngine)
    }

    private fun configureAppUpdateChannel(flutterEngine: FlutterEngine) {
        MethodChannel(
            flutterEngine.dartExecutor.binaryMessenger,
            UPDATE_CHANNEL
        ).setMethodCallHandler { call, result ->
            when (call.method) {
                "installApk" -> {
                    val filePath = call.argument<String>("filePath")
                    if (filePath.isNullOrBlank()) {
                        result.error("INVALID_ARGUMENT", "filePath is required.", null)
                        return@setMethodCallHandler
                    }

                    try {
                        openAndroidInstaller(filePath)
                        result.success(true)
                    } catch (exception: Exception) {
                        result.error("INSTALL_FAILED", exception.message, null)
                    }
                }

                else -> result.notImplemented()
            }
        }
    }

    private fun configureDeviceSecurityChannel(flutterEngine: FlutterEngine) {
        MethodChannel(
            flutterEngine.dartExecutor.binaryMessenger,
            SECURITY_CHANNEL
        ).setMethodCallHandler { call, result ->
            when (call.method) {
                "collectSecuritySignals" -> {
                    try {
                        result.success(collectSecuritySignals())
                    } catch (exception: Exception) {
                        result.error("SECURITY_COLLECTION_FAILED", exception.message, null)
                    }
                }

                else -> result.notImplemented()
            }
        }
    }

    private fun openAndroidInstaller(filePath: String) {
        val apkFile = File(filePath)
        require(apkFile.exists()) { "File update tidak ditemukan." }

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O &&
            !packageManager.canRequestPackageInstalls()
        ) {
            val settingsIntent = Intent(
                Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES,
                Uri.parse("package:$packageName")
            ).apply {
                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            }
            startActivity(settingsIntent)
            throw IllegalStateException(
                "Izinkan instalasi dari sumber ini, lalu jalankan update lagi."
            )
        }

        val apkUri = FileProvider.getUriForFile(
            this,
            "$packageName.fileprovider",
            apkFile
        )

        val installIntent = Intent(Intent.ACTION_VIEW).apply {
            setDataAndType(apkUri, APK_MIME_TYPE)
            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
        }

        startActivity(installIntent)
    }

    private fun collectSecuritySignals(): Map<String, Any?> {
        val packageInfo = loadPackageInfo()
        val instrumentationSignals = detectInstrumentationSignals()
        val rootDetected = isRootDetected()
        val magiskRisk = isMagiskRiskDetected()
        val developerOptionsEnabled = isDeveloperOptionsEnabled()
        val adbEnabled = isAdbEnabled()
        val cloneRisk = detectCloneAppRisk()
        val installerSource = resolveInstallerSource()
        val signatureSha256 = resolveSignatureSha256(packageInfo)
        val suspiciousDeviceState = rootDetected ||
            magiskRisk ||
            cloneRisk ||
            instrumentationSignals["instrumentation_detected"] == true ||
            instrumentationSignals["xposed_detected"] == true ||
            instrumentationSignals["frida_detected"] == true

        return mapOf(
            "package_name" to packageName,
            "installer_source" to installerSource,
            "signature_sha256" to signatureSha256,
            "developer_options_enabled" to developerOptionsEnabled,
            "adb_enabled" to adbEnabled,
            "usb_debugging_enabled" to adbEnabled,
            "root_detected" to rootDetected,
            "magisk_risk" to magiskRisk,
            "app_clone_risk" to cloneRisk,
            "suspicious_device_state" to suspiciousDeviceState,
            "build_tags" to (Build.TAGS ?: ""),
            "build_fingerprint" to (Build.FINGERPRINT ?: ""),
            "is_debuggable_build" to isDebuggableBuild(),
            "security_detector_version" to "android-native-v1",
            "detected_at" to System.currentTimeMillis(),
        ) + instrumentationSignals
    }

    private fun loadPackageInfo(): PackageInfo? {
        return try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                packageManager.getPackageInfo(
                    packageName,
                    PackageManager.PackageInfoFlags.of(PackageManager.GET_SIGNING_CERTIFICATES.toLong())
                )
            } else {
                @Suppress("DEPRECATION")
                packageManager.getPackageInfo(packageName, PackageManager.GET_SIGNING_CERTIFICATES)
            }
        } catch (_: Exception) {
            null
        }
    }

    private fun isDeveloperOptionsEnabled(): Boolean {
        return try {
            Settings.Global.getInt(
                contentResolver,
                Settings.Global.DEVELOPMENT_SETTINGS_ENABLED,
                0
            ) == 1
        } catch (_: Exception) {
            false
        }
    }

    private fun isAdbEnabled(): Boolean {
        return try {
            Settings.Global.getInt(
                contentResolver,
                Settings.Global.ADB_ENABLED,
                0
            ) == 1
        } catch (_: Exception) {
            false
        }
    }

    private fun isRootDetected(): Boolean {
        if ((Build.TAGS ?: "").contains("test-keys", ignoreCase = true)) {
            return true
        }

        val suspiciousPaths = listOf(
            "/system/app/Superuser.apk",
            "/sbin/su",
            "/system/bin/su",
            "/system/xbin/su",
            "/data/local/xbin/su",
            "/data/local/bin/su",
            "/system/sd/xbin/su",
            "/system/bin/failsafe/su",
            "/data/local/su",
            "/su/bin/su",
            "/system/xbin/daemonsu",
            "/sbin/magisk"
        )

        if (suspiciousPaths.any { File(it).exists() }) {
            return true
        }

        return try {
            val process = Runtime.getRuntime().exec(arrayOf("/system/xbin/which", "su"))
            process.inputStream.bufferedReader().use { !it.readLine().isNullOrBlank() }
        } catch (_: Exception) {
            false
        }
    }

    private fun isMagiskRiskDetected(): Boolean {
        val magiskPaths = listOf(
            "/sbin/magisk",
            "/data/adb/magisk",
            "/cache/.disable_magisk",
            "/dev/.magisk.unblock",
            "/data/adb/modules"
        )

        return magiskPaths.any { File(it).exists() }
    }

    private fun detectCloneAppRisk(): Boolean {
        val dataDir = applicationInfo.dataDir?.lowercase(Locale.ROOT).orEmpty()
        val sourceDir = applicationInfo.sourceDir?.lowercase(Locale.ROOT).orEmpty()
        val nativeDir = applicationInfo.nativeLibraryDir?.lowercase(Locale.ROOT).orEmpty()
        val suspiciousKeywords = listOf("dual", "clone", "parallel", "virtual", "multiapp")

        return suspiciousKeywords.any { keyword ->
            dataDir.contains(keyword) || sourceDir.contains(keyword) || nativeDir.contains(keyword)
        }
    }

    private fun detectInstrumentationSignals(): Map<String, Boolean> {
        val xposedDetected = isClassAvailable("de.robv.android.xposed.XposedBridge") ||
            isClassAvailable("org.lsposed.hiddenapibypass.HiddenApiBypass")
        val substrateDetected = isClassAvailable("com.saurik.substrate.MS\$2")
        val fridaDetected = fileContainsAnyKeyword(
            "/proc/self/maps",
            listOf("frida", "frida-agent", "frida-gadget")
        ) || fileContainsAnyKeyword("/proc/net/unix", listOf("frida"))
        val hookingDetected = fileContainsAnyKeyword(
            "/proc/self/maps",
            listOf("xposed", "lsposed", "zygisk", "substrate", "edxp")
        ) || substrateDetected
        val tracerDetected = resolveTracerPid() > 0
        val instrumentationDetected = xposedDetected || fridaDetected || hookingDetected || tracerDetected

        return mapOf(
            "instrumentation_detected" to instrumentationDetected,
            "xposed_detected" to xposedDetected,
            "frida_detected" to fridaDetected,
            "hooking_detected" to hookingDetected,
            "debugger_connected" to (Debug.isDebuggerConnected() || Debug.waitingForDebugger())
        )
    }

    private fun resolveTracerPid(): Int {
        return try {
            File("/proc/self/status").useLines { lines ->
                lines.firstOrNull { it.startsWith("TracerPid:") }
                    ?.substringAfter(':')
                    ?.trim()
                    ?.toIntOrNull() ?: 0
            }
        } catch (_: Exception) {
            0
        }
    }

    private fun fileContainsAnyKeyword(path: String, keywords: List<String>): Boolean {
        return try {
            val loweredKeywords = keywords.map { it.lowercase(Locale.ROOT) }
            File(path).useLines { lines ->
                lines.any { line ->
                    val normalized = line.lowercase(Locale.ROOT)
                    loweredKeywords.any { normalized.contains(it) }
                }
            }
        } catch (_: Exception) {
            false
        }
    }

    private fun isClassAvailable(className: String): Boolean {
        return try {
            Class.forName(className)
            true
        } catch (_: Exception) {
            false
        }
    }

    private fun resolveInstallerSource(): String? {
        val installer = try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
                packageManager.getInstallSourceInfo(packageName).installingPackageName
            } else {
                @Suppress("DEPRECATION")
                packageManager.getInstallerPackageName(packageName)
            }
        } catch (_: Exception) {
            null
        }

        return installer?.takeIf { !TextUtils.isEmpty(it) }
    }

    private fun resolveSignatureSha256(packageInfo: PackageInfo?): String? {
        if (packageInfo == null) {
            return null
        }

        return try {
            val signatures = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
                val signingInfo = packageInfo.signingInfo ?: return null
                if (signingInfo.hasMultipleSigners()) {
                    signingInfo.apkContentsSigners
                } else {
                    signingInfo.signingCertificateHistory
                }
            } else {
                @Suppress("DEPRECATION")
                packageInfo.signatures
            }

            val firstSignature = signatures?.firstOrNull() ?: return null
            val digest = MessageDigest.getInstance("SHA-256")
                .digest(firstSignature.toByteArray())

            digest.joinToString("") { byte -> "%02x".format(byte) }
        } catch (_: Exception) {
            null
        }
    }

    private fun isDebuggableBuild(): Boolean {
        return (applicationInfo.flags and ApplicationInfo.FLAG_DEBUGGABLE) != 0
    }

    companion object {
        private const val UPDATE_CHANNEL = "siaps/app_update"
        private const val SECURITY_CHANNEL = "siaps/device_security"
        private const val APK_MIME_TYPE = "application/vnd.android.package-archive"
    }
}
