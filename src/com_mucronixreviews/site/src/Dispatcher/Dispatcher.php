<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  com_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Component\MucronixReviews\Site\Dispatcher;

use Joomla\CMS\Dispatcher\ComponentDispatcher;
use Joomla\CMS\Language\Text;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The site part takes reviews and gives more of them to a script. It has no pages.
 *
 * @since  0.1.0
 */
class Dispatcher extends ComponentDispatcher
{
    /**
     * The tasks of the site part
     *
     * @var    string[]
     * @since  0.1.0
     */
    private const TASKS = ['review.save', 'list.more'];

    /**
     * Dispatches what exists, anything else does not.
     *
     * @return  void
     *
     * @throws  \Exception
     *
     * @since   0.1.0
     */
    public function dispatch()
    {
        $task = $this->input->get('task', '', 'raw');

        if (!\in_array($task, self::TASKS, true)) {
            throw new \Exception(Text::_('JERROR_PAGE_NOT_FOUND'), 404);
        }

        parent::dispatch();
    }
}
