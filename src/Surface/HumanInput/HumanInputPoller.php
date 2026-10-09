<?php

namespace Surface\HumanInput;

use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\LoopResources\Background;
use Voyager\IOPools\Resources\Pollable;

/**
 * HumanInput on the loop: one poll per turn. Background, so input never keeps a loop
 * running. Registration order against the toolkit sessions does not matter: a frame's
 * first read catches up with whatever the sessions pumped since the last poll.
 */
final class HumanInputPoller extends Pollable implements Background
{
    public const string NAME = 'input';

    public function __construct(private readonly HumanInputManager $input) {}

    public function tick(): void
    {
        $this->input->poll();
    }

    /**
     * Put $input on $loop, and destroy it when the loop stops. Joined when the loop is
     * first resolved, the stop hook runs before any sketch's own shutdown, which closes
     * windows and disconnects the bridge after input is gone.
     *
     * @param Loop $loop
     * @param HumanInputManager $input
     * @return void
     */
    public static function join(Loop $loop, HumanInputManager $input): void
    {
        $loop->resource(self::NAME, new self($input));
        $loop->onStop(fn () => $input->destroy());
    }
}
