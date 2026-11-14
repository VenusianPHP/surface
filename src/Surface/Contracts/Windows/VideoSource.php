<?php

namespace Surface\Contracts\Windows;

/**
 * Where a staged window's video gets its picture. The app picks: Surface makes
 * the one it is asked for and refuses the one it cannot make.
 */
enum VideoSource: string
{
    /**
     * The toolkit's own media player, in a view of its own laid over the
     * window's output: a NativeVideo. Needs WindowCapability::Video.
     */
    case Native = 'native';

    /**
     * Frames ext-ffmpeg decodes into a framebuffer the app draws with
     * RenderingEngine::image(): a DecodedVideo. Needs ext-ffmpeg and ext-fb.
     */
    case Decoded = 'decoded';
}
