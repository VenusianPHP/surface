<?php

namespace Surface\NativeWindows\Views;

use Surface\Contracts\NativeWindows\Views\OSScrollView;

/**
 * A container whose inner space can outgrow its frame. The frame is the
 * viewport; setContentSize() is the scrollable extent children lay out
 * against. The engine draws the scrollbars and owns the scrolling.
 *
 * Until setContentSize() is called the extent tracks the frame, so an
 * unconfigured scroll view behaves like a plain group.
 */
abstract class ScrollView extends ViewGroup implements OSScrollView
{
    protected ?int $content_width = null;

    protected ?int $content_height = null;

    public function setContentSize(int $width, int $height): static
    {
        $this->content_width = max(0, $width);
        $this->content_height = max(0, $height);
        $this->applyContentSize($this->content_width, $this->content_height);

        // The children's space just changed size — re-resolve them the way
        // a window resize re-resolves its views.
        foreach ($this->children() as $child) {
            $child->relayout();
        }

        return $this;
    }

    public function contentExtent(): array
    {
        return [
            $this->content_width ?? $this->width,
            $this->content_height ?? $this->height,
        ];
    }

    public function innerSize(): array
    {
        return $this->contentExtent();
    }

    /**
     * Size the native scrollable surface. The engine keeps the viewport
     * where it is; a shrunken extent may force a scroll.
     * @return void
     */
    abstract protected function applyContentSize(int $width, int $height): void;
}
