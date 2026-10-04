<?php

namespace Surface\Rasterize;

use Surface\Contracts\Rasterize\RasterizeDriver;
use Surface\Contracts\Rasterize\RasterizeException;
use Surface\Rasterize\Extended\ExtendedRasterizeDriver;
use Surface\Rasterize\Native\NativeRasterizeDriver;
use Voyager\NutsAndBolts\Manager;

/**
 * Where the geometry runs. 'native' is PHP and always there; 'extended' is C
 * and needs ext-rasterize. Both answer the same span bytes:
 *
 *     app('rasterize')->driver()->rasterizer(Region::wholeSurface(320, 240), Edges::ANTIALIASED)->fillEllipse(160, 120, 80, 50);
 *
 * @method RasterizeDriver driver(?string $driver = null)
 */
class RasterizeManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('rasterize.default', 'native');
    }

    public function createNativeDriver(): RasterizeDriver
    {
        return new NativeRasterizeDriver();
    }

    /** @throws RasterizeException When ext-rasterize is not loaded in this PHP. */
    public function createExtendedDriver(): RasterizeDriver
    {
        return new ExtendedRasterizeDriver();
    }
}
