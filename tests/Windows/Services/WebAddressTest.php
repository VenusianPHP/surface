<?php

declare(strict_types=1);

use Surface\Windows\Services\WebAddress;

it('passes an http or https address with a host through unchanged', function (string $url): void {
    expect(WebAddress::checked($url))->toBe($url);
})->with([
    'https' => 'https://projectsaturnstudios.com/stargazer',
    'http with a port and query' => 'http://localhost:8080/a?b=c',
    'upper-case scheme' => 'HTTPS://example.com',
]);

it('refuses anything that is not an http or https address with a host', function (string $url): void {
    expect(fn () => WebAddress::checked($url))->toThrow(InvalidArgumentException::class);
})->with([
    'a file' => 'file:///etc/hosts',
    'script' => 'javascript:alert(1)',
    'no host' => 'https:///path',
    'no scheme' => 'example.com',
    'empty' => '',
]);
