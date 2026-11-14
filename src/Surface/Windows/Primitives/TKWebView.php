<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Windows\Primitives\TKWebView as PrimitiveContract;
use Surface\Contracts\Windows\WindowException;

/**
 * url(), title() and isLoading() are the engine's last report (nativeLoadChanged()), not the
 * last call. The driver posts WebViewLoaded and WebViewFailed from the native signals.
 */
abstract class TKWebView extends TKPrimitive implements PrimitiveContract
{
    protected ?string $url = null;

    protected ?string $title = null;

    protected bool $loading = false;

    public function load(string $url): static
    {
        $this->live();
        $this->applyLoad(self::absolute($url));

        return $this;
    }

    public function loadHtml(string $html, ?string $baseUrl = null): static
    {
        $this->live();
        $this->applyHtml($html, is_null($baseUrl) ? null : self::absolute($baseUrl));

        return $this;
    }

    public function url(): ?string
    {
        return $this->url;
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function isLoading(): bool
    {
        return $this->loading;
    }

    public function reload(): static
    {
        $this->live()->applyReload();

        return $this;
    }

    public function stop(): static
    {
        $this->live()->applyStop();

        return $this;
    }

    /**
     * Engine callback: record what the engine reports, post nothing.
     *
     * @param bool $loading
     * @param string|null $url
     * @param string|null $title
     * @return void
     */
    public function nativeLoadChanged(bool $loading, ?string $url, ?string $title): void
    {
        [$this->loading, $this->url, $this->title] = [$loading, $url, $title];
    }

    /**
     * @throws WindowException
     */
    private static function absolute(string $url): string
    {
        if (! preg_match('~^[a-z][a-z0-9+.-]*:~i', $url) || ! is_array(parse_url($url))) {
            throw new WindowException("A web view needs an absolute URL, got '{$url}'.");
        }

        return $url;
    }

    abstract protected function applyLoad(string $url): void;

    abstract protected function applyHtml(string $html, ?string $baseUrl): void;

    abstract protected function applyReload(): void;

    abstract protected function applyStop(): void;
}
