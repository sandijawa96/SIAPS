import Flutter
import UIKit

@main
@objc class AppDelegate: FlutterAppDelegate {
  private let guardChannelName = "id.sch.sman1sumbercirebon.sbt/guard"
  private var guardChannel: FlutterMethodChannel?
  private var guardEnabled = false

  override func application(
    _ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
  ) -> Bool {
    if let controller = window?.rootViewController as? FlutterViewController {
      let channel = FlutterMethodChannel(
        name: guardChannelName,
        binaryMessenger: controller.binaryMessenger
      )
      guardChannel = channel
      channel.setMethodCallHandler { [weak self] call, result in
        self?.handleGuardMethod(call, result: result)
      }
    }

    GeneratedPluginRegistrant.register(with: self)
    return super.application(application, didFinishLaunchingWithOptions: launchOptions)
  }

  override func applicationWillResignActive(_ application: UIApplication) {
    if guardEnabled {
      sendGuardEvent(
        type: "IOS_APP_INACTIVE",
        message: "Aplikasi ujian kehilangan fokus sementara."
      )
    }
    super.applicationWillResignActive(application)
  }

  override func applicationDidEnterBackground(_ application: UIApplication) {
    if guardEnabled {
      sendGuardEvent(
        type: "IOS_APP_BACKGROUND",
        message: "Aplikasi ujian masuk latar belakang."
      )
    }
    super.applicationDidEnterBackground(application)
  }

  override func applicationWillTerminate(_ application: UIApplication) {
    if guardEnabled {
      sendGuardEvent(
        type: "IOS_APP_HIDDEN",
        message: "Aplikasi ujian ditutup dari iOS."
      )
    }
    super.applicationWillTerminate(application)
  }

  private func handleGuardMethod(_ call: FlutterMethodCall, result: FlutterResult) {
    switch call.method {
    case "enableExamGuard":
      guardEnabled = true
      UIApplication.shared.isIdleTimerDisabled = true
      result(nil)
    case "disableExamGuard":
      guardEnabled = false
      UIApplication.shared.isIdleTimerDisabled = false
      result(nil)
    case "isInMultiWindowMode":
      result(false)
    case "getBatteryInfo":
      result(getBatteryInfo())
    case "getDoNotDisturbStatus":
      result([
        "supported": false,
        "permissionGranted": false,
        "enabled": false,
        "label": "iOS tidak membuka status Jangan Ganggu untuk aplikasi biasa"
      ])
    case "getOverlayProtectionStatus":
      result([
        "supported": false,
        "enabled": false
      ])
    case "getScreenPinningStatus", "requestScreenPinning":
      result([
        "supported": false,
        "active": false,
        "canRequest": false,
        "label": "iOS memakai deteksi keluar aplikasi, bukan screen pinning"
      ])
    case "openDoNotDisturbSettings":
      if let url = URL(string: UIApplication.openSettingsURLString) {
        UIApplication.shared.open(url)
      }
      result(nil)
    case "openExternalUrl":
      if
        let arguments = call.arguments as? [String: Any],
        let urlString = arguments["url"] as? String,
        let url = URL(string: urlString)
      {
        UIApplication.shared.open(url)
      }
      result(nil)
    default:
      result(FlutterMethodNotImplemented)
    }
  }

  private func getBatteryInfo() -> [String: Any] {
    UIDevice.current.isBatteryMonitoringEnabled = true
    let rawLevel = UIDevice.current.batteryLevel
    let level = rawLevel < 0 ? -1 : Int(rawLevel * 100)
    let state = UIDevice.current.batteryState

    return [
      "level": level,
      "isCharging": state == .charging || state == .full
    ]
  }

  private func sendGuardEvent(type: String, message: String) {
    guardChannel?.invokeMethod(
      "guardEvent",
      arguments: [
        "type": type,
        "message": message
      ]
    )
  }
}
