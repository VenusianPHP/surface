---
type: Reference
title: Test suite
description: >-
  What the suite covers, the fakes it covers it with, and which directories
  are held out of the default run.
tags: [surface, testing]
status: draft
generated: { by: claude-opus-5/claude-code, at: "2026-08-30T02:30:00Z" }
sources:
  - id: phpunit
    resource: phpunit.xml
    title: Suite definition
  - id: fakes
    resource: tests/Support/Fakes
    title: Shared window and driver fakes
---

# Scope

The suite runs with no application container, no extension loaded, and no
engine package installed. Everything it asserts is PHP-side policy: the
session state machine, the drain order, name-keyed lookup, the presentation
guard, the driver type guards.

Engine behaviour is proven on hardware instead — macOS for AppKit, the Pi
over `fnk` for GTK.

# Fakes

Shared fakes live in `tests/Support/Fakes`.[^fakes]

| Fake | Stands in for |
|---|---|
| `FakeSession` | the abstract session; counts every engine hook and window request |
| `FakeWindow` | a `Windowable` delegate; counts presentations and destructions |
| `FakeMacWindow` / `FakeLinuxWindow` | the same, carrying one OS marker interface |
| `ContractOnlyLinuxWindow` | a `LinuxOSWindow` built from the contract alone, with no public `$name` |
| `FakeWindowDriver` | a driver with no marker check, isolating the shared registry |

# Excluded directories

`tests/Views` and three files under `tests/NativeWindows` reference the
`Surface\NativeWindows\Views\*` tree torn out before 0.8. `phpunit.xml`
holds them out of the default run with `<exclude>` entries; they are still
on disk and still runnable with an explicit path argument.[^phpunit]

The ~40 fakes under `tests/Views/Fakes` describe the view API the rebuild is
working toward. The exclude list goes away when the view tree lands.

# Running

```bash
vendor/bin/pest                   # default suite
vendor/bin/pest tests/Views       # orphans, explicitly
php -l <file>                     # syntax gate for touched files
```

[^phpunit]: Suite definition
[^fakes]: Shared window and driver fakes
