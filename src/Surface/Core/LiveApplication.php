<?php

namespace Surface\Core;

use Surface\Contracts\Bridge\BridgedOSSession;
use Surface\Contracts\Core\AboutInfo;
use Surface\Contracts\NativeWindows\OSWindowDriver;
use Surface\Contracts\NativeWindows\WindowableException;
use Surface\NativeWindows\Menus\MenuItemSpec;
use Voyager\Contracts\IOPools\PoolService;
use Voyager\IOPools\IOEventBag;
use Voyager\NutsAndBolts\Collection;

class LiveApplication
{
    protected Collection $menu_bar_profiles;

    /**
     * Program identity for the OS About panel, or null for the bare panel.
     * @var AboutInfo|null
     */
    protected ?AboutInfo $about = null;

    public function __construct(
        public readonly PoolService $io_pool,
        public readonly BridgedOSSession $session,
        public readonly OSWindowDriver   $window_service,
    ) {
        $this->menu_bar_profiles = new Collection();
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
    }

    public function events() : IOEventBag
    {
        return $this->io_pool->drain()->keyBy('name');
    }

    public function destroy(): void
    {
        $this->window_service->destroyAll();
        if ($this->session->connected()) {
            $this->session->pump(0);
            $this->session->disconnect();
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
