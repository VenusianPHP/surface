---
type: Architecture
title: Components
description: >-
  Opinionated shapes composed from view primitives: one root Group, parts
  named <component>.<part>, pure PHP — no engine code per component.
tags: [surface, native-windows, components, composition]
status: draft
generated: { by: cursor-grok-4.6/cursor, at: "2026-09-04T18:20:00Z" }
sources:
  - id: base
    resource: src/Surface/NativeWindows/Components/Component.php
    title: Component — root mount, parts, move/place, subtree removal
  - id: sidebar
    resource: src/Surface/NativeWindows/Components/Sidebar.php
    title: Sidebar — scroll, sticky selection, collapse breakpoint
  - id: iconfield
    resource: src/Surface/NativeWindows/Components/IconField.php
    title: IconField — the thin-wrapper recipe Wave 1 copies
  - id: tests
    resource: tests/NativeWindows/Components/SidebarTest.php
    title: Sidebar tests
  - id: wrappers
    resource: src/Surface/NativeWindows/Components/InputText.php
    title: InputText — first of the seven primitive wrappers
---

# The layer

Components are the opinionated tier over the primitives, modelled on the
PrimeVue catalogue (25 shapes in `Surface\NativeWindows\Components`).
A component is **pure PHP**: it mounts one root Group, conjures its parts
inside, and never touches an engine — which caps the twin burden at the
primitive list forever. Every component works on both engines the day it
is written.[^base]

# Anatomy

- Root: `$window->group($name, ...)` — so moving the component moves the
  subtree natively, and part coordinates are component-relative FREE
  (the layoutSpace() host seam from the primitive wave).
- Parts: window-global names `<component>.<part>` (`nav.scroll`,
  `nav.link.home`), short names in the component's registry —
  `part('title')`, `move('title', 10, 10)` (component-relative, size
  kept), `register()` from build(). Only a component's OWN parts get the
  prefix: content a sketch conjures into a body() group registers under
  exactly the name given, window-global — two cards each holding a
  `readout` collide, so the sketch prefixes those itself.
- Lifecycle: ctor mounts root → `build()` conjures → `layout()`
  arranges. Constructor-promoted subclass state is assigned before the
  parent ctor body runs, so subclasses just promote and call
  `parent::__construct()` last. `place()` re-frames + re-runs
  `layout()` — responsive behaviour lives there. `remove()` kills the
  subtree and frees every name.

# Built (12 of 25)

| Component | Shape | Notable |
|---|---|---|
| **Sidebar** | root → ScrollView → full-width ToggleButton rows | Native pressed = selected; sticky (unpress snaps back); `onSelect` fires on user presses only; extent grows per `addLink`; width under `collapse_below` (default 140) relabels rows to their glyph — icons are STRINGS (emoji/symbol), the one icon path both engines render in a control label today |
| **Card** | title + optional subtitle + `body()` group | Sketch conjures its own content into the body |
| **Toolbar** | flowed strip | `addButton/addToggle/addSeparator`, natural widths via hug(), vertical centring, auto-named `sep.<n>` |
| **Message** | severity callout | `MessageSeverity` fill/ink pairs; closable `×` removes the component then hooks `onClose` |
| **IconField** | glyph label + TextInput | value/onChange/onSubmit delegate to the inner input — the recipe the seven wrappers copy[^iconfield] |
| **InputText** | `textInput` filling the root | `search.input`; value/onChange/onSubmit; optional placeholder; `setValue` silent[^wrappers] |
| **TextArea** | `textArea` filling the root | `notes.area`; value/onChange/setEditable; `setValue` silent |
| **Slider** | `slider` filling the root | `volume.slider`; value/min/max/setRange/onChange; `setValue` silent |
| **ToggleSwitch** | `toggle` filling the root | `wifi.switch`; isOn/setOn/onToggle; `setOn` silent |
| **ProgressBar** | `progressBar` filling the root | `load.bar`; progress 0..1, clamp lives on the view |
| **ProgressSpinner** | `spinner` filling the root | `busy.spinner`; conjured stopped; start/stop |
| **Select** | `dropdown` filling the root | `planet.dropdown`; options/select/onSelect; programmatic `select` silent |

Hooks follow the view rule: in-pump, one per slot, replace not stack.
Typed mail still flows from the underlying primitives (`Toggled` from
sidebar rows, `TextChanged` from an IconField's input) — a sketch may
listen instead of hooking, same two doors as everywhere.

# Remaining stubs

Every remaining stub composes from the sixteen shipped primitives, the
same way the twelve built shapes do. Where a native widget exists for the
same job (NSTableView, NSDatePicker, GtkNotebook), it stays an unbound
twin: composition is the decided route, chosen at the primitive
inventory because those natives have no peer on the other engine. A
composition that needs a native call bound in the ext but missing from a
jovian wrapper exposes it just-in-time.

Datepicker and DataTable stay empty stubs until a date or table
primitive is worth it — they do not compose from today's list.

| Stub | Recipe |
|---|---|
| Badge / Chip | Group + Label; Chip adds a close Button — Message minus severity |
| Breadcrumb | Buttons + '›' Labels flowed like Toolbar |
| DataTable | empty stub — revisit when a table primitive is worth it |
| DataView | ScrollView + a card-ish Group per item |
| Datepicker | empty stub — revisit when a date primitive is worth it |
| Drawer | Group slid on/off with place() + show/hide |
| InputNumber | TextInput + two small Buttons (no stepper primitive — see engine-asymmetries) |
| ListBox | ScrollView + selectable Group rows — Sidebar minus the collapse |
| SelectButton | ToggleButton row with exclusive selection — Sidebar's sticky logic, horizontal |
| Skeleton | Groups with a grey setBackground |
| Tabs | ToggleButton headers + one Group panel per tab toggled with setVisible |
| Toast | Message in a Group placed over the content, show/hide |

Copy the closest exemplar: selection shapes copy Sidebar, callouts copy
Message, form fields copy IconField, flowed strips copy Toolbar, titled
containers copy Card.

[^base]: Component — root mount, parts, move/place, subtree removal
[^sidebar]: Sidebar — scroll, sticky selection, collapse breakpoint
[^iconfield]: IconField — the thin-wrapper recipe Wave 1 copies
[^tests]: Sidebar tests
[^wrappers]: InputText — first of the seven primitive wrappers
