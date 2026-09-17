<?php

namespace Surface\Core;

use Surface\Contracts\Bridge\BridgedOSSession;
use Surface\Contracts\Core\AboutInfo;
use Surface\Contracts\NativeWindows\OSWindowDriver;
use Surface\Contracts\NativeWindows\WindowableException;
use Surface\HumanInput\HumanInputManager;
use Surface\NativeWindows\Menus\MenuItemSpec;
use Surface\Stage\StageManager;
use Voyager\Contracts\IOPools\PoolService;
use Voyager\IOPools\IOEventBag;
use Voyager\NutsAndBolts\Collection;

class LiveApplication
{
    protected Collection $menu_bar_profiles;

    /** What the last tick() drained. */
    protected IOEventBag $mail;

    /**
     * Program identity for the OS About panel, or null for the bare panel.
     * @var AboutInfo|null
     */
    protected ?AboutInfo $about = null;

    public function __construct(
        public readonly PoolService $io_pool,
        public readonly BridgedOSSession $session,
        public readonly OSWindowDriver   $window_service,
        protected readonly ?StageManager $stages = null,
        protected readonly ?HumanInputManager $inputs = null,
    ) {
        $this->menu_bar_profiles = new Collection();
        $this->mail = new IOEventBag();
    }

    /**
     * One turn of the loop. $ms is the idle budget: the OS-level resource
     * spends it blocking in the native wait, waking instantly on input —
     * never a blind sleep.
     */
    public function tick(int $ms = 0): void
    {
        $this->io_pool->os()?->waitBudget($ms);
        $this->io_pool->pump();
        $this->mail = $this->io_pool->drain();
    }

    /**
     * This tick's mail keyed by name — one entry per name, the last wins.
     * Lookup by name; for every piece, including repeats, read mail().
     */
    public function events() : IOEventBag
    {
        return $this->mail->keyBy('name');
    }

    /** This tick's mail, every piece, in arrival order. Two edges of one pin in one tick are two entries. */
    public function mail(): IOEventBag
    {
        return $this->mail;
    }

    /**
     * Input engines first, then stages, then the windows, then the bridge.
     * Stages close and disconnect their host sessions. A failure at any
     * step still tears the rest down; it propagates after.
     */
    public function destroy(): void
    {
        try {
            try {
                $this->inputs?->destroy();
            } finally {
                $this->stages?->destroy();
            }
        } finally {
            $this->window_service->destroyAll();
            if ($this->session->connected()) {
                $this->session->pump(0);
                $this->session->disconnect();
            }
        }
    }

    public function provisionWindow(string $name, int $width, int $height): static|false
    {
        if ($this->window_service->has($name)) {
            return false;
        }

        $windowable = $this->session->provisionNewWindow($name, $width, $height);
        $windowable->setPool($this->io_pool);
        $this->window_service->add($windowable);

        return $this;
    }

    public function showWindow(string $name): void
    {
        $this->window_service->presentWindow($name);
    }

    public function getBridgedSession(): BridgedOSSession
    {
        return $this->session;
    }

    public function getWindowService(): OSWindowDriver
    {
        return $this->window_service;
    }

    /**
     * Hand back a registered profile as its parsed spec tree, or null.
     * @param string $profile
     * @return list<MenuItemSpec>|null
     */
    public function getMenuBarProfile(string $profile): ?array
    {
        return $this->menu_bar_profiles->get($profile);
    }

    /**
     * Register named menu-bar profiles, parsing each once at registration.
     *
     * Engines never see the raw sketch arrays — a window that elects a
     * profile receives the validated MenuItemSpec tree stored here.
     *
     * @param array<string, array> $profiles Profile definitions keyed by name.
     * @return $this
     * @throws WindowableException When a definition is malformed.
     */
    public function addMenuBarProfiles(array $profiles): static
    {
        foreach($profiles as $name => $profile) {
            $this->menu_bar_profiles->put($name, MenuItemSpec::parseList($profile));
        }
        return $this;
    }

    /**
     * Register what the OS About panel shows. Program-level, like the app menu.
     */
    public function setAbout(AboutInfo $about): static
    {
        $this->about = $about;

        return $this;
    }

    public function getAbout(): ?AboutInfo
    {
        return $this->about;
    }

    public function get(): static
    {
        return $this;
    }
}
