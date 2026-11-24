<?php

/**
 * @package     Mucronix Reviews
 * @subpackage  com_mucronixreviews
 *
 * @copyright   (C) 2026 Mucronix
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Mucronix\Component\MucronixReviews\Administrator\View\Review;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\User\UserFactoryInterface;
use Mucronix\Component\MucronixReviews\Administrator\Model\ReviewModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The form of a review.
 *
 * @since  0.2.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The form
     *
     * @var    Form
     * @since  0.2.0
     */
    protected $form;

    /**
     * The review
     *
     * @var    object
     * @since  0.2.0
     */
    protected $item;

    /**
     * What is reviewed
     *
     * @var    object
     * @since  0.2.0
     */
    protected $object;

    /**
     * The present username of the author. Null if the account is deleted.
     *
     * @var    string|null
     * @since  0.2.0
     */
    protected $account;

    /**
     * Shows the page.
     *
     * @param   string  $tpl  The name of the template file to parse
     *
     * @return  void
     *
     * @throws  \Exception  If there is no such review
     *
     * @since   0.2.0
     */
    public function display($tpl = null): void
    {
        /** @var ReviewModel $model */
        $model = $this->getModel();

        // Not with exceptions yet: the one for a missing record is a 500
        $this->item = $model->getItem();

        if (!$this->item || !$this->item->id) {
            throw new \Exception(Text::_('COM_MUCRONIXREVIEWS_ERROR_NOT_FOUND'), 404);
        }

        $model->setUseExceptions(true);

        $this->form   = $model->getForm();
        $this->object = Factory::getApplication()->bootComponent('com_mucronixreviews')->getContextRegistry()
            ->describe($this->item->context, (int) $this->item->item_id);

        $user          = $this->item->user_id ? Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById((int) $this->item->user_id) : null;
        $this->account = $user && $user->id ? $user->username : null;

        Factory::getApplication()->getInput()->set('hidemainmenu', true);

        ToolbarHelper::title(Text::_('COM_MUCRONIXREVIEWS_REVIEW_TITLE'), 'star');

        $toolbar = $this->getDocument()->getToolbar();

        $toolbar->apply('review.apply');
        $toolbar->save('review.save');
        $toolbar->cancel('review.cancel', 'JTOOLBAR_CLOSE');

        $this->form->addControlField('task');

        parent::display($tpl);
    }
}
