<?php

namespace Nativephp\MobileWidgets\Commands;

use Native\Mobile\Plugins\Commands\NativePluginHookCommand;

/**
 * Copy assets hook command for Widgets plugin.
 *
 * This hook runs during the copy_assets phase of the build process.
 * Use it to copy ML models, binary files, or other assets that need
 * to be in specific locations in the native project.
 *
 * @see \Native\Mobile\Plugins\Commands\NativePluginHookCommand
 */
class CopyAssetsCommand extends NativePluginHookCommand
{
    protected $signature = 'nativephp:widgets:copy-assets';

    protected $description = 'Copy assets for Widgets plugin';

    public function handle(): int
    {
        if ($this->isAndroid()) {
            $this->copyAndroidAssets();
        }

        if ($this->isIos()) {
            $this->copyIosAssets();
        }

        return self::SUCCESS;
    }

    /**
     * Copy assets for Android build
     */
    protected function copyAndroidAssets(): void
    {
        $this->copyToAndroidRes('android/res/layout/widget_nativephp.xml', 'layout/widget_nativephp.xml');
        $this->copyToAndroidRes('android/res/xml/nativephp_widget_info.xml', 'xml/nativephp_widget_info.xml');
        $this->copyToAndroidRes('android/res/drawable/widget_bg.xml', 'drawable/widget_bg.xml');
        $this->copyToAndroidRes('android/res/drawable/widget_bg_compact.xml', 'drawable/widget_bg_compact.xml');
        $this->copyToAndroidRes('android/res/drawable/widget_bg_success.xml', 'drawable/widget_bg_success.xml');
        $this->copyToAndroidRes('android/res/drawable/widget_bg_warning.xml', 'drawable/widget_bg_warning.xml');
        $this->copyToAndroidRes('android/res/drawable/widget_bg_alert.xml', 'drawable/widget_bg_alert.xml');
        $this->copyToAndroidRes('android/res/values/widget_strings.xml', 'values/widget_strings.xml');

        $this->info('Android assets copied for Widgets');
    }

    /**
     * Copy assets for iOS build
     */
    protected function copyIosAssets(): void
    {
        $this->copyToIosBundle('ios/WidgetExtension/Info.plist', 'WidgetsExtension/Info.plist');
        $this->copyToIosBundle('ios/WidgetExtension/NativePHPWidgets.entitlements', 'WidgetsExtension/NativePHPWidgets.entitlements');
        $this->copyToIosBundle('ios/WidgetExtension/NativePHPWidget.swift', 'WidgetsExtension/NativePHPWidget.swift');
        $this->copyToIosBundle('ios/WidgetExtension/NativePHPWidgetsBundle.swift', 'WidgetsExtension/NativePHPWidgetsBundle.swift');
        $this->copyToIosBundle('ios/WidgetExtension/WidgetDataStore.swift', 'WidgetsExtension/WidgetDataStore.swift');

        $this->info('iOS assets copied for Widgets');
    }
}
