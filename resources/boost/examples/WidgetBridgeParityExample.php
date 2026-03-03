<?php

namespace App\Livewire;

use Nativephp\MobileWidgets\Facades\Widgets;

class WidgetBridgeParityExample
{
    public array $payload = [
        'title' => 'Build Pipeline',
        'subtitle' => 'Production',
        'body' => 'Deployment in progress.',
        'status' => 'live',
        'status_label' => 'Live',
        'progress' => 68,
        'updated_at' => 'Updated just now',
        'state_text' => 'Healthy',
        'variant' => 'warning',
        'content_mode' => 'regular',
    ];

    public array $status = [];

    public function executeAlias(): void
    {
        Widgets::execute($this->payload);
    }

    public function setData(): void
    {
        Widgets::setData($this->payload);
    }

    public function configure(): void
    {
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
    }

    public function reloadAll(): void
    {
        Widgets::reloadAll();
    }

    public function getStatus(): void
    {
        $this->status = Widgets::getStatus();
    }

    public function renderTemplate(): string
    {
        return <<<'BLADE'
<div class="space-y-2">
    <button wire:click="executeAlias">execute</button>
    <button wire:click="setData">setData</button>
    <button wire:click="configure">configure</button>
    <button wire:click="reloadAll">reloadAll</button>
    <button wire:click="getStatus">getStatus</button>

    <pre>{{ json_encode($status, JSON_PRETTY_PRINT) }}</pre>
</div>
BLADE;
    }
}
