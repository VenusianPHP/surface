# Log

## 2026-10-02

* Surface 0.10 slice 3 (toolkit primitives), Surface layer: added [toolkit primitives](architecture/primitives.md), [view mail](api/view-mail.md), [styling](api/styling.md); [components](architecture/components.md) gains the `venusian-surface/nuts-and-bolts` split; [windows](architecture/windows.md) gains the content container and lookups; [window mail](api/mail.md) gains `WindowResized`; [bridge](architecture/bridge.md) gains `postLatest`/`flushLatest`; [testing](runbooks/testing.md) names the primitive fakes. Review fixes the same day: `Placement`/`PrimitiveRegistry` in contracts, reorder on the child, `TKFixed::frameOf()`, `forgetLatest()`, guarded forgets, finite-float and trailing-newline guards.

## 2026-10-01

* Bundle created with Surface 0.10 slices 1-2 (bridge, toolkit windows): [components](architecture/components.md), [bridge](architecture/bridge.md), [windows](architecture/windows.md), [mail](api/mail.md), [menu profiles](api/menu-profiles.md), [config](api/config.md), [composing an app](runbooks/composing-an-app.md), [testing](runbooks/testing.md).
