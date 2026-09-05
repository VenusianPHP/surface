<?php

namespace Surface\NativeWindows\Components;

/**
 * An empty host that stacks nested Messages. Each push returns the part
 * key (`msg.1`, `msg.2`, …). Closing a Message's own × drops that part
 * and re-stacks the rest.
 *
 * Parts: `msg.<n>` — each is the nested Message's root.
 */
class Toast extends Component
{
    protected const GAP = 8;

    protected const TOAST_HEIGHT = 40;

    protected int $sequence = 0;

    /** @var array<string, Message> */
    protected array $messages = [];

    protected function build(): void
    {
        // Toasts land through push().
    }

    /**
     * Stack a Message at the next key. Closable by default; its ×
     * removes that subtree, drops the part, and re-lays the rest.
     */
    public function push(
        string $text,
        MessageSeverity $severity = MessageSeverity::INFO,
        bool $closable = true,
    ): string {
        $this->sequence++;
        $key = "msg.{$this->sequence}";
        [$width] = $this->innerSize();

        $message = new Message(
            $this->window,
            $this->partName($key),
            0,
            0,
            $width,
            self::TOAST_HEIGHT,
            $text,
            $severity,
            $closable,
            in: $this->root,
        );
        $this->register($key, $message->root());
        $this->messages[$key] = $message;
        $message->onClose(function () use ($key): void {
            unset($this->parts[$key], $this->messages[$key]);
            $this->layout();
        });
        $this->layout();

        return $key;
    }

    protected function layout(): void
    {
        [$width] = $this->innerSize();
        $y = 0;

        foreach ($this->messages as $message) {
            $message->place(0, $y, $width, self::TOAST_HEIGHT);
            $y += self::TOAST_HEIGHT + self::GAP;
        }
    }
}
