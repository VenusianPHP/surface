<?php

namespace Surface\NativeWindows\Components;

use Closure;
use Surface\Contracts\NativeWindows\Views\OSGroup;
use Surface\NativeWindows\Windowable;

/**
 * A sketch-placed panel that opens and closes by show/hide. The component
 * IS the panel — there is no scrim and no auto-docking. `$side` is
 * recorded for the sketch; open()/close() are the user-facing verbs, so
 * their hooks fire (unlike select()).
 *
 * Starts closed. Parts: `body`.
 */
class Drawer extends Component
{
    protected const PAD = 12;

    protected ?Closure $on_open = null;

    protected ?Closure $on_close = null;

    public function __construct(
        Windowable $window,
        string $name,
        int $x,
        int $y,
        int $width,
        int $height,
        protected DrawerSide $side,
        ?OSGroup $in = null,
    ) {
        parent::__construct($window, $name, $x, $y, $width, $height, $in);
    }

    protected function build(): void
    {
        $this->register('body', $this->root->group($this->partName('body'), 0, 0, 1, 1));
        $this->hide();
    }

    protected function layout(): void
    {
        [$width, $height] = $this->innerSize();
        $this->parts['body']->place(
            self::PAD,
            self::PAD,
            max(0, $width - 2 * self::PAD),
            max(0, $height - 2 * self::PAD),
        );
    }

    /** Show the panel. Fires onOpen — this is the user-facing verb. */
    public function open(): static
    {
        $this->show();

        if (! is_null($this->on_open)) {
            ($this->on_open)();
        }

        return $this;
    }

    /** Hide the panel. Fires onClose — this is the user-facing verb. */
    public function close(): static
    {
        $this->hide();

        if (! is_null($this->on_close)) {
            ($this->on_close)();
        }

        return $this;
    }

    public function isOpen(): bool
    {
        return $this->isVisible();
    }

    public function side(): DrawerSide
    {
        return $this->side;
    }

    /** The group the sketch conjures the drawer's content into. */
    public function body(): OSGroup
    {
        /** @var OSGroup */
        return $this->parts['body'];
    }

    public function onOpen(callable $hook): static
    {
        $this->on_open = $hook(...);

        return $this;
    }

    public function onClose(callable $hook): static
    {
        $this->on_close = $hook(...);

        return $this;
    }
}
