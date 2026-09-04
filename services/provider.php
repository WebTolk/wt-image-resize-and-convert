<?php

/**
 * WT Image Resize and Convert service provider.
 *
 * @package       WT Image Resize and Convert
 * @subpackage    plg_media-action_wtimageresizeconvert
 * @author     WebTolk
 * @copyright  Copyright (c) 2026 WebTolk. All rights reserved.
 * @version       1.0.0
 * @license       GNU General Public License version 3 or later.
 * @link          https://web-tolk.ru
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Webtolk\Plugin\MediaAction\WtImageResizeConvert\Extension\WtImageResizeConvert;

return new class () implements ServiceProviderInterface {
    /**
     * Registers services with the DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            $container->lazy(WtImageResizeConvert::class, static function () {
                $plugin = new WtImageResizeConvert(
                    (array) PluginHelper::getPlugin('media-action', 'wtimageresizeconvert')
                );
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            })
        );
    }
};
