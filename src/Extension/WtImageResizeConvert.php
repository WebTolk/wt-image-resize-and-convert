<?php

/**
 * WT Image Resize and Convert media-action plugin.
 *
 * @package       WT Image Resize and Convert
 * @subpackage    plg_media-action_wtimageresizeconvert
 * @author     WebTolk
 * @copyright  Copyright (c) 2026 WebTolk. All rights reserved.
 * @version       1.0.0
 * @license       GNU General Public License version 3 or later.
 * @link          https://web-tolk.ru
 */

namespace Webtolk\Plugin\MediaAction\WtImageResizeConvert\Extension;

use Joomla\CMS\Event\Model\BeforeSaveEvent;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Component\Media\Administrator\Plugin\MediaActionPlugin;
use Joomla\Event\SubscriberInterface;
use Joomla\Filesystem\Path;
use Webtolk\Plugin\MediaAction\WtImageResizeConvert\Service\FolderRuleMatcher;
use Webtolk\Plugin\MediaAction\WtImageResizeConvert\Service\ImageProcessorBackendInterface;
use Webtolk\Plugin\MediaAction\WtImageResizeConvert\Service\JoomlaCoreImageProcessor;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Processes uploaded Media Manager images before com_media persists them.
 *
 * @since  1.0.0
 */
final class WtImageResizeConvert extends MediaActionPlugin implements SubscriberInterface
{
    /**
     * Load the language file on instantiation.
     *
     * @var    boolean
     *
     * @since  1.0.0
     */
    protected $autoloadLanguage = true;

    /**
     * Available image processor classes keyed by plugin parameter value.
     *
     * @var    array<string, class-string<ImageProcessorBackendInterface>>
     *
     * @since  1.0.0
     */
    private const PROCESSOR_CLASSES = [
        'joomla-core' => JoomlaCoreImageProcessor::class,
    ];

    /**
     * Folder rule matcher service.
     *
     * @var    FolderRuleMatcher
     *
     * @since  1.0.0
     */
    private FolderRuleMatcher $folderRuleMatcher;

    /**
     * Constructor.
     *
     * @param   array  $config  Plugin configuration.
     *
     * @since   1.0.0
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        $this->folderRuleMatcher = new FolderRuleMatcher();
    }

    /**
     * Returns subscribed events.
     *
     * @return  array<string, string>
     *
     * @since   1.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return array_merge(parent::getSubscribedEvents(), [
            'onContentBeforeSave' => 'onContentBeforeSave',
        ]);
    }

    /**
     * Processes com_media file data before it is written by the media adapter.
     *
     * @param   BeforeSaveEvent  $event  The save event.
     *
     * @return  void
     *
     * @throws  \Throwable
     *
     * @since   1.0.0
     */
    public function onContentBeforeSave(BeforeSaveEvent $event): void
    {
        if (!(bool)$this->params->get('enabled', 1)) {
            return;
        }

        if ($event->getContext() !== 'com_media.file') {
            return;
        }

        $item = $event->getItem();

        if (!isset($item->data, $item->extension, $item->name) || !\is_string($item->data) || $item->data === '') {
            return;
        }

        $options = $this->buildProcessorOptions($item);
        $processor = $this->createProcessor((string) $options['processor_backend']);

        if ($processor === null || !$processor->isAvailable()) {
            return;
        }

        try {
            $result = $processor->process($item->data, (string) $item->extension, $options);
        } catch (\Throwable $exception) {
            if ((bool)$this->params->get('strict_mode', 0)) {
                throw $exception;
            }

            return;
        }

        if ($result === null) {
            return;
        }

        $item->data = $result['data'];
        $item->extension = $result['extension'];
        $item->name = pathinfo((string) $item->name, PATHINFO_FILENAME) . '.' . $result['extension'];
    }

    /**
     * Builds service options from plugin parameters.
     *
     * @return  array<string, mixed>
     *
     * @since   1.0.0
     */
    private function buildProcessorOptions(object $item): array
    {
        $profile = $this->getDefaultProfile();
        $profileId = $this->folderRuleMatcher->selectProfileId(
            $this->params->get('folder_rules', []),
            $this->folderRuleMatcher->resolveLocalFolder($item)
        );

        if ($profileId !== null && $profileId !== 'default') {
            $profiles = $this->getCustomProfiles();

            if (isset($profiles[$profileId])) {
                $profile = array_replace($profile, $profiles[$profileId]);
            }
        }

        return [
            'allow_upscale' => (bool) $profile['allow_upscale'],
            'image_fill_color' => (string) $profile['image_fill_color'],
            'max_height' => max(0, (int) $profile['max_height']),
            'max_width' => max(0, (int) $profile['max_width']),
            'mode' => (string) $profile['resize_mode'],
            'png_compression' => min(9, max(0, (int) $profile['png_compression'])),
            'processor_backend' => (string) $profile['processor_backend'],
            'quality' => min(100, max(1, (int) $profile['quality'])),
            'target_format' => (string) $profile['output_format'],
            'watermark_enabled' => (bool) $profile['watermark_enabled'],
            'watermark_margin_x' => max(0, (int) $profile['watermark_margin_x']),
            'watermark_margin_y' => max(0, (int) $profile['watermark_margin_y']),
            'watermark_opacity' => min(100, max(1, (int) $profile['watermark_opacity'])),
            'watermark_path' => $this->resolveWatermarkPath((string) $profile['watermark_path']),
            'watermark_position' => (string) $profile['watermark_position'],
            'watermark_scale' => min(100, max(1, (int) $profile['watermark_scale'])),
        ];
    }

    /**
     * Creates the processor selected by the effective profile.
     *
     * @param   string  $name  Processor name from plugin parameters.
     *
     * @return  ImageProcessorBackendInterface|null
     *
     * @since   1.0.0
     */
    private function createProcessor(string $name): ?ImageProcessorBackendInterface
    {
        $processorClass = self::PROCESSOR_CLASSES[strtolower(trim($name))] ?? null;

        return $processorClass === null ? null : new $processorClass();
    }

    /**
     * Returns the legacy flat parameters as the default processing profile.
     *
     * @return  array<string, mixed>
     *
     * @since   1.0.0
     */
    private function getDefaultProfile(): array
    {
        return [
            'allow_upscale' => $this->params->get('allow_upscale', 0),
            'image_fill_color' => $this->params->get('image_fill_color', '#ffffff'),
            'max_height' => $this->params->get('max_height', 1080),
            'max_width' => $this->params->get('max_width', 1920),
            'png_compression' => $this->params->get('png_compression', 6),
            'processor_backend' => $this->params->get('processor_backend', 'joomla-core'),
            'quality' => $this->params->get('quality', 82),
            'resize_mode' => $this->params->get('resize_mode', 'none'),
            'output_format' => $this->params->get('output_format', 'keep'),
            'watermark_enabled' => $this->params->get('watermark_enabled', 0),
            'watermark_margin_x' => $this->params->get('watermark_margin_x', 24),
            'watermark_margin_y' => $this->params->get('watermark_margin_y', 24),
            'watermark_opacity' => $this->params->get('watermark_opacity', 80),
            'watermark_path' => $this->params->get('watermark_path', ''),
            'watermark_position' => $this->params->get('watermark_position', 'bottom-right'),
            'watermark_scale' => $this->params->get('watermark_scale', 20),
        ];
    }

    /**
     * Returns valid custom processing profiles keyed by their saved identifier.
     *
     * @return  array<string, array<string, mixed>>
     *
     * @since   1.0.0
     */
    private function getCustomProfiles(): array
    {
        $profiles = $this->params->get('processing_profiles', []);

        if (!\is_array($profiles) && !\is_object($profiles)) {
            return [];
        }

        $available = [];

        foreach ($profiles as $profile) {
            if (\is_object($profile) && method_exists($profile, 'toArray')) {
                $profile = $profile->toArray();
            }

            if (\is_object($profile)) {
                $profile = (array) $profile;
            }

            if (!\is_array($profile) || !isset($profile['id']) || !\is_scalar($profile['id'])) {
                continue;
            }

            $id = trim((string) $profile['id']);

            if ($id === '' || $id === 'default' || isset($available[$id])) {
                continue;
            }

            $available[$id] = $profile;
        }

        return $available;
    }

    /**
     * Resolves a Joomla site-root-relative watermark path.
     *
     * @param   string  $path  Configured path.
     *
     * @return  string|null
     *
     * @since   1.0.0
     */
    private function resolveWatermarkPath(string $path): ?string
    {
        $path = trim(str_replace('\\', '/', (string) HTMLHelper::cleanImageURL($path)->url), '/');

        if ($path === '') {
            return null;
        }

        try {
            $filePath = Path::check(JPATH_SITE . '/' . $path, JPATH_SITE);
        } catch (\Throwable) {
            return null;
        }

        $root = realpath(JPATH_SITE);
        $file = realpath($filePath);

        if ($root === false || $file === false || !file_exists($file) || !is_readable($file)) {
            return null;
        }

        $root = rtrim(str_replace('\\', '/', $root), '/') . '/';
        $file = str_replace('\\', '/', $file);

        if (!str_starts_with($file, $root)) {
            return null;
        }

        return $file;
    }
}
