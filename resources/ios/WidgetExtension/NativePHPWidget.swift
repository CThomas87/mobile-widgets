import WidgetKit
import SwiftUI
#if canImport(AppIntents)
import AppIntents
#endif

struct NativePHPWidgetEntry: TimelineEntry {
    let date: Date
    let appGroupReady: Bool
    let title: String
    let subtitle: String
    let body: String
    let deepLinkPath: String
    let imageURL: String?
    let status: String
    let statusLabel: String
    let progress: Int?
    let updatedAt: String
    let stateText: String
    let style: String
    let size: String
    let variant: String
    let contentMode: String
}

struct NativePHPWidgetOverrides {
    let contentMode: String?
    let deepLinkPath: String?
}

struct NativePHPWidgetProvider: TimelineProvider {
    let store = WidgetDataStore()

    func placeholder(in context: Context) -> NativePHPWidgetEntry {
        NativePHPWidgetProvider.placeholderEntry()
    }

    func getSnapshot(in context: Context, completion: @escaping (NativePHPWidgetEntry) -> Void) {
        completion(NativePHPWidgetProvider.makeEntry(store: store, overrides: nil))
    }

    func getTimeline(in context: Context, completion: @escaping (Timeline<NativePHPWidgetEntry>) -> Void) {
        let entry = NativePHPWidgetProvider.makeEntry(store: store, overrides: nil)
        let nextUpdate = Calendar.current.date(byAdding: .minute, value: 30, to: Date()) ?? Date().addingTimeInterval(1800)
        completion(Timeline(entries: [entry], policy: .after(nextUpdate)))
    }

    static func placeholderEntry() -> NativePHPWidgetEntry {
        NativePHPWidgetEntry(
            date: Date(),
            appGroupReady: true,
            title: "NativePHP",
            subtitle: "Build #341",
            body: "Tap to open app",
            deepLinkPath: "/",
            imageURL: nil,
            status: "live",
            statusLabel: "Live",
            progress: 68,
            updatedAt: "just now",
            stateText: "Healthy",
            style: "card",
            size: "medium",
            variant: "default",
            contentMode: "regular"
        )
    }

    static func makeEntry(store: WidgetDataStore, overrides: NativePHPWidgetOverrides?) -> NativePHPWidgetEntry {
        let appGroupReady = store.appGroupAvailable()

        if !appGroupReady {
            return NativePHPWidgetEntry(
                date: Date(),
                appGroupReady: false,
                title: "NativePHP Widget",
                subtitle: "Configuration required",
                body: "Shared App Group is unavailable.",
                deepLinkPath: "/",
                imageURL: nil,
                status: "error",
                statusLabel: "Unavailable",
                progress: nil,
                updatedAt: "",
                stateText: "",
                style: "card",
                size: "medium",
                variant: "alert",
                contentMode: "regular"
            )
        }

        let payload = store.payload()
        let config = store.config()

        let titleKey = config["widget_title_key"] as? String ?? "title"
        let subtitleKey = config["widget_subtitle_key"] as? String ?? "subtitle"
        let bodyKey = config["widget_body_key"] as? String ?? "body"
        let imageKey = config["widget_image_key"] as? String ?? "image_url"
        let statusKey = config["widget_status_key"] as? String ?? "status"
        let statusLabelKey = config["widget_status_label_key"] as? String ?? "status_label"
        let progressKey = config["widget_progress_key"] as? String ?? "progress"
        let updatedAtKey = config["widget_updated_at_key"] as? String ?? "updated_at"
        let stateTextKey = config["widget_state_text_key"] as? String ?? "state_text"
        let deepLinkPath = (overrides?.deepLinkPath?.isEmpty == false)
            ? (overrides?.deepLinkPath ?? "/")
            : (config["deep_link_path"] as? String ?? "/")
        let style = config["widget_style"] as? String ?? "card"
        let size = config["widget_size"] as? String ?? "medium"
        let variant = (config["widget_variant"] as? String) ?? (payload["variant"] as? String) ?? "default"
        let contentMode = overrides?.contentMode
            ?? (config["widget_content_mode"] as? String)
            ?? (payload["content_mode"] as? String)
            ?? "regular"

        let title = payload[titleKey] as? String ?? "NativePHP"
        let subtitle = payload[subtitleKey] as? String ?? ""
        let body = payload[bodyKey] as? String ?? "Tap to open app"
        let imageURL = payload[imageKey] as? String ?? payload["image_url"] as? String
        let status = payload[statusKey] as? String ?? ""
        let statusLabel = payload[statusLabelKey] as? String ?? ""
        let updatedAt = payload[updatedAtKey] as? String ?? ""
        let stateText = payload[stateTextKey] as? String ?? ""

        let rawProgress = payload[progressKey]
        let progressInt = (rawProgress as? Int)
            ?? ((rawProgress as? String).flatMap { Int($0) })
            ?? ((rawProgress as? Double).map { Int($0) })
        let progress = progressInt.map { min(100, max(0, $0)) }

        return NativePHPWidgetEntry(
            date: Date(),
            appGroupReady: true,
            title: title,
            subtitle: subtitle,
            body: body,
            deepLinkPath: deepLinkPath,
            imageURL: imageURL,
            status: status,
            statusLabel: statusLabel,
            progress: progress,
            updatedAt: updatedAt,
            stateText: stateText,
            style: style,
            size: size,
            variant: variant,
            contentMode: contentMode
        )
    }
}

#if canImport(AppIntents)
@available(iOSApplicationExtension 17.0, *)
enum NativePHPWidgetContentMode: String, AppEnum {
    case compact
    case regular
    case detailed

    static var typeDisplayRepresentation: TypeDisplayRepresentation {
        "Content Mode"
    }

    static var caseDisplayRepresentations: [NativePHPWidgetContentMode: DisplayRepresentation] {
        [
            .compact: "Compact",
            .regular: "Regular",
            .detailed: "Detailed",
        ]
    }
}

@available(iOSApplicationExtension 17.0, *)
struct NativePHPWidgetIntent: WidgetConfigurationIntent {
    static var title: LocalizedStringResource = "Widget Options"
    static var description = IntentDescription("Configure widget presentation and launch path.")

    @Parameter(title: "Content Mode", default: .regular)
    var contentMode: NativePHPWidgetContentMode

    @Parameter(title: "Deep Link Path", default: "/")
    var deepLinkPath: String
}

@available(iOSApplicationExtension 17.0, *)
struct NativePHPWidgetIntentProvider: AppIntentTimelineProvider {
    let store = WidgetDataStore()

    func placeholder(in context: Context) -> NativePHPWidgetEntry {
        NativePHPWidgetProvider.placeholderEntry()
    }

    func snapshot(for configuration: NativePHPWidgetIntent, in context: Context) async -> NativePHPWidgetEntry {
        NativePHPWidgetProvider.makeEntry(
            store: store,
            overrides: NativePHPWidgetOverrides(
                contentMode: configuration.contentMode.rawValue,
                deepLinkPath: configuration.deepLinkPath
            )
        )
    }

    func timeline(for configuration: NativePHPWidgetIntent, in context: Context) async -> Timeline<NativePHPWidgetEntry> {
        let entry = NativePHPWidgetProvider.makeEntry(
            store: store,
            overrides: NativePHPWidgetOverrides(
                contentMode: configuration.contentMode.rawValue,
                deepLinkPath: configuration.deepLinkPath
            )
        )

        let nextUpdate = Calendar.current.date(byAdding: .minute, value: 30, to: Date()) ?? Date().addingTimeInterval(1800)

        return Timeline(entries: [entry], policy: .after(nextUpdate))
    }
}
#endif

struct NativePHPWidgetEntryView: View {
    var entry: NativePHPWidgetProvider.Entry

    private var compactMode: Bool {
        entry.style == "compact" || entry.size == "small" || entry.contentMode == "compact"
    }

    private var detailedMode: Bool {
        entry.contentMode == "detailed"
    }

    private var statusText: String {
        if !entry.statusLabel.isEmpty {
            return entry.statusLabel
        }

        return entry.status
    }

    private var metadataText: String {
        [entry.updatedAt, entry.stateText]
            .map { $0.trimmingCharacters(in: .whitespacesAndNewlines) }
            .filter { !$0.isEmpty }
            .joined(separator: " · ")
    }

    private var statusColor: Color {
        switch entry.status.lowercased() {
        case "live", "ok", "healthy", "success":
            return .green
        case "warning", "paused":
            return .yellow
        case "error", "failed", "alert":
            return .red
        default:
            return .white
        }
    }

    private var backgroundColor: Color {
        if compactMode {
            return Color(red: 0.10, green: 0.13, blue: 0.21)
        }

        switch entry.variant {
        case "success":
            return Color(red: 0.08, green: 0.28, blue: 0.18)
        case "warning":
            return Color(red: 0.52, green: 0.29, blue: 0.07)
        case "alert":
            return Color(red: 0.56, green: 0.13, blue: 0.13)
        default:
            return Color(red: 0.09, green: 0.13, blue: 0.23)
        }
    }

    var body: some View {
        let normalizedPath = entry.deepLinkPath.hasPrefix("/") ? entry.deepLinkPath : "/\(entry.deepLinkPath)"

        ZStack {
            backgroundColor

            VStack(alignment: .leading, spacing: compactMode ? 4 : 6) {
                if let imageURL = entry.imageURL,
                   let url = URL(string: imageURL),
                         url.scheme?.lowercased() == "https",
                   !imageURL.isEmpty,
                   !compactMode {
                    AsyncImage(url: url) { phase in
                        switch phase {
                        case .success(let image):
                            image
                                .resizable()
                                .scaledToFill()
                        default:
                            Rectangle()
                                .fill(.quaternary)
                        }
                    }
                    .frame(maxWidth: .infinity)
                    .frame(height: 56)
                    .clipShape(RoundedRectangle(cornerRadius: 8))
                }

                Text(entry.title)
                    .font(compactMode ? .subheadline : .headline)
                    .foregroundStyle(.white)
                    .lineLimit(1)

                if !entry.subtitle.isEmpty {
                    Text(entry.subtitle)
                        .font(.caption2)
                        .foregroundStyle(.white.opacity(0.85))
                        .lineLimit(1)
                }

                if !compactMode, !statusText.isEmpty {
                    Text(statusText)
                        .font(.caption2.bold())
                        .foregroundStyle(statusColor)
                        .lineLimit(1)
                }

                if !compactMode {
                    Text(entry.body)
                        .font(.caption)
                        .foregroundStyle(.white.opacity(0.9))
                        .lineLimit(2)
                }

                if !compactMode, let progress = entry.progress {
                    ProgressView(value: Double(progress), total: 100)
                        .tint(.white)
                }

                if !compactMode, detailedMode, !metadataText.isEmpty {
                    Text(metadataText)
                        .font(.caption2)
                        .foregroundStyle(.white.opacity(0.75))
                        .lineLimit(1)
                }

                if !entry.appGroupReady {
                    Text("App Group unavailable")
                        .font(.caption2)
                        .foregroundStyle(.red)
                        .lineLimit(1)
                }
            }
            .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .leading)
            .padding(compactMode ? 10 : 12)
        }
        .widgetURL(URL(string: "nativephp://app\(normalizedPath)"))
    }
}

struct NativePHPWidget: Widget {
    let kind: String = "NativePHPWidget"

    var body: some WidgetConfiguration {
        if #available(iOSApplicationExtension 17.0, *) {
            AppIntentConfiguration(kind: kind, intent: NativePHPWidgetIntent.self, provider: NativePHPWidgetIntentProvider()) { entry in
                NativePHPWidgetEntryView(entry: entry)
            }
            .configurationDisplayName("NativePHP Widget")
            .description("Shows app data from NativePHP bridge calls.")
            .supportedFamilies([.systemSmall, .systemMedium])
        } else {
            StaticConfiguration(kind: kind, provider: NativePHPWidgetProvider()) { entry in
                NativePHPWidgetEntryView(entry: entry)
            }
            .configurationDisplayName("NativePHP Widget")
            .description("Shows app data from NativePHP bridge calls.")
            .supportedFamilies([.systemSmall, .systemMedium])
        }
    }
}
