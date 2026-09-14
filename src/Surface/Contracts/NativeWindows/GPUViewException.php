<?php

namespace Surface\Contracts\NativeWindows;

class GPUViewException extends WindowableException
{
    public static function unsupported(string $engine, string $host): self
    {
        return new self("The '{$host}' window engine cannot host a '{$engine}' GPU region.");
    }
}
