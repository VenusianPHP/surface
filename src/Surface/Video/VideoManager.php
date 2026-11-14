<?php

namespace Surface\Video;

use Surface\Contracts\Video\VideoDriver;
use Surface\Contracts\Video\VideoException;
use Voyager\NutsAndBolts\Manager;

/**
 * The video drivers registered by video packages, by name. Surface knows no
 * decoding library: a package extend()s this manager with its driver, and the
 * default is the first registered name in video.<os>.default.
 */
class VideoManager extends Manager
{
    /** Where the drivers Surface's error messages name come from. */
    protected const array PACKAGES = [
        'avkit' => 'jovian/venusian-appkit',
        'avkit-player' => 'jovian/venusian-appkit',
        'ffmpeg' => 'jovian/venusian-ffmpeg',
        'gstreamer' => 'jovian/venusian-gstreamer',
        'gstreamer-playbin' => 'jovian/venusian-gstreamer',
        'vlc' => 'jovian/venusian-vlc',
    ];

    /** The first name in video.<os>.default (a name or a list of names) that a package registered. */
    public function getDefaultDriver(): ?string
    {
        $wanted = (array) config('video.'.device_os_family().'.default', []);
        foreach ($wanted as $name) {
            if (isset($this->customCreators[$name])) {
                return $name;
            }
        }

        return null;
    }

    public function driver(?string $driver = null): VideoDriver
    {
        $name = $driver ?? $this->getDefaultDriver() ?? throw new VideoException($this->noDefault());
        if (! isset($this->customCreators[$name])) {
            throw new VideoException($this->unknown($name));
        }
        $made = parent::driver($name);

        return $made instanceof VideoDriver
            ? $made
            : throw new VideoException("The '{$name}' video driver is a ".get_debug_type($made).', not a '.VideoDriver::class.'.');
    }

    /** @return list<string> */
    public function registered(): array
    {
        return array_keys($this->customCreators);
    }

    protected function noDefault(): string
    {
        $wanted = implode(', ', (array) config('video.'.device_os_family().'.default', [])) ?: 'none';

        return "No video driver: video.".device_os_family().".default names {$wanted}, and the registered drivers are "
            .(implode(', ', $this->registered()) ?: 'none').'. A video package registers one.';
    }

    protected function unknown(string $name): string
    {
        $package = isset(self::PACKAGES[$name]) ? ' It comes with '.self::PACKAGES[$name].'.' : '';

        return "No video driver named '{$name}' is registered (registered: ".(implode(', ', $this->registered()) ?: 'none').").{$package}";
    }
}
