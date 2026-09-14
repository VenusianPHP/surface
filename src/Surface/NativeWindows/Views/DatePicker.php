<?php

namespace Surface\NativeWindows\Views;

use Closure;
use Surface\Contracts\Core\Events\SurfaceEventType;
use Surface\Contracts\NativeWindows\Views\OSDatePicker;
use Surface\Contracts\NativeWindows\WindowableException;
use Surface\NativeWindows\Windowable;

/**
 * A graphical day picker. Engines wire their native day change into
 * fireChanged(); every pick rides the dock as DATE_CHANGED
 * (`<window>.<name>.changed`). Value is `Y-m-d`; twins speak ints.
 */
abstract class DatePicker extends View implements OSDatePicker
{
    use HasEnabledState;

    protected ?Closure $on_change = null;

    protected ?int $year = null;

    protected ?int $month = null;

    protected ?int $day = null;

    public function __construct(
        string $name,
        Windowable $window,
        ?string $date,
    ) {
        parent::__construct($name, $window);
        $this->installDate($date);
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

    public function setDate(?string $date): static
    {
        if (is_null($date)) {
            $this->year = null;
            $this->month = null;
            $this->day = null;

            return $this;
        }

        [$year, $month, $day] = $this->parseDate($date);
        if ($this->year !== $year || $this->month !== $month || $this->day !== $day) {
            $this->year = $year;
            $this->month = $month;
            $this->day = $day;
            $this->applyDate($year, $month, $day);
        }

        return $this;
    }

    public function onChange(callable $hook): static
    {
        $this->on_change = $hook(...);

        return $this;
    }

    /**
     * Engine callbacks land here with the day read from the native
     * control. Pushes DATE_CHANGED, then invokes the hook with
     * (year, month, day); safe with no hook and no sink.
     * @return void
     */
    protected function fireChanged(int $year, int $month, int $day): void
    {
        $this->year = $year;
        $this->month = $month;
        $this->day = $day;
        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $this->window->emitViewEvent(SurfaceEventType::DATE_CHANGED, $this->name, [
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'date' => $date,
        ]);

        if (! is_null($this->on_change)) {
            ($this->on_change)($year, $month, $day);
        }
    }

    /**
     * Write the day to the native control. Month is 1-based.
     * @return void
     */
    abstract protected function applyDate(int $year, int $month, int $day): void;

    /**
     * @return array{int, int, int}
     */
    protected function parseDate(string $date): array
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

    protected function installDate(?string $date): void
    {
        if (is_null($date)) {
            return;
        }

        [$this->year, $this->month, $this->day] = $this->parseDate($date);
    }
}
