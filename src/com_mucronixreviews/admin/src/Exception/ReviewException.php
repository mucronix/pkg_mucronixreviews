<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  com_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Component\MucronixReviews\Administrator\Exception;

use Joomla\CMS\Language\Text;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Why a review is refused. The code is an HTTP status.
 *
 * @since  0.1.0
 */
class ReviewException extends \RuntimeException
{
    /**
     * Constructor
     *
     * @param   string   $reason     The reason, for scripts: "flood". The string of the text ends with it
     * @param   integer  $status     The HTTP status
     * @param   array    $arguments  The values for the text
     *
     * @since   0.1.0
     */
    public function __construct(private readonly string $reason, int $status, array $arguments = [])
    {
        $key = 'COM_MUCRONIXREVIEWS_ERROR_' . strtoupper($reason);

        parent::__construct($arguments ? Text::sprintf($key, ...$arguments) : Text::_($key), $status);
    }

    /**
     * Returns the reason.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public function getReason(): string
    {
        return $this->reason;
    }
}
