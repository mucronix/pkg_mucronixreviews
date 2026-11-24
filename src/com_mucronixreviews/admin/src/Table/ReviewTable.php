<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  com_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Component\MucronixReviews\Administrator\Table;

use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\DispatcherInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * A review, for reading. What writes goes through the service, or the statistics would be wrong.
 *
 * @since  0.2.0
 */
class ReviewTable extends Table
{
    /**
     * Constructor
     *
     * @param   DatabaseInterface     $db          The database driver
     * @param   ?DispatcherInterface  $dispatcher  The event dispatcher
     *
     * @since   0.2.0
     */
    public function __construct(DatabaseInterface $db, ?DispatcherInterface $dispatcher = null)
    {
        parent::__construct('#__mucronixreviews_reviews', 'id', $db, $dispatcher);
    }

    /**
     * Not here.
     *
     * @param   boolean  $updateNulls  True to update fields even if they are null
     *
     * @return  boolean
     *
     * @throws  \LogicException
     *
     * @since   0.2.0
     */
    public function store($updateNulls = false)
    {
        throw new \LogicException('Reviews are stored by ReviewService');
    }

    /**
     * Not here.
     *
     * @param   mixed  $pk  The primary key
     *
     * @return  boolean
     *
     * @throws  \LogicException
     *
     * @since   0.2.0
     */
    public function delete($pk = null)
    {
        throw new \LogicException('Reviews are deleted by ReviewService');
    }
}
