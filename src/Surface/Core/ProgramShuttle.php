<?php

namespace Surface\Core;

use Surface\Contracts\Bridge\BridgedOSSession;
use Surface\Contracts\Core\AboutInfo;
use Voyager\Contracts\IOPools\EventSink;
use Voyager\Contracts\IOPools\HttpDriver;
use Voyager\Contracts\IOPools\Tickable;
use Voyager\IOPools\EventQueue;
use Voyager\IOPools\HttpPool;
use Voyager\IOPools\MultiCurlDriver;
use Voyager\IOPools\PendingCall;
use Voyager\IOPools\TickRoster;
use Surface\Contracts\NativeWindows\OSWindowDriver;
use Surface\Contracts\NativeWindows\WindowableException;
use Surface\NativeWindows\Menus\MenuItemSpec;
use Voyager\NutsAndBolts\Collection;


class ProgramShuttle
{
    protected Collection $menu_bar_profiles;

    /**
     * The one queue every window's engine reports into.
     * @var EventQueue
     */
    protected EventQueue $event_queue;

    /**
     * Program identity for the OS About panel, or null for the bare panel.
     * @var AboutInfo|null
     */
    protected ?AboutInfo $about = null;

    /**
     * Everything pumped once per tick, after the engine and layout.
     * @var TickRoster
     */
    protected TickRoster $roster;

    /**
     * Lazy non-blocking HTTP, registered as a tickable on first call.
     * @var HttpPool|null
     */
    protected ?HttpPool $http_pool = null;

    public function __construct(
        public readonly BridgedOSSession $session,
        public readonly OSWindowDriver   $window_service,
    )
    {
        $this->menu_bar_profiles = new Collection();
        $this->event_queue = new EventQueue();
        $this->roster = new TickRoster();
    }

    public function destroy(): void
    {
        $this->window_service->destroyAll();
        if ($this->session->connected()) {
            $this->session->pump(0);
            $this->session->disconnect();
        }
    }

    /**
     * Pump the engine, then let every window detect a resize and re-resolve
     * its views. Layout follows the OS without the sketch relaying anything.
     */
    public function tick(int $ms = 0): void
    {
        $this->session->pump($ms);

        foreach ($this->window_service->all() as $window) {
            $window->syncLayout();
        }

        $this->roster->tick();
    }

    /**
     * Ride the loop: the shuttle pumps every registered tickable each tick.
     */
    public function register(Tickable $tickable): static
    {
        $this->roster->register($tickable);

        return $this;
    }

    /**
     * The queue engines and tickables report into, for anything that wants
     * to deliver through events() — an async pool, a future custom source.
     */
    public function sink(): EventSink
    {
        return $this->event_queue;
    }

    /**
     * Start a non-blocking HTTP call. The result arrives two ways at once:
     * a TASK event named $name in events(), and the returned PendingCall's
     * onSuccess/onFail hooks — the button convention. One in-flight call
     * per name; the name frees when it settles.
     *
     * @param array<string, string> $headers
     */
    public function callHttp(string $name, string $method, string $url, array $headers = [], ?string $body = null): PendingCall
    {
        return $this->httpPool()->call($name, $method, $url, $headers, $body);
    }

    /**
     * The program's one HTTP pool, minted on first need and put on the
     * roster. Public — and container-bound by the provider — so an API
     * client package can ride the same pool the sketch's callHttp() does.
     */
    public function httpPool(): HttpPool
    {
        if (is_null($this->http_pool)) {
            $this->http_pool = new HttpPool($this->makeHttpDriver(), $this->event_queue);
            $this->register($this->http_pool);
        }

        return $this->http_pool;
    }

    /**
     * The transport behind callHttp(). multi-curl is the truth for I/O-bound
     * work; overridable for tests and for a compute driver later.
     */
    protected function makeHttpDriver(): HttpDriver
    {
        return new MultiCurlDriver();
    }

    public function provisionWindow(string $name, int $width, int $height): static|false
    {
        if ($this->window_service->has($name)) {
            return false;
        }
        $windowable = $this->session->provisionNewWindow($name, $width, $height);
        $windowable->setEventSink($this->event_queue);
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

    /**
     * Drain everything the engines observed since the last call.
     *
     * Keyed by event name, so a sketch loop reads has('do-thing') and
     * get('do-thing') for the SurfaceEvent's props. The queue is empty after.
     *
     * @return Collection
     */
    public function events(): Collection
    {
        return $this->event_queue->drain();
    }

    public function get(): static
    {
        return $this;
    }
}
