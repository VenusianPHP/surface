<?php

namespace Surface\NativeWindows\Views;

use Closure;
use Surface\Contracts\NativeWindows\Events\SurfaceEventType;
use Surface\Contracts\NativeWindows\Views\OSButton;
use Surface\NativeWindows\Windowable;

/**
 * A push button. Holds the label and the click hook Surface believes in;
 * engines wire their native click to fireClick() and translate the label
 * through applyLabel().
 *
 * Every click also rides the event queue as VIEW_CLICKED, named
 * `view.clicked.<window>.<name>`, so a sketch may listen instead of (or as
 * well as) hooking. The hook runs inside the pump that delivered the click
 * — a deliberate exception to the menu rule, chosen for view hooks:
 * `onClick` is the sketch author's own closure against their own state,
 * not a definition crossing an engine seam.
 */
abstract class Button extends View implements OSButton
{
    use StylesText;

    protected ?Closure $on_click = null;

    public function __construct(
        string $name,
        Windowable $window,
        protected string $button_label,
    ) {
        parent::__construct($name, $window);
    }

    public function label(): ?string
    {
        return $this->button_label;
    }

    public function setLabel(string $label): static
    {
        $this->button_label = $label;
        $this->applyLabel($label);

        if ($this->sizing === \Surface\NativeWindows\Enums\SizeRule::NATURAL) {
            $this->relayout();
        }

        return $this;
    }

    public function onClick(callable $hook): static
    {
        $this->on_click = Closure::fromCallable($hook);

        return $this;
    }

    /**
     * Push the VIEW_CLICKED event, then invoke the stored hook. Engine
     * callbacks land here; safe with no hook and no sink.
     * @return void
     */
    protected function fireClick(): void
    {
        $this->window->emitViewEvent(SurfaceEventType::VIEW_CLICKED, $this->name);

        if (! is_null($this->on_click)) {
            ($this->on_click)();
        }
    }

    abstract protected function applyLabel(string $label): void;
}
