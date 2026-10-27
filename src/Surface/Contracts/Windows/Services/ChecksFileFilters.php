<?php

namespace Surface\Contracts\Windows\Services;

use InvalidArgumentException;

/** The filter rule OpenRequest and SaveRequest share. */
trait ChecksFileFilters
{
    /**
     * @param array<string, list<string>> $filters filter name => glob patterns
     * @throws InvalidArgumentException When a name is not a non-empty string, or its patterns are not a list of one or more non-empty strings.
     */
    private static function checkFilters(array $filters): void
    {
        foreach ($filters as $name => $patterns) {
            if (! is_string($name) || $name === '') {
                throw new InvalidArgumentException('A file filter name must be a non-empty string.');
            }
            if (! is_array($patterns) || $patterns === [] || ! array_is_list($patterns)) {
                throw new InvalidArgumentException("File filter '{$name}' must have a list of one or more patterns.");
            }
            foreach ($patterns as $pattern) {
                if (! is_string($pattern) || $pattern === '') {
                    throw new InvalidArgumentException("File filter '{$name}' has a pattern that is not a non-empty string.");
                }
            }
        }
    }
}
