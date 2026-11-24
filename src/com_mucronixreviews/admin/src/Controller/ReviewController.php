<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  com_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Component\MucronixReviews\Administrator\Controller;

use Joomla\CMS\MVC\Controller\FormController;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Edits a review. A review cannot be created here: its author is a user who has sent it.
 *
 * @since  0.2.0
 */
class ReviewController extends FormController
{
    /**
     * The view to return to
     *
     * @var    string
     * @since  0.2.0
     */
    protected $view_list = 'reviews';

    /**
     * Nobody may add a review.
     *
     * @param   array  $data  An array of input data
     *
     * @return  boolean
     *
     * @since   0.2.0
     */
    protected function allowAdd($data = [])
    {
        return false;
    }
}
