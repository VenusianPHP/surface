<?php

namespace Surface\Contracts\Windows\Primitives;

/**
 * An editable text field with a dropdown of options and a completion list under the field.
 *
 * Typing posts TextChanged; Return with no completion highlighted posts TextSubmitted. Choosing
 * an option or a completion sets the value, hides the list and posts SelectionChanged: the index
 * in options(), -1 for a completion, and the chosen text. The list never takes focus. Up and Down
 * move its highlight, Return or a click chooses; Esc, focus leaving the field, a choice,
 * setValue() and setCompletions([]) hide it, and a hidden list is cleared.
 */
interface TKComboBox extends TKPrimitive
{
    /**
     * @return string
     */
    public function value(): string;

    /**
     * Posts nothing; hides shown completions.
     * @param string $value
     * @return $this
     */
    public function setValue(string $value): static;

    /**
     * @return list<string>
     */
    public function options(): array;

    /**
     * @param list<string> $options the dropdown
     * @return $this
     * @throws \Surface\Contracts\Windows\WindowException When not a list of strings.
     */
    public function setOptions(array $options): static;

    /**
     * @return list<string> what the list shows; [] once hidden
     */
    public function completions(): array;

    /**
     * Show these under the field while it has focus; [] hides the list. Set on a field without
     * focus, the list is dropped.
     *
     * @param list<string> $completions
     * @return $this
     * @throws \Surface\Contracts\Windows\WindowException When not a list of strings.
     */
    public function setCompletions(array $completions): static;
}
