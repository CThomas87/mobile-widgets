<?php

namespace Nativephp\MobileWidgets;

class Widgets
{
    public function __construct(
        protected WidgetManager $manager
    ) {}

    public function execute(array $options = []): array
    {
        return $this->setData($options);
    }

    public function getStatus(): array
    {
        return $this->manager->getStatus();
    }

    public function setData(array $payload): array
    {
        return $this->manager->setData($payload);
    }

    public function reloadAll(): array
    {
        return $this->manager->reloadAll();
    }

    public function configure(array $options = []): array
    {
        return $this->manager->configure($options);
    }

    public function envConfiguration(): array
    {
        return $this->manager->envConfiguration();
    }
}
