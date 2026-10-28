# Venusian Surface

[![Tests](https://github.com/VenusianPHP/surface/actions/workflows/tests.yml/badge.svg)](https://github.com/VenusianPHP/surface/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/venusian/surface.svg)](https://packagist.org/packages/venusian/surface)
[![Total Downloads](https://img.shields.io/packagist/dt/venusian/surface.svg)](https://packagist.org/packages/venusian/surface)
[![License](https://img.shields.io/packagist/l/venusian/surface.svg)](LICENSE)

## About Venusian Surface

> **Note:** This repository contains the display panel framework for Venusian. If you want to build an application using Venusian, visit the main [Venusian skeleton repository](https://github.com/VenusianPHP/venusian).

Venusian Surface is the Venusian Display Panel Framework: native windows and toolkit widgets, staged windows, pixel framebuffers, drawing engines, fonts, images, embedded display panels and human input, all in the Laravel-like syntax you already know.

Want a real desktop window with menus, buttons and dialogs? Surface bridges to AppKit, GTK 4 or Qt 6 and runs the toolkit on your app's own loop. Rather draw every pixel yourself? Open a staged window over SDL3 or GLFW and hand it to a rendering engine. Velvet, the software engine, ships in the box; Metal, OpenGL, SDL_GPU and Vulkan engines plug in as their own packages.

And the same drawing code reaches past the desktop. Got an OLED, a TFT or an ePaper panel wired to a Raspberry Pi? Attach it as an embedded display, draw on it like any other framebuffer, and only the pixels that changed go over the bus.

Every hot path has a PHP driver that works out of the box and an `extended` driver in C (ext-fb, ext-rasterize, ext-imgdec) for when you want the speed. Same API either way.

> **Built with AI assistance.** This package was developed LLM-assisted — not vibe-coded. Generated code was deliberately prompted and reviewed by a professional software engineer. See [AI-assisted development](#ai-assisted-development).

## Learning Venusian Surface

Venusian Surface has documentation and guides on the [Venusian website](https://venusian.projectsaturnstudios.com/docs/surface/overview), from the [overview](https://venusian.projectsaturnstudios.com/docs/surface/overview) and [its components](https://venusian.projectsaturnstudios.com/docs/surface/guide/components) to [composing an app](https://venusian.projectsaturnstudios.com/docs/surface/runbooks/composing-an-app).

## AI-assisted development

This codebase was built with LLM assistance under human direction. Prompts, reviews, and architectural decisions were owned by a professional software engineer — the model accelerates drafting and exploration; it does not replace judgment on APIs, dependency direction, or what ships.

## Contributing

Thank you for considering contributing to Venusian Surface! The contribution guide can be found in the [Venusian documentation](https://venusian.projectsaturnstudios.com/docs/contributions).

## Code of Conduct

In order to ensure that the Venusian community is welcoming to all, please review and abide by the [Code of Conduct](https://venusian.projectsaturnstudios.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

Please review [our security policy](https://github.com/VenusianPHP/surface/security/policy) on how to report security vulnerabilities.

## License

Venusian Surface is open-sourced software licensed under the [MIT license](LICENSE).
