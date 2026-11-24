<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  plg_system_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Mucronix\Plugin\System\MucronixReviews\Extension\MucronixReviews;

return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            $container->lazy(MucronixReviews::class, function (Container $container) {
                $plugin = new MucronixReviews((array) PluginHelper::getPlugin('system', 'mucronixreviews'));
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            })
        );
    }
};
