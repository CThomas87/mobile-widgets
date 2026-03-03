# Mobile Widgets MVP Plan (Android + iOS)

This document proposes a practical way to add home-screen widgets to NativePHP Mobile using the existing plugin system.

Related: on-device transcription plugin contract is documented in [docs/transcription-plugin-mvp.md](transcription-plugin-mvp.md).

## Goal

Ship a cross-platform plugin (`vendor/plugin-widgets`) with:

- Android widget support first (AppWidgetProvider + RemoteViews)
- iOS widget support second (WidgetKit extension)
- A shared PHP API surface such as:
  - `Widget::setData(array $payload)`
  - `Widget::reloadAll()`
  - `Widget::configure(array $options)`
- A shared JavaScript API surface such as:
  - `widget.setData(payload)`
  - `widget.reloadAll()`
  - `widget.configure(options)`

---

## Recommended Delivery Phases

## Phase 1 — Android MVP (lowest risk)

Use current plugin support for Android manifest injection (`receivers`, `services`, `providers`) and bridge functions.

### Android MVP Scope

- One static layout widget (small/medium)
- Data loaded from shared storage (JSON)
- Manual refresh from app action + optional periodic refresh worker
- Deep link from widget tap into app route

### Android plugin layout (example)

```text
packages/vendor/plugin-widgets/
  composer.json
  nativephp.json
  src/
    WidgetsServiceProvider.php
    Facades/Widget.php
    WidgetManager.php
  resources/
    android/
      WidgetBridgeFunctions.kt
      widgets/
        NativePhpWidgetProvider.kt
        NativePhpWidgetUpdateWorker.kt
      res/
        layout/widget_nativephp.xml
        xml/nativephp_widget_info.xml
        drawable/widget_bg.xml
    ios/
      Functions.swift
    js/
      widget.js
```

### Android bridge functions to expose

- `Widget.SetData` (save JSON payload + trigger update)
- `Widget.ReloadAll` (force redraw)
- `Widget.Configure` (save preferences)

These same bridge methods should be callable from JavaScript via `bridgeCall(...)` wrappers.

---

## Phase 2 — iOS MVP (higher effort)

Implement WidgetKit in an app extension target (`.appex`).

### iOS MVP Scope

- One static timeline widget
- Data from App Group shared `UserDefaults` / shared file
- Reload timeline from app bridge call
- Tap action opens app deep link

### iOS plugin layout additions (example)

```text
resources/ios/
  Functions.swift
  WidgetExtension/
    NativePHPWidgetsBundle.swift
    NativePHPWidget.swift
    WidgetDataStore.swift
    NativePHPWidgets.entitlements
    Info.plist
```

---

## Example `nativephp.json` (plugin manifest)

```json
{
  "namespace": "Widgets",
  "bridge_functions": [
    {
      "name": "Widget.SetData",
      "android": "com.vendor.plugins.widgets.WidgetBridgeFunctions.SetData",
      "ios": "WidgetFunctions.SetData"
    },
    {
      "name": "Widget.ReloadAll",
      "android": "com.vendor.plugins.widgets.WidgetBridgeFunctions.ReloadAll",
      "ios": "WidgetFunctions.ReloadAll"
    },
    {
      "name": "Widget.Configure",
      "android": "com.vendor.plugins.widgets.WidgetBridgeFunctions.Configure",
      "ios": "WidgetFunctions.Configure"
    }
  ],
  "android": {
    "permissions": [],
    "receivers": [
      {
        "name": "com.vendor.plugins.widgets.widgets.NativePhpWidgetProvider",
        "exported": true,
        "intent_filters": [
          {
            "action": "android.appwidget.action.APPWIDGET_UPDATE"
          }
        ],
        "meta_data": [
          {
            "name": "android.appwidget.provider",
            "value": "@xml/nativephp_widget_info"
          }
        ]
      }
    ],
    "dependencies": {
      "gradle": ["implementation(\"androidx.work:work-runtime-ktx:2.10.0\")"]
    }
  },
  "ios": {
    "entitlements": {
      "com.apple.security.application-groups": [
        "group.${NATIVEPHP_APP_ID}.widgets"
      ]
    }
  },
  "assets": {
    "android": {
      "res/layout/widget_nativephp.xml": "res/layout/widget_nativephp.xml",
      "res/xml/nativephp_widget_info.xml": "res/xml/nativephp_widget_info.xml",
      "res/drawable/widget_bg.xml": "res/drawable/widget_bg.xml"
    },
    "ios": {
      "WidgetExtension/Info.plist": "Resources/WidgetExtension/Info.plist",
      "WidgetExtension/NativePHPWidgets.entitlements": "Resources/WidgetExtension/NativePHPWidgets.entitlements"
    }
  },
  "hooks": {
    "post_compile": "widgets:plugin:post-compile"
  }
}
```

## JavaScript Bridge Support

Keep method parity across PHP and JavaScript wrappers.

Example `resources/js/widget.js`:

```javascript
import { bridgeCall } from "@nativephp/native";

export async function setData(payload) {
  return bridgeCall("Widget.SetData", payload);
}

export async function reloadAll() {
  return bridgeCall("Widget.ReloadAll", {});
}

export async function configure(options = {}) {
  return bridgeCall("Widget.Configure", options);
}

export const widget = {
  setData,
  reloadAll,
  configure,
};
```

Naming parity:

- PHP: `Widget::setData`, `Widget::reloadAll`, `Widget::configure`
- JavaScript: `widget.setData`, `widget.reloadAll`, `widget.configure`
- Bridge: `Widget.SetData`, `Widget.ReloadAll`, `Widget.Configure`

## JavaScript Integration Checklist

- Import `bridgeCall` from `@nativephp/native` in a single widget bridge module (`resources/js/widget.js`).
- Keep request payloads JSON-serializable and stable across PHP and JS wrappers.
- Return normalized success/error objects from wrappers so UI code does not parse raw bridge responses.
- Use one place in app code to subscribe to widget-related native events (if/when events are added).
- Add timeout and retry behavior in JS only for idempotent calls like `Widget.ReloadAll`.
- Keep naming parity strict across PHP facade, JS wrapper, and bridge method strings.
- Log bridge errors with method name + payload metadata (without sensitive values) for debugging.

## Shared Bridge Response Shape

Use the same normalized wrapper output in PHP and JavaScript:

```json
{
  "ok": true,
  "data": {},
  "error": null
}
```

Error shape:

```json
{
  "ok": false,
  "data": null,
  "error": {
    "code": "EXECUTION_FAILED",
    "message": "Widget update failed",
    "recoverable": true
  }
}
```

Contract rules:

- `ok=true` means `error=null` and `data` is present (object, list, scalar, or empty object).
- `ok=false` means `data=null` and `error.code` is always populated.
- Wrapper code should normalize native bridge responses to this shape before returning to app code.

Helper snippet (JavaScript):

```javascript
export function normalizeBridgeResult(raw) {
  const isError = raw?.status === "error" || !!raw?.error;

  if (isError) {
    const code = raw?.code || raw?.error?.code || "EXECUTION_FAILED";
    const message = raw?.message || raw?.error?.message || "Bridge call failed";

    return {
      ok: false,
      data: null,
      error: {
        code,
        message,
        recoverable: true,
      },
    };
  }

  return {
    ok: true,
    data: raw?.data ?? raw ?? {},
    error: null,
  };
}
```

Helper snippet (PHP):

```php
<?php

function normalize_bridge_result(?string $raw): array
{
    if (! $raw) {
        return [
            'ok' => false,
            'data' => null,
            'error' => [
                'code' => 'EXECUTION_FAILED',
                'message' => 'Bridge call failed',
                'recoverable' => true,
            ],
        ];
    }

    $decoded = json_decode($raw, true) ?? [];
    $isError = ($decoded['status'] ?? null) === 'error' || isset($decoded['error']);

    if ($isError) {
        $code = $decoded['code'] ?? ($decoded['error']['code'] ?? 'EXECUTION_FAILED');
        $message = $decoded['message'] ?? ($decoded['error']['message'] ?? 'Bridge call failed');

        return [
            'ok' => false,
            'data' => null,
            'error' => [
                'code' => $code,
                'message' => $message,
                'recoverable' => true,
            ],
        ];
    }

    return [
        'ok' => true,
        'data' => $decoded['data'] ?? $decoded,
        'error' => null,
    ];
}
```

Notes:

- Android `meta_data` under `receiver` is required for widget provider XML.
- iOS requires extension-target wiring in Xcode project; this is the main complexity point.

---

## Short Step-by-Step (Execution)

1. Create plugin scaffold
   - `php artisan native:plugin:create vendor/plugin-widgets`
   - `php artisan native:plugin:register vendor/plugin-widgets`

2. Build Android MVP first
   - Add `AppWidgetProvider` class and widget layouts in plugin Android resources
   - Add `Widget.*` Android bridge function classes
   - Persist widget payload to local JSON / shared prefs
   - Trigger widget update from `Widget.SetData` and `Widget.ReloadAll`

3. Validate Android
   - Run `php artisan native:run android`
   - Add widget to launcher, verify render/refresh/deep link behavior

4. Add iOS bridge + shared storage
   - Implement `WidgetFunctions.*` in plugin Swift
   - Write data to App Group shared storage
   - Call `WidgetCenter.shared.reloadAllTimelines()` from `Widget.ReloadAll`

5. Add WidgetKit extension target
   - Add `.appex` target with `NSExtension` and WidgetKit entry points
   - Add App Group capability to app + extension
   - Include extension sources and plist in Xcode project

6. Validate iOS
   - Run `php artisan native:run ios`
   - Add widget in simulator/device, verify timeline updates and deep links

---

## Effort Estimate

- Android MVP: medium (about 1–2 days)
- iOS MVP: high (about 3–6 days), mostly extension target integration
- Full production polish: +2–5 days (error handling, fallback states, refresh policy, QA)

---

## Main Technical Risk

Current iOS plugin compilation is app-target centric. WidgetKit requires extension-target creation and maintenance in `project.pbxproj`.

Practical options:

1. Start with manual extension target setup (fastest path)
2. Then automate target injection in compiler hooks once design stabilizes

This staged approach keeps delivery predictable while preserving long-term automation.
