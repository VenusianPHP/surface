<?php

namespace Surface\Images;

use Surface\Contracts\Framebuffers\FramebufferDriver;
use Surface\Contracts\Images\ImageDecoder;
use Surface\Contracts\Images\ImageException;
use Surface\Images\Extended\ExtendedImageDecoder;
use Surface\Images\Native\NativeImageDecoder;
use Voyager\NutsAndBolts\Manager;

/**
 * Where the decoding runs. 'native' is ext-gd for PNG and JPEG and PHP for
 * TIFF; 'extended' is C and needs ext-imgdec; 'auto' (the default) is
 * extended when ext-imgdec is loaded, native when not. The image lands in a
 * full RGBA8 framebuffer on the framebuffer driver config names:
 *
 *     $tile = app('images')->decode($response->body());
 *
 * @method ImageDecoder driver(?string $driver = null)
 * @method \Surface\Contracts\Framebuffers\Framebuffer decode(string $bytes)
 */
class ImagesManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('images.default', 'auto');
    }

    /** Extended when ext-imgdec is loaded, native when not. */
    public function createAutoDriver(): ImageDecoder
    {
        return function_exists('imgdec_png') ? $this->createExtendedDriver() : $this->createNativeDriver();
    }

    public function createNativeDriver(): ImageDecoder
    {
        return new NativeImageDecoder($this->framebuffers());
    }

    /** @throws ImageException When ext-imgdec is not loaded in this PHP. */
    public function createExtendedDriver(): ImageDecoder
    {
        return new ExtendedImageDecoder($this->framebuffers());
    }

    /** 'images.framebuffers' names the driver decoded images live on; unset, it is the framebuffers default. */
    protected function framebuffers(): FramebufferDriver
    {
        return $this->vessel->make('framebuffers')->driver($this->config->get('images.framebuffers'));
    }
}
