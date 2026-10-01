<?php

namespace Surface\Contracts\Windows\Menus;

interface MenuProfile
{
    public static function parse(string $name, array $nodes): MenuProfile;
}