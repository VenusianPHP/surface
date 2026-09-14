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

    /** Backing scale the fake hands GPU engines. */
    public float $backing_scale = 1.0;

    /** The GPU engine driver the fake resolver hands back. */
    public ?FakeGPUEngineDriver $gpu_driver = null;

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

    protected function mintLabel(string $name, string $text, ?\Surface\Contracts\NativeWindows\Views\OSGroup $in): \Surface\NativeWindows\Views\Label
    {
        $label = new FakeLabel($name, $this, $text);
        $this->minted_labels[] = $label;

        return $label;
    }

    protected function mintButton(string $name, string $label, ?\Surface\Contracts\NativeWindows\Views\OSGroup $in): \Surface\NativeWindows\Views\Button
    {
        return new FakeButton($name, $this, $label);
    }

    protected function mintSpinner(string $name, ?\Surface\Contracts\NativeWindows\Views\OSGroup $in): \Surface\NativeWindows\Views\Spinner
    {
        return new FakeSpinner($name, $this);
    }

    protected function mintImage(string $name, ?string $path, ?\Surface\Contracts\NativeWindows\Views\OSGroup $in): \Surface\NativeWindows\Views\Image
    {
        return new FakeImage($name, $this, $path);
    }

    protected function mintVideo(string $name, ?string $path, ?\Surface\Contracts\NativeWindows\Views\OSGroup $in): \Surface\NativeWindows\Views\Video
    {
        return new FakeVideo($name, $this, $path);
    }

    protected function mintTextInput(string $name, string $value, ?string $placeholder, bool $secret, ?\Surface\Contracts\NativeWindows\Views\OSGroup $in): \Surface\NativeWindows\Views\TextInput
    {
        return new FakeTextInput($name, $this, $value, $placeholder, $secret);
    }

    protected function mintTextArea(string $name, string $value, ?\Surface\Contracts\NativeWindows\Views\OSGroup $in): \Surface\NativeWindows\Views\TextArea
    {
        return new FakeTextArea($name, $this, $value);
    }

    protected function mintSlider(string $name, float $min, float $max, float $value, ?\Surface\Contracts\NativeWindows\Views\OSGroup $in): \Surface\NativeWindows\Views\Slider
    {
        return new FakeSlider($name, $this, $min, $max, $value);
    }

    protected function mintToggle(string $name, bool $on, ?\Surface\Contracts\NativeWindows\Views\OSGroup $in): \Surface\NativeWindows\Views\Toggle
    {
        return new FakeToggle($name, $this, $on);
    }

    protected function mintToggleButton(string $name, string $label, bool $pressed, ?\Surface\Contracts\NativeWindows\Views\OSGroup $in): \Surface\NativeWindows\Views\ToggleButton
    {
        return new FakeToggleButton($name, $this, $label, $pressed);
    }

    protected function mintCheckbox(string $name, string $label, bool $checked, ?\Surface\Contracts\NativeWindows\Views\OSGroup $in): \Surface\NativeWindows\Views\Checkbox
    {
        return new FakeCheckbox($name, $this, $label, $checked);
    }

    protected function mintProgressBar(string $name, float $progress, ?\Surface\Contracts\NativeWindows\Views\OSGroup $in): \Surface\NativeWindows\Views\ProgressBar
    {
        return new FakeProgressBar($name, $this, $progress);
    }

    protected function mintDropdown(string $name, array $options, int $selected, ?\Surface\Contracts\NativeWindows\Views\OSGroup $in): \Surface\NativeWindows\Views\Dropdown
    {
        return new FakeDropdown($name, $this, $options, $selected);
    }

    protected function mintDatePicker(string $name, ?string $date, ?\Surface\Contracts\NativeWindows\Views\OSGroup $in): \Surface\NativeWindows\Views\DatePicker
    {
        return new FakeDatePicker($name, $this, $date);
    }

    protected function mintTable(string $name, array $columns, array $rows, ?\Surface\Contracts\NativeWindows\Views\OSGroup $in): \Surface\NativeWindows\Views\Table
    {
        return new FakeTable($name, $this, $columns, $rows);
    }

    protected function mintSeparator(string $name, bool $horizontal, ?\Surface\Contracts\NativeWindows\Views\OSGroup $in): \Surface\NativeWindows\Views\Separator
    {
        return new FakeSeparator($name, $this, $horizontal);
    }

    protected function mintGroup(string $name, ?\Surface\Contracts\NativeWindows\Views\OSGroup $in): \Surface\NativeWindows\Views\Group
    {
        return new FakeGroup($name, $this);
    }

    protected function mintScrollView(string $name, ?\Surface\Contracts\NativeWindows\Views\OSGroup $in): \Surface\NativeWindows\Views\ScrollView
    {
        return new FakeScrollView($name, $this);
    }

    /** Resolve from the fake's own driver, so the flow is provable without a container. */
    protected function resolveGPUEngine(\Surface\Contracts\Drawing\GPUEngine|string|null $engine): \Surface\Contracts\Drawing\GPUEngineDriver
    {
        $name = $engine instanceof \Surface\Contracts\Drawing\GPUEngine ? $engine : \Surface\Contracts\Drawing\GPUEngine::tryFrom($engine ?? 'metal') ?? \Surface\Contracts\Drawing\GPUEngine::METAL;

        return $this->gpu_driver ??= new FakeGPUEngineDriver($name);
    }

    /**
     * Mint order is the contract: the surface exists before attach(), the twin
     * is built after it — a twin built before its executor exists is a test
     * failure here, not a runtime surprise in an engine package.
     */
    protected function mintGPU(string $name, \Surface\Contracts\Drawing\GPUEngineDriver $driver, ?\Surface\Contracts\NativeWindows\Views\OSGroup $in): \Surface\NativeWindows\Views\GPUView
    {
        $gl = $driver->surfaceKind() === \Surface\Contracts\Drawing\SurfaceKind::GL_CONTEXT ? new FakeGLSurface() : null;
        $attachment = $driver->attach(new \Surface\Contracts\Drawing\GPUHost(0, 0, 0, $this->backing_scale, $gl));

        return new FakeGPUView($name, $this, $driver->engine(), $attachment->executor, $this->backing_scale, $gl);
    }

    /** Test door into the close path, as an engine's native close callback would use it. */
    public function fireClosed(): void
    {
        $this->emitWindowClosed();
    }
}
