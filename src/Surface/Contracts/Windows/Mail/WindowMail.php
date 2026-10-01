<?php

namespace Surface\Contracts\Windows\Mail;

use Voyager\Contracts\Signals\NamedSignal;

interface WindowMail extends NamedSignal
{
    public function window(): string;
}