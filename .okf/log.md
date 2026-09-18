# Surface Update Log

## 2026-09-18 (bitmap fonts Task 5 — Drawing2D::text)
* **Update**: [drawing](/drawing.md) — `text()` / `textBounds()` on
  Painter (atlas quads, `releaseAtlases`) and Rasterizer (one span
  per glyph run). `surface/drawing` still does not import
  `Surface\Fonts\`.
* **Update**: [components-to-come](/components-to-come.md) — drawer
  verbs landed; letterhead is next.

## 2026-09-18 (bitmap fonts Task 4 — Typesetter)
* **Update**: [drawing](/drawing.md) — `Surface\Drawing\Text`: Typesetter,
  PlacedGlyph, GlyphAtlas. Layout is engine-free; `Drawing2D::text` is
  still next. `surface/drawing` does not import `Surface\Fonts\`.
* **Update**: [testing](/testing.md) — `TinyFace` / `TinyAAFace` fakes.
* **Update**: [components-to-come](/components-to-come.md) — layout
  landed; drawer verbs still to come.

## 2026-09-17 (CPU stages)
* **Update**: [stage](/stage.md) — `# CPU stages`: two kinds over
  `AbstractStage`, `openCPU` / `emulate`, fit + nearest, fixed canvas,
  refusing default (appkit says no today), `cpu_renderer`. Rules gain
  the present points (after a ran frame, on show, on resize). sdl3
  host row names the CPU mint.
* **Update**: [cpu-drawing](/cpu-drawing.md) — one canvas can drive a
  stage and a panel at once; `Drawing2D::releaseTexture()` exists.
* **Update**: [index](/index.md) — opening names CPU stages;
  suite at 659.

## 2026-09-18 (CPU stages Task 4 — StageManager door)
* **Update**: [stage](/stage.md) — `StageManager::openCPU` and
  `emulate` mint a CPU stage on the same host session and resource
  driver as GPU stages. `emulate` is whole-pixel zoom with
  `INTEGER_SCALE`. `config('stage.cpu_fit')` / `cpu_renderer`.
* **Update**: [testing](/testing.md) — `stageManager()` binds the
  shipped CPU stack; `StageManagerCPUTest` covers the door. Suite
  at 658.

## 2026-09-18 (CPU stages Task 3 — CPUStagedWindow)
* **Update**: [stage](/stage.md) — the CPU class presents a fixed
  canvas through `applyPresent(string $rgba8)`. Window size and
  canvas size stay apart; `show()` and resize re-present without
  running the hook. `StageSession::openCPU` / `mintCPUStage` stay
  the refusing default (Task 1); `FakeStageSession` now mints
  `FakeCPUStagedWindow`.
* **Update**: [testing](/testing.md) — `FakeCPUStagedWindow` records
  presents and close order; `CPUStagedWindowTest` is the 128×64-in-
  512×256 twin of the GPU stage tests.

## 2026-09-18 (CPU stages Task 2 — AbstractStage)
* **Update**: [stage](/stage.md) — `AbstractStage` holds the shared
  window policy (size/scale, change-only resize mail, one close
  announcement, release-before-destroy, `visible()`). GPU
  `StagedWindow` is that plus `RunsFrames`; `applyResize` resizes the
  executor in pixels. Host packages still fill the three native hooks.

## 2026-09-18 (CPU stages Task 1 — contracts)
* **Update**: [stage](/stage.md) — `StagedWindow` contract is engine-free.
  `GPUStagedWindow` / `CPUStagedWindow` add the engine. `StageFit`
  names four scalers. `StageSession::open()` returns the GPU kind;
  `openCPU()` refuses by default (`cpuUnsupported`).
* **Update**: [drawing](/drawing.md) — `GPUDrawTarget` is
  `OSGPUView` + `GPUStagedWindow`. `Drawing2D::releaseTexture()`:
  Painter flushes then the executor; Rasterizer unsets and
  `image()` throws.
* **Update**: [index](/index.md) — suite at 641.

## 2026-09-17 (CPU drawing + framebuffers concepts)
* **Creation**: [cpu-drawing](/cpu-drawing.md) — five in-house engines
  over host-format canvases. Drawing2D-not-Executor; batches never
  per-pixel; two affine paths; colour-key; clear policy per engine;
  damage epoch; true U8G2 + `onPage`; paged `flush()` host-only;
  `SchedulesFrames` split. Goal snippet is the door.
* **Creation**: [framebuffers](/framebuffers.md) — store is the host
  format. `php` / `native` drivers, packings table, PixelMapper rules,
  pixel-word conventions, no-bleedover, fixtures are the contract,
  refusals.
* **Update**: [drawing](/drawing.md) — Decisions pointer to
  cpu-drawing for the CPU/GPU split; `Affine` / `Geometry` stay
  shared. Engine table not duplicated here.
* **Update**: [testing](/testing.md) — fixtures directory,
  `RecordingFramebuffer`, `FakeCPUEngineDriver`, `FixtureRunner`;
  engine / manager / provider tests.
* **Update**: [index](/index.md) — opening names CPU rendering and
  framebuffers; both concepts linked; split packages gain
  `surface/framebuffers`; suite at 635.

## 2026-09-17 (CPU canvases — epaper / paged / nframes)
* **Update**: [drawing](/drawing.md) — `EPaperCanvas` defaults to paper
  before `bootSchedule()`. `PagedCanvas` is true U8G2 (one page of RAM,
  same hook per page, `onPage` sink); `flush()`/`rgba8()` re-run and
  concatenate; a foreign spec is `pagedHostOnly()`. `NFramesCanvas`
  clears the back, flips on present, reads the front.
* **Update**: [testing](/testing.md) — `CPUCanvasKindsTest` hex stays
  exact (`ff00`/`ff80`, page sink bytes, nframes `0000`/`00ff`/`ffff`).
* **Update**: [index](/index.md) — suite count 629.

## 2026-09-17 (CPU canvases — full / dirty)
* **Update**: [drawing](/drawing.md) — `SchedulesFrames` is the shared
  hook/clock; `RunsFrames` keeps the GPU Painter/Executor frame.
  `CPUCanvas` + `FullCanvas` / `DirtyCanvas` rasterise into a php
  framebuffer. Scale is `1.0`. Preserving buffers fill once at attach.
  `bootSchedule()` leaves a pre-set clear colour alone (epaper door).
* **Update**: [testing](/testing.md) — `CPUCanvasTest` on an SSD1306-shaped
  host; hex and `rgba8` length stay exact.
* **Update**: [index](/index.md) — suite count 625.

## 2026-09-17 (Rasterizer)
* **Update**: [drawing](/drawing.md) — `Rasterizer` is the one `Drawing2D`
  over any `Framebuffer`. `Affine` and `Geometry` are shared with
  `Painter`. `RastersNatively` is a construction-time seam; no driver
  implements it this slice.
* **Update**: [testing](/testing.md) — `RecordingFramebuffer` counts write
  verbs so batching (`setSegment`, `setPixels`, `fill`) is fake-provable.
  Pixel pictures in `RasterizerTest` are exact `#`/`.` rows, never
  weakened to `toContain`.

## 2026-09-17 (PixelMapper planar unmap)
* **Update**: [testing](/testing.md) — planar `unmap` of a multi-bit mask is the
  lowest set bit's colour (`0` is paper). Exact-word miss used to return white,
  so php/native parity failed on PLANAR when a random op wrote word `3`.

## 2026-09-17 (php driver — dirty, epaper, paged, ring)
* **Update**: [testing](/testing.md) — `BuffersTest` + `PhpDriverFixturesTest`
  runs all 27 fixtures. Driver mints all five kinds; Task 4 throws gone.

## 2026-09-17 (php driver — packings + FullFramebuffer)
* **Update**: [testing](/testing.md) — `PackingsTest` + `PhpDriverFixturesTest`
  (full-kind filter). Nine packings, `PackedGrid`, `FullFramebuffer`;
  temporary driver refuses dirty/epaper/paged/ring until Task 4.

## 2026-09-17 (PixelMapper + fixtures)
* **Update**: [testing](/testing.md) — `FixtureRunner` + `Rgba8Source` under
  `tests/Support/Framebuffers`; 27 hand-packed golden fixtures; PixelMapper
  colour policy is driver-independent and fake-provable without a packing.

## 2026-09-17 (CPU contracts)
* **Update**: [drawing](/drawing.md) — `DrawTarget` is engine-free;
  `GPUDrawTarget` keeps `engine()`/`executor()` for GPUView and Stage;
  CPU target / host / five `CPUEngine` cases are on the contracts.
* **Update**: [testing](/testing.md) — `FakeCPUEngineDriver` in the
  shared fakes table.
* **Update**: [index](/index.md) — suite count 520.

## 2026-09-17 (hardware)
* **Update**: [HumanInput](/human-input.md) — `# Proven on`: Mac sdl3/appkit, Pi 5 gtk/sdl3, seesaw circuit.

## 2026-09-17 (HumanInput fix wave)
* **Update**: [HumanInput](/human-input.md) — focus-loss rule (deactivate
  releases every key and mouse button); `window()` = name under the pointer
  or null; wheel in lines, `dy > 0` = rolled away (physical). `ICInput`
  keeps sub-tick taps from circuit edges; a throwing circuit faults
  (`faulted()`/`fault()`), releases, zeroes, reads disconnected, never polled
  again, recovered by `detach()` + `attach()`. I2C circuit polls sleep;
  unbooted circuits read disconnected. "Who sees what" table: appkit covers
  appkit stages, sdl3 pads need no window, GTK 4.8 hides natural scroll.

## 2026-09-17 (HumanInput, Surface side)
* **Creation**: [HumanInput](/human-input.md) — keyboards, mice, game pads,
  game controllers behind one vocabulary. Same seam as GPU/Stage:
  `input.<engine>` container alias (sdl3 default, `INPUT_ENGINE` env) →
  `InputEngineDriver`; IC circuits (`Circuits\ButtonPad`/`GameController`)
  attach by name → `HumanInputManager` → `input` dock resource. Engine start
  lazy at first `engine()`; the `input` resource never starts one.
  `keyboard()`/`mouse()` read the default engine only; `gamePads()`/
  `gameControllers()` merge every connected engine plus attached circuits,
  a same-named circuit replacing an engine device. Edge rule: `settle()`
  then `update()`, ORed within a tick, so a tap inside one poll shows
  `isPressed()` and `wasReleased()` together. Axes clamp on write
  (sticks −1…1, triggers 0…1). GamePad vs GameController decided once by
  axis set (`LEFT_X`/`RIGHT_X` present). A disconnected circuit stops
  polling and reads released but stays attached until `detach()`. Mail
  `input.gamepad.connected.<id>` / `.disconnected.<id>`, diffed per tick,
  no per-key mail. `input` registers on the dock inside
  `Application::booted()`, landing after `os`. `LiveApplication::destroy()`
  order: input engines, then stages, then windows, then the bridge — a
  throw at any step still runs the rest. None of the three engine packages
  (sdl3, appkit, gtk) ship yet; they are being built in `jovian/venusian-*`
  against this contract.
* **Update**: [Components to come](/components-to-come.md) — HumanInput
  section removed (shipped); Fonts only.
* **Update**: [index](/index.md) — HumanInput concept linked; "What stands
  today" gains it; suite at 499.
* **Update**: [async](/async.md) — `input` dock resource row.
* **Update**: [engine-seam](/engine-seam.md) — `# Input engines` section,
  mirroring `# Stage hosts`.
* **Update**: [stage](/stage.md) — `# Not here` drops input; points at
  [human-input](/human-input.md) instead.
* **Update**: [testing](/testing.md) — `FakeInputEngine`, `FakeButtonPad`,
  `FakeControllerPad` join the fakes table.

## 2026-09-14 (split manifests)
* **Update**: [index](/index.md) — every component except Core carries a
  `composer.json`; `surface/embedded-panels`, `surface/fonts`,
  `surface/human-input` join the root `replace` map. Eight split packages.
  Each manifest declares its own provider and alias under `extra.venusian`.

## 2026-09-14
* **Update**: [stage.md](/stage.md) — `StageManager::destroy()` (close
  stages, disconnect host sessions, keep going, rethrow first), called by
  `LiveApplication::destroy()` before the windows; `StageSession::disconnect()`
  ends disconnected on a throwing hook; `StageException::attachFailed`.
* **Update**: [drawing.md](/drawing.md), [index](/index.md) — four seam
  shapes; GTK hosts only `GL_CONTEXT`; SDL lends `VULKAN_SURFACE` on
  Linux; Metal blends; engines `metal`, `opengl`, `vulkan`, `sdl3`.

## 2026-09-14 (Stage, slice 4 — Surface side)
* **Creation**: [Stage — engine-owned windows](/stage.md) — whole windows
  an engine draws every pixel of; `Stage::open($name, $engine, $w, $h,
  $host)` names a host by alias (`stage.<host>`) and an engine by alias
  (`gpu.<engine>`), mints a `StagedWindow` hidden, same
  `onDraw(fn (Drawing2D $g, Frame $f))` as a GPUView. Rules: engine start
  lazy at first `connect()`; a stage renders only once shown
  (`frameVisible()` open && shown, never auto-shown by `StageManager::open()`);
  `resized()` is change-only, resizes the executor, requests a frame, then
  mails; `close()` is terminal and idempotent — release → `destroyNative()`
  in a `finally` so a throwing release still destroys the native — and
  announces `StageClosed` once; `closeRequested()` announces `StageClosed`
  once and leaves the window open, latching only when the mail was
  actually pushed so a request before `setPool()` is not spent. Loop: dock
  resource `stage.<host>` pumps (skipped for AppKit when `os` is on the
  dock), then one frame per open stage; the pump never waits.
* **Creation**: [Components to come — HumanInput, Fonts](/components-to-come.md)
  — two reserved directories, no code, the facts each will need recorded
  ahead of the work: AppKit has no PHP-side key/mouse events yet, SDL3
  read-side events exist but are unread by the stage session, no glyph
  rasteriser exists in the stack.
* **Update**: [GPU drawing](/drawing.md) — four seam shapes now:
  `LAYER`, `GL_CONTEXT`, `VULKAN_SURFACE` (host lends a
  `VulkanSurfaceLender` via `GPUHost->vk`), `HOST_WINDOW` (engine drives
  the host's own window via `GPUHost->native_view`). A host may also lend
  a layer it owns (`GPUHost->layer`) for a LAYER engine to adopt. The
  frame loop (`RunsFrames`) is shared by GPUView and StagedWindow.
* **Probe**: native windows and engine-owned stages coexist in one
  process. Mac: `PROBE_COEXIST_OK`, a native AppKit window plus an SDL3
  window. Pi: `PROBE_COEXIST_OK` with `DISPLAY=:0` — GTK on Wayland, SDL
  on XWayland, because the Pi's hand-built `/usr/local` libSDL3 3.4.9 has
  no Wayland video driver (without `DISPLAY` set, `SDL_Init(VIDEO)` finds
  no available video device). Durable rule: an SDL stage's video driver
  follows what libSDL3 was built with; coexistence with GTK holds either
  way because the display connections are separate.
* Suite at 452.

## 2026-09-14
* **Update**: [GPU drawing](/drawing.md) — slice 3: Vulkan is a `LAYER`
  engine (MoltenVK → `CAMetalLayer`); engines list is metal / opengl /
  vulkan; blending true on OpenGL and Vulkan; a Linux Vulkan host is a
  later slice. Zero Surface code change — AppKit already adopts any
  LAYER pointer; GTK still refuses LAYER by enum.

## 2026-09-13
* **Update**: [GPU drawing](/drawing.md) — slice 2: `SurfaceKind`, `GLSurface`, `GPUHost->gl`; two seam shapes; the self-driving twin; blending real on OpenGL first.
* **Proof**: `orbit metal` and `orbit opengl` on the Mac, `orbit` on the Pi (GTK + GLES 3.1) — the same scene on three targets; alpha real on OpenGL, opaque on Metal, on purpose. Angel confirmed the Pi panel (windowed GtkGLArea, clean close, no `Gtk-CRITICAL`).
* **Note**: Headless ogx Feature tests on this Pi: 21 passed / 4 failed — the EGL surfaceless desktop context has no GLSL 1.50 (only 1.40 + ES 3.00). Windowed GtkGLArea is ES.
* **Update**: [Where AppKit and GTK disagree](/engine-asymmetries.md) — the GPU-region rows (context owner, target FBO, dialect, GLES gate, no-drawable, present, frame driver, drawable size, blending).
* **Update**: [Test suite](/testing.md) — `FakeGLSurface`; the GL route through `FakeWindow` and `FakeLinuxWindow`; mint order held by the fake.

## 2026-09-13 (GPU drawing, slice 1 — Surface side)
* **Creation**: [GPU drawing](/drawing.md) — `Surface\Contracts\Drawing`
  (Executor, Drawing2D, DrawTarget, GPUEngineDriver, values, enums, Frame),
  the `surface/drawing` split (Painter, GPUEngineManager, `config/gpu.php`,
  `GPU` alias), `GPUView` + `Windowable::gpu()` + `renderFrames()` on the
  tick after layout. Decisions: projection-only Transform with the affine
  stack folded into vertices; mid-frame `readPixels()`; Frame in contracts;
  release-before-destroy in the abstract. Suite at 405.
* **Update**: [Conjured views](/views.md) — nineteen kinds. [Test
  suite](/testing.md) — FakeExecutor and friends.

## 2026-09-13 (LiveApplication replaces ProgramShuttle)
* **Update**: [Window provisioning](/window-provisioning.md),
  [Menu-bar profiles](/menu-profiles.md), [Conjured views](/views.md) —
  `ProgramShuttle` / `Program` (`os-program`) → `LiveApplication` /
  `LiveApp` (`live-app`). The engine pump and `syncLayout()` run in
  `OSLevelResourceDriver`, registered on the IOPool dock as `os`. Menu mail
  is `MenuOccurrence` named `menu.<window>` with the author's event on
  `event_name`; an item whose event is `quit` pushes `QuitRequested`.
* **Update**: [The loop and the IOPool dock](/async.md) — rewritten.
  `callHttp()` / `register()` / `sink()` are gone: HTTP is the dock's `http`
  resource (`MultiCurlResourceDriver` → `Presumption`), periodic work
  registers as a dock resource.
* **Update**: index — suite at 359.
* **Fix**: [A container alias is the only seam to an engine](/engine-seam.md)
  — hard dependencies are `nuts-and-bolts` and `io-pools`.

## 2026-09-12 (Datepicker becomes a field + dropped calendar)
* **Update**: [Components](/components.md) — **Datepicker** is no
  longer a thin wrap that fills its frame with the month grid. It is a
  TextInput plus a `▾` trigger; the `datePicker` calendar is conjured
  into the host under the field on open and removed on close; a pick
  resolves the field to `Y-m-d`, collapses the calendar, and fires
  `onChange`. Typed whole dates resolve too. Recorded why the field
  itself cannot open it yet (no focus hook on `textInput`).
  [Views](/views.md) and [Asymmetries](/engine-asymmetries.md) — the
  "AppKit setters are silent" claim is narrowed: `NSDatePicker` and
  `NSTableView` re-enter their delegates on programmatic writes, so
  those two AppKit twins hold the `applying` flag as well.

## 2026-09-12 (datePicker + table primitives)
* **Update**: [Conjured views](/views.md) — eighteen kinds. `datePicker`
  (day-only, `Y-m-d`, `DateChanged`) and `table` (read-only string cells,
  single-row selection, `RowSelected`) join the tree with contracts,
  abstracts, Windowable/ViewGroup sugar, fakes, and typed mail.
  [Components](/components.md) — **Datepicker** and **DataTable** wrap
  those primitives the Select way; catalogue is 25 of 25, stubs gone.
  [Asymmetries](/engine-asymmetries.md) — GTK calendar month is 0-based;
  AppKit `NSDate` crosses through `NSDateFormatter`; table twins are
  ColumnView / NSTableView.

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

## 2026-09-17
* **Update**: [async](/async.md) — `tick()` drains once into a per-tick bag; `mail()` returns it whole and ordered, `events()` keyed by name. Same-name mail in one tick is no longer lost.

## 2026-09-17
* **Update**: [stage](/stage.md) — a host may own the native pump (`ownsNativePump()`; SDL on macOS): the `os` resource then skips its NSApp drain and hands that host the idle wait, since SDL reads keys only inside its own pump.

## 2026-09-17
* **Creation**: [fonts](/fonts.md) — faces, registry, text on both engines, make:font. **Removal**: components-to-come.md (Fonts landed).
