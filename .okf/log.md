# Surface Update Log

## 2026-09-04 (components, wave 3 panels)
* **Update**: [Components](/components.md) — six panel `Component`
  subclasses ship: **Tabs**, **Drawer**, **Toast**, **ListBox**,
  **Skeleton**, **DataView**. Visibility is how Tabs/Drawer/Toast swap
  (`show`/`hide`); ListBox and DataView are scroll stacks; Skeleton is a
  painted placeholder (`SkeletonShape` documents CIRCLE without an oval
  clip). `DrawerSide` is recorded, not docked. Datepicker and DataTable
  stay empty stubs. Suite at 331.

## 2026-09-04 (components, wave 2 compositions)
* **Update**: [Components](/components.md) — five more `Component`
  subclasses ship: **Badge**, **Chip**, **InputNumber**, **SelectButton**,
  **Breadcrumb**. Badge reuses `MessageSeverity` paint (muted defaults
  when none); Chip is Message minus severity with `onRemove`; InputNumber
  is a text field plus stacked steppers (null min/max unbounded,
  `setValue` silent, non-numeric typeText ignored); SelectButton is
  Toolbar flow plus Sidebar sticky selection; Breadcrumb is a button
  trail whose last crumb is disabled. Datepicker and DataTable stay
  empty stubs. Suite at 296.

## 2026-09-04 (components, wave 1 wrappers)
* **Update**: [Components](/components.md) — seven thin primitive
  wrappers ship as `Component` subclasses: **InputText**, **TextArea**,
  **Slider**, **ToggleSwitch**, **ProgressBar**, **ProgressSpinner**,
  **Select**. Each mounts one inner view that fills the root; the field
  API delegates like IconField; programmatic setters stay silent and the
  fake engine doors (`typeText` / `edit` / `drag` / `flip` / `pick`) fire
  the hooks. Datepicker and DataTable stay empty stubs. Suite at 262.

## 2026-09-04 (composition recipes)
* **Update**: [Components](/components.md) — a recipe per remaining stub;
  [Conjured views](/views.md) and [asymmetries](/engine-asymmetries.md)
  record the Calendar/Table verdict: those natives stay unbound twins,
  Datepicker and DataTable compose from the shipped primitives.

## 2026-09-04 (visibility)
* **Update**: [Conjured views](/views.md) — `setVisible/isVisible/show/hide`
  on every view (change-only `applyVisible`; NSView setHidden / GtkWidget
  setVisible — one write hides a container's subtree). Component gains the
  same sugar via its root. Suite at 228.
* **Update**: [Components](/components.md) — view names stay window-global
  for content conjured into a component's body(); the component prefixes
  only its own parts.

## 2026-09-04 (GTK live smoke + text read-back)
* **Verified**: the primitive wave runs live on the Pi (gtk4 4.18.6) —
  all eleven kinds, every signal seam driven from the native side, group
  hosting/cascade/subtree removal, scroll extent. ALL CHECKS PASSED.
* **Update**: [Conjured views](/views.md) — the GTK text-read-back gap is
  CLOSED: the gtk ext unreserved `gtk_text_buffer_get_text` (iters as
  character offsets), `GTKTextArea` reads its buffer per edit, and the
  null-value lane is removed — `TextChanged::$value` is `string`,
  `TextArea::fireChanged(string)`, contracts tightened. Suite at 224.

## 2026-09-04 (components, first five)
* **Creation**: [Components](/components.md) — the opinionated tier lands:
  abstract `Component` (root Group mount, `<component>.<part>` naming,
  component-relative `move()`, `place()` → `layout()` responsive hook,
  terminal subtree removal) and five concrete shapes — **Sidebar**
  (ScrollView + ToggleButton rows, native pressed = selected, sticky,
  glyph collapse under a breakpoint), **Card**, **Toolbar**, **Message**
  (+ `MessageSeverity`), **IconField**. Pure PHP over the primitives —
  zero engine code, works on both twins by construction. Suite at 225.

## 2026-09-03 (primitive wave + containers)
* **Update**: [Conjured views](/views.md) — eleven new kinds: textInput
  (secret variant), textArea, slider, toggle, toggleButton, checkbox,
  progressBar, dropdown, separator, and the **group** / **scrollView**
  containers. Children conjure INTO a container (`in:` or its sugar),
  natives parent under its surface, rules and the AppKit inversion resolve
  against `View::layoutSpace()` (host `innerSize()` or window content);
  relayout cascades, removal kills the subtree and frees names. New mail:
  TextChanged / TextSubmitted / ValueChanged / Toggled / SelectionChanged,
  named `<window>.<view>.<verb>`; hooks run in-pump like Button. Twins in
  both engines (zero ext work — all natives already bound). Known gap:
  gtk ext reserves `gtk_text_buffer_get_text`, so GTK textArea mails a
  null value and value() answers the last write. Suite at 192. Twins are
  class-load verified; live smoke on both engines still owed.

## 2026-08-31 (shared pool)
* **Update**: [Non-blocking calls on the tick](/async.md) — `ProgramShuttle::httpPool()`
  goes public and the provider binds it under `Voyager\IOPools\HttpPool::class`, so a
  package that only knows voyager/io-pools (venusian-stargazer's `NASA::apod()->date()
  ->async()`) dispatches through the same pool and event queue the sketch's callHttp()
  rides. Suite at 156. Proven live both machines: the HelloAsync meta hop now travels
  as `stargazer.apod.date`.

## 2026-08-31 (centerX)
* **Update**: [Conjured views](/views.md) — `PlacementRule::CENTER_X` +
  `View::centerX($dx)`: horizontal re-centre with the y anchored, so a top-anchored
  card follows a resize the way center() always did. Pure rule arithmetic, no engine
  hooks. Proven through the real AppKit engine (600->900 grow re-resolved every card
  view). Suite at 155.

## 2026-08-31 (video)
* **Update**: [Conjured views](/views.md) — `Video` abstract + `OSVideo` contract,
  `Windowable::video()`. File-path source only (GTK has no GFile binding for remote
  URLs, and callHttp bytes go through a temp file anyway); `play()/pause()/isPlaying`,
  `setMuted`; conjured paused; both engines ship native transport controls. AppKit:
  `AVPlayerView` + per-path `AVPlayer` (a plain NSView — pays its own inversion, held
  boxes). GTK: `GtkVideo` + per-path `GtkMediaFile` (playback commands ride the
  inherited GtkMediaStream surface). Proven live both engines with a real NASA mp4.
  Suite at 153.
* **Update**: [Non-blocking calls on the tick](/async.md) — the progress lane:
  `progress.<name>` events (family 'task.progress') + `PendingCall::onProgress`, spoken
  only when bytes move; pool total-time cap replaced by stall detection.

## 2026-08-31 (spinner, image, wrap)
* **Update**: [Conjured views](/views.md) — `Spinner` (indeterminate only, applySpinning)
  + `Image` (file-path loading, proportional fit baked in) abstracts with `OSSpinner`/
  `OSImage` contracts and `Windowable::spinner()/image()`; `SizeRule::WRAP` +
  `Label::wrap($width)` (applyWrap + measureWrappedHeight hooks; text/font changes
  re-measure — the NATURAL relayout guard is now `!== FIXED`). Built for the NASA APOD
  sketch. Proven end-to-end on both engines by throwaway boot scripts (Mac wrap 150x80,
  Pi 150x131) plus fakes. Suite at 146.

## 2026-08-31 (io-pools)
* **Migration**: async machinery moved down to voyager/io-pools. Surface deletes its
  Async namespace, EventQueue and EventSink; `SurfaceEvent extends Voyager\IOPools\Event`
  (TASK dropped from SurfaceEventType — tasks are the framework's 'task' family);
  shuttle pumps a TickRoster. Second hard dependency added. Suite still 129.

## 2026-08-30 (async)
* **Creation**: [Non-blocking calls on the tick](/async.md) — `Tickable` +
  `ProgramShuttle::register()/sink()/callHttp()`, `HttpPool` over a `MultiCurlDriver`
  (driver seam reserved for an ext-parallel compute driver, not HTTP), `PendingCall`
  hooks + raw-named TASK events, both lanes always. Proven against real network:
  parallel completions, transport-fail lane, 404-is-success. Suite at 129.

## 2026-08-30 (style)
* **Update**: [Conjured views](/views.md) — styling. `Color`/`FontSpec`/`FontWeight`
  contracts, `StylesText` trait (typed setters + `textCSS` sugar), `setBackground` on
  every view. AppKit: direct setters on labels, attributed titles on buttons, layer
  backgrounds. GTK: per-window `CssEngine` over the newly bound `GtkCssProvider`.
  Suite at 117.

## 2026-08-30 (button)
* **Update**: [Conjured views](/views.md) — `Button` abstract + `OSButton` contract,
  `Windowable::button()`. Click hook stored on the abstract, engines wire native click
  to `fireClick()`; hook runs inside the pump by design (sketch-owned closure, not a
  seam-crossing definition). Engine frame mechanics extracted into traits. Suite at 107.

## 2026-08-30 (about)
* **Update**: [Menu-bar profiles](/menu-profiles.md) — `AboutInfo` + `ProgramShuttle::setAbout()`;
  ABOUT role is PHP-backed on both engines through `Windowable::showAbout()` →
  `presentAbout()`. Corrects the earlier claim that GTK had no About: `GtkAboutDialog`
  was bound all along. Suite at 99.

## 2026-08-30 (resize)
* **Update**: [Conjured views](/views.md) — placement is a rule (`PlacementRule`,
  `SizeRule`) re-resolved by `View::relayout()`. `Windowable::syncLayout()` detects a
  content change, re-resolves every view, pushes `window.resized.<window>`;
  `ProgramShuttle::tick()` calls it per window after the pump. Uniform poll on both
  engines; first real size counts as a resize so GTK lays out from 0x0 without the
  show-then-conjure ordering. `OSWindowDriver::all()` added. Sketch untouched.

## 2026-08-30 (label)
* **Creation**: [Conjured views](/views.md) — `View`/`Label` abstracts, `OSView`/`OSLabel`
  contracts, `TextAlignment`. `Windowable::label()` conjures + registers; `view()` reads
  back; `place`/`hug`/`center`/`remove`. Top-left pixels promised; AppKit inverts in
  `applyFrame`. GTK content reads 0x0 pre-layout so the sketch shows+ticks before
  conjuring. Resize-following deliberately out. Suite at 87.

## 2026-08-30 (window closed)
* **Update**: [Menu-bar profiles](/menu-profiles.md) — `WINDOW_CLOSED` events. AppKit
  hears `windowShouldClose:` via a held `NSWindowDelegate` (allow; close hides);
  GTK hears `close-request` (allow; destroy), flips a `closed` guard so no later call
  touches the recycled handle, and its QUIT role now emits before destroying. Events
  named `window.closed.<window>`; sketches exit cleanly on chrome close. Suite at 78.

## 2026-08-30 (events)
* **Update**: [Menu-bar profiles](/menu-profiles.md) — `action` closures replaced by
  `event` names. Engines only push: activation lands in `Windowable::emitMenuEvent()`
  → `EventQueue` on the shuttle; the sketch drains `$program->events()` (Collection
  keyed by name, `has()`/`get()`, empty after). Event vocabulary in
  `Surface\Contracts\NativeWindows\Events`, queue in native-windows. `MenuEvent`
  removed. Roles stay platform-native by decision: macOS QUIT terminates the process,
  GTK QUIT destroys the window. Suite at 76.

## 2026-08-30 (menus)
* **Creation**: [Menu-bar profiles](/menu-profiles.md) — engine-neutral definitions
  (`MenuItemSpec`, `MenuRole`, `MenuEvent`), registered on the shuttle, elected per
  window, translated by each engine. Session-level `setMenuBar(array)` removed from the
  bridge contract; the AppKit-dialect `selector`/`char_code` vocabulary no longer
  crosses the seam. 17 new tests; suite at 68.

## 2026-08-30
* **Update**: [OS bridge lifecycle](/bridge-lifecycle.md) back to `status: draft`. The
  contract grew a fifth verb, `provisionNewWindow()`. It is a factory rather than
  lifecycle, so it lives in the engine packages, not the abstract.
* **Creation**: [Window provisioning](/window-provisioning.md) — the slice above the
  bridge. Session mints, driver holds, `ProgramShuttle` pairs the two. Open: driver
  selection, last-window-closed, placement.
* **Creation**: [Test suite](/testing.md) — scope, the shared fakes, and the
  directories `phpunit.xml` excludes from the default run.
* **Update**: [Where AppKit and GTK disagree](/engine-asymmetries.md) — recorded the
  style mask, placement and size the window slice ships with.
* **Fix**: `GTKWindowDriver::add()` now keys the registry off the contract's `name()`
  instead of `Windowable`'s public `$name` field.

## 2026-08-29
* **Initialization**: Seeded the bundle after the 0.8 view tree was torn out. The
  previous `.okf` described a view tree that no longer exists and was removed with it.
* **Creation**: [OS bridge lifecycle](/bridge-lifecycle.md) — the connect / disconnect /
  pump contract, the `$initialized` and `$connected` split, and the invariants
  `tests/Bridge/SessionTest.php` proves.
* **Creation**: [A container alias is the only seam to an engine](/engine-seam.md) —
  records the rejection of `class_exists` probing and why the raw PSR exception on a
  missing engine package is accepted.
* **Creation**: [Where AppKit and GTK disagree](/engine-asymmetries.md) — the engine
  differences measured against jovian 0.8.0, split into what the bridge slice settled
  and what the window slice still has to decide.
