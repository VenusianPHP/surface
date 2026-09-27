<?php

namespace Surface\EmbeddedDisplays;

use GeneralPurposeIO\Contracts\IntegratedCircuits\BootSequence;
use GeneralPurposeIO\Contracts\IntegratedCircuits\DisplayPanel;
use GeneralPurposeIO\Contracts\IntegratedCircuits\RefreshesOnCommand;
use GeneralPurposeIO\Contracts\IntegratedCircuits\WindowAddressable;
use Surface\Contracts\Drawing\CPUEngine;
use Surface\Contracts\Drawing\CPUEngineDriver;
use Surface\Contracts\Drawing\CPUHost;
use Surface\Contracts\EmbeddedDisplays\EmbeddedDisplayException;
use Surface\Contracts\Framebuffers\FormatSpecification;
use Voyager\Contracts\Config\Repository;
use Voyager\Contracts\Vessel\Vessel;

/**
 * Holds the displays a sketch attached. attach() sizes a CPU canvas to the
 * panel in the panel's own format and asks the CPU engine seam for it by
 * name, so this package never names a concrete engine. With no engine named,
 * the panel's kind picks: a panel that refreshes on command gets the epaper
 * engine, one that addresses a window gets dirty, anything else gets full.
 */
class EmbeddedDisplayManager
{
    /** @var array<string, EmbeddedDisplay> */
    protected array $displays = [];

    public function __construct(protected Vessel $vessel) {}

    /** The panel must also speak Surface's pixel formats; gpio/contracts deliberately does not know them. */
    public function attach(DisplayPanel&FormatSpecification $panel, string $name, CPUEngine|string|null $engine = null, ?CPUHost $host = null): EmbeddedDisplay
    {
        if (isset($this->displays[$name])) {
            throw EmbeddedDisplayException::nameTaken($name);
        }

        if ($panel instanceof BootSequence && ! $panel->hasBooted()) {
            throw EmbeddedDisplayException::notBooted($name);
        }

        $host ??= new CPUHost($panel->width(), $panel->height(), $panel->formatSpec());

        if ($host->width !== $panel->width() || $host->height !== $panel->height() || ! $host->format->equals($panel->formatSpec())) {
            throw EmbeddedDisplayException::hostMismatch($name, $panel->width(), $panel->height());
        }

        $display = new EmbeddedDisplay(
            $name,
            $panel,
            $this->engine($this->engineFor($panel, $engine))->attach($host),
            fn (string $closed) => $this->forget($closed),
        );

        if ($this->vessel->bound('io-pool')) {
            $display->setPool($this->vessel->get('io-pool'));
        }

        return $this->displays[$name] = $display;
    }

    /**
     * Conjure a panel. The GPIO catalog builds the chip from the app's
     * config/circuits/<panel>.php — default_config, or the one named — so a
     * sketch never spells out an adapter, a bus or a pin. Asking again for a
     * display already conjured answers the same one.
     */
    public function panel(string $panel, ?string $config = null, ?string $name = null, CPUEngine|string|null $engine = null, ?CPUHost $host = null): EmbeddedDisplay
    {
        $name ??= is_null($config) ? $panel : "{$panel}.{$config}";

        if (isset($this->displays[$name])) {
            return $this->displays[$name];
        }

        $chip = $this->catalog()->conjure($panel, $config);

        if (! $chip instanceof DisplayPanel || ! $chip instanceof FormatSpecification) {
            throw EmbeddedDisplayException::notADisplayPanel($panel, get_debug_type($chip));
        }

        return $this->attach($chip, $name, $engine, $host);
    }

    /** Close the display and forget it. */
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

    /** Program teardown: close every display. A failure does not spare the rest; the first is rethrown. */
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

    /**
     * The GPIO catalog, by container key like every other dock service here.
     * Not by its contract: `gpio/contracts` is a peer package on its own
     * release cadence, and what Surface actually needs guaranteed is the type
     * of the panel that comes back, which panel() checks.
     */
    protected function catalog(): object
    {
        return $this->vessel->bound('circuit')
            ? $this->vessel->get('circuit')
            : throw EmbeddedDisplayException::noCatalog();
    }

    protected function engineFor(DisplayPanel $panel, CPUEngine|string|null $engine): string
    {
        if (! is_null($engine)) {
            return $engine instanceof CPUEngine ? $engine->value : $engine;
        }

        return match (true) {
            $panel instanceof RefreshesOnCommand => $this->config()->get('embedded-displays.defaults.refreshing', CPUEngine::EPAPER->value),
            $panel instanceof WindowAddressable => $this->config()->get('embedded-displays.defaults.addressable', CPUEngine::DIRTY->value),
            default => $this->config()->get('embedded-displays.defaults.whole', CPUEngine::FULL->value),
        };
    }

    protected function engine(string $name): CPUEngineDriver
    {
        return $this->vessel->get('cpu-engines')->driver($name);
    }

    protected function config(): Repository
    {
        return $this->vessel->make('config');
    }
}
