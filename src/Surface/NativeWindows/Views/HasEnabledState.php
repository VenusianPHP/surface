<?php

namespace Surface\NativeWindows\Views;

/**
 * The enabled flag interactive controls share. A disabled control greys
 * out and its engine swallows the interaction — no mail, no hook. State
 * lives here; the native write goes through applyEnabled(), change-only.
 */
trait HasEnabledState
{
    protected bool $enabled = true;

    public function setEnabled(bool $enabled): static
    {
        if ($this->enabled !== $enabled) {
            $this->enabled = $enabled;
            $this->applyEnabled($enabled);
        }

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function enable(): static
    {
        return $this->setEnabled(true);
    }

    public function disable(): static
    {
        return $this->setEnabled(false);
    }

    /**
     * Write the enabled state to the native control.
     * @return void
     */
    abstract protected function applyEnabled(bool $enabled): void;
}
