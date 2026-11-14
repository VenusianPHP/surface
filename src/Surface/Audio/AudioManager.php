<?php

namespace Surface\Audio;

use Surface\Contracts\Audio\AudioDriver;
use Surface\Contracts\Audio\AudioException;
use Voyager\NutsAndBolts\Manager;

/**
 * The audio outputs registered by audio packages, by name. Surface knows no
 * audio library: a package extend()s this manager with its driver, and the
 * default is the first registered name in audio.<os>.default.
 */
class AudioManager extends Manager
{
    /** Where the drivers Surface's error messages name come from. */
    protected const array PACKAGES = [
        'avkit' => 'jovian/venusian-appkit',
        'libsoundio' => 'jovian/venusian-libsoundio',
        'miniaudio' => 'jovian/venusian-miniaudio',
        'sdl3' => 'jovian/venusian-sdl3',
        'sokol-audio' => 'jovian/venusian-sokol-audio',
        'soloud' => 'jovian/venusian-soloud',
    ];

    /** The first name in audio.<os>.default (a name or a list of names) that a package registered. */
    public function getDefaultDriver(): ?string
    {
        $wanted = (array) config('audio.'.device_os_family().'.default', []);
        foreach ($wanted as $name) {
            if (isset($this->customCreators[$name])) {
                return $name;
            }
        }

        return null;
    }

    public function driver(?string $driver = null): AudioDriver
    {
        $name = $driver ?? $this->getDefaultDriver() ?? throw new AudioException($this->noDefault());
        if (! isset($this->customCreators[$name])) {
            throw new AudioException($this->unknown($name));
        }
        $made = parent::driver($name);

        return $made instanceof AudioDriver
            ? $made
            : throw new AudioException("The '{$name}' audio driver is a ".get_debug_type($made).', not an '.AudioDriver::class.'.');
    }

    /** @return list<string> */
    public function registered(): array
    {
        return array_keys($this->customCreators);
    }

    protected function noDefault(): string
    {
        $wanted = implode(', ', (array) config('audio.'.device_os_family().'.default', [])) ?: 'none';

        return "No audio driver: audio.".device_os_family().".default names {$wanted}, and the registered drivers are "
            .(implode(', ', $this->registered()) ?: 'none').'. An audio package registers one.';
    }

    protected function unknown(string $name): string
    {
        $package = isset(self::PACKAGES[$name]) ? ' It comes with '.self::PACKAGES[$name].'.' : '';

        return "No audio driver named '{$name}' is registered (registered: ".(implode(', ', $this->registered()) ?: 'none').").{$package}";
    }
}
