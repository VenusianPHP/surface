<?php

namespace Surface\Drawing;

use Surface\Contracts\Drawing\CPUEngineDriver;
use Voyager\NutsAndBolts\Manager;

class CPUEngineManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('cpu.default');
    }



    protected function resolveAlias(string $engine, string $default): CPUEngineDriver
    {
        return $this->vessel->get($this->config->get("cpu.engines.{$engine}.alias", $default));
    }
}