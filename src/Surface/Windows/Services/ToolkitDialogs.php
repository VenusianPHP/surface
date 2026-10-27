<?php

namespace Surface\Windows\Services;

use Closure;
use Surface\Contracts\Windows\Mail\Services\AlertAnswered;
use Surface\Contracts\Windows\Mail\Services\FilesChosen;
use Surface\Contracts\Windows\Services\Alert;
use Surface\Contracts\Windows\Services\DialogCapability;
use Surface\Contracts\Windows\Services\Dialogs;
use Surface\Contracts\Windows\Services\OpenRequest;
use Surface\Contracts\Windows\Services\SaveRequest;
use Surface\Contracts\Windows\ToolkitWindow;
use Surface\Contracts\Windows\ToolkitWindowDriver;
use Surface\Contracts\Windows\WindowException;
use Surface\Windows\ChildWindows;
use Throwable;
use Voyager\Contracts\Signals\NamedSignal;

/**
 * The request ids, the parent rules and the capability checks of dialogs, one time for every
 * driver. A request that needs a capability the driver lacks throws before anything opens. A
 * parent holds one modal at a time (one dialog or one child window); its close answers the open
 * dialog first; each request id gets one answer. The driver shows, dismisses and posts.
 */
abstract class ToolkitDialogs implements Dialogs
{
    /** @var array<string, array{parent: ToolkitWindow, kind: 'alert'|'files'}> */
    private array $open = [];

    public function __construct(protected readonly ToolkitWindowDriver $driver) {}

    final public function alert(ToolkitWindow $parent, Alert $alert): string
    {
        if (! is_null($alert->title)) {
            $this->need(DialogCapability::AlertTitle, 'an alert title');
        }
        $id = $this->begin($parent, 'alert');
        $this->show($id, fn () => $this->showAlert($id, $parent, $alert));

        return $id;
    }

    final public function openFiles(ToolkitWindow $parent, OpenRequest $request): string
    {
        if ($request->showHidden) {
            $this->need(DialogCapability::ShowHidden, 'hidden files');
        }
        $id = $this->begin($parent, 'files');
        $this->show($id, fn () => $this->showOpen($id, $parent, $request));

        return $id;
    }

    final public function saveFile(ToolkitWindow $parent, SaveRequest $request): string
    {
        if ($request->showHidden) {
            $this->need(DialogCapability::ShowHidden, 'hidden files');
        }
        $id = $this->begin($parent, 'files');
        $this->show($id, fn () => $this->showSave($id, $parent, $request));

        return $id;
    }

    /** The request id of the open dialog on $parent, or null. */
    public function openOn(ToolkitWindow $parent): ?string
    {
        foreach ($this->open as $id => $request) {
            if ($request['parent'] === $parent) {
                return $id;
            }
        }

        return null;
    }

    /** @throws WindowException When $parent has an open dialog. */
    public function refuseChild(ToolkitWindow $parent): void
    {
        if (! is_null($this->openOn($parent))) {
            throw new WindowException("Window '{$parent->name()}' has an open dialog, so it cannot open a child.");
        }
    }

    /** The parent closes: its open dialog ends with no native answer, and the parent-close answer posts. */
    public function closeOn(ToolkitWindow $parent): void
    {
        $id = $this->openOn($parent);
        if (is_null($id)) {
            return;
        }
        $kind = $this->open[$id]['kind'];
        unset($this->open[$id]);
        $this->dismiss($id);
        $this->post($kind === 'alert' ? new AlertAnswered($id, -1, false) : new FilesChosen($id, []));
    }

    /** A directory for the native: realpath() of a folder, or null for anything else. */
    public static function folder(?string $directory): ?string
    {
        if (is_null($directory) || ! is_dir($directory)) {
            return null;
        }

        return realpath($directory) ?: null;
    }

    /** The native answered. An id that is not open (answered, or ended by its parent's close) posts nothing. */
    protected function answerAlert(string $id, int $button, bool $suppressed = false): void
    {
        if (! isset($this->open[$id])) {
            return;
        }
        unset($this->open[$id]);
        $this->post(new AlertAnswered($id, $button, $suppressed));
    }

    /**
     * @param string $id
     * @param list<string> $paths
     */
    protected function answerFiles(string $id, array $paths): void
    {
        if (! isset($this->open[$id])) {
            return;
        }
        unset($this->open[$id]);
        // Qt on macOS ends a chosen folder with a slash; AppKit and GTK do not.
        $this->post(new FilesChosen($id, array_map(static fn (string $path): string => $path === '/' ? $path : rtrim($path, '/'), $paths)));
    }

    abstract protected function showAlert(string $id, ToolkitWindow $parent, Alert $alert): void;

    abstract protected function showOpen(string $id, ToolkitWindow $parent, OpenRequest $request): void;

    abstract protected function showSave(string $id, ToolkitWindow $parent, SaveRequest $request): void;

    /** End the native dialog of $id with no answer, and let go of it. */
    abstract protected function dismiss(string $id): void;

    abstract protected function post(NamedSignal $mail): void;

    /** @throws WindowException When the driver lacks $capability. */
    private function need(DialogCapability $capability, string $what): void
    {
        if (! $this->supports($capability)) {
            throw new WindowException("This driver's dialogs cannot show {$what} (no {$capability->name} capability).");
        }
    }

    /** A show that throws leaves no request open: the parent takes the next dialog. */
    private function show(string $id, Closure $show): void
    {
        try {
            $show();
        } catch (Throwable $thrown) {
            unset($this->open[$id]);
            throw $thrown;
        }
    }

    /** @param 'alert'|'files' $kind */
    private function begin(ToolkitWindow $parent, string $kind): string
    {
        $name = $parent->name();
        if (! $parent->isOpen()) {
            throw new WindowException("Window '{$name}' is closed, so it cannot show a dialog.");
        }
        if ($this->driver->get($name) !== $parent) {
            throw new WindowException("Window '{$name}' is not a window of this driver, so it cannot show a dialog.");
        }
        $child = ChildWindows::childOf($this->driver, $parent);
        if (! is_null($child)) {
            throw new WindowException("Window '{$name}' has the open child '{$child->name()}', so it cannot show a dialog.");
        }
        if (! is_null($this->openOn($parent))) {
            throw new WindowException("Window '{$name}' already has an open dialog.");
        }
        $id = bin2hex(random_bytes(8));
        $this->open[$id] = ['parent' => $parent, 'kind' => $kind];

        return $id;
    }
}
