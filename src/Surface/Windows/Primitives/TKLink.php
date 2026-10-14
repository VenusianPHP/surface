<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Windows\Primitives\Placement;
use Surface\Contracts\Windows\Primitives\TKLink as PrimitiveContract;
use Surface\Contracts\Windows\Styling\FontSpec;
use Surface\Contracts\Windows\Styling\TextAlignment;
use Surface\Contracts\Windows\ToolkitWindow;
use Surface\Contracts\Windows\WindowException;

/**
 * The driver builds the toolkit's own link, which opens the URL; nothing here posts. A URL needs a
 * scheme, checked before the native is made and before a change reaches it.
 */
abstract class TKLink extends TKPrimitive implements PrimitiveContract
{
    protected TextAlignment $alignment = TextAlignment::LEFT;

    protected ?FontSpec $font = null;

    /**
     * @throws WindowException When the URL has no scheme or the name is not valid.
     */
    public function __construct(
        string $name,
        ToolkitWindow $window,
        ?TKPrimitiveGroup $parent,
        Placement $placement,
        protected string $text,
        protected string $url,
    ) {
        self::guardUrl($name, $url);
        parent::__construct($name, $window, $parent, $placement);
    }

    public function text(): string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->live();
        $this->text = $text;
        $this->applyText($text);

        return $this;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->live();
        self::guardUrl($this->name(), $url);
        $this->url = $url;
        $this->applyUrl($url);

        return $this;
    }

    public function setAlignment(TextAlignment $alignment): static
    {
        $this->live();
        $this->alignment = $alignment;
        $this->applyAlignment($alignment);

        return $this;
    }

    public function setFont(FontSpec $font): static
    {
        $this->live();
        $this->font = $font;
        $this->applyFont($font);

        return $this;
    }

    /**
     * @throws WindowException
     */
    protected static function guardUrl(string $name, string $url): void
    {
        if ((string) parse_url($url, PHP_URL_SCHEME) === '') {
            throw new WindowException("Link '{$name}' needs a URL with a scheme, got '{$url}'.");
        }
    }

    abstract protected function applyText(string $text): void;

    abstract protected function applyUrl(string $url): void;

    abstract protected function applyAlignment(TextAlignment $alignment): void;

    abstract protected function applyFont(FontSpec $font): void;
}
