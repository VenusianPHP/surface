<?php

namespace Surface\Contracts\Windows\Mail\View;

use Voyager\Contracts\Signals\NamedSignal;

/**
 * Mail a primitive's native posts: named `view.<event>.<window>.<path>`.
 */
interface PrimitiveMail extends NamedSignal
{
    /**
     * @return string the window's name
     */
    public function window(): string;

    /**
     * @return string the primitive's dotted path
     */
    public function path(): string;

    /**
     * @return string the primitive's uuid
     */
    public function uuid(): string;
}
