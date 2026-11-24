<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Mucronix\Plugin\Content\Mxrtest\Extension\Mxrtest;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            $container->lazy(Mxrtest::class, fn () => new Mxrtest((array) PluginHelper::getPlugin('content', 'mxrtest')))
        );
    }
};
