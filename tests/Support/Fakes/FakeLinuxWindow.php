<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\Contracts\Drawing\GPUEngineDriver;
use Surface\Contracts\NativeWindows\GPUViewException;
use Surface\Contracts\NativeWindows\LinuxOSWindow;
use Surface\Contracts\NativeWindows\Views\OSGroup;
use Surface\NativeWindows\Views\GPUView;

/** A fake carrying the Linux marker interface. Refuses layer engines, hosts GL ones — GTK's line in slice 2. */
final class FakeLinuxWindow extends FakeWindow implements LinuxOSWindow
{
    /** GTK hosts GL surfaces and nothing else; a layer engine is refused by kind, no package named. */
    protected function mintGPU(string $name, GPUEngineDriver $driver, ?OSGroup $in): GPUView
    {
        if ($driver->surfaceKind() !== \Surface\Contracts\Drawing\SurfaceKind::GL_CONTEXT) {
            throw GPUViewException::unsupported($driver->engine()->value, 'gtk');
        }

        return parent::mintGPU($name, $driver, $in);
    }
}
