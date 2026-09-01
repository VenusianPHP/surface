<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Surface has no application container. Tests run against plain objects and
| the fakes in tests/Support/Fakes, so every shared policy — the session state
| machine, the window registry, the shuttle — is provable with no extension
| loaded and no engine package installed.
|
| Engine sessions are proven on real hardware instead: macOS for AppKit, the
| Pi over `fnk` for GTK.
|
| tests/Views and the three orphaned tests/NativeWindows files are held out of
| the default suite by phpunit.xml. They reference the deleted
| Surface\NativeWindows\Views\* tree from before the 0.8 teardown.
|
*/
