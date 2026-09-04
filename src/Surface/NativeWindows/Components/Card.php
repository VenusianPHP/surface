<?php

namespace Surface\NativeWindows\Components;

use Surface\Contracts\NativeWindows\Views\Color;
use Surface\Contracts\NativeWindows\Views\FontWeight;
use Surface\Contracts\NativeWindows\Views\OSGroup;
use Surface\Contracts\NativeWindows\Views\OSLabel;
use Surface\NativeWindows\Windowable;

/**
 * A titled panel: a heading, an optional subtitle, and a body group the
 * sketch conjures its own content into — `$card->body()->label(...)`.
 *
 * Parts: `title`, `subtitle` (only when given), `body`.
 */
class Card extends Component
{
    protected const PAD = 12;

    protected const TITLE_HEIGHT = 22;

    protected const SUBTITLE_HEIGHT = 18;

    public function __construct(
        Windowable $window,
        string $name,
        int $x,
        int $y,
        int $width,
        int $height,
        protected string $title,
        protected ?string $subtitle = null,
        ?OSGroup $in = null,
    ) {
        parent::__construct($window, $name, $x, $y, $width, $height, $in);
    }

    protected function build(): void
    {
        $title = $this->root->label($this->partName('title'), $this->title, 0, 0, 1, 1);
        $title->setFont(15.0, FontWeight::SEMIBOLD);
        $this->register('title', $title);

        if (! is_null($this->subtitle)) {
            $subtitle = $this->root->label($this->partName('subtitle'), $this->subtitle, 0, 0, 1, 1);
            $subtitle->setFont(12.0)->setTextColor(Color::hex('#6c757d'));
            $this->register('subtitle', $subtitle);
        }

        $this->register('body', $this->root->group($this->partName('body'), 0, 0, 1, 1));
    }

    protected function layout(): void
    {
        [$width, $height] = $this->innerSize();
        $inner_width = max(0, $width - 2 * self::PAD);
        $y = self::PAD;

        $this->parts['title']->place(self::PAD, $y, $inner_width, self::TITLE_HEIGHT);
        $y += self::TITLE_HEIGHT;

        if (isset($this->parts['subtitle'])) {
            $this->parts['subtitle']->place(self::PAD, $y, $inner_width, self::SUBTITLE_HEIGHT);
            $y += self::SUBTITLE_HEIGHT;
        }

        $y += self::PAD;
        $this->parts['body']->place(self::PAD, $y, $inner_width, max(0, $height - $y - self::PAD));
    }

    public function title(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        /** @var OSLabel $label */
        $label = $this->parts['title'];
        $label->setText($title);

        return $this;
    }

    public function setSubtitle(string $subtitle): static
    {
        $this->subtitle = $subtitle;

        if (isset($this->parts['subtitle'])) {
            /** @var OSLabel $label */
            $label = $this->parts['subtitle'];
            $label->setText($subtitle);

            return $this;
        }

        // A card built without one grows it, and the body makes room.
        $part = $this->root->label($this->partName('subtitle'), $subtitle, 0, 0, 1, 1);
        $part->setFont(12.0)->setTextColor(Color::hex('#6c757d'));
        $this->register('subtitle', $part);
        $this->layout();

        return $this;
    }

    /** The group the sketch conjures the card's content into. */
    public function body(): OSGroup
    {
        /** @var OSGroup */
        return $this->parts['body'];
    }
}
