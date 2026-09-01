<?php

if(!function_exists('device_os_family')) {
    function device_os_family(): string
    {
        return str_contains(php_uname(), 'Darwin') ? 'mac' : 'linux';
    }
}