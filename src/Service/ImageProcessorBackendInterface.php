<?php

/**
 * WT Image Resize and Convert backend contract.
 *
 * @package       WT Image Resize and Convert
 * @subpackage    plg_media-action_wtimageresizeconvert
 * @author     WebTolk
 * @copyright  Copyright (c) 2026 WebTolk. All rights reserved.
 * @version       1.0.0
 * @license       GNU General Public License version 3 or later.
 * @link          https://web-tolk.ru
 */

namespace Webtolk\Plugin\MediaAction\WtImageResizeConvert\Service;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Contract for image processor backends.
 *
 * @since  1.0.0
 */
interface ImageProcessorBackendInterface
{
    /**
     * Returns backend identifier used by plugin parameters.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    public function getName(): string;

    /**
     * Checks whether the backend can be used in the current runtime.
     *
     * @return  bool
     *
     * @since   1.0.0
     */
    public function isAvailable(): bool;

    /**
     * Processes source image bytes.
     *
     * @param   string                $data             Source image bytes.
     * @param   string                $sourceExtension  Source file extension.
     * @param   array<string, mixed>  $options          Processing options.
     *
     * @return  array{data: string, extension: string}|null
     *
     * @since   1.0.0
     */
    public function process(string $data, string $sourceExtension, array $options): ?array;
}
