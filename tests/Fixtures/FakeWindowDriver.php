<?php

declare(strict_types=1);

namespace Venusian\Surface\Tests\Fixtures;

use Surface\Contracts\Windows\Menus\MenuProfile;
use Surface\Contracts\Windows\ToolkitWindow;
use Surface\Contracts\Windows\ToolkitWindowDriver;
use Surface\Contracts\Windows\WindowException;

/** Records what the manager hands a toolkit window driver. */
final class FakeWindowDriver implements ToolkitWindowDriver
{
    /** @var array<string, ToolkitWindow> */
    public array $windows = [];

    public ?MenuProfile $default_menu = null;

    public ?MenuProfile $last_menu = null;

    public int $closed_all = 0;

    public function open(string $name, int $width, int $height, ?MenuProfile $menu = null): ToolkitWindow
    {
        if (isset($this->windows[$name])) {
            throw new WindowException("A window named '{$name}' is already open.");
        }

        $this->last_menu = $menu;

        return $this->windows[$name] = new FakeWindow($name);
    }

    public function has(string $name): bool { return isset($this->windows[$name]); }

    public function get(string $name): ?ToolkitWindow { return $this->windows[$name] ?? null; }

    public function all(): array { return $this->windows; }

    public function closeAll(): void { $this->closed_all++; }

    public function setDefaultMenuBar(?MenuProfile $menu): void { $this->default_menu = $menu; }
}

final class FakeWindow implements ToolkitWindow
{
    public function __construct(private readonly string $name) {}

    public function name(): string { return $this->name; }

    public function close(): void {}

    public function isOpen(): bool { return true; }

    public function title(): string { return $this->name; }

    public function present(): static { return $this; }

    public function isToggled(string $item): bool { return false; }

    public function setTitle(string $title): static { return $this; }

    public function setMenuBar(string $profile): static { return $this; }

    public function setToggle(string $item, bool $on): static { return $this; }
}
