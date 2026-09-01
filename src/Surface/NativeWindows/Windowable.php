<?php

namespace Surface\NativeWindows;

use Surface\Contracts\Core\AboutInfo;
use Surface\Contracts\NativeWindows\OSWindow;
use Surface\Contracts\NativeWindows\WindowableException;
use Voyager\Contracts\IOPools\EventSink;
use Surface\Contracts\NativeWindows\Events\SurfaceEvent;
use Surface\Contracts\NativeWindows\Events\SurfaceEventType;
use Surface\Contracts\NativeWindows\Views\OSButton;
use Surface\Contracts\NativeWindows\Views\OSLabel;
use Surface\Contracts\NativeWindows\Views\OSView;
use Surface\NativeWindows\Menus\MenuItemSpec;
use Surface\Contracts\NativeWindows\Views\OSImage;
use Surface\Contracts\NativeWindows\Views\OSSpinner;
use Surface\Contracts\NativeWindows\Views\OSVideo;
use Surface\NativeWindows\Views\Button;
use Surface\NativeWindows\Views\Image;
use Surface\NativeWindows\Views\Label;
use Surface\NativeWindows\Views\Spinner;
use Surface\NativeWindows\Views\Video;
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
     * Handed over by ProgramShuttle at provisioning.
     * @var EventSink|null
     */
    protected ?EventSink $event_sink = null;

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
     * @param string $profile Name registered through ProgramShuttle::addMenuBarProfiles().
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
        return app('os-program')->getMenuBarProfile($profile);
    }

    /**
     * Conjure a label into the content and place it. Top-left pixels.
     *
     * The engine mints the native node through mintLabel(); Surface owns
     * the name registry and the frame.
     *
     * @throws WindowableException When the name is already taken.
     */
    public function label(string $name, string $text, int $x, int $y, int $width, int $height): OSLabel
    {
        if ($this->views->has($name)) {
            throw new WindowableException("View '{$name}' already exists in window '{$this->name}'.");
        }

        $label = $this->mintLabel($name, $text);
        $this->views->put($name, $label);
        $label->place($x, $y, $width, $height);

        return $label;
    }

    /**
     * Conjure a button into the content and place it. Top-left pixels.
     * @throws WindowableException When the name is already taken.
     */
    public function button(string $name, string $label, int $x, int $y, int $width, int $height): OSButton
    {
        if ($this->views->has($name)) {
            throw new WindowableException("View '{$name}' already exists in window '{$this->name}'.");
        }

        $button = $this->mintButton($name, $label);
        $this->views->put($name, $button);
        $button->place($x, $y, $width, $height);

        return $button;
    }

    /**
     * Conjure an indeterminate busy spinner into the content and place it.
     * Top-left pixels. Conjured stopped — the sketch decides when to spin.
     * @throws WindowableException When the name is already taken.
     */
    public function spinner(string $name, int $x, int $y, int $width, int $height): OSSpinner
    {
        if ($this->views->has($name)) {
            throw new WindowableException("View '{$name}' already exists in window '{$this->name}'.");
        }

        $spinner = $this->mintSpinner($name);
        $this->views->put($name, $spinner);
        $spinner->place($x, $y, $width, $height);

        return $spinner;
    }

    /**
     * Conjure an image into the content and place it. Top-left pixels.
     * A null path conjures an empty picture to setPath() into later.
     * @throws WindowableException When the name is already taken.
     */
    public function image(string $name, ?string $path, int $x, int $y, int $width, int $height): OSImage
    {
        if ($this->views->has($name)) {
            throw new WindowableException("View '{$name}' already exists in window '{$this->name}'.");
        }

        $image = $this->mintImage($name, $path);
        $this->views->put($name, $image);
        $image->place($x, $y, $width, $height);

        return $image;
    }

    /**
     * Conjure a video player into the content and place it. Top-left pixels.
     * A null path conjures an empty player to setPath() into later; playback
     * starts paused either way.
     * @throws WindowableException When the name is already taken.
     */
    public function video(string $name, ?string $path, int $x, int $y, int $width, int $height): OSVideo
    {
        if ($this->views->has($name)) {
            throw new WindowableException("View '{$name}' already exists in window '{$this->name}'.");
        }

        $video = $this->mintVideo($name, $path);
        $this->views->put($name, $video);
        $video->place($x, $y, $width, $height);

        return $video;
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
        return app('os-program')->getAbout();
    }

    /**
     * Present the engine's native About with this identity, or its bare panel on null.
     */
    abstract protected function presentAbout(?AboutInfo $about): void;

    /**
     * Detect a content resize and, if there was one, re-resolve every view
     * and push WINDOW_RESIZED. ProgramShuttle::tick() calls this per window
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
     * Mint a native label wrapped in the engine's Label subclass. The node is
     * attached to the content but not yet placed — Windowable::label() does that.
     */
    abstract protected function mintLabel(string $name, string $text): Label;

    /**
     * Mint a native button wrapped in the engine's Button subclass, with its
     * click already wired to fireClick(). Attached but not yet placed.
     */
    abstract protected function mintButton(string $name, string $label): Button;

    /**
     * Mint a native indeterminate spinner wrapped in the engine's Spinner
     * subclass, attached but stopped and not yet placed.
     */
    abstract protected function mintSpinner(string $name): Spinner;

    /**
     * Mint a native image view wrapped in the engine's Image subclass,
     * attached but not yet placed; a non-null $path is already loaded.
     */
    abstract protected function mintImage(string $name, ?string $path): Image;

    /**
     * Mint a native video player wrapped in the engine's Video subclass,
     * attached but not yet placed; a non-null $path is already loaded,
     * paused.
     */
    abstract protected function mintVideo(string $name, ?string $path): Video;

    /**
     * Receive the sink this window reports through.
     * @param EventSink $sink
     * @return $this
     */
    public function setEventSink(EventSink $sink): static
    {
        $this->event_sink = $sink;

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
        if (is_null($this->event_sink)) {
            return;
        }

        $this->event_sink->push(new SurfaceEvent(
            type: SurfaceEventType::WINDOW_CLOSED,
            name: SurfaceEventType::WINDOW_CLOSED->value . ".{$this->name}",
            window: $this->name,
        ));
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
        if (is_null($this->event_sink)) {
            return;
        }

        $this->event_sink->push(new SurfaceEvent(
            type: $type,
            name: "{$type->value}.{$this->name}.{$view}",
            window: $this->name,
            payload: $payload,
        ));
    }

    /**
     * Push WINDOW_RESIZED, named `window.resized.<window>`, with the new size.
     * @return void
     */
    protected function emitWindowResized(int $width, int $height): void
    {
        if (is_null($this->event_sink)) {
            return;
        }

        $this->event_sink->push(new SurfaceEvent(
            type: SurfaceEventType::WINDOW_RESIZED,
            name: SurfaceEventType::WINDOW_RESIZED->value . ".{$this->name}",
            window: $this->name,
            payload: ['width' => $width, 'height' => $height],
        ));
    }

    protected function emitMenuEvent(MenuItemSpec $item): void
    {
        if (is_null($this->event_sink) || is_null($item->event)) {
            return;
        }

        $this->event_sink->push(new SurfaceEvent(
            type: SurfaceEventType::MENU,
            name: $item->event,
            window: $this->name,
            payload: ['id' => $item->id, 'label' => $item->label],
        ));
    }
}