# Media - WT Image Resize and Convert

[![Joomla](https://img.shields.io/badge/Joomla-5.0%2B-5091CD?logo=joomla)](https://www.joomla.org/)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Latest release](https://img.shields.io/github/v/release/WebTolk/wt-image-resize-and-convert)](https://github.com/WebTolk/wt-image-resize-and-convert/releases)
[![License](https://img.shields.io/badge/license-GPL--3.0--or--later-4A8F00)](https://www.gnu.org/licenses/gpl-3.0.html)

Media Action plugin for Joomla Media Manager. It resizes, converts and optionally watermarks supported images when Media Manager saves them.

## Features

- Convert uploaded images to JPG, PNG, GIF, WEBP or AVIF when supported by PHP GD.
- Resize by bounds, crop, width or height; optionally prevent upscaling.
- Apply an optional image watermark.
- Define named processing profiles and assign them to Media Manager folders.
- Use the most specific matching folder rule; otherwise use the default profile.

## Requirements

- Joomla 5.0 or later.
- PHP 8.1 or later.
- PHP GD with support for the source and selected output format.

## Installation

1. Download the ZIP from the [latest release](https://github.com/WebTolk/wt-image-resize-and-convert/releases/latest).
2. In Joomla administrator, open **System → Install → Extensions** and upload the package.
3. Enable **Media - WT Image Resize and Convert** under **System → Plugins**.
4. Configure the default profile on the **Plugin** and **Watermark** tabs.

## Documentation

Read the complete English documentation and configuration guide on [WebTolk](https://web-tolk.ru/en/dev/joomla-plugins/wt-image-resize-and-convert).

## License

GNU General Public License version 3 or later.
