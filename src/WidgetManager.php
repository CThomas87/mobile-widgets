<?php

namespace Nativephp\MobileWidgets;

class WidgetManager
{
    public function setData(array $payload): array
    {
        return $this->call('Widget.SetData', [
            'payload' => $payload,
            'app_group' => $this->resolveAppGroup(),
        ]);
    }

    public function reloadAll(): array
    {
        return $this->call('Widget.ReloadAll', [
            'app_group' => $this->resolveAppGroup(),
        ]);
    }

    public function configure(array $options = []): array
    {
        $config = array_merge($this->envConfiguration(), $options);

        return $this->call('Widget.Configure', $config);
    }

    public function getStatus(): array
    {
        return $this->call('Widget.GetStatus', [
            'app_group' => $this->resolveAppGroup(),
        ]);
    }

    public function envConfiguration(): array
    {
        return [
            'background_workers_enabled' => $this->envBool('MOBILE_WIDGETS_ENABLE_BACKGROUND_WORKERS', false),
            'scheduled_tasks_enabled' => $this->envBool('MOBILE_WIDGETS_ENABLE_SCHEDULED_TASKS', false),
            'refresh_interval_minutes' => max(15, (int) env('MOBILE_WIDGETS_REFRESH_INTERVAL_MINUTES', 30)),
            'deep_link_path' => (string) env('MOBILE_WIDGETS_DEEP_LINK_PATH', '/widgets/example'),
            'widget_title_key' => (string) env('MOBILE_WIDGETS_TITLE_KEY', 'title'),
            'widget_subtitle_key' => (string) env('MOBILE_WIDGETS_SUBTITLE_KEY', 'subtitle'),
            'widget_body_key' => (string) env('MOBILE_WIDGETS_BODY_KEY', 'body'),
            'widget_image_key' => (string) env('MOBILE_WIDGETS_IMAGE_KEY', 'image_url'),
            'widget_status_key' => (string) env('MOBILE_WIDGETS_STATUS_KEY', 'status'),
            'widget_status_label_key' => (string) env('MOBILE_WIDGETS_STATUS_LABEL_KEY', 'status_label'),
            'widget_progress_key' => (string) env('MOBILE_WIDGETS_PROGRESS_KEY', 'progress'),
            'widget_updated_at_key' => (string) env('MOBILE_WIDGETS_UPDATED_AT_KEY', 'updated_at'),
            'widget_state_text_key' => (string) env('MOBILE_WIDGETS_STATE_TEXT_KEY', 'state_text'),
            'widget_style' => (string) env('MOBILE_WIDGETS_STYLE', 'card'),
            'widget_size' => (string) env('MOBILE_WIDGETS_SIZE', 'medium'),
            'widget_variant' => (string) env('MOBILE_WIDGETS_VARIANT', 'default'),
            'widget_content_mode' => (string) env('MOBILE_WIDGETS_CONTENT_MODE', 'regular'),
            'app_group' => $this->resolveAppGroup(),
        ];
    }

    protected function resolveAppGroup(): string
    {
        $configured = (string) env('MOBILE_WIDGETS_APP_GROUP', '');

        if ($configured !== '') {
            return $configured;
        }

        $appId = (string) env('NATIVEPHP_APP_ID', '');

        if ($appId === '') {
            try {
                $configuredAppId = config('nativephp.app_id');
                if (is_string($configuredAppId) && $configuredAppId !== '') {
                    $appId = $configuredAppId;
                }
            } catch (\Throwable) {
                $appId = '';
            }
        }

        if ($appId === '') {
            $appId = 'nativephp.app';
        }

        return 'group.'.$appId.'.widgets';
    }

    protected function envBool(string $key, bool $default): bool
    {
        $value = env($key);

        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            '1', 'true', 'on', 'yes' => true,
            '0', 'false', 'off', 'no' => false,
            default => $default,
        };
    }

    protected function call(string $method, array $params): array
    {
        if (! is_callable('\nativephp_call')) {
            return [
                'ok' => false,
                'data' => null,
                'error' => [
                    'code' => 'NATIVEPHP_UNAVAILABLE',
                    'message' => 'nativephp_call is not available in the current runtime',
                    'recoverable' => true,
                ],
            ];
        }

        $encoded = json_encode($params);

        if (! is_string($encoded)) {
            return [
                'ok' => false,
                'data' => null,
                'error' => [
                    'code' => 'INVALID_PAYLOAD',
                    'message' => 'Failed to encode widget bridge payload',
                    'recoverable' => true,
                ],
            ];
        }

        $raw = call_user_func('\\nativephp_call', $method, $encoded);

        return $this->normalizeBridgeResult($raw);
    }

    public function normalizeBridgeResult(?string $raw): array
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

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return [
                'ok' => false,
                'data' => null,
                'error' => [
                    'code' => 'INVALID_BRIDGE_RESPONSE',
                    'message' => 'Bridge response was not valid JSON',
                    'recoverable' => true,
                ],
            ];
        }

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
}
