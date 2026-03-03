<?php

use Nativephp\MobileWidgets\WidgetManager;
use Nativephp\MobileWidgets\Widgets;

function plugin_path(string $path = ''): string
{
    $base = dirname(__DIR__);

    return $path === '' ? $base : $base.'/'.$path;
}

describe('WidgetManager normalization', function () {
    it('normalizes successful bridge response', function () {
        $manager = new WidgetManager;

        $result = $manager->normalizeBridgeResult(json_encode([
            'status' => 'success',
            'data' => ['saved' => true],
        ]));

        expect($result['ok'])->toBeTrue()
            ->and($result['data'])->toBe(['saved' => true])
            ->and($result['error'])->toBeNull();
    });

    it('normalizes error bridge response', function () {
        $manager = new WidgetManager;

        $result = $manager->normalizeBridgeResult(json_encode([
            'status' => 'error',
            'code' => 'EXECUTION_FAILED',
            'message' => 'Widget update failed',
        ]));

        expect($result['ok'])->toBeFalse()
            ->and($result['data'])->toBeNull()
            ->and($result['error']['code'])->toBe('EXECUTION_FAILED');
    });

    it('normalizes empty response as error', function () {
        $manager = new WidgetManager;

        $result = $manager->normalizeBridgeResult(null);

        expect($result['ok'])->toBeFalse()
            ->and($result['data'])->toBeNull()
            ->and($result['error']['code'])->toBe('EXECUTION_FAILED');
    });

    it('normalizes invalid json response as error', function () {
        $manager = new WidgetManager;

        $result = $manager->normalizeBridgeResult('not-json');

        expect($result['ok'])->toBeFalse()
            ->and($result['data'])->toBeNull()
            ->and($result['error']['code'])->toBe('INVALID_BRIDGE_RESPONSE');
    });

    it('normalizes non-object json response as error', function () {
        $manager = new WidgetManager;

        $result = $manager->normalizeBridgeResult('"ok"');

        expect($result['ok'])->toBeFalse()
            ->and($result['data'])->toBeNull()
            ->and($result['error']['code'])->toBe('INVALID_BRIDGE_RESPONSE');
    });

    it('normalizes nested error payload response', function () {
        $manager = new WidgetManager;

        $result = $manager->normalizeBridgeResult(json_encode([
            'error' => [
                'code' => 'WIDGET_CONFIG_SAVE_FAILED',
                'message' => 'Unable to write widget config',
            ],
        ]));

        expect($result['ok'])->toBeFalse()
            ->and($result['data'])->toBeNull()
            ->and($result['error']['code'])->toBe('WIDGET_CONFIG_SAVE_FAILED')
            ->and($result['error']['message'])->toBe('Unable to write widget config');
    });
});

describe('WidgetManager env configuration', function () {
    it('loads defaults from environment safely', function () {
        putenv('MOBILE_WIDGETS_ENABLE_BACKGROUND_WORKERS=');
        putenv('MOBILE_WIDGETS_ENABLE_SCHEDULED_TASKS=');
        putenv('MOBILE_WIDGETS_REFRESH_INTERVAL_MINUTES=');

        $manager = new WidgetManager;
        $config = $manager->envConfiguration();

        expect($config)->toHaveKeys([
            'background_workers_enabled',
            'scheduled_tasks_enabled',
            'refresh_interval_minutes',
            'deep_link_path',
            'widget_title_key',
            'widget_body_key',
        ])
            ->and($config['refresh_interval_minutes'])->toBeGreaterThanOrEqual(15);
    });

    it('enforces minimum refresh interval', function () {
        putenv('MOBILE_WIDGETS_REFRESH_INTERVAL_MINUTES=5');

        $manager = new WidgetManager;
        $config = $manager->envConfiguration();

        expect($config['refresh_interval_minutes'])->toBe(15);
    });

    it('parses boolean env values safely', function () {
        putenv('MOBILE_WIDGETS_ENABLE_BACKGROUND_WORKERS=false');
        putenv('MOBILE_WIDGETS_ENABLE_SCHEDULED_TASKS=true');

        $manager = new WidgetManager;
        $config = $manager->envConfiguration();

        expect($config['background_workers_enabled'])->toBeFalse()
            ->and($config['scheduled_tasks_enabled'])->toBeTrue();
    });

    it('falls back to default app group when no ids are configured', function () {
        putenv('MOBILE_WIDGETS_APP_GROUP=');
        putenv('NATIVEPHP_APP_ID=');

        $manager = new WidgetManager;
        $config = $manager->envConfiguration();

        expect($config['app_group'])->toBe('group.nativephp.app.widgets');
    });

    it('uses app id env for derived app group', function () {
        putenv('MOBILE_WIDGETS_APP_GROUP=');
        putenv('NATIVEPHP_APP_ID=com.example.widgets');

        $manager = new WidgetManager;
        $config = $manager->envConfiguration();

        expect($config['app_group'])->toBe('group.com.example.widgets.widgets');
    });

    it('uses explicit app group env when provided', function () {
        putenv('MOBILE_WIDGETS_APP_GROUP=group.custom.widgets');

        $manager = new WidgetManager;
        $config = $manager->envConfiguration();

        expect($config['app_group'])->toBe('group.custom.widgets');
    });
});

describe('WidgetManager dispatch', function () {
    it('setData forwards payload and app group to bridge call', function () {
        putenv('MOBILE_WIDGETS_APP_GROUP=group.dispatch.widgets');

        $manager = new class extends WidgetManager
        {
            public string $method = '';

            public array $params = [];

            protected function call(string $method, array $params): array
            {
                $this->method = $method;
                $this->params = $params;

                return [
                    'ok' => true,
                    'data' => ['method' => $method, 'params' => $params],
                    'error' => null,
                ];
            }
        };

        $payload = ['title' => 'Deploy', 'status' => 'live'];
        $result = $manager->setData($payload);

        expect($result['ok'])->toBeTrue()
            ->and($manager->method)->toBe('Widget.SetData')
            ->and($manager->params['payload'])->toBe($payload)
            ->and($manager->params['app_group'])->toBe('group.dispatch.widgets');
    });

    it('reloadAll forwards app group to bridge call', function () {
        putenv('MOBILE_WIDGETS_APP_GROUP=group.dispatch.widgets');

        $manager = new class extends WidgetManager
        {
            public string $method = '';

            public array $params = [];

            protected function call(string $method, array $params): array
            {
                $this->method = $method;
                $this->params = $params;

                return [
                    'ok' => true,
                    'data' => ['method' => $method, 'params' => $params],
                    'error' => null,
                ];
            }
        };

        $result = $manager->reloadAll();

        expect($result['ok'])->toBeTrue()
            ->and($manager->method)->toBe('Widget.ReloadAll')
            ->and($manager->params['app_group'])->toBe('group.dispatch.widgets');
    });

    it('getStatus forwards app group to bridge call', function () {
        putenv('MOBILE_WIDGETS_APP_GROUP=group.dispatch.widgets');

        $manager = new class extends WidgetManager
        {
            public string $method = '';

            public array $params = [];

            protected function call(string $method, array $params): array
            {
                $this->method = $method;
                $this->params = $params;

                return [
                    'ok' => true,
                    'data' => ['method' => $method, 'params' => $params],
                    'error' => null,
                ];
            }
        };

        $result = $manager->getStatus();

        expect($result['ok'])->toBeTrue()
            ->and($manager->method)->toBe('Widget.GetStatus')
            ->and($manager->params['app_group'])->toBe('group.dispatch.widgets');
    });

    it('configure merges env defaults with explicit options', function () {
        putenv('MOBILE_WIDGETS_ENABLE_BACKGROUND_WORKERS=true');
        putenv('MOBILE_WIDGETS_ENABLE_SCHEDULED_TASKS=false');
        putenv('MOBILE_WIDGETS_DEEP_LINK_PATH=/widgets/default');

        $manager = new class extends WidgetManager
        {
            public string $method = '';

            public array $params = [];

            protected function call(string $method, array $params): array
            {
                $this->method = $method;
                $this->params = $params;

                return [
                    'ok' => true,
                    'data' => ['method' => $method, 'params' => $params],
                    'error' => null,
                ];
            }
        };

        $result = $manager->configure([
            'scheduled_tasks_enabled' => true,
            'deep_link_path' => '/widgets/overridden',
        ]);

        expect($result['ok'])->toBeTrue()
            ->and($manager->method)->toBe('Widget.Configure')
            ->and($manager->params['background_workers_enabled'])->toBeTrue()
            ->and($manager->params['scheduled_tasks_enabled'])->toBeTrue()
            ->and($manager->params['deep_link_path'])->toBe('/widgets/overridden');
    });
});

describe('Widgets wrapper', function () {
    it('proxies methods to widget manager', function () {
        $manager = new class extends WidgetManager
        {
            public string $method = '';

            protected function call(string $method, array $params): array
            {
                $this->method = $method;

                return [
                    'ok' => true,
                    'data' => ['method' => $method, 'params' => $params],
                    'error' => null,
                ];
            }
        };

        $widgets = new Widgets($manager);

        $setData = $widgets->setData(['title' => 'Hello']);
        $reload = $widgets->reloadAll();
        $configure = $widgets->configure(['scheduled_tasks_enabled' => true]);
        $status = $widgets->getStatus();

        expect($setData['ok'])->toBeTrue()
            ->and($reload['ok'])->toBeTrue()
            ->and($configure['ok'])->toBeTrue()
            ->and($status['ok'])->toBeTrue();
    });

    it('keeps execute alias for backward compatibility', function () {
        $manager = new class extends WidgetManager
        {
            protected function call(string $method, array $params): array
            {
                return [
                    'ok' => true,
                    'data' => ['method' => $method, 'params' => $params],
                    'error' => null,
                ];
            }
        };

        $widgets = new Widgets($manager);
        $result = $widgets->execute(['title' => 'Alias']);

        expect($result['ok'])->toBeTrue()
            ->and($result['data']['method'])->toBe('Widget.SetData');
    });
});

describe('Manifest and files', function () {
    it('has valid nativephp.json', function () {
        $manifestPath = plugin_path('nativephp.json');

        expect(file_exists($manifestPath))->toBeTrue();

        $manifest = json_decode(file_get_contents($manifestPath), true);

        expect(json_last_error())->toBe(JSON_ERROR_NONE)
            ->and($manifest['name'])->toBe('nativephp/mobile-widgets')
            ->and($manifest['namespace'])->toBe('Widgets');
    });

    it('pins plugin release version to 1.0.0', function () {
        $manifest = json_decode(file_get_contents(plugin_path('nativephp.json')), true);
        $composer = json_decode(file_get_contents(plugin_path('composer.json')), true);

        expect($manifest['version'] ?? null)->toBe('1.0.0')
            ->and($composer['version'] ?? null)->toBe('1.0.0');
    });

    it('declares required widget bridge functions', function () {
        $manifest = json_decode(file_get_contents(plugin_path('nativephp.json')), true);
        $bridgeNames = array_column($manifest['bridge_functions'], 'name');

        expect($bridgeNames)->toContain('Widget.SetData')
            ->toContain('Widget.ReloadAll')
            ->toContain('Widget.Configure')
            ->toContain('Widget.GetStatus');
    });

    it('declares required Android permissions for periodic refresh', function () {
        $manifest = json_decode(file_get_contents(plugin_path('nativephp.json')), true);
        $permissions = $manifest['android']['permissions'] ?? [];

        expect($permissions)->toContain('android.permission.WAKE_LOCK');
    });

    it('declares post compile and copy assets hooks', function () {
        $manifest = json_decode(file_get_contents(plugin_path('nativephp.json')), true);

        expect($manifest['hooks']['copy_assets'])->toBe('nativephp:widgets:copy-assets')
            ->and($manifest['hooks']['post_compile'])->toBe('nativephp:widgets:post-compile');
    });

    it('includes MIT license file', function () {
        $licensePath = plugin_path('LICENSE');

        expect(file_exists($licensePath))->toBeTrue();

        $content = file_get_contents($licensePath);

        expect($content)->toContain('MIT License')
            ->and($content)->toContain('Permission is hereby granted, free of charge');
    });

    it('includes Android resources and provider files', function () {
        expect(file_exists(plugin_path('resources/android/widgets/NativePhpWidgetProvider.kt')))->toBeTrue()
            ->and(file_exists(plugin_path('resources/android/widgets/NativePhpWidgetUpdateWorker.kt')))->toBeTrue()
            ->and(file_exists(plugin_path('resources/android/res/layout/widget_nativephp.xml')))->toBeTrue()
            ->and(file_exists(plugin_path('resources/android/res/xml/nativephp_widget_info.xml')))->toBeTrue()
            ->and(file_exists(plugin_path('resources/android/res/drawable/widget_bg.xml')))->toBeTrue();
    });

    it('includes iOS WidgetKit extension templates', function () {
        expect(file_exists(plugin_path('resources/ios/WidgetExtension/NativePHPWidgetsBundle.swift')))->toBeTrue()
            ->and(file_exists(plugin_path('resources/ios/WidgetExtension/NativePHPWidget.swift')))->toBeTrue()
            ->and(file_exists(plugin_path('resources/ios/WidgetExtension/WidgetDataStore.swift')))->toBeTrue()
            ->and(file_exists(plugin_path('resources/ios/WidgetExtension/Info.plist')))->toBeTrue()
            ->and(file_exists(plugin_path('resources/ios/WidgetExtension/NativePHPWidgets.entitlements')))->toBeTrue();
    });

    it('declares secrets for env-driven background and scheduling', function () {
        $manifest = json_decode(file_get_contents(plugin_path('nativephp.json')), true);

        expect($manifest['secrets'])->toHaveKeys([
            'MOBILE_WIDGETS_ENABLE_BACKGROUND_WORKERS',
            'MOBILE_WIDGETS_ENABLE_SCHEDULED_TASKS',
            'MOBILE_WIDGETS_REFRESH_INTERVAL_MINUTES',
            'MOBILE_WIDGETS_APP_GROUP',
        ]);
    });

    it('documents parity methods in readme', function () {
        $content = file_get_contents(plugin_path('README.md'));

        expect($content)->toContain('execute(...)')
            ->and($content)->toContain('setData(...)')
            ->and($content)->toContain('configure(...)')
            ->and($content)->toContain('reloadAll()')
            ->and($content)->toContain('getStatus()');
    });

    it('exports parity methods from javascript bridge', function () {
        $content = file_get_contents(plugin_path('resources/js/widgets.js'));

        expect($content)->toContain('export async function execute')
            ->and($content)->toContain('export async function setData')
            ->and($content)->toContain('export async function configure')
            ->and($content)->toContain('export async function reloadAll')
            ->and($content)->toContain('export async function getStatus')
            ->and($content)->toContain('execute,')
            ->and($content)->toContain('setData,')
            ->and($content)->toContain('configure,')
            ->and($content)->toContain('reloadAll,')
            ->and($content)->toContain('getStatus,');
    });

    it('includes livewire parity fixture component', function () {
        $fixturePath = plugin_path('resources/boost/examples/WidgetBridgeParityExample.php');

        expect(file_exists($fixturePath))->toBeTrue();

        $content = file_get_contents($fixturePath);

        expect($content)->toContain('class WidgetBridgeParityExample')
            ->and($content)->toContain('Widgets::execute(')
            ->and($content)->toContain('Widgets::setData(')
            ->and($content)->toContain('Widgets::configure(')
            ->and($content)->toContain('Widgets::reloadAll(')
            ->and($content)->toContain('Widgets::getStatus(');
    });

    it('references livewire parity fixture in readme', function () {
        $content = file_get_contents(plugin_path('README.md'));

        expect($content)->toContain('resources/boost/examples/WidgetBridgeParityExample.php');
    });

    it('includes blade wire click parity fixture', function () {
        $fixturePath = plugin_path('resources/boost/examples/widget-bridge-parity-example.blade.php');

        expect(file_exists($fixturePath))->toBeTrue();

        $content = file_get_contents($fixturePath);

        expect($content)->toContain('wire:click="executeAlias"')
            ->and($content)->toContain('wire:click="setData"')
            ->and($content)->toContain('wire:click="configure"')
            ->and($content)->toContain('wire:click="reloadAll"')
            ->and($content)->toContain('wire:click="getStatus"');
    });

    it('references blade parity fixture in readme', function () {
        $content = file_get_contents(plugin_path('README.md'));

        expect($content)->toContain('resources/boost/examples/widget-bridge-parity-example.blade.php');
    });

    it('keeps boost guideline aligned with current parity feature set', function () {
        $content = file_get_contents(plugin_path('resources/boost/guidelines/core.blade.php'));

        expect($content)->toContain('Widgets::execute(')
            ->and($content)->toContain('Widgets::setData(')
            ->and($content)->toContain('Widgets::configure(')
            ->and($content)->toContain('Widgets::reloadAll(')
            ->and($content)->toContain('Widgets::getStatus(')
            ->and($content)->toContain('Widgets::envConfiguration()')
            ->and($content)->toContain('widgets.execute(')
            ->and($content)->toContain('widgets.setData(')
            ->and($content)->toContain('widgets.configure(')
            ->and($content)->toContain('widgets.reloadAll(')
            ->and($content)->toContain('widgets.getStatus(')
            ->and($content)->toContain('widget_style')
            ->and($content)->toContain('widget_size')
            ->and($content)->toContain('widget_variant')
            ->and($content)->toContain('widget_content_mode');
    });
});

describe('Hook command classes', function () {
    it('has copy-assets hook command', function () {
        $file = plugin_path('src/Commands/CopyAssetsCommand.php');

        expect(file_exists($file))->toBeTrue()
            ->and(file_get_contents($file))->toContain("nativephp:widgets:copy-assets");
    });

    it('has post-compile hook command', function () {
        $file = plugin_path('src/Commands/PostCompileCommand.php');
        $content = file_get_contents($file);

        expect(file_exists($file))->toBeTrue()
            ->and($content)->toContain("nativephp:widgets:post-compile")
            ->and($content)->toContain('NATIVEPHP_WIDGETS_AUTOGENERATED')
            ->and($content)->toContain('Injected WidgetKit app extension target into iOS project.pbxproj.');
    });

    it('injects WidgetKit target into pbxproj content idempotently', function () {
        $command = new class extends \Nativephp\MobileWidgets\Commands\PostCompileCommand
        {
            protected function appId(): string
            {
                return 'com.example.app';
            }
        };

        $injector = new ReflectionMethod($command, 'injectWidgetTarget');
        $injector->setAccessible(true);

        $pbxproj = <<<PBX
/* Begin PBXBuildFile section */
/* End PBXBuildFile section */

/* Begin PBXContainerItemProxy section */
/* End PBXContainerItemProxy section */

/* Begin PBXCopyFilesBuildPhase section */
/* End PBXCopyFilesBuildPhase section */

/* Begin PBXFileReference section */
/* End PBXFileReference section */

/* Begin PBXFileSystemSynchronizedRootGroup section */
/* End PBXFileSystemSynchronizedRootGroup section */

/* Begin PBXFrameworksBuildPhase section */
/* End PBXFrameworksBuildPhase section */

/* Begin PBXGroup section */
		AAAAAAAAAAAAAAAAAAAAAAAA /* Products */ = {
			isa = PBXGroup;
			children = (
			);
			name = Products;
			sourceTree = "<group>";
		};
/* End PBXGroup section */

/* Begin PBXNativeTarget section */
		BBBBBBBBBBBBBBBBBBBBBBBB /* NativePHP */ = {
			isa = PBXNativeTarget;
			buildPhases = (
			);
			dependencies = (
			);
			productType = "com.apple.product-type.application";
		};
/* End PBXNativeTarget section */

/* Begin PBXProject section */
		95BD5DB92D178E9D00C72704 /* Project object */ = {
			isa = PBXProject;
			targets = (
				BBBBBBBBBBBBBBBBBBBBBBBB /* NativePHP */,
			);
		};
/* End PBXProject section */

/* Begin PBXResourcesBuildPhase section */
/* End PBXResourcesBuildPhase section */

/* Begin PBXSourcesBuildPhase section */
/* End PBXSourcesBuildPhase section */

/* Begin PBXTargetDependency section */
/* End PBXTargetDependency section */

/* Begin XCBuildConfiguration section */
/* End XCBuildConfiguration section */

/* Begin XCConfigurationList section */
/* End XCConfigurationList section */

rootObject = 95BD5DB92D178E9D00C72704 /* Project object */;
PBX;

        $updated = $injector->invoke($command, $pbxproj);

        expect($updated)->not->toBeNull()
            ->and($updated)->toContain('NATIVEPHP_WIDGETS_AUTOGENERATED')
            ->and($updated)->toContain('NativePHPWidgetsExtension')
            ->and($updated)->toContain('Embed App Extensions')
            ->and($updated)->toContain('NATIVEPHP_WIDGETS_APP_GROUP = group.com.example.app.widgets;');
    });
});
