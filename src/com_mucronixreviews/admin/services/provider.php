<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  com_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Mucronix\Component\MucronixReviews\Administrator\Extension\MucronixReviewsComponent;
use Mucronix\Component\MucronixReviews\Administrator\Service\ContextRegistry;
use Mucronix\Component\MucronixReviews\Administrator\Service\ReviewService;

/**
 * The service provider of the component.
 *
 * @since  0.1.0
 */
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
        $container->registerServiceProvider(new MVCFactory('\\Mucronix\\Component\\MucronixReviews'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\Mucronix\\Component\\MucronixReviews'));

        $container->set(
            ComponentInterface::class,
            function (Container $container) {
                $dispatcher = $container->get(DispatcherInterface::class);
                $contexts   = new ContextRegistry($dispatcher);
                $component  = new MucronixReviewsComponent($container->get(ComponentDispatcherFactoryInterface::class));

                $component->setMVCFactory($container->get(MVCFactoryInterface::class));
                $component->setContextRegistry($contexts);
                $component->setReviewService(new ReviewService($container->get(DatabaseInterface::class), $contexts, $dispatcher));

                return $component;
            }
        );
    }
};
