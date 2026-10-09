# venusian-surface/human-input

Keyboards, mice, game pads, game controllers. One vocabulary. Read state each frame.

```php
use Surface\Contracts\HumanInput\GamepadButton;
use Surface\Contracts\HumanInput\Key;
use Surface\HumanInput\MagicAliases\HumanInput;

public function loop(array $mail): SketchLoopResult
{
    $keys = HumanInput::keyboard();

    if ($keys->isPressed(Key::SPACE)) { $this->jump(); }
    if ($keys->isDown(Key::LEFT)) { $this->x -= 2; }
    $this->name .= $keys->text();

    foreach (HumanInput::gameControllers() as $pad) {
        $this->x += 2 * $pad->leftStick()['x'];
        if ($pad->isPressed(GamepadButton::SOUTH)) { $this->jump(); }
    }

    return SketchLoopResult::CONTINUE;
}
```

## Where input comes from

| Source | Starts | Registered by |
|---|---|---|
| Toolkit engine (keyboard, mouse) | when that toolkit's bridge session connects | the toolkit package: `app('human-input')->extend('<toolkit>', …)` |
| Pad source (every pad, window or not) | first poll, the one `human-input.pads.<os>` names | the pad package: `extendPads('<name>', …)` |
| Circuit | `HumanInput::attach($ic, 'p1')` | the sketch |

One pad source per process lists every pad once; engines list none. Defaults:
`mac` → `gamecontroller`, `linux` → `evdev`; `sdl3` works on both. `null` = circuits only.

Errors land on the reads that need them. Session without an engine: `keyboard()` and
`mouse()` reads throw. Pad source not registered, or its connect failed: `gamePads()`
and `gameControllers()` throw; the next poll retries the connect.

Players are the game's. `$pad->hardwareId()` is the same physical pad across a
reconnect (null where the source has no identity); `$pad->setPlayerIndex(0)` lights
its player LEDs where it has them.

Several toolkit sessions (Linux): one merged keyboard, one merged mouse. Mouse position,
window and buttons follow the engine that reported last; motion and wheel sum.

Circuit faulted (bus gone): released, mailed disconnected, never polled again.
`detach()` + `attach()` recovers.

## Frames

Loop turns outnumber frames. Edges (`isPressed`, `wasReleased`, text, motion, wheel)
stay until a frame reads input, so a tap between two frames reads both edges in the
next. Input unread for two frame lengths (`sketches.refresh_rate`) drops its edges.
First read of a frame applies what the toolkit dispatched since the last poll.

## Mail

`input.gamepad.connected.<id>` (`GamepadConnected`) and
`input.gamepad.disconnected.<id>` (`GamepadDisconnected`), once per change.

## No loop

```php
while (true) {
    HumanInput::poll();
    if (HumanInput::keyboard()->isPressed(Key::ESCAPE)) { break; }
    usleep(16_000);
}
```
