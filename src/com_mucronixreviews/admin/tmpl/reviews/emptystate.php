<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  com_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Layout\LayoutHelper;

/** @var \Mucronix\Component\MucronixReviews\Administrator\View\Reviews\HtmlView $this */

echo LayoutHelper::render(
    'joomla.content.emptystate',
    [
        'textPrefix'    => 'COM_MUCRONIXREVIEWS',
        'formURL'       => 'index.php?option=com_mucronixreviews&view=reviews',
        'icon'          => 'icon-star',
        'btnadd'        => '',
        'controlFields' => $this->filterForm->renderControlFields(),
    ]
);
