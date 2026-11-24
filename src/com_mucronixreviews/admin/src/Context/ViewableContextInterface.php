<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  com_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Component\MucronixReviews\Administrator\Context;

use Joomla\CMS\User\User;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * A type whose objects can be seen although they cannot be reviewed any more, an archived article for example.
 * Without it, what can be seen is what can be reviewed.
 *
 * @since  0.3.0
 */
interface ViewableContextInterface
{
    /**
     * Whether the user may see the object.
     *
     * @param   integer  $itemId  The id of the object
     * @param   User     $user    The user
     *
     * @return  boolean
     *
     * @since   0.3.0
     */
    public function isViewable(int $itemId, User $user): bool;
}
