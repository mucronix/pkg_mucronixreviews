<?php

namespace Mucronix\Plugin\Content\Mxrdemo\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * Two buttons: to create the reviews and to delete them.
 */
class DemobuttonsField extends FormField
{
    protected $type = 'Demobuttons';

    protected function getInput()
    {
        if (!PluginHelper::isEnabled('content', 'mxrdemo')) {
            return '<div class="alert alert-warning">Включите плагин и сохраните: кнопки появятся здесь.</div>';
        }

        $link = 'index.php?option=com_ajax&group=content&plugin=mxrdemo&format=raw&' . Session::getFormToken() . '=1&do=';

        return '<a class="btn btn-primary" href="' . Route::_($link . 'create') . '">Создать 30 тестовых отзывов</a> '
            . '<a class="btn btn-danger" href="' . Route::_($link . 'delete') . '">Удалить тестовые отзывы</a>';
    }
}
