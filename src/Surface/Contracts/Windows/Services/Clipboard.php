<?php

namespace Surface\Contracts\Windows\Services;

interface Clipboard
{
    public function setText(string $text): void;
}
