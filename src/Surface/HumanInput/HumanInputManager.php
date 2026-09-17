<?php

namespace Surface\HumanInput;

use Surface\Contracts\HumanInput\Circuits\ButtonPad;
use Surface\Contracts\HumanInput\Devices\GameController as GameControllerContract;
use Surface\Contracts\HumanInput\Devices\Keyboard;
use Surface\Contracts\HumanInput\Devices\Mouse;
use Surface\Contracts\HumanInput\HumanInputException;
use Surface\Contracts\HumanInput\InputEngineDriver;
use Voyager\NutsAndBolts\Manager;

/**
 * Names an input engine and resolves the container alias its package bound;
 * holds the circuits a sketch attached. Reads the injected config repository,
 * never the global helper. Missing package: the container's own not-found —
 * the same decision as the bridge, GPU and stage seams. Engines start lazily:
 * the first engine() call connects.
 */
class HumanInputManager extends Manager
{
    /** @var array<string, InputEngineDriver> connected engines, by name */
    protected array $engines = [];

    /** @var array<string, ICInput> */
    protected array $circuits = [];

    public function getDefaultDriver(): string
    {
        return $this->config->get('human-input.default', 'sdl3');
    }

    public function engine(?string $name = null): InputEngineDriver
    {
        $name ??= $this->getDefaultDriver();

        if (isset($this->engines[$name])) {
            return $this->engines[$name];
        }

        /** @var InputEngineDriver $engine */
        $engine = $this->driver($name);

        return $this->engines[$name] = $engine->connect();
    }

    /** @return array<string, InputEngineDriver> */
    public function engines(): array
    {
        return $this->engines;
    }

    public function attach(ButtonPad $ic, string $name): ICInput
    {
        if (isset($this->circuits[$name])) {
            throw HumanInputException::nameTaken($name);
        }

        return $this->circuits[$name] = new ICInput($name, $ic);
    }

    public function detach(string $name): void
    {
        if (! isset($this->circuits[$name])) {
            throw HumanInputException::noSuchCircuit($name);
        }

        unset($this->circuits[$name]);
    }

    /** @return array<string, ICInput> */
    public function circuits(): array
    {
        return $this->circuits;
    }

    public function keyboard(): ?Keyboard
    {
        return $this->engine()->keyboard();
    }

    public function mouse(): ?Mouse
    {
        return $this->engine()->mouse();
    }

    /** @return array<string, \Surface\Contracts\HumanInput\Devices\GamePad> */
    public function gamePads(): array
    {
        $pads = [];

        foreach ($this->engines as $engine) {
            $pads += $engine->gamePads();
        }

        foreach ($this->circuits as $name => $input) {
            if ($input->connected() && ! $input->device() instanceof GameControllerContract) {
                $pads[$name] = $input->device();
            }
        }

        return $pads;
    }

    /** @return array<string, GameControllerContract> */
    public function gameControllers(): array
    {
        $controllers = [];

        foreach ($this->engines as $engine) {
            $controllers += $engine->gameControllers();
        }

        foreach ($this->circuits as $name => $input) {
            if ($input->connected() && $input->device() instanceof GameControllerContract) {
                $controllers[$name] = $input->device();
            }
        }

        return $controllers;
    }

    /** Program teardown: disconnect every engine this manager connected. A failure does not spare the rest; the first is rethrown. */
    public function destroy(): void
    {
        $failure = null;

        foreach ($this->engines as $engine) {
            try {
                $engine->disconnect();
            } catch (\Throwable $e) {
                $failure ??= $e;
            }
        }

        $this->engines = [];

        if (! is_null($failure)) {
            throw $failure;
        }
    }

    protected function createSdl3Driver(): InputEngineDriver
    {
        return $this->resolveAlias('sdl3', 'input.sdl3');
    }

    protected function createAppkitDriver(): InputEngineDriver
    {
        return $this->resolveAlias('appkit', 'input.appkit');
    }

    protected function createGtkDriver(): InputEngineDriver
    {
        return $this->resolveAlias('gtk', 'input.gtk');
    }

    protected function resolveAlias(string $engine, string $default): InputEngineDriver
    {
        return $this->vessel->get($this->config->get("human-input.engines.{$engine}.alias", $default));
    }
}
