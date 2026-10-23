<?php

namespace Surface\Contracts\Windows\Services;

use InvalidArgumentException;

final readonly class Alert
{
    /** The index answered by Esc and by the alert's own close. */
    public int $cancel;

    /**
     * @param string $message
     * @param string|null $detail
     * @param list<string> $buttons 1 to 3, in reading order
     * @param int $default the index answered by Enter
     * @param int|null $cancel null: the last button
     * @throws InvalidArgumentException When $buttons is not a list of 1 to 3 strings, or an index is outside it.
     */
    public function __construct(
        public string $message,
        public ?string $detail = null,
        public array $buttons = ['OK'],
        public int $default = 0,
        ?int $cancel = null,
    ) {
        if (! array_is_list($buttons)) {
            throw new InvalidArgumentException('Alert buttons must be a list.');
        }
        $count = count($buttons);
        if ($count < 1 || $count > 3) {
            throw new InvalidArgumentException("An alert takes 1 to 3 buttons, not {$count} buttons.");
        }
        foreach ($buttons as $index => $label) {
            if (! is_string($label)) {
                throw new InvalidArgumentException("Alert button at index {$index} is not a string.");
            }
        }
        $this->cancel = $cancel ?? $count - 1;
        foreach (['default' => $default, 'cancel' => $this->cancel] as $role => $index) {
            if ($index < 0 || $index >= $count) {
                throw new InvalidArgumentException("Alert {$role} {$index} is outside its {$count} buttons.");
            }
        }
    }
}
