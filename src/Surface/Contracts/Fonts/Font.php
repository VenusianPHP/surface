<?php

namespace Surface\Contracts\Fonts;

/** A face an engine can draw text with. */
interface Font
{
    public function type(): FontType;
}
