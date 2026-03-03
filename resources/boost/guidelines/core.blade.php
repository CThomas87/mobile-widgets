## nativephp/mobile-widgets

A NativePHP Mobile plugin

### Installation

```bash
composer require nativephp/mobile-widgets
```

### PHP Usage (Livewire/Blade)

Use the `Widgets` facade (same bridge surface as JavaScript):

@verbatim
    <code-snippet name="Using Widgets Facade" lang="php">
        use Nativephp\MobileWidgets\Facades\Widgets;

        // Execute (alias of setData)
        $result = Widgets::execute(['title' => 'Now Playing', 'body' => 'Track info']);

        // Direct payload update (same bridge call as execute)
        Widgets::setData([
        'title' => 'Now Playing',
        'subtitle' => 'Production',
        'body' => 'Track info',
        'status' => 'live',
        'status_label' => 'Live',
        'progress' => 68,
        'updated_at' => 'Updated just now',
        ]);

        // Configure refresh/deep-link behavior
        Widgets::configure([
        'background_workers_enabled' => true,
        'scheduled_tasks_enabled' => true,
        'refresh_interval_minutes' => 30,
        'deep_link_path' => '/widgets/example',
        'widget_style' => 'card',
        'widget_size' => 'medium',
        'widget_variant' => 'warning',
        'widget_content_mode' => 'regular',
        ]);

        // Force widget redraw
        Widgets::reloadAll();

        // Get the current status
        $status = Widgets::getStatus();
    </code-snippet>
@endverbatim

### Available Methods

- `Widgets::execute(array $payload = [])`: Alias of `setData`
- `Widgets::setData(array $payload)`: Set widget payload data
- `Widgets::configure(array $options = [])`: Configure widget behavior
- `Widgets::reloadAll()`: Trigger widget redraw
- `Widgets::getStatus()`: Get current payload/config/status
- `Widgets::envConfiguration()`: Read resolved env-driven defaults

### Events

- `WidgetsCompleted`: Listen with `#[OnNative(WidgetsCompleted::class)]`

@verbatim
    <code-snippet name="Listening for Widgets Events" lang="php">
        use Native\Mobile\Attributes\OnNative;
        use Nativephp\MobileWidgets\Events\WidgetsCompleted;

        #[OnNative(WidgetsCompleted::class)]
        public function handleWidgetsCompleted($result, $id = null)
        {
        // Handle the event
        }
    </code-snippet>
@endverbatim

### JavaScript Usage (Vue/React/Inertia)

@verbatim
    <code-snippet name="Using Widgets in JavaScript" lang="javascript">
        import { widgets } from '@nativephp/mobile-widgets';

        // Execute (alias of setData)
        const result = await widgets.execute({ title: 'Now Playing', body: 'Track info' });

        // Direct payload update
        await widgets.setData({
        title: 'Now Playing',
        subtitle: 'Production',
        body: 'Track info',
        status: 'live',
        status_label: 'Live',
        progress: 68,
        updated_at: 'Updated just now',
        });

        // Configure refresh/deep-link behavior
        await widgets.configure({
        background_workers_enabled: true,
        scheduled_tasks_enabled: true,
        refresh_interval_minutes: 30,
        deep_link_path: '/widgets/example',
        widget_style: 'card',
        widget_size: 'medium',
        widget_variant: 'warning',
        widget_content_mode: 'regular',
        });

        // Force widget redraw
        await widgets.reloadAll();

        // Get the current status
        const status = await widgets.getStatus();
    </code-snippet>
@endverbatim
