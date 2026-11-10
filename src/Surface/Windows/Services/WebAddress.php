<?php

namespace Surface\Windows\Services;

use InvalidArgumentException;

/** The rule every Desktop::browse() applies: an http or https address with a host, nothing else. */
final class WebAddress
{
    /**
     * @return string the address, unchanged
     * @throws InvalidArgumentException When it is not an http or https address with a host.
     */
    public static function checked(string $url): string
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = parse_url($url, PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || $host === '') {
            throw new InvalidArgumentException("'{$url}' is not an http or https address with a host.");
        }

        return $url;
    }
}
