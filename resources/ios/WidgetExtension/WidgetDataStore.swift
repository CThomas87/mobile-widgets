import Foundation

struct WidgetDataStore {
    let appGroup: String

    init(appGroup: String? = nil) {
        if let appGroup, !appGroup.isEmpty {
            self.appGroup = appGroup
            return
        }

        let suffix = ".widgets"
        let bundleId = Bundle.main.bundleIdentifier ?? "nativephp.app"
        let baseBundleId = bundleId.hasSuffix(suffix)
            ? String(bundleId.dropLast(suffix.count))
            : bundleId

        self.appGroup = "group.\(baseBundleId).widgets"
    }

    private var defaults: UserDefaults? {
        UserDefaults(suiteName: appGroup)
    }

    func appGroupAvailable() -> Bool {
        defaults != nil
    }

    func payload() -> [String: Any] {
        defaults?.dictionary(forKey: "widget_payload") ?? [:]
    }

    func config() -> [String: Any] {
        defaults?.dictionary(forKey: "widget_config") ?? [:]
    }
}
