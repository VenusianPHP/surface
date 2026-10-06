<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\Endianness;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\PixelFormat;

require_once __DIR__.'/Fixtures/FakeSession.php';
require_once __DIR__.'/Fixtures/FakeWindowDriver.php';
require_once __DIR__.'/Fixtures/FakePrimitives.php';
require_once __DIR__.'/Fixtures/FakeFramebuffers.php';
require_once __DIR__.'/Fixtures/RecordingEngine.php';
require_once __DIR__.'/Fixtures/FakeManagers.php';
require_once __DIR__.'/Fixtures/FakePanels.php';
require_once __DIR__.'/Fixtures/FakeGpu.php';

/** An RGB565 host format, MSB first. */
function rgb565(): FormatSpec
{
    return new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B16, endianness: Endianness::MSB);
}
