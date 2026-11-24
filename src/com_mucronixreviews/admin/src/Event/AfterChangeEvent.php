<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  com_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Component\MucronixReviews\Administrator\Event;

use Joomla\Event\Event;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The event after reviews have changed, for those who keep what is made of them.
 *
 * @since  0.3.0
 */
class AfterChangeEvent extends Event
{
    /**
     * The name of the event
     *
     * @var    string
     * @since  0.3.0
     */
    public const NAME = 'onMucronixReviewsAfterChange';

    /**
     * Constructor
     *
     * @param   string[]  $contexts  The types of the objects whose reviews have changed
     *
     * @since   0.3.0
     */
    public function __construct(private readonly array $contexts)
    {
        parent::__construct(self::NAME);
    }

    /**
     * Returns the types of the objects whose reviews have changed.
     *
     * @return  string[]
     *
     * @since   0.3.0
     */
    public function getContexts(): array
    {
        return $this->contexts;
    }
}
