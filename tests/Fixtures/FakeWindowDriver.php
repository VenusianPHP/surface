<?php

declare(strict_types=1);

namespace Venusian\Surface\Tests\Fixtures;

use LogicException;
use Surface\Contracts\Windows\Mail\WindowClosed;
use Surface\Contracts\Windows\Menus\MenuProfile;
use Surface\Contracts\Windows\Services\Alert;
use Surface\Contracts\Windows\Services\Clipboard;
use Surface\Contracts\Windows\Services\Desktop;
use Surface\Contracts\Windows\Services\OpenRequest;
use Surface\Contracts\Windows\Services\SaveRequest;
use Surface\Windows\Services\ToolkitDialogs;
use Voyager\Contracts\Signals\NamedSignal;
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
use Surface\Windows\ChildWindows;
use Surface\Windows\Menus\MenuStates;

/** Records each dialog shown and dismissed; the test answers with answer(). */
final class FakeDialogs extends ToolkitDialogs
{
    /** @var list<array{string, string, string}> [kind, id, parent name] */
    public array $shown = [];

    /** @var list<string> */
    public array $dismissed = [];

    public function __construct(private readonly FakeWindowDriver $fake)
    {
        parent::__construct($fake);
    }

    /** @param int|list<string> $value a button index for an alert, paths for a file panel */
    public function answer(string $id, int|array $value): void
    {
        is_int($value) ? $this->answerAlert($id, $value) : $this->answerFiles($id, $value);
    }

    protected function showAlert(string $id, ToolkitWindow $parent, Alert $alert): void { $this->shown[] = ['alert', $id, $parent->name()]; }

    protected function showOpen(string $id, ToolkitWindow $parent, OpenRequest $request): void { $this->shown[] = ['open', $id, $parent->name()]; }

    protected function showSave(string $id, ToolkitWindow $parent, SaveRequest $request): void { $this->shown[] = ['save', $id, $parent->name()]; }

    protected function dismiss(string $id): void { $this->dismissed[] = $id; }

    protected function post(NamedSignal $mail): void { $this->fake->mail[] = $mail; }
}

final class FakeClipboard implements Clipboard
{
    public ?string $text = null;

    public function setText(string $text): void { $this->text = $text; }
}

final class FakeDesktop implements Desktop
{
    public ?string $path = null;

    public function open(string $path): void { $this->path = $path; }
}

/** Records what the manager hands a toolkit window driver. */
final class FakeWindowDriver implements ToolkitWindowDriver
{
    /** @var array<string, ToolkitWindow> */
    public array $windows = [];

    public ?MenuProfile $default_menu = null;

    public ?MenuProfile $last_menu = null;

    public int $closed_all = 0;

    /** @var list<string> the names of the windows closed, in order */
    public array $closed = [];

    /** @var list<NamedSignal> the dialog answers and WindowClosed, in order */
    public array $mail = [];

    private ?FakeDialogs $dialogs = null;

    private ?FakeClipboard $clipboard = null;

    private ?FakeDesktop $desktop = null;

    public function open(string $name, int $width, int $height, ?MenuProfile $menu = null, ?ToolkitWindow $parent = null): ToolkitWindow
    {
        if (isset($this->windows[$name])) {
            throw new WindowException("A window named '{$name}' is already open.");
        }
        if (! is_null($parent)) {
            ChildWindows::check($this, $parent, $menu);
            $this->dialogs()->refuseChild($parent);
        }

        $this->last_menu = $menu;

        return $this->windows[$name] = new FakeWindow($name, is_null($menu) ? null : new MenuStates($menu), $this, $parent);
    }

    /** A closed window leaves the driver, as a driver's does. */
    public function forget(string $name): void
    {
        $this->closed[] = $name;
        $this->mail[] = new WindowClosed($name);
        unset($this->windows[$name]);
    }

    public function dialogs(): FakeDialogs { return $this->dialogs ??= new FakeDialogs($this); }

    public function clipboard(): FakeClipboard { return $this->clipboard ??= new FakeClipboard(); }

    public function desktop(): FakeDesktop { return $this->desktop ??= new FakeDesktop(); }

    public function has(string $name): bool { return isset($this->windows[$name]); }

    public function get(string $name): ?ToolkitWindow { return $this->windows[$name] ?? null; }

    public function all(): array { return $this->windows; }

    public function closeAll(): void { $this->closed_all++; }

    private ?MenuStates $default_states = null;

    public function setDefaultMenuBar(?MenuProfile $menu): void
    {
        if ($menu !== $this->default_menu) {
            $this->default_states = is_null($menu) ? null : new MenuStates($menu);
        }
        $this->default_menu = $menu;
    }

    public function setItemEnabled(string $item, bool $enabled): void { $this->defaultStates()->set($item, $enabled); }

    public function isItemEnabled(string $item): bool { return $this->defaultStates()->isEnabled($item); }

    public function setToggle(string $item, bool $on): void { $this->defaultStates()->setToggle($item, $on); }

    public function isToggled(string $item): bool { return $this->defaultStates()->toggle($item); }

    public function setRadio(string $group, string $item): void { $this->defaultStates()->setRadio($group, $item); }

    public function radio(string $group): string { return $this->defaultStates()->radio($group); }

    public function setItemLabel(string $item, string $label): void { $this->defaultStates()->setLabel($item, $label); }

    public function itemLabel(string $item): string { return $this->defaultStates()->label($item); }

    private function defaultStates(): MenuStates
    {
        return $this->default_states ?? throw new WindowException('No default menu bar profile is set.');
    }
}

final class FakeWindow implements ToolkitWindow
{
    private bool $open = true;

    private bool $confirming = false;

    public function __construct(
        private readonly string $name,
        private readonly ?MenuStates $states = null,
        private readonly ?FakeWindowDriver $driver = null,
        private readonly ?ToolkitWindow $parent = null,
    ) {}

    public function name(): string { return $this->name; }

    /** The child closes first. */
    public function close(): void
    {
        if (! $this->open) {
            return;
        }
        if (! is_null($this->driver)) {
            ChildWindows::childOf($this->driver, $this)?->close();
            $this->driver->dialogs()->closeOn($this);
        }
        $this->open = false;
        $this->driver?->forget($this->name);
    }

    public function isOpen(): bool { return $this->open; }

    public function parent(): ?ToolkitWindow { return $this->parent; }

    public function confirmClose(bool $on = true): static
    {
        $this->confirming = $on;

        return $this;
    }

    public function isConfirmingClose(): bool { return $this->confirming; }

    public ?TKPrimitive $initial_focus = null;

    public function focused(): ?TKPrimitive { return null; }

    public function setInitialFocus(?TKPrimitive $primitive): static
    {
        $this->initial_focus = $primitive;

        return $this;
    }

    public function title(): string { return $this->name; }

    public function present(): static { return $this; }

    public function isToggled(string $item): bool { return false; }

    public function setTitle(string $title): static { return $this; }

    public function setMenuBar(string $profile): static
    {
        ChildWindows::refuseMenuBar($this);

        return $this;
    }

    public function setToggle(string $item, bool $on): static { return $this; }

    public function setItemEnabled(string $item, bool $enabled): static
    {
        $this->states()->set($item, $enabled);

        return $this;
    }

    public function isItemEnabled(string $item): bool { return $this->states()->isEnabled($item); }

    public function setRadio(string $group, string $item): static
    {
        $this->states()->setRadio($group, $item);

        return $this;
    }

    public function radio(string $group): string { return $this->states()->radio($group); }

    public function setItemLabel(string $item, string $label): static
    {
        $this->states()->setLabel($item, $label);

        return $this;
    }

    public function itemLabel(string $item): string { return $this->states()->label($item); }

    private function states(): MenuStates
    {
        return $this->states ?? throw new WindowException("Window '{$this->name}' has no menu bar.");
    }

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
