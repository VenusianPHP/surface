<?php

namespace Surface\Contracts\Bridge;

interface ToolkitLibrary
{
    public function connect(): BridgedToolkitSession;
}