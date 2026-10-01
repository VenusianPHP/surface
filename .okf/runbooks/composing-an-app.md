---
type: Runbook
title: Composing an app
description: Require Surface + a toolkit driver, publish configs, sketch mail, boot/shutdown pattern.
tags: [surface, sketches, composer]
status: draft
generated: { by: claude-opus/5.5, at: 2026-10-01T20:04:54Z }
---

# Overview

1. App `composer.json`: require `venusian/surface` and the drivers for the target OS (`jovian/venusian-appkit` is macOS-only: it needs ext-appkit). The ext for each driver loaded in the PHP that runs the app; ext-kqueue (macOS) or ext-epoll (Linux) for the loop waiter.
2. `php computer vendor:publish --tag=surface-config`.
3. `.env`: `IO_POOLS_MAIL_HANDLER=sketch` so mail reaches `loop($mail)`; `IO_POOLS_WAITER_BACKEND=auto`.
4. Sketch:

```php
public function boot(): void
{
    $this->session = app('toolkit-bridge')->driver()->connect();
    $this->session->joinLoop(app('event-loop'));
    app('toolkit-windows')->open('main', 640, 400, 'main')->present();
}

public function loop(array $mail = []): SketchLoopResult
{
    foreach ($mail as $item) {
        if ($item instanceof QuitRequested) {
            return SketchLoopResult::STOP;
        }
    }

    return SketchLoopResult::CONTINUE;
}

public function shutdown(): void
{
    app('toolkit-windows')->closeAll();
    $this->session->leaveLoop();
    $this->session->disconnect();
}
```

Run: `php rocket <sketch-name>`. Toolkit per run via the published `bridge.toolkit.<os>.default`.
