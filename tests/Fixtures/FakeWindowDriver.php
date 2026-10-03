<?php

declare(strict_types=1);

namespace Venusian\Surface\Tests\Fixtures;

use LogicException;
use Surface\Contracts\Windows\Menus\MenuProfile;
use Surface\Contracts\Windows\Primitives\PrimitiveFactory;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\Primitives\TKFixed;
use Surface\Contracts\Windows\Primitives\TKGrid;
use Surface\Contracts\Windows\Primitives\TKPrimitive;
use Surface\Contracts\Windows\Primitives\TKPrimitiveGroup;
use Surface\Contracts\Windows\Primitives\TKRow;
use Surface\Contracts\Windows\ToolkitWindow;
use Surface\Contracts\Windows\ToolkitWindowDriver;
use Surface\Contracts\Windows\WindowException;
use Surface\Contracts\Windows\Primitives\PrimitiveRegistry;

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
    public function column(string $name, int $spacing = 0, int $padding = 0): TKColumn { throw new LogicException('not used by this test'); }

    public function row(string $name, int $spacing = 0, int $padding = 0): TKRow { throw new LogicException('not used by this test'); }

    public function grid(string $name, int $spacing = 0, int $padding = 0): TKGrid { throw new LogicException('not used by this test'); }

    public function fixed(string $name): TKFixed { throw new LogicException('not used by this test'); }

    public function content(): ?TKPrimitiveGroup { return null; }

    public function view(string $path): ?TKPrimitive { return null; }

    public function uuid(string $uuid): ?TKPrimitive { return null; }

    public function size(): array { return [0, 0]; }

    public function registry(): PrimitiveRegistry { throw new LogicException('not used by this test'); }

    public function factory(): PrimitiveFactory { throw new LogicException('not used by this test'); }

    public function forgetContent(TKPrimitiveGroup $content): void {}
}
