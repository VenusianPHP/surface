<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\NativeWindows\Windowable;

/**
 * A window that records every call instead of touching an engine.
 *
 * Windowable supplies name() and the title() getter/setter combo; everything
 * else on the OSWindow contract is filled in here so driver and shuttle policy
 * can be asserted with no extension loaded.
 */
class FakeWindow extends Windowable
{
    /** Times present() actually reached the engine. */
    public int $presentations = 0;

    /** Times destroy() actually reached the engine. */
    public int $destructions = 0;

    /** Whether the window believes it is on screen. */
    public bool $presenting = false;

    /** The last title written, or null while none has been. */
    protected ?string $window_title = null;

    /** @var list<list<\Surface\NativeWindows\Menus\MenuItemSpec>> Every spec tree applied, in order. */
    public array $applied_menu_bars = [];

    /** @var array<string, list<\Surface\NativeWindows\Menus\MenuItemSpec>> Profiles the fake resolver hands back. */
    public array $known_profiles = [];

    /** @var array{int, int} What contentSize() answers. */
    public array $content_size = [640, 480];

    /** @var list<FakeLabel> Every label minted, in order. */
    public array $minted_labels = [];

    /** What the fake resolver hands showAbout(). */
    public ?\Surface\Contracts\Core\AboutInfo $known_about = null;

    /** @var list<\Surface\Contracts\Core\AboutInfo|null> Every About presented, in order. */
    public array $presented_abouts = [];

    public function setTitle(string $title): static
    {
        $this->window_title = $title;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->window_title;
    }

    public function present(): void
    {
        $this->presentations++;
        $this->presenting = true;
    }

    public function destroy(): void
    {
        $this->destructions++;
        $this->presenting = false;
    }

    public function isPresenting(): bool
    {
        return $this->presenting;
    }

    /** Resolve from the fake's own table, so the flow is provable without a container. */
    protected function resolveMenuBarProfile(string $profile): ?array
    {
        return $this->known_profiles[$profile] ?? null;
    }

    protected function applyMenuBar(array $spec): void
    {
        $this->applied_menu_bars[] = $spec;
    }

    /** Test door into the shared emit path, as an engine callback would use it. */
    public function fireMenuItem(\Surface\NativeWindows\Menus\MenuItemSpec $item): void
    {
        $this->emitMenuEvent($item);
    }

    public function contentSize(): array
    {
        return $this->content_size;
    }

    protected function resolveAbout(): ?\Surface\Contracts\Core\AboutInfo
    {
        return $this->known_about;
    }

    protected function presentAbout(?\Surface\Contracts\Core\AboutInfo $about): void
    {
        $this->presented_abouts[] = $about;
    }

    protected function mintLabel(string $name, string $text): \Surface\NativeWindows\Views\Label
    {
        $label = new FakeLabel($name, $this, $text);
        $this->minted_labels[] = $label;

        return $label;
    }

    protected function mintButton(string $name, string $label): \Surface\NativeWindows\Views\Button
    {
        return new FakeButton($name, $this, $label);
    }

    protected function mintSpinner(string $name): \Surface\NativeWindows\Views\Spinner
    {
        return new FakeSpinner($name, $this);
    }

    protected function mintImage(string $name, ?string $path): \Surface\NativeWindows\Views\Image
    {
        return new FakeImage($name, $this, $path);
    }

    protected function mintVideo(string $name, ?string $path): \Surface\NativeWindows\Views\Video
    {
        return new FakeVideo($name, $this, $path);
    }

    /** Test door into the close path, as an engine's native close callback would use it. */
    public function fireClosed(): void
    {
        $this->emitWindowClosed();
    }
}
