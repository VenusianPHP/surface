<?php

namespace Surface\NativeWindows;

use Surface\Contracts\Core\AboutInfo;
use Surface\Contracts\Core\Events\SurfaceEventType;
use Surface\Contracts\NativeWindows\Events\Menu\MenuOccurrence;
use Surface\Contracts\NativeWindows\Events\QuitRequested;
use Surface\Contracts\NativeWindows\Events\View\ButtonClicked;
use Surface\Contracts\NativeWindows\Events\View\SelectionChanged;
use Surface\Contracts\NativeWindows\Events\View\TextChanged;
use Surface\Contracts\NativeWindows\Events\View\TextSubmitted;
use Surface\Contracts\NativeWindows\Events\View\Toggled;
use Surface\Contracts\NativeWindows\Events\View\ValueChanged;
use Surface\Contracts\NativeWindows\Events\View\ViewComponentOccurrence;
use Surface\Contracts\NativeWindows\Events\Window\WindowClosed;
use Surface\Contracts\NativeWindows\Events\Window\WindowResized;
use Surface\Contracts\NativeWindows\OSWindow;
use Surface\Contracts\NativeWindows\Views\OSButton;
use Surface\Contracts\NativeWindows\Views\OSCheckbox;
use Surface\Contracts\NativeWindows\Views\OSDropdown;
use Surface\Contracts\NativeWindows\Views\OSGroup;
use Surface\Contracts\NativeWindows\Views\OSImage;
use Surface\Contracts\NativeWindows\Views\OSLabel;
use Surface\Contracts\NativeWindows\Views\OSProgressBar;
use Surface\Contracts\NativeWindows\Views\OSScrollView;
use Surface\Contracts\NativeWindows\Views\OSSeparator;
use Surface\Contracts\NativeWindows\Views\OSSlider;
use Surface\Contracts\NativeWindows\Views\OSSpinner;
use Surface\Contracts\NativeWindows\Views\OSTextArea;
use Surface\Contracts\NativeWindows\Views\OSTextInput;
use Surface\Contracts\NativeWindows\Views\OSToggle;
use Surface\Contracts\NativeWindows\Views\OSToggleButton;
use Surface\Contracts\NativeWindows\Views\OSVideo;
use Surface\Contracts\NativeWindows\Views\OSView;
use Surface\Contracts\NativeWindows\WindowableException;
use Surface\NativeWindows\Menus\MenuItemSpec;
use Surface\NativeWindows\Views\Button;
use Surface\NativeWindows\Views\Checkbox;
use Surface\NativeWindows\Views\Dropdown;
use Surface\NativeWindows\Views\Group;
use Surface\NativeWindows\Views\Image;
use Surface\NativeWindows\Views\Label;
use Surface\NativeWindows\Views\ProgressBar;
use Surface\NativeWindows\Views\ScrollView;
use Surface\NativeWindows\Views\Separator;
use Surface\NativeWindows\Views\Slider;
use Surface\NativeWindows\Views\Spinner;
use Surface\NativeWindows\Views\TextArea;
use Surface\NativeWindows\Views\TextInput;
use Surface\NativeWindows\Views\Toggle;
use Surface\NativeWindows\Views\ToggleButton;
use Surface\NativeWindows\Views\Video;
use Surface\NativeWindows\Views\View;
use Voyager\Contracts\IOPools\PoolPump;
use Voyager\NutsAndBolts\Collection;

abstract class Windowable implements OSWindow
{
    /**
     * Every node conjured into this window, keyed by name.
     * @var Collection
     */
    protected Collection $views;

    /**
     * The content size the last syncLayout() saw. Starts at 0x0 so the first
     * real size counts as a resize — that is what lays GTK out once its
     * content stops reading 0x0.
     * @var array{int, int}
     */
    protected array $laid_out_size = [0, 0];

    /**
     * Where this window's engine drops what it observes during a pump.
     * Handed over by LiveApplication at provisioning.
     * @var PoolPump|null
     */
    protected ?PoolPump $io_pool = null;

    public function __construct(
        public readonly string $name
    ) {
        $this->views = new Collection();
    }

    /**
     * Render a parsed spec tree through the engine.
     * @param list<MenuItemSpec> $spec
     * @return void
     */
    abstract protected function applyMenuBar(array $spec): void;

    /**
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * @param string|null $title
     * @return string|$this|null
     */
    public function title(?string $title = null): string|static|null
    {
        if(is_null($title)) {
            return $this->getTitle();
        }

        return $this->setTitle($title);
    }

    /**
     * Elect a registered menu-bar profile for this window.
     *
     * What electing means is the engine's truth: AppKit swaps the one real
     * bar, GTK builds widgets into this window's scaffold.
     *
     * @param string $profile Name registered through LiveApplication::addMenuBarProfiles().
     * @return $this
     * @throws WindowableException When no profile is registered under that name.
     */
    public function setMenuBar(string $profile): static
    {
        $spec = $this->resolveMenuBarProfile($profile);

        if (is_null($spec)) {
            throw new WindowableException("Menu bar profile '{$profile}' is not registered.");
        }

        $this->applyMenuBar($spec);

        return $this;
    }

    /**
     * Look a profile up by name. Overridable so the flow is provable without a container.
     * @param string $profile
     * @return list<MenuItemSpec>|null
     */
    protected function resolveMenuBarProfile(string $profile): ?array
    {
        return app('live-app')->getMenuBarProfile($profile);
    }

    /**
     * The shared tail of every conjure: guard the name BEFORE minting,
     * register, wire the host, place. `$in` is the group the view was
     * conjured into — the engine already parented the native under it, so
     * the wiring here is Surface's half: rules resolve against the group
     * and the group cascades into the view.
     *
     * @throws WindowableException When the name is already taken.
     */
    protected function guardName(string $name): void
    {
        if ($this->views->has($name)) {
            throw new WindowableException("View '{$name}' already exists in window '{$this->name}'.");
        }
    }

    protected function settle(View $view, ?OSGroup $in, int $x, int $y, int $width, int $height): View
    {
        $this->views->put($view->name(), $view);

        if (! is_null($in)) {
            $view->hostIn($in);
            $in->registerChild($view);
        }

        $view->place($x, $y, $width, $height);

        return $view;
    }

    /**
     * Conjure a label and place it. Top-left pixels, relative to the
     * window content — or to `$in` when conjured into a group.
     *
     * The engine mints the native node through mintLabel(); Surface owns
     * the name registry and the frame.
     *
     * @throws WindowableException When the name is already taken.
     */
    public function label(string $name, string $text, int $x, int $y, int $width, int $height, ?OSGroup $in = null): OSLabel
    {
        $this->guardName($name);

        /** @var Label */
        return $this->settle($this->mintLabel($name, $text, $in), $in, $x, $y, $width, $height);
    }

    /**
     * Conjure a button and place it. Top-left pixels, window- or group-relative.
     * @throws WindowableException When the name is already taken.
     */
    public function button(string $name, string $label, int $x, int $y, int $width, int $height, ?OSGroup $in = null): OSButton
    {
        $this->guardName($name);

        /** @var Button */
        return $this->settle($this->mintButton($name, $label, $in), $in, $x, $y, $width, $height);
    }

    /**
     * Conjure an indeterminate busy spinner and place it. Conjured stopped —
     * the sketch decides when to spin.
     * @throws WindowableException When the name is already taken.
     */
    public function spinner(string $name, int $x, int $y, int $width, int $height, ?OSGroup $in = null): OSSpinner
    {
        $this->guardName($name);

        /** @var Spinner */
        return $this->settle($this->mintSpinner($name, $in), $in, $x, $y, $width, $height);
    }

    /**
     * Conjure an image and place it. A null path conjures an empty picture
     * to setPath() into later.
     * @throws WindowableException When the name is already taken.
     */
    public function image(string $name, ?string $path, int $x, int $y, int $width, int $height, ?OSGroup $in = null): OSImage
    {
        $this->guardName($name);

        /** @var Image */
        return $this->settle($this->mintImage($name, $path, $in), $in, $x, $y, $width, $height);
    }

    /**
     * Conjure a video player and place it. A null path conjures an empty
     * player to setPath() into later; playback starts paused either way.
     * @throws WindowableException When the name is already taken.
     */
    public function video(string $name, ?string $path, int $x, int $y, int $width, int $height, ?OSGroup $in = null): OSVideo
    {
        $this->guardName($name);

        /** @var Video */
        return $this->settle($this->mintVideo($name, $path, $in), $in, $x, $y, $width, $height);
    }

    /**
     * Conjure a single-line text field and place it. A secret field masks
     * its glyphs; engines without an honest secret placeholder ignore it.
     * @throws WindowableException When the name is already taken.
     */
    public function textInput(string $name, string $value, int $x, int $y, int $width, int $height, ?string $placeholder = null, bool $secret = false, ?OSGroup $in = null): OSTextInput
    {
        $this->guardName($name);

        /** @var TextInput */
        return $this->settle($this->mintTextInput($name, $value, $placeholder, $secret, $in), $in, $x, $y, $width, $height);
    }

    /**
     * Conjure a multi-line text editor and place it.
     * @throws WindowableException When the name is already taken.
     */
    public function textArea(string $name, string $value, int $x, int $y, int $width, int $height, ?OSGroup $in = null): OSTextArea
    {
        $this->guardName($name);

        /** @var TextArea */
        return $this->settle($this->mintTextArea($name, $value, $in), $in, $x, $y, $width, $height);
    }

    /**
     * Conjure a slider over [$min, $max] holding $value, and place it.
     * @throws WindowableException When the name is already taken.
     */
    public function slider(string $name, float $min, float $max, float $value, int $x, int $y, int $width, int $height, ?OSGroup $in = null): OSSlider
    {
        $this->guardName($name);

        /** @var Slider */
        return $this->settle($this->mintSlider($name, $min, $max, $value, $in), $in, $x, $y, $width, $height);
    }

    /**
     * Conjure an on/off switch and place it.
     * @throws WindowableException When the name is already taken.
     */
    public function toggle(string $name, bool $on, int $x, int $y, int $width, int $height, ?OSGroup $in = null): OSToggle
    {
        $this->guardName($name);

        /** @var Toggle */
        return $this->settle($this->mintToggle($name, $on, $in), $in, $x, $y, $width, $height);
    }

    /**
     * Conjure a press-and-stay button and place it.
     * @throws WindowableException When the name is already taken.
     */
    public function toggleButton(string $name, string $label, bool $pressed, int $x, int $y, int $width, int $height, ?OSGroup $in = null): OSToggleButton
    {
        $this->guardName($name);

        /** @var ToggleButton */
        return $this->settle($this->mintToggleButton($name, $label, $pressed, $in), $in, $x, $y, $width, $height);
    }

    /**
     * Conjure a labelled checkbox and place it.
     * @throws WindowableException When the name is already taken.
     */
    public function checkbox(string $name, string $label, bool $checked, int $x, int $y, int $width, int $height, ?OSGroup $in = null): OSCheckbox
    {
        $this->guardName($name);

        /** @var Checkbox */
        return $this->settle($this->mintCheckbox($name, $label, $checked, $in), $in, $x, $y, $width, $height);
    }

    /**
     * Conjure a determinate progress bar holding $progress (0..1) and place it.
     * @throws WindowableException When the name is already taken.
     */
    public function progressBar(string $name, float $progress, int $x, int $y, int $width, int $height, ?OSGroup $in = null): OSProgressBar
    {
        $this->guardName($name);

        /** @var ProgressBar */
        return $this->settle($this->mintProgressBar($name, $progress, $in), $in, $x, $y, $width, $height);
    }

    /**
     * Conjure a dropdown over $options with $selected picked, and place it.
     *
     * @param list<string> $options
     * @throws WindowableException When the name is already taken.
     */
    public function dropdown(string $name, array $options, int $selected, int $x, int $y, int $width, int $height, ?OSGroup $in = null): OSDropdown
    {
        $this->guardName($name);

        /** @var Dropdown */
        return $this->settle($this->mintDropdown($name, array_values($options), $selected, $in), $in, $x, $y, $width, $height);
    }

    /**
     * Conjure a thin dividing line and place it. Orientation comes from the
     * frame's aspect — wider than tall is horizontal — and is fixed for life.
     * @throws WindowableException When the name is already taken.
     */
    public function separator(string $name, int $x, int $y, int $width, int $height, ?OSGroup $in = null): OSSeparator
    {
        $this->guardName($name);

        /** @var Separator */
        return $this->settle($this->mintSeparator($name, $width >= $height, $in), $in, $x, $y, $width, $height);
    }

    /**
     * Conjure a plain container and place it. Children are conjured into it
     * with `in:` or its own conjure sugar, never reparented in.
     * @throws WindowableException When the name is already taken.
     */
    public function group(string $name, int $x, int $y, int $width, int $height, ?OSGroup $in = null): OSGroup
    {
        $this->guardName($name);

        /** @var Group */
        return $this->settle($this->mintGroup($name, $in), $in, $x, $y, $width, $height);
    }

    /**
     * Conjure a scrolling container and place it. The frame is the
     * viewport; setContentSize() on the view is the scrollable extent.
     * @throws WindowableException When the name is already taken.
     */
    public function scrollView(string $name, int $x, int $y, int $width, int $height, ?OSGroup $in = null): OSScrollView
    {
        $this->guardName($name);

        /** @var ScrollView */
        return $this->settle($this->mintScrollView($name, $in), $in, $x, $y, $width, $height);
    }

    /**
     * A conjured node by name, or null.
     */
    public function view(string $name): ?OSView
    {
        return $this->views->get($name);
    }

    /**
     * Drop a name from the registry. Called by View::remove() after the
     * native node is gone — removal is terminal, the name is free again.
     */
    public function forgetView(string $name): void
    {
        $this->views->forget($name);
    }

    /**
     * Show the OS's own About panel with the program's registered identity.
     *
     * The ABOUT menu role lands here on both engines; a sketch owning About
     * through an event can call it too.
     */
    public function showAbout(): void
    {
        $this->presentAbout($this->resolveAbout());
    }

    /**
     * Look the registered identity up. Overridable so the flow is provable without a container.
     */
    protected function resolveAbout(): ?AboutInfo
    {
        return app('live-app')->getAbout();
    }

    /**
     * Present the engine's native About with this identity, or its bare panel on null.
     */
    abstract protected function presentAbout(?AboutInfo $about): void;

    /**
     * Detect a content resize and, if there was one, re-resolve every view
     * and push WINDOW_RESIZED. The OS-level resource calls this per window
     * after each pump; the sketch is not involved.
     *
     * @return bool Whether the content size changed since the last call.
     */
    public function syncLayout(): bool
    {
        $size = $this->contentSize();
        if ($size === $this->laid_out_size) {
            return false;
        }

        $this->laid_out_size = $size;
        $this->relayout();
        $this->emitWindowResized($size[0], $size[1]);

        return true;
    }

    /**
     * Re-resolve every conjured view's rules against the current content size.
     * @return void
     */
    public function relayout(): void
    {
        foreach ($this->views as $view) {
            /** @var OSView $view */
            $view->relayout();
        }
    }

    /**
     * The content area's size in pixels, the space center() divides.
     * GTK answers 0x0 before its first layout; AppKit answers at once.
     * @return array{int, int} [width, height]
     */
    abstract public function contentSize(): array;

    /**
     * Mint a native label wrapped in the engine's Label subclass. The node
     * is attached to `$in`'s surface — or the window content on null — but
     * not yet placed; Windowable::label() does that. Every mint hook reads
     * `$in` the same way.
     */
    abstract protected function mintLabel(string $name, string $text, ?OSGroup $in): Label;

    /**
     * Mint a native button wrapped in the engine's Button subclass, with its
     * click already wired to fireClick(). Attached but not yet placed.
     */
    abstract protected function mintButton(string $name, string $label, ?OSGroup $in): Button;

    /**
     * Mint a native indeterminate spinner wrapped in the engine's Spinner
     * subclass, attached but stopped and not yet placed.
     */
    abstract protected function mintSpinner(string $name, ?OSGroup $in): Spinner;

    /**
     * Mint a native image view wrapped in the engine's Image subclass,
     * attached but not yet placed; a non-null $path is already loaded.
     */
    abstract protected function mintImage(string $name, ?string $path, ?OSGroup $in): Image;

    /**
     * Mint a native video player wrapped in the engine's Video subclass,
     * attached but not yet placed; a non-null $path is already loaded,
     * paused.
     */
    abstract protected function mintVideo(string $name, ?string $path, ?OSGroup $in): Video;

    /**
     * Mint a native single-line text field wrapped in the engine's
     * TextInput subclass, with edit and submit wired to fireChanged() /
     * fireSubmitted(). Attached but not yet placed.
     */
    abstract protected function mintTextInput(string $name, string $value, ?string $placeholder, bool $secret, ?OSGroup $in): TextInput;

    /**
     * Mint a native multi-line editor wrapped in the engine's TextArea
     * subclass, with its buffer change wired to fireChanged(). Attached but
     * not yet placed.
     */
    abstract protected function mintTextArea(string $name, string $value, ?OSGroup $in): TextArea;

    /**
     * Mint a native slider wrapped in the engine's Slider subclass, with
     * its value change wired to fireChanged(). Attached but not yet placed.
     */
    abstract protected function mintSlider(string $name, float $min, float $max, float $value, ?OSGroup $in): Slider;

    /**
     * Mint a native on/off switch wrapped in the engine's Toggle subclass,
     * with its flip wired to fireToggled(). Attached but not yet placed.
     */
    abstract protected function mintToggle(string $name, bool $on, ?OSGroup $in): Toggle;

    /**
     * Mint a native two-state button wrapped in the engine's ToggleButton
     * subclass, with its toggle wired to fireToggled(). Attached but not
     * yet placed.
     */
    abstract protected function mintToggleButton(string $name, string $label, bool $pressed, ?OSGroup $in): ToggleButton;

    /**
     * Mint a native checkbox wrapped in the engine's Checkbox subclass,
     * with its toggle wired to fireToggled(). Attached but not yet placed.
     */
    abstract protected function mintCheckbox(string $name, string $label, bool $checked, ?OSGroup $in): Checkbox;

    /**
     * Mint a native determinate progress bar wrapped in the engine's
     * ProgressBar subclass, attached but not yet placed.
     */
    abstract protected function mintProgressBar(string $name, float $progress, ?OSGroup $in): ProgressBar;

    /**
     * Mint a native dropdown wrapped in the engine's Dropdown subclass,
     * with its selection change wired to fireSelected(). Attached but not
     * yet placed.
     * @param list<string> $options
     */
    abstract protected function mintDropdown(string $name, array $options, int $selected, ?OSGroup $in): Dropdown;

    /**
     * Mint a native separator line wrapped in the engine's Separator
     * subclass, attached but not yet placed.
     */
    abstract protected function mintSeparator(string $name, bool $horizontal, ?OSGroup $in): Separator;

    /**
     * Mint a native container wrapped in the engine's Group subclass,
     * attached but not yet placed. Later mints with this group as `$in`
     * parent their natives under its surface.
     */
    abstract protected function mintGroup(string $name, ?OSGroup $in): Group;

    /**
     * Mint a native scrolling container wrapped in the engine's ScrollView
     * subclass, attached but not yet placed, scrollbars owned by the engine.
     */
    abstract protected function mintScrollView(string $name, ?OSGroup $in): ScrollView;

    /**
     * Receive the sink this window reports through.
     * @param PoolPump $pool
     * @return $this
     */
    public function setPool(PoolPump $pool): static
    {
        $this->io_pool = $pool;

        return $this;
    }

    /**
     * Push the MENU event for an activated item. Engine callbacks land here
     * so both platforms report identically; without a sink this is a no-op.
     * @param MenuItemSpec $item
     * @return void
     */
    /**
     * Push the WINDOW_CLOSED event for this window.
     *
     * Named `window.closed.<window>` so a sketch checks a specific window
     * with has('window.closed.main') and two windows closing in one tick
     * cannot collapse into one entry. Engine delegates call this from their
     * native close path; without a sink it is a no-op.
     * @return void
     */
    protected function emitWindowClosed(): void
    {
        if (is_null($this->io_pool)) {
            return;
        }

        $this->io_pool->push(new WindowClosed($this->name));
    }

    /**
     * Push an event observed on one of this window's views, named
     * `<type>.<window>.<view>` — window-qualified because view names are
     * only unique within a window.
     *
     * Views call this from their engine callbacks; without a sink it is a
     * no-op, same as every other emit.
     */
    public function emitViewEvent(SurfaceEventType $type, string $view, array $payload = []): void
    {
        if (is_null($this->io_pool)) {
            return;
        }

        match($type) {
            SurfaceEventType::BUTTON_CLICKED => $this->io_pool->push(new ButtonClicked($view, $this->name)),
            SurfaceEventType::TEXT_CHANGED => $this->io_pool->push(new TextChanged($view, $this->name, (string) ($payload['value'] ?? ''))),
            SurfaceEventType::TEXT_SUBMITTED => $this->io_pool->push(new TextSubmitted($view, $this->name, (string) ($payload['value'] ?? ''))),
            SurfaceEventType::VALUE_CHANGED => $this->io_pool->push(new ValueChanged($view, $this->name, (float) ($payload['value'] ?? 0.0))),
            SurfaceEventType::TOGGLED => $this->io_pool->push(new Toggled($view, $this->name, (bool) ($payload['on'] ?? false))),
            SurfaceEventType::SELECTION_CHANGED => $this->io_pool->push(new SelectionChanged($view, $this->name, (int) ($payload['index'] ?? -1), $payload['option'] ?? null)),
            default => $this->io_pool->push(new ViewComponentOccurrence(
                $this->name,
                $type,
                "{$type->value}.{$this->name}.{$view}",
                $payload
            ))
        };
    }

    /**
     * Push WINDOW_RESIZED, named `window.resized.<window>`, with the new size.
     * @return void
     */
    protected function emitWindowResized(int $width, int $height): void
    {
        if (is_null($this->io_pool)) {
            return;
        }

        $this->io_pool->push(new WindowResized($this->name, $width, $height));
    }

    protected function emitMenuEvent(MenuItemSpec $item): void
    {
        if (is_null($this->io_pool) || is_null($item->event)) {
            return;
        }

        match($item->event) {
            'quit' => $this->io_pool->push(new QuitRequested()),
            default => $this->io_pool->push(new MenuOccurrence(
                $this->name, $item->event, $item->id, $item->label
            ))
        };


    }
}