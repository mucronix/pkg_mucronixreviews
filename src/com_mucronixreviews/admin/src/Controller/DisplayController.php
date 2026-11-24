<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  com_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Component\MucronixReviews\Administrator\Controller;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The pages of the component.
 *
 * @since  0.1.0
 */
class DisplayController extends BaseController
{
    /**
     * The default view
     *
     * @var    string
     * @since  0.2.0
     */
    protected $default_view = 'reviews';

    /**
     * Shows a page.
     *
     * @param   boolean  $cachable   If true, the view output will be cached
     * @param   array    $urlparams  An array of safe URL parameters
     *
     * @return  static|boolean
     *
     * @since   0.1.0
     */
    public function display($cachable = false, $urlparams = [])
    {
        $id = $this->input->getInt('id');

        // The form opens by its task, which checks the rights
        if ($this->input->get('view') === 'review' && !$this->checkEditId('com_mucronixreviews.edit.review', $id)) {
            $this->setMessage(Text::sprintf('JLIB_APPLICATION_ERROR_UNHELD_ID', (int) $id), 'error');
            $this->setRedirect(Route::_('index.php?option=com_mucronixreviews&view=reviews', false));

            return false;
        }

        return parent::display($cachable, $urlparams);
    }
}
