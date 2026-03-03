import Foundation
#if canImport(WidgetKit)
import WidgetKit
#endif

enum WidgetFunctions {

    private static let defaultsSuitePrefix = "group."
    private static let defaultsSuffix = ".widgets"

    private static func baseBundleIdentifier() -> String {
        let bundleId = Bundle.main.bundleIdentifier ?? "nativephp.app"

        if bundleId.hasSuffix(defaultsSuffix) {
            return String(bundleId.dropLast(defaultsSuffix.count))
        }

        return bundleId
    }

    private static func appGroupName(from parameters: [String: Any]) -> String {
        if let appGroup = parameters["app_group"] as? String, !appGroup.isEmpty {
            return appGroup
        }

        return "\(defaultsSuitePrefix)\(baseBundleIdentifier())\(defaultsSuffix)"
    }

    private static func sharedDefaults(from parameters: [String: Any]) -> UserDefaults? {
        let appGroup = appGroupName(from: parameters)
        return UserDefaults(suiteName: appGroup)
    }

    private static func resolvePayload(from parameters: [String: Any]) -> [String: Any] {
        if let nestedPayload = parameters["payload"] as? [String: Any], !nestedPayload.isEmpty {
            return nestedPayload
        }

        var flatPayload = parameters
        flatPayload.removeValue(forKey: "app_group")
        flatPayload.removeValue(forKey: "payload")

        return flatPayload
    }

    class SetData: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let defaults = WidgetFunctions.sharedDefaults(from: parameters) else {
                return BridgeResponse.error(code: "WIDGET_APP_GROUP_UNAVAILABLE", message: "Shared app group UserDefaults is unavailable")
            }

            let payload = WidgetFunctions.resolvePayload(from: parameters)

            defaults.set(payload, forKey: "widget_payload")
            defaults.set(Date().timeIntervalSince1970, forKey: "widget_updated_at")

#if canImport(WidgetKit)
            WidgetCenter.shared.reloadAllTimelines()
#endif

            return BridgeResponse.success(data: [
                "saved": true,
                "keys": Array(payload.keys)
            ])
        }
    }

    class ReloadAll: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
#if canImport(WidgetKit)
            WidgetCenter.shared.reloadAllTimelines()
#endif

            return BridgeResponse.success(data: [
                "reloaded": true
            ])
        }
    }

    class Configure: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let defaults = WidgetFunctions.sharedDefaults(from: parameters) else {
                return BridgeResponse.error(code: "WIDGET_APP_GROUP_UNAVAILABLE", message: "Shared app group UserDefaults is unavailable")
            }

            defaults.set(parameters, forKey: "widget_config")

            let workersEnabled = parameters["background_workers_enabled"] as? Bool ?? false
            let scheduledEnabled = parameters["scheduled_tasks_enabled"] as? Bool ?? false
            let schedulingEnabled = workersEnabled && scheduledEnabled

#if canImport(WidgetKit)
            WidgetCenter.shared.reloadAllTimelines()
#endif

            return BridgeResponse.success(data: [
                "configured": true,
                "scheduling_enabled": schedulingEnabled,
            ])
        }
    }

    class GetStatus: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let defaults = WidgetFunctions.sharedDefaults(from: parameters) else {
                let appGroup = WidgetFunctions.appGroupName(from: parameters)

                return BridgeResponse.success(data: [
                    "status": "error",
                    "error_code": "WIDGET_APP_GROUP_UNAVAILABLE",
                    "message": "Shared app group UserDefaults is unavailable",
                    "app_group": appGroup,
                    "payload": [:],
                    "config": [:],
                ])
            }

            let payload = defaults.dictionary(forKey: "widget_payload") ?? [:]
            let config = defaults.dictionary(forKey: "widget_config") ?? [:]

            return BridgeResponse.success(data: [
                "status": "ready",
                "payload": payload,
                "config": config,
            ])
        }
    }
}
