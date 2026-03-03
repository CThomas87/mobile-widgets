# Widgets Plugin for NativePHP Mobile

Home-screen widgets for NativePHP Mobile apps on Android and iOS.

This plugin lets your Laravel app send structured widget data (title, body, status, progress, image URL, metadata), control widget presentation (style/variant/content mode), and trigger redraws through parity bridges for:

- PHP / Laravel facades (`Widget`, `Widgets`)
- Livewire (via the same facades)
- JavaScript bridge (`widget` / `widgets`)

Bridge functions:

- `Widget.SetData`
- `Widget.ReloadAll`
- `Widget.Configure`
- `Widget.GetStatus`

Parity methods:

- `execute(...)` (alias of `setData(...)`)
- `setData(...)`
- `configure(...)`
- `reloadAll()`
- `getStatus()`

## Requirements

- PHP: `^8.2`
- NativePHP Mobile: `^3.0`
- Plugin package: `nativephp/mobile-widgets`

## Installation

Install with Composer:

```bash
composer require nativephp/mobile-widgets
```

## Activate in NativePHP Mobile / Mobile Air

After install, activate by rebuilding/running your native targets so plugin hooks and assets are applied:

```bash
php artisan native:plugin:register nativephp/mobile-widgets

php artisan native:run android
php artisan native:run ios
```

If you use Mobile Air plugin management, install/enable this plugin there and then run the same NativePHP build/run commands so the local native projects pick up changes.

## Permissions

- Android: `android.permission.WAKE_LOCK`
- iOS: no runtime privacy permissions are requested by this plugin

The iOS side uses App Group shared storage for app/widget data exchange.

## Configuration

Add to `.env` (or your deployment secrets):

```dotenv
MOBILE_WIDGETS_ENABLE_BACKGROUND_WORKERS=false
MOBILE_WIDGETS_ENABLE_SCHEDULED_TASKS=false
MOBILE_WIDGETS_REFRESH_INTERVAL_MINUTES=30
MOBILE_WIDGETS_APP_GROUP=
MOBILE_WIDGETS_DEEP_LINK_PATH=/widgets/example

MOBILE_WIDGETS_TITLE_KEY=title
MOBILE_WIDGETS_SUBTITLE_KEY=subtitle
MOBILE_WIDGETS_BODY_KEY=body
MOBILE_WIDGETS_IMAGE_KEY=image_url
MOBILE_WIDGETS_STATUS_KEY=status
MOBILE_WIDGETS_STATUS_LABEL_KEY=status_label
MOBILE_WIDGETS_PROGRESS_KEY=progress
MOBILE_WIDGETS_UPDATED_AT_KEY=updated_at
MOBILE_WIDGETS_STATE_TEXT_KEY=state_text

MOBILE_WIDGETS_STYLE=card
MOBILE_WIDGETS_SIZE=medium
MOBILE_WIDGETS_VARIANT=default
MOBILE_WIDGETS_CONTENT_MODE=regular
```

## API: parameters and return payload

All bridges normalize responses into:

```json
{
  "ok": true,
  "data": {},
  "error": null
}
```

Or on failure:

```json
{
  "ok": false,
  "data": null,
  "error": {
    "code": "SOME_ERROR_CODE",
    "message": "Human-readable message",
    "recoverable": true
  }
}
```

### `setData(payload)` / `execute(payload)`

Supported payload keys (full set):

| Key            | Type              | Allowed values / restrictions                                                                                                               |
| -------------- | ----------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| `title`        | string            | Recommended short text (single-line display).                                                                                               |
| `subtitle`     | string            | Recommended short text (single-line display).                                                                                               |
| `body`         | string            | Main message text; compact mode may hide this field.                                                                                        |
| `image_url`    | string URL        | Must be `https://` for image caching/rendering support.                                                                                     |
| `status`       | string            | Any string allowed; known values (`live`, `ok`, `healthy`, `success`, `warning`, `paused`, `error`, `failed`, `alert`) affect status color. |
| `status_label` | string            | Any string allowed; if present it is shown instead of `status`.                                                                             |
| `progress`     | int/string/number | Parsed as integer and clamped to `0..100`; invalid/non-numeric values hide progress UI.                                                     |
| `updated_at`   | string            | Any display string (for example: `Updated just now`).                                                                                       |
| `state_text`   | string            | Any display string (for example: `Healthy`).                                                                                                |
| `variant`      | string            | Any string allowed; supported visual presets are `default`, `success`, `warning`, `alert`.                                                  |
| `content_mode` | string            | Any string allowed; supported modes are `compact`, `regular`, `detailed`.                                                                   |

Notes:

- Additional custom keys are allowed and stored; widget rendering only uses configured key mappings.
- Key mapping defaults can be changed via `configure(...)` / environment keys (for example `MOBILE_WIDGETS_TITLE_KEY`, `MOBILE_WIDGETS_PROGRESS_KEY`, etc.).
- For best cross-platform parity, prefer canonical keys listed above.

Return data includes:

- `saved` (bool)
- `keys` (payload keys saved)
- `image_cached` (bool)
- `image_cache_error` (string, empty when successful)

### `configure(options)`

Main options:

- Scheduling flags and interval
- Deep-link path
- Key remapping for payload fields
- Style/size/variant/content mode defaults
- App group override (`app_group`) when needed

Return data includes:

- `configured` (bool)
- `scheduling_enabled` (bool)
- `refresh_interval_minutes` (int)

### `reloadAll()`

Return data:

- `reloaded` (bool)

### `getStatus()`

Return data:

- `status` (`ready` or error state)
- `payload` (current saved payload)
- `config` (current saved config)
- Android includes `image_cached` and `image_cache_error`

## Restrictions and behavior notes

- Remote images must use `https://`.
- Oversized/invalid image downloads are rejected and surfaced via `image_cache_error`.
- `progress` is clamped to `0..100` by platform renderers.
- `refresh_interval_minutes` has a minimum of 15.
- `deep_link_path` is normalized to begin with `/`.

## Usage examples

### Laravel / PHP facade

```php
use Nativephp\MobileWidgets\Facades\Widget;

$save = Widget::setData([
        'title' => 'Build Pipeline',
        'subtitle' => 'Production',
        'body' => 'Deployment in progress.',
        'status' => 'live',
        'status_label' => 'Live',
        'progress' => 68,
        'updated_at' => 'Updated just now',
        'state_text' => 'Healthy',
        'image_url' => 'https://picsum.photos/640/320',
]);

$config = Widget::configure([
        'deep_link_path' => '/widgets/example',
        'widget_style' => 'card',
        'widget_size' => 'medium',
        'widget_variant' => 'warning',
        'widget_content_mode' => 'regular',
]);

Widget::reloadAll();
$status = Widget::getStatus();
```

### Livewire

```php
use Nativephp\MobileWidgets\Facades\Widgets;

Widgets::execute(['title' => 'Build Pipeline']); // alias of setData
Widgets::setData(['title' => 'Build Pipeline', 'status' => 'live']);
Widgets::configure(['deep_link_path' => '/widgets/example']);
Widgets::reloadAll();
$status = Widgets::getStatus();
```

Fixtures:

- `resources/boost/examples/WidgetBridgeParityExample.php`
- `resources/boost/examples/widget-bridge-parity-example.blade.php`

### Vue / JavaScript

```js
import { widget } from "@nativephp/mobile-widgets";

await widget.execute({ title: "Build Pipeline" });

await widget.setData({
  title: "Build Pipeline",
  subtitle: "Production",
  body: "Deployment in progress.",
  status: "live",
  status_label: "Live",
  progress: 68,
  updated_at: "Updated just now",
  state_text: "Healthy",
  image_url: "https://picsum.photos/640/320",
});

await widget.configure({
  deep_link_path: "/widgets/example",
  widget_style: "card",
  widget_size: "medium",
  widget_variant: "warning",
  widget_content_mode: "regular",
});

await widget.reloadAll();
const status = await widget.getStatus();
```

## Background workers and scheduled tasks

The plugin exposes scheduling controls:

- `background_workers_enabled`
- `scheduled_tasks_enabled`
- `refresh_interval_minutes`

How they are implemented:

- Android: periodic refresh depends on WorkManager execution windows, battery/OS policies, and process state; timing is best-effort, not exact.
- iOS: this plugin does not provide an equivalent long-running periodic worker; it persists config/data and requests timeline reloads only.
- Cross-platform: enabling these flags does not guarantee the same background behavior on Android and iOS.

How to enable in configuration:

```php
Widget::configure([
        'background_workers_enabled' => true,
        'scheduled_tasks_enabled' => true,
        'refresh_interval_minutes' => 30,
]);
```

Safe fallback patterns:

- Keep `background_workers_enabled=false` and `scheduled_tasks_enabled=false` for production by default.
- Treat widgets as event-driven: call `Widget::setData(...)` + `Widget::reloadAll()` when app-visible events happen (app open, user action, successful API sync, completed job while app is active).
- Provide a manual in-app refresh action that re-fetches data, writes widget payload, and triggers `reloadAll()`.
- Show freshness in the widget payload (for example `updated_at`) so users can see when data was last updated.

## Production checklist

- Confirm iOS App Group entitlement value matches app + widget extension.
- Validate `getStatus()` in release builds.
- Use only `https://` images and keep them small.
- Verify deep links from all supported widget sizes/styles.
- Re-run native build commands after plugin/config changes.

## Support

If you find a bug, please open an issue in the repository with steps to reproduce.

I’m also open to pull requests for fixes and improvements.

## License

MIT
