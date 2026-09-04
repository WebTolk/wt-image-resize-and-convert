<?php

/**
 * WT Image Resize and Convert processing profile selector field.
 *
 * @package       WT Image Resize and Convert
 * @subpackage    plg_media-action_wtimageresizeconvert
 * @author     WebTolk
 * @copyright  Copyright (c) 2026 WebTolk. All rights reserved.
 * @license       GNU General Public License version 3 or later.
 * @link          https://web-tolk.ru
 */

namespace Webtolk\Plugin\MediaAction\WtImageResizeConvert\Field;

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Lists saved processing profiles for a folder rule.
 *
 * Newly added profiles become selectable after saving and reloading the plugin form.
 *
 * @since  1.0.0
 */
final class FolderprofileField extends ListField
{
    /**
     * Field type.
     *
     * @var    string
     *
     * @since  1.0.0
     */
    protected $type = 'Folderprofile';

    /**
     * Returns the default profile and all saved custom profile choices.
     *
     * @return  array<int, object>
     *
     * @since   1.0.0
     */
    protected function getOptions(): array
    {
        $options = parent::getOptions();
        $options[] = HTMLHelper::_('select.option', '', Text::_('PLG_MEDIA-ACTION_WTIMAGERESIZECONVERT_FOLDER_RULE_PROFILE_SELECT'));
        $options[] = HTMLHelper::_('select.option', 'default', Text::_('PLG_MEDIA-ACTION_WTIMAGERESIZECONVERT_FOLDER_RULE_PROFILE_DEFAULT'));

        $plugin = PluginHelper::getPlugin('media-action', 'wtimageresizeconvert');

        if (!$plugin || !isset($plugin->params)) {
            return $options;
        }

        $params = new Registry($plugin->params ?? '');
        $profiles = $params->get('processing_profiles', []);

        $seen = ['default' => true];

        foreach ($profiles as $profile) {
            if (\is_object($profile)) {
                $profile = (array) $profile;
            }

            if (!\is_array($profile) || !isset($profile['id']) || !\is_scalar($profile['id'])) {
                continue;
            }

            $id = trim((string) $profile['id']);

            if ($id === '' || isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            $title = isset($profile['title']) && \is_scalar($profile['title']) ? trim((string) $profile['title']) : '';
            $options[] = HTMLHelper::_('select.option', $id, $title !== '' ? $title . ' (' . $id . ')' : $id);
        }

        return $options;
    }
}
