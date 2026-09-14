<?php

namespace Surface\NativeWindows\Components;

use Closure;
use Surface\Contracts\NativeWindows\Views\OSButton;
use Surface\Contracts\NativeWindows\Views\OSDatePicker;
use Surface\Contracts\NativeWindows\Views\OSGroup;
use Surface\Contracts\NativeWindows\Views\OSTextInput;
use Surface\Contracts\NativeWindows\WindowableException;
use Surface\NativeWindows\Windowable;

/**
 * A date field. The component is a text input with a trigger button;
 * the trigger drops a `datePicker` calendar under the field, a pick
 * resolves the field to `Y-m-d`, collapses the calendar, and fires
 * onChange. Typing a full `Y-m-d` into the field resolves it the same
 * way; partial text is mid-edit and leaves date() alone. Programmatic
 * setDate is silent.
 *
 * The calendar is conjured on open and removed on close, so it always
 * lands above whatever the sketch conjured since, and it lives in the
 * component's host (the group the component was conjured into, or the
 * window) rather than inside the root — the root is field-sized and
 * clips.
 *
 * Parts: `input`, `trigger`, and `calendar` while open.
 */
class Datepicker extends Component
{
    protected ?Closure $on_change = null;

    protected ?int $year = null;

    protected ?int $month = null;

    protected ?int $day = null;

    protected bool $enabled = true;

    public function __construct(
        Windowable $window,
        string $name,
        int $x,
        int $y,
        int $width,
        int $height,
        protected ?string $date = null,
        protected string $placeholder = 'YYYY-MM-DD',
        protected int $calendar_width = 280,
        protected int $calendar_height = 190,
        protected ?OSGroup $in = null,
    ) {
        parent::__construct($window, $name, $x, $y, $width, $height, $in);
    }

    protected function build(): void
    {
        if (! is_null($this->date)) {
            [$this->year, $this->month, $this->day] = $this->parse($this->date);
        }

        $input = $this->root->textInput(
            $this->partName('input'),
            $this->date ?? '',
            0,
            0,
            1,
            1,
            placeholder: $this->placeholder,
        );
        $input->onChange(function (string $text): void {
            $this->typed($text);
        });
        $this->register('input', $input);

        $trigger = $this->root->button($this->partName('trigger'), '▾', 0, 0, 1, 1);
        $trigger->onClick(function (): void {
            $this->isOpen() ? $this->close() : $this->open();
        });
        $this->register('trigger', $trigger);
    }

    protected function layout(): void
    {
        [$width, $height] = $this->innerSize();
        $trigger = min($height, $width);
        $field = max(0, $width - $trigger);

        $this->parts['input']->place(0, 0, $field, $height);
        $this->parts['trigger']->place($field, 0, $trigger, $height);

        if ($this->isOpen()) {
            [$x, $y] = $this->calendarOrigin();
            $this->parts['calendar']->place($x, $y, $this->calendar_width, $this->calendar_height);
        }
    }

    /** The field, for anything not delegated below. */
    public function input(): OSTextInput
    {
        /** @var OSTextInput */
        return $this->parts['input'];
    }

    /** The dropped calendar while open, null while collapsed. */
    public function calendar(): ?OSDatePicker
    {
        /** @var ?OSDatePicker */
        return $this->parts['calendar'] ?? null;
    }

    public function isOpen(): bool
    {
        return isset($this->parts['calendar']);
    }

    /** Drop the calendar under the field, seeded with the current date. No-op while open or disabled. */
    public function open(): static
    {
        if ($this->isOpen() || ! $this->enabled) {
            return $this;
        }

        [$x, $y] = $this->calendarOrigin();
        $calendar = $this->window->datePicker(
            $this->partName('calendar'),
            $this->date(),
            $x,
            $y,
            $this->calendar_width,
            $this->calendar_height,
            in: $this->in,
        );
        $calendar->onChange(function (int $year, int $month, int $day): void {
            $this->resolve($year, $month, $day);
            $this->close();
            $this->fireChange();
        });
        $this->register('calendar', $calendar);

        return $this;
    }

    /** Collapse the calendar. The date stays whatever it last resolved to. */
    public function close(): static
    {
        if (! $this->isOpen()) {
            return $this;
        }

        $this->parts['calendar']->remove();
        unset($this->parts['calendar']);

        return $this;
    }

    public function date(): ?string
    {
        if (is_null($this->year) || is_null($this->month) || is_null($this->day)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $this->year, $this->month, $this->day);
    }

    public function year(): ?int
    {
        return $this->year;
    }

    public function month(): ?int
    {
        return $this->month;
    }

    public function day(): ?int
    {
        return $this->day;
    }

    /**
     * Silent: writes the field (and an open calendar) without firing onChange.
     * @throws WindowableException When $date is not a real Y-m-d.
     */
    public function setDate(?string $date): static
    {
        if (is_null($date)) {
            $this->year = null;
            $this->month = null;
            $this->day = null;
            $this->input()->setValue('');

            return $this;
        }

        [$year, $month, $day] = $this->parse($date);
        $this->resolve($year, $month, $day);

        return $this;
    }

    /** Hook receives (year, month, day), month 1-based, on user picks and typed resolutions only. */
    public function onChange(callable $hook): static
    {
        $this->on_change = $hook(...);

        return $this;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;
        $this->input()->setEnabled($enabled);
        $this->trigger()->setEnabled($enabled);

        if (! $enabled) {
            $this->close();
        }

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /** Hiding collapses the calendar — it lives outside the root, so the root cannot take it along. */
    public function setVisible(bool $visible): static
    {
        if (! $visible) {
            $this->close();
        }

        return parent::setVisible($visible);
    }

    public function remove(): void
    {
        $this->close();
        parent::remove();
    }

    protected function trigger(): OSButton
    {
        /** @var OSButton */
        return $this->parts['trigger'];
    }

    /** Store the day and mirror it into the field and an open calendar. Silent. */
    protected function resolve(int $year, int $month, int $day): void
    {
        $this->year = $year;
        $this->month = $month;
        $this->day = $day;

        $date = $this->date();
        if ($this->input()->value() !== $date) {
            $this->input()->setValue($date);
        }

        $this->calendar()?->setDate($date);
    }

    /** A typed edit resolves only once it is a whole, real Y-m-d. */
    protected function typed(string $text): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) !== 1) {
            return;
        }

        try {
            [$year, $month, $day] = $this->parse($text);
        } catch (WindowableException) {
            return;
        }

        $this->resolve($year, $month, $day);
        $this->fireChange();
    }

    protected function fireChange(): void
    {
        if (! is_null($this->on_change) && ! is_null($this->year)) {
            ($this->on_change)($this->year, $this->month, $this->day);
        }
    }

    /**
     * Where the calendar drops: flush with the field's left edge, just
     * under it, in the host's coordinate space.
     * @return array{int, int}
     */
    protected function calendarOrigin(): array
    {
        $frame = $this->root->frame();

        return [$frame['x'], $frame['y'] + $frame['height'] + 2];
    }

    /**
     * @return array{int, int, int}
     * @throws WindowableException
     */
    protected function parse(string $date): array
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) !== 1) {
            throw new WindowableException("Date '{$date}' is not Y-m-d.");
        }

        $year = (int) $parts[1];
        $month = (int) $parts[2];
        $day = (int) $parts[3];
        if (! checkdate($month, $day, $year)) {
            throw new WindowableException("Date '{$date}' is not Y-m-d.");
        }

        return [$year, $month, $day];
    }
}
