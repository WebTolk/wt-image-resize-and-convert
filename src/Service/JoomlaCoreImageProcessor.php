<?php

/**
 * WT Image Resize and Convert Joomla core image backend.
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

use Joomla\CMS\Image\Image;
use Joomla\Filesystem\File;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Joomla core Image-backed processor with small GD fallbacks for byte decoding and watermark compositing.
 *
 * @since  1.0.0
 */
final class JoomlaCoreImageProcessor implements ImageProcessorBackendInterface
{
    /**
     * Returns backend identifier used by plugin parameters.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    public function getName(): string
    {
        return 'joomla-core';
    }

    /**
     * Checks whether the backend can be used in the current runtime.
     *
     * @return  bool
     *
     * @since   1.0.0
     */
    public function isAvailable(): bool
    {
        return class_exists(Image::class) && extension_loaded('gd');
    }

    /**
     * Processes source image bytes.
     *
     * @param   string                $data             Source image bytes.
     * @param   string                $sourceExtension  Source file extension.
     * @param   array<string, mixed>  $options          Processing options.
     *
     * @return  array{data: string, extension: string}|null
     *
     * @throws  \RuntimeException
     *
     * @since   1.0.0
     */
    public function process(string $data, string $sourceExtension, array $options): ?array
    {
        $sourceExtension = $this->normalizeExtension($sourceExtension);

        if (!$this->canDecode($sourceExtension)) {
            return null;
        }

        $targetExtension = $this->normalizeExtension((string)($options['target_format'] ?? 'keep'));
        $targetExtension = $targetExtension === 'keep' ? $sourceExtension : $targetExtension;

        if (!$this->canEncode($targetExtension)) {
            return null;
        }

        $source = @imagecreatefromstring($data);

        if (!$source instanceof \GdImage) {
            return null;
        }

        $sourceImage = new Image($source);

        try {
            $image = $this->resizeImage($sourceImage, $options);

            if (!empty($options['watermark_enabled']) && !empty($options['watermark_path'])) {
                $this->applyWatermark($image, (string)$options['watermark_path'], $options);
            }

            return [
                'data' => $this->encode($image, $targetExtension, $options),
                'extension' => $targetExtension,
            ];
        } finally {
            $sourceImage->destroy();

            if (isset($image) && $image instanceof Image && $image !== $sourceImage) {
                $image->destroy();
            }
        }
    }

    /**
     * Normalizes supported extensions.
     *
     * @param   string  $extension  File extension.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    private function normalizeExtension(string $extension): string
    {
        $extension = strtolower(trim($extension, ". \t\n\r\x00\x0B"));

        return $extension === 'jpeg' ? 'jpg' : $extension;
    }

    /**
     * Checks whether GD can decode the source format.
     *
     * @param   string  $extension  Source extension.
     *
     * @return  bool
     *
     * @since   1.0.0
     */
    private function canDecode(string $extension): bool
    {
        return match ($extension) {
            'gif' => function_exists('imagecreatefromgif'),
            'jpg' => function_exists('imagecreatefromjpeg'),
            'png' => function_exists('imagecreatefrompng'),
            'webp' => function_exists('imagecreatefromwebp'),
            'avif' => function_exists('imagecreatefromavif'),
            default => false,
        };
    }

    /**
     * Checks whether GD can encode the target format.
     *
     * @param   string  $extension  Target extension.
     *
     * @return  bool
     *
     * @since   1.0.0
     */
    private function canEncode(string $extension): bool
    {
        return match ($extension) {
            'gif' => function_exists('imagegif'),
            'jpg' => function_exists('imagejpeg'),
            'png' => function_exists('imagepng'),
            'webp' => function_exists('imagewebp'),
            'avif' => function_exists('imageavif'),
            default => false,
        };
    }

    /**
     * Resizes the source image according to options.
     *
     * @param   Image                 $source   Source image.
     * @param   array<string, mixed>  $options  Processing options.
     *
     * @return  Image
     *
     * @since   1.0.0
     */
    private function resizeImage(Image $source, array $options): Image
    {
        $sourceWidth = $source->getWidth();
        $sourceHeight = $source->getHeight();
        $maxWidth = max(0, (int)($options['max_width'] ?? 0));
        $maxHeight = max(0, (int)($options['max_height'] ?? 0));
        $allowUpscale = (bool)($options['allow_upscale'] ?? false);
        $fillColor = $this->normalizeFillColor((string)($options['image_fill_color'] ?? '#ffffff'));
        $mode = (string)($options['mode'] ?? 'none');

        if ($mode === 'none') {
            return $source;
        }

        if ($mode === 'crop' && $maxWidth > 0 && $maxHeight > 0) {
            if (!$allowUpscale) {
                $maxWidth = min($maxWidth, $sourceWidth);
                $maxHeight = min($maxHeight, $sourceHeight);
            }

            if ($maxWidth === $sourceWidth && $maxHeight === $sourceHeight) {
                return $source;
            }

            return $source->cropResize($maxWidth, $maxHeight);
        }

        if ($mode === 'fit' && $maxWidth > 0 && $maxHeight > 0) {
            if (!$allowUpscale) {
                $maxWidth = min($maxWidth, $sourceWidth);
                $maxHeight = min($maxHeight, $sourceHeight);
            }

            if ($maxWidth === $sourceWidth && $maxHeight === $sourceHeight) {
                return $source;
            }

            return $this->fitOnCanvas($source, $maxWidth, $maxHeight, $fillColor);
        }

        [$targetWidth, $targetHeight] = $this->calculateFitSize(
            $sourceWidth,
            $sourceHeight,
            $maxWidth,
            $maxHeight,
            $mode,
            $allowUpscale
        );

        if ($targetWidth === $sourceWidth && $targetHeight === $sourceHeight) {
            return $source;
        }

        return $source->resize($targetWidth, $targetHeight, true, Image::SCALE_FILL);
    }

    /**
     * Fits the source image onto a filled canvas while preserving proportions.
     *
     * @since   1.0.0
     */
    private function fitOnCanvas(Image $source, int $canvasWidth, int $canvasHeight, string $fillColor): Image
    {
        [$imageWidth, $imageHeight] = $this->calculateFitSize(
            $source->getWidth(),
            $source->getHeight(),
            $canvasWidth,
            $canvasHeight,
            'fit',
            true
        );

        $canvas = imagecreatetruecolor($canvasWidth, $canvasHeight);

        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);
        imagefilledrectangle(
            $canvas,
            0,
            0,
            $canvasWidth,
            $canvasHeight,
            $this->allocateFillColor($canvas, $fillColor)
        );

        imagecopyresampled(
            $canvas,
            $source->getHandle(),
            (int)round(($canvasWidth - $imageWidth) / 2),
            (int)round(($canvasHeight - $imageHeight) / 2),
            0,
            0,
            $imageWidth,
            $imageHeight,
            $source->getWidth(),
            $source->getHeight()
        );

        return new Image($canvas);
    }

    /**
     * Allocates the configured fill color on a GD image.
     *
     * @since   1.0.0
     */
    private function allocateFillColor(\GdImage $image, string $color): int
    {
        $hex = ltrim($color, '#');
        $alpha = strlen($hex) === 8 ? (int)floor((255 - hexdec(substr($hex, 6, 2))) / 2) : 0;

        $color = imagecolorallocatealpha(
            $image,
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
            min(127, max(0, $alpha))
        );

        return $color === false ? 0 : $color;
    }

    /**
     * Normalizes the configured fill color for GD allocation.
     *
     * @since   1.0.0
     */
    private function normalizeFillColor(string $color): string
    {
        $color = trim($color);

        if (preg_match('/^#[0-9a-f]{6}([0-9a-f]{2})?$/i', $color)) {
            return $color;
        }

        if (preg_match('/^[0-9a-f]{6}([0-9a-f]{2})?$/i', $color)) {
            return '#' . $color;
        }

        return '#ffffff';
    }

    /**
     * Calculates proportional target size.
     *
     * @return  array{0: int, 1: int}
     *
     * @since   1.0.0
     */
    private function calculateFitSize(
        int $sourceWidth,
        int $sourceHeight,
        int $maxWidth,
        int $maxHeight,
        string $mode,
        bool $allowUpscale
    ): array {
        if ($maxWidth <= 0 && $maxHeight <= 0) {
            return [$sourceWidth, $sourceHeight];
        }

        $ratio = match ($mode) {
            'width' => $maxWidth > 0 ? $maxWidth / $sourceWidth : 1.0,
            'height' => $maxHeight > 0 ? $maxHeight / $sourceHeight : 1.0,
            default => min(
                $maxWidth > 0 ? $maxWidth / $sourceWidth : PHP_FLOAT_MAX,
                $maxHeight > 0 ? $maxHeight / $sourceHeight : PHP_FLOAT_MAX
            ),
        };

        if (!$allowUpscale) {
            $ratio = min(1.0, $ratio);
        }

        return [
            max(1, (int)round($sourceWidth * $ratio)),
            max(1, (int)round($sourceHeight * $ratio)),
        ];
    }

    /**
     * Creates a truecolor canvas with alpha support.
     *
     * @since   1.0.0
     */
    private function createTransparentCanvas(int $width, int $height): \GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $transparent);
        imagealphablending($canvas, true);

        return $canvas;
    }

    /**
     * Applies a watermark image to the target image.
     *
     * @param   Image                 $image          Target image.
     * @param   string                $watermarkPath  Watermark file path.
     * @param   array<string, mixed>  $options        Processing options.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    private function applyWatermark(Image $image, string $watermarkPath, array $options): void
    {
        if (!File::exists($watermarkPath) || !is_readable($watermarkPath)) {
            return;
        }

        $watermarkData = @file_get_contents($watermarkPath);

        if ($watermarkData === false) {
            return;
        }

        $watermark = @imagecreatefromstring($watermarkData);

        if (!$watermark instanceof \GdImage) {
            return;
        }

        $watermarkImage = new Image($watermark);

        try {
            $scaled = $this->scaleWatermark(
                $watermarkImage,
                $image->getWidth(),
                (int)($options['watermark_scale'] ?? 20)
            );
            $this->copyWatermark($image, $scaled, $options);
        } finally {
            $watermarkImage->destroy();

            if (isset($scaled) && $scaled instanceof Image && $scaled !== $watermarkImage) {
                $scaled->destroy();
            }
        }
    }

    /**
     * Scales watermark relative to target image width.
     *
     * @since   1.0.0
     */
    private function scaleWatermark(Image $watermark, int $targetImageWidth, int $scalePercent): Image
    {
        $sourceWidth = $watermark->getWidth();
        $sourceHeight = $watermark->getHeight();
        $targetWidth = max(1, (int)round($targetImageWidth * min(100, max(1, $scalePercent)) / 100));
        $targetWidth = min($targetWidth, $sourceWidth);
        $targetHeight = max(1, (int)round($sourceHeight * ($targetWidth / $sourceWidth)));

        if ($targetWidth === $sourceWidth && $targetHeight === $sourceHeight) {
            return $watermark;
        }

        return $watermark->resize($targetWidth, $targetHeight, true, Image::SCALE_FILL);
    }

    /**
     * Copies the watermark with configured opacity and position.
     *
     * @param   Image                 $image      Target image.
     * @param   Image                 $watermark  Watermark image.
     * @param   array<string, mixed>  $options    Processing options.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    private function copyWatermark(Image $image, Image $watermark, array $options): void
    {
        $opacity = min(100, max(1, (int)($options['watermark_opacity'] ?? 80)));
        $marginX = max(0, (int)($options['watermark_margin_x'] ?? 24));
        $marginY = max(0, (int)($options['watermark_margin_y'] ?? 24));
        $width = $watermark->getWidth();
        $height = $watermark->getHeight();

        [$x, $y] = $this->calculateWatermarkPosition(
            $image->getWidth(),
            $image->getHeight(),
            $width,
            $height,
            (string)($options['watermark_position'] ?? 'bottom-right'),
            $marginX,
            $marginY
        );

        if ($opacity >= 100) {
            imagecopy($image->getHandle(), $watermark->getHandle(), $x, $y, 0, 0, $width, $height);

            return;
        }

        $this->copyWatermarkWithOpacity($image->getHandle(), $watermark->getHandle(), $x, $y, $opacity);
    }

    /**
     * Calculates watermark top-left coordinates.
     *
     * @return  array{0: int, 1: int}
     *
     * @since   1.0.0
     */
    private function calculateWatermarkPosition(
        int $imageWidth,
        int $imageHeight,
        int $watermarkWidth,
        int $watermarkHeight,
        string $position,
        int $marginX,
        int $marginY
    ): array {
        return match ($position) {
            'top-left' => [$marginX, $marginY],
            'top-right' => [max(0, $imageWidth - $watermarkWidth - $marginX), $marginY],
            'center' => [
                max(0, (int)floor(($imageWidth - $watermarkWidth) / 2)),
                max(0, (int)floor(($imageHeight - $watermarkHeight) / 2)),
            ],
            'bottom-left' => [$marginX, max(0, $imageHeight - $watermarkHeight - $marginY)],
            default => [
                max(0, $imageWidth - $watermarkWidth - $marginX),
                max(0, $imageHeight - $watermarkHeight - $marginY),
            ],
        };
    }

    /**
     * Copies a watermark while reducing existing alpha by opacity.
     *
     * @since   1.0.0
     */
    private function copyWatermarkWithOpacity(
        \GdImage $image,
        \GdImage $watermark,
        int $x,
        int $y,
        int $opacity
    ): void {
        $width = imagesx($watermark);
        $height = imagesy($watermark);
        $copy = $this->createTransparentCanvas($width, $height);

        for ($pixelX = 0; $pixelX < $width; $pixelX++) {
            for ($pixelY = 0; $pixelY < $height; $pixelY++) {
                $rgba = imagecolorsforindex($watermark, imagecolorat($watermark, $pixelX, $pixelY));
                $alpha = 127 - (int)round((127 - $rgba['alpha']) * ($opacity / 100));
                $color = imagecolorallocatealpha($copy, $rgba['red'], $rgba['green'], $rgba['blue'], $alpha);

                imagesetpixel($copy, $pixelX, $pixelY, $color);
            }
        }

        imagecopy($image, $copy, $x, $y, 0, 0, $width, $height);
        imagedestroy($copy);
    }

    /**
     * Encodes the image to target format.
     *
     * @param   Image                 $image      Target image.
     * @param   string                $extension  Target extension.
     * @param   array<string, mixed>  $options    Processing options.
     *
     * @return  string
     *
     * @throws  \RuntimeException|\Throwable
     *
     * @since   1.0.0
     */
    private function encode(Image $image, string $extension, array $options): string
    {
        $outputImage = $extension === 'jpg' ? $this->flattenForJpeg($image) : $image;
        $quality = $extension === 'png'
            ? min(9, max(0, (int)($options['png_compression'] ?? 6)))
            : min(100, max(1, (int)($options['quality'] ?? 82)));

        ob_start();

        try {
            $success = $outputImage->toFile(null, $this->imageTypeFromExtension($extension), ['quality' => $quality]);
            $data = ob_get_clean();
        } catch (\Throwable $exception) {
            ob_end_clean();

            throw $exception;
        } finally {
            if ($outputImage !== $image) {
                $outputImage->destroy();
            }
        }

        if (!$success || !\is_string($data) || $data === '') {
            throw new \RuntimeException('Could not encode processed image.');
        }

        return $data;
    }

    /**
     * Maps a normalized extension to the GD image type expected by Joomla Image.
     *
     * @since   1.0.0
     */
    private function imageTypeFromExtension(string $extension): int
    {
        return match ($extension) {
            'avif' => IMAGETYPE_AVIF,
            'gif' => IMAGETYPE_GIF,
            'png' => IMAGETYPE_PNG,
            'webp' => IMAGETYPE_WEBP,
            default => IMAGETYPE_JPEG,
        };
    }

    /**
     * Flattens alpha onto a white background for JPEG output.
     *
     * @since   1.0.0
     */
    private function flattenForJpeg(Image $image): Image
    {
        $flattened = $image->resize($image->getWidth(), $image->getHeight(), true, Image::SCALE_FILL);
        $flattened->filter('backgroundfill', ['color' => '#FFFFFFFF']);

        return $flattened;
    }
}
