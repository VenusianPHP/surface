<?php

namespace Surface\EmbeddedDisplays;

use Closure;
use GeneralPurposeIO\Contracts\IntegratedCircuits\BootSequence;
use GeneralPurposeIO\Contracts\IntegratedCircuits\DisplayPanel;
use Surface\Contracts\EmbeddedDisplays\EmbeddedDisplayException;
use Surface\Framebuffers\FramebufferManager;

/**
 * Holds the displays a sketch attached:
 *
 *     $oled = app('displays')->panel('ssd1306');        // built from config/circuits/ssd1306.php
 *     $tft = app('displays')->attach($st7789, 'tft');   // a chip the sketch built
 */
class EmbeddedDisplayManager
{
    /** @var array<string, EmbeddedDisplay> */
    protected array $displays = [];

    /**
     * @param  array{refreshing: string, addressable: string, whole: string}  $kinds  The framebuffer kind each panel behaviour gets.
     * @param  (Closure(): object)|null  $catalog  Answers the GPIO circuit registry, when one is installed.
     * @param  (Closure(object): void)|null  $post  Where fault mail goes.
     */
    public function __construct(
        protected readonly FramebufferManager $framebuffers,
        protected readonly array $kinds,
        protected readonly ?Closure $catalog = null,
        protected readonly ?Closure $post = null,
    ) {}

    /**
     * A display over a chip the sketch built. The chip must have booted and
     * answer formatSpec(); gpio/contracts leaves that method out because pixel
     * formats are Surface's vocabulary.
     */
    public function attach(DisplayPanel $panel, string $name): EmbeddedDisplay
    {
        if (isset($this->displays[$name])) {
            throw EmbeddedDisplayException::nameTaken($name);
        }
        if (! method_exists($panel, 'formatSpec')) {
            throw EmbeddedDisplayException::notADisplayPanel($name, get_debug_type($panel));
        }
        if ($panel instanceof BootSequence && ! $panel->hasBooted()) {
            throw EmbeddedDisplayException::notBooted($name);
        }

        return $this->displays[$name] = new EmbeddedDisplay(
            $name,
            $panel,
            $this->framebuffers,
            $this->kinds,
            fn (string $closed) => $this->forget($closed),
            $this->post,
        );
    }

    /**
     * Conjure a chip from config/circuits/<panel>.php — its default_config, or
     * the one named — and attach it, so a sketch never spells out an adapter,
     * a bus or a pin. Asking again for a display already conjured answers the
     * same one.
     */
    public function panel(string $panel, ?string $config = null, ?string $name = null): EmbeddedDisplay
    {
        $name ??= is_null($config) ? $panel : "{$panel}.{$config}";
        if (isset($this->displays[$name])) {
            return $this->displays[$name];
        }

        $catalog = is_null($this->catalog) ? throw EmbeddedDisplayException::noCatalog() : ($this->catalog)();
        $chip = $catalog->conjure($panel, $config);
        if (! $chip instanceof DisplayPanel || ! method_exists($chip, 'formatSpec')) {
            throw EmbeddedDisplayException::notADisplayPanel($panel, get_debug_type($chip));
        }

        return $this->attach($chip, $name);
    }

    /** Close the display; it leaves the manager. */
    public function detach(string $name): void
    {
        $this->display($name)->close();
    }

    public function display(string $name): EmbeddedDisplay
    {
        return $this->displays[$name] ?? throw EmbeddedDisplayException::noSuchDisplay($name);
    }

    public function has(string $name): bool
    {
        return isset($this->displays[$name]);
    }

    /** @return array<string, EmbeddedDisplay> */
    public function displays(): array
    {
        return $this->displays;
    }

    /** Close every display. A failure does not spare the rest; the first is rethrown. */
    public function destroy(): void
    {
        $failure = null;
        foreach ($this->displays as $display) {
            try {
                $display->close();
            } catch (\Throwable $e) {
                $failure ??= $e;
            }
        }

        $this->displays = [];

        if (! is_null($failure)) {
            throw $failure;
        }
    }

    protected function forget(string $name): void
    {
        unset($this->displays[$name]);
    }
}
